@extends('layouts.app')

@section('title', "Заказ №{$order->order_id}")

@section('content')
@php
    $isClosedOrderStatus = in_array($order->order_status, ['completed', 'cancelled_cc', 'cancelled_city'], true);
    $canUploadDocsOnClosed = auth()->user()->hasAnyRole(['developer', 'senior_dispatcher', 'general_director']);
    $canEditDocuments = ! $isClosedOrderStatus || $canUploadDocsOnClosed;
    $activityLogService = app(\App\Services\OrderActivityLogService::class);
@endphp

    <div class="mb-4">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Заказы', 'url' => route('orders.index')],
                ['label' => 'Заказ №' . $order->order_id, 'url' => null],
            ]"
        />
    </div>

    <div class="card mb-4 p-4">
        <h3 class="mb-2 text-base font-semibold">Просмотр филиалом</h3>
        @if (!empty($orderCityViewLines))
            <p class="mb-2 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                <span class="orders-view-badge orders-view-badge--seen inline-flex items-center gap-1">Просмотрено</span>
            </p>
            <ul class="list-none space-y-1.5 text-sm text-foreground">
                @foreach ($orderCityViewLines as $line)
                    <li>
                        <span class="font-medium">{{ $line['name'] }}</span>
                        <span class="text-muted-foreground"> — {{ $line['role_label'] }}, {{ $line['at'] }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-foreground">
                <span class="orders-view-badge orders-view-badge--unseen">Не просмотрено</span>
                <span class="ml-2 text-muted-foreground">Карточку ещё не открывали старший менеджер или руководитель филиала.</span>
            </p>
        @endif
    </div>

    {{-- Информация о заказе --}}
    <div class="order-show-page">
            {{-- Основная область: две колонки --}}
            <div class="order-show-layout">
                
                {{-- ЛЕВАЯ КОЛОНКА --}}
                <div>
                    {{-- Форма редактирования --}}
                    <div class="card p-4">
                    <h3 class="mb-4 text-base font-semibold">
                        Редактирование заказа
                    </h3>
                    @php
                        // Незаполненные поля КЦ для подсветки при статусе «Ожидает» (pending)
                        $isPending = $order->order_status === 'pending';
                        $highlightFields = [];
                        if ($isPending) {
                            if (empty($order->order_adds)) $highlightFields[] = 'order_adds';
                            if (empty($order->source_id)) $highlightFields[] = 'source_id';
                        }
                    @endphp
                    <form method="POST" action="{{ route('orders.update', $order->order_id) }}" id="orderForm">
                        @csrf
                        @method('PUT')

                        @if ($errors->any())
                            <div class="order-alert-warning mb-4" role="alert">
                                {!! icon('warning') !!}
                                <ul class="m-0 mt-1 list-inside list-disc text-sm">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if (session('success'))
                            <div class="mb-4 rounded-md border border-green-500/40 bg-green-500/10 px-3 py-2 text-sm text-green-700 dark:text-green-300" role="status">
                                {{ session('success') }}
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="order-alert-warning mb-4" role="alert">
                                {!! icon('warning') !!} {{ session('error') }}
                            </div>
                        @endif
                        
                        {{-- Подсказка о незаполненных полях --}}
                        @if($isPending && count($highlightFields) > 0)
                        <div class="order-alert-warning" role="status">
                            {!! icon('warning') !!} Заявка требует обработки — жёлтым выделены незаполненные обязательные поля КЦ; «Переносы» необязательны и не подсвечиваются
                        </div>
                        @endif
                        
                        {{-- Строка 1: Тип | Вид техники | Профильность | Дальний выезд | Дата и время --}}
                        <div class="order-show-grid-row-1{{ ($canEditLongTrip ?? false) ? ' order-show-grid-row-1--with-long-trip' : '' }}">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Тип заказа *</label>
                                <select name="order_type" class="form-input" required {{ !$isEditable || !$canEditCcFields ? 'disabled' : '' }}>
                                    <option value="new" {{ $order->order_type == 'new' ? 'selected' : '' }}>Впервые</option>
                                    <option value="repeat" {{ $order->order_type == 'repeat' ? 'selected' : '' }}>Повтор</option>
                                    <option value="warranty" {{ $order->order_type == 'warranty' ? 'selected' : '' }}>Гарантия</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Вид техники *</label>
                                <select name="equipment_type" class="form-input" {{ !$isEditable || !$canEditCcFields ? 'disabled' : '' }} onchange="updateOrderCore(this)">
                                    <option value="">Выберите</option>
                                    @foreach(\App\Models\Order::EQUIPMENT_TYPES as $code => $label)
                                        <option value="{{ $code }}" {{ $order->equipment_type == $code ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Профильность *</label>
                                <input type="hidden" name="order_core" id="orderCoreSelect" value="{{ $order->order_core }}">
                                <input
                                    type="text"
                                    id="orderCoreDisplay"
                                    class="form-input bg-muted cursor-default"
                                    readonly
                                    value="{{ \App\Models\Order::orderCoreLabel($order->order_core) }}"
                                >
                            </div>
                            @if($canEditLongTrip)
                                @php
                                    $longTripSelected = (string) (int) old('is_long_trip', $order->is_long_trip ? 1 : 0);
                                @endphp
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Дальний выезд</label>
                                    <select name="is_long_trip" class="form-input" {{ !$isEditable ? 'disabled' : '' }}>
                                        <option value="0" {{ $longTripSelected === '0' ? 'selected' : '' }}>Нет</option>
                                        <option value="1" {{ $longTripSelected === '1' ? 'selected' : '' }}>Да</option>
                                    </select>
                                </div>
                            @endif
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Дата и время заявки *</label>
                                <input type="datetime-local" name="datetime_order" class="form-input" 
                                       value="{{ $order->datetime_order->format('Y-m-d\TH:i') }}" required {{ !$isEditable || !($canEditScheduleFields ?? false) ? 'disabled' : '' }}>
                            </div>
                        </div>
                        
                        {{-- Строка 2: Описание | Переносы (только КЦ и разработчик) --}}
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Описание заказа</label>
                                <textarea name="order_adds" class="form-input {{ in_array('order_adds', $highlightFields) ? 'field-highlight' : '' }}" rows="5" {{ !$isEditable || !$canEditCcFields ? 'readonly' : '' }}>{{ $order->order_adds }}</textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Переносы</label>
                                <textarea name="shift_adds" class="form-input" rows="5" {{ !$isEditable || !($canEditScheduleFields ?? false) ? 'readonly' : '' }}>{{ $order->shift_adds }}</textarea>
                            </div>
                        </div>
                        
                        {{-- Строка 3: Мастер | Оплачено | Комплектующие | Подытог (НЕ для КЦ) --}}
                        <div class="order-show-grid-row-3">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Мастер</label>
                                <select name="master_id" class="form-input" {{ !$isEditable || !$canEditCityFields ? 'disabled' : '' }}>
                                    <option value="">Не назначен</option>
                                    @foreach($masters as $master)
                                        @php
                                            $masterBusy = ! $master->isAvailableForNewOrder()
                                                && (int) $order->master_id !== (int) $master->user_id;
                                        @endphp
                                        <option value="{{ $master->user_id }}" {{ $order->master_id == $master->user_id ? 'selected' : '' }}>
                                            {{ $master->user_name }}{{ $masterBusy ? ' (занят)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Оплачено *</label>
                                <input type="number" name="amount_paid" class="form-input" id="amount_paid"
                                       value="{{ $order->amount_paid }}" min="0" required
                                       oninput="calculateSubtotal()" {{ !$isEditable || !$canEditCityFields ? 'disabled' : '' }}>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Комплектующие *</label>
                                <input type="number" name="amount_comp" class="form-input" id="amount_comp"
                                       value="{{ $order->amount_comp }}" min="0" required
                                       oninput="calculateSubtotal()" {{ !$isEditable || !$canEditCityFields ? 'disabled' : '' }}>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Подытог (р.)</label>
                                <span id="subtotal" class="order-show-subtotal">{{ number_format($order->amount_paid - $order->amount_comp, 0, ',', ' ') }}</span>
                            </div>
                        </div>
                        
                        {{-- Строка 4: Коммент филиала (НЕ для КЦ) --}}
                        <div class="form-group">
                            <label class="form-label">Коммент филиала</label>
                            <textarea name="city_adds" class="form-input" rows="10" {{ !$isEditable || !$canEditCityFields ? 'readonly' : '' }}>{{ $order->city_adds }}</textarea>
                        </div>
                        
                        {{-- Информация о закрытии --}}
                        @if($order->order_status === 'completed' && $order->order_closed_at)
                        <div class="order-order-closed-box">
                            <strong>Заявка закрыта</strong> {{ $order->order_closed_at->format('d.m.Y H:i') }}
                            ({{ $order->address->city->city_name }} / {{ $order->closedBy->user_name ?? 'Неизвестно' }})
                        </div>
                        @endif
                    </form>
                    </div>
                    
                    {{-- История заказов клиента --}}
                    @if($canViewClientHistory)
                        <x-orders.client-history
                            :orders="$orderHistory"
                            :status-labels="$statusLabels"
                            :highlight-order-id="$order->order_id"
                        />
                    @endif
                </div>
                
                {{-- ПРАВАЯ КАРТОЧКА: информация о клиенте, заказе, действия --}}
                <div class="order-show-sidebar">
                    {{-- Всё в одной карточке --}}
                    <div class="card" style="padding: 1rem;">
                        <h3 class="order-sidebar-heading">{!! icon('orders') !!} Заказ №{{ $order->order_id }}</h3>
                        <p class="order-sidebar-muted" style="margin: 0.25rem 0 0.5rem;">
                            Создал: <strong>{{ $order->creator->user_name ?? '—' }}</strong>
                            @if($order->order_created_at)
                                · {{ $order->order_created_at->format('d.m.Y H:i') }}
                            @endif
                        </p>
                        @if($order->partner_user_id)
                            <p class="order-sidebar-muted" style="margin: 0.25rem 0 0.75rem;">Партнёр SuperPart: user_id {{ $order->partner_user_id }}</p>
                        @endif

                        {{-- Расчёты (превью до проведения и итог после) --}}
                        @if($calculations)
                        <div class="order-calc-box">
                            @if($calculations['is_satellite'] ?? false)
                                <div class="order-calc-row">
                                    <span class="order-calc-label">Город-спутник:</span>
                                    <span class="order-calc-value">50/50 (авто)</span>
                                </div>
                            @endif
                            <div class="order-calc-row">
                                <span class="order-calc-label">Проведено:</span>
                                <span class="order-calc-value">{{ number_format($calculations['net_amount'], 0, ',', ' ') }} р.</span>
                            </div>
                            <div class="order-calc-row">
                                <span class="order-calc-label">Доля мастера:</span>
                                <span class="order-calc-value">{{ $calculations['master_percent'] }}%</span>
                            </div>
                            <div class="order-calc-row">
                                <span class="order-calc-label">ЗП мастера:</span>
                                <span class="order-calc-value">{{ number_format($calculations['master_salary'], 0, ',', ' ') }} р.</span>
                            </div>
                            <div class="order-calc-row">
                                <span class="order-calc-label">К сдаче:</span>
                                <span class="order-calc-value">{{ number_format($calculations['amount_to_pay'], 0, ',', ' ') }} р.</span>
                            </div>
                        </div>
                        @endif
                        
                        {{-- Информация о клиенте --}}
                        <h3 class="order-sidebar-heading">👥 Информация о клиенте</h3>

                        @if($canEditCallbackClientData && $callbackPerson)
                            <div class="order-callback-client-edit mb-4 space-y-3">
                                <p class="text-muted-foreground text-xs">Статус «Прозвон» / «Не оформлена» — можно дополнить данные клиента</p>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Имя клиента *</label>
                                    <input type="text" name="client_person_name" class="form-input" form="orderForm"
                                           value="{{ old('client_person_name', $callbackPerson->person_name) }}" required>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Телефон</label>
                                    @php
                                        $phoneDigits = $callbackPhone ? preg_replace('/\D/', '', $callbackPhone->phone_number) : '';
                                        if (strlen($phoneDigits) === 11 && in_array($phoneDigits[0], ['7', '8'], true)) {
                                            $phoneDigits = substr($phoneDigits, 1);
                                        }
                                    @endphp
                                    <x-input-phone-ru
                                        name="client_phone_number"
                                        id="client_phone_number"
                                        form="orderForm"
                                        :value="old('client_phone_number', $phoneDigits)"
                                    />
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Комментарий к телефону</label>
                                    <input type="text" name="client_phone_adds" class="form-input" form="orderForm"
                                           value="{{ old('client_phone_adds', $callbackPhone?->phone_adds) }}">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Город *</label>
                                    <select name="address_city_id" class="form-input" form="orderForm" required>
                                        @foreach($clientCities as $city)
                                            <option value="{{ $city->city_id }}" {{ (int) old('address_city_id', $order->address->city_id) === (int) $city->city_id ? 'selected' : '' }}>
                                                {{ $city->city_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Улица</label>
                                    <input type="text" name="address_street" class="form-input" form="orderForm"
                                           value="{{ old('address_street', $order->address->street) }}">
                                </div>
                                <div class="order-show-grid-row-1" style="grid-template-columns: 1fr 1fr;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">Дом</label>
                                        <input type="text" name="address_house" class="form-input" form="orderForm"
                                               value="{{ old('address_house', $order->address->house) }}">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">Квартира</label>
                                        <input type="text" name="address_flat" class="form-input" form="orderForm"
                                               value="{{ old('address_flat', $order->address->flat) }}">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Комментарий к адресу</label>
                                    <textarea name="address_adds" class="form-input" form="orderForm" rows="2">{{ old('address_adds', $order->address->address_adds) }}</textarea>
                                </div>
                            </div>
                        @else
                        @foreach($order->persons as $person)
                            <div style="margin-bottom: 0.75rem;">
                                <div class="order-sidebar-person-name">{{ $person->person_name }}{{ $person->person_age ? ', ' . $person->person_age : '' }}</div>
                                @foreach($person->phones as $phone)
                                    <div class="order-sidebar-muted">
                                        📞 <x-phone-masked :phone="$phone" />
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                        
                        <div class="order-sidebar-section">
                            <div class="order-sidebar-section-title">📍 Адрес</div>
                            @if($isEditable && $canEditCityFields && !($canEditCallbackClientData ?? false) && $clientCities->isNotEmpty())
                                <div class="form-group" style="margin-bottom: 0.5rem;">
                                    <label class="form-label">Город *</label>
                                    <select name="address_city_id" class="form-input" form="orderForm" required>
                                        @foreach($clientCities as $city)
                                            <option value="{{ $city->city_id }}" {{ (int) old('address_city_id', $order->address->city_id) === (int) $city->city_id ? 'selected' : '' }}>
                                                {{ $city->city_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                            <div class="order-sidebar-city">{{ $order->address->city->city_name }}</div>
                            @endif
                            @if($order->isSatelliteCityOrder())
                                <div class="order-sidebar-muted" style="margin-top: 0.25rem;">
                                    <strong>Город-спутник:</strong> да (50/50)
                                    @if($order->address->city->parentCity && ! $order->address->city->nameIncludesParent())
                                        · {{ $order->address->city->parentCity->city_name }}
                                    @endif
                                </div>
                            @endif
                            <div class="order-sidebar-muted">Улица: {{ $order->address->street }}</div>
                            <div class="order-sidebar-muted">Дом: {{ $order->address->house }}</div>
                            @if($order->address->flat)
                                <div class="order-sidebar-muted">Квартира/офис: {{ $order->address->flat }}</div>
                            @endif
                            @if($order->address->address_adds)
                                <div class="order-sidebar-muted" style="margin-top: 0.5rem;">{{ $order->address->address_adds }}</div>
                            @endif
                            @if($cityOpenTime)
                                <div class="order-sidebar-muted" style="margin-top: 0.75rem; padding: 0.5rem; border-radius: 0.375rem; background: rgba(var(--primary-rgb, 59 130 246), 0.08);">
                                    <strong>Закреп:</strong> {{ $cityOpenTime['summary'] }}
                                </div>
                            @endif
                        </div>
                        @endif
                        
                        <div class="order-sidebar-section">
                            <div class="order-sidebar-muted">
                                <strong>Вид техники:</strong> {{ $order->equipment_type ? (\App\Models\Order::EQUIPMENT_TYPES[$order->equipment_type] ?? $order->equipment_type) : '—' }}
                            </div>
                            <div class="order-sidebar-muted" style="margin-top: 0.25rem;">
                                <strong>Профильность:</strong> {{ \App\Models\Order::orderCoreLabel($order->order_core) }}
                            </div>
                            @if($order->in_progress_at)
                                <div class="order-sidebar-muted" style="margin-top: 0.25rem;">
                                    <strong>В работе с:</strong>
                                    {{ $order->in_progress_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                                </div>
                            @endif
                            @if($order->is_long_trip && !($canEditLongTrip ?? false))
                                <div class="order-sidebar-muted" style="margin-top: 0.25rem;">
                                    <strong>Дальний выезд:</strong> да
                                </div>
                            @endif
                            <div class="order-sidebar-muted {{ in_array('source_id', $highlightFields) ? 'source-row-pending' : '' }}" style="margin-top: 0.25rem;">
                                @if($isEditable && ($canEditCityFields || ($canEditSource ?? false)))
                                    <label class="form-label" style="margin-bottom: 0.25rem;">Источник заказа (РК)</label>
                                    <select name="source_id" class="form-input" form="orderForm">
                                        <option value="">Не указан</option>
                                        @foreach($sources as $source)
                                            <option value="{{ $source->source_id }}" {{ (int) $order->source_id === (int) $source->source_id ? 'selected' : '' }}>
                                                {{ $source->display_label }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <strong>Источник заказа:</strong> {{ $order->source->display_label ?? 'Не указан' }}
                                @endif
                            </div>
                            <div class="order-sidebar-muted" style="margin-top: 0.25rem;">
                                <strong>Создан:</strong> {{ $order->order_created_at->format('d.m.Y H:i') }} ({{ $order->creator->user_name ?? '—' }})
                            </div>
                        </div>

                    
                    {{-- Статус заказа (селект из устанавливаемых для роли) --}}
                    <div class="order-sidebar-section">
                        <label class="form-label" style="margin-bottom: 0.5rem;">Статус заказа *</label>
                        <select name="order_status" class="form-input" form="orderForm" required {{ !$isEditable ? 'disabled' : '' }}>
                            @foreach($statusesForSelect as $statusValue)
                                <option value="{{ $statusValue }}" {{ $order->order_status == $statusValue ? 'selected' : '' }}>
                                    {{ $statusLabels[$statusValue] ?? $statusValue }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="order-sidebar-section">
                    {{-- Кнопки действий --}}
                        @php
                            $canEdit = $isEditable && ($canEditCcFields || $canEditCityFields || ($canEditCallbackClientData ?? false) || ($canEditLongTrip ?? false));
                        @endphp
                        <div class="order-show-buttons">
                            @if($canEdit)
                                <button type="submit" form="orderForm" class="btn btn-primary btn-order-compact">
                                    {!! icon('save') !!} Сохранить
                                </button>
                                <button type="button" class="btn btn-secondary btn-order-compact" onclick="copyOrderInfo()">
                                    {!! icon('copy') !!} Копировать
                                </button>
                                <button type="submit" form="orderForm" class="btn btn-primary btn-order-compact" name="save_and_close" value="1">
                                    {!! icon('save') !!} Сохр. и закрыть
                                </button>
                            @else
                                <button type="button" class="btn btn-secondary btn-order-compact" onclick="copyOrderInfo()">
                                    {!! icon('copy') !!} Копировать
                                </button>
                            @endif
                            
                            <a href="{{ route('orders.index') }}" class="btn btn-secondary btn-order-compact">
                                {!! icon('back') !!} Закрыть
                            </a>
                        </div>
                        
                        {{-- Кнопка создания претензии (только КЦ и разработчик — см. route complaints.create) --}}
                        @if(auth()->user()->hasAnyRole(['developer', 'call_center']))
                        <a href="{{ route('complaints.create', ['order_id' => $order->order_id]) }}" class="btn btn-secondary btn-complaint-warn btn-order-compact" style="width: 100%; margin-top: 0.5rem;">
                            {!! icon('warning') !!} Создать претензию
                        </a>
                        @endif

                        @if(auth()->user()->hasAnyRole(['developer', 'call_center', 'senior_dispatcher', 'branch_head', 'regional_director', 'senior_manager']))
                        <a href="{{ route('cfm.create', ['type' => 'client_refund', 'order_id' => $order->order_id]) }}" class="btn btn-secondary btn-order-compact" style="width: 100%; margin-top: 0.5rem;">
                            {!! icon('add') !!} Возврат клиенту
                        </a>
                        @endif
                        
                        {{-- Кнопка проведения / открытия --}}
                        @if(!$order->order_closed_at && $isEditable)
                            @if($canComplete['can'])
                                <form method="POST" action="{{ route('orders.complete', $order->order_id) }}" id="completeOrderForm" style="margin-top: 0.5rem;">
                                    @csrf
                                    <button type="button" class="btn btn-success btn-order-compact" style="width: 100%;" onclick="saveAndCompleteOrder()">
                                        {!! icon('check') !!} Провести заказ
                                    </button>
                                </form>
                            @else
                                <div class="order-alert-warning" style="margin-top: 0.5rem; margin-bottom: 0;" role="alert">
                                    <p style="margin: 0; font-size: 0.75rem;">
                                        {{ implode(', ', $canComplete['errors']) }}
                                    </p>
                                </div>
                            @endif
                        @endif
                        @if($order->order_closed_at && ($canReopenClosedOrder ?? false))
                            <form method="POST" action="{{ route('orders.reopen', $order->order_id) }}" style="margin-top: 0.5rem;">
                                @csrf
                                <button type="submit" class="btn btn-warning btn-order-compact" style="width: 100%;" onclick="return confirm('Открыть заказ для редактирования? Связанные кассовые операции будут удалены.')">
                                    {!! icon('edit') !!} Открыть заказ
                                </button>
                            </form>
                            <p class="text-muted-foreground" style="margin-top: 0.35rem; font-size: 0.75rem;">
                                Или измените суммы / профильность и нажмите «Сохранить» — касса пересчитается автоматически.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
    </div>
    </div>
    
@php
    $orderPhotoAccept = \App\Models\Document::orderPhotoAcceptAttribute();
    $orderPhotoFormats = \App\Models\Document::orderPhotoFormatsLabel();
@endphp
    {{-- Документы заказа --}}
    <section class="order-show-documents" aria-label="Документы заказа">
            <h2 class="order-documents-heading">{!! icon('document') !!} Документы заказа</h2>
            <p class="text-sm text-muted-foreground mb-3">Обязательны при проведении, если «Оплачено» от {{ \App\Models\Order::DOCUMENTS_REQUIRED_FROM_PAID }}&nbsp;₽ включительно. Ниже порога — можно провести без документов.</p>
            <div class="order-show-docs-grid mb-6">
                {{-- Договор --}}
                <div class="order-doc-panel">
                    <h3 class="order-documents-heading" style="font-size: 1rem;">{!! icon('document') !!} Договор</h3>
                    @if($canEditDocuments)
                    <div class="dropzone" data-category="contract" onclick="openFileDialog('contract')">
                        <div class="dropzone-inner">
                            <div class="dropzone-emoji">📄</div>
                            <div>Перетащите фото сюда</div>
                            <div class="dropzone-inner-note">или нажмите для выбора</div>
                        </div>
                    </div>
                    <input type="file" id="file-contract" style="display: none;" multiple accept="{{ $orderPhotoAccept }}" onchange="uploadDocuments('contract', this.files)">
                    @else
                    <div class="order-doc-disabled-placeholder">
                        Загрузка документов недоступна для завершённого или отменённого заказа
                    </div>
                    @endif
                    <div id="files-contract" class="files-list order-files-list">
                        <div class="files-empty files-empty--hidden">
                            Документы не загружены
                        </div>
                    </div>
                </div>
                
                {{-- Чеки на комплектующие/расходы --}}
                <div class="order-doc-panel">
                    <h3 class="order-documents-heading" style="font-size: 1rem;">{!! icon('document') !!} Чеки на комплектующие/расходы</h3>
                    @if($canEditDocuments)
                    <div class="dropzone" data-category="receipts" onclick="openFileDialog('receipts')">
                        <div class="dropzone-inner">
                            <div class="dropzone-emoji">🧾</div>
                            <div>Перетащите фото сюда</div>
                            <div class="dropzone-inner-note">или нажмите для выбора</div>
                        </div>
                    </div>
                    <input type="file" id="file-receipts" style="display: none;" multiple accept="{{ $orderPhotoAccept }}" onchange="uploadDocuments('receipts', this.files)">
                    @else
                    <div class="order-doc-disabled-placeholder">
                        Загрузка документов недоступна для завершённого или отменённого заказа
                    </div>
                    @endif
                    <div id="files-receipts" class="files-list order-files-list">
                        <div class="files-empty files-empty--hidden">
                            Документы не загружены
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="order-show-docs-grid">
                {{-- Фото запчастей/комплектующих --}}
                <div class="order-doc-panel">
                    <h3 class="order-documents-heading" style="font-size: 1rem;">{!! icon('document') !!} Фото запчастей/комплектующих</h3>
                    @if($canEditDocuments)
                    <div class="dropzone" data-category="parts_photos" onclick="openFileDialog('parts_photos')">
                        <div class="dropzone-inner">
                            <div class="dropzone-emoji">📸</div>
                            <div>Перетащите фото сюда</div>
                            <div class="dropzone-inner-note">или нажмите для выбора</div>
                        </div>
                    </div>
                    <input type="file" id="file-parts_photos" style="display: none;" multiple accept="{{ $orderPhotoAccept }}" onchange="uploadDocuments('parts_photos', this.files)">
                    @else
                    <div class="order-doc-disabled-placeholder">
                        Загрузка документов недоступна для завершённого или отменённого заказа
                    </div>
                    @endif
                    <div id="files-parts_photos" class="files-list order-files-list">
                        <div class="files-empty files-empty--hidden">
                            Документы не загружены
                        </div>
                    </div>
                    <div class="order-doc-note-warn">⚠️ Запчасть должна быть на фоне чека</div>
                </div>
                
                {{-- Сохранная расписка --}}
                <div class="order-doc-panel">
                    <h3 class="order-documents-heading" style="font-size: 1rem;">{!! icon('document') !!} Сохранная расписка</h3>
                    @if($canEditDocuments)
                    <div class="dropzone" data-category="storage_receipt" onclick="openFileDialog('storage_receipt')">
                        <div class="dropzone-inner">
                            <div class="dropzone-emoji">📋</div>
                            <div>Перетащите фото сюда</div>
                            <div class="dropzone-inner-note">или нажмите для выбора</div>
                        </div>
                    </div>
                    <input type="file" id="file-storage_receipt" style="display: none;" multiple accept="{{ $orderPhotoAccept }}" onchange="uploadDocuments('storage_receipt', this.files)">
                    @else
                    <div class="order-doc-disabled-placeholder">
                        Загрузка документов недоступна для завершённого или отменённого заказа
                    </div>
                    @endif
                    <div id="files-storage_receipt" class="files-list order-files-list">
                        <div class="files-empty files-empty--hidden">
                            Документы не загружены
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="order-docs-hint">
                <strong>Только фотографии:</strong> {{ $orderPhotoFormats }}<br>
                <strong>Максимальный размер:</strong> 10 МБ на фото<br>
                <strong>Количество:</strong> до 20 фото в каждой категории (можно выбрать несколько сразу)
            </div>
    </section>

    <div id="orderPhotoLightbox" class="order-photo-lightbox hidden" role="dialog" aria-modal="true" aria-label="Просмотр фото" hidden>
        <div class="order-photo-lightbox-backdrop" data-lightbox-close></div>
        <button type="button" class="order-photo-lightbox-close" data-lightbox-close aria-label="Закрыть">&times;</button>
        <button type="button" class="order-photo-lightbox-nav order-photo-lightbox-prev" data-lightbox-prev aria-label="Предыдущее фото">&#8249;</button>
        <button type="button" class="order-photo-lightbox-nav order-photo-lightbox-next" data-lightbox-next aria-label="Следующее фото">&#8250;</button>
        <div class="order-photo-lightbox-counter" aria-live="polite"></div>
        <div class="order-photo-lightbox-toolbar" role="toolbar" aria-label="Управление фото">
            <button type="button" class="order-photo-lightbox-tool" data-lightbox-zoom-out title="Уменьшить" aria-label="Уменьшить">−</button>
            <button type="button" class="order-photo-lightbox-tool" data-lightbox-zoom-in title="Увеличить" aria-label="Увеличить">+</button>
            <button type="button" class="order-photo-lightbox-tool" data-lightbox-rotate-left title="Повернуть влево" aria-label="Повернуть влево">↺</button>
            <button type="button" class="order-photo-lightbox-tool" data-lightbox-rotate-right title="Повернуть вправо" aria-label="Повернуть вправо">↻</button>
            <button type="button" class="order-photo-lightbox-tool order-photo-lightbox-tool-reset" data-lightbox-reset title="Сбросить масштаб и поворот" aria-label="Сбросить">100%</button>
        </div>
        <div class="order-photo-lightbox-stage">
            <div class="order-photo-lightbox-viewport" data-lightbox-viewport>
                <img src="" alt="" class="order-photo-lightbox-image" draggable="false">
            </div>
        </div>
        <div class="order-photo-lightbox-caption"></div>
        <p class="order-photo-lightbox-hint">Колёсико — масштаб · двойной клик — увеличить · перетаскивание при зуме</p>
    </div>

    @if($canViewActivityLog ?? false)
    <section class="card mt-4 p-4" aria-label="История изменений заказа">
        <h2 class="order-documents-heading mb-3">{!! icon('document') !!} История изменений</h2>
        @if ($order->activityLogs->isEmpty())
            <p class="text-sm text-muted-foreground">Изменений пока нет.</p>
        @else
        <div class="table-responsive">
            <table class="table text-sm order-activity-log-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Сотрудник</th>
                        <th>Действие</th>
                        <th>Поле</th>
                        <th>Подробности</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->activityLogs as $log)
                        <tr>
                            <td class="whitespace-nowrap">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                            <td>{{ $log->user->user_name ?? '—' }}</td>
                            <td>{{ $log->action_label }}</td>
                            <td>{{ $log->field_name ? $activityLogService->fieldLabel($log->field_name) : '—' }}</td>
                            <td class="order-activity-log-details">{{ $log->details }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </section>
    @endif

@endsection


@push('scripts')
<script>
    // Ждём загрузки DOM
    document.addEventListener('DOMContentLoaded', function() {
        // Загружаем документы при загрузке страницы с небольшой задержкой
        setTimeout(function() {
            loadDocuments();
        }, 100);
    });
    
    // Расчёт подытога в реальном времени
    function calculateSubtotal() {
        const paid = parseFloat(document.getElementById('amount_paid').value) || 0;
        const comp = parseFloat(document.getElementById('amount_comp').value) || 0;
        const subtotal = paid - comp;
        
        // Форматирование с пробелами как разделителями тысяч
        document.getElementById('subtotal').textContent = subtotal.toLocaleString('ru-RU', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }
    
    // Функция для форматирования даты с днём недели
    function formatDateWithWeekday(dateStr) {
        const parts = dateStr.split(' ');
        const dateParts = parts[0].split('.');
        const timeParts = parts[1];
        
        const date = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
        
        const weekdays = ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'];
        const weekday = weekdays[date.getDay()];
        
        return `${parts[0]} (${weekday}) ${timeParts}`;
    }
    
    // Копирование информации о заказе (формат по документу)
    function copyOrderInfo() {
        @php
            $addr = $order->address;
            $copyData = [
                'id' => $order->order_id,
                'type' => $order->order_type,
                'core' => $order->order_core,
                'equipment_type_label' => $order->equipment_type ? (\App\Models\Order::EQUIPMENT_TYPES[$order->equipment_type] ?? $order->equipment_type) : '',
                'source_name' => $order->source->display_label ?? '',
                'order_adds' => $order->order_adds ?? '',
                'address_formatted' => 'г. ' . $addr->city->city_name . ', ул. ' . $addr->street . ', ' . $addr->house . ($addr->flat ? ', кв.' . $addr->flat : ''),
                'rush' => $order->order_adds && stripos($order->order_adds, 'поспешить') !== false,
                'datetime' => $order->datetime_order->format('d.m.Y'),
                'time' => $order->datetime_order->format('H:i'),
                'person_name' => $order->persons->first()->person_name ?? '',
                'person_age' => $order->persons->first()->person_age ?? '',
            ];
        @endphp
        const d = @json($copyData);
        const typeLabels = { 'new': 'Впервые', 'repeat': 'Повтор', 'warranty': 'Гарантия' };
        const coreLabels = { 'core': 'проф.', 'non_core': 'непроф.', 'other': 'проч.' };
        const typeStr = typeLabels[d.type] || d.type;
        const coreStr = coreLabels[d.core] || d.core;
        const dateTimeStr = d.datetime && d.time ? (d.datetime + 'г. ' + d.time) : '';
        const personStr = [d.person_name, d.person_age].filter(Boolean).join(',');
        const rush = !!d.rush || /поспешить/i.test(d.order_adds || '');
        const adds = String(d.order_adds || '').replace(/поспешить\.?\s*/gi, '').trim();
        const lines = [
            'Заказ №' + d.id,
            typeStr + ', ' + coreStr,
            d.source_name || '',
            d.equipment_type_label || '',
            d.address_formatted || '',
            adds,
            rush ? 'Поспешить' : '',
            personStr,
            dateTimeStr
        ].filter(Boolean);
        const text = lines.join('\n');
        copyTextToClipboard(text).then(() => {
            showCopyFeedback(true, 'Информация скопирована в буфер обмена');
        }).catch(() => {
            showCopyFeedback(false, 'Ошибка при копировании');
        });
    }
    
    // ========== ДОКУМЕНТЫ ==========

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    const orderPhotoLightboxEl = document.getElementById('orderPhotoLightbox');
    const orderPhotoLightboxImage = orderPhotoLightboxEl?.querySelector('.order-photo-lightbox-image');
    const orderPhotoLightboxViewport = orderPhotoLightboxEl?.querySelector('[data-lightbox-viewport]');
    const orderPhotoLightboxCaption = orderPhotoLightboxEl?.querySelector('.order-photo-lightbox-caption');
    const orderPhotoLightboxCounter = orderPhotoLightboxEl?.querySelector('.order-photo-lightbox-counter');
    let orderPhotoGallery = [];
    let orderPhotoGalleryIndex = 0;
    let orderPhotoTransform = { scale: 1, rotation: 0, x: 0, y: 0 };
    let orderPhotoPanning = false;
    let orderPhotoPanStart = { x: 0, y: 0 };
    let orderPhotoTransformStart = { x: 0, y: 0 };
    const ORDER_PHOTO_MIN_SCALE = 1;
    const ORDER_PHOTO_MAX_SCALE = 4;
    const ORDER_PHOTO_ZOOM_STEP = 0.3;

    function applyOrderPhotoTransform() {
        if (!orderPhotoLightboxImage) return;
        const t = orderPhotoTransform;
        orderPhotoLightboxImage.style.transform =
            `translate(${t.x}px, ${t.y}px) scale(${t.scale}) rotate(${t.rotation}deg)`;
        if (orderPhotoLightboxViewport) {
            orderPhotoLightboxViewport.classList.toggle('is-zoomed', t.scale > 1);
        }
    }

    function resetOrderPhotoTransform() {
        orderPhotoTransform = { scale: 1, rotation: 0, x: 0, y: 0 };
        orderPhotoPanning = false;
        applyOrderPhotoTransform();
    }

    function zoomOrderPhoto(delta) {
        const prevScale = orderPhotoTransform.scale;
        const nextScale = Math.min(
            ORDER_PHOTO_MAX_SCALE,
            Math.max(ORDER_PHOTO_MIN_SCALE, +(prevScale + delta).toFixed(2))
        );
        orderPhotoTransform.scale = nextScale;
        if (nextScale <= 1) {
            orderPhotoTransform.x = 0;
            orderPhotoTransform.y = 0;
        }
        applyOrderPhotoTransform();
    }

    function rotateOrderPhoto(delta) {
        orderPhotoTransform.rotation = (orderPhotoTransform.rotation + delta + 360) % 360;
        applyOrderPhotoTransform();
    }

    function renderOrderPhotoLightbox() {
        if (!orderPhotoLightboxEl || !orderPhotoLightboxImage || orderPhotoGallery.length === 0) return;

        resetOrderPhotoTransform();
        const item = orderPhotoGallery[orderPhotoGalleryIndex];
        orderPhotoLightboxImage.src = item.url;
        orderPhotoLightboxImage.alt = item.caption || 'Фото';
        if (orderPhotoLightboxCaption) {
            orderPhotoLightboxCaption.textContent = item.caption || '';
            orderPhotoLightboxCaption.hidden = !item.caption;
        }
        if (orderPhotoLightboxCounter) {
            orderPhotoLightboxCounter.textContent = orderPhotoGallery.length > 1
                ? `${orderPhotoGalleryIndex + 1} / ${orderPhotoGallery.length}`
                : '';
        }

        const prevBtn = orderPhotoLightboxEl.querySelector('[data-lightbox-prev]');
        const nextBtn = orderPhotoLightboxEl.querySelector('[data-lightbox-next]');
        if (prevBtn) prevBtn.hidden = orderPhotoGallery.length <= 1;
        if (nextBtn) nextBtn.hidden = orderPhotoGallery.length <= 1;
    }

    function openOrderPhotoLightbox(items, index) {
        if (!orderPhotoLightboxEl || !items.length) return;
        orderPhotoGallery = items;
        orderPhotoGalleryIndex = Math.max(0, Math.min(index, items.length - 1));
        renderOrderPhotoLightbox();
        orderPhotoLightboxEl.hidden = false;
        orderPhotoLightboxEl.classList.remove('hidden');
        document.body.classList.add('order-photo-lightbox-open');
    }

    function closeOrderPhotoLightbox() {
        if (!orderPhotoLightboxEl) return;
        orderPhotoLightboxEl.hidden = true;
        orderPhotoLightboxEl.classList.add('hidden');
        document.body.classList.remove('order-photo-lightbox-open');
        if (orderPhotoLightboxImage) {
            orderPhotoLightboxImage.removeAttribute('src');
        }
        resetOrderPhotoTransform();
        orderPhotoGallery = [];
        orderPhotoGalleryIndex = 0;
    }

    function stepOrderPhotoLightbox(delta) {
        if (orderPhotoGallery.length <= 1) return;
        orderPhotoGalleryIndex = (orderPhotoGalleryIndex + delta + orderPhotoGallery.length) % orderPhotoGallery.length;
        renderOrderPhotoLightbox();
    }

    if (orderPhotoLightboxEl) {
        orderPhotoLightboxEl.querySelectorAll('[data-lightbox-close]').forEach((el) => {
            el.addEventListener('click', closeOrderPhotoLightbox);
        });
        orderPhotoLightboxEl.querySelector('[data-lightbox-prev]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            stepOrderPhotoLightbox(-1);
        });
        orderPhotoLightboxEl.querySelector('[data-lightbox-next]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            stepOrderPhotoLightbox(1);
        });
        orderPhotoLightboxEl.querySelector('[data-lightbox-zoom-in]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            zoomOrderPhoto(ORDER_PHOTO_ZOOM_STEP);
        });
        orderPhotoLightboxEl.querySelector('[data-lightbox-zoom-out]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            zoomOrderPhoto(-ORDER_PHOTO_ZOOM_STEP);
        });
        orderPhotoLightboxEl.querySelector('[data-lightbox-rotate-left]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            rotateOrderPhoto(-90);
        });
        orderPhotoLightboxEl.querySelector('[data-lightbox-rotate-right]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            rotateOrderPhoto(90);
        });
        orderPhotoLightboxEl.querySelector('[data-lightbox-reset]')?.addEventListener('click', (e) => {
            e.stopPropagation();
            resetOrderPhotoTransform();
        });

        orderPhotoLightboxViewport?.addEventListener('wheel', (e) => {
            if (orderPhotoLightboxEl.hidden) return;
            e.preventDefault();
            zoomOrderPhoto(e.deltaY < 0 ? ORDER_PHOTO_ZOOM_STEP : -ORDER_PHOTO_ZOOM_STEP);
        }, { passive: false });

        orderPhotoLightboxViewport?.addEventListener('dblclick', (e) => {
            e.preventDefault();
            if (orderPhotoTransform.scale > 1) {
                resetOrderPhotoTransform();
            } else {
                orderPhotoTransform.scale = 2;
                applyOrderPhotoTransform();
            }
        });

        orderPhotoLightboxViewport?.addEventListener('pointerdown', (e) => {
            if (orderPhotoTransform.scale <= 1 || e.button !== 0) return;
            orderPhotoPanning = true;
            orderPhotoPanStart = { x: e.clientX, y: e.clientY };
            orderPhotoTransformStart = { x: orderPhotoTransform.x, y: orderPhotoTransform.y };
            orderPhotoLightboxViewport.classList.add('is-panning');
            orderPhotoLightboxViewport.setPointerCapture(e.pointerId);
        });

        orderPhotoLightboxViewport?.addEventListener('pointermove', (e) => {
            if (!orderPhotoPanning) return;
            orderPhotoTransform.x = orderPhotoTransformStart.x + (e.clientX - orderPhotoPanStart.x);
            orderPhotoTransform.y = orderPhotoTransformStart.y + (e.clientY - orderPhotoPanStart.y);
            applyOrderPhotoTransform();
        });

        const endPan = () => {
            orderPhotoPanning = false;
            orderPhotoLightboxViewport?.classList.remove('is-panning');
        };
        orderPhotoLightboxViewport?.addEventListener('pointerup', endPan);
        orderPhotoLightboxViewport?.addEventListener('pointercancel', endPan);

        document.addEventListener('keydown', (e) => {
            if (orderPhotoLightboxEl.hidden) return;
            if (e.key === 'Escape') closeOrderPhotoLightbox();
            if (e.key === 'ArrowLeft' && orderPhotoTransform.scale <= 1) stepOrderPhotoLightbox(-1);
            if (e.key === 'ArrowRight' && orderPhotoTransform.scale <= 1) stepOrderPhotoLightbox(1);
            if (e.key === '+' || e.key === '=') {
                e.preventDefault();
                zoomOrderPhoto(ORDER_PHOTO_ZOOM_STEP);
            }
            if (e.key === '-') {
                e.preventDefault();
                zoomOrderPhoto(-ORDER_PHOTO_ZOOM_STEP);
            }
            if (e.key === 'r' || e.key === 'R') {
                e.preventDefault();
                rotateOrderPhoto(90);
            }
        });
    }

    document.querySelector('.order-show-documents')?.addEventListener('click', (e) => {
        const thumb = e.target.closest('.order-photo-thumb');
        if (!thumb) return;
        e.preventDefault();

        const gallery = thumb.closest('.order-files-list');
        if (!gallery) return;

        const items = Array.from(gallery.querySelectorAll('.order-photo-thumb')).map((el) => ({
            url: el.dataset.full,
            caption: el.dataset.caption || '',
        }));
        const index = items.findIndex((item) => item.url === thumb.dataset.full);
        openOrderPhotoLightbox(items, index >= 0 ? index : 0);
    });
    
    // Загрузка списка документов
    function loadDocuments() {
        const categories = ['contract', 'receipts', 'parts_photos', 'storage_receipt'];
        
        // Очищаем списки
        categories.forEach(cat => {
            const filesList = document.getElementById(`files-${cat}`);
            if (filesList) {
                filesList.innerHTML = '';
            }
        });
        
        // Загружаем документы заказа
        @if($order->documents && $order->documents->count() > 0)
            @foreach($order->documents as $doc)
                addFileToList('{{ $doc->document_category }}', {
                    document_id: {{ $doc->document_id }},
                    file_name: '{{ addslashes($doc->file_name) }}',
                    human_size: '{{ $doc->human_size }}',
                    file_mime: '{{ $doc->file_mime ?? 'application/octet-stream' }}'
                });
            @endforeach
        @endif
        
        // Показываем индикатор пустого списка для категорий без документов
        categories.forEach(cat => {
            const filesList = document.getElementById(`files-${cat}`);
            if (filesList && filesList.children.length === 0) {
                const emptyIndicator = document.createElement('div');
                emptyIndicator.className = 'files-empty';
                emptyIndicator.textContent = 'Документы не загружены';
                filesList.appendChild(emptyIndicator);
            }
        });
    }
    
    
    // Открытие диалога выбора файла
    function openFileDialog(category) {
        const canEditDocs = {{ $canEditDocuments ? 'true' : 'false' }};
        if (!canEditDocs) {
            Toast.error('Нельзя добавлять документы к завершённому или отменённому заказу');
            return;
        }
        
        document.getElementById(`file-${category}`).click();
    }
    
    const MAX_FILES_PER_CATEGORY = 20;
    const ORDER_PHOTO_EXTENSIONS = @json(\App\Models\Document::ORDER_PHOTO_EXTENSIONS);

    function isAllowedOrderPhoto(file) {
        if (!file) return false;
        const name = file.name || '';
        const ext = name.includes('.') ? name.split('.').pop().toLowerCase() : '';
        if (!ORDER_PHOTO_EXTENSIONS.includes(ext)) {
            return false;
        }
        if (file.type && !file.type.startsWith('image/')) {
            return false;
        }
        return true;
    }

    function countFilesInCategory(category) {
        const filesList = document.getElementById(`files-${category}`);
        if (!filesList) return 0;
        return filesList.querySelectorAll('.order-file-item').length;
    }

    // Загрузка одного или нескольких документов
    async function uploadDocuments(category, files) {
        if (!files || !files.length) return;

        const currentCount = countFilesInCategory(category);
        const filesToUpload = Array.from(files);
        if (currentCount + filesToUpload.length > MAX_FILES_PER_CATEGORY) {
            const canAdd = Math.max(0, MAX_FILES_PER_CATEGORY - currentCount);
            Toast.warning(
                canAdd === 0
                    ? `Максимум ${MAX_FILES_PER_CATEGORY} файлов в этой категории`
                    : `Можно добавить ещё ${canAdd} файл(ов). Выбрано: ${filesToUpload.length}`
            );
            if (canAdd === 0) {
                document.getElementById(`file-${category}`).value = '';
                return;
            }
            filesToUpload.splice(canAdd);
        }

        let uploaded = 0;
        let failed = 0;
        let lastError = '';
        for (const file of filesToUpload) {
            const ok = await uploadDocument(category, file, { silent: true });
            if (ok) {
                uploaded++;
            } else {
                failed++;
                if (window.__lastUploadError) {
                    lastError = window.__lastUploadError;
                }
            }
        }

        document.getElementById(`file-${category}`).value = '';

        if (uploaded > 0) {
            Toast.success(uploaded === 1 ? 'Файл успешно загружен' : `Загружено файлов: ${uploaded}`);
        }
        if (failed > 0) {
            Toast.error(lastError || (failed === 1 ? 'Не удалось загрузить 1 файл' : `Не удалось загрузить: ${failed}`));
        }
    }

    // Сохранить форму и провести заказ (чтобы суммы не терялись)
    async function saveAndCompleteOrder() {
        const paidEl = document.getElementById('amount_paid');
        const paid = paidEl ? (parseInt(paidEl.value, 10) || 0) : {{ (int) ($order->amount_paid ?? 0) }};
        const docsRequiredFrom = {{ \App\Models\Order::DOCUMENTS_REQUIRED_FROM_PAID }};
        if (paid >= docsRequiredFrom) {
            const totalDocs = ['contract', 'receipts', 'parts_photos', 'storage_receipt']
                .reduce((n, cat) => n + countFilesInCategory(cat), 0);
            if (totalDocs < 1) {
                Toast.error(`При сумме от ${docsRequiredFrom} ₽ загрузите хотя бы один документ`);
                document.querySelector('.order-show-documents')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }
        }

        if (!confirm('Провести заказ? Статус автоматически изменится на «Готов», сумма попадёт в кассу.')) {
            return;
        }

        const form = document.getElementById('orderForm');
        const completeForm = document.getElementById('completeOrderForm');
        const canEdit = {{ ($isEditable && ($canEditCcFields || $canEditCityFields || ($canEditLongTrip ?? false))) ? 'true' : 'false' }};

        if (canEdit && form) {
            try {
                const fd = new FormData(form);
                fd.append('_method', 'PUT');
                const saveResp = await fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!saveResp.ok) {
                    const err = await saveResp.json().catch(() => ({}));
                    Toast.error(err.message || 'Не удалось сохранить заказ перед проведением');
                    return;
                }
            } catch (e) {
                console.error(e);
                Toast.error('Ошибка сохранения перед проведением');
                return;
            }
        }

        if (!completeForm) return;

        if (canEdit && form) {
            const fd = new FormData(form);
            for (const [key, value] of fd.entries()) {
                if (key === '_token' || key === '_method') continue;
                let input = completeForm.querySelector(`input[name="${key}"]`);
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    completeForm.appendChild(input);
                }
                input.value = value;
            }
        }

        completeForm.submit();
    }

    // Загрузка документа
    async function uploadDocument(category, file, options = {}) {
        window.__lastUploadError = '';
        if (!file) return false;
        
        const canEditDocs = {{ $canEditDocuments ? 'true' : 'false' }};
        if (!canEditDocs) {
            window.__lastUploadError = 'Нельзя добавлять документы к завершённому или отменённому заказу';
            Toast.error(window.__lastUploadError);
            return false;
        }

        if (countFilesInCategory(category) >= MAX_FILES_PER_CATEGORY) {
            window.__lastUploadError = `Максимум ${MAX_FILES_PER_CATEGORY} файлов в этой категории`;
            Toast.warning(window.__lastUploadError);
            return false;
        }
        
        // Проверка размера (10 МБ)
        if (file.size > 10 * 1024 * 1024) {
            window.__lastUploadError = 'Файл слишком большой! Максимальный размер: 10 МБ';
            Toast.warning(window.__lastUploadError);
            return false;
        }

        if (!isAllowedOrderPhoto(file)) {
            window.__lastUploadError = 'Можно загружать только фотографии: {{ $orderPhotoFormats }}';
            Toast.warning(window.__lastUploadError);
            return false;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('category', category);
        
        try {
            const response = await fetch(crmUrl('/orders/{{ $order->order_id }}/documents'), {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            });
            
            let result = {};
            try {
                result = await response.json();
            } catch (parseErr) {
                window.__lastUploadError = 'Ошибка ответа сервера при загрузке файла';
                if (!options.silent) Toast.error(window.__lastUploadError);
                return false;
            }

            if (response.ok && result.success) {
                addFileToList(category, result.document);
                if (!options.silent) {
                    document.getElementById(`file-${category}`).value = '';
                    Toast.success('Файл успешно загружен');
                }
                return true;
            }

            const errMsg = result.message
                || (result.errors ? Object.values(result.errors).flat().join(' ') : null)
                || 'Ошибка при загрузке файла';
            window.__lastUploadError = errMsg;
            if (!options.silent) {
                Toast.error(errMsg);
            } else {
                console.error(errMsg);
            }
        } catch (e) {
            console.error(e);
            window.__lastUploadError = 'Ошибка при загрузке файла';
            if (!options.silent) {
                Toast.error(window.__lastUploadError);
            }
        }
        return false;
    }
    
    // Добавление файла в список
    function addFileToList(category, docData) {
        const filesList = document.getElementById(`files-${category}`);
        if (!filesList) {
            console.error(`Элемент files-${category} не найден`);
            return;
        }
        
        // Удаляем индикатор пустого списка, если он есть
        const emptyIndicator = filesList.querySelector('.files-empty');
        if (emptyIndicator) {
            emptyIndicator.remove();
        }
        
        const fileItem = document.createElement('div');
        fileItem.className = 'order-file-item file-item';
        fileItem.dataset.documentId = docData.document_id;
        
        const fileMime = docData.file_mime || 'application/octet-stream';
        const isImage = fileMime.startsWith('image/');
        const viewUrl = crmUrl(`/documents/${docData.document_id}`);
        const safeName = escapeHtml(docData.file_name);
        
        const canEditDocs = {{ $canEditDocuments ? 'true' : 'false' }};
        const deleteButton = canEditDocs 
            ? `<button type="button" class="order-file-delete file-delete" onclick="event.stopPropagation(); deleteDocument(${docData.document_id}, '${category}')" title="Удалить">✕</button>`
            : '';
        
        if (isImage) {
            fileItem.innerHTML = `
                <div class="order-photo-thumb-wrap">
                    <a href="#" class="order-photo-thumb" data-full="${viewUrl}" data-caption="${safeName}" title="${safeName}">
                        <img src="${viewUrl}" alt="${safeName}" loading="lazy">
                    </a>
                    ${deleteButton}
                </div>
                <div class="order-file-meta">
                    <span class="order-file-name" title="${safeName}">${safeName}</span>
                    ${docData.human_size ? `<span class="file-size">${escapeHtml(docData.human_size)}</span>` : ''}
                </div>
            `;
        } else {
            fileItem.innerHTML = `
                <a href="${viewUrl}/download" class="order-file-download" target="_blank" rel="noopener" title="Скачать файл">
                    <span class="order-file-download-icon">📄</span>
                </a>
                <div class="order-file-meta">
                    <a href="${viewUrl}/download" class="file-name order-file-name" target="_blank" rel="noopener" title="Скачать файл">${safeName}</a>
                    ${docData.human_size ? `<span class="file-size">${escapeHtml(docData.human_size)}</span>` : ''}
                    ${deleteButton}
                </div>
            `;
        }
        
        filesList.appendChild(fileItem);
    }
    
    // Удаление документа
    async function deleteDocument(documentId, category) {
        const canEditDocs = {{ $canEditDocuments ? 'true' : 'false' }};
        if (!canEditDocs) {
            Toast.error('Нельзя удалять документы из завершённого или отменённого заказа');
            return;
        }
        
        if (!confirm('Удалить файл?')) return;
        
        try {
            const response = await fetch(crmUrl(`/documents/${documentId}`), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            });
            
            const result = await response.json();
            
        if (result.success) {
            // Удаляем элемент из списка
            const fileItem = document.querySelector(`[data-document-id="${documentId}"]`);
            if (fileItem) {
                const filesList = fileItem.closest('.files-list');
                fileItem.remove();
                if (filesList && filesList.children.length === 0) {
                    const emptyIndicator = document.createElement('div');
                    emptyIndicator.className = 'files-empty';
                    emptyIndicator.textContent = 'Документы не загружены';
                    filesList.appendChild(emptyIndicator);
                }
            }
            Toast.success('Файл удалён');
        } else {
            Toast.error(result.message || 'Ошибка при удалении файла');
        }
        } catch (e) {
            console.error(e);
            Toast.error('Ошибка при удалении файла');
        }
    }
    
    // Drag & Drop
    const coreEquipment = @json(\App\Models\Order::CORE_EQUIPMENT);
    function updateOrderCore(select) {
        const val = select.value;
        const coreHidden = document.getElementById('orderCoreSelect');
        const coreDisplay = document.getElementById('orderCoreDisplay');
        if (!coreHidden || !val) return;
        let core = 'non_core';
        let label = 'Непрофильный';
        if (val === 'other_device') {
            core = 'other';
            label = 'Прочий (наш 50% / партнёр 40%)';
        } else if (coreEquipment.includes(val)) {
            core = 'core';
            label = 'Профильный';
        }
        coreHidden.value = core;
        if (coreDisplay) coreDisplay.value = label;
    }

    @if($canEditDocuments)
    document.querySelectorAll('.dropzone').forEach(dropzone => {
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
        
        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('dragover');
        });
        
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            
            const category = dropzone.dataset.category;
            const files = e.dataTransfer.files;

            if (files && files.length) {
                uploadDocuments(category, files);
            }
        });
    });
    @endif
</script>
@endpush
