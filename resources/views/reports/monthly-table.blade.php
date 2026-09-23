@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
@php
    $intFmt = fn ($v) => $v === null ? '—' : number_format((int) $v, 0, ',', ' ');
    $decFmt = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ');
@endphp
<div class="mx-auto max-w-[1800px] min-w-0">
    <div class="orders-index-toolbar mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Отчёты', 'url' => route('reports.closed-orders')],
                ['label' => 'Помесячная таблица', 'url' => null],
            ]"
        />
    </div>

    <x-ui.card padding="none" class="mb-4">
        <form method="GET" action="{{ route('reports.monthly-table') }}" class="min-w-0">
            <div class="orders-filters-sticky flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Город</label>
                    <select name="city_id" class="form-input text-sm" onchange="this.form.submit()">
                        @foreach ($cities as $c)
                            <option value="{{ $c->city_id }}" @selected($cityId == $c->city_id)>{{ $c->city_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Месяц с</label>
                    <input type="month" name="month_from" class="form-input text-sm" value="{{ $monthFrom }}" onchange="this.form.submit()">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-xs">Месяц по</label>
                    <input type="month" name="month_to" class="form-input text-sm" value="{{ $monthTo }}" onchange="this.form.submit()">
                </div>
                <div class="ml-auto flex gap-2 items-end">
                    <x-ui.button href="{{ route('reports.monthly-table', array_filter(['city_id' => $cityId])) }}" variant="primary" size="icon" title="Сбросить">{!! icon('refresh') !!}</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="icon" title="Поиск">{!! icon('search') !!}</x-ui.button>
                </div>
            </div>
            <p class="px-4 py-2 text-sm text-muted-foreground">
                {{ $pageTitle }}
                · {{ $fromMonth->format('m.Y') }} — {{ $toMonth->format('m.Y') }}
            </p>
        </form>

        <div class="overflow-x-auto">
            <table class="table w-full text-sm monthly-table-report">
                <thead>
                    <tr>
                        <th>Месяц</th>
                        <th>Сумма филиала</th>
                        <th>Закрыто наши</th>
                        <th>Листовки</th>
                        <th>Расклейка</th>
                        <th>ЧС</th>
                        <th>Промоутеры</th>
                        <th>Объявления</th>
                        <th>Оплата промов</th>
                        <th>Цена заявки</th>
                        <th>Цена ящика</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="whitespace-nowrap font-medium">{{ $row['month_label'] }}</td>
                            <td>{{ $intFmt($row['turnover']) }}</td>
                            <td>{{ $intFmt($row['closed_our']) }}</td>
                            <td>{{ $intFmt($row['leaflets']) }}</td>
                            <td>{{ $intFmt($row['posting']) }}</td>
                            <td>{{ $intFmt($row['chs']) }}</td>
                            <td>{{ $intFmt($row['promo_ut']) }}</td>
                            <td>{{ $intFmt($row['ads']) }}</td>
                            <td>{{ $intFmt($row['promo_pay']) }}</td>
                            <td>{{ $decFmt($row['lead_price']) }}</td>
                            <td>{{ $decFmt($row['box_price']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted-foreground py-8">Выберите город</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="px-4 py-3 text-xs text-muted-foreground border-t border-border">
            Цена заявки = (оплата промов + объявления) / закрыто наши
            · Цена ящика = оплата промов / листовки
            · ЧС — листовки по маршрутам частного сектора
            · Расклейка — если есть статья в кассе
        </p>
    </x-ui.card>
</div>

@push('styles')
<style>
    .monthly-table-report th,
    .monthly-table-report td {
        white-space: nowrap;
    }
</style>
@endpush
@endsection
