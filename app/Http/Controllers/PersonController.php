<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Address;
use App\Models\City;
use App\Models\PersonPhone;
use App\Models\Order;
use App\Contracts\AtsProviderInterface;
use App\Services\PersonService;
use App\Services\OrderActivityLogService;
use App\Services\OrderClientHistoryService;
use App\Services\OrderVisibilityService;
use App\Services\CityOpenTimeService;
use App\Services\MangoCallService;
use App\Services\PartnerApiService;
use App\Support\PersonClientValidation;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function __construct(
        private PersonService $personService,
        private AtsProviderInterface $atsProvider,
        private MangoCallService $mangoCallService,
        private OrderVisibilityService $orderVisibility,
        private OrderClientHistoryService $clientHistory,
        private OrderActivityLogService $activityLog,
    ) {}
    
    /**
     * Страница поиска персон
     */
    public function index()
    {
        // Загружаем все города для формы создания
        $cities = \App\Models\City::orderBy('city_name')->get();
        // Загружаем источники для формы заказа
        $sources = \App\Models\Source::with('city')->where('is_active', true)->orderBy('source_name')->get();
        return view('persons.index', compact('cities', 'sources'));
    }

    /**
     * Отдельная страница создания записи + заказа
     */
    public function create()
    {
        $cities = \App\Models\City::orderBy('city_name')->get();
        $sources = \App\Models\Source::with('city')->where('is_active', true)->orderBy('source_name')->get();
        $statusesForCreate = $this->orderVisibility->getAllowedStatusesForOrderCreate(auth()->user());
        $statusLabels = Order::getStatusLabels();

        return view('persons.create', compact('cities', 'sources', 'statusesForCreate', 'statusLabels'));
    }
    
    /**
     * Поиск персон (AJAX)
     */
    public function search(Request $request)
    {
        $orderId = $request->input('order_id');
        $phone = $request->input('phone');
        $address = $request->input('address');

        if ($orderId !== null && $orderId !== '' && !ctype_digit((string) $orderId)) {
            return response()->json(['message' => 'ID заказа — только цифры'], 422);
        }
        if ($phone !== null && $phone !== '' && !preg_match('/^\d{10}$/', (string) $phone)) {
            return response()->json(['message' => 'Телефон — 10 цифр (код после +7)'], 422);
        }

        try {
            $results = $this->personService->search($orderId, $phone, $address);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Неверный ввод данных'], 422);
        }

        return response()->json($results);
    }
    
    /**
     * Карточка персоны
     */
    public function show(int $person_id)
    {
        $person = Person::with([
            'phones',
            'addresses.city',
            'calls' => fn ($q) => $q->orderBy('call_created_at', 'desc')->limit(50),
            'calls.operator',
            'calls.source.city',
        ])->findOrFail($person_id);

        $user = auth()->user();
        $canViewClientHistory = $this->activityLog->canViewClientHistory($user);
        $orderHistory = $canViewClientHistory
            ? $this->clientHistory->forPersonIds([$person_id], $user)
            : collect();
        $statusLabels = Order::getStatusLabels();
        
        // Загружаем все города для форм добавления адреса
        $cities = \App\Models\City::orderBy('city_name')->get();
        // Загружаем источники для формы заказа (только активные)
        $sources = \App\Models\Source::with('city')->where('is_active', true)->orderBy('source_name')->get();

        // Проверяем настроена ли интеграция с АТС (любой провайдер: Mango, другая АТС)
        $atsConfigured = $this->atsProvider->isConfigured();
        
        // Источник по умолчанию при создании заказа — из последнего звонка с известным источником
        $defaultSourceIdForOrder = $person->calls->first(fn($c) => $c->source_id)?->source_id;
        
        return view('persons.show', compact(
            'person', 'cities', 'sources', 'atsConfigured', 'defaultSourceIdForOrder',
            'orderHistory', 'statusLabels', 'canViewClientHistory',
        ));
    }
    
    /**
     * Получить время города (AJAX)
     */
    public function getCityTime(Request $request, int $city_id)
    {
        $city = City::findOrFail($city_id);
        $timezone = $city->city_timezone ?? 'Asia/Almaty';
        $currentTime = now()->setTimezone($timezone)->format('H:i');

        $onDate = $request->filled('date')
            ? \Carbon\Carbon::parse($request->input('date'))
            : null;

        $openTime = app(CityOpenTimeService::class)->relevantPayloadForCity($city_id, $onDate);

        return response()->json([
            'success' => true,
            'city_name' => $city->city_name,
            'time' => $currentTime,
            'open_time' => $openTime,
        ]);
    }
    
    /**
     * Создание персоны (AJAX)
     */
    public function store(Request $request)
    {
        $allowedOrderStatuses = $this->orderVisibility->getAllowedStatusesForOrderCreate(auth()->user());

        $validated = $request->validate([
            'person_name' => 'required|string|max:255',
            'person_age' => 'nullable|integer|min:0|max:150',
            'phone_number' => ['required', 'string', PersonClientValidation::PHONE_DIGITS_10],
            'city_id' => 'required|exists:cities,city_id',
            'street' => ['nullable', 'string', 'max:255', PersonClientValidation::ADDRESS_FRAGMENT],
            'house' => ['nullable', 'string', 'max:50', PersonClientValidation::ADDRESS_FRAGMENT],
            'flat' => ['nullable', 'string', 'max:50', PersonClientValidation::ADDRESS_FRAGMENT],
            'address_adds' => ['nullable', 'string', 'max:' . PersonClientValidation::ADDRESS_ADDS_MAX, PersonClientValidation::ADDRESS_FRAGMENT],
            // Поля заказа (опциональные)
            'order_type' => 'nullable|in:new,repeat,warranty',
            'order_core' => 'nullable|in:core,non_core,other',
            'order_status' => 'nullable|in:' . implode(',', $allowedOrderStatuses),
            'datetime_order' => 'nullable|date',
            'order_adds' => 'nullable|string',
            'source_id' => 'required_with:order_type|exists:sources,source_id',
            'equipment_type' => 'nullable|in:' . implode(',', array_keys(Order::EQUIPMENT_TYPES)),
        ]);
        
        $person = $this->personService->create($validated);
        
        // Если переданы данные заказа, создаём заказ
        $orderId = null;
        if ($request->filled('order_type')) {
            $address = $person->addresses->first();
            
            if (!$address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка: у персоны нет адреса'
                ], 400);
            }
            
            $orderCore = $validated['order_core'] ?? 'core';
            if (!empty($validated['equipment_type'])) {
                $orderCore = Order::getOrderCoreByEquipment($validated['equipment_type']);
            }

            $order = new \App\Models\Order([
                'order_type' => $validated['order_type'],
                'order_core' => $orderCore,
                'order_status' => $validated['order_status'] ?? 'pending',
                'datetime_order' => $validated['datetime_order'],
                'order_adds' => $validated['order_adds'] ?? null,
                'source_id' => $validated['source_id'] ?? null,
                'address_id' => $address->address_id,
                'equipment_type' => $validated['equipment_type'] ?? null,
                'order_created_at' => now(),
                'amount_paid' => 0,
                'amount_comp' => 0,
            ]);
            $order->order_created_by = auth()->id();
            $order->save();
            
            // Связываем заказ с персоной через pivot таблицу
            $order->persons()->attach($person->person_id);

            // Тот же путь, что orders.store: иначе SP-заявки из «Клиент+заказ» часами не появляются.
            app(PartnerApiService::class)->pushCreatedToSuperpart($order->fresh());
            $this->activityLog->logCreated($order->fresh(['address.city', 'source', 'persons']), auth()->user());
            
            $orderId = $order->order_id;
        }
        
        $message = 'Персона создана';
        if ($orderId) {
            $message .= ". Заказ №{$orderId} создан";
        }
        
        return response()->json([
            'success' => true,
            'person' => $person,
            'message' => $message
        ]);
    }
    
    /**
     * Обновление персоны (AJAX)
     */
    public function update(Request $request, int $person_id)
    {
        $person = Person::findOrFail($person_id);
        
        $validated = $request->validate([
            'person_name' => 'required|string|max:255',
            'person_age' => 'nullable|integer|min:0|max:150',
        ]);
        
        $person->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Данные обновлены'
        ]);
    }
    
    /**
     * Инициация звонка через АТС (AJAX)
     */
    public function initiateCall(Request $request, int $person_id)
    {
        $person = Person::with('phones')->findOrFail($person_id);
        
        $validated = $request->validate([
            'phone_id' => 'required|exists:person_phones,phone_id',
            'extension' => 'required|string|max:20',
        ]);
        
        // Проверяем, что телефон принадлежит этой персоне
        $phone = $person->phones->firstWhere('phone_id', $validated['phone_id']);
        
        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Телефон не найден у этой персоны'
            ], 400);
        }
        
        // Проверяем настройку интеграции с АТС
        if (!$this->atsProvider->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Интеграция с АТС не настроена'
            ], 400);
        }
        
        // Инициируем звонок через провайдер АТС
        $result = $this->atsProvider->initiateCall(
            $validated['extension'],
            $phone->phone_number
        );
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Звонок инициирован. Ожидайте соединения.'
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Ошибка при инициации звонка'
        ], 500);
    }
}
