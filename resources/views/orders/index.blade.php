@extends('layouts.app')

@section('title', 'Заявки клиентов')

@section('content')
    @php
        $ordersListQuery = request()->except(['show_closed', 'closed_from', 'closed_to', 'page']);
        $ordersActiveUrl = route('orders.index', array_merge($ordersListQuery, ['show_closed' => '0']));
        $ordersClosedUrl = route('orders.index', array_merge($ordersListQuery, ['show_closed' => '1']));
    @endphp
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index', ['clear_filters' => 1])],
                    ['label' => 'Заявки клиентов', 'url' => null],
                ]"
            />
            @if (!empty($legendItems))
                <x-legend :items="$legendItems" />
            @endif
        </div>
        @if (auth()->user()->hasAnyRole(['developer', 'call_center', 'senior_dispatcher']))
            <div class="shrink-0">
                <x-ui.button
                    href="{{ route('persons.create') }}"
                    title="Создать новую запись клиента и заявку"
                    class="gap-2 rounded-md px-4 py-1.5 text-sm"
                >
                    {!! icon('add') !!}
                    Создать заказ
                </x-ui.button>
            </div>
        @else
            <div class="ml-auto flex shrink-0 items-center gap-2">
                @if ($showClosed)
                    <x-ui.button
                        href="{{ $ordersActiveUrl }}"
                        variant="outline"
                        size="sm"
                        class="h-8 whitespace-nowrap rounded-full px-3 text-xs"
                    >
                        Активные
                    </x-ui.button>
                @else
                    <x-ui.button
                        href="{{ $ordersClosedUrl }}"
                        variant="outline"
                        size="sm"
                        class="h-8 whitespace-nowrap rounded-full px-3 text-xs"
                    >
                        Закрытые
                    </x-ui.button>
                @endif
            </div>
        @endif
    </div>

    <x-ui.card padding="none" class="mb-4 min-w-0 max-w-full">
        <form method="GET" action="{{ route('orders.index') }}" id="filtersForm" class="min-w-0 max-w-full">
            @if ($showClosed)
                <input type="hidden" name="show_closed" value="1">
            @endif

            @php
                $indexFiltersOpen = request()->filled('source_format')
                    || request()->boolean('only_review')
                    || request()->boolean('without_source')
                    || ($showClosed && (request()->filled('closed_from') || request()->filled('closed_to')));
                $dateFilterPanelOpen = ($hasExplicitDateFrom ?? false) || ($hasExplicitDateTo ?? false);
                $selectedType = request('type');
                if (is_array($selectedType)) {
                    $selectedType = $selectedType[0] ?? '';
                }
                $selectedCityId = request('city_id');
                if (is_array($selectedCityId)) {
                    $selectedCityId = $selectedCityId[0] ?? '';
                }
                $selectedMasterId = request('master_id');
                if (is_array($selectedMasterId)) {
                    $selectedMasterId = $selectedMasterId[0] ?? '';
                }
            @endphp

            <div class="orders-list-top-bar orders-filters-sticky">
                <div id="ordersIndexFilters" class="orders-index-filters w-full {{ $indexFiltersOpen ? '' : 'hidden' }}">
                    <div class="orders-index-filters-inner flex min-w-0 flex-wrap items-end gap-3 border-b border-border px-2 py-2">
                        <label class="flex h-8 items-center gap-2 whitespace-nowrap text-xs">
                            <input
                                type="checkbox"
                                name="only_review"
                                value="1"
                                class="rounded border-border"
                                {{ request()->boolean('only_review') ? 'checked' : '' }}
                            >
                            Отзыв
                        </label>
                        <x-ui.button
                            href="{{ route('orders.index', ['without_source' => 1]) }}"
                            variant="{{ request()->boolean('without_source') ? 'primary' : 'outline' }}"
                            size="sm"
                            class="h-8 shrink-0 whitespace-nowrap rounded-full px-3 text-xs"
                            title="Заказы без привязанного источника"
                        >
                            Без источника
                        </x-ui.button>
                        <div>
                            <label class="mb-1 block text-xs text-muted-foreground">Формат источника</label>
                            <select name="source_format" class="form-input orders-table-filter-select min-w-[9rem] text-xs">
                                <option value="">Все</option>
                                <option value="{{ \App\Models\Source::FORMAT_ONLINE }}" {{ request('source_format') === \App\Models\Source::FORMAT_ONLINE ? 'selected' : '' }}>Онлайн</option>
                                <option value="{{ \App\Models\Source::FORMAT_OFFLINE }}" {{ request('source_format') === \App\Models\Source::FORMAT_OFFLINE ? 'selected' : '' }}>Офлайн</option>
                            </select>
                        </div>
                        @if ($showClosed)
                            <x-orders.filter-dates
                                :date-from="$displayDateFrom"
                                :date-to="$displayDateTo"
                                :closed-from="request('closed_from')"
                                :closed-to="request('closed_to')"
                                mode="closed"
                            />
                        @endif
                        <x-ui.button type="submit" variant="primary" size="sm" class="h-8 shrink-0 whitespace-nowrap rounded-full px-3 text-xs">
                            Найти
                        </x-ui.button>
                    </div>
                </div>

                <div
                    id="ordersDatetimeIntervalBar"
                    class="orders-list-interval-bar orders-date-filter-panel w-full {{ $dateFilterPanelOpen ? '' : 'hidden' }}"
                >
                    <input
                        type="text"
                        id="ordersDatetimeIntervalDisplay"
                        class="form-input orders-list-interval-input"
                        readonly
                        placeholder="Выберите интервал"
                        data-orders-dt-open
                    >
                    <input type="hidden" name="date_from" id="ordersDateFrom" value="{{ $displayDateFrom }}" disabled>
                    <input type="hidden" name="date_to" id="ordersDateTo" value="{{ $displayDateTo }}" disabled>
                    <x-ui.button type="submit" variant="primary" size="sm" class="orders-list-interval-btn h-7 whitespace-nowrap rounded-full px-3 text-xs">
                        Найти
                    </x-ui.button>
                    <x-ui.button type="button" variant="outline" size="sm" id="ordersDatetimeToday" class="orders-list-interval-btn h-7 whitespace-nowrap rounded-full px-3 text-xs">
                        Сегодня
                    </x-ui.button>
                </div>

                <div class="orders-list-top-meta flex min-w-0 flex-1 flex-wrap items-center gap-2 px-2 py-1">
                    @if ($orders->total() > 0)
                        <p class="orders-list-records-count m-0">
                            Показаны записи {{ $orders->firstItem() }}–{{ $orders->lastItem() }} из {{ $orders->total() }}
                        </p>
                    @endif
                    <button
                        type="button"
                        id="ordersToggleIndexFilters"
                        class="orders-index-filters-toggle orders-dt-filter-btn h-7 shrink-0 px-3"
                        title="Дополнительные фильтры"
                    >
                        Фильтр
                    </button>
                </div>

                <div class="orders-list-top-actions">
                    @if (auth()->user()->hasAnyRole(['developer', 'call_center', 'senior_dispatcher', 'general_director']))
                        @if ($showClosed)
                            <x-ui.button
                                href="{{ $ordersActiveUrl }}"
                                variant="outline"
                                size="sm"
                                class="h-9 whitespace-nowrap rounded-full px-3 text-xs"
                            >
                                Активные
                            </x-ui.button>
                        @else
                            <x-ui.button
                                href="{{ $ordersClosedUrl }}"
                                variant="outline"
                                size="sm"
                                class="h-9 whitespace-nowrap rounded-full px-3 text-xs"
                            >
                                Закрытые
                            </x-ui.button>
                        @endif
                    @endif
                    <x-ui.button
                        href="{{ route('orders.index', ['clear_filters' => 1]) }}"
                        variant="primary"
                        size="icon"
                        class="shrink-0"
                        title="Сбросить фильтры"
                    >
                        {!! icon('refresh') !!}
                    </x-ui.button>
                </div>
            </div>

            <button type="submit" id="filtersFormSubmit" class="hidden" tabindex="-1" aria-hidden="true">Применить</button>

            <x-orders.datetime-range-filter :date-from="$displayDateFrom" :date-to="$displayDateTo" />

            {{-- Одна таблица w-full в .orders-table-scroll + клон thead на body — как superpart.ru (initOrdersStickyTable в app.js) --}}
            <table class="table table-sticky orders-sticky-table w-full min-w-0 text-sm">
                    <thead>
                        <tr>
                            <th style="width: 5rem; min-width: 5rem;">
                                <a
                                    href="?{{ http_build_query(array_merge(request()->all(), ['sort' => 'order_id', 'dir' => request('sort') == 'order_id' && request('dir') == 'asc' ? 'desc' : 'asc'])) }}"
                                >ID</a>
                            </th>
                            <th style="width: 40px;" title="Просмотр филиалом">П</th>
                            <th style="width: 40px;" title="Непрофильный заказ">Н</th>
                            <th>
                                <a
                                    href="?{{ http_build_query(array_merge(request()->all(), ['sort' => 'datetime_order', 'dir' => request('sort') == 'datetime_order' && request('dir') == 'asc' ? 'desc' : 'asc'])) }}"
                                >Время заявки</a>
                            </th>
                            <th>Тип</th>
                            <th>Статус</th>
                            <th>Город</th>
                            <th>Источник</th>
                            <th title="Номер линии РК (source_phone)">Линия</th>
                            <th>Имя клиента</th>
                            @if ($showClientPhoneColumn ?? false)
                                <th title="Телефон клиента">Тел. клиента</th>
                            @endif
                            <th>Адрес</th>
                            <th>Мастер</th>
                            <th style="width: 90px;">
                                <a
                                    href="?{{ http_build_query(array_merge(request()->all(), ['sort' => 'order_created_at', 'dir' => request('sort') == 'order_created_at' && request('dir') == 'asc' ? 'desc' : 'asc'])) }}"
                                >Создано (лок)</a>
                            </th>
                            <th style="width: 90px;">
                                <a
                                    href="?{{ http_build_query(array_merge(request()->all(), ['sort' => 'order_closed_at', 'dir' => request('sort') == 'order_closed_at' && request('dir') == 'asc' ? 'desc' : 'asc'])) }}"
                                >Закрыто (лок)</a>
                            </th>
                            <th>
                                <a
                                    href="?{{ http_build_query(array_merge(request()->all(), ['sort' => 'amount_paid', 'dir' => request('sort') == 'amount_paid' && request('dir') == 'asc' ? 'desc' : 'asc'])) }}"
                                >Сумма</a>
                            </th>
                            @if ($showCreatorColumn ?? false)
                                <th>Создал</th>
                            @endif
                        </tr>
                        <tr class="border-b border-border bg-muted">
                            <td class="w-[5rem] min-w-[5rem] px-2 py-2 align-top">
                                <x-ui.filter-input
                                    type="number"
                                    name="search_id"
                                    value="{{ request('search_id') }}"
                                    size="sm"
                                    class="w-full min-w-[5rem]"
                                    placeholder="ID"
                                />
                            </td>
                            <td class="px-1 py-2"></td>
                            <td class="px-1 py-2"></td>
                            <td class="px-2 py-2 align-top">
                                <button type="button" class="orders-dt-filter-btn" data-orders-dt-open>Фильтр</button>
                            </td>
                            <td class="min-w-[6.5rem] px-2 py-2 align-top orders-filter-col-type">
                                <select name="type" class="form-input orders-table-filter-select w-full min-w-0 text-xs">
                                    <option value="">Тип</option>
                                    <option value="new" {{ $selectedType === 'new' ? 'selected' : '' }}>Впервые</option>
                                    <option value="repeat" {{ $selectedType === 'repeat' ? 'selected' : '' }}>Повтор</option>
                                    <option value="warranty" {{ $selectedType === 'warranty' ? 'selected' : '' }}>Гарантия</option>
                                </select>
                            </td>
                            <td class="min-w-[6.5rem] px-2 py-2 align-top orders-filter-col-status">
                                <div class="multiselect multiselect-compact" data-name="status" data-no-auto-submit>
                                    <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                                        <span class="multiselect-text text-muted-foreground">Статус</span>
                                        <span class="ml-auto shrink-0">▼</span>
                                    </div>
                                    <div class="multiselect-dropdown">
                                        <div class="multiselect-actions">
                                            <button type="button" onclick="selectAll(event, this)">выбрать все</button>
                                            <button type="button" onclick="deselectAll(event, this)">снять все</button>
                                            <button type="button" class="multiselect-apply-btn" onclick="applyMultiselectFilter(event, this)">поиск</button>
                                        </div>
                                        @foreach ($statusesForFilter as $status)
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    name="status[]"
                                                    value="{{ $status }}"
                                                    {{ in_array($status, request('status', [])) ? 'checked' : '' }}
                                                    onchange="updateMultiselect(this)"
                                                >
                                                {{ $statusLabels[$status] ?? $status }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                            <td class="min-w-[7rem] px-2 py-2 align-top orders-filter-col-city">
                                <select name="city_id" class="form-input orders-table-filter-select w-full min-w-0 text-xs" data-city-filter>
                                    <option value="">Город</option>
                                    @foreach ($cities as $city)
                                        <option
                                            value="{{ $city->city_id }}"
                                            {{ (string) $selectedCityId === (string) $city->city_id ? 'selected' : '' }}
                                        >
                                            {{ $city->city_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="min-w-[8.5rem] px-2 py-2 align-top orders-filter-col-source">
                                <div class="multiselect multiselect-compact" data-name="source_id" data-no-auto-submit>
                                    <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                                        <span class="multiselect-text text-muted-foreground">Источник</span>
                                        <span class="ml-auto shrink-0">▼</span>
                                    </div>
                                    <div class="multiselect-dropdown">
                                        <div class="multiselect-actions">
                                            <button type="button" onclick="selectAll(event, this)">все</button>
                                            <button type="button" onclick="deselectAll(event, this)">снять</button>
                                            <button type="button" class="multiselect-apply-btn" onclick="applyWithoutSourceFilter(event, this)" title="Только заказы без источника">без ист.</button>
                                            <button type="button" class="multiselect-apply-btn" onclick="applyMultiselectFilter(event, this)">поиск</button>
                                        </div>
                                        <label class="orders-without-source-option">
                                            <input
                                                type="checkbox"
                                                name="without_source"
                                                value="1"
                                                data-without-source
                                                {{ request()->boolean('without_source') ? 'checked' : '' }}
                                                onchange="onWithoutSourceToggle(this)"
                                            >
                                            <strong>Без источника</strong>
                                        </label>
                                        @foreach ($sources as $source)
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    name="source_id[]"
                                                    value="{{ $source->source_id }}"
                                                    {{ ! request()->boolean('without_source') && in_array($source->source_id, (array) request('source_id', [])) ? 'checked' : '' }}
                                                    onchange="onSourceIdToggle(this)"
                                                >
                                                {{ $source->display_label }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                            <td class="px-1 py-2" title="Номер линии берётся из выбранного источника"></td>
                            <td class="min-w-[5rem] px-2 py-2 align-top">
                                <x-ui.filter-input
                                    type="text"
                                    name="search_name"
                                    value="{{ request('search_name') }}"
                                    size="sm"
                                    placeholder="Имя"
                                />
                            </td>
                            @if ($showClientPhoneColumn ?? false)
                                <td class="px-1 py-2"></td>
                            @endif
                            <td class="min-w-[6rem] px-2 py-2 align-top">
                                <x-ui.filter-input
                                    type="text"
                                    name="search_address"
                                    value="{{ request('search_address') }}"
                                    size="sm"
                                    placeholder="Адрес"
                                />
                            </td>
                            <td class="min-w-[7rem] px-2 py-2 align-top">
                                <select name="master_id" class="form-input orders-table-filter-select w-full min-w-0 text-xs" data-master-filter>
                                    <option value="">Мастер</option>
                                    @foreach ($masters as $master)
                                        <option
                                            value="{{ $master['user_id'] }}"
                                            {{ (string) $selectedMasterId === (string) $master['user_id'] ? 'selected' : '' }}
                                        >
                                            {{ $master['display_name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-1 py-2"></td>
                            <td class="px-1 py-2"></td>
                            <td class="min-w-[4rem] px-2 py-2 align-top">
                                <x-ui.button type="submit" variant="primary" size="sm" class="h-8 w-full whitespace-nowrap rounded-full px-2 text-xs" id="ordersTableFiltersApply">
                                    Поиск
                                </x-ui.button>
                            </td>
                            @if ($showCreatorColumn ?? false)
                                <td class="min-w-[6rem] px-2 py-2 align-top">
                                    @if (($dispatchCreators ?? collect())->isNotEmpty())
                                        <div class="multiselect multiselect-compact" data-name="created_by">
                                            <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                                                <span class="multiselect-text text-muted-foreground">Создал</span>
                                                <span class="ml-auto shrink-0">▼</span>
                                            </div>
                                            <div class="multiselect-dropdown">
                                                <div class="multiselect-actions">
                                                    <button type="button" onclick="selectAll(event, this)">выбрать все</button>
                                                    <button type="button" onclick="deselectAll(event, this)">снять все</button>
                                                </div>
                                                @foreach ($dispatchCreators as $creator)
                                                    <label>
                                                        <input
                                                            type="checkbox"
                                                            name="created_by[]"
                                                            value="{{ $creator->user_id }}"
                                                            {{ in_array($creator->user_id, request('created_by', [])) ? 'checked' : '' }}
                                                            onchange="updateMultiselect(this)"
                                                        >
                                                        {{ $creator->user_name }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            @php
                                $rowUnseenClass = ! $order->city_views_exists
                                    && auth()->user()->hasAnyRole(['senior_manager', 'branch_head'])
                                    ? 'order-row--unseen'
                                    : '';
                                $sdPhoneE164 = '';
                                $sdStatus = '';
                                if ($showSdCopyButton ?? false) {
                                    $sdPhone = $order->persons->first()?->phones->first();
                                    $sdPhoneE164 = $sdPhone ? \App\Helpers\PhoneHelper::toE164($sdPhone->phone_number) : '';
                                    $sdStatus = mb_strtolower($statusLabels[$order->order_status] ?? $order->order_status, 'UTF-8');
                                }
                            @endphp
                            @php
                                $urgencyClass = $order->eventUrgencyClass();
                            @endphp
                            <tr
                                class="order-row cursor-pointer {{ $rowUnseenClass }} {{ $urgencyClass }}"
                                onclick="window.location='{{ route('orders.show', $order->order_id) }}'"
                            >
                                <td class="orders-id-cell">
                                    @php
                                        $addr = $order->address;
                                        $city = $addr?->city?->city_name ?? '';
                                        $street = $addr?->street ?? '';
                                        $house = $addr?->house ?? '';
                                        $flat = $addr?->flat ?? '';
                                        $addressFormatted = $city ? 'г. ' . $city . ', ул. ' . $street . ', ' . $house . ($flat ? ', кв.' . $flat : '') : '';
                                        $equipmentLabel = $order->equipment_type ? (\App\Models\Order::EQUIPMENT_TYPES[$order->equipment_type] ?? $order->equipment_type) : '';
                                        $copyInfo = [
                                            'id' => $order->order_id,
                                            'type' => $order->order_type,
                                            'core' => $order->order_core,
                                            'address_formatted' => $addressFormatted,
                                            'equipment_type_label' => $equipmentLabel,
                                            'source_name' => $order->source?->display_label ?? '',
                                            'source_phone' => $order->source?->source_phone ?? '',
                                            'order_adds' => $order->order_adds ?? '',
                                            'rush' => $order->order_adds && stripos($order->order_adds, 'поспешить') !== false,
                                            'datetime' => $order->datetime_order?->format('d.m.Y') ?? '',
                                            'time' => $order->datetime_order?->format('H:i') ?? '',
                                            'person_name' => $order->persons->first()?->person_name ?? '',
                                            'person_age' => $order->persons->first()?->person_age ?? '',
                                        ];
                                    @endphp
                                    <span class="orders-id-cell__actions">
                                        <a
                                            href="{{ route('orders.show', $order->order_id) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="orders-id-cell__link"
                                            title="Открыть в новой вкладке"
                                            onclick="event.stopPropagation()"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                        </a>
                                        <span
                                            class="orders-id-cell__copy"
                                            title="Копировать"
                                            onclick="event.stopPropagation(); copyOrderFromRow(this)"
                                        >
                                            {!! icon('copy') !!}
                                            <script type="application/json" class="copy-data">{!! json_encode($copyInfo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
                                        </span>
                                    </span>
                                    {{ $order->order_id }}
                                    @if (($showSdCopyButton ?? false) && ! ($showClientPhoneColumn ?? false) && $sdPhoneE164 !== '')
                                        <button
                                            type="button"
                                            class="orders-sd-copy orders-sd-copy--id"
                                            title="Скопировать для СД: ID, статус, телефон"
                                            onclick="event.stopPropagation(); copyOrderSdLink(this)"
                                            data-order-id="{{ $order->order_id }}"
                                            data-status="{{ $sdStatus }}"
                                            data-phone="{{ $sdPhoneE164 }}"
                                        >🔗</button>
                                    @endif
                                </td>
                                <td class="text-center orders-view-cell" title="{{ ($order->city_views_exists || in_array($order->order_status, ['on_way', 'in_progress', 'in_progress_sd', 'waiting_parts', 'waiting_payment', 'completed'], true)) ? 'Просмотрено филиалом' : 'Не просмотрено филиалом' }}">
                                    @php
                                        $isCityViewed = $order->city_views_exists
                                            || in_array($order->order_status, ['on_way', 'in_progress', 'in_progress_sd', 'waiting_parts', 'waiting_payment', 'completed'], true);
                                    @endphp
                                    @if ($isCityViewed)
                                        <span class="orders-view-badge orders-view-badge--seen" aria-label="Просмотрено">{!! icon('check') !!}</span>
                                    @else
                                        <span class="orders-view-badge orders-view-badge--unseen" aria-label="Не просмотрено">○</span>
                                    @endif
                                </td>
                                <td class="text-center" title="{{ $order->order_core === 'non_core' ? 'Непрофиль' : ($order->order_core === 'core' ? 'Профиль' : 'Прочее') }}">
                                    @if ($order->order_core === 'non_core')
                                        <span class="orders-view-badge orders-view-badge--seen" aria-label="Непрофиль">{!! icon('check') !!}</span>
                                    @endif
                                </td>
                                @php
                                    $cityTimezone = $order->address->city->city_timezone ?? config('app.timezone', 'Europe/Moscow');
                                    $orderDisplay = $order->datetime_order;
                                    $blinkClass = $urgencyClass;
                                @endphp
                                <td class="orders-datetime-cell {{ $blinkClass }}" title="Время заявки / события прозвона, город: {{ $cityTimezone }}">
                                    {{ $orderDisplay ? $orderDisplay->format('d.m.y')."\u{00A0}".$orderDisplay->format('H:i') : '—' }}
                                </td>
                                <td class="order-type-cell order-type-{{ $order->order_type }}">
                                    @switch ($order->order_type)
                                        @case ('new')
                                            Впервые

                                            @break
                                        @case ('repeat')
                                            Повтор

                                            @break
                                        @case ('warranty')
                                            Гарантия

                                            @break
                                    @endswitch
                                </td>
                                <td
                                    class="status-cell status-{{ $order->order_status }} {{ $blinkClass }}"
                                    title="@if ($order->order_status === 'pending') Открыть заказ и заполнить недостающие данные @else Открыть заказ @endif"
                                >
                                    {{ $statusLabels[$order->order_status] ?? $order->order_status }}
                                </td>
                                <td>{{ $order->address->city->city_name }}</td>
                                <td class="max-w-[10rem] truncate" title="{{ $order->source?->display_label ?? '' }}">
                                    {{ $order->source?->source_name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap text-muted-foreground" title="Номер линии РК">
                                    @if ($order->source?->source_phone)
                                        {{ \App\Helpers\PhoneHelper::format($order->source->source_phone) ?: $order->source->source_phone }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @foreach ($order->persons as $person)
                                        {{ $person->person_name }}{{ $person->person_age ? ', ' . $person->person_age : '' }}{{ !$loop->last ? '; ' : '' }}
                                    @endforeach
                                </td>
                                @if ($showClientPhoneColumn ?? false)
                                    <td class="orders-client-phone-cell" onclick="event.stopPropagation()">
                                        @php
                                            $clientPhone = $order->persons->first()?->phones->first();
                                        @endphp
                                        <span class="orders-client-phone-cell__inner">
                                            @if ($clientPhone)
                                                <x-phone-masked :phone="$clientPhone" />
                                            @else
                                                —
                                            @endif
                                            @if (($showSdCopyButton ?? false) && $sdPhoneE164 !== '')
                                                <button
                                                    type="button"
                                                    class="orders-sd-copy orders-sd-copy--phone"
                                                    title="Скопировать для СД: ID, статус, телефон"
                                                    onclick="event.stopPropagation(); copyOrderSdLink(this)"
                                                    data-order-id="{{ $order->order_id }}"
                                                    data-status="{{ $sdStatus }}"
                                                    data-phone="{{ $sdPhoneE164 }}"
                                                >🔗</button>
                                            @endif
                                        </span>
                                    </td>
                                @endif
                                <td title="{{ \App\Support\OrderAddressReveal::formatListAddress($order->address, $order->datetime_order, $cityTimezone ?? null) }}">
                                    {{ \App\Support\OrderAddressReveal::formatListAddress($order->address, $order->datetime_order, $cityTimezone ?? null) }}
                                </td>
                                <td>{{ $order->master->user_name ?? '—' }}</td>
                                <td class="orders-datetime-cell" title="Локальное время города">
                                    @php
                                        $tz = $order->address->city->city_timezone ?? config('app.timezone', 'Europe/Moscow');
                                    @endphp
                                    {{ $order->order_created_at?->copy()->timezone($tz)->format('d.m.y H:i') ?? '—' }}
                                </td>
                                <td class="orders-datetime-cell" title="Локальное время города">
                                    @if ($order->order_closed_at)
                                        {{ $order->order_closed_at->copy()->timezone($tz)->format('d.m.y H:i') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @php $orderSum = $order->amount_paid - $order->amount_comp; @endphp
                                    {{ $orderSum ? number_format($orderSum, 0, ',', ' ') : '—' }}
                                </td>
                                @if ($showCreatorColumn ?? false)
                                    <td>{{ $order->creator->user_name ?? '—' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ (($showCreatorColumn ?? false) ? 1 : 0) + (($showClientPhoneColumn ?? false) ? 1 : 0) + 14 }}" class="py-8 text-center text-muted-foreground">
                                    Заказы не найдены
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
            </table>

            @if ($orders->total() > 0)
                <div
                    class="flex flex-col gap-2 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Показано
                        @if ($orders->firstItem())
                            <span class="font-medium text-foreground">{{ $orders->firstItem() }}</span>
                            –
                            <span class="font-medium text-foreground">{{ $orders->lastItem() }}</span>
                        @else
                            {{ $orders->count() }}
                        @endif
                        из
                        <span class="font-medium text-foreground">{{ $orders->total() }}</span>
                        @if (!$orders->hasPages())
                            <span class="text-muted-foreground">(страница 1 из 1)</span>
                        @endif
                    </p>
                    <div class="flex justify-center sm:justify-end">
                        {{ $orders->withQueryString()->links('vendor.pagination.leadcontrol') }}
                    </div>
                </div>
            @endif
        </form>
    </x-ui.card>
@endsection

@push('scripts')
    <script src="{{ asset('js/orders-datetime-filter.js') }}?v={{ file_exists(public_path('js/orders-datetime-filter.js')) ? filemtime(public_path('js/orders-datetime-filter.js')) : 1 }}"></script>
    <script>
        function copyOrderSdLink(btn) {
            const id = btn.dataset.orderId;
            const status = btn.dataset.status || '';
            const phone = btn.dataset.phone || '';
            const text = 'ID' + id + ' ' + status + '\n' + phone;
            copyTextToClipboard(text)
                .then(() => showCopyFeedback(true, 'Скопировано для СД'))
                .catch(() => showCopyFeedback(false, 'Ошибка при копировании'));
        }

        function copyOrderFromRow(el) {
            const root = el && el.closest ? el.closest('.orders-id-cell__copy, td') : el;
            if (!root) return;
            try {
                const dataEl = root.querySelector('.copy-data');
                if (!dataEl) return;
                const d = JSON.parse(dataEl.textContent);
                const typeLabels = { new: 'Впервые', repeat: 'Повтор', warranty: 'Гарантия' };
                const coreLabels = { core: 'проф.', non_core: 'непроф.', other: 'проч.' };
                const typeStr = typeLabels[d.type] || d.type;
                const coreStr = coreLabels[d.core] || d.core;
                const dateTimeStr = [d.datetime, d.time].filter(Boolean).join(' ') ? d.datetime + 'г. ' + d.time : '';
                const personStr = [d.person_name, d.person_age].filter(Boolean).join(',');
                const rush = !!d.rush || /поспешить/i.test(d.order_adds || '');
                const adds = String(d.order_adds || '').replace(/поспешить\.?\s*/gi, '').trim();
                const lines = [
                    'Заказ №' + d.id,
                    typeStr + ', ' + coreStr,
                    d.source_name || '',
                    d.source_phone ? ('Линия: ' + d.source_phone) : '',
                    d.equipment_type_label || '',
                    d.address_formatted || '',
                    adds,
                    rush ? 'Поспешить' : '',
                    personStr,
                    dateTimeStr,
                ].filter(Boolean);
                const text = lines.join('\n');
                copyTextToClipboard(text)
                    .then(() => {
                        showCopyFeedback(true, 'Информация скопирована в буфер обмена');
                    })
                    .catch(() => {
                        showCopyFeedback(false, 'Ошибка при копировании');
                    });
            } catch (e) {
                showCopyFeedback(false, 'Ошибка при копировании');
            }
        }

        function resolveOrdersMultiselectContext(selectedEl) {
            const cloneRoot = selectedEl.closest('.table-sticky-clone');
            if (cloneRoot) {
                const cloneMs = selectedEl.closest('.multiselect');
                const cloneBoxes = cloneRoot.querySelectorAll('.multiselect');
                const idx = Array.from(cloneBoxes).indexOf(cloneMs);
                const origThead = document.querySelector('table.orders-sticky-table thead');
                const origMs = origThead?.querySelectorAll('.multiselect')[idx];
                if (origMs) {
                    return {
                        dropdown: origMs.querySelector('.multiselect-dropdown'),
                        anchorEl: selectedEl,
                    };
                }
            }

            return {
                dropdown: selectedEl.nextElementSibling,
                anchorEl: selectedEl,
            };
        }

        function getOrdersMultiselectAnchorForOpenDropdown(dropdown) {
            const origMs = dropdown.closest('.multiselect');
            if (!origMs) {
                return dropdown.previousElementSibling;
            }

            const origThead = origMs.closest('thead');
            if (!origThead) {
                return origMs.querySelector('.multiselect-selected');
            }

            const idx = Array.from(origThead.querySelectorAll('.multiselect')).indexOf(origMs);
            const clone = document.querySelector('.table-sticky-clone');
            if (clone && clone.style.display !== 'none') {
                const cloneSel = clone.querySelectorAll('.multiselect')[idx]?.querySelector('.multiselect-selected');
                if (cloneSel) {
                    return cloneSel;
                }
            }

            return origMs.querySelector('.multiselect-selected');
        }

        function clearOrdersMultiselectDropdownLayout(dropdown) {
            if (!dropdown) {
                return;
            }
            ['position', 'top', 'left', 'right', 'bottom', 'minWidth', 'maxWidth', 'maxHeight', 'width', 'zIndex'].forEach(
                function (k) {
                    dropdown.style[k] = '';
                }
            );
            delete dropdown.dataset.ordersFixed;
        }

        function positionOrdersMultiselectDropdownFixed(selectedEl, dropdown) {
            const r = selectedEl.getBoundingClientRect();
            const margin = 4;
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const isSource = dropdown.closest('.multiselect')?.getAttribute('data-name') === 'source_id';
            const minW = Math.max(Math.ceil(r.width), isSource ? 320 : 256);
            const maxW = Math.min(isSource ? 448 : 384, Math.floor(vw * 0.92) - 16);
            let left = r.left;
            if (left + minW > vw - 8) {
                left = Math.max(8, vw - minW - 8);
            }
            const spaceBelow = vh - r.bottom - margin - 8;
            const spaceAbove = r.top - margin - 8;
            const preferUp = spaceBelow < 180 && spaceAbove > spaceBelow;

            dropdown.style.position = 'fixed';
            dropdown.style.left = left + 'px';
            dropdown.style.right = 'auto';
            dropdown.style.minWidth = Math.min(minW, maxW) + 'px';
            dropdown.style.maxWidth = maxW + 'px';
            dropdown.style.width = 'auto';
            dropdown.style.zIndex = '6000';

            if (preferUp) {
                const maxH = Math.min(300, Math.max(120, spaceAbove));
                dropdown.style.top = 'auto';
                dropdown.style.bottom = vh - r.top + margin + 'px';
                dropdown.style.maxHeight = maxH + 'px';
            } else {
                const maxH = Math.min(300, Math.max(120, spaceBelow));
                dropdown.style.top = r.bottom + margin + 'px';
                dropdown.style.bottom = 'auto';
                dropdown.style.maxHeight = maxH + 'px';
            }
            dropdown.dataset.ordersFixed = '1';
        }

        function repositionOpenOrdersTableMultiselect() {
            document.querySelectorAll('table.orders-sticky-table thead .multiselect-dropdown.open').forEach(function (open) {
                const anchor = getOrdersMultiselectAnchorForOpenDropdown(open);
                if (anchor) {
                    positionOrdersMultiselectDropdownFixed(anchor, open);
                }
            });
        }

        function closeAllMultiselectDropdowns() {
            document.querySelectorAll('.multiselect-dropdown.open').forEach(function (d) {
                d.classList.remove('open');
                clearOrdersMultiselectDropdownLayout(d);
            });
        }

        window.closeAllMultiselectDropdowns = closeAllMultiselectDropdowns;

        function toggleMultiselect(element) {
            const ctx = resolveOrdersMultiselectContext(element);
            const dropdown = ctx.dropdown;
            if (!dropdown || !dropdown.classList.contains('multiselect-dropdown')) {
                return;
            }
            const wasOpen = dropdown.classList.contains('open');
            closeAllMultiselectDropdowns();
            if (!wasOpen) {
                dropdown.classList.add('open');
                if (element.closest('.orders-sticky-table, .table-sticky-clone')) {
                    positionOrdersMultiselectDropdownFixed(ctx.anchorEl, dropdown);
                }
            }
        }

        window.toggleMultiselect = toggleMultiselect;
        window.updateMultiselect = updateMultiselect;

        function onWithoutSourceToggle(checkbox) {
            const multiselect = checkbox.closest('.multiselect');
            if (checkbox.checked) {
                multiselect.querySelectorAll('input[name="source_id[]"]').forEach((cb) => {
                    cb.checked = false;
                });
            }
            updateMultiselect(checkbox);
        }

        function onSourceIdToggle(checkbox) {
            const multiselect = checkbox.closest('.multiselect');
            if (checkbox.checked) {
                const without = multiselect.querySelector('input[data-without-source]');
                if (without) {
                    without.checked = false;
                }
            }
            updateMultiselect(checkbox);
        }

        window.onWithoutSourceToggle = onWithoutSourceToggle;
        window.onSourceIdToggle = onSourceIdToggle;

        function applyWithoutSourceFilter(e, button) {
            if (e) e.preventDefault();
            const multiselect = button.closest('.multiselect');
            const form = button.closest('form');
            if (!multiselect || !form) return;
            multiselect.querySelectorAll('input[name="source_id[]"]').forEach((cb) => {
                cb.checked = false;
            });
            const without = multiselect.querySelector('input[data-without-source]');
            if (without) {
                without.checked = true;
            }
            form.querySelectorAll('input[name="without_source"]').forEach((cb) => {
                cb.checked = true;
            });
            closeAllMultiselectDropdowns();
            form.submit();
        }
        window.applyWithoutSourceFilter = applyWithoutSourceFilter;

        function updateMultiselect(checkbox) {
            const multiselect = checkbox.closest('.multiselect');
            const withoutSource = multiselect.querySelector('input[data-without-source]');
            const sourceBoxes = multiselect.querySelectorAll('input[name="source_id[]"]');
            const checkboxes = sourceBoxes.length ? sourceBoxes : multiselect.querySelectorAll('input[type="checkbox"]');
            const selected = Array.from(checkboxes).filter((cb) => cb.checked);
            const textElement = multiselect.querySelector('.multiselect-text');
            const total = checkboxes.length;
            if (!textElement) return;
            if (!textElement.dataset.placeholder) {
                textElement.dataset.placeholder = textElement.textContent;
            }
            if (withoutSource && withoutSource.checked) {
                textElement.textContent = 'Без источника';
                textElement.classList.remove('text-muted-foreground');
                return;
            }
            if (selected.length === 0 || selected.length === total) {
                textElement.textContent = textElement.dataset.placeholder;
                textElement.classList.add('text-muted-foreground');
            } else if (selected.length === 1) {
                textElement.textContent = selected[0].nextSibling.textContent.trim();
                textElement.classList.remove('text-muted-foreground');
            } else if (selected.length === 2) {
                textElement.textContent = selected.map((s) => s.nextSibling.textContent.trim()).join(', ');
                textElement.classList.remove('text-muted-foreground');
            } else {
                const firstTwo = selected
                    .slice(0, 2)
                    .map((s) => s.nextSibling.textContent.trim())
                    .join(', ');
                textElement.textContent = `${firstTwo} +${selected.length - 2}`;
                textElement.classList.remove('text-muted-foreground');
            }
        }

        function selectAll(e, button) {
            if (e) e.preventDefault();
            const multiselect = button.closest('.multiselect');
            const without = multiselect.querySelector('input[data-without-source]');
            if (without) {
                without.checked = false;
            }
            const targets = multiselect.querySelectorAll('input[name="source_id[]"]').length
                ? multiselect.querySelectorAll('input[name="source_id[]"]')
                : multiselect.querySelectorAll('input[type="checkbox"]:not([data-without-source])');
            targets.forEach((cb) => (cb.checked = true));
            if (targets[0]) {
                updateMultiselect(targets[0]);
            }
        }

        function deselectAll(e, button) {
            if (e) e.preventDefault();
            const multiselect = button.closest('.multiselect');
            multiselect.querySelectorAll('input[type="checkbox"]').forEach((cb) => (cb.checked = false));
            const first = multiselect.querySelector('input[type="checkbox"]');
            if (first) {
                updateMultiselect(first);
            }
            // «Снять все» всегда применяет фильтр (даже при data-no-auto-submit на чекбоксах)
            const form = button.closest('form');
            if (form) form.submit();
        }

        function applyMultiselectFilter(e, button) {
            if (e) e.preventDefault();
            closeAllMultiselectDropdowns();
            const form = button.closest('form');
            if (form) form.submit();
        }

        window.applyMultiselectFilter = applyMultiselectFilter;

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.multiselect')) {
                closeAllMultiselectDropdowns();
            }
        });

        document.addEventListener('scroll', repositionOpenOrdersTableMultiselect, true);
        window.addEventListener('resize', repositionOpenOrdersTableMultiselect);

        document.addEventListener('DOMContentLoaded', function () {
            initOrdersDatetimeFilter();

            document.getElementById('ordersToggleIndexFilters')?.addEventListener('click', function () {
                document.getElementById('ordersIndexFilters')?.classList.toggle('hidden');
            });

            document.querySelectorAll('.multiselect').forEach((multiselect) => {
                const firstCheckbox = multiselect.querySelector('input[type="checkbox"]');
                if (firstCheckbox) {
                    updateMultiselect(firstCheckbox);
                }
            });

            const form = document.getElementById('filtersForm');
            if (!form) return;

            form.querySelector('[data-city-filter]')?.addEventListener('change', function () {
                form.submit();
            });

            form.querySelectorAll('[data-filter-input] .filter-search-btn').forEach((btn) => {
                btn.addEventListener('click', function () {
                    form.submit();
                });
            });

            const inputs = form.querySelectorAll('input, select');
            inputs.forEach((input) => {
                if (input.type === 'checkbox') {
                    if (input.closest('.multiselect')) {
                        return;
                    }
                    if (input.name === 'only_review') {
                        return;
                    }
                    input.addEventListener('change', function () {
                        form.submit();
                    });
                } else if (input.tagName === 'SELECT' && input.name === 'city_id') {
                    return;
                } else if (input.type === 'date' || input.type === 'number' || input.type === 'text') {
                    if (input.id === 'ordersDatetimeIntervalDisplay' || input.closest('#ordersDatetimeFilterPopup')) {
                        return;
                    }
                    input.addEventListener('blur', function () {
                        if (this.value !== this.defaultValue) {
                            form.submit();
                        }
                    });
                    input.addEventListener('keypress', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            form.submit();
                        }
                    });
                }
            });

            document.querySelectorAll('.table th a').forEach((link) => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const url = new URL(this.href);
                    const formData = new FormData(form);
                    for (let [key, value] of formData.entries()) {
                        if (value && key !== '_token') {
                            if (key.endsWith('[]')) {
                                const allValues = formData.getAll(key);
                                allValues.forEach((val) => {
                                    if (val) url.searchParams.append(key, val);
                                });
                            } else if (!url.searchParams.has(key)) {
                                url.searchParams.set(key, value);
                            }
                        }
                    }
                    window.location.href = url.toString();
                });
            });
        });
    </script>
@endpush
