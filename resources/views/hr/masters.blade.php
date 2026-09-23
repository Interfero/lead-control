@extends('layouts.app')

@section('title', 'Рейтинг мастеров')

@section('content')
<div style="max-width: 1800px; margin: 0 auto;">
    <div class="mb-3">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Сотрудники', 'url' => route('hr.index')],
                ['label' => 'Рейтинг мастеров', 'url' => null],
            ]"
        />
    </div>

    {{-- Фильтры: верхняя полоса (город — сброс) и отдельные карточки по группам колонок --}}
    <form method="GET" action="{{ route('hr.masters') }}" id="filtersForm">
        <div class="card masters-filters-main-card" style="padding: 0.75rem; margin-bottom: 0.625rem;">
            {{-- Строка: Фильтры и базовые настройки --}}
            <div class="masters-filters-top flex flex-wrap items-center gap-4">
                {{-- Фильтры --}}
                <div style="flex: 0 0 auto; display: flex; gap: 1rem; align-items: flex-end;">
                    <div>
                        <div class="multiselect multiselect-compact" data-name="city_id" style="width: 140px;">
                            <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                                <span class="multiselect-text text-xs text-muted-foreground">Город</span>
                                <span style="margin-left: auto; font-size: 0.7rem;">▼</span>
                            </div>
                            <div class="multiselect-dropdown">
                                <div class="multiselect-actions">
                                    <button type="button" onclick="selectAll(this)">выбрать все</button>
                                    <button type="button" onclick="deselectAll(this)">снять все</button>
                                </div>
                                @foreach($cities as $city)
                                    <label>
                                        <input type="checkbox" name="city_id[]" value="{{ $city->city_id }}" 
                                            {{ in_array($city->city_id, (array) request('city_id', [])) ? 'checked' : '' }}
                                            onchange="updateMultiselect(this); submitForm()">
                                        {{ $city->city_name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <input type="hidden" name="period" id="periodInput" value="{{ request('period') }}">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <input type="date" name="date_from" class="form-input" value="{{ $dateFrom?->format('Y-m-d') }}"
                                onchange="clearPeriod(); submitForm()" style="width: 140px; font-size: 0.8rem; padding: 0.375rem;">
                            <input type="date" name="date_to" class="form-input" value="{{ $dateTo?->format('Y-m-d') }}"
                                onchange="clearPeriod(); submitForm()" style="width: 140px; font-size: 0.8rem; padding: 0.375rem;">
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button"
                                    class="btn btn-sm rounded-full {{ request('period') === 'today' ? 'btn-primary' : 'btn-secondary' }}"
                                    onclick="setPeriod('today')">Сегодня</button>
                                <button type="button"
                                    class="btn btn-sm rounded-full {{ request('period') === 'yesterday' ? 'btn-primary' : 'btn-secondary' }}"
                                    onclick="setPeriod('yesterday')">Вчера</button>
                                <button type="button"
                                    class="btn btn-sm rounded-full {{ request('period') === 'week' ? 'btn-primary' : 'btn-secondary' }}"
                                    onclick="setPeriod('week')">Неделя</button>
                                <button type="button"
                                    class="btn btn-sm rounded-full {{ request('period') === 'month' ? 'btn-primary' : 'btn-secondary' }}"
                                    onclick="setPeriod('month')">Месяц</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-wrap items-center gap-3">
                    <label class="checkbox-setting" style="font-size: 0.8rem; margin: 0;">
                        <input type="hidden" name="show_names" id="showNamesField" value="{{ request('show_names', '1') !== '0' ? '1' : '0' }}">
                        <input type="checkbox" id="hideNamesCheckbox"
                            {{ request('show_names', '1') === '0' ? 'checked' : '' }}
                            onchange="document.getElementById('showNamesField').value = this.checked ? '0' : '1'; submitForm();"
                            style="margin-right: 0.375rem;">
                        <span>Скрыть имена</span>
                    </label>
                    
                    <label class="checkbox-setting" style="font-size: 0.8rem; margin: 0;">
                        <input type="checkbox" name="show_under_10" value="1" 
                            {{ request('show_under_10') ? 'checked' : '' }}
                            onchange="submitForm()" style="margin-right: 0.375rem;">
                        <span>Показать до 10 заказов</span>
                    </label>
                    
                    <label class="checkbox-setting" style="font-size: 0.8rem; margin: 0;">
                        <input type="checkbox" name="show_city_summary" value="1" 
                            {{ request('show_city_summary') ? 'checked' : '' }}
                            onchange="submitForm()" style="margin-right: 0.375rem;">
                        <span>Города</span>
                    </label>
                </div>
                
                {{-- Сброс фильтров (как на странице заказов) --}}
                <div style="flex: 0 0 auto; display: flex; gap: 0.5rem; margin-left: auto;">
                    <x-ui.button
                        href="{{ route('hr.masters') }}"
                        variant="primary"
                        size="icon"
                        class="shrink-0"
                        title="Сбросить фильтры"
                    >
                        {!! icon('refresh') !!}
                    </x-ui.button>
                </div>
            </div>
        </div>

        {{-- Группы показателей таблицы — по одной карточке --}}
        <div class="hr-masters-column-groups" style="margin-bottom: 0.75rem;">
                    <div class="card hr-masters-metric-group setting-category-compact" style="padding: 0.75rem;">
                        <label class="checkbox-setting parent" style="font-size: 0.75rem; margin-bottom: 0.25rem;">
                            <input type="checkbox" id="avgCheckParent" onchange="toggleCategory('avgCheck', this.checked)" style="margin-right: 0.375rem;">
                            <span style="font-weight: 600;">Средний чек:</span>
                        </label>
                        <div class="setting-children-compact" id="avgCheckChildren">
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="avg_check" class="avgCheck-child"
                                    {{ in_array('avg_check', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Общий</span>
                            </label>
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="avg_check_profile" class="avgCheck-child"
                                    {{ in_array('avg_check_profile', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Профиль</span>
                            </label>
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="avg_check_non_profile" class="avgCheck-child"
                                    {{ in_array('avg_check_non_profile', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Непрофиль</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="card hr-masters-metric-group setting-category-compact" style="padding: 0.75rem;">
                        <label class="checkbox-setting parent" style="font-size: 0.75rem; margin-bottom: 0.25rem;">
                            <input type="checkbox" id="lowHighParent" onchange="toggleCategory('lowHigh', this.checked)" style="margin-right: 0.375rem;">
                            <span style="font-weight: 600;">Микра / чеки</span>
                        </label>
                        <div class="setting-children-compact" id="lowHighChildren">
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="low" class="lowHigh-child"
                                    {{ in_array('low', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Микра</span>
                            </label>
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="high" class="lowHigh-child"
                                    {{ in_array('high', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Чеки</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="card hr-masters-metric-group setting-category-compact" style="padding: 0.75rem;">
                        <label class="checkbox-setting parent" style="font-size: 0.75rem; margin-bottom: 0.25rem;">
                            <input type="checkbox" id="ordersCountParent" onchange="toggleCategory('ordersCount', this.checked)" style="margin-right: 0.375rem;">
                            <span style="font-weight: 600;">Количество заявок:</span>
                        </label>
                        <div class="setting-children-compact" id="ordersCountChildren">
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="all_orders" class="ordersCount-child"
                                    {{ in_array('all_orders', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Принято</span>
                            </label>
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="completed_count" class="ordersCount-child"
                                    {{ in_array('completed_count', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Закрыто.</span>
                            </label>
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="in_progress_count" class="ordersCount-child"
                                    {{ in_array('in_progress_count', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>В работе</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="card hr-masters-metric-group setting-category-compact" style="padding: 0.75rem;">
                        <label class="checkbox-setting parent" style="font-size: 0.75rem; margin-bottom: 0.25rem;">
                            <input type="checkbox" id="revenueParent" onchange="toggleCategory('revenue', this.checked)" style="margin-right: 0.375rem;">
                            <span style="font-weight: 600;">Выручка:</span>
                        </label>
                        <div class="setting-children-compact" id="revenueChildren">
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="completed_sum" class="revenue-child"
                                    {{ in_array('completed_sum', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Общая</span>
                            </label>
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="completed_sum_profile" class="revenue-child"
                                    {{ in_array('completed_sum_profile', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Профиль</span>
                            </label>
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="completed_sum_non_profile" class="revenue-child"
                                    {{ in_array('completed_sum_non_profile', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Непрофиль</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="card hr-masters-metric-group setting-category-compact" style="padding: 0.75rem;">
                        <label class="checkbox-setting parent" style="font-size: 0.75rem; margin-bottom: 0.25rem;">
                            <input type="checkbox" id="salaryParent" onchange="toggleCategory('salary', this.checked)" style="margin-right: 0.375rem;">
                            <span style="font-weight: 600;">Зарплата:</span>
                        </label>
                        <div class="setting-children-compact" id="salaryChildren">
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="salary" class="salary-child"
                                    {{ in_array('salary', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Общая</span>
                            </label>
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="salary_profile" class="salary-child"
                                    {{ in_array('salary_profile', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Профиль</span>
                            </label>
                            <label class="checkbox-setting child" style="font-size: 0.75rem; margin: 0;">
                                <input type="checkbox" name="columns[]" value="salary_non_profile" class="salary-child"
                                    {{ in_array('salary_non_profile', $columns) ? 'checked' : '' }}
                                    onchange="submitForm()" style="margin-right: 0.25rem;">
                                <span>Непрофиль</span>
                            </label>
                        </div>
                    </div>
        </div>
    </form>
    
    {{-- Таблица --}}
    <div class="card" style="padding: 0;">
            <div class="card" style="padding: 0;">
                <div class="table-responsive" style="overflow-x: auto;">
                    <input type="hidden" name="sort_by" id="sortBy" form="filtersForm" value="{{ $sortBy ?? '' }}">
                    <input type="hidden" name="sort_dir" id="sortDir" form="filtersForm" value="{{ $sortDir ?? 'desc' }}">
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th onclick="sortTable('user_name')" class="sortable">
                                    Мастер
                                    @if(($sortBy ?? '') === 'user_name')
                                        <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                    @endif
                                </th>
                                <th onclick="sortTable('cities')" class="sortable">
                                    Город
                                    @if(($sortBy ?? '') === 'cities')
                                        <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                    @endif
                                </th>
                                
                                @if(in_array('all_orders', $columns))
                                    <th onclick="sortTable('all_orders')" class="sortable" style="text-align: center;">
                                        Прин.
                                        @if(($sortBy ?? '') === 'all_orders')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if(in_array('completed_count', $columns))
                                    <th onclick="sortTable('completed_count')" class="sortable" style="text-align: center;">
                                        Зкр
                                        @if(($sortBy ?? '') === 'completed_count')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if(in_array('in_progress_count', $columns))
                                    <th onclick="sortTable('in_progress_count')" class="sortable" style="text-align: center;">
                                        В раб.
                                        @if(($sortBy ?? '') === 'in_progress_count')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                
                                @if(in_array('completed_sum', $columns))
                                    <th onclick="sortTable('completed_sum')" class="sortable" style="text-align: right;">
                                        Выручка общ.
                                        @if(($sortBy ?? '') === 'completed_sum')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if(in_array('completed_sum_profile', $columns))
                                    <th onclick="sortTable('completed_sum_profile')" class="sortable" style="text-align: right;">
                                        Выручка П
                                        @if(($sortBy ?? '') === 'completed_sum_profile')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if(in_array('completed_sum_non_profile', $columns))
                                    <th onclick="sortTable('completed_sum_non_profile')" class="sortable" style="text-align: right;">
                                        Выручка НП
                                        @if(($sortBy ?? '') === 'completed_sum_non_profile')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                
                                @if(in_array('salary', $columns))
                                    <th onclick="sortTable('salary')" class="sortable" style="text-align: right;">
                                        ЗП общая
                                        @if(($sortBy ?? '') === 'salary')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if(in_array('salary_profile', $columns))
                                    <th onclick="sortTable('salary_profile')" class="sortable" style="text-align: right;">
                                        ЗП Профиль
                                        @if(($sortBy ?? '') === 'salary_profile')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if(in_array('salary_non_profile', $columns))
                                    <th onclick="sortTable('salary_non_profile')" class="sortable" style="text-align: right;">
                                        ЗП Непрофиль
                                        @if(($sortBy ?? '') === 'salary_non_profile')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                
                                @if(in_array('avg_check', $columns))
                                    <th onclick="sortTable('avg_check')" class="sortable" style="text-align: right;">
                                        Ср. ч. общ.
                                        @if(($sortBy ?? '') === 'avg_check')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if(in_array('avg_check_profile', $columns))
                                    <th onclick="sortTable('avg_check_profile')" class="sortable" style="text-align: right;">
                                        Ср. ч. Профиль
                                        @if(($sortBy ?? '') === 'avg_check_profile')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if(in_array('avg_check_non_profile', $columns))
                                    <th onclick="sortTable('avg_check_non_profile')" class="sortable" style="text-align: right;">
                                        Ср. ч. непрофиль
                                        @if(($sortBy ?? '') === 'avg_check_non_profile')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                
                                @if(in_array('low', $columns))
                                    <th onclick="sortTable('low_orders')" class="sortable" style="text-align: center;">
                                        Микра
                                        @if(($sortBy ?? '') === 'low_orders')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                                @if(in_array('high', $columns))
                                    <th onclick="sortTable('high_orders')" class="sortable" style="text-align: center;">
                                        Чеки
                                        @if(($sortBy ?? '') === 'high_orders')
                                            <span class="sort-icon">{{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}</span>
                                        @endif
                                    </th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @php 
                                $showNames = request('show_names') !== '0';
                            @endphp
                            
                            {{-- Сводки по городам если включено --}}
                            @if(!empty($citySummaries))
                                @foreach($citySummaries as $cityId => $summary)
                                    <tr class="city-summary-row">
                                        <td colspan="2">
                                            <strong style="color: var(--primary);">{!! icon('location') !!} {{ $summary['city_name'] }} (сводка)</strong>
                                        </td>
                                        
                                        @if(in_array('all_orders', $columns))
                                            <td style="text-align: center; font-weight: 600;">{{ $summary['all_orders'] }}</td>
                                        @endif
                                        @if(in_array('completed_count', $columns))
                                            <td style="text-align: center; font-weight: 600;">{{ $summary['completed_count'] }}</td>
                                        @endif
                                        @if(in_array('in_progress_count', $columns))
                                            <td style="text-align: center; font-weight: 600;">{{ $summary['in_progress_count'] }}</td>
                                        @endif
                                        
                                        @if(in_array('completed_sum', $columns))
                                            <td style="text-align: right; font-weight: 600; color: #059669;">
                                                {{ number_format($summary['completed_sum'], 0, ',', ' ') }} 
                                            </td>
                                        @endif
                                        @if(in_array('completed_sum_profile', $columns))
                                            <td style="text-align: right; font-weight: 600; color: #059669;">
                                                {{ number_format($summary['completed_sum_profile'], 0, ',', ' ') }} 
                                            </td>
                                        @endif
                                        @if(in_array('completed_sum_non_profile', $columns))
                                            <td style="text-align: right; font-weight: 600; color: #059669;">
                                                {{ number_format($summary['completed_sum_non_profile'], 0, ',', ' ') }} 
                                            </td>
                                        @endif
                                        
                                        @if(in_array('salary', $columns))
                                            <td style="text-align: right; font-weight: 600; color: #7c3aed;">
                                                {{ number_format($summary['salary'], 0, ',', ' ') }} 
                                            </td>
                                        @endif
                                        @if(in_array('salary_profile', $columns))
                                            <td style="text-align: right; font-weight: 600; color: #7c3aed;">
                                                {{ number_format($summary['salary_profile'], 0, ',', ' ') }} 
                                            </td>
                                        @endif
                                        @if(in_array('salary_non_profile', $columns))
                                            <td style="text-align: right; font-weight: 600; color: #7c3aed;">
                                                {{ number_format($summary['salary_non_profile'], 0, ',', ' ') }} 
                                            </td>
                                        @endif
                                        
                                        @if(in_array('avg_check', $columns))
                                            <td style="text-align: right; font-weight: 600;">
                                                {{ number_format($summary['avg_check'], 0, ',', ' ') }} 
                                            </td>
                                        @endif
                                        @if(in_array('avg_check_profile', $columns))
                                            <td style="text-align: right; font-weight: 600;">
                                                {{ number_format($summary['avg_check_profile'], 0, ',', ' ') }} 
                                            </td>
                                        @endif
                                        @if(in_array('avg_check_non_profile', $columns))
                                            <td style="text-align: right; font-weight: 600;">
                                                {{ number_format($summary['avg_check_non_profile'], 0, ',', ' ') }} 
                                            </td>
                                        @endif
                                        
                                        @if(in_array('low', $columns))
                                            <td style="text-align: center; font-weight: 600;">{{ $summary['low_orders'] }}</td>
                                        @endif
                                        @if(in_array('high', $columns))
                                            <td style="text-align: center; font-weight: 600;">{{ $summary['high_orders'] }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endif
                            
                            @forelse($rating as $master)
                                <tr>
                                    <td>
                                        @if($showNames)
                                            <strong>{{ $master['user_name'] }}</strong>
                                        @else
                                            <span style="color: #6b7280;">Мастер {{ $master['user_id'] }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $master['cities'] }}</td>
                                    
                                    @if(in_array('all_orders', $columns))
                                        <td style="text-align: center;">{{ $master['all_orders'] }}</td>
                                    @endif
                                    @if(in_array('completed_count', $columns))
                                        <td style="text-align: center;">
                                            <span class="badge badge-success">{{ $master['completed_count'] }}</span>
                                        </td>
                                    @endif
                                    @if(in_array('in_progress_count', $columns))
                                        <td style="text-align: center;">
                                            @if($master['in_progress_count'] > 0)
                                                <span class="badge badge-warning">{{ $master['in_progress_count'] }}</span>
                                            @else
                                                <span style="color: #9ca3af;">0</span>
                                            @endif
                                        </td>
                                    @endif
                                    
                                    @if(in_array('completed_sum', $columns))
                                        <td style="text-align: right; font-weight: 500; color: #059669;">
                                            {{ number_format($master['completed_sum'], 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    @if(in_array('completed_sum_profile', $columns))
                                        <td style="text-align: right; font-weight: 500; color: #059669;">
                                            {{ number_format($master['completed_sum_profile'], 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    @if(in_array('completed_sum_non_profile', $columns))
                                        <td style="text-align: right; font-weight: 500; color: #059669;">
                                            {{ number_format($master['completed_sum_non_profile'], 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    
                                    @if(in_array('salary', $columns))
                                        <td style="text-align: right; font-weight: 500; color: #7c3aed;">
                                            {{ number_format($master['salary'], 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    @if(in_array('salary_profile', $columns))
                                        <td style="text-align: right; font-weight: 500; color: #7c3aed;">
                                            {{ number_format($master['salary_profile'], 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    @if(in_array('salary_non_profile', $columns))
                                        <td style="text-align: right; font-weight: 500; color: #7c3aed;">
                                            {{ number_format($master['salary_non_profile'], 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    
                                    @if(in_array('avg_check', $columns))
                                        <td style="text-align: right;">
                                            {{ number_format($master['avg_check'], 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    @if(in_array('avg_check_profile', $columns))
                                        <td style="text-align: right;">
                                            {{ number_format($master['avg_check_profile'], 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    @if(in_array('avg_check_non_profile', $columns))
                                        <td style="text-align: right;">
                                            {{ number_format($master['avg_check_non_profile'], 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    
                                    @if(in_array('low', $columns))
                                        <td style="text-align: center;">
                                            @if($master['low_orders'] > 0)
                                                <span class="badge badge-low">{{ $master['low_orders'] }}</span>
                                            @else
                                                <span style="color: #9ca3af;">0</span>
                                            @endif
                                        </td>
                                    @endif
                                    @if(in_array('high', $columns))
                                        <td style="text-align: center;">
                                            @if($master['high_orders'] > 0)
                                                <span class="badge badge-high">{{ $master['high_orders'] }}</span>
                                            @else
                                                <span style="color: #9ca3af;">0</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="20" style="text-align: center; color: #6b7280; padding: 2rem;">
                                        Мастера не найдены
                                    </td>
                                </tr>
                            @endforelse
                            
                            {{-- Итого --}}
                            @if($rating->count() > 0)
                                <tr class="total-row">
                                    <td colspan="2"><strong>ИТОГО</strong></td>
                                    
                                    @if(in_array('all_orders', $columns))
                                        <td style="text-align: center; font-weight: 600;">{{ $rating->sum('all_orders') }}</td>
                                    @endif
                                    @if(in_array('completed_count', $columns))
                                        <td style="text-align: center; font-weight: 600;">{{ $rating->sum('completed_count') }}</td>
                                    @endif
                                    @if(in_array('in_progress_count', $columns))
                                        <td style="text-align: center; font-weight: 600;">{{ $rating->sum('in_progress_count') }}</td>
                                    @endif
                                    
                                    @if(in_array('completed_sum', $columns))
                                        <td style="text-align: right; font-weight: 600; color: #059669;">
                                            {{ number_format($rating->sum('completed_sum'), 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    @if(in_array('completed_sum_profile', $columns))
                                        <td style="text-align: right; font-weight: 600; color: #059669;">
                                            {{ number_format($rating->sum('completed_sum_profile'), 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    @if(in_array('completed_sum_non_profile', $columns))
                                        <td style="text-align: right; font-weight: 600; color: #059669;">
                                            {{ number_format($rating->sum('completed_sum_non_profile'), 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    
                                    @if(in_array('salary', $columns))
                                        <td style="text-align: right; font-weight: 600; color: #7c3aed;">
                                            {{ number_format($rating->sum('salary'), 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    @if(in_array('salary_profile', $columns))
                                        <td style="text-align: right; font-weight: 600; color: #7c3aed;">
                                            {{ number_format($rating->sum('salary_profile'), 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    @if(in_array('salary_non_profile', $columns))
                                        <td style="text-align: right; font-weight: 600; color: #7c3aed;">
                                            {{ number_format($rating->sum('salary_non_profile'), 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    
                                    @if(in_array('avg_check', $columns))
                                        <td style="text-align: right; font-weight: 600;">
                                            @php
                                                $totalSum = $rating->sum('completed_sum');
                                                $totalCount = $rating->sum('completed_count');
                                                $totalAvg = $totalCount > 0 ? round($totalSum / $totalCount) : 0;
                                            @endphp
                                            {{ number_format($totalAvg, 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    @if(in_array('avg_check_profile', $columns))
                                        <td style="text-align: right; font-weight: 600;">
                                            @php
                                                $totalSumProfile = $rating->sum('completed_sum_profile');
                                                $totalCountProfile = $rating->sum('completed_profile');
                                                $totalAvgProfile = $totalCountProfile > 0 ? round($totalSumProfile / $totalCountProfile) : 0;
                                            @endphp
                                            {{ number_format($totalAvgProfile, 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    @if(in_array('avg_check_non_profile', $columns))
                                        <td style="text-align: right; font-weight: 600;">
                                            @php
                                                $totalSumNonProfile = $rating->sum('completed_sum_non_profile');
                                                $totalCountNonProfile = $rating->sum('completed_non_profile');
                                                $totalAvgNonProfile = $totalCountNonProfile > 0 ? round($totalSumNonProfile / $totalCountNonProfile) : 0;
                                            @endphp
                                            {{ number_format($totalAvgNonProfile, 0, ',', ' ') }} 
                                        </td>
                                    @endif
                                    
                                    @if(in_array('low', $columns))
                                        <td style="text-align: center; font-weight: 600;">{{ $rating->sum('low_orders') }}</td>
                                    @endif
                                    @if(in_array('high', $columns))
                                        <td style="text-align: center; font-weight: 600;">{{ $rating->sum('high_orders') }}</td>
                                    @endif
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function submitForm() {
    document.getElementById('filtersForm').submit();
}

function setPeriod(period) {
    document.getElementById('periodInput').value = period;
    // Очистить даты
    document.querySelector('input[name="date_from"]').value = '';
    document.querySelector('input[name="date_to"]').value = '';
    submitForm();
}

function clearPeriod() {
    document.getElementById('periodInput').value = '';
}

function sortTable(column) {
    const sortBy = document.getElementById('sortBy');
    const sortDir = document.getElementById('sortDir');
    
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDir.value = 'desc';
    }
    
    submitForm();
}

function toggleCategory(category, checked) {
    const checkboxes = document.querySelectorAll(`.${category}-child`);
    checkboxes.forEach(cb => cb.checked = checked);
    submitForm();
}

function toggleMultiselect(element) {
    const dropdown = element.nextElementSibling;
    const wasOpen = dropdown.classList.contains('open');
    document.querySelectorAll('.multiselect-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!wasOpen) {
        dropdown.classList.add('open');
    }
}

function updateMultiselect(checkbox) {
    const multiselect = checkbox.closest('.multiselect');
    const checkboxes = multiselect.querySelectorAll('input[type="checkbox"]');
    const selected = Array.from(checkboxes).filter(cb => cb.checked);
    const textElement = multiselect.querySelector('.multiselect-text');
    
    if (!textElement.dataset.placeholder) {
        textElement.dataset.placeholder = textElement.textContent;
    }
    
    if (selected.length === 0) {
        textElement.textContent = textElement.dataset.placeholder;
        textElement.style.color = '';
        textElement.classList.add('text-muted-foreground');
    } else if (selected.length === 1) {
        textElement.textContent = selected[0].parentElement.textContent.trim();
        textElement.style.color = '';
        textElement.classList.remove('text-muted-foreground');
    } else {
        textElement.textContent = `Выбрано: ${selected.length}`;
        textElement.style.color = '';
        textElement.classList.remove('text-muted-foreground');
    }
}

function selectAll(button) {
    event.preventDefault();
    const multiselect = button.closest('.multiselect');
    const checkboxes = multiselect.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = true);
    if (checkboxes[0]) {
        updateMultiselect(checkboxes[0]);
    }
}

function deselectAll(button) {
    event.preventDefault();
    const multiselect = button.closest('.multiselect');
    const checkboxes = multiselect.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
    if (checkboxes[0]) {
        updateMultiselect(checkboxes[0]);
    }
    const form = button.closest('form');
    if (form) form.submit();
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.multiselect')) {
        document.querySelectorAll('.multiselect-dropdown.open').forEach(d => d.classList.remove('open'));
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.multiselect').forEach(multiselect => {
        const firstCheckbox = multiselect.querySelector('input[type="checkbox"]');
        if (firstCheckbox) {
            updateMultiselect(firstCheckbox);
        }
    });
    
    // Инициализация родительских чекбоксов
    ['avgCheck', 'lowHigh', 'ordersCount', 'revenue', 'salary'].forEach(category => {
        const children = document.querySelectorAll(`.${category}-child`);
        const parent = document.getElementById(`${category}Parent`);
        if (parent) {
            const anyChecked = Array.from(children).some(cb => cb.checked);
            parent.checked = anyChecked;
        }
    });
    
});
</script>
@endpush
