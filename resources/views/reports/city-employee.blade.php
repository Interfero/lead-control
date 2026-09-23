@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
@php
    $money = fn ($v) => number_format((int) $v, 0, ',', ' ') . ' ₽';
@endphp
<div class="mx-auto max-w-[1800px] min-w-0">
    <div class="orders-index-toolbar mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Отчёты', 'url' => route('reports.city-employee')],
                ['label' => $pageTitle, 'url' => null],
            ]"
        />
        <a href="{{ route('reports.closed-orders', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="text-sm text-primary hover:underline">Отчёт по закрытым заявкам</a>
    </div>

    <div class="mb-3 flex flex-wrap gap-2">
        <x-ui.button
            href="{{ route('reports.city-employee', request()->except('city_id')) }}"
            :variant="!$focusCityId ? 'primary' : 'outline'"
            size="sm"
        >Все города</x-ui.button>
        @foreach ($cities as $city)
            <x-ui.button
                href="{{ route('reports.city-employee', array_merge(request()->except('city_id'), ['city_id' => $city->city_id])) }}"
                :variant="$focusCityId == $city->city_id ? 'primary' : 'outline'"
                size="sm"
            >{{ $city->city_name }}</x-ui.button>
        @endforeach
    </div>

    <x-ui.card padding="none" class="mb-4">
        <form method="GET" action="{{ route('reports.city-employee') }}" id="cityEmployeeFilters">
            @if ($focusCityId)
                <input type="hidden" name="city_id" value="{{ $focusCityId }}">
            @endif
            <div class="orders-filters-sticky flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
                <x-ui.filter-dates :date-from="$dateFrom" :date-to="$dateTo" :show-closed-dates="false" />
                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="button" variant="outline" size="sm" onclick="setPeriod('today')">Сегодня</x-ui.button>
                    <x-ui.button type="button" variant="outline" size="sm" onclick="setPeriod('month')">Месяц</x-ui.button>
                </div>
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input type="checkbox" name="show_fired" value="1" {{ $showFired ? 'checked' : '' }} onchange="this.form.submit()">
                    Показывать уволенных
                </label>
                <div class="ml-auto flex gap-2">
                    <x-reports.export-button route="reports.city-employee" :formats="['csv']" />
                    <x-ui.button href="{{ route('reports.city-employee', $focusCityId ? ['city_id' => $focusCityId] : []) }}" variant="primary" size="icon" title="Сбросить">{!! icon('refresh') !!}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="icon" title="Применить">{!! icon('search') !!}</x-ui.button>
                </div>
            </div>
        </form>

        @include('reports.partials.city-stats-table', [
            'title' => $todayLabel,
            'rows' => $todayRows,
            'totals' => $todayTotals,
        ])

        @include('reports.partials.city-stats-table', [
            'title' => $periodLabel,
            'rows' => $periodRows,
            'totals' => $periodTotals,
        ])
    </x-ui.card>

    <x-ui.card padding="none">
        <div class="border-b border-border px-4 py-2">
            <h2 class="text-base font-semibold">Мастера</h2>
            <p class="text-sm text-muted-foreground">Всего {{ $masters->count() }} записей · период: {{ $periodLabel }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="table w-full text-sm">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>ФИО</th>
                        <th>Кол-во заявок</th>
                        <th>СД</th>
                        <th>Чистый ср.чек</th>
                        <th>Чистыми всего</th>
                        <th>Общий ср.чек</th>
                        <th>Зап/части</th>
                        <th>Микра</th>
                        <th>Микра %</th>
                        <th>Зарплата</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($masters as $i => $m)
                        <tr @class(['opacity-60' => $m['is_fired']])>
                            <td>{{ $i + 1 }}</td>
                            <td class="font-medium">{{ $m['user_name'] }}{{ $m['is_fired'] ? ' (увол.)' : '' }}</td>
                            <td>{{ $m['orders_count'] }}</td>
                            <td>{{ $m['sd_count'] }}</td>
                            <td>{{ $money($m['net_avg_check']) }}</td>
                            <td>{{ $money($m['net_sum']) }}</td>
                            <td>{{ $money($m['avg_check']) }}</td>
                            <td>{{ $money($m['parts']) }}</td>
                            <td>{{ $m['micra'] }}</td>
                            <td>{{ $m['micra_pct'] }}%</td>
                            <td>{{ $money($m['salary']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-8 text-center text-muted-foreground">Нет данных за выбранный период</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($masters->isNotEmpty())
                <tfoot>
                    <tr class="bg-muted font-bold">
                        <td colspan="2">Итого</td>
                        <td>{{ $masters->sum('orders_count') }}</td>
                        <td>{{ $masters->sum('sd_count') }}</td>
                        <td>—</td>
                        <td>{{ $money($masters->sum('net_sum')) }}</td>
                        <td>—</td>
                        <td>{{ $money($masters->sum('parts')) }}</td>
                        <td>{{ $masters->sum('micra') }}</td>
                        <td>—</td>
                        <td>{{ $money($masters->sum('salary')) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </x-ui.card>
</div>
@endsection

@push('scripts')
<script>
function setPeriod(p) {
    const form = document.getElementById('cityEmployeeFilters');
    let input = form.querySelector('input[name=period]');
    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'period';
        form.appendChild(input);
    }
    input.value = p;
    form.submit();
}
</script>
@endpush
