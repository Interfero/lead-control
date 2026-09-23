<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Order;
use App\Models\Source;
use App\Models\User;
use App\Support\OrderRescheduleLog;
use App\Support\PersonClientValidation;
use App\Services\OrderActivityLogService;
use App\Services\OrderCityViewService;
use App\Services\OrderClientHistoryService;
use App\Services\CityOpenTimeService;
use App\Services\OrderService;
use App\Services\OrderVisibilityService;
use App\Services\PartnerApiService;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private SearchService $searchService,
        private OrderVisibilityService $orderVisibility,
        private OrderCityViewService $orderCityViewService,
        private OrderActivityLogService $activityLog,
        private OrderClientHistoryService $clientHistory,
    ) {}

    /**
     * Доступ к изменению/проведению заказа по городу (как у show / getData).
     * developer и general_director — без ограничения по городу; остальные — только свои города.
     */
    private function authorizeOrderAccessForMutation(Order $order): void
    {
        $user = auth()->user();
        if ($user->hasRole('developer') || $user->hasRole('general_director')) {
            return;
        }
        $order->loadMissing('address');
        if (! $order->address) {
            abort(403);
        }
        if (! $user->hasAccessToCity($order->address->city_id)) {
            abort(403, 'Нет доступа к этому заказу');
        }
    }

    /** Диспетчер КЦ может менять источник (РК) на незакрытой заявке. */
    private function userCanEditSource(User $user, Order $order): bool
    {
        if ($order->order_closed_at) {
            return false;
        }

        return $user->hasRole('call_center');
    }

    /** Дальний выезд отмечают только диспетчеры КЦ и старший диспетчер. */
    private function userCanEditLongTrip(User $user): bool
    {
        return $user->hasAnyRole(['call_center', 'senior_dispatcher']);
    }

    /** Дата/время заявки и поле «Переносы» — диспы, ст. дисп, директора. */
    private function userCanEditScheduleFields(User $user): bool
    {
        return $user->hasAnyRole([
            'developer',
            'call_center',
            'senior_dispatcher',
            'general_director',
            'regional_director',
            'branch_head',
        ]);
    }

    /** Редактирование адреса и контактов на карточке — для «Прозвон» / «Не оформлена». */
    private function userCanEditCallbackClientData(User $user, Order $order): bool
    {
        if (! $user->hasAnyRole(['call_center', 'senior_dispatcher'])) {
            return false;
        }

        if ($order->order_closed_at) {
            return false;
        }

        return in_array($order->order_status, ['callback', 'not_processed'], true);
    }

    /**
     * Статусы, допустимые при сохранении: устанавливаемые ролью + текущий статус заказа
     * (диспетчер КЦ может менять дату/переносы и дальний выезд, не меняя статус филиала).
     *
     * @return list<string>
     */
    private function orderStatusesAllowedOnUpdate(User $user, Order $order): array
    {
        $allowed = $this->orderVisibility->getSettableStatuses($user);

        if ($order->order_status && ! in_array($order->order_status, $allowed, true)) {
            $allowed[] = $order->order_status;
        }

        return array_values(array_unique($allowed));
    }

    /** @param  array<string, mixed>  $validated */
    private function applyCallbackClientDataUpdate(Order $order, array $validated, User $user): void
    {
        $order->loadMissing(['address', 'persons.phones']);

        if ($order->address) {
            $order->address->update([
                'city_id' => $validated['address_city_id'] ?? $order->address->city_id,
                'street' => $validated['address_street'] ?? $order->address->street,
                'house' => $validated['address_house'] ?? $order->address->house,
                'flat' => $validated['address_flat'] ?? $order->address->flat,
                'address_adds' => $validated['address_adds'] ?? $order->address->address_adds,
            ]);
        }

        $person = $order->persons->first();
        if (! $person) {
            return;
        }

        if (array_key_exists('client_person_name', $validated) && $validated['client_person_name'] !== null) {
            $person->update(['person_name' => $validated['client_person_name']]);
        }

        if (! empty($validated['client_phone_number'])) {
            $phone = $person->phones->first();
            $phoneData = [
                'phone_number' => $validated['client_phone_number'],
                'phone_adds' => $validated['client_phone_adds'] ?? null,
            ];
            if ($phone) {
                $phone->update($phoneData);
            } else {
                $person->phones()->create($phoneData);
            }
        } elseif (array_key_exists('client_phone_adds', $validated) && $person->phones->first()) {
            $person->phones->first()->update(['phone_adds' => $validated['client_phone_adds']]);
        }
    }

    /**
     * Мастера для селекта в карточке заказа: город заказа + уже работающие в городе + текущий мастер.
     * Не скрываем «занятых» — иначе нельзя назначить мастера, который есть в базе и в списке заказов.
     */
    private function orderMastersForSelect(Order $order): \Illuminate\Support\Collection
    {
        $orderCityId = $order->address?->city_id;
        if (! $orderCityId) {
            return collect();
        }

        $cityGroupIds = City::operationGroupIds((int) $orderCityId);

        $activeMasterIdsInCity = Order::query()
            ->whereHas('address', fn ($q) => $q->whereIn('city_id', $cityGroupIds))
            ->whereNotNull('master_id')
            ->whereNull('order_closed_at')
            ->distinct()
            ->pluck('master_id');

        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('role_code', 'master'))
            ->where('is_active', true)
            ->where(function ($q) use ($cityGroupIds, $activeMasterIdsInCity, $order) {
                $q->whereHas('cities', fn ($c) => $c->whereIn('cities.city_id', $cityGroupIds));
                if ($activeMasterIdsInCity->isNotEmpty()) {
                    $q->orWhereIn('user_id', $activeMasterIdsInCity);
                }
                if ($order->master_id) {
                    $q->orWhere('user_id', $order->master_id);
                }
            })
            ->orderBy('user_name')
            ->get();
    }

    /**
     * Список заказов
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $sessionKey = 'orders_list_filters_'.$user->user_id;

        if ($request->boolean('clear_filters')) {
            session()->forget($sessionKey);

            return redirect()->route('orders.index');
        }

        $searchById = $request->filled('search_id');
        $searchAddress = trim((string) $request->input('search_address', ''));
        $searchName = trim((string) $request->input('search_name', ''));
        $hasTargetedLookup = $searchById
            || $searchAddress !== ''
            || $searchName !== ''
            || $request->filled('status')
            || $request->filled('source_id')
            || $request->boolean('without_source');

        if (! $searchById && count($request->query()) === 0) {
            $saved = session($sessionKey);
            if (is_array($saved) && $saved !== []) {
                unset(
                    $saved['date_from'],
                    $saved['date_to'],
                    $saved['today'],
                    $saved['closed_from'],
                    $saved['closed_to'],
                    $saved['show_closed']
                );
                $saved = array_filter(
                    $saved,
                    fn ($value) => $value !== null && $value !== '' && $value !== []
                );
                if ($saved !== []) {
                    return redirect()->route('orders.index', $saved);
                }
            }
        }

        $query = Order::with(['address.city', 'master', 'creator', 'persons.phones', 'source.city']);

        // Все города (КЦ / разраб / ген.дир / access_all_cities) или свои + спутники
        $orderCityIds = $user->cityIdsForOrdersFilter();
        if ($orderCityIds !== null) {
            if ($orderCityIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereHas('address', fn ($q) => $q->whereIn('city_id', $orderCityIds));
            }
        }

        // Фильтр по статусам, доступным роли (ген.директор видит все — не сужаем по статусу)
        $visibleStatuses = $this->orderVisibility->getVisibleStatuses($user);
        if (! $searchById && ! empty($visibleStatuses)) {
            $query->whereIn('order_status', $visibleStatuses);
        }

        // Закрытые статусы: для диспетчеров — в списке 7 дней после закрытия; для остальных — скрыты без «Закрытые»
        $closedStatuses = ['completed', 'cancelled_cc', 'cancelled_city'];
        $showClosed = $request->boolean('show_closed');
        $usesArchiveWindow = $this->orderVisibility->usesDispatcherArchiveWindow($user);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $hasExplicitDateFrom = is_string($dateFrom) && trim($dateFrom) !== '';
        $hasExplicitDateTo = is_string($dateTo) && trim($dateTo) !== '';

        if ($request->boolean('today')) {
            $dateFrom = now()->format('Y-m-d');
            $dateTo = now()->format('Y-m-d');
            $hasExplicitDateFrom = true;
            $hasExplicitDateTo = true;
        }

        $skipListModeRestrictions = $searchById
            || $hasTargetedLookup
            || $hasExplicitDateFrom
            || $hasExplicitDateTo;

        if (! $skipListModeRestrictions) {
            if ($usesArchiveWindow) {
                if ($showClosed && ! $request->filled('status')) {
                    $query->onlyDispatcherArchiveWithinWindow();
                } elseif (! $request->filled('status')) {
                    $query->visibleWithinDispatcherArchiveWindow();
                } elseif (array_intersect((array) $request->status, Order::dispatcherArchiveStatusCodes())) {
                    $query->whereRaw(
                        'COALESCE(order_closed_at, order_created_at) >= ?',
                        [Order::dispatcherArchiveWindowStart()]
                    );
                }
            } else {
                if (! $showClosed && ! $request->filled('status')) {
                    $query->whereNotIn('order_status', $closedStatuses);
                }

                if ($showClosed && ! $request->filled('status')) {
                    $query->whereIn('order_status', array_merge($closedStatuses, ['rejected']));
                }
            }
        }

        // Применение фильтров из запроса (при поиске по ID — только ID)
        if (! $searchById) {
            if ($request->filled('status')) {
                $query->whereIn('order_status', (array) $request->status);
            }
            if ($request->filled('type')) {
                $query->whereIn('order_type', (array) $request->type);
            }
            if ($request->filled('city_id')) {
                $query->whereHas('address', fn ($q) => $q->whereIn('city_id', (array) $request->city_id));
            }
            if ($request->filled('master_id')) {
                $query->whereIn('master_id', (array) $request->master_id);
            }
            if ($request->filled('created_by')) {
                $query->whereIn('order_created_by', (array) $request->created_by);
            }
            if ($request->boolean('without_source')) {
                $query->whereNull($query->getModel()->getTable().'.source_id');
            } elseif ($request->filled('source_id')) {
                $query->filterBySource($request->source_id);
            }
            if ($request->filled('source_format') && ! $request->boolean('without_source')) {
                $query->filterBySource(null, $request->source_format);
            }
            if ($request->boolean('only_review')) {
                $query->where('order_status', 'review');
            }

            $shouldApplyDateRange = ($hasExplicitDateFrom || $hasExplicitDateTo)
                && $searchAddress === ''
                && $searchName === '';

            if ($shouldApplyDateRange) {
                if ($hasExplicitDateFrom) {
                    $query->whereRaw('COALESCE(datetime_order, order_created_at) >= ?', [$dateFrom]);
                }
                if ($hasExplicitDateTo) {
                    $query->whereRaw('COALESCE(datetime_order, order_created_at) <= ?', [$dateTo.' 23:59:59']);
                }
            }

            if ($request->filled('closed_from')) {
                $query->where('order_closed_at', '>=', $request->closed_from);
            }
            if ($request->filled('closed_to')) {
                $query->where('order_closed_at', '<=', $request->closed_to.' 23:59:59');
            }

            // Фильтр по сумме (amount_paid - amount_comp)
            if ($request->filled('amount_from')) {
                $query->whereRaw('(amount_paid - amount_comp) >= ?', [$request->amount_from]);
            }
            if ($request->filled('amount_to')) {
                $query->whereRaw('(amount_paid - amount_comp) <= ?', [$request->amount_to]);
            }

            // Поиск по имени клиента
            if ($searchName !== '') {
                $query->whereHas('persons', function ($q) use ($searchName) {
                    $q->where('person_name', 'LIKE', '%'.$searchName.'%');
                });
            }

            // Поиск по адресу
            if ($searchAddress !== '') {
                $parts = preg_split('/[\s,]+/u', $searchAddress, -1, PREG_SPLIT_NO_EMPTY);

                $query->whereHas('address', function ($q) use ($parts) {
                    foreach ($parts as $part) {
                        $q->where(function ($subQ) use ($part) {
                            $subQ->where('street', 'LIKE', '%'.$part.'%')
                                ->orWhere('house', 'LIKE', '%'.$part.'%')
                                ->orWhere('flat', 'LIKE', '%'.$part.'%');
                        });
                    }
                });
            }
        }

        if ($searchById) {
            $query->where('order_id', (int) $request->search_id);
        }

        // Сортировка: по умолчанию — приоритет статуса, затем дата/время встречи
        $sortField = $request->get('sort');
        $sortDir = $request->get('dir', 'asc');

        $allowedSortFields = [
            'order_id', 'datetime_order', 'order_status', 'order_type',
            'order_core', 'amount_paid', 'amount_comp', 'order_created_at', 'order_closed_at',
        ];
        $allowedSortDirs = ['asc', 'desc'];
        $usesDispatcherSort = $user->isDispatcher() && ! $user->hasRole('developer');

        if ($sortField && in_array($sortField, $allowedSortFields)) {
            if (! in_array(strtolower($sortDir), $allowedSortDirs)) {
                $sortDir = 'asc';
            }
            // «Время встречи»: приоритет статуса сохраняем, внутри — дата и время
            if ($sortField === 'datetime_order') {
                if ($usesDispatcherSort) {
                    $query->orderByDispatcherListDefault($sortDir);
                } else {
                    $query->orderByListDefault($sortDir);
                }
            } else {
                $query->orderBy($sortField, $sortDir);
            }
        } else {
            if ($usesDispatcherSort) {
                $query->orderByDispatcherListDefault();
            } else {
                $query->orderByListDefault();
            }
        }
        $query->withExists('cityViews');

        $orders = $query->paginate(config('pagination.large', 50));

        // Данные для фильтров
        $userCityIds = $user->cityIdsForOrdersFilter();
        if ($userCityIds === null) {
            $userCityIds = \App\Models\City::where('is_active', true)->pluck('city_id');
        } else {
            $userCityIds = collect($userCityIds);
        }

        $activeMasterIdsInScope = Order::query()
            ->whereHas('address', fn ($q) => $q->whereIn('city_id', $userCityIds))
            ->whereNotNull('master_id')
            ->whereNull('order_closed_at')
            ->distinct()
            ->pluck('master_id');

        $masters = User::whereHas('roles', fn ($q) => $q->where('role_code', 'master'))
            ->where('is_active', true)
            ->where(function ($q) use ($userCityIds, $activeMasterIdsInScope) {
                $q->whereHas('cities', fn ($c) => $c->whereIn('cities.city_id', $userCityIds));
                if ($activeMasterIdsInScope->isNotEmpty()) {
                    $q->orWhereIn('user_id', $activeMasterIdsInScope);
                }
            })
            ->with('cities')
            ->orderBy('user_name')
            ->get()
            ->map(function ($master) {
                $cityNames = $master->cities->pluck('city_name')->join(', ');

                return [
                    'user_id' => $master->user_id,
                    'user_name' => $master->user_name,
                    'city_names' => $cityNames,
                    'display_name' => $master->user_name.($cityNames ? ' ('.$cityNames.')' : ''),
                ];
            });

        // Города пользователя
        $cities = $user->accessibleCities()->get();

        $sources = Source::with('city')->where('is_active', true)->orderBy('source_name')->get();

        $visibleStatuses = $this->orderVisibility->getVisibleStatuses($user);
        $statusesForFilter = $visibleStatuses;
        $legendItems = $this->orderVisibility->getLegendItemsForOrders($user);
        $statusLabels = Order::getStatusLabels();
        $showCreatorColumn = $user->isDispatcher() || $user->hasRole('developer');
        $showClientPhoneColumn = \App\Helpers\PhoneHelper::userSeesFullPhone($user);
        $showSdCopyButton = \App\Helpers\PhoneHelper::userCanCopySdLink($user);
        $dispatchCreators = $showCreatorColumn
            ? User::whereHas('roles', fn ($q) => $q->whereIn('role_code', ['call_center', 'senior_dispatcher']))
                ->where('is_active', true)
                ->orderBy('user_name')
                ->get()
            : collect();

        $displayDateFrom = $hasExplicitDateFrom ? $dateFrom : '';
        $displayDateTo = $hasExplicitDateTo ? $dateTo : '';

        if (! $searchById) {
            $savedFilters = array_filter(
                $request->only([
                    'status', 'type', 'city_id', 'master_id', 'created_by',
                    'source_id', 'source_format', 'without_source', 'only_review',
                    'amount_from', 'amount_to', 'search_name', 'search_address', 'search_id',
                    'sort', 'dir',
                ]),
                fn ($value) => $value !== null && $value !== '' && $value !== []
            );
            session([$sessionKey => $savedFilters]);
        }

        return view('orders.index', compact(
            'orders', 'masters', 'cities', 'sources', 'displayDateFrom', 'displayDateTo', 'visibleStatuses', 'statusesForFilter',
            'legendItems', 'statusLabels', 'showClosed', 'showCreatorColumn', 'showClientPhoneColumn', 'showSdCopyButton', 'dispatchCreators', 'hasExplicitDateFrom', 'hasExplicitDateTo'
        ));
    }

    /**
     * Форма создания заказа
     */
    public function create(int $person_id)
    {
        $person = \App\Models\Person::with(['phones', 'addresses.city'])->findOrFail($person_id);
        $sources = Source::with('city')->where('is_active', true)->orderBy('source_name')->get();
        $user = auth()->user();
        $statusesForCreate = $this->orderVisibility->getAllowedStatusesForOrderCreate($user);
        $statusLabels = Order::getStatusLabels();

        $cityOpenTimeService = app(CityOpenTimeService::class);
        $cityOpenTimesByCityId = [];
        foreach ($person->addresses as $address) {
            $payload = $cityOpenTimeService->relevantPayloadForCity((int) $address->city_id);
            if ($payload) {
                $cityOpenTimesByCityId[$address->city_id] = $payload;
            }
        }

        return view('orders.create', compact(
            'person', 'sources', 'statusesForCreate', 'statusLabels', 'cityOpenTimesByCityId'
        ));
    }

    /**
     * Создание заказа
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $allowedStatuses = $this->orderVisibility->getAllowedStatusesForOrderCreate($user);

        $validated = $request->validate([
            'person_id' => 'required|exists:persons,person_id',
            'address_id' => 'required|exists:addresses,address_id',
            'datetime_order' => 'nullable|date',
            'order_date' => 'nullable|date',
            'order_time' => 'nullable',
            'order_type' => 'required|in:new,repeat,warranty',
            'order_core' => 'nullable|in:core,non_core,other',
            'equipment_type' => 'nullable|in:'.implode(',', array_keys(Order::EQUIPMENT_TYPES)),
            'order_status' => 'required|in:'.implode(',', $allowedStatuses),
            'order_adds' => 'nullable|string',
            'source_id' => 'required|exists:sources,source_id',
            'partner_user_id' => 'nullable|integer|min:1',
        ]);

        if ($this->userCanEditLongTrip($user)) {
            $request->validate(['is_long_trip' => 'nullable|in:0,1']);
        }

        $datetimeOrder = $validated['datetime_order'] ?? null;
        if (! $datetimeOrder && $request->filled('order_date') && $request->filled('order_time')) {
            $datetimeOrder = $request->order_date.' '.$request->order_time;
        }

        // Профильность: из формы или авто по виду техники (Order::CORE_EQUIPMENT / getOrderCoreByEquipment)
        $orderCore = $validated['order_core'] ?? null;
        if (! empty($validated['equipment_type'])) {
            $orderCore = Order::getOrderCoreByEquipment($validated['equipment_type']);
        }
        $orderCore = $orderCore ?: 'core';

        // Создаём заказ
        $order = new Order([
            'address_id' => $validated['address_id'],
            'datetime_order' => $datetimeOrder,
            'order_type' => $validated['order_type'],
            'order_core' => $orderCore,
            'equipment_type' => $validated['equipment_type'] ?? null,
            'order_status' => $validated['order_status'],
            'order_adds' => $validated['order_adds'] ?? null,
            'source_id' => $validated['source_id'] ?? null,
            'is_long_trip' => $this->userCanEditLongTrip($user) && (int) $request->input('is_long_trip', 0) === 1,
            'order_created_at' => now(),
            'master_id' => null,
            'amount_paid' => 0,
            'amount_comp' => 0,
        ]);
        $order->order_created_by = auth()->id();
        $order->save();

        // Привязываем персону к заказу
        $order->persons()->attach($validated['person_id']);

        $partnerApi = app(PartnerApiService::class);
        if (! empty($validated['partner_user_id'])) {
            $order->partner_user_id = (int) $validated['partner_user_id'];
            $order->save();
        }
        $partnerApi->pushCreatedToSuperpart($order->fresh());

        $this->activityLog->logCreated($order, auth()->user());

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Заказ создан',
                'order_id' => $order->order_id,
            ]);
        }

        return redirect()->route('orders.show', $order->order_id)->with('success', 'Заказ создан');
    }

    /**
     * Получить данные заказа (AJAX для модального окна)
     */
    public function getData(int $order_id)
    {
        $order = Order::with([
            'address.city',
            'master',
            'source.city',
            'persons.phones',
        ])->findOrFail($order_id);

        // Проверка доступа: разработчик и ген.директор — все заказы; остальные — по городу
        $user = auth()->user();
        if (! $user->hasRole('developer') && ! $user->hasRole('general_director') && ! $user->hasAccessToCity($order->address->city_id)) {
            return response()->json(['success' => false, 'message' => 'Нет доступа'], 403);
        }

        $canEditClosedOrder = $user->hasAnyRole(['developer', 'senior_dispatcher', 'general_director']);
        $canEditCcFields = $user->hasAnyRole(['developer', 'call_center', 'senior_dispatcher', 'general_director']);
        $canEditScheduleFields = $this->userCanEditScheduleFields($user);
        $canEditCityFields = $user->hasAnyRole(['developer', 'senior_dispatcher', 'general_director']) ||
            (! $user->hasRole('call_center') && ! $user->hasRole('tech_director'));
        $isEditable = ! $order->order_closed_at || $canEditClosedOrder;
        $isDeveloper = $user->hasRole('developer');

        // Текущее время в часовом поясе города
        $cityTimezone = $order->address->city->city_timezone ?? 'Asia/Almaty';
        $cityTime = now()->setTimezone($cityTimezone)->format('H:i');

        $visibleStatuses = $this->orderVisibility->getVisibleStatuses($user);
        $settableStatuses = $this->orderVisibility->getSettableStatuses($user);
        $statusLabels = Order::getStatusLabels();

        return response()->json([
            'success' => true,
            'visibleStatuses' => $visibleStatuses,
            'settableStatuses' => $settableStatuses,
            'statusLabels' => $statusLabels,
            'order' => [
                'order_id' => $order->order_id,
                'order_type' => $order->order_type,
                'order_core' => $order->order_core,
                'is_long_trip' => (bool) $order->is_long_trip,
                'equipment_type' => $order->equipment_type,
                'order_status' => $order->order_status,
                'datetime_order' => $order->datetime_order->format('Y-m-d\TH:i'),
                'order_adds' => $order->order_adds,
                'shift_adds' => $order->shift_adds,
                'city_adds' => $order->city_adds,
                'master_id' => $order->master_id,
                'master_name' => $order->master?->user_name,
                'amount_paid' => $order->amount_paid,
                'amount_comp' => $order->amount_comp,
                'source_id' => $order->source_id,
                'source_name' => $order->source?->display_label,
                'order_closed_at' => $order->order_closed_at?->format('d.m.Y H:i'),
                'city_time' => $cityTime,
                'city_name' => $order->address->city->city_name,
                'address' => [
                    'city_name' => $order->address->city->city_name,
                    'street' => $order->address->street,
                    'house' => $order->address->house,
                    'flat' => $order->address->flat,
                ],
            ],
            'permissions' => [
                'canEditCcFields' => $canEditCcFields && $isEditable,
                'canEditScheduleFields' => $canEditScheduleFields && $isEditable,
                'canEditCityFields' => $canEditCityFields && $isEditable,
                'isEditable' => $isEditable,
                'isDeveloper' => $isDeveloper,
            ],
        ]);
    }

    /**
     * Страница заказа
     */
    public function show(int $order_id)
    {
        $order = Order::with([
            'address.city.parentCity',
            'master',
            'creator',
            'closedBy',
            'source.city',
            'persons.phones',
            'persons.addresses',
            'documents',
        ])->findOrFail($order_id);

        $user = auth()->user();
        $order->loadMissing('address');
        if (! $user->hasRole('developer') && ! $user->hasRole('general_director')) {
            if (! $order->address || ! $user->hasAccessToCity($order->address->city_id)) {
                abort(403);
            }
        }

        $this->activityLog->loadLogsForOrder($order);

        $this->orderCityViewService->recordFirstView($order, $user);
        $orderCityViewLines = $this->orderCityViewService->getViewLinesForOrderCard($order->fresh());

        $canComplete = $this->orderService->canComplete($order);

        // Флаг редактируемости
        $isDeveloper = $user->hasRole('developer');
        $canEditClosedOrder = $user->hasAnyRole(['developer', 'senior_dispatcher', 'general_director']);
        $isEditable = ! $order->order_closed_at || $canEditClosedOrder;
        $canReopenClosedOrder = $user->hasAnyRole(['developer', 'regional_director', 'senior_dispatcher', 'general_director']);

        $canEditCcFields = $user->hasAnyRole(['developer', 'call_center', 'senior_dispatcher', 'general_director']);
        $canEditScheduleFields = $this->userCanEditScheduleFields($user);
        $canEditCityFields = $user->hasAnyRole(['developer', 'senior_dispatcher', 'general_director']) ||
            (! $user->hasRole('call_center') && ! $user->hasRole('tech_director'));

        // Расчёты: для проведённых и для ролей с доступом к суммам (превью до проведения)
        $calculations = null;
        if ($canEditCityFields || $order->order_closed_at) {
            $order->loadMissing(['address.city.parentCity', 'persons.addresses.city']);
            $calculations = [
                'net_amount' => $this->orderService->getNetAmount($order),
                'master_percent' => $this->orderService->getMasterPercent($order),
                'master_salary' => $this->orderService->calculateMasterSalary($order),
                'amount_to_pay' => $this->orderService->calculateAmountToPay($order),
                'is_satellite' => $order->isSatelliteCityOrder(),
            ];
        }

        // История заказов клиента (как в КП)
        $personIds = $order->persons->pluck('person_id')->all();
        $canViewClientHistory = $this->activityLog->canViewClientHistory($user);
        $orderHistory = $canViewClientHistory
            ? $this->clientHistory->forPersonIds($personIds, $user, $order_id)
            : collect();

        $masters = $this->orderMastersForSelect($order);

        $sources = Source::with('city')->where('is_active', true)->orderBy('source_name')->get();

        $visibleStatuses = $this->orderVisibility->getVisibleStatuses($user);
        $settableStatuses = $this->orderVisibility->getSettableStatuses($user);
        // Для селекта: устанавливаемые; если текущий статус не в списке (например ген.дир) — добавляем для отображения
        $statusesForSelect = $settableStatuses;
        if ($order->order_status && ! in_array($order->order_status, $settableStatuses)) {
            $statusesForSelect = array_values(array_unique(array_merge([$order->order_status], $settableStatuses)));
        }

        $statusLabels = Order::getStatusLabels();

        $canViewActivityLog = $this->activityLog->canViewActivityLog($user)
            && $order->relationLoaded('activityLogs');

        $canEditLongTrip = $this->userCanEditLongTrip($user);
        $canEditCallbackClientData = $this->userCanEditCallbackClientData($user, $order) && $isEditable;
        $canEditSource = $this->userCanEditSource($user, $order);
        $clientCities = ($canEditCallbackClientData || ($isEditable && $canEditCityFields))
            ? City::where('is_active', true)->orderBy('city_name')->get(['city_id', 'city_name'])
            : collect();
        $callbackPerson = $order->persons->first();
        $callbackPhone = $callbackPerson?->phones->first();

        $cityOpenTime = null;
        if ($user->hasAnyRole(['call_center', 'senior_dispatcher', 'developer'])) {
            $cityOpenTime = app(CityOpenTimeService::class)->relevantPayloadForCity($order->address->city_id);
        }

        return view('orders.show', compact(
            'order', 'calculations', 'canComplete', 'orderHistory', 'masters', 'sources',
            'isEditable', 'isDeveloper', 'canReopenClosedOrder', 'canEditCcFields', 'canEditScheduleFields', 'canEditCityFields',
            'canEditLongTrip', 'canEditCallbackClientData', 'canEditSource', 'clientCities', 'callbackPerson', 'callbackPhone',
            'visibleStatuses', 'settableStatuses', 'statusesForSelect', 'statusLabels',
            'orderCityViewLines', 'canViewActivityLog', 'canViewClientHistory', 'cityOpenTime'
        ));
    }

    /**
     * Обновление заказа
     */
    public function update(Request $request, int $order_id)
    {
        $order = Order::findOrFail($order_id);
        $this->authorizeOrderAccessForMutation($order);
        $user = auth()->user();

        if ($user->hasRole('tech_director') && ! $user->hasRole('developer')) {
            abort(403, 'У вас нет прав на редактирование');
        }

        if ($order->order_closed_at && ! $user->hasAnyRole(['developer', 'senior_dispatcher', 'general_director'])) {
            abort(403, 'Проведённый заказ нельзя редактировать');
        }

        $isEditable = ! $order->order_closed_at || $user->hasAnyRole(['developer', 'senior_dispatcher', 'general_director']);
        $canEditCcFields = $user->hasAnyRole(['developer', 'call_center', 'senior_dispatcher', 'general_director']);
        $canEditScheduleFields = $this->userCanEditScheduleFields($user);
        $canEditCityFields = $user->hasAnyRole(['developer', 'senior_dispatcher', 'general_director']) ||
            (! $user->hasRole('call_center') && ! $user->hasRole('tech_director'));
        $canEditLongTrip = $this->userCanEditLongTrip($user);
        $canEditSource = $this->userCanEditSource($user, $order);
        $canRefreshClosedCfm = $order->order_closed_at && $user->hasAnyRole(['developer', 'senior_dispatcher', 'general_director']);

        $rules = [];

        if ($canEditScheduleFields && $isEditable) {
            $rules['datetime_order'] = 'required|date';
            $rules['shift_adds'] = 'nullable|string';
        }

        // Правила для полей КЦ (только если пользователь может их редактировать)
        if ($canEditCcFields && $isEditable) {
            if (! array_key_exists('datetime_order', $rules)) {
                $rules['datetime_order'] = 'required|date';
            }
            $rules['order_type'] = 'required|in:new,repeat,warranty';
            $rules['order_core'] = 'required|in:core,non_core,other';
            $rules['equipment_type'] = 'nullable|in:'.implode(',', array_keys(Order::EQUIPMENT_TYPES));
            $rules['order_adds'] = 'nullable|string';
            if (! array_key_exists('shift_adds', $rules)) {
                $rules['shift_adds'] = 'nullable|string';
            }
        }

        if ($canEditLongTrip && $isEditable) {
            $rules['is_long_trip'] = 'nullable|in:0,1';
        }

        // Статус могут менять только роли с непустым списком устанавливаемых статусов
        $allowedStatuses = $this->orderStatusesAllowedOnUpdate($user, $order);
        if ($isEditable && $allowedStatuses !== []) {
            $rules['order_status'] = 'required|in:'.implode(',', $allowedStatuses);
        }

        // Правила для полей города (только если пользователь может их редактировать)
        if ($canEditCityFields && $isEditable) {
            $rules['master_id'] = 'nullable|exists:users,user_id';
            $rules['source_id'] = 'nullable|exists:sources,source_id';
            $rules['amount_paid'] = 'required|integer|min:0';
            $rules['amount_comp'] = 'required|integer|min:0';
            $rules['city_adds'] = 'nullable|string';
            $rules['address_city_id'] = 'required|exists:cities,city_id';
        } elseif ($canEditSource && $isEditable) {
            $rules['source_id'] = 'nullable|exists:sources,source_id';
        }

        if ($this->userCanEditCallbackClientData($user, $order)) {
            $rules['client_person_name'] = 'required|string|max:255';
            $rules['client_phone_number'] = ['nullable', 'string', 'max:20'];
            $rules['client_phone_adds'] = 'nullable|string|max:255';
            $rules['address_city_id'] = 'required|exists:cities,city_id';
            $rules['address_street'] = ['nullable', 'string', 'max:255', PersonClientValidation::ADDRESS_FRAGMENT];
            $rules['address_house'] = ['nullable', 'string', 'max:50', PersonClientValidation::ADDRESS_FRAGMENT];
            $rules['address_flat'] = ['nullable', 'string', 'max:50', PersonClientValidation::ADDRESS_FRAGMENT];
            $rules['address_adds'] = ['nullable', 'string', 'max:'.PersonClientValidation::ADDRESS_ADDS_MAX, PersonClientValidation::ADDRESS_FRAGMENT];
        }

        $validated = $request->validate($rules);

        if (! empty($validated['equipment_type'])) {
            $validated['order_core'] = Order::getOrderCoreByEquipment($validated['equipment_type']);
        }

        if (array_key_exists('is_long_trip', $rules)) {
            $validated['is_long_trip'] = (int) $request->input('is_long_trip', 0) === 1;
        }

        $addressCityChanged = false;

        if ($this->userCanEditCallbackClientData($user, $order)) {
            if (! empty($validated['client_phone_number'])) {
                $digits = preg_replace('/\D/', '', $validated['client_phone_number']);
                if (strlen($digits) === 11 && $digits[0] === '8') {
                    $digits = substr($digits, 1);
                }
                if (strlen($digits) === 11 && $digits[0] === '7') {
                    $digits = substr($digits, 1);
                }
                $validated['client_phone_number'] = strlen($digits) === 10 ? $digits : null;
            }
            $beforeCityId = (int) ($order->address?->city_id ?? 0);
            $this->applyCallbackClientDataUpdate($order, $validated, $user);
            $addressCityChanged = $beforeCityId !== (int) ($order->address?->fresh()?->city_id ?? 0);
            unset(
                $validated['client_person_name'],
                $validated['client_phone_number'],
                $validated['client_phone_adds'],
                $validated['address_city_id'],
                $validated['address_street'],
                $validated['address_house'],
                $validated['address_flat'],
                $validated['address_adds'],
            );
        } elseif ($canEditCityFields && $isEditable && array_key_exists('address_city_id', $validated) && $order->address) {
            $beforeCityId = (int) $order->address->city_id;
            $order->address->update(['city_id' => $validated['address_city_id']]);
            $addressCityChanged = $beforeCityId !== (int) $validated['address_city_id'];
            unset($validated['address_city_id']);
        }

        // Устанавливаем amount_deposit = 0 (больше не используется)
        if ($canEditCityFields) {
            $validated['amount_deposit'] = 0;
        }

        $before = $order->only(array_keys($validated));
        $previousSourceId = $order->source_id;
        $previousPartnerUserId = $order->partner_user_id;

        if (
            $canEditScheduleFields
            && $isEditable
            && array_key_exists('datetime_order', $validated)
            && $order->datetime_order !== null
        ) {
            $cityTz = $order->address?->city?->city_timezone;
            $newDatetime = OrderRescheduleLog::parseSubmittedDatetime($validated['datetime_order'], $cityTz);
            $shiftBase = array_key_exists('shift_adds', $validated)
                ? $validated['shift_adds']
                : $order->shift_adds;
            $appendedShift = OrderRescheduleLog::appendOnDatetimeChange(
                $order,
                $order->datetime_order,
                $newDatetime,
                $user,
                $shiftBase
            );
            if ($appendedShift !== null) {
                $validated['shift_adds'] = $appendedShift;
            }
            $validated['datetime_order'] = $newDatetime;
        }

        $deliverEventId = null;
        $notifyCompleted = false;

        DB::transaction(function () use (
            $order,
            $validated,
            $before,
            $user,
            $previousSourceId,
            $previousPartnerUserId,
            &$deliverEventId,
            &$notifyCompleted
        ) {
            $order->update($validated);

            if (
                array_key_exists('order_status', $validated)
                && (string) ($before['order_status'] ?? '') !== (string) $validated['order_status']
            ) {
                $order->status_changed_at = now();
                if ($validated['order_status'] === 'in_progress' && $order->in_progress_at === null) {
                    $order->in_progress_at = now();
                }
                $order->save();
            }

            $this->activityLog->logChanges($order, $user, $before, $validated);

            $partnerApi = app(PartnerApiService::class);
            $sourceChanged = array_key_exists('source_id', $validated)
                && (int) ($validated['source_id'] ?? 0) !== (int) ($previousSourceId ?? 0);
            $statusChanged = array_key_exists('order_status', $validated)
                && (string) ($before['order_status'] ?? '') !== (string) ($validated['order_status'] ?? '');
            // ТЗ FR-SYNC-02: outbox и на деньги / вид работ / закрытие, не только source/status.
            $snapshotFieldChanged = collect([
                'amount_paid',
                'amount_comp',
                'equipment_type',
                'order_core',
                'order_type',
                'order_closed_at',
                'partner_user_id',
            ])->contains(fn (string $field) => array_key_exists($field, $validated));

            if ($sourceChanged || $statusChanged || array_key_exists('source_id', $validated) || $snapshotFieldChanged) {
                $fresh = $order->fresh();
                $partnerApi->syncOrderPartnerFromSource($fresh);
                $fresh = $order->fresh();
                $partnerUserId = $partnerApi->resolvePartnerUserId($fresh);

                if ($partnerUserId) {
                    $deliverEventId = $partnerApi->enqueueSnapshotForOrder($fresh);
                    if ($fresh->order_closed_at && in_array((string) $fresh->order_status, ['completed', 'waiting_payment'], true)) {
                        $notifyCompleted = true;
                    }
                } elseif ($previousPartnerUserId && ($sourceChanged || $snapshotFieldChanged)) {
                    $deliverEventId = $partnerApi->enqueueSnapshotForOrder($fresh, (int) $previousPartnerUserId);
                }
            }
        });

        if (is_string($deliverEventId) && $deliverEventId !== '') {
            try {
                app(\App\Services\SuperpartOutboxDeliverer::class)->deliverEventId($deliverEventId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('OrderController::update: SuperPart deliver failed', [
                    'order_id' => $order->order_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
        if ($notifyCompleted) {
            try {
                \App\Jobs\NotifySuperpartOrderCompletedJob::dispatchSync((int) $order->order_id);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('OrderController::update: completed notify failed', [
                    'order_id' => $order->order_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $gmSyncFields = [
            'master_id',
            'datetime_order',
            'order_type',
            'order_core',
            'equipment_type',
            'order_status',
            'order_adds',
            'shift_adds',
            'city_adds',
            'amount_paid',
            'amount_comp',
        ];
        if (collect(array_keys($validated))->intersect($gmSyncFields)->isNotEmpty()) {
            \App\Jobs\SyncOrderToGmJob::dispatch((int) $order->order_id);
        }

        $financialFields = ['amount_paid', 'amount_comp', 'order_core', 'order_type', 'is_long_trip', 'city_adds', 'order_adds'];
        if (($canRefreshClosedCfm ?? false) && ($addressCityChanged || array_intersect(array_keys($validated), $financialFields))) {
            $this->orderService->refreshClosedOrderCfm($order->fresh(['master', 'address.city.parentCity', 'persons.addresses.city']), $user->user_id);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Заказ сохранён']);
        }

        // Если нажата кнопка "Сохранить и закрыть" - редирект на список
        if ($request->has('save_and_close')) {
            return redirect()->route('orders.index')->with('success', 'Заказ сохранён');
        }

        return back()->with('success', 'Заказ сохранён');
    }

    /**
     * Проведение заказа (с предварительным сохранением сумм из формы, если переданы).
     */
    public function complete(Request $request, int $order_id)
    {
        $order = Order::findOrFail($order_id);
        $this->authorizeOrderAccessForMutation($order);
        $user = auth()->user();

        if (! $order->order_closed_at) {
            $canEditCityFields = $user->hasRole('developer') || $user->hasRole('senior_dispatcher') ||
                (! $user->hasRole('call_center') && ! $user->hasRole('tech_director'));

            if ($canEditCityFields) {
                $cityRules = [
                    'master_id' => 'nullable|exists:users,user_id',
                    'amount_paid' => 'nullable|integer|min:0',
                    'amount_comp' => 'nullable|integer|min:0',
                    'source_id' => 'nullable|exists:sources,source_id',
                    'city_adds' => 'nullable|string',
                ];
                $cityData = $request->validate($cityRules);
                $order->fill(array_intersect_key($cityData, array_flip(array_keys($cityRules))));
                $order->save();
                app(PartnerApiService::class)->syncOrderPartnerFromSource($order->fresh());
                $order->refresh();
                try {
                    app(PartnerApiService::class)->pushCreatedToSuperpart($order);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('OrderController::complete: SuperPart push failed', [
                        'order_id' => $order->order_id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $canComplete = $this->orderService->canComplete($order);
        if (! $canComplete['can']) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => implode('. ', $canComplete['errors'])], 422);
            }

            return back()->with('error', implode('. ', $canComplete['errors']));
        }

        $this->orderService->complete($order, auth()->id());
        $this->activityLog->logCompleted($order->fresh(), $user);
        \App\Jobs\SyncOrderToGmJob::dispatch((int) $order->order_id);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Заказ проведён']);
        }

        return back()->with('success', 'Заказ проведён');
    }

    /**
     * Открытие проведённого заказа (разработчик, рег. директор, старший диспетчер).
     * Сбрасывает order_closed_at и order_closed_by.
     */
    public function reopen(int $order_id)
    {
        $order = Order::findOrFail($order_id);
        $this->authorizeOrderAccessForMutation($order);
        $user = auth()->user();

        if (! $user->hasAnyRole(['developer', 'regional_director', 'senior_dispatcher', 'general_director'])) {
            abort(403, 'Нет прав на открытие проведённого заказа');
        }

        if (! $order->order_closed_at) {
            return back()->with('error', 'Заказ не был проведён');
        }

        try {
            app(\App\Services\ReportCityDailyService::class)->markStaleForOrder($order);
        } catch (\Throwable) {
        }

        // Удаляем связанные кассовые операции
        \App\Models\CfmOperation::where('related_order_id', $order_id)->delete();

        $order->order_closed_at = null;
        $order->order_closed_by = null;
        $order->save();

        $this->activityLog->log($order, auth()->user(), 'reopened', null, null, 'Заказ снова открыт для редактирования');
        \App\Jobs\SyncOrderToGmJob::dispatch((int) $order->order_id);

        return back()->with('success', 'Заказ открыт для редактирования');
    }
}
