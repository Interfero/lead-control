@extends('layouts.app')

@section('title', $master->user_name)

@section('navbar_context')
    {{ $master->user_name }}
@endsection

@section('content')
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Касса', 'url' => route('cfm.index')],
                ['label' => 'Рассчитать мастера', 'url' => route('cfm.master-settlement.index')],
                ['label' => $master->user_name, 'url' => null],
            ]"
        />
        <div class="text-sm text-muted-foreground">
            Общий депозит: <strong class="text-foreground">0 р.</strong>
        </div>
    </div>

    <x-ui.card padding="none" class="mb-4 min-w-0 max-w-full">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
            <h2 class="m-0 text-base font-semibold">{{ $master->user_name }}</h2>
            <div class="flex items-center gap-3">
                <button type="button" class="btn btn-secondary btn-sm" id="selectAllOrders">Выбрать все</button>
                <span class="text-sm text-muted-foreground">Всего {{ $orders->count() }} {{ trans_choice('запись|записи|записей', $orders->count()) }}.</span>
            </div>
        </div>

        <form method="POST" action="{{ route('cfm.master-settlement.process', $master->user_id) }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}" id="ordersSettlementForm">
            @csrf

            <div class="overflow-x-auto">
                <table class="table w-full min-w-[56rem] text-sm">
                    <thead>
                        <tr>
                            <th style="width: 2.5rem;"></th>
                            <th>ID заявки</th>
                            <th class="sortable">
                                <a
                                    href="?{{ http_build_query(array_merge(request()->all(), ['sort' => 'order_closed_at', 'dir' => request('sort') === 'order_closed_at' && request('dir') === 'asc' ? 'desc' : 'asc'])) }}"
                                    class="text-inherit no-underline hover:text-primary"
                                >
                                    Дата закрытия
                                    @if (($sort ?? 'order_closed_at') === 'order_closed_at')
                                        {{ ($sortDir ?? 'desc') === 'asc' ? '▲' : '▼' }}
                                    @endif
                                </a>
                            </th>
                            <th>Адрес</th>
                            <th>Имя клиента</th>
                            <th class="text-right">Сумма заявки</th>
                            <th>Группа расчета</th>
                            <th class="text-right">К сдаче</th>
                            <th class="text-right">Депозит</th>
                            <th class="text-right">Всего</th>
                            <th class="text-center">Сдано</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $row)
                            <tr>
                                <td></td>
                                <td>
                                    <a href="{{ route('orders.show', $row['order_id']) }}" class="text-primary hover:underline" target="_blank">
                                        {{ $row['order_id'] }}
                                    </a>
                                </td>
                                <td>{{ $row['closed_at']?->format('d.m.y') ?? '—' }}</td>
                                <td>{{ $row['address'] }}</td>
                                <td>{{ $row['client_name'] }}</td>
                                <td class="text-right">{{ number_format($row['amount_paid'], 0, ',', ' ') }} р.</td>
                                <td>«{{ $row['calculation_group'] }}»</td>
                                <td class="text-right font-medium text-red-600">{{ number_format($row['amount_to_pay'], 0, ',', ' ') }} р.</td>
                                <td class="text-right">0 р.</td>
                                <td class="text-right"></td>
                                <td class="text-center">
                                    <input
                                        type="checkbox"
                                        name="order_ids[]"
                                        value="{{ $row['order_id'] }}"
                                        class="order-row-checkbox h-4 w-4 rounded border-border"
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="py-8 text-center text-muted-foreground">
                                    У мастера нет несданных заказов
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($orders->isNotEmpty())
                        <tfoot>
                            <tr class="bg-muted font-semibold">
                                <td colspan="7" class="text-right">Итого:</td>
                                <td class="text-right text-red-600">{{ number_format($totalToPay, 0, ',', ' ') }} р.</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if ($orders->isNotEmpty())
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button type="submit" class="bg-emerald-600 hover:bg-emerald-700">
                            Обработать выбранных
                        </x-ui.button>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" id="resetSelection">
                        Сбросить выбор
                    </button>
                </div>
            @endif
        </form>
    </x-ui.card>
@endsection

@push('scripts')
<script>
document.getElementById('selectAllOrders')?.addEventListener('click', function () {
    document.querySelectorAll('.order-row-checkbox').forEach(cb => { cb.checked = true; });
});

document.getElementById('resetSelection')?.addEventListener('click', function () {
    document.querySelectorAll('.order-row-checkbox').forEach(cb => { cb.checked = false; });
});

document.getElementById('ordersSettlementForm')?.addEventListener('submit', function (e) {
    const checked = document.querySelectorAll('.order-row-checkbox:checked');
    if (checked.length === 0) {
        e.preventDefault();
        alert('Выберите хотя бы один заказ');
        return;
    }
    if (!confirm('Отметить выбранные заказы как сданные?')) {
        e.preventDefault();
    }
});
</script>
@endpush
