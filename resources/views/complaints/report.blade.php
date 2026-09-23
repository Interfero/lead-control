@extends('layouts.app')

@section('title', 'Отчёт по претензиям')

@section('content')
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'Претензии', 'url' => route('complaints.index')],
                    ['label' => 'Отчёт', 'url' => null],
                ]"
            />
        </div>
    </div>

    <x-ui.card padding="none" class="mb-4 min-w-0 max-w-full">
        <form method="GET" action="{{ route('complaints.report') }}" id="filtersForm" class="min-w-0 max-w-full">
            <div class="orders-filters-sticky flex flex-wrap items-end gap-4 border-b border-border px-4 py-2">
                <x-ui.filter-dates
                    :date-from="$dateFrom"
                    :date-to="$dateTo"
                    :show-closed-dates="false"
                />
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <x-ui.button
                        href="{{ route('complaints.report') }}"
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
                        id="filtersFormSubmit"
                        class="shrink-0"
                        title="Применить фильтр"
                    >
                        {!! icon('search') !!}
                    </x-ui.button>
                </div>
            </div>

            @php
                $complaintTypes = \App\Models\Complaint::TYPES;
                $typeCount = count($complaintTypes);
                $emptyColspan = 6 + $typeCount;
                $cityColPct = 11;
                $metricColPct = 5;
                $typeColPct = round((100 - $cityColPct - 5 * $metricColPct) / max($typeCount, 1), 3);
            @endphp

            <div class="min-w-0 max-w-full overflow-x-auto">
                <table class="table table-sticky orders-sticky-table complaints-report-table w-full min-w-0 text-xs" lang="ru">
                    <colgroup>
                        <col style="width: {{ $cityColPct }}%" />
                        @foreach (range(1, 5) as $_)
                            <col style="width: {{ $metricColPct }}%" />
                        @endforeach
                        @foreach (range(1, $typeCount) as $_)
                            <col style="width: {{ $typeColPct }}%" />
                        @endforeach
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col" class="complaints-report-city">Город</th>
                            <th scope="col" class="complaints-report-metric">Всего</th>
                            <th scope="col" class="complaints-report-metric">Новых</th>
                            <th scope="col" class="complaints-report-metric">В работе</th>
                            <th scope="col" class="complaints-report-metric">Решено</th>
                            <th scope="col" class="complaints-report-metric">Отклонено</th>
                            @foreach ($complaintTypes as $code => $label)
                                <th scope="col" class="complaints-report-type">
                                    {!! str_replace('/', '/<wbr>', e($label)) !!}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($report as $row)
                        <tr>
                            <td class="complaints-report-city font-semibold text-foreground" title="{{ $row['city_name'] }}">
                                {{ $row['city_name'] }}
                            </td>
                            <td class="complaints-report-metric tabular-nums">{{ $row['total'] }}</td>
                            <td class="complaints-report-metric tabular-nums">{{ $row['new'] }}</td>
                            <td class="complaints-report-metric tabular-nums">{{ $row['in_progress'] }}</td>
                            <td class="complaints-report-metric tabular-nums">{{ $row['resolved'] }}</td>
                            <td class="complaints-report-metric tabular-nums">{{ $row['rejected'] }}</td>
                            @foreach ($complaintTypes as $code => $label)
                                <td class="complaints-report-type tabular-nums">{{ $row['by_type'][$code] ?? 0 }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $emptyColspan }}" class="px-4 py-8 text-center text-muted-foreground">
                                Нет данных за выбранный период
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </x-ui.card>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('filtersForm');
            if (!form) return;

            form.querySelectorAll('input').forEach(function (input) {
                if (input.type === 'date') {
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
        });
    </script>
@endpush
