@extends('layouts.app')

@section('title', 'Отчёт по кассе')

@section('content')
@php
    $ref = now();
    $today = $ref->format('Y-m-d');
    $yesterday = $ref->copy()->subDay()->format('Y-m-d');
    $monthStart = $ref->copy()->startOfMonth()->format('Y-m-d');
    $presetMonth = $dateFrom === $monthStart && $dateTo === $today;
    $presetYesterday = $dateFrom === $yesterday && $dateTo === $yesterday;
    $presetToday = $dateFrom === $today && $dateTo === $today;
    $summaryQueryPreserve = request()->only(['sort', 'dir']);

    $sort = request('sort', 'city_name');
    $dir = request('dir', 'asc');
    $nextDir = $dir === 'asc' ? 'desc' : 'asc';
    $exportQuery = array_merge(request()->except('export'), []);
    $exportXlsxUrl = route('cfm.summary', array_merge($exportQuery, ['export' => 'xlsx']));
    $exportCsvUrl = route('cfm.summary', array_merge($exportQuery, ['export' => 'csv']));

    $sortedCities = collect($report['cities'])->sortBy(function ($city) use ($sort) {
        return $city[$sort] ?? 0;
    }, SORT_REGULAR, $dir === 'desc')->values();

    $fmt = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $moneyPlus = fn ($v) => '+'.$fmt($v);
    $moneyMinus = fn ($v) => ((int) $v > 0 ? '-' : '').$fmt($v);
    $showOtherInflows = collect($report['cities'])->contains(fn ($city) => (int) ($city['other_inflows'] ?? 0) > 0)
        || (int) ($report['totals']['other_inflows'] ?? 0) > 0;

    $sortUrl = function (string $column, string $defaultDir = 'desc') use ($sort, $nextDir) {
        return '?'.http_build_query(array_merge(
            request()->except('sort', 'dir'),
            ['sort' => $column, 'dir' => $sort === $column ? $nextDir : $defaultDir]
        ));
    };
