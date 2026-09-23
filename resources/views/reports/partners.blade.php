@extends('layouts.app')

@section('title', 'Отчёт по партнёрам')

@section('content')
    <div class="mx-auto max-w-[1600px] min-w-0">
        <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
                <x-breadcrumbs
                    :items="[
                        ['label' => 'Главная', 'url' => route('orders.index')],
                        ['label' => 'Отчёты', 'url' => route('reports.orders')],
                        ['label' => 'Партнёры', 'url' => null],
                    ]"
                />
            </div>
        </div>
        <x-ui.card padding="none" class="min-w-0 max-w-full">
            <form method="GET" action="{{ route('reports.partners') }}" id="filtersForm" class="min-w-0 max-w-full">
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
                        <label class="mb-1 block text-xs text-muted-foreground">Партнёр</label>
                        <x-ui.select name="source_id" class="min-w-[12rem] max-w-[20rem]">
                            <option value="">Все партнёры</option>
                            @foreach($sources as $source)
                                <option value="{{ $source->source_id }}" {{ $selectedSourceId == $source->source_id ? 'selected' : '' }}>{{ $source->display_label }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <x-reports.export-button route="reports.partners" :formats="['csv']" />
                        <x-ui.button
                            href="{{ route('reports.partners') }}"
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
                                <th>Отмены</th>
                                <th>Закрыто</th>
                                <th>В работе</th>
                                <th>Оплачено</th>
                                <th>Комплектующие</th>
                                <th>Чистыми</th>
                                <th>Ср. чек</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totals = ['accepted'=>0, 'cancelled'=>0, 'completed'=>0, 'in_work'=>0, 'total_paid'=>0, 'total_comp'=>0, 'net'=>0]; @endphp
                            @foreach($report as $row)
                                @php foreach($totals as $k => &$v) $v += $row[$k]; @endphp
                                <tr>
                                    <td class="font-semibold">{{ $row['city_name'] }}</td>
                                    <td>{{ $row['accepted'] }}</td>
                                    <td>{{ $row['cancelled'] }}</td>
                                    <td>{{ $row['completed'] }}</td>
                                    <td>{{ $row['in_work'] }}</td>
                                    <td>{{ number_format($row['total_paid'], 0, ',', ' ') }} р.</td>
                                    <td>{{ number_format($row['total_comp'], 0, ',', ' ') }} р.</td>
                                    <td>{{ number_format($row['net'], 0, ',', ' ') }} р.</td>
                                    <td>{{ number_format($row['avg_check'], 0, ',', ' ') }} р.</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-border bg-muted font-bold">
                                <td>ИТОГО</td>
                                <td>{{ $totals['accepted'] }}</td>
                                <td>{{ $totals['cancelled'] }}</td>
                                <td>{{ $totals['completed'] }}</td>
                                <td>{{ $totals['in_work'] }}</td>
                                <td>{{ number_format($totals['total_paid'], 0, ',', ' ') }} р.</td>
                                <td>{{ number_format($totals['total_comp'], 0, ',', ' ') }} р.</td>
                                <td>{{ number_format($totals['net'], 0, ',', ' ') }} р.</td>
                                <td>{{ $totals['completed'] > 0 ? number_format(round($totals['total_paid'] / $totals['completed']), 0, ',', ' ') : 0 }} р.</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
