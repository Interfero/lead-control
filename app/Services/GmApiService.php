<?php

namespace App\Services;

use App\Models\City;
use App\Models\CfmOperation;
use App\Models\Complaint;
use App\Models\Document;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Exceptions\HubOrderConflictException;
use App\Exceptions\HubSourceUnavailableException;
use App\Services\Hub\HubOrderPresenter;
use App\Services\Hub\HubOrderRepository;
use App\Services\Hub\HubOrderWriteService;
use App\Support\Hub\HubStatusMap;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class GmApiService
{
    private const MAX_FILES_PER_ORDER_CATEGORY = 20;

    /** Из этих статусов мастер закрывает заявку через POST …/review сразу в «Готов». */
    private const CLOSE_FROM_STATUSES = ['in_progress', 'in_progress_sd', 'review'];

    /** bcrypt of "password" — только для выравнивания времени при неизвестном пользователе */
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    /** Заявки CRM1/Lead Control. */
    public const SOURCE_LC = 'lc';

    public function __construct(
        private OrderService $orderService,
        private GmUserSyncService $gmUserSyncService,
        private OrderActivityLogService $orderActivityLogService,
        private HubOrderRepository $hubOrders,
        private HubOrderWriteService $hubWrite,
    ) {}

    public function resolveUser(Request $request): User
    {
        $userId = trim((string) ($request->header('X-GM-User-Id', $request->query('userId', ''))));
        $login = trim((string) ($request->header('X-GM-Login', $request->query('login', ''))));
        $email = trim((string) ($request->header('X-GM-Email', $request->query('email', ''))));

        if ($userId === '' && $login === '' && $email === '') {
            throw ValidationException::withMessages([
                'identity' => ['Provide at least one of userId, login, email via query string or X-GM-* headers.'],
            ]);
        }

        if ($userId !== '') {
            if (! ctype_digit($userId)) {
                throw ValidationException::withMessages([
                    'userId' => ['userId must be a positive integer.'],
                ]);
            }

            $user = User::query()
                ->with(['roles', 'cities', 'documents'])
                ->find((int) $userId);

            if ($user) {
                return $user;
            }
        }

        if ($login !== '') {
            $user = $this->findUserByLogin($login);
            if ($user) {
                return $user->loadMissing(['roles', 'cities', 'documents']);
            }
        }

        if ($email !== '') {
            $user = User::query()
                ->with(['roles', 'cities', 'documents'])
                ->where('email', $email)
                ->first();

            if ($user) {
                return $user;
            }
        }

        throw (new ModelNotFoundException())->setModel(User::class);
    }

    public function health(): array
    {
        return [
            'ok' => true,
            'service' => (string) config('services.gm_api.service_name'),
            'version' => app()->version(),
            'time' => now()->toIso8601String(),
        ];
    }

    public function profile(User $user): array
    {
        $user->loadMissing(['roles', 'cities', 'documents']);

        $branch = $user->cities->first();
        $primaryRole = $user->roles->first();
        $cityIds = $user->cityIdsForOrdersFilter();

        return [
            'userId' => (int) $user->user_id,
            'login' => $this->loginFromUser($user),
            'email' => $user->email,
            'displayName' => $user->user_name,
            'fullName' => $user->user_name,
            'inn' => $this->normalizeInn($user->user_inn),
            'birthDate' => $user->user_birth_date?->toDateString(),
            'guildJoinedAt' => $user->user_hired_at?->toDateString(),
            'role' => $primaryRole?->role_code,
            'roles' => $user->roles->pluck('role_code')->values()->all(),
            'city_ids' => $cityIds,
            'cities' => $this->serializeScopeCities($cityIds),
            'access_all_cities' => $cityIds === null,
            'rating' => $this->calculateUserRating($user),
            'status' => $this->mapUserStatus($user),
            'isVerifiedByPassport' => $this->isPassportVerified($user),
            'isBranchDirector' => $user->hasRole('branch_head'),
            'branchId' => $branch?->city_id ? (int) $branch->city_id : null,
            'branchCity' => $branch?->city_name,
            'photoUrl' => null,
            'svyazGuildLogin' => null,
            'svyazGuildPassword' => null,
            'deputySvyazGuildLogin' => null,
            'deputySvyazGuildPassword' => null,
        ];
    }

    /**
     * Сервер-сервер проверка логина/пароля для входа в GM.
     * Не различает «нет пользователя» и «неверный пароль» (одинаковый 401).
     *
     * @return array{status:int, body:array<string,mixed>}
     */
    public function verifyPassword(string $loginOrEmail, string $password): array
    {
        $login = strtolower(trim($loginOrEmail));
        $user = $this->findUserForPasswordVerify($login);

        $hash = is_string($user?->password) && $user->password !== ''
            ? $user->password
            : self::DUMMY_PASSWORD_HASH;

        $passwordOk = Hash::check($password, $hash);

        if (! $user || ! $passwordOk) {
            return [
                'status' => 401,
                'body' => [
                    'ok' => false,
                    'error' => 'invalid_credentials',
                ],
            ];
        }

        if (! $user->is_active || $user->is_blacklisted) {
            return [
                'status' => 403,
                'body' => [
                    'ok' => false,
                    'error' => 'forbidden',
                ],
            ];
        }

        $user->loadMissing(['roles', 'cities']);
        $primaryRole = $user->roles
            ->sortByDesc(fn ($role) => match ($role->role_code) {
                'developer' => 700,
                'general_director' => 600,
                'regional_director' => 500,
                'tech_director' => 400,
                'senior_dispatcher' => 300,
                'call_center' => 200,
                'senior_master' => 150,
                'master' => 100,
                default => 0,
            })
            ->first();

        $displayName = (string) ($user->user_name ?: $user->email);
        $cityName = $user->cities->first()?->city_name;
        $mustChange = (bool) ($user->must_change_password ?? false);

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'user' => [
                    // id / userId — числовой LC user_id (GM обязан читать один из них)
                    'id' => (int) $user->user_id,
                    'userId' => (int) $user->user_id,
                    'email' => (string) $user->email,
                    // login = локальная часть email; канон входа: email ИЛИ login (см. GM-VERIFY-PASSWORD.md)
                    'login' => $this->loginFromUser($user),
                    'displayName' => $displayName,
                    'name' => $displayName,
                    'role' => $this->gmUserSyncService->mapRole($primaryRole?->role_code),
                    'isActive' => (bool) $user->is_active,
                    'city' => $cityName,
                    'branchCity' => $cityName,
                    'mustChangePassword' => $mustChange,
                    'passwordSet' => is_string($user->password) && $user->password !== '',
                ],
            ],
        ];
    }

    /**
     * Смена пароля мастера из GM (server-to-server). Источник правды — LC.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function changePassword(?int $userId, ?string $login, string $currentPassword, string $newPassword): array
    {
        $user = $this->resolveUserForPasswordChange($userId, $login);

        if (! $user) {
            return [
                'status' => 404,
                'body' => [
                    'ok' => false,
                    'error' => 'not_found',
                ],
            ];
        }

        if (! $user->is_active || $user->is_blacklisted) {
            return [
                'status' => 403,
                'body' => [
                    'ok' => false,
                    'error' => 'forbidden',
                ],
            ];
        }

        $hash = is_string($user->password) && $user->password !== ''
            ? $user->password
            : self::DUMMY_PASSWORD_HASH;

        if (! Hash::check($currentPassword, $hash)) {
            Hash::check($currentPassword, self::DUMMY_PASSWORD_HASH);

            return [
                'status' => 401,
                'body' => [
                    'ok' => false,
                    'error' => 'invalid_credentials',
                ],
            ];
        }

        if ($currentPassword === $newPassword || Hash::check($newPassword, $hash)) {
            return [
                'status' => 422,
                'body' => [
                    'ok' => false,
                    'error' => 'validation_error',
                    'message' => 'Новый пароль не должен совпадать с текущим',
                ],
            ];
        }

        $user->forceFill([
            'password' => $newPassword,
            'must_change_password' => false,
            'password_set_at' => now(),
        ])->save();

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'userId' => (int) $user->user_id,
                'mustChangePassword' => false,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function passwordStatus(int $userId): array
    {
        $user = User::query()->find($userId);

        if (! $user) {
            return [
                'status' => 404,
                'body' => [
                    'ok' => false,
                    'error' => 'not_found',
                ],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'userId' => (int) $user->user_id,
                'hasPassword' => is_string($user->password) && $user->password !== '',
                'mustChangePassword' => (bool) ($user->must_change_password ?? false),
            ],
        ];
    }

    private function resolveUserForPasswordChange(?int $userId, ?string $login): ?User
    {
        if ($userId !== null && $userId > 0) {
            $user = User::query()->find($userId);
            if ($user) {
                return $user;
            }
        }

        $normalizedLogin = strtolower(trim((string) $login));
        if ($normalizedLogin === '') {
            return null;
        }

        return $this->findUserForPasswordVerify($normalizedLogin);
    }

    private function findUserForPasswordVerify(string $login): ?User
    {
        if ($login === '') {
            return null;
        }

        $byEmail = User::query()
            ->whereRaw('LOWER(email) = ?', [$login])
            ->first();

        if ($byEmail) {
            return $byEmail;
        }

        if (str_contains($login, '@')) {
            return null;
        }

        // Логин без @ — локальная часть email (как loginFromUser)
        return User::query()
            ->whereRaw('LOWER(email) LIKE ?', [$login.'@%'])
            ->orderBy('user_id')
            ->first();
    }

    public function metrics(User $user): array
    {
        $orders = $this->ordersForMaster($user)
            ->with(['address.city'])
            ->get();

        [$monthStart, $monthEnd] = $this->currentMonthBoundsMoscow();

        $monthOrders = $orders->filter(function (Order $order) use ($monthStart, $monthEnd) {
            $anchor = $order->order_closed_at ?? $order->order_created_at;

            return $anchor !== null
                && $anchor->greaterThanOrEqualTo($monthStart)
                && $anchor->lessThanOrEqualTo($monthEnd);
        });

        $completedMonthOrders = $monthOrders->where('order_status', 'completed');
        // primary* closed/avg — тот же набор, что roster closedOrdersMonth / avgCheckRub
        // (все completed за месяц). Раньше фильтр order_core=core давал 0 при живых закрытиях.
        $primaryCompleted = $completedMonthOrders;
        $coreCompleted = $completedMonthOrders->where('order_core', 'core');

        $branchCity = $user->cities->first();
        $branchCashRub = 0;
        if ($branchCity) {
            $branchCashRub = CfmOperation::query()
                ->with('category')
                ->where('city_id', $branchCity->city_id)
                ->whereBetween('cfm_created_at', [$monthStart, $monthEnd])
                ->get()
                ->sum(fn (CfmOperation $operation) => (int) $operation->signed_amount);
        }

        $lcClosedCount = (int) $primaryCompleted->count();
        $lcNetSum = (int) $primaryCompleted->sum(fn (Order $order) => (int) ($order->amount_paid - $order->amount_comp));

        // Заявки Единого хаба (KP-Lead) того же мастера — считаем в тех же границах месяца (МСК).
        $hub = $this->hubOrders->ordersForMaster($user);
        $hubMonth = $this->hubOrdersInMonth($hub['items'], $monthStart, $monthEnd);
        $hubCompleted = array_values(array_filter($hubMonth, fn (array $item) => $item['status'] === 'completed'));

        $hubClosedCount = count($hubCompleted);
        $hubNetSum = array_sum(array_map(fn (array $item) => (int) $item['amountRub'], $hubCompleted));
        $hubSalary = array_sum(array_map(
            fn (array $item) => (int) ($item['financialSnapshot']['masterSalaryRub'] ?? 0),
            $hubCompleted
        ));
        $hubCoreSalary = array_sum(array_map(
            fn (array $item) => ($item['financialSnapshot']['orderCore'] ?? 'core') === 'core'
                ? (int) ($item['financialSnapshot']['masterSalaryRub'] ?? 0)
                : 0,
            $hubCompleted
        ));

        $totalClosed = $lcClosedCount + $hubClosedCount;
        $avgCheck = $totalClosed === 0 ? 0 : (int) round(($lcNetSum + $hubNetSum) / $totalClosed);

        $hubRefusals = count(array_filter($hubMonth, fn (array $item) => $item['status'] === 'rejected'));
        $hubCancelled = count(array_filter(
            $hubMonth,
            fn (array $item) => in_array($item['status'], ['cancelled_cc', 'cancelled_city'], true)
        ));

        $meta = $this->sourcesMeta($hub['source']);

        return array_merge([
            'primaryClosedOrdersMonth' => $totalClosed,
            'primarySalaryRub' => (int) $coreCompleted->sum(fn (Order $order) => $this->calculateMasterSalary($order)) + $hubCoreSalary,
            'masterEarnedRub' => (int) $completedMonthOrders->sum(fn (Order $order) => $this->calculateMasterSalary($order)) + $hubSalary,
            'branchCashRub' => (int) $branchCashRub,
            'primaryAvgCheckRub' => $avgCheck,
            'masterMonthRefusals' => (int) $monthOrders->where('order_status', 'rejected')->count() + $hubRefusals,
            'masterMonthCancelledApplications' => (int) $monthOrders->whereIn('order_status', ['cancelled_cc', 'cancelled_city'])->count() + $hubCancelled,
            'unreadMessages' => 0,
            'deputyUnreadMessages' => 0,
            'period' => [
                'timezone' => 'Europe/Moscow',
                'from' => $monthStart->toIso8601String(),
                'to' => $monthEnd->toIso8601String(),
            ],
            'bySource' => [
                self::SOURCE_LC => [
                    'closedOrdersMonth' => $lcClosedCount,
                    'netAmountRub' => $lcNetSum,
                ],
                HubOrderPresenter::SOURCE_KP => [
                    'closedOrdersMonth' => $hubClosedCount,
                    'netAmountRub' => $hubNetSum,
                ],
            ],
        ], $meta);
    }

    /**
     * Метаданные источников для ответов GM: статус и признак неполных данных.
     * Нули при недоступном источнике подменять нельзя — GM должен увидеть partial=true.
     *
     * @param  array<string, mixed>  $hubSource
     * @return array<string, mixed>
     */
    private function sourcesMeta(array $hubSource): array
    {
        $sources = [
            [
                'source' => self::SOURCE_LC,
                'name' => 'Lead Control',
                'enabled' => true,
                'available' => true,
                'status' => 'ok',
                'lastSyncAt' => null,
                'message' => null,
            ],
            $hubSource,
        ];

        $partial = false;
        $degraded = false;
        foreach ($sources as $source) {
            if (($source['enabled'] ?? false) && ! ($source['available'] ?? false)) {
                $partial = true;
            }
            if (in_array($source['status'] ?? 'ok', ['stale', 'degraded'], true)) {
                $degraded = true;
            }
        }

        return [
            'sources' => $sources,
            'partial' => $partial,
            'degraded' => $degraded,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function hubOrdersInMonth(array $items, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        return array_values(array_filter($items, function (array $item) use ($monthStart, $monthEnd) {
            $anchor = $item['closedAt'] ?? $item['createdAt'] ?? null;
            if ($anchor === null) {
                return false;
            }

            try {
                $date = CarbonImmutable::parse($anchor)->setTimezone('Europe/Moscow');
            } catch (\Throwable) {
                return false;
            }

            return $date->greaterThanOrEqualTo($monthStart) && $date->lessThanOrEqualTo($monthEnd);
        }));
    }

    public function ratingHistory(): array
    {
        return [
            'items' => [],
        ];
    }

    /**
     * Единая выборка заявок мастера: CRM1/LC + KP-Lead из Единого хаба.
     * ID заявок уникальны с учётом источника (LC — прежний числовой, KP — «kp-<external_id>»).
     */
    public function orders(User $user, array $filters): array
    {
        $pageSize = $this->sanitizePageSize($filters['pageSize'] ?? 20);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $sourceFilter = $this->sourceFilter($filters);

        $hub = $this->hubOrders->ordersForMaster($user);
        $hubItems = $sourceFilter !== null && ! in_array(HubOrderPresenter::SOURCE_KP, $sourceFilter, true)
            ? []
            : $this->applyHubFilters($hub['items'], $filters);
        $meta = $this->sourcesMeta($hub['source']);

        $wantsLc = $sourceFilter === null || in_array(self::SOURCE_LC, $sourceFilter, true);

        if ($hubItems === [] && $wantsLc) {
            // Быстрый путь без KP-заявок: прежняя постраничная выборка LC один-в-один.
            $query = $this->masterOrdersQuery($user, $filters);
            $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

            return array_merge(
                $this->paginatedResponse($paginator, fn (Order $order) => $this->mapOrderSummary($order)),
                $meta,
            );
        }

        $lcItems = $wantsLc
            ? $this->masterOrdersQuery($user, $filters)
                ->limit(2000)
                ->get()
                ->map(fn (Order $order) => $this->mapOrderSummary($order))
                ->all()
            : [];

        $merged = $this->mergeOrderItems($lcItems, $hubItems);
        $total = count($merged);
        $items = array_slice($merged, ($page - 1) * $pageSize, $pageSize);

        return array_merge([
            'items' => array_values($items),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
        ], $meta);
    }

    /**
     * Слияние с защитой от двойного учёта: один canonical ID = одна заявка.
     *
     * @param  list<array<string, mixed>>  $lcItems
     * @param  list<array<string, mixed>>  $hubItems
     * @return list<array<string, mixed>>
     */
    private function mergeOrderItems(array $lcItems, array $hubItems): array
    {
        $byKey = [];
        foreach (array_merge($lcItems, $hubItems) as $item) {
            $key = ($item['source'] ?? self::SOURCE_LC).':'.($item['sourceOrderId'] ?? $item['id']);
            if (! isset($byKey[$key])) {
                $byKey[$key] = $item;
            }
        }

        $merged = array_values($byKey);
        usort($merged, function (array $left, array $right) {
            $cmp = strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $right['sourceOrderId'], (string) $left['sourceOrderId']);
        });

        return $merged;
    }

    private function masterOrdersQuery(User $user, array $filters): Builder
    {
        $query = $this->ordersForMaster($user)
            ->with([
                'address.city',
                'master.roles',
                'persons.phones',
                'source.city',
                'complaints',
            ])
            ->withCount(['documents', 'complaints'])
            ->orderByDesc('order_created_at')
            ->orderByDesc('order_id');

        $this->applyOrderFilters($query, $filters);

        return $query;
    }

    /**
     * @return list<string>|null
     */
    private function sourceFilter(array $filters): ?array
    {
        if (empty($filters['source'])) {
            return null;
        }

        $sources = is_array($filters['source'])
            ? $filters['source']
            : explode(',', (string) $filters['source']);

        $sources = array_values(array_filter(array_map('trim', $sources)));

        return $sources === [] ? null : $sources;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function applyHubFilters(array $items, array $filters): array
    {
        $statuses = null;
        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? $filters['status']
                : explode(',', (string) $filters['status']);
            $statuses = array_values(array_filter(array_map('trim', $statuses)));
        }

        $from = ! empty($filters['from']) ? CarbonImmutable::parse((string) $filters['from'])->startOfDay() : null;
        $to = ! empty($filters['to']) ? CarbonImmutable::parse((string) $filters['to'])->endOfDay() : null;

        return array_values(array_filter($items, function (array $item) use ($statuses, $from, $to) {
            if ($statuses !== null && $statuses !== [] && ! in_array($item['status'], $statuses, true)) {
                return false;
            }

            if ($from === null && $to === null) {
                return true;
            }

            $createdAt = $item['createdAt'] ?? null;
            if ($createdAt === null) {
                return false;
            }

            $date = CarbonImmutable::parse($createdAt)->setTimezone('Europe/Moscow');
            if ($from !== null && $date->lessThan($from)) {
                return false;
            }

            return ! ($to !== null && $date->greaterThan($to));
        }));
    }

    /**
     * Карточка заявки по каноническому ID: числовой — LC, «kp-…» — Единый хаб.
     *
     * @param  int|string  $orderId
     */
    public function orderDetails(User $user, int|string $orderId): array
    {
        $hubId = $this->parseHubOrderId($orderId);
        if ($hubId !== null) {
            return $this->hubOrderDetails($user, $hubId[0], $hubId[1]);
        }

        $order = $this->findMasterOrder($user, (int) $orderId);

        return $this->orderDetailsPayload($order);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function parseHubOrderId(int|string $orderId): ?array
    {
        if (is_int($orderId) || ctype_digit((string) $orderId)) {
            return null;
        }

        return HubOrderPresenter::parseCanonicalId((string) $orderId);
    }

    /**
     * @return array<string, mixed>
     */
    private function hubOrderDetails(User $user, string $source, string $externalId): array
    {
        $result = $this->hubOrders->findForMaster($user, $source, $externalId);

        if (! $result['found']) {
            if (! ($result['source']['available'] ?? false)) {
                throw new \App\Exceptions\HubSourceUnavailableException($source);
            }

            throw (new ModelNotFoundException())->setModel(Order::class);
        }

        return array_merge($result['order'], $this->sourcesMeta($result['source']));
    }

    public function orderDocuments(User $user, int|string $orderId): array
    {
        $hubId = $this->parseHubOrderId($orderId);
        if ($hubId !== null) {
            $details = $this->hubOrderDetails($user, $hubId[0], $hubId[1]);

            return ['items' => $details['documents'] ?? []];
        }

        $order = $this->findMasterOrder($user, (int) $orderId);

        return [
            'items' => $order->documents->map(fn ($document) => $this->mapDocument($document))->values()->all(),
        ];
    }

    public function uploadOrderDocument(User $user, int|string $orderId, Request $request): array
    {
        $this->guardHubAction($user, $orderId, 'загрузка документов');
        $order = $this->findMasterOrder($user, (int) $orderId);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                Document::orderPhotoMimeValidationRule(),
            ],
            'category' => ['required', 'string', 'in:contract,receipts,parts_photos,storage_receipt'],
        ]);

        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ! Document::isAllowedOrderPhoto($file)) {
            throw ValidationException::withMessages([
                'file' => ['Можно загружать только фотографии: '.Document::orderPhotoFormatsLabel()],
            ]);
        }

        $category = $validated['category'];

        $existingCount = Document::query()
            ->where('documentable_type', Order::class)
            ->where('documentable_id', $order->order_id)
            ->where('document_category', $category)
            ->count();

        if ($existingCount >= self::MAX_FILES_PER_ORDER_CATEGORY) {
            throw ValidationException::withMessages([
                'category' => ['Maximum '.self::MAX_FILES_PER_ORDER_CATEGORY.' files in this category.'],
            ]);
        }

        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => ['File is required.'],
            ]);
        }

        $safeName = $this->safeOriginalFileName($file);
        $fileName = time().'_'.$safeName;
        $folder = "documents/orders/{$order->order_id}/{$category}";
        $filePath = $file->storeAs($folder, $fileName, 'local');

        try {
            $document = new Document([
                'documentable_type' => Order::class,
                'documentable_id' => $order->order_id,
                'document_category' => $category,
                'file_name' => $safeName,
                'file_path' => $filePath,
                'file_mime' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
            $document->uploaded_by = $user->user_id;
            $document->save();
        } catch (\Throwable $exception) {
            if (Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }

            throw $exception;
        }

        return [
            'ok' => true,
            'document' => $this->mapDocument($document),
        ];
    }

    public function reviews(User $user, array $filters): array
    {
        $pageSize = $this->sanitizePageSize($filters['pageSize'] ?? 20);
        $page = max(1, (int) ($filters['page'] ?? 1));

        $query = Review::query()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $masterOrderIds = $this->ordersForMaster($user)
            ->pluck('order_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $query->where(function (Builder $builder) use ($user, $masterOrderIds) {
            $builder->where('created_by', $user->user_id);

            if ($masterOrderIds !== []) {
                $builder->orWhereIn('order_number', $masterOrderIds);
            }
        });

        if (! empty($filters['month'])) {
            [$from, $to] = $this->monthBounds((string) $filters['month']);
            $query->whereBetween('created_at', [$from, $to]);
        }

        if (! empty($filters['sentiment']) && $filters['sentiment'] !== 'all') {
            $query->whereRaw('1 = 0');
        }

        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        return $this->paginatedResponse(
            $paginator,
            fn (Review $review) => $this->mapReview($review),
        );
    }

    public function negativeOpenReviews(User $user): array
    {
        return [
            'items' => [],
        ];
    }

    public function guildApplications(): array
    {
        return [
            'items' => [],
        ];
    }

    public function branchRoster(User $user, ?int $cityId = null): array
    {
        $branchCity = $this->resolveBranchCity($user, $cityId);
        if (! $branchCity) {
            return [
                'branchId' => null,
                'branchCity' => null,
                'items' => [],
            ];
        }

        $masters = User::query()
            ->with(['roles', 'cities', 'documents'])
            ->whereHas('roles', fn (Builder $builder) => $builder->where('role_code', 'master'))
            ->whereHas('cities', fn (Builder $builder) => $builder->where('cities.city_id', $branchCity->city_id))
            ->orderBy('user_name')
            ->get();

        $orders = Order::query()
            ->whereIn('master_id', $masters->pluck('user_id'))
            ->get()
            ->groupBy('master_id');

        [$monthStart, $monthEnd] = $this->currentMonthBoundsMoscow();

        $hub = $this->hubOrders->ordersForCity((int) $branchCity->city_id);
        $hubByMaster = [];
        foreach ($this->hubOrdersInMonth($hub['items'], $monthStart, $monthEnd) as $item) {
            if ($item['status'] !== 'completed' || $item['masterId'] === null) {
                continue;
            }
            $hubByMaster[(int) $item['masterId']][] = $item;
        }

        return array_merge([
            'branchId' => (int) $branchCity->city_id,
            'branchCity' => $branchCity->city_name,
            'items' => $masters->map(function (User $master) use ($orders, $monthStart, $monthEnd, $hubByMaster) {
                $masterOrders = $orders->get($master->user_id, collect());
                $monthCompleted = $masterOrders
                    ->where('order_status', 'completed')
                    ->filter(function (Order $order) use ($monthStart, $monthEnd) {
                        $anchor = $order->order_closed_at ?? $order->order_created_at;

                        return $anchor !== null
                            && $anchor->greaterThanOrEqualTo($monthStart)
                            && $anchor->lessThanOrEqualTo($monthEnd);
                    });

                $hubItems = $hubByMaster[(int) $master->user_id] ?? [];
                $hubCount = count($hubItems);
                $hubNet = array_sum(array_map(fn (array $item) => (int) $item['amountRub'], $hubItems));
                $hubSalary = array_sum(array_map(
                    fn (array $item) => (int) ($item['financialSnapshot']['masterSalaryRub'] ?? 0),
                    $hubItems
                ));

                $lcCount = $monthCompleted->count();
                $lcNet = (int) $monthCompleted->sum(fn (Order $order) => (int) ($order->amount_paid - $order->amount_comp));
                $totalCount = $lcCount + $hubCount;

                return [
                    'id' => (int) $master->user_id,
                    'displayName' => $master->user_name,
                    'photoUrl' => null,
                    'rating' => $this->calculateUserRating($master),
                    'expelled' => ! $master->is_active || $master->user_fired_at !== null,
                    'banishedFromGuild' => (bool) $master->is_blacklisted,
                    'birthDate' => $master->user_birth_date?->toDateString(),
                    'isVerifiedByPassport' => $this->isPassportVerified($master),
                    'verifiedByReg' => $this->isPassportVerified($master),
                    'trainingBadgeFromReg' => false,
                    'roleTier' => $master->roles->first()?->role_code,
                    'isBranchDirector' => $master->hasRole('branch_head'),
                    'manualBadges' => [],
                    'avgCheckRub' => $totalCount === 0 ? 0 : (int) round(($lcNet + $hubNet) / $totalCount),
                    'closedOrdersMonth' => $totalCount,
                    'salaryRub' => (int) $monthCompleted->sum(fn (Order $order) => $this->calculateMasterSalary($order)) + $hubSalary,
                ];
            })->values()->all(),
        ], $this->sourcesMeta($hub['source']));
    }

    public function branchStats(User $user, ?int $cityId = null): array
    {
        $branchCity = $this->resolveBranchCity($user, $cityId);
        if (! $branchCity) {
            return [
                'branchId' => null,
                'branchCity' => null,
                'ordersClosedMonth' => 0,
                'avgCheckRub' => 0,
                'cashRub' => 0,
                'activeMasters' => 0,
                'expelledMasters' => 0,
                'negativeReviewsCount' => 0,
                'cancellationsTotal' => 0,
            ];
        }

        [$monthStart, $monthEnd] = $this->currentMonthBoundsMoscow();

        $branchOrders = Order::query()
            ->with('address.city')
            ->whereHas('address', fn (Builder $builder) => $builder->where('city_id', $branchCity->city_id))
            ->get();

        $closedMonthOrders = $branchOrders
            ->where('order_status', 'completed')
            ->filter(function (Order $order) use ($monthStart, $monthEnd) {
                $anchor = $order->order_closed_at ?? $order->order_created_at;

                return $anchor !== null
                    && $anchor->greaterThanOrEqualTo($monthStart)
                    && $anchor->lessThanOrEqualTo($monthEnd);
            });

        $cashRub = CfmOperation::query()
            ->with('category')
            ->where('city_id', $branchCity->city_id)
            ->whereBetween('cfm_created_at', [$monthStart, $monthEnd])
            ->get()
            ->sum(fn (CfmOperation $operation) => (int) $operation->signed_amount);

        $masters = User::query()
            ->whereHas('roles', fn (Builder $builder) => $builder->where('role_code', 'master'))
            ->whereHas('cities', fn (Builder $builder) => $builder->where('cities.city_id', $branchCity->city_id))
            ->get();

        $hub = $this->hubOrders->ordersForCity((int) $branchCity->city_id);
        $hubMonth = $this->hubOrdersInMonth($hub['items'], $monthStart, $monthEnd);
        $hubClosed = array_values(array_filter($hubMonth, fn (array $item) => $item['status'] === 'completed'));
        $hubClosedCount = count($hubClosed);
        $hubNet = array_sum(array_map(fn (array $item) => (int) $item['amountRub'], $hubClosed));
        $hubCancellations = count(array_filter(
            $hub['items'],
            fn (array $item) => in_array($item['status'], ['cancelled_cc', 'cancelled_city'], true)
        ));

        $lcClosedCount = (int) $closedMonthOrders->count();
        $lcNet = (int) $closedMonthOrders->sum(fn (Order $order) => (int) ($order->amount_paid - $order->amount_comp));
        $totalClosed = $lcClosedCount + $hubClosedCount;

        return array_merge([
            'branchId' => (int) $branchCity->city_id,
            'branchCity' => $branchCity->city_name,
            'ordersClosedMonth' => $totalClosed,
            'avgCheckRub' => $totalClosed === 0 ? 0 : (int) round(($lcNet + $hubNet) / $totalClosed),
            'cashRub' => (int) $cashRub,
            'activeMasters' => (int) $masters->filter(fn (User $master) => $master->is_active && $master->user_fired_at === null)->count(),
            'expelledMasters' => (int) $masters->filter(fn (User $master) => ! $master->is_active || $master->user_fired_at !== null)->count(),
            'negativeReviewsCount' => 0,
            'cancellationsTotal' => (int) $branchOrders->whereIn('order_status', ['cancelled_cc', 'cancelled_city'])->count() + $hubCancellations,
            'bySource' => [
                self::SOURCE_LC => ['closedOrdersMonth' => $lcClosedCount, 'netAmountRub' => $lcNet],
                HubOrderPresenter::SOURCE_KP => ['closedOrdersMonth' => $hubClosedCount, 'netAmountRub' => $hubNet],
            ],
        ], $this->sourcesMeta($hub['source']));
    }

    /**
     * Состояние источников Единого хаба для GM: статус подключения и время
     * последней успешной синхронизации по источникам и городам.
     *
     * @return array<string, mixed>
     */
    public function sourcesStatus(User $user): array
    {
        $cityIds = $user->cityIdsForOrdersFilter();
        $state = $this->hubOrders->sourceState($cityIds === [] ? null : array_map('intval', $cityIds));

        return array_merge(
            $this->sourcesMeta($state),
            ['cities' => $this->hubOrders->citySyncState($cityIds)],
        );
    }

    public function messenger(User $user): array
    {
        $user->loadMissing(['roles', 'cities']);

        return [
            'externalId' => (int) $user->user_id,
            'name' => $user->user_name,
            'email' => $user->email,
            'role' => $user->roles->first()?->role_code,
            'title' => $user->roles->first()?->role_name,
            'city' => $user->cities->first()?->city_name,
        ];
    }

    /**
     * Действие GM по заявке внешнего источника: проверяем принадлежность мастеру
     * и отдаём явную ошибку вместо того, чтобы отправлять KP-заявку в LC-обработчик.
     */
    private function guardHubAction(User $user, int|string $orderId, string $action): void
    {
        $hubId = $this->parseHubOrderId($orderId);
        if ($hubId === null) {
            return;
        }

        $result = $this->hubOrders->findForMaster($user, $hubId[0], $hubId[1]);
        if (! $result['found']) {
            if (! ($result['source']['available'] ?? false)) {
                throw new \App\Exceptions\HubSourceUnavailableException($hubId[0]);
            }

            throw (new ModelNotFoundException())->setModel(Order::class);
        }

        throw new \App\Exceptions\HubActionNotSupportedException($hubId[0], $action);
    }

    public function acceptOrder(User $user, int|string $orderId): array
    {
        $hubId = $this->parseHubOrderId($orderId);
        if ($hubId !== null) {
            return $this->acceptHubOrder($user, $hubId[0], $hubId[1]);
        }

        $order = $this->findMasterOrder($user, (int) $orderId);

        if ($this->isFinalOrderStatus($order->order_status)) {
            throw new ConflictHttpException('Order is already final.');
        }

        $this->applyMasterStatusTransition($order, $user, 'on_way');

        return $this->orderActionResponse($order);
    }

    /**
     * Принятие KP-заявки: pending (и любой нефинальный) → on_way через Desk → КП.
     *
     * @return array{ok: bool, order: array<string, mixed>}
     */
    private function acceptHubOrder(User $user, string $source, string $externalId): array
    {
        $result = $this->requireHubOrderForMaster($user, $source, $externalId);
        $status = (string) ($result['order']['status'] ?? 'pending');

        if (HubStatusMap::isFinal($status)) {
            throw HubOrderConflictException::alreadyFinal($source);
        }

        if ($status !== 'on_way') {
            $extra = [];
            $kpEmployeeId = trim((string) ($user->kp_employee_id ?? ''));
            if ($kpEmployeeId !== '') {
                $extra['master_external_id'] = $kpEmployeeId;
            }
            $extra['comment'] = 'Принято мастером через GM';

            $this->hubWrite->setRawStatus($externalId, 'on_way', $extra);
        }

        return $this->hubOrderActionResponse($user, $source, $externalId);
    }

    /**
     * @return array{found: bool, order: array<string, mixed>|null, source: array<string, mixed>, ok: bool}
     */
    private function requireHubOrderForMaster(User $user, string $source, string $externalId): array
    {
        $result = $this->hubOrders->findForMaster($user, $source, $externalId);
        if (! $result['found']) {
            if (! ($result['source']['available'] ?? false)) {
                throw new HubSourceUnavailableException($source);
            }

            throw (new ModelNotFoundException())->setModel(Order::class);
        }

        return $result;
    }

    /**
     * @return array{ok: bool, order: array<string, mixed>}
     */
    private function hubOrderActionResponse(User $user, string $source, string $externalId): array
    {
        $fresh = $this->requireHubOrderForMaster($user, $source, $externalId);

        return [
            'ok' => true,
            'order' => array_merge($fresh['order'], $this->sourcesMeta($fresh['source'])),
        ];
    }

    /**
     * Мастер закрывает работу из гильдии сразу в `completed` («Готов»):
     * суммы, отписка, касса и расчёт — как кнопка «Готов» в вебе. Статус `review` больше не ставится.
     *
     * @param  array{amountPaidRub:int, amountCompRub:int, masterComment:string}  $payload
     */
    public function markOrderReview(User $user, int|string $orderId, array $payload): array
    {
        $this->guardHubAction($user, $orderId, 'закрыть заявку');
        $order = $this->findMasterOrder($user, (int) $orderId);

        if ($this->isFinalOrderStatus($order->order_status)) {
            throw new ConflictHttpException('Order is already final.');
        }

        if (! in_array($order->order_status, self::CLOSE_FROM_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['invalid_status_transition'],
            ]);
        }

        $amountPaid = (int) $payload['amountPaidRub'];
        $amountComp = (int) $payload['amountCompRub'];
        $comment = trim((string) $payload['masterComment']);

        if ($comment === '') {
            throw ValidationException::withMessages([
                'masterComment' => ['masterComment is required.'],
            ]);
        }

        $before = [
            'order_status' => $order->order_status,
            'amount_paid' => $order->amount_paid,
            'amount_comp' => $order->amount_comp,
            'city_adds' => $order->city_adds,
        ];

        $order->amount_paid = $amountPaid;
        $order->amount_comp = $amountComp;
        $order->appendBranchCommentBlock(Order::BRANCH_COMMENT_CLOSE_HEADING, $comment);
        $order->save();

        $this->orderActivityLogService->logChanges($order, $user, $before, [
            'amount_paid' => $order->amount_paid,
            'amount_comp' => $order->amount_comp,
            'city_adds' => $order->city_adds,
        ]);

        try {
            $this->orderService->complete($order, (int) $user->user_id);
        } catch (QueryException|\RuntimeException $exception) {
            Log::error('gm.markOrderReview persist failed', [
                'order_id' => $order->order_id,
                'from_status' => $before['order_status'] ?? null,
                'sqlstate' => $exception instanceof QueryException ? $exception->getCode() : null,
            ]);
            throw new ConflictHttpException('Order status cannot be saved.');
        }

        $order->refresh();
        $this->orderActivityLogService->logCompleted($order, $user);
        \App\Jobs\SyncOrderToGmJob::dispatch((int) $order->order_id);

        return $this->orderActionResponse($order);
    }

    /**
     * @param  array{masterComment:string}  $payload
     */
    public function moveOrderToSd(User $user, int|string $orderId, array $payload): array
    {
        $this->guardHubAction($user, $orderId, 'отправить в СД');
        $order = $this->findMasterOrder($user, (int) $orderId);

        if ($this->isFinalOrderStatus($order->order_status) || $order->order_status === 'review') {
            throw new ConflictHttpException('Order is already final.');
        }

        $comment = trim((string) $payload['masterComment']);
        if ($comment === '') {
            throw ValidationException::withMessages([
                'masterComment' => ['masterComment is required.'],
            ]);
        }

        $before = [
            'order_status' => $order->order_status,
            'city_adds' => $order->city_adds,
        ];

        $order->appendBranchCommentBlock(Order::BRANCH_COMMENT_SD_HEADING, $comment);
        $this->applyMasterStatusTransition($order, $user, 'in_progress_sd', $before);

        return $this->orderActionResponse($order);
    }

    public function moveOrderInProgress(User $user, int|string $orderId): array
    {
        $hubId = $this->parseHubOrderId($orderId);
        if ($hubId !== null) {
            return $this->moveHubOrderInProgress($user, $hubId[0], $hubId[1]);
        }

        $order = $this->findMasterOrder($user, (int) $orderId);

        if ($this->isFinalOrderStatus($order->order_status) || $order->order_status === 'review') {
            throw new ConflictHttpException('Order is already final.');
        }

        if ($order->order_status !== 'on_way') {
            throw ValidationException::withMessages([
                'status' => ['invalid_status_transition'],
            ]);
        }

        $this->applyMasterStatusTransition($order, $user, 'in_progress');

        return $this->orderActionResponse($order);
    }

    /**
     * @return array{ok: bool, order: array<string, mixed>}
     */
    private function moveHubOrderInProgress(User $user, string $source, string $externalId): array
    {
        $result = $this->requireHubOrderForMaster($user, $source, $externalId);
        $status = (string) ($result['order']['status'] ?? 'pending');

        if (HubStatusMap::isFinal($status) || $status === 'review') {
            throw HubOrderConflictException::alreadyFinal($source);
        }

        if ($status === 'in_progress') {
            return $this->hubOrderActionResponse($user, $source, $externalId);
        }

        if ($status !== 'on_way') {
            throw HubOrderConflictException::invalidTransition($status, 'in_progress', $source);
        }

        $this->hubWrite->setRawStatus($externalId, 'in_progress', [
            'comment' => 'В работе (GM)',
        ]);

        return $this->hubOrderActionResponse($user, $source, $externalId);
    }

    private function isFinalOrderStatus(string $status): bool
    {
        return in_array($status, ['completed', 'cancelled_cc', 'cancelled_city', 'rejected'], true);
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    private function applyMasterStatusTransition(Order $order, User $user, string $newStatus, ?array $before = null): void
    {
        $before ??= ['order_status' => $order->order_status];
        $now = now();

        if ($order->order_status !== $newStatus) {
            $order->order_status = $newStatus;
            $order->status_changed_at = $now;
        }

        if ($newStatus === 'in_progress' && $order->in_progress_at === null) {
            $order->in_progress_at = $now;
        }

        $order->save();

        $after = [
            'order_status' => $order->order_status,
        ];
        if (array_key_exists('amount_paid', $before)) {
            $after['amount_paid'] = $order->amount_paid;
        }
        if (array_key_exists('amount_comp', $before)) {
            $after['amount_comp'] = $order->amount_comp;
        }
        if (array_key_exists('city_adds', $before)) {
            $after['city_adds'] = $order->city_adds;
        }

        $this->orderActivityLogService->logChanges($order, $user, $before, $after);
    }

    private function orderActionResponse(Order $order): array
    {
        $order->refresh();
        $order->loadMissing([
            'address.city',
            'master.roles',
            'persons.phones',
            'source.city',
            'documents',
            'activityLogs.user',
        ]);
        $order->loadCount('documents');

        return [
            'ok' => true,
            'order' => $this->orderDetailsPayload($order),
        ];
    }

    private function orderDetailsPayload(Order $order): array
    {
        return array_merge(
            $this->mapOrderSummary($order),
            [
                'documents' => $order->relationLoaded('documents')
                    ? $order->documents->map(fn ($document) => $this->mapDocument($document))->values()->all()
                    : [],
                'timeline' => $this->mapOrderTimeline($order),
                'assignedMaster' => $order->master ? $this->mapAssignedMaster($order->master) : null,
                'branch' => [
                    'branchId' => $order->address?->city?->city_id ? (int) $order->address->city->city_id : null,
                    'branchCity' => $order->address?->city?->city_name,
                ],
                'canCallClient' => $this->extractClientPhone($order) !== null,
                'canMessageClient' => $this->extractClientPhone($order) !== null,
            ],
        );
    }

    private function ordersForMaster(User $user): Builder
    {
        return Order::query()->where('master_id', $user->user_id);
    }

    private function findMasterOrder(User $user, int $orderId): Order
    {
        $order = $this->ordersForMaster($user)
            ->with([
                'address.city',
                'master.roles',
                'persons.phones',
                'source.city',
                'documents',
                'activityLogs.user',
                'complaints',
            ])
            ->withCount(['documents', 'complaints'])
            ->find($orderId);

        if (! $order) {
            throw (new ModelNotFoundException())->setModel(Order::class);
        }

        return $order;
    }

    private function applyOrderFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? $filters['status']
                : explode(',', (string) $filters['status']);

            $statuses = array_values(array_filter(array_map('trim', $statuses)));
            if ($statuses !== []) {
                $query->whereIn('order_status', $statuses);
            }
        }

        if (! empty($filters['from'])) {
            $query->whereDate('order_created_at', '>=', (string) $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('order_created_at', '<=', (string) $filters['to']);
        }
    }

    private function paginatedResponse(LengthAwarePaginator $paginator, callable $mapper): array
    {
        return [
            'items' => $paginator->getCollection()->map($mapper)->values()->all(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    private function sanitizePageSize(mixed $pageSize): int
    {
        return min(100, max(1, (int) $pageSize));
    }

    private function mapOrderSummary(Order $order): array
    {
        $clientPhone = $this->extractClientPhone($order);
        $person = $order->persons->first();
        $updatedAt = $order->status_changed_at
            ?? $order->order_closed_at
            ?? $order->order_created_at;
        $cityName = $order->address?->city?->displayName()
            ?: $order->address?->city?->city_name;
        $address = $order->address?->full_address;
        $addressFull = $this->formatOrderAddressFull(
            is_string($cityName) ? $cityName : null,
            is_string($address) ? $address : null,
        );

        return [
            // Обратная совместимость: для LC-заявок id остаётся числовым, как раньше.
            'id' => (int) $order->order_id,
            'source' => self::SOURCE_LC,
            'sourceOrderId' => (string) $order->order_id,
            'orderNumber' => (string) $order->order_id,
            'masterId' => $order->master_id ? (string) $order->master_id : null,
            'cityId' => $order->address?->city_id ? (int) $order->address->city_id : null,
            'cityName' => $cityName ? (string) $cityName : null,
            'status' => $order->order_status,
            'description' => $order->order_adds,
            'address' => $address,
            'addressFull' => $addressFull,
            'clientName' => $person?->person_name,
            'clientPhone' => $clientPhone,
            'clientAge' => $person?->person_age !== null ? (int) $person->person_age : null,
            'equipmentType' => $order->equipment_type,
            'previousContactsSummary' => $order->shift_adds,
            'clientContactType' => $clientPhone ? 'phone' : null,
            'orderKind' => $order->orderKind(),
            'techDirectorComment' => $order->city_adds,
            'branchComment' => $order->city_adds,
            'masterSdComment' => $order->extractBranchCommentBlock(Order::BRANCH_COMMENT_SD_HEADING),
            'masterCloseComment' => $order->extractBranchCommentBlock(Order::BRANCH_COMMENT_CLOSE_HEADING),
            'amountRub' => (int) ($order->amount_paid - $order->amount_comp),
            'billingCategory' => $order->order_type,
            'distanceKm' => null,
            'financialSnapshot' => [
                'amountPaidRub' => (int) $order->amount_paid,
                'amountCompRub' => (int) $order->amount_comp,
                'netAmountRub' => (int) ($order->amount_paid - $order->amount_comp),
                'masterSalaryRub' => $this->calculateMasterSalary($order),
                'orderType' => $order->order_type,
                'orderCore' => $order->order_core,
            ],
            'hasDocuments' => (int) ($order->documents_count ?? $order->documents->count()) > 0,
            'hasClaim' => $this->orderHasClaim($order),
            'claim' => $this->mapOrderClaim($order),
            'statusChangedAt' => $order->status_changed_at?->toIso8601String(),
            'inProgressAt' => $order->in_progress_at?->toIso8601String(),
            'scheduledAt' => $order->datetime_order?->toIso8601String(),
            'createdAt' => $order->order_created_at?->toIso8601String(),
            'updatedAt' => $updatedAt?->toIso8601String(),
            'closedAt' => $order->order_closed_at?->toIso8601String(),
            'contactUri' => $clientPhone ? 'tel:'.$clientPhone : null,
        ];
    }

    private function mapOrderTimeline(Order $order): array
    {
        $items = [];

        if ($order->order_created_at) {
            $items[] = [
                'date' => $order->order_created_at->toIso8601String(),
                'type' => 'created',
                'label' => 'Order created',
                'author' => $order->creator?->user_name,
            ];
        }

        if ($order->datetime_order) {
            $items[] = [
                'date' => $order->datetime_order->toIso8601String(),
                'type' => 'scheduled',
                'label' => 'Client visit scheduled',
                'author' => null,
            ];
        }

        foreach ($order->activityLogs as $activityLog) {
            $items[] = [
                'date' => $activityLog->created_at?->toIso8601String(),
                'type' => $activityLog->action,
                'label' => $activityLog->action_label,
                'author' => $activityLog->user?->user_name,
            ];
        }

        if ($order->order_closed_at) {
            $items[] = [
                'date' => $order->order_closed_at->toIso8601String(),
                'type' => 'closed',
                'label' => 'Order closed',
                'author' => $order->closedBy?->user_name,
            ];
        }

        usort($items, fn (array $left, array $right) => strcmp((string) $left['date'], (string) $right['date']));

        return $items;
    }

    private function mapAssignedMaster(User $user): array
    {
        return [
            'userId' => (int) $user->user_id,
            'displayName' => $user->user_name,
            'role' => $user->roles->first()?->role_code,
            'phone' => $this->formatPhone($user->user_phone),
        ];
    }

    private function mapDocument($document): array
    {
        return [
            'id' => (int) $document->document_id,
            'kind' => $document->document_category,
            'fileName' => $document->file_name,
            'mimeType' => $document->file_mime,
            'url' => $this->documentUrl($document),
            'uploadedAt' => $document->created_at?->toIso8601String(),
        ];
    }

    private function safeOriginalFileName(UploadedFile $file): string
    {
        $name = basename(str_replace("\0", '', $file->getClientOriginalName()));
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file';
        }

        return mb_strlen($name) > 200 ? mb_substr($name, 0, 200) : $name;
    }

    private function mapReview(Review $review): array
    {
        return [
            'id' => (int) $review->id,
            'author' => $review->partner_id ?: $review->createdBy?->user_name,
            'text' => null,
            'score' => null,
            'emoji' => null,
            'date' => $review->created_at?->toIso8601String(),
            'source' => $review->link,
            'sentiment' => null,
            'correctedAt' => null,
            'correctedBy' => null,
        ];
    }

    private function findUserByLogin(string $login): ?User
    {
        $normalized = mb_strtolower(trim($login));
        if ($normalized === '') {
            return null;
        }

        $candidates = User::query()
            ->where('email', $normalized)
            ->orWhere('email', 'like', $normalized.'@%')
            ->get();

        return $candidates->first(function (User $user) use ($normalized) {
            $userLogin = mb_strtolower($this->loginFromUser($user));

            return $userLogin === $normalized || mb_strtolower((string) $user->email) === $normalized;
        });
    }

    /**
     * Филиал для roster/stats: ?city_id= или первый город из pivot.
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    private function resolveBranchCity(User $user, ?int $cityId): ?City
    {
        $user->loadMissing('cities');

        if ($cityId === null) {
            return $user->cities->first();
        }

        $city = City::query()->find($cityId);
        if (! $city) {
            throw (new ModelNotFoundException())->setModel(City::class);
        }

        if (! $user->hasAccessToCity((int) $city->city_id)) {
            throw new AuthorizationException('No access to this city.');
        }

        return $city;
    }

    /**
     * Города скоупа как в Desk: null cityIds = все активные.
     *
     * @param  array<int, int>|null  $cityIds
     * @return list<array{id: int, name: string, parent_id: int|null, is_satellite: bool}>
     */
    private function serializeScopeCities(?array $cityIds): array
    {
        $citiesQuery = City::query()
            ->where('is_active', true)
            ->orderBy('city_name');

        if ($cityIds !== null) {
            $citiesQuery->whereIn('city_id', $cityIds === [] ? [-1] : $cityIds);
        }

        return $citiesQuery
            ->get(['city_id', 'city_name', 'parent_city_id'])
            ->map(function (City $city) {
                $parentId = $city->parent_city_id ? (int) $city->parent_city_id : null;

                return [
                    'id' => (int) $city->city_id,
                    'name' => $city->city_name,
                    'parent_id' => $parentId,
                    'is_satellite' => $parentId !== null,
                ];
            })
            ->values()
            ->all();
    }

    private function mapUserStatus(User $user): string
    {
        if ($user->is_blacklisted) {
            return 'blacklisted';
        }

        if (! $user->is_active || $user->user_fired_at !== null) {
            return 'inactive';
        }

        return 'active';
    }

    /**
     * Рейтинг для UI GM: проценты 0–100.
     * Пока источник — число завершённых заказов с clamp (пока нет отдельного гильдейского %).
     */
    private function calculateUserRating(User $user): int
    {
        $completed = Order::query()
            ->where('master_id', $user->user_id)
            ->where('order_status', 'completed')
            ->count();

        return max(0, min(100, (int) $completed));
    }

    /**
     * ИНН из кадров: только 10 или 12 цифр, иначе null (не "" / "0").
     */
    private function normalizeInn(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if ($digits === '' || $digits === '0') {
            return null;
        }

        $len = strlen($digits);
        if ($len !== 10 && $len !== 12) {
            return null;
        }

        return $digits;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function currentMonthBoundsMoscow(): array
    {
        $start = CarbonImmutable::now('Europe/Moscow')->startOfMonth();

        return [$start, $start->endOfMonth()];
    }

    private function isPassportVerified(User $user): bool
    {
        if ($user->documents->where('is_verified', true)->isNotEmpty()) {
            return true;
        }

        return ! empty($user->user_passport);
    }

    private function loginFromUser(User $user): string
    {
        $email = (string) $user->email;
        $parts = explode('@', $email, 2);

        return $parts[0] ?? $email;
    }

    private function extractClientPhone(Order $order): ?string
    {
        $person = $order->persons->first();
        if (! $person) {
            return null;
        }

        $phones = $person->relationLoaded('phones')
            ? $person->phones
            : (method_exists($person, 'phones') ? $person->phones : collect());

        $rawPhone = $phones->first()?->phone_number ?? null;

        return $this->formatPhone($rawPhone);
    }

    private function formatPhone(?string $rawPhone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $rawPhone);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            return '+7'.$digits;
        }

        if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
            return '+7'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    private function documentUrl($document): string
    {
        return url('/storage/'.$document->file_path);
    }

    private function averageNetAmount(Collection $orders): int
    {
        $count = $orders->count();
        if ($count === 0) {
            return 0;
        }

        $sum = $orders->sum(fn (Order $order) => (int) ($order->amount_paid - $order->amount_comp));

        return (int) round($sum / $count);
    }

    private function orderHasClaim(Order $order): bool
    {
        if (isset($order->complaints_count)) {
            return (int) $order->complaints_count > 0;
        }

        if ($order->relationLoaded('complaints')) {
            return $order->complaints->isNotEmpty();
        }

        return $order->complaints()->exists();
    }

    private function mapOrderClaim(Order $order): ?array
    {
        $complaint = $this->latestOrderComplaint($order);
        if (! $complaint) {
            return null;
        }

        $text = trim((string) $complaint->complaint_text);
        $summary = $text === '' ? null : mb_substr($text, 0, 180);

        return [
            'id' => (int) $complaint->complaint_id,
            'status' => (string) $complaint->complaint_status,
            'createdAt' => $complaint->complaint_created_at?->toIso8601String(),
            'summary' => $summary,
        ];
    }

    private function latestOrderComplaint(Order $order): ?Complaint
    {
        $collection = $order->relationLoaded('complaints')
            ? $order->complaints
            : $order->complaints()->orderByDesc('complaint_created_at')->get();

        if ($collection->isEmpty()) {
            return null;
        }

        $open = $collection
            ->whereIn('complaint_status', [Complaint::STATUS_NEW, Complaint::STATUS_IN_PROGRESS])
            ->sortByDesc(fn (Complaint $item) => $item->complaint_created_at?->timestamp ?? 0)
            ->first();

        if ($open) {
            return $open;
        }

        return $collection
            ->sortByDesc(fn (Complaint $item) => $item->complaint_created_at?->timestamp ?? 0)
            ->first();
    }

    private function formatOrderAddressFull(?string $cityName, ?string $address): ?string
    {
        $cityName = trim((string) $cityName);
        $address = trim((string) $address);
        if ($cityName === '' && $address === '') {
            return null;
        }
        if ($cityName === '') {
            return $address;
        }
        if ($address === '') {
            return $cityName;
        }
        if (str_starts_with(mb_strtolower($address), mb_strtolower($cityName))) {
            return $address;
        }

        return $cityName.', '.$address;
    }

    private function calculateMasterSalary(Order $order): int
    {
        return $this->orderService->calculateMasterSalary($order);
    }

    private function monthBounds(string $month): array
    {
        $date = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();

        return [$date, $date->endOfMonth()];
    }
}
