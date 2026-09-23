@extends('layouts.app')

@section('title', 'Отчёт по закрытым заявкам')

@section('content')
@php
    $money = fn ($v) => number_format((int) $v, 0, ',', ' ') . ' р.';
    $ref = now();
    $today = $ref->format('Y-m-d');
    $monthStart = $ref->copy()->startOfMonth()->format('Y-m-d');
    $presetMonth = $dateFrom === $monthStart && $dateTo === $today;
    $sort = $sort ?? 'turnover';
    $dir = $dir ?? 'desc';
    $sortLink = function (string $field) use ($sort, $dir) {
        $nextDir = ($sort === $field && $dir === 'desc') ? 'asc' : 'desc';

        return '?'.http_build_query(array_merge(request()->except('export'), ['sort' => $field, 'dir' => $nextDir]));
    };
    $sortArrow = fn (string $field) => $sort === $field ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
@endphp
<div class="mx-auto max-w-[1800px] min-w-0">
    <div class="orders-index-toolbar mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Отчёты', 'url' => route('reports.closed-orders')],
                ['label' => 'По закрытым заявкам', 'url' => null],
            ]"
        />
    </div>

    <x-ui.card padding="none" class="mb-4">
        <form method="GET" action="{{ route('reports.closed-orders') }}" class="min-w-0">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="dir" value="{{ $dir }}">
            <div class="orders-filters-sticky flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Город</label>
                    <select name="city_id" class="form-input text-sm" onchange="this.form.submit()">
                        <option value="">Все города</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->city_id }}" @selected(request('city_id') == $city->city_id)>{{ $city->city_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Период</label>
                    <x-ui.filter-dates :date-from="$dateFrom" :date-to="$dateTo" :show-closed-dates="false" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button href="{{ route('reports.closed-orders', array_filter(['date_from' => $monthStart, 'date_to' => $today, 'city_id' => request('city_id'), 'sort' => $sort, 'dir' => $dir])) }}" :variant="$presetMonth ? 'primary' : 'outline'" size="sm">С начала месяца</x-ui.button>
                    <x-ui.button href="{{ route('reports.closed-orders', array_filter(['period' => 'today', 'city_id' => request('city_id'), 'sort' => $sort, 'dir' => $dir])) }}" variant="outline" size="sm">Сегодня</x-ui.button>
                    <x-ui.button href="{{ route('reports.closed-orders', array_filter(['period' => 'yesterday', 'city_id' => request('city_id'), 'sort' => $sort, 'dir' => $dir])) }}" variant="outline" size="sm">Вчера</x-ui.button>
                </div>
                <div class="ml-auto flex gap-2 items-end">
                    <x-reports.export-button route="reports.closed-orders" :formats="['csv']" />
                    <x-ui.button href="{{ route('reports.closed-orders') }}" variant="primary" size="icon" title="Сбросить">{!! icon('refresh') !!}</x-ui.button>
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
                        <th><a href="{{ $sortLink('city_name') }}" class="text-inherit hover:text-primary">Город{{ $sortArrow('city_name') }}</a></th>
                        <th><a href="{{ $sortLink('turnover') }}" class="text-inherit hover:text-primary">Сумма филиала{{ $sortArrow('turnover') }}</a></th>
                        <th>Прогноз оборота</th>
                        <th><a href="{{ $sortLink('complaints_pct') }}" class="text-inherit hover:text-primary">% претензий{{ $sortArrow('complaints_pct') }}</a></th>
                        <th><a href="{{ $sortLink('closed_total') }}" class="text-inherit hover:text-primary">Заявок закрыто{{ $sortArrow('closed_total') }}</a></th>
                        <th><a href="{{ $sortLink('closed_our') }}" class="text-inherit hover:text-primary">Закрыто наши{{ $sortArrow('closed_our') }}</a></th>
                        <th><a href="{{ $sortLink('closed_partner') }}" class="text-inherit hover:text-primary">Закрыто партн.{{ $sortArrow('closed_partner') }}</a></th>
                        <th><a href="{{ $sortLink('net') }}" class="text-inherit hover:text-primary">Чистыми{{ $sortArrow('net') }}</a></th>
                        <th><a href="{{ $sortLink('avg_check') }}" class="text-inherit hover:text-primary">Средний чек{{ $sortArrow('avg_check') }}</a></th>
                        <th><a href="{{ $sortLink('net_avg_check') }}" class="text-inherit hover:text-primary">Чистый средний{{ $sortArrow('net_avg_check') }}</a></th>
                        <th><a href="{{ $sortLink('lead_price') }}" class="text-inherit hover:text-primary">Цена заявки{{ $sortArrow('lead_price') }}</a></th>
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
                            <td>{{ $money($row['turnover']) }}</td>
                            <td>{{ $money($row['forecast']) }}</td>
                            <td>{{ $row['complaints_pct'] }}%</td>
                            <td>{{ $row['closed_total'] }}</td>
                            <td>{{ $row['closed_our'] }}</td>
                            <td>{{ $row['closed_partner'] }}</td>
                            <td>{{ $money($row['net']) }}</td>
                            <td>{{ $money($row['avg_check']) }}</td>
                            <td>{{ $money($row['net_avg_check']) }}</td>
                            <td>{{ ($row['price_order_count'] ?? 0) > 0 ? $money($row['lead_price']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                @if (count($rows) > 0)
                <tfoot>
                    <tr class="bg-muted font-bold">
                        <td></td>
                        <td>Итого</td>
                        <td>{{ $money($totals['turnover'] ?? 0) }}</td>
                        <td>{{ $money($totals['forecast'] ?? 0) }}</td>
                        <td>{{ $totals['complaints_pct'] ?? 0 }}%</td>
                        <td>{{ $totals['closed_total'] ?? 0 }}</td>
                        <td>{{ $totals['closed_our'] ?? 0 }}</td>
                        <td>{{ $totals['closed_partner'] ?? 0 }}</td>
                        <td>{{ $money($totals['net'] ?? 0) }}</td>
                        <td>{{ isset($totals['avg_check']) ? $money($totals['avg_check']) : '—' }}</td>
                        <td>{{ isset($totals['net_avg_check']) ? $money($totals['net_avg_check']) : '—' }}</td>
                        <td>{{ ($totals['price_order_count'] ?? 0) > 0 ? $money($totals['lead_price'] ?? 0) : '—' }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </x-ui.card>
    <p class="text-xs text-muted-foreground">
        Закрытые — по дате проведения. «Наши» — без партнёра (SuperPart / партнёрский источник). «Чистыми» — оплачено минус комплектующие.
        Цена заявки — выплаты промоутерам (выплаты в модуле промо или касса «Зарплата промоутеров») ÷ число закрытых заявок (если закрытых нет — по принятым).
    </p>
</div>
@endsection
