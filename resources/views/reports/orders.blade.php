@extends('layouts.app')

@section('title', 'Статистика по заявкам')

@section('content')
    <div class="mx-auto max-w-[1600px] min-w-0">
        <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
                <x-breadcrumbs
                    :items="[
                        ['label' => 'Главная', 'url' => route('orders.index')],
                        ['label' => 'Отчёты', 'url' => route('reports.orders')],
                        ['label' => 'Статистика заявок', 'url' => null],
                    ]"
                />
            </div>
        </div>
        <x-ui.card padding="none" class="min-w-0 max-w-full">
            <form method="GET" action="{{ route('reports.orders') }}" id="filtersForm" class="min-w-0 max-w-full">
                <div class="orders-filters-sticky flex flex-wrap items-end gap-4 border-b border-border px-4 py-2">
                    <x-ui.filter-dates
                        :date-from="$dateFrom"
                        :date-to="$dateTo"
                        :show-closed-dates="false"
                    />
                    <div>
                        <label class="mb-1 block text-xs text-muted-foreground">Формат источника</label>
                        <select name="source_format" class="form-input min-w-[8.5rem] text-sm">
                            <option value="">Все</option>
                            <option value="{{ \App\Models\Source::FORMAT_ONLINE }}" {{ ($selectedSourceFormat ?? '') === \App\Models\Source::FORMAT_ONLINE ? 'selected' : '' }}>Онлайн</option>
                            <option value="{{ \App\Models\Source::FORMAT_OFFLINE }}" {{ ($selectedSourceFormat ?? '') === \App\Models\Source::FORMAT_OFFLINE ? 'selected' : '' }}>Офлайн</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-muted-foreground">Источник</label>
                        <select name="source_id" class="form-input min-w-[12rem] max-w-[20rem] text-sm">
                            <option value="">Все</option>
                            @foreach ($sources as $source)
                                <option value="{{ $source->source_id }}" {{ (string) ($selectedSourceId ?? '') === (string) $source->source_id ? 'selected' : '' }}>{{ $source->display_label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <x-reports.export-button route="reports.orders" :formats="['csv']" />
                        <x-ui.button
                            href="{{ route('reports.orders') }}"
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
                                <th>Принято</th>
                                <th>Закрыто</th>
                                <th>Отмен КЦ</th>
                                <th>Отмен Филиала</th>
                                <th>Отмен Всего</th>
                                <th>Гарантий</th>
                                <th>% Отмен</th>
                                <th>Ожидает</th>
                                <th>В работе</th>
                                <th>В работе (СД)</th>
                                <th>Ожид. запчасти</th>
                                <th>Ожид. оплаты</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totals = ['accepted'=>0, 'completed'=>0, 'cancelled_cc'=>0, 'cancelled_city'=>0, 'cancelled_total'=>0, 'warranty'=>0, 'pending'=>0, 'in_progress'=>0, 'in_progress_sd'=>0, 'waiting_parts'=>0, 'waiting_payment'=>0]; @endphp
                            @foreach($report as $row)
                                @php foreach($totals as $k => &$v) $v += $row[$k]; @endphp
                                <tr>
                                    <td class="font-semibold">{{ $row['city_name'] }}</td>
                                    <td>{{ $row['accepted'] }}</td>
                                    <td>{{ $row['completed'] }}</td>
                                    <td>{{ $row['cancelled_cc'] }}</td>
                                    <td>{{ $row['cancelled_city'] }}</td>
                                    <td>{{ $row['cancelled_total'] }}</td>
                                    <td>{{ $row['warranty'] }}</td>
                                    <td>{{ $row['cancel_pct'] }}%</td>
                                    <td>{{ $row['pending'] }}</td>
                                    <td>{{ $row['in_progress'] }}</td>
                                    <td>{{ $row['in_progress_sd'] }}</td>
                                    <td>{{ $row['waiting_parts'] }}</td>
                                    <td>{{ $row['waiting_payment'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-border bg-muted font-bold">
                                <td>ИТОГО</td>
                                <td>{{ $totals['accepted'] }}</td>
                                <td>{{ $totals['completed'] }}</td>
                                <td>{{ $totals['cancelled_cc'] }}</td>
                                <td>{{ $totals['cancelled_city'] }}</td>
                                <td>{{ $totals['cancelled_total'] }}</td>
                                <td>{{ $totals['warranty'] }}</td>
                                <td>{{ $totals['accepted'] > 0 ? round($totals['cancelled_total'] / $totals['accepted'] * 100, 1) : 0 }}%</td>
                                <td>{{ $totals['pending'] }}</td>
                                <td>{{ $totals['in_progress'] }}</td>
                                <td>{{ $totals['in_progress_sd'] }}</td>
                                <td>{{ $totals['waiting_parts'] }}</td>
                                <td>{{ $totals['waiting_payment'] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
