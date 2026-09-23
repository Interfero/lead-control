<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderCityView;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Просмотры карточки заказа сотрудниками филиала (senior_manager, branch_head).
 * Синхронизировать условия подсчёта «непросмотренных» с OrderController@index (текущий месяц, без фильтров формы).
 */
class OrderCityViewService
{
    /** Роли, при открытии карточки которыми создаётся запись просмотра. */
    public const CITY_STAFF_ROLES = ['senior_manager', 'branch_head'];

    /** Роли, которым показываются уведомления о новых заявках. */
    public const NOTIFY_ROLES = [
        'senior_manager',
        'branch_head',
        'regional_director',
        'general_director',
        'developer',
    ];

    public function __construct(
        private OrderVisibilityService $orderVisibility,
    ) {}

    public function recordFirstView(Order $order, User $user): void
    {
        if (! $user->hasAnyRole(self::CITY_STAFF_ROLES)) {
            return;
        }

        $order->loadMissing('address');
        if (! $order->address || ! $user->hasAccessToCity($order->address->city_id)) {
            return;
        }

        OrderCityView::firstOrCreate(
            [
                'order_id' => $order->order_id,
                'user_id' => $user->user_id,
            ],
            ['viewed_at' => now()]
        );
    }

    /**
     * Базовый запрос заказов как у списка по умолчанию: текущий месяц, без закрытых, видимые статусы.
     * Без параметров из query string (поиск, фильтры).
     */
    public function baseDefaultMonthOrdersQuery(User $user): Builder
    {
        $query = Order::query();

        $cityIds = $user->cityIdsForOrdersFilter();
        if ($cityIds !== null) {
            if ($cityIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereHas('address', fn ($q) => $q->whereIn('city_id', $cityIds));
            }
        }

        $visibleStatuses = $this->orderVisibility->getVisibleStatuses($user);
        if (! empty($visibleStatuses)) {
            $query->whereIn('order_status', $visibleStatuses);
        }

        $closedStatuses = ['completed', 'cancelled_cc', 'cancelled_city'];
        $query->whereNotIn('order_status', $closedStatuses);

        // Окно как у списка по умолчанию: по дате создания/встречи текущего месяца
        $dateFrom = now()->startOfMonth()->format('Y-m-d');
        $dateTo = now()->endOfMonth()->format('Y-m-d');
        $query->whereRaw('COALESCE(datetime_order, order_created_at) >= ?', [$dateFrom]);
        $query->whereRaw('COALESCE(datetime_order, order_created_at) <= ?', [$dateTo.' 23:59:59']);

        return $query;
    }

    /**
     * Заказы в дефолтном окне списка, которые ни разу не открывали senior_manager / branch_head.
     */
    public function countUnseenByCityStaff(User $user): int
    {
        if (! $user->hasAnyRole(self::NOTIFY_ROLES)) {
            return 0;
        }

        return (int) $this->baseDefaultMonthOrdersQuery($user)
            ->whereDoesntHave('cityViews')
            ->count();
    }

    /**
     * ID таких же «непросмотренных филиалом» заявок (для клиентского diff и текста уведомлений).
     * Ограничение по числу записей — достаточно для сравнения между опросами.
     *
     * @return array<int, int>
     */
    public function getUnseenCityOrderIds(User $user): array
    {
        if (! $user->hasAnyRole(self::NOTIFY_ROLES)) {
            return [];
        }

        return $this->baseDefaultMonthOrdersQuery($user)
            ->whereDoesntHave('cityViews')
            ->orderByDesc('order_id')
            ->limit(500)
            ->pluck('order_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Строки для блока «Просмотр филиалом» на карточке заказа.
     *
     * @return array<int, array{name: string, role_label: string, at: string}>
     */
    public function getViewLinesForOrderCard(Order $order): array
    {
        $views = $order->cityViews()
            ->with(['user.roles'])
            ->orderBy('viewed_at')
            ->get();

        $lines = [];
        foreach ($views as $view) {
            $user = $view->user;
            if (! $user) {
                continue;
            }

            $role = $user->roles->first(fn ($r) => in_array($r->role_code, self::CITY_STAFF_ROLES, true));
            if (! $role) {
                continue;
            }

            $lines[] = [
                'name' => $user->user_name,
                'role_label' => $role->role_name,
                'at' => $view->viewed_at->format('d.m.Y H:i'),
            ];
        }

        return $lines;
    }
}
