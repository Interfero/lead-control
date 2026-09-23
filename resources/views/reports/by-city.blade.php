@extends('layouts.app')

@section('title', 'Отчёт по городу')

@section('content')
@php
    $money = fn ($v) => number_format((int) $v, 0, ',', ' ') . ' р.';
    $num = fn ($v, $dec = 0) => number_format((float) $v, $dec, ',', ' ');
    $ref = now();
    $today = $ref->format('Y-m-d');
    $monthStart = $ref->copy()->startOfMonth()->format('Y-m-d');
    $presetMonth = $dateFrom === $monthStart && $dateTo === $today;
@endphp
<div class="mx-auto max-w-[1800px] min-w-0">
    <div class="orders-index-toolbar mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Отчёты', 'url' => route('reports.by-city')],
                ['label' => 'Отчёт по городу', 'url' => null],
            ]"
        />
    </div>

    <x-ui.card padding="none" class="mb-4">
        <form method="GET" action="{{ route('reports.by-city') }}" class="min-w-0">
            <div class="orders-filters-sticky flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
                <x-ui.filter-dates :date-from="$dateFrom" :date-to="$dateTo" :show-closed-dates="false" />
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Город</label>
                    <select name="city_id" class="form-input text-sm" onchange="this.form.submit()">
                        <option value="">Все города</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->city_id }}" @selected(request('city_id') == $city->city_id)>{{ $city->city_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button href="{{ route('reports.by-city', ['date_from' => $monthStart, 'date_to' => $today]) }}" :variant="$presetMonth ? 'primary' : 'outline'" size="sm">С начала месяца</x-ui.button>
                    <x-ui.button href="{{ route('reports.by-city', ['period' => 'today']) }}" variant="outline" size="sm">Сегодня</x-ui.button>
                    <x-ui.button href="{{ route('reports.by-city', ['period' => 'yesterday']) }}" variant="outline" size="sm">Вчера</x-ui.button>
                </div>
                <div class="ml-auto flex gap-2">
                    <x-reports.export-button route="reports.by-city" />
                    <x-ui.button href="{{ route('reports.by-city') }}" variant="primary" size="icon" title="Сбросить">{!! icon('refresh') !!}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="icon" title="Поиск">{!! icon('search') !!}</x-ui.button>
                </div>
            </div>
            <p class="px-4 py-2 text-sm text-muted-foreground">{{ $periodLabel }}</p>
        </form>

        <div class="overflow-x-auto">
            <table class="table w-full text-sm">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Город</th>
                        <th>Сумма филиала</th>
                        <th>Прогноз оборота</th>
                        <th>% приемки</th>
                        <th>Принято продаж</th>
                        <th>Заявок открыто</th>
                        <th>Закрыто выезд</th>
                        <th>Закрыто стац</th>
                        <th>Чек выезд</th>
                        <th>Средний чек</th>
                        <th>Чек стационарный</th>
                        <th>Гарантия</th>
                        <th>Отказ/НФ</th>
                        <th>Принял заявок</th>
                        <th>% П Оборот</th>
                        <th>% П Заявки</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td class="font-semibold">
                                <a href="{{ route('reports.city-employee', ['city_id' => $row['city_id'], 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="text-primary hover:underline">
                                    {{ $row['city_name'] }}
                                </a>
                            </td>
                            <td>{{ $money($row['branch_sum']) }}</td>
                            <td>{{ $money($row['forecast']) }}</td>
                            <td>{{ $num($row['acceptance_pct'], 1) }}%</td>
                            <td>{{ $row['sales_accepted'] }}</td>
                            <td>{{ $row['orders_open'] }}</td>
                            <td>{{ $row['closed_visit'] }}</td>
                            <td>{{ $row['closed_stationary'] }}</td>
                            <td>{{ $money($row['check_visit']) }}</td>
                            <td>{{ $money($row['avg_check']) }}</td>
                            <td>{{ $num($row['check_stationary'], 1) }}</td>
                            <td>{{ $money($row['warranty_sum']) }}</td>
                            <td>{{ $row['refusal_nf'] }}</td>
                            <td>{{ $row['taken_by_cc'] }}</td>
                            <td>{{ $num($row['pct_p_turnover'], 2) }}%</td>
                            <td>{{ $num($row['pct_p_orders'], 2) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
                @if (count($rows) > 0)
                <tfoot>
                    <tr class="bg-muted font-bold">
                        <td></td>
                        <td>Итого</td>
                        <td>{{ $money($totals['branch_sum'] ?? 0) }}</td>
                        <td>{{ $money($totals['forecast'] ?? 0) }}</td>
                        <td>{{ isset($totals['acceptance_pct']) ? $num($totals['acceptance_pct'], 1).'%' : '—' }}</td>
                        <td>{{ $totals['sales_accepted'] ?? 0 }}</td>
                        <td>{{ $totals['orders_open'] ?? 0 }}</td>
                        <td>{{ $totals['closed_visit'] ?? 0 }}</td>
                        <td>{{ $totals['closed_stationary'] ?? 0 }}</td>
                        <td>{{ $money($totals['check_visit'] ?? 0) }}</td>
                        <td>{{ isset($totals['avg_check']) ? $money($totals['avg_check']) : '—' }}</td>
                        <td>{{ isset($totals['check_stationary']) ? $num($totals['check_stationary'], 1) : '—' }}</td>
                        <td>{{ $money($totals['warranty_sum'] ?? 0) }}</td>
                        <td>{{ $totals['refusal_nf'] ?? 0 }}</td>
                        <td>{{ $totals['taken_by_cc'] ?? 0 }}</td>
                        <td>{{ isset($totals['pct_p_turnover']) ? $num($totals['pct_p_turnover'], 2).'%' : '—' }}</td>
                        <td>{{ isset($totals['pct_p_orders']) ? $num($totals['pct_p_orders'], 2).'%' : '—' }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
        <div class="flex flex-wrap items-center gap-3 border-t border-border px-4 py-3">
            <span class="text-sm text-muted-foreground">Скачать отчёт:</span>
            <x-reports.export-button route="reports.by-city" />
        </div>
    </x-ui.card>
    <p class="text-xs text-muted-foreground">
        Закрытые — по дате проведения; открытые/принятые — по дате создания.
        Выезд = профильные (core, other), стационар = непрофильные (non_core).
        % приемки — доля непрофильных среди принятых; % П — доля профильных в обороте и в закрытых заявках.
    </p>
</div>
@endsection
