@php
    $statusLabels = $statusLabels ?? \App\Models\Order::getStatusLabels();
@endphp

<x-ui.card padding="none" class="mb-4">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
        <div>
            <h2 class="m-0 text-base font-semibold">Активные заявки</h2>
            <p class="mb-0 mt-1 text-xs text-muted-foreground">
                Прозвон → В пути → В работе → В работе (СД) → … → Не оформлена
            </p>
        </div>
        <x-ui.button href="{{ route('orders.index') }}" variant="secondary" size="sm">
            Все заявки
        </x-ui.button>
    </div>

    <div class="table-responsive">
        <table class="table orders-sticky-table text-sm">
            <thead>
                <tr>
                    <th style="width: 72px;">ID</th>
                    <th>Статус</th>
                    <th>Дата</th>
                    <th>Город</th>
                    <th>Мастер</th>
                    <th>РК</th>
                    <th>Клиент</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($activeOrders as $order)
                    @php $urgencyClass = $order->eventUrgencyClass(); @endphp
                    <tr
                        class="cursor-pointer hover:bg-muted/60 {{ $urgencyClass }}"
                        onclick="window.location='{{ route('orders.show', $order->order_id) }}'"
                    >
                        <td>{{ $order->order_id }}</td>
                        <td class="status-cell status-{{ $order->order_status }} {{ $urgencyClass }}">
                            {{ $statusLabels[$order->order_status] ?? $order->order_status }}
                        </td>
                        <td class="orders-datetime-cell {{ $urgencyClass }}">{{ $order->datetime_order?->format('d.m.y H:i') ?? '—' }}</td>
                        <td>{{ $order->address?->city?->city_name ?? '—' }}</td>
                        <td>{{ $order->master?->user_name ?? '—' }}</td>
                        <td>{{ $order->source?->display_label ?? '—' }}</td>
                        <td>{{ $order->persons->first()?->person_name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-muted-foreground">
                            Нет активных заявок
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-ui.card>
