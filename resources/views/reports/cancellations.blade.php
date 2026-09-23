@extends('layouts.app')

@section('title', 'Статистика отмен и отказов')

@section('content')
    <div class="mx-auto max-w-[1400px] min-w-0">
        <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
                <x-breadcrumbs
                    :items="[
                        ['label' => 'Главная', 'url' => route('orders.index')],
                        ['label' => 'Отчёты', 'url' => route('reports.orders')],
                        ['label' => 'Отмены и отказы', 'url' => null],
                    ]"
                />
            </div>
        </div>
        <x-ui.card padding="none" class="min-w-0 max-w-full">
            <form method="GET" action="{{ route('reports.cancellations') }}" id="filtersForm" class="min-w-0 max-w-full">
                <div class="orders-filters-sticky flex flex-wrap items-end gap-4 border-b border-border px-4 py-2">
                    <x-ui.filter-dates
                        :date-from="$dateFrom"
                        :date-to="$dateTo"
                        :show-closed-dates="false"
                    />
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <x-reports.export-button route="reports.cancellations" :formats="['csv']" />
                        <x-ui.button
                            href="{{ route('reports.cancellations') }}"
                            variant="primary"
                            size="icon"
                            class="shrink-0"
                            title="Сбросить фильтры"
                        >
                            {!! icon('refresh') !!}
                        </x-ui.button>
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            size="icon"
                            class="shrink-0"
                            title="Применить фильтр"
                        >
                            {!! icon('search') !!}
                        </x-ui.button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="table orders-sticky-table w-full min-w-0 text-sm">
                        <thead>
                            <tr>
                                <th>Город</th>
                                <th>Всего заявок</th>
                                <th>Закрыто</th>
                                <th>Ср. чек</th>
                                <th>Отмен Филиала</th>
                                <th>Отмен КЦ</th>
                                <th>Отмен Всего</th>
                                <th>% Отмен Филиала</th>
                                <th>% Отмен Всего</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totals = ['total'=>0, 'completed'=>0, 'cancelled_city'=>0, 'cancelled_cc'=>0, 'cancelled_total'=>0, 'total_check'=>0]; @endphp
                            @foreach($report as $row)
                                @php
                                    $totals['total'] += $row['total'];
                                    $totals['completed'] += $row['completed'];
                                    $totals['cancelled_city'] += $row['cancelled_city'];
                                    $totals['cancelled_cc'] += $row['cancelled_cc'];
                                    $totals['cancelled_total'] += $row['cancelled_total'];
                                    $totals['total_check'] += $row['avg_check'] * $row['completed'];
                                @endphp
                                <tr>
                                    <td class="font-semibold">{{ $row['city_name'] }}</td>
                                    <td>{{ $row['total'] }}</td>
                                    <td>{{ $row['completed'] }}</td>
                                    <td>{{ number_format($row['avg_check'], 0, ',', ' ') }} р.</td>
                                    <td>{{ $row['cancelled_city'] }}</td>
                                    <td>{{ $row['cancelled_cc'] }}</td>
                                    <td>{{ $row['cancelled_total'] }}</td>
                                    <td>{{ $row['cancel_city_pct'] }}%</td>
                                    <td>{{ $row['cancel_total_pct'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-border bg-muted font-bold">
                                <td>ИТОГО</td>
                                <td>{{ $totals['total'] }}</td>
                                <td>{{ $totals['completed'] }}</td>
                                <td>{{ $totals['completed'] > 0 ? number_format(round($totals['total_check'] / $totals['completed']), 0, ',', ' ') : 0 }} р.</td>
                                <td>{{ $totals['cancelled_city'] }}</td>
                                <td>{{ $totals['cancelled_cc'] }}</td>
                                <td>{{ $totals['cancelled_total'] }}</td>
                                <td>{{ $totals['total'] > 0 ? round($totals['cancelled_city'] / $totals['total'] * 100, 1) : 0 }}%</td>
                                <td>{{ $totals['total'] > 0 ? round($totals['cancelled_total'] / $totals['total'] * 100, 1) : 0 }}%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
