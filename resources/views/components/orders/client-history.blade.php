@props([
    'orders',
    'statusLabels',
    'highlightOrderId' => null,
    'useModal' => false,
    'title' => 'История заказов клиента',
])

@inject('history', 'App\Services\OrderClientHistoryService')

@php
    $total = $orders->count();
@endphp

<div class="card order-client-history-card">
    <div class="card-header order-client-history-header">
        <h3>{!! icon('document') !!} {{ $title }}</h3>
    </div>
    <div class="order-client-history-body">
        @if($total > 0)
            <p class="order-client-history-summary">Показаны записи <strong>1–{{ $total }}</strong> из <strong>{{ $total }}</strong>.</p>
        @endif

        {{-- Десктоп: таблица --}}
        <div class="table-responsive order-client-history-desktop">
            <table class="table order-client-history-table">
                <thead>
                    <tr>
                        <th class="order-client-history-col-id">ID</th>
                        <th class="order-client-history-col-type">Тип</th>
                        <th class="order-client-history-col-callback">Прозвон</th>
                        <th class="order-client-history-col-date">Дата принятия заявки</th>
                        <th class="order-client-history-col-date">Дата исполнения</th>
                        <th>Статус</th>
                        <th>Мастер</th>
                        <th>Сумма заявки, руб.</th>
                        <th>Кем создана</th>
                        <th>РК</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $hist)
                        @php
                            $isCurrent = $highlightOrderId && (int) $hist->order_id === (int) $highlightOrderId;
                        @endphp
                        <tr class="{{ $isCurrent ? 'order-client-history-row--current' : '' }}">
                            <td class="order-client-history-col-id">
                                @if($useModal)
                                    <a href="#" class="order-client-history-link" onclick="event.preventDefault(); openOrderModal({{ $hist->order_id }})">{{ $hist->order_id }}</a>
                                @else
                                    <a href="{{ route('orders.show', $hist->order_id) }}" class="order-client-history-link" target="_blank" rel="noopener">
                                        {{ $hist->order_id }} <sup class="order-client-history-ext">↗</sup>
                                    </a>
                                @endif
                            </td>
                            <td class="order-client-history-col-type">
                                @switch($hist->order_type)
                                    @case('new') Впервые @break
                                    @case('repeat') Повтор @break
                                    @case('warranty') Гарантия @break
                                    @default {{ $hist->order_type }}
                                @endswitch
                            </td>
                            <td class="order-client-history-col-callback">{{ $history->callbackCell($hist) }}</td>
                            <td class="order-client-history-col-date">{{ $history->formatAcceptedAt($hist) }}</td>
                            <td class="order-client-history-col-date">{{ $history->formatClosedAt($hist) }}</td>
                            <td>{{ $statusLabels[$hist->order_status] ?? $hist->order_status }}</td>
                            <td>{{ $hist->master->user_name ?? '' }}</td>
                            <td>{{ $history->formatAmount($hist) }}</td>
                            <td>{{ $history->creatorShortLabel($hist->creator) }}</td>
                            <td class="order-client-history-col-rk">{{ $history->complaintMark($hist) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="order-client-history-empty">Заказов пока нет</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Мобилка: карточки (без горизонтального скролла страницы) --}}
        <div class="order-client-history-mobile" aria-label="{{ $title }}">
            @forelse($orders as $hist)
                @php
                    $isCurrent = $highlightOrderId && (int) $hist->order_id === (int) $highlightOrderId;
                    $typeLabel = match ($hist->order_type) {
                        'new' => 'Впервые',
                        'repeat' => 'Повтор',
                        'warranty' => 'Гарантия',
                        default => (string) $hist->order_type,
                    };
                    $statusLabel = $statusLabels[$hist->order_status] ?? $hist->order_status;
                    $rk = $history->complaintMark($hist);
                    $callback = $history->callbackCell($hist);
                    $acceptedAt = $history->formatAcceptedAt($hist);
                    $closedAt = $history->formatClosedAt($hist);
                @endphp
                @if($useModal)
                    <button
                        type="button"
                        class="order-client-history-item{{ $isCurrent ? ' order-client-history-item--current' : '' }}"
                        onclick="openOrderModal({{ $hist->order_id }})"
                    >
                @else
                    <a
                        href="{{ route('orders.show', $hist->order_id) }}"
                        class="order-client-history-item{{ $isCurrent ? ' order-client-history-item--current' : '' }}"
                        target="_blank"
                        rel="noopener"
                    >
                @endif
                    <div class="order-client-history-item__top">
                        <span class="order-client-history-item__id">#{{ $hist->order_id }}</span>
                        <span class="order-client-history-item__status">{{ $statusLabel }}</span>
                        @if($rk)
                            <span class="order-client-history-item__rk" title="Рекламация">{{ $rk }}</span>
                        @endif
                    </div>
                    <div class="order-client-history-item__amount">{{ $history->formatAmount($hist) }}</div>
                    <div class="order-client-history-item__meta">
                        <span>{{ $typeLabel }}</span>
                        @if($callback)
                            <span>· {{ $callback }}</span>
                        @endif
                    </div>
                    @if($hist->master?->user_name)
                        <div class="order-client-history-item__meta">{{ $hist->master->user_name }}</div>
                    @endif
                    <div class="order-client-history-item__meta">
                        {{ $acceptedAt }}
                        @if($closedAt && $closedAt !== '—' && $closedAt !== '-')
                            → {{ $closedAt }}
                        @endif
                    </div>
                    <div class="order-client-history-item__meta">{{ $history->creatorShortLabel($hist->creator) }}</div>
                @if($useModal)
                    </button>
                @else
                    </a>
                @endif
            @empty
                <div class="order-client-history-empty">Заказов пока нет</div>
            @endforelse
        </div>
    </div>
</div>