@endphp
<div class="cfm-summary-page">
    <div class="cfm-summary-toolbar">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Касса', 'url' => route('cfm.index')],
                ['label' => 'Отчёт по кассе', 'url' => null],
            ]"
        />
        @if (auth()->user()->hasAnyRole(['developer', 'general_director']) && $mcCity && $mcBalance !== null)
            <a
                href="{{ route('cfm.summary.city', ['city_id' => $mcCity->city_id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                class="cfm-summary-uk"
                title="Баланс Управляющей Компании"
            >
                {!! icon('bank') !!}
                <span>УК</span>
                <span class="cfm-summary-uk__sum">{{ $fmt($mcBalance) }} ₽</span>
            </a>
        @endif
    </div>

    <form method="GET" action="{{ route('cfm.summary') }}" id="cfmSummaryFiltersForm" class="cfm-summary-filters">
        @foreach (['sort', 'dir'] as $_cfmSortKey)
            @if (request()->filled($_cfmSortKey))
                <input type="hidden" name="{{ $_cfmSortKey }}" value="{{ request($_cfmSortKey) }}">
            @endif
        @endforeach

        <div class="cfm-summary-dates">
            <label>
                <span>От</span>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="cfm-summary-date-input">
            </label>
            <label>
                <span>По</span>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="cfm-summary-date-input">
            </label>
        </div>

        <div class="cfm-summary-presets">
            <a href="{{ route('cfm.summary', array_merge($summaryQueryPreserve, ['date_from' => $monthStart, 'date_to' => $today])) }}"
               class="cfm-summary-chip {{ $presetMonth ? 'is-active' : '' }}">С начала месяца</a>
            <a href="{{ route('cfm.summary', array_merge($summaryQueryPreserve, ['date_from' => $yesterday, 'date_to' => $yesterday])) }}"
               class="cfm-summary-chip {{ $presetYesterday ? 'is-active' : '' }}">Вчера</a>
            <a href="{{ route('cfm.summary', array_merge($summaryQueryPreserve, ['date_from' => $today, 'date_to' => $today])) }}"
               class="cfm-summary-chip {{ $presetToday ? 'is-active' : '' }}">Сегодня</a>
        </div>

        <div class="cfm-summary-actions">
            <a href="{{ route('cfm.summary', array_merge($summaryQueryPreserve, ['date_from' => $monthStart, 'date_to' => $today])) }}" class="cfm-summary-icon-btn" title="Сбросить период на «с начала месяца»">
                {!! icon('refresh') !!}
            </a>
            <button type="submit" class="cfm-summary-submit">
                {!! icon('search') !!}
                <span>Показать</span>
            </button>
        </div>
    </form>

    <p class="mb-3 text-xs text-muted-foreground">
        Период: {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d.m.Y') }} — {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d.m.Y') }}.
        Колонки прихода и расхода — за период. Остаток на дату «по»: сколько в кассе (все поступления минус все расходы с начала учёта).
    </p>

    {{-- Desktop table --}}
    <div class="cfm-summary-desktop">
        <div class="cfm-summary-table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th><a href="{{ $sortUrl('city_name', 'asc') }}" class="sort-link">Город {{ $sort === 'city_name' ? ($dir === 'asc' ? '▲' : '▼') : '' }}</a></th>
                        <th class="text-right"><a href="{{ $sortUrl('order_income') }}" class="sort-link">Приход с Заказов {{ $sort === 'order_income' ? ($dir === 'asc' ? '▲' : '▼') : '' }}</a></th>
                        @if ($showOtherInflows)
                            <th class="text-right"><a href="{{ $sortUrl('other_inflows') }}" class="sort-link">Прочее поступление {{ $sort === 'other_inflows' ? ($dir === 'asc' ? '▲' : '▼') : '' }}</a></th>
                        @endif
                        <th class="text-right"><a href="{{ $sortUrl('promo_salary') }}" class="sort-link">Зарплата промоутеров {{ $sort === 'promo_salary' ? ($dir === 'asc' ? '▲' : '▼') : '' }}</a></th>
                        <th class="text-right"><a href="{{ $sortUrl('incas') }}" class="sort-link">Инкас {{ $sort === 'incas' ? ($dir === 'asc' ? '▲' : '▼') : '' }}</a></th>
                        <th class="text-right"><a href="{{ $sortUrl('ads_expense') }}" class="sort-link">Расход объявления {{ $sort === 'ads_expense' ? ($dir === 'asc' ? '▲' : '▼') : '' }}</a></th>
                        <th class="text-right"><a href="{{ $sortUrl('total_outflows') }}" class="sort-link">Общий расход {{ $sort === 'total_outflows' ? ($dir === 'asc' ? '▲' : '▼') : '' }}</a></th>
                        <th class="text-right font-semibold"><a href="{{ $sortUrl('balance') }}" class="sort-link">Остаток {{ $sort === 'balance' ? ($dir === 'asc' ? '▲' : '▼') : '' }}</a></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-muted font-semibold">
                        <td>ИТОГО</td>
                        <td class="text-right text-[color:var(--success)]">{{ $moneyPlus($report['totals']['order_income']) }}</td>
                        @if ($showOtherInflows)
                            <td class="text-right text-[color:var(--success)]">{{ $moneyPlus($report['totals']['other_inflows'] ?? 0) }}</td>
                        @endif
                        <td class="text-right text-destructive">-{{ $fmt($report['totals']['promo_salary']) }}</td>
                        <td class="text-right text-destructive">-{{ $fmt($report['totals']['incas']) }}</td>
                        <td class="text-right text-destructive">-{{ $fmt($report['totals']['ads_expense']) }}</td>
                        <td class="text-right text-destructive">-{{ $fmt($report['totals']['total_outflows'] ?? 0) }}</td>
                        <td class="text-right">
                            <span @class([
                                'text-[color:var(--success)]' => $report['totals']['balance'] >= 0,
                                'text-destructive' => $report['totals']['balance'] < 0,
                            ])>{{ $fmt($report['totals']['balance']) }}</span>
                        </td>
                    </tr>
                    @forelse($sortedCities as $cityData)
                        <tr
                            class="order-row cursor-pointer"
                            onclick="window.location='{{ route('cfm.summary.city', ['city_id' => $cityData['city_id'], 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}'"
                        >
                            <td class="text-primary">{{ $cityData['city_name'] }}</td>
                            <td class="text-right text-[color:var(--success)]">{{ $moneyPlus($cityData['order_income']) }}</td>
                            @if ($showOtherInflows)
                                <td class="text-right text-[color:var(--success)]">{{ $moneyPlus($cityData['other_inflows'] ?? 0) }}</td>
                            @endif
                            <td class="text-right text-destructive">{{ $moneyMinus($cityData['promo_salary']) }}</td>
                            <td class="text-right text-destructive">{{ $moneyMinus($cityData['incas']) }}</td>
                            <td class="text-right text-destructive">{{ $moneyMinus($cityData['ads_expense']) }}</td>
                            <td class="text-right text-destructive">{{ $moneyMinus($cityData['total_outflows'] ?? 0) }}</td>
                            <td class="text-right font-medium">
                                <span @class([
                                    'text-[color:var(--success)]' => $cityData['balance'] >= 0,
                                    'text-destructive' => $cityData['balance'] < 0,
                                ])>{{ $fmt($cityData['balance']) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 7 + ($showOtherInflows ? 1 : 0) }}" class="px-4 py-8 text-center text-muted-foreground">Нет данных за выбранный период</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile cards --}}
    <div class="cfm-summary-mobile">
        <div class="cfm-summary-card cfm-summary-card--total">
            <div class="cfm-summary-card__head">
                <div class="cfm-summary-card__title">ИТОГО</div>
                <div @class([
                    'cfm-summary-card__balance',
                    'is-pos' => $report['totals']['balance'] >= 0,
                    'is-neg' => $report['totals']['balance'] < 0,
                ])>{{ $fmt($report['totals']['balance']) }}</div>
            </div>
            <div class="cfm-summary-card__grid">
                <div><span>Приход</span><b class="is-pos">{{ $moneyPlus($report['totals']['order_income']) }}</b></div>
                <div><span>Промо</span><b class="is-neg">-{{ $fmt($report['totals']['promo_salary']) }}</b></div>
                <div><span>Инкас</span><b class="is-neg">-{{ $fmt($report['totals']['incas']) }}</b></div>
                <div><span>Объявл.</span><b class="is-neg">-{{ $fmt($report['totals']['ads_expense']) }}</b></div>
                <div class="cfm-summary-card__span"><span>Общий расход</span><b class="is-neg">-{{ $fmt($report['totals']['total_outflows'] ?? 0) }}</b></div>
            </div>
        </div>

        @forelse($sortedCities as $cityData)
            <a
                class="cfm-summary-card"
                href="{{ route('cfm.summary.city', ['city_id' => $cityData['city_id'], 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
            >
                <div class="cfm-summary-card__head">
                    <div class="cfm-summary-card__title">{{ $cityData['city_name'] }}</div>
                    <div @class([
                        'cfm-summary-card__balance',
                        'is-pos' => $cityData['balance'] >= 0,
                        'is-neg' => $cityData['balance'] < 0,
                    ])>{{ $fmt($cityData['balance']) }}</div>
                </div>
                <div class="cfm-summary-card__grid">
                    <div><span>Приход</span><b class="is-pos">{{ $moneyPlus($cityData['order_income']) }}</b></div>
                    <div><span>Промо</span><b class="is-neg">{{ $moneyMinus($cityData['promo_salary']) }}</b></div>
                    <div><span>Инкас</span><b class="is-neg">{{ $moneyMinus($cityData['incas']) }}</b></div>
                    <div><span>Объявл.</span><b class="is-neg">{{ $moneyMinus($cityData['ads_expense']) }}</b></div>
                    <div class="cfm-summary-card__span"><span>Общий расход</span><b class="is-neg">{{ $moneyMinus($cityData['total_outflows'] ?? 0) }}</b></div>
                </div>
            </a>
        @empty
            <div class="cfm-summary-empty">Нет данных за выбранный период</div>
        @endforelse
    </div>

    <div class="cfm-summary-export">
        <a href="{{ $exportXlsxUrl }}" class="cfm-summary-export__btn is-primary">Скачать Excel</a>
        <a href="{{ $exportCsvUrl }}" class="cfm-summary-export__btn">CSV</a>
    </div>
</div>
@endsection
