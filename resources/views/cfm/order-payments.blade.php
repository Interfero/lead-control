@extends('layouts.app')

@section('title', 'Оплата заявок')

@section('content')
    <div class="mx-auto max-w-5xl">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'Касса', 'url' => route('cfm.index')],
                    ['label' => 'Оплата заявок', 'url' => null],
                ]"
            />
        </div>

        <x-ui.card class="mb-4">
            <form method="GET" action="{{ route('cfm.order-payments') }}" class="flex flex-wrap items-end gap-3">
                <div class="form-group mb-0">
                    <label class="form-label">Город</label>
                    <select name="city_id" class="form-input">
                        <option value="">Все доступные города</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->city_id }}" {{ (string) ($cityId ?? '') === (string) $city->city_id ? 'selected' : '' }}>
                                {{ $city->city_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">С</label>
                    <input type="date" name="date_from" class="form-input" value="{{ $dateFrom }}">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">По</label>
                    <input type="date" name="date_to" class="form-input" value="{{ $dateTo }}">
                </div>
                <x-ui.button type="submit" class="gap-2">
                    {!! icon('search') !!}
                    Показать
                </x-ui.button>
            </form>
            <p class="mt-3 text-xs text-muted-foreground">
                Приход — сумма «Оплачено» по проведённым заказам; расход ЗП мастера — по проценту мастера;
                расходы города — офис, объявления, промоутеры и др. статьи из кассы.
            </p>
        </x-ui.card>

        @forelse ($reports as $item)
            @php
                $city = $item['city'];
                $r = $item['report'];
            @endphp
            <x-ui.card padding="none" class="mb-4 overflow-hidden">
                <div class="border-b border-border px-4 py-3">
                    <h3 class="m-0 text-base font-semibold">{{ $city->city_name }}</h3>
                    <p class="m-0 mt-1 text-xs text-muted-foreground">Проведённых заказов: {{ $r['orders_count'] }}</p>
                </div>
                <table class="table w-full text-sm">
                    <tbody>
                        <tr>
                            <td>Приход с заявок (оплачено клиентом)</td>
                            <td class="text-right font-medium text-[color:var(--success)]">+{{ number_format($r['gross_income'], 0, ',', ' ') }} ₽</td>
                        </tr>
                        <tr>
                            <td class="pl-8 text-muted-foreground">− Расход ЗП мастера</td>
                            <td class="text-right text-destructive">−{{ number_format($r['master_salary'], 0, ',', ' ') }} ₽</td>
                        </tr>
                        <tr>
                            <td class="pl-8 text-muted-foreground">− Оплата партов / расход партнерам</td>
                            <td class="text-right text-destructive">−{{ number_format($r['party_payments'], 0, ',', ' ') }} ₽</td>
                        </tr>
                        <tr>
                            <td class="pl-8 text-muted-foreground">− Расходы города</td>
                            <td class="text-right text-destructive">−{{ number_format($r['city_expenses'], 0, ',', ' ') }} ₽</td>
                        </tr>
                        @foreach ($r['city_expense_details'] as $catName => $amount)
                            <tr>
                                <td class="pl-12 text-xs text-muted-foreground">{{ $catName }}</td>
                                <td class="text-right text-xs text-destructive">−{{ number_format($amount, 0, ',', ' ') }} ₽</td>
                            </tr>
                        @endforeach
                        <tr class="bg-muted/60">
                            <td class="font-semibold">Чистый остаток</td>
                            <td class="text-right text-base font-semibold {{ $r['net_remainder'] >= 0 ? 'text-[color:var(--success)]' : 'text-destructive' }}">
                                {{ $r['net_remainder'] >= 0 ? '+' : '' }}{{ number_format($r['net_remainder'], 0, ',', ' ') }} ₽
                            </td>
                        </tr>
                    </tbody>
                </table>
            </x-ui.card>
        @empty
            <x-ui.card>
                <p class="m-0 text-muted-foreground">Нет данных за выбранный период.</p>
            </x-ui.card>
        @endforelse
    </div>
@endsection
