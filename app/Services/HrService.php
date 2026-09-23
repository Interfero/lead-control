<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\MasterSchedule;
use App\Models\City;
use App\Models\Order;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class HrService
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    /**
     * Гарантировать наличие служебных ролей в БД (если миграция ещё не накатывалась).
     */
    public function ensureBuiltinRoles(): void
    {
        $builtin = [
            [
                'role_code' => 'senior_dispatcher',
                'role_name' => 'Старший диспетчер',
                'role_description' => 'Контроль заказов, закрытых заявок, документов и отчёт за 7 дней',
            ],
        ];

        foreach ($builtin as $data) {
            Role::updateOrCreate(
                ['role_code' => $data['role_code']],
                [
                    'role_name' => $data['role_name'],
                    'role_description' => $data['role_description'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Получить сотрудников с учётом видимости по роли и городам
     */
    public function getEmployeesForUser(User $currentUser, array $filters = [])
    {
        $visibleRoles = $this->getVisibleRoleCodesForUser($currentUser);

        $query = User::with(['roles', 'cities', 'blacklistedByUser']);

        // Allowlist ролей: все роли сотрудника должны быть из списка (иначе дисп+мастер и т.п. протекут)
        if ($visibleRoles !== null) {
            if ($visibleRoles === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('roles')
                    ->whereDoesntHave('roles', fn ($q) => $q->whereNotIn('role_code', $visibleRoles));
            }
        }

        // При фильтре «Только чёрный список» для developer/general_director не ограничиваем по городам
        $skipCityFilterForBlacklist = ! empty($filters['show_blacklisted'])
            && $currentUser->hasAnyRole(['developer', 'general_director']);

        $skipCityFilter = $skipCityFilterForBlacklist
            || $this->isCallCenterSupervisor($currentUser);

        // Только сотрудники пересекающихся городов (КЦ-супервизор видит всех диспетчеров КЦ без городов)
        if (! $currentUser->hasAnyRole(['developer', 'general_director']) && ! $skipCityFilter) {
            $userCityIds = $currentUser->cities->pluck('city_id')->map(fn ($id) => (int) $id)->all();
            if ($userCityIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('cities', fn ($q2) => $q2->whereIn('cities.city_id', $userCityIds));
            }
        }

        // Фильтры (столбцы как на странице заказов)
        if (! empty($filters['search_id'])) {
            $sid = (int) $filters['search_id'];
            if ($sid > 0) {
                $query->where('user_id', $sid);
            }
        }
        $roleCodes = $filters['role'] ?? [];
        if (is_array($roleCodes) && count($roleCodes) > 0) {
            $allowedFilterRoles = $visibleRoles === null
                ? $roleCodes
                : array_values(array_intersect($roleCodes, $visibleRoles));
            if ($allowedFilterRoles === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('roles', fn ($q) => $q->whereIn('role_code', $allowedFilterRoles));
            }
        }
        if (! empty($filters['city_id'])) {
            $cityIds = is_array($filters['city_id']) ? $filters['city_id'] : [$filters['city_id']];
            $cityIds = array_values(array_filter(array_map('intval', $cityIds)));
            if ($cityIds !== []) {
                if (
                    ! $currentUser->hasAnyRole(['developer', 'general_director'])
                    && ! $this->isCallCenterSupervisor($currentUser)
                ) {
                    $viewerCities = $currentUser->cities->pluck('city_id')->map(fn ($id) => (int) $id)->all();
                    $cityIds = array_values(array_intersect($cityIds, $viewerCities));
                }
                if ($cityIds === []) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereHas('cities', fn ($q2) => $q2->whereIn('cities.city_id', $cityIds));
                }
            }
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'LIKE', "%{$search}%")
                    ->orWhere('user_passport', 'LIKE', "%{$search}%");
            });
        }
        if (! empty($filters['search_phone'])) {
            $digits = preg_replace('/\D+/', '', (string) $filters['search_phone']);
            if ($digits !== '') {
                $query->where('user_phone', 'LIKE', "%{$digits}%");
            }
        }
        if (! empty($filters['search_note'])) {
            $note = $filters['search_note'];
            $query->where('user_note', 'LIKE', "%{$note}%");
        }

        // Чёрный список: все записи в ЧС, в т.ч. уволенных (не режем по is_active)
        if (! empty($filters['show_blacklisted'])) {
            $query->where('is_blacklisted', true);
        } elseif (! ($filters['show_fired'] ?? false)) {
            $query->where('is_active', true)->whereNull('user_fired_at');
        }

        return $query->orderBy('user_name')->paginate(15);
    }

    /**
     * Роли, которые пользователь может СОЗДАВАТЬ (назначать новым сотрудникам)
     */
    public function getCreatableRolesForUser(User $user): array
    {
        if ($user->hasAnyRole(['developer', 'general_director'])) {
            return []; // пустой массив = без ограничений (все видимые роли)
        }

        if ($user->hasRole('regional_director')) {
            return ['branch_head', 'tech_director', 'senior_manager', 'master'];
        }

        if ($user->hasRole('branch_head')) {
            return ['master'];
        }

        // КЦ, менеджер, тех.дир создавать не могут — вернём пустой запрет-список
        // (доступ к route уже закрыт через middleware, но на всякий случай)
        return ['__none__'];
    }

    /**
     * Проверить, может ли пользователь создать сотрудника с заданной ролью
     */
    public function canCreateWithRole(User $user, string $roleCode): bool
    {
        if ($user->hasAnyRole(['developer', 'general_director'])) {
            return true;
        }

        $allowed = $this->getCreatableRolesForUser($user);

        return in_array($roleCode, $allowed, true);
    }

    /**
     * Роли, которые текущий пользователь видит в разделе Сотрудники.
     * null = без ограничений (гендир / разработчик).
     *
     * @return list<string>|null
     */
    public function getVisibleRoleCodesForUser(User $user): ?array
    {
        if ($user->hasAnyRole(['developer', 'general_director'])) {
            return null;
        }

        // Старший диспетчер: только назначенные супервизоры КЦ (Иса) видят учётки call_center
        if ($user->hasRole('senior_dispatcher')) {
            return $this->isCallCenterSupervisor($user) ? ['call_center'] : [];
        }

        // Региональный: директора филиалов, техдиры, менеджеры, мастера по своим городам
        if ($user->hasRole('regional_director')) {
            return ['branch_head', 'tech_director', 'senior_manager', 'order_manager', 'master'];
        }

        // Руководитель филиала: команда филиала (без других директоров филиалов и без КЦ)
        if ($user->hasRole('branch_head')) {
            return ['tech_director', 'senior_manager', 'order_manager', 'master'];
        }

        // Тех. директор: мастера и менеджеры + руководитель филиала
        if ($user->hasRole('tech_director')) {
            return ['branch_head', 'senior_manager', 'order_manager', 'master'];
        }

        // Менеджер филиала / прочие — только мастера
        return ['master'];
    }

    /**
     * Супервизор КЦ: роль senior_dispatcher и (опционально) user_id из config/hr.php.
     * Диспетчеры КЦ сюда не входят — у них нет раздела «Сотрудники».
     */
    public function isCallCenterSupervisor(User $user): bool
    {
        if (! $user->hasRole('senior_dispatcher')) {
            return false;
        }

        $ids = config('hr.cc_supervisor_user_ids', []);
        if (! is_array($ids) || $ids === []) {
            return true;
        }

        return in_array((int) $user->user_id, array_map('intval', $ids), true);
    }

    /**
     * Получить скрытые роли для фильтров/форм (все активные минус видимые).
     *
     * @return list<string>
     */
    public function getHiddenRolesForUser(User $user): array
    {
        $visible = $this->getVisibleRoleCodesForUser($user);
        if ($visible === null) {
            return [];
        }

        return Role::query()
            ->where('is_active', true)
            ->whereNotIn('role_code', $visible)
            ->pluck('role_code')
            ->all();
    }

    /**
     * @deprecated используйте getHiddenRolesForUser / getVisibleRoleCodesForUser
     */
    public function getVisibleRolesForUser(User $user): array
    {
        return $this->getHiddenRolesForUser($user);
    }

    /**
     * Можно ли смотреть/править карточку сотрудника (роль + город).
     */
    public function canViewEmployee(User $viewer, User $employee): bool
    {
        if ($viewer->hasAnyRole(['developer', 'general_director'])) {
            return true;
        }

        $employee->loadMissing(['roles', 'cities']);
        $visibleRoles = $this->getVisibleRoleCodesForUser($viewer);
        if ($visibleRoles === null) {
            return true;
        }

        $employeeRoles = $employee->roles->pluck('role_code')->all();
        if ($employeeRoles === []) {
            return false;
        }
        foreach ($employeeRoles as $code) {
            if (! in_array($code, $visibleRoles, true)) {
                return false;
            }
        }

        // КЦ без привязки к филиалам — супервизор видит всех диспетчеров КЦ
        if ($this->isCallCenterSupervisor($viewer)) {
            return true;
        }

        $viewerCities = $viewer->cities->pluck('city_id')->map(fn ($id) => (int) $id)->all();
        if ($viewerCities === []) {
            return false;
        }

        $employeeCities = $employee->cities->pluck('city_id')->map(fn ($id) => (int) $id)->all();

        return count(array_intersect($viewerCities, $employeeCities)) > 0;
    }
    /**
     * Рейтинг мастеров
     */
    public function getMastersRating(array $cityIds = [], ?Carbon $dateFrom = null, ?Carbon $dateTo = null, bool $showUnder10 = false): Collection
    {
        $query = User::whereHas('roles', fn($q) => $q->where('role_code', 'master'))
            ->where('is_active', true)
            ->whereNull('user_fired_at');
        
        if (!empty($cityIds)) {
            $query->whereHas('cities', fn($q) => $q->whereIn('cities.city_id', $cityIds));
        }
        
        $masters = $query->with('cities')->get();
        
        $result = $masters->map(function ($master) use ($dateFrom, $dateTo) {
            return $this->calculateMasterStats($master->user_id, $master, $dateFrom, $dateTo);
        });
        
        // Фильтрация по порогу (если галочка не включена)
        if (!$showUnder10) {
            $result = $result->filter(fn($stats) => $stats['completed_count'] >= 10);
        }
        
        return $result->sortByDesc('completed_sum')->values();
    }

    /**
     * Оборот мастеров (закрытые заказы по дате проведения).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getMastersTurnover(
        array $cityIds,
        Carbon $from,
        Carbon $to,
        bool $showFired = false,
    ): Collection {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->where('role_code', 'master'))
            ->with('cities');

        if (! $showFired) {
            $query->where('is_active', true)->whereNull('user_fired_at');
        }

        if ($cityIds !== []) {
            $query->whereHas('cities', fn ($q) => $q->whereIn('cities.city_id', $cityIds));
        }

        $masters = $query->orderBy('user_name')->get();
        if ($masters->isEmpty()) {
            return collect();
        }

        $masterIds = $masters->pluck('user_id')->all();

        // Один SQL-агрегат вместо N× Order::get() (ТЗ §6.2)
        $aggQuery = Order::query()
            ->from('orders')
            ->whereIn('orders.master_id', $masterIds)
            ->where('orders.order_status', 'completed')
            ->whereNotNull('orders.order_closed_at')
            ->where('orders.order_closed_at', '>=', $from)
            ->where('orders.order_closed_at', '<=', $to);

        if ($cityIds !== []) {
            $aggQuery
                ->join('addresses', 'addresses.address_id', '=', 'orders.address_id')
                ->whereIn('addresses.city_id', $cityIds);
        }

        $agg = $aggQuery
            ->selectRaw('
                orders.master_id as master_id,
                COUNT(*) as completed_count,
                COALESCE(SUM(orders.amount_paid - orders.amount_comp), 0) as turnover
            ')
            ->groupBy('orders.master_id')
            ->get()
            ->keyBy('master_id');

        return $masters
            ->map(function (User $master) use ($agg) {
                $row = $agg->get($master->user_id);
                $completedCount = (int) ($row->completed_count ?? 0);
                if ($completedCount === 0) {
                    return null;
                }
                $turnover = (int) ($row->turnover ?? 0);

                return [
                    'user_id' => $master->user_id,
                    'user_name' => $master->user_name,
                    'cities' => $master->cities->pluck('city_name')->join(', ') ?: '—',
                    'is_fired' => (bool) $master->user_fired_at,
                    'completed_count' => $completedCount,
                    'turnover' => $turnover,
                    'percent_4' => (int) round($turnover * 0.04),
                    'total_turnover_temp' => 0,
                    'percent_4_checks' => 0,
                ];
            })
            ->filter()
            ->sortByDesc('turnover')
            ->values();
    }

    /**
     * Сводка по городу для отчёта «Оборот мастеров».
     *
     * @return array<string, mixed>
     */
    public function getCityTurnoverSummary(int $cityId, Carbon $from, Carbon $to): array
    {
        $city = City::findOrFail($cityId);
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $agg = Order::query()
            ->from('orders')
            ->join('addresses', 'addresses.address_id', '=', 'orders.address_id')
            ->where('addresses.city_id', $cityId)
            ->where('orders.order_status', 'completed')
            ->whereNotNull('orders.order_closed_at')
            ->where('orders.order_closed_at', '>=', $from)
            ->where('orders.order_closed_at', '<=', $to)
            ->selectRaw('
                COUNT(*) as completed_count,
                COALESCE(SUM(orders.amount_paid), 0) as turnover,
                COALESCE(SUM(orders.amount_paid - orders.amount_comp), 0) as net_sum,
                SUM(CASE WHEN (orders.amount_paid - orders.amount_comp) < 4500 THEN 1 ELSE 0 END) as low_orders,
                SUM(CASE WHEN (orders.amount_paid - orders.amount_comp) > 10500 THEN 1 ELSE 0 END) as high_orders
            ')
            ->first();

        $count = (int) ($agg->completed_count ?? 0);
        $turnover = (int) ($agg->turnover ?? 0);
        $netSum = (int) ($agg->net_sum ?? 0);
        $lowOrders = (int) ($agg->low_orders ?? 0);
        $highOrders = (int) ($agg->high_orders ?? 0);

        $salary = 0;
        if ($count > 0) {
            $completed = Order::query()
                ->with(['address.city.parentCity', 'persons.addresses.city'])
                ->where('order_status', 'completed')
                ->whereNotNull('order_closed_at')
                ->where('order_closed_at', '>=', $from)
                ->where('order_closed_at', '<=', $to)
                ->whereHas('address', fn ($q) => $q->where('city_id', $cityId))
                ->get();

            foreach ($completed as $order) {
                $salary += $this->calculateMasterSalary($order);
            }
        }

        return [
            'city_id' => $cityId,
            'city_name' => $city->city_name,
            'completed_count' => $count,
            'turnover' => $turnover,
            'net_sum' => $netSum,
            'avg_check' => $count > 0 ? (int) round($turnover / $count) : 0,
            'net_avg_check' => $count > 0 ? (int) round($netSum / $count) : 0,
            'salary' => $salary,
            'low_orders' => $lowOrders,
            'high_orders' => $highOrders,
        ];
    }

    /**
     * Расчёт статистики мастера или города
     */
    private function calculateMasterStats(int $masterId, $master, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $ordersQuery = Order::where('master_id', $masterId);

        if ($dateFrom) {
            $ordersQuery->where('order_created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $ordersQuery->where('order_created_at', '<=', $dateTo->endOfDay());
        }

        $allOrders = (clone $ordersQuery)->count();
        $completed = (clone $ordersQuery)->where('order_status', 'completed')->get();
        $completedCount = $completed->count();

        $completedSum = 0;
        $completedProfileCount = 0;
        $completedProfileSum = 0;
        $completedNonProfileCount = 0;
        $completedNonProfileSum = 0;
        $lowOrders = 0;
        $highOrders = 0;
        $lowProfile = 0;
        $highProfile = 0;
        $lowNonProfile = 0;
        $highNonProfile = 0;
        $salaryTotal = 0;
        $salaryProfile = 0;
        $salaryNonProfile = 0;

        foreach ($completed as $order) {
            $netAmount = $order->amount_paid - $order->amount_comp;
            $salary = $this->calculateMasterSalary($order);

            $completedSum += $netAmount;
            $salaryTotal += $salary;

            if ($order->order_core === 'core') {
                $completedProfileCount++;
                $completedProfileSum += $netAmount;
                $salaryProfile += $salary;

                if ($netAmount < 4500) {
                    $lowProfile++;
                }
                if ($netAmount > 10500) {
                    $highProfile++;
                }
            } else {
                $completedNonProfileCount++;
                $completedNonProfileSum += $netAmount;
                $salaryNonProfile += $salary;

                if ($netAmount < 4500) {
                    $lowNonProfile++;
                }
                if ($netAmount > 10500) {
                    $highNonProfile++;
                }
            }

            if ($netAmount < 4500) {
                $lowOrders++;
            }
            if ($netAmount > 10500) {
                $highOrders++;
            }
        }

        $inProgressCount = (clone $ordersQuery)->whereNotIn('order_status', ['completed', 'cancelled_cc', 'cancelled_city'])->count();

        $avgCheck = $completedCount > 0 ? round($completedSum / $completedCount) : 0;
        $avgCheckProfile = $completedProfileCount > 0 ? round($completedProfileSum / $completedProfileCount) : 0;
        $avgCheckNonProfile = $completedNonProfileCount > 0 ? round($completedNonProfileSum / $completedNonProfileCount) : 0;

        return [
            'user_id' => $master->user_id ?? null,
            'user_name' => $master->user_name ?? $master->city_name,
            'cities' => $master->cities ? $master->cities->pluck('city_name')->join(', ') : $master->city_name,
            'city_ids' => $master->cities ? $master->cities->pluck('city_id')->toArray() : [$master->city_id],
            'all_orders' => $allOrders,
            'completed_count' => $completedCount,
            'in_progress_count' => $inProgressCount,
            'completed_sum' => $completedSum,
            'completed_sum_profile' => $completedProfileSum,
            'completed_sum_non_profile' => $completedNonProfileSum,
            'avg_check' => $avgCheck,
            'avg_check_profile' => $avgCheckProfile,
            'avg_check_non_profile' => $avgCheckNonProfile,
            'low_orders' => $lowOrders,
            'high_orders' => $highOrders,
            'low_profile' => $lowProfile,
            'high_profile' => $highProfile,
            'low_non_profile' => $lowNonProfile,
            'high_non_profile' => $highNonProfile,
            'completed_profile' => $completedProfileCount,
            'completed_non_profile' => $completedNonProfileCount,
            'salary' => $salaryTotal,
            'salary_profile' => $salaryProfile,
            'salary_non_profile' => $salaryNonProfile,
        ];
    }

    /**
     * Расчёт зарплаты мастера
     */
    private function calculateMasterSalary(Order $order): int
    {
        return $this->orderService->calculateMasterSalary($order);
    }
    
    /**
     * Сводка по городу (для рейтинга)
     */
    public function getCitySummary(int $cityId, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $city = City::find($cityId);
        
        $ordersQuery = Order::whereHas('address', fn($q) => $q->where('city_id', $cityId));
        
        if ($dateFrom) {
            $ordersQuery->where('order_created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $ordersQuery->where('order_created_at', '<=', $dateTo->endOfDay());
        }
        
        // Все заказы
        $allOrders = (clone $ordersQuery)->count();
        
        // Закрытые (completed)
        $completed = (clone $ordersQuery)->where('order_status', 'completed')->get();
        $completedCount = $completed->count();
        
        // Инициализация переменных
        $completedSum = 0;
        $completedProfileCount = 0;
        $completedProfileSum = 0;
        $completedNonProfileCount = 0;
        $completedNonProfileSum = 0;
        $lowOrders = 0;
        $highOrders = 0;
        $lowProfile = 0;
        $highProfile = 0;
        $lowNonProfile = 0;
        $highNonProfile = 0;
        $salaryTotal = 0;
        $salaryProfile = 0;
        $salaryNonProfile = 0;
        
        // Обрабатываем каждый закрытый заказ
        foreach ($completed as $order) {
            $netAmount = $order->amount_paid - $order->amount_comp;
            $salary = $this->calculateMasterSalary($order);
            
            $completedSum += $netAmount;
            $salaryTotal += $salary;
            
            // Профильные (core) vs Непрофильные (non_core)
            if ($order->order_core === 'core') {
                $completedProfileCount++;
                $completedProfileSum += $netAmount;
                $salaryProfile += $salary;
                
                if ($netAmount < 4500) $lowProfile++;
                if ($netAmount > 10500) $highProfile++;
            } else {
                $completedNonProfileCount++;
                $completedNonProfileSum += $netAmount;
                $salaryNonProfile += $salary;
                
                if ($netAmount < 4500) $lowNonProfile++;
                if ($netAmount > 10500) $highNonProfile++;
            }
            
            // Общие Low/High
            if ($netAmount < 4500) $lowOrders++;
            if ($netAmount > 10500) $highOrders++;
        }
        
        // В работе
        $inProgressCount = (clone $ordersQuery)->whereNotIn('order_status', ['completed', 'cancelled_cc', 'cancelled_city'])->count();
        
        // Средние чеки
        $avgCheck = $completedCount > 0 ? round($completedSum / $completedCount) : 0;
        $avgCheckProfile = $completedProfileCount > 0 ? round($completedProfileSum / $completedProfileCount) : 0;
        $avgCheckNonProfile = $completedNonProfileCount > 0 ? round($completedNonProfileSum / $completedNonProfileCount) : 0;
        
        return [
            'city_id' => $cityId,
            'city_name' => $city->city_name,
            
            // Количество заказов
            'all_orders' => $allOrders,
            'completed_count' => $completedCount,
            'in_progress_count' => $inProgressCount,
            
            // Выручка
            'completed_sum' => $completedSum,
            'completed_sum_profile' => $completedProfileSum,
            'completed_sum_non_profile' => $completedNonProfileSum,
            
            // Средние чеки
            'avg_check' => $avgCheck,
            'avg_check_profile' => $avgCheckProfile,
            'avg_check_non_profile' => $avgCheckNonProfile,
            
            // Low/High
            'low_orders' => $lowOrders,
            'high_orders' => $highOrders,
            'low_profile' => $lowProfile,
            'high_profile' => $highProfile,
            'low_non_profile' => $lowNonProfile,
            'high_non_profile' => $highNonProfile,
            
            // Профиль/непрофиль
            'completed_profile' => $completedProfileCount,
            'completed_non_profile' => $completedNonProfileCount,
            
            // Зарплата
            'salary' => $salaryTotal,
            'salary_profile' => $salaryProfile,
            'salary_non_profile' => $salaryNonProfile,
        ];
    }
    
    /**
     * График мастеров на неделю
     */
    public function getMastersSchedule(array $cityIds, Carbon $startDate): array
    {
        $endDate = $startDate->copy()->addDays(6);
        
        $mastersQuery = User::whereHas('roles', fn($q) => $q->where('role_code', 'master'))
            ->where('is_active', true)
            ->whereNull('user_fired_at');
        
        if (!empty($cityIds)) {
            $mastersQuery->whereHas('cities', fn($q) => $q->whereIn('cities.city_id', $cityIds));
        }
        
        $masters = $mastersQuery->with('cities')->orderBy('user_name')->get();
        
        $schedules = MasterSchedule::whereBetween('schedule_date', [$startDate, $endDate])
            ->whereIn('user_id', $masters->pluck('user_id'))
            ->get()
            ->keyBy(fn($s) => "{$s->user_id}_{$s->schedule_date->format('Y-m-d')}");
        
        $result = [];
        $dailyTotals = array_fill(0, 7, 0);
        
        foreach ($masters as $master) {
            $row = [
                'user_id' => $master->user_id,
                'user_name' => $master->user_name,
                'cities' => $master->cities->pluck('city_name')->join(', '),
                'days' => [],
            ];
            
            for ($i = 0; $i < 7; $i++) {
                $date = $startDate->copy()->addDays($i);
                $key = "{$master->user_id}_{$date->format('Y-m-d')}";
                $schedule = $schedules->get($key);
                
                $isWorking = $schedule?->is_working ?? true;
                
                $row['days'][] = [
                    'date' => $date->format('Y-m-d'),
                    'day_name' => $date->locale('ru')->isoFormat('dd'),
                    'day_number' => $date->format('d'),
                    'is_working' => $isWorking,
                    'note' => $schedule?->schedule_note,
                ];
                
                if ($isWorking) {
                    $dailyTotals[$i]++;
                }
            }
            
            $result[] = $row;
        }
        
        return [
            'masters' => $result,
            'daily_totals' => $dailyTotals,
            'week_dates' => collect(range(0, 6))->map(fn($i) => [
                'date' => $startDate->copy()->addDays($i)->format('Y-m-d'),
                'day_name' => $startDate->copy()->addDays($i)->locale('ru')->isoFormat('dd'),
                'day_number' => $startDate->copy()->addDays($i)->format('d'),
                'month' => $startDate->copy()->addDays($i)->locale('ru')->isoFormat('MMM'),
            ])->toArray(),
        ];
    }
    
    /**
     * Обновление графика
     */
    public function updateSchedule(int $userId, string $date, bool $isWorking, ?string $note = null): void
    {
        // Нормализуем дату
        $dateObj = Carbon::parse($date);
        $dateStr = $dateObj->format('Y-m-d');
        
        // Ищем существующую запись
        $schedule = MasterSchedule::where('user_id', $userId)
            ->whereDate('schedule_date', $dateStr)
            ->first();
        
        // Подготавливаем данные для обновления/создания
        $data = [
            'is_working' => $isWorking,
            'schedule_note' => ($note !== null && $note !== '') ? $note : null,
        ];
        
        if ($schedule) {
            // Обновляем существующую запись
            $schedule->update($data);
        } else {
            // Создаем новую запись
            MasterSchedule::create([
                'user_id' => $userId,
                'schedule_date' => $dateStr,
                'is_working' => $isWorking,
                'schedule_note' => ($note !== null && $note !== '') ? $note : null,
            ]);
        }
    }
    
    /**
     * Генерация пароля
     */
    public function generatePassword(int $length = 8): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    /**
     * Проверка чёрного списка при создании сотрудника (телефон, паспорт, ФИО+дата рождения, email).
     * Возвращает сообщение об ошибке или null, если совпадений нет.
     */
    public function isBlacklistedForCreate(array $data): ?string
    {
        // Телефон (нормализуем до 10 цифр)
        if (!empty($data['user_phone'])) {
            $phone = preg_replace('/\D/', '', $data['user_phone']);
            if (strlen($phone) === 11 && in_array($phone[0], ['7', '8'])) {
                $phone = substr($phone, 1);
            }
            $phone = substr($phone, 0, 10);
            if (strlen($phone) === 10 && User::where('is_blacklisted', true)->where('user_phone', $phone)->exists()) {
                return 'Сотрудник с таким телефоном уже в чёрном списке';
            }
        }
        
        // Паспорт (серия + номер, нормализуем пробелы)
        if (!empty($data['user_passport'])) {
            $passport = trim(preg_replace('/\s+/', ' ', $data['user_passport']));
            if (User::where('is_blacklisted', true)->where('user_passport', $passport)->exists()) {
                return 'Сотрудник с таким паспортом уже в чёрном списке';
            }
            $passportNoSpaces = preg_replace('/\s+/', '', $passport);
            if ($passportNoSpaces !== $passport && User::where('is_blacklisted', true)->whereRaw("REPLACE(REPLACE(COALESCE(user_passport,''), ' ', ''), '\t', '') = ?", [$passportNoSpaces])->exists()) {
                return 'Сотрудник с таким паспортом уже в чёрном списке';
            }
        }
        
        // ФИО + дата рождения
        if (!empty($data['user_name']) && !empty($data['user_birth_date'])) {
            if (User::where('is_blacklisted', true)
                ->where('user_name', $data['user_name'])
                ->whereDate('user_birth_date', $data['user_birth_date'])
                ->exists()) {
                return 'Сотрудник с таким ФИО и датой рождения уже в чёрном списке';
            }
        }
        
        // Email
        if (!empty($data['email'])) {
            if (User::where('is_blacklisted', true)->where('email', $data['email'])->exists()) {
                return 'Сотрудник с таким email добавлен в черный список';
            }
        }
        
        return null;
    }
    
}
