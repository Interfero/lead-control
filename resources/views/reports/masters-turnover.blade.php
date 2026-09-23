@extends('layouts.app')

@section('title', 'Оборот мастеров')

@section('content')
@php
    $money = fn ($v) => number_format((int) $v, 0, ',', ' ') . ' р.';
    $sortLink = function (string $field) use ($sort, $dir) {
        $nextDir = ($sort === $field && $dir === 'desc') ? 'asc' : 'desc';

        return '?'.http_build_query(array_merge(request()->except('export'), ['sort' => $field, 'dir' => $nextDir]));
    };
    $sortArrow = fn (string $field) => $sort === $field ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    $total = $totalCount ?? $rows->count();
@endphp
<div class="mx-auto max-w-[1200px] min-w-0">
    <h1 class="mb-4 text-xl font-semibold">Оборот мастеров</h1>

    <x-ui.card padding="none" class="mb-4">
        <form method="GET" action="{{ route('reports.masters-turnover') }}" class="min-w-0">
            <div class="orders-filters-sticky border-b border-border px-4 py-3">
                <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
                    <div class="flex min-w-0 flex-col gap-2">
                        <x-ui.filter-dates :date-from="$dateFrom" :date-to="$dateTo" :show-closed-dates="false" />
                        <div class="flex flex-wrap gap-2">
                            <x-ui.button type="submit" variant="primary" size="md">Поиск</x-ui.button>
                            <x-ui.button href="{{ route('reports.masters-turnover') }}" variant="outline" size="md">Сброс</x-ui.button>
                            <x-reports.export-button route="reports.masters-turnover" :formats="['csv']" />
                        </div>
                    </div>
                    <div class="form-group mb-0 shrink-0">
                        <label class="form-label text-xs">Город</label>
                        <select name="city_id" class="form-input min-w-[180px] text-sm">
                            <option value="">Выбрать город</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city->city_id }}" @selected(request('city_id') == $city->city_id)>{{ $city->city_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            @if ($total > 0)
                <p class="px-4 py-2 text-sm text-muted-foreground">
                    Показаны записи 1–{{ $total }} из {{ $total }}
                </p>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="table w-full text-sm">
                <thead>
                    <tr>
                        <th class="w-12">№</th>
                        <th>Мастер</th>
                        <th class="text-right"><a href="{{ $sortLink('turnover') }}" class="text-inherit hover:text-primary">Оборот{{ $sortArrow('turnover') }}</a></th>
                        <th class="text-right"><a href="{{ $sortLink('percent_4') }}" class="text-inherit hover:text-primary">4%{{ $sortArrow('percent_4') }}</a></th>
                        <th class="text-right">Общий оборот (временно)</th>
                        <th class="text-right">4% от чеков</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $index => $row)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="font-medium">{{ $row['user_name'] }}</td>
                            <td class="text-right">{{ $money($row['turnover']) }}</td>
                            <td class="text-right">{{ $money($row['percent_4']) }}</td>
                            <td class="text-right">{{ $money($row['total_turnover_temp']) }}</td>
                            <td class="text-right">{{ $money($row['percent_4_checks']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-muted-foreground">
                                Нет данных за выбранный период
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-border px-4 py-3">
            <p class="text-sm text-muted-foreground">
                Столбец «оборот» расчитан по формуле: «всего заплачено клиентом» – «зап.части»
            </p>
        </div>
    </x-ui.card>
</div>
@endsection
