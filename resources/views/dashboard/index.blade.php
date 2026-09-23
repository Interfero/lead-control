@extends('layouts.app')

@section('title', 'Главная')

@section('content')
<div class="page-header">
    <h1 class="page-title">{!! icon('dashboard') !!} Главная</h1>
</div>

{{-- Карточки статистики --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">{!! icon('orders') !!}</div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['today']['new_orders'] }}</div>
            <div class="stat-label">Новых заказов сегодня</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">{!! icon('success') !!}</div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['today']['completed_orders'] }}</div>
            <div class="stat-label">Завершено сегодня</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">{!! icon('money') !!}</div>
        <div class="stat-content">
            <div class="stat-value">{{ number_format($stats['today']['revenue'], 0, '', ' ') }} ₽</div>
            <div class="stat-label">Выручка сегодня</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">{!! icon('report') !!}</div>
        <div class="stat-content">
            <div class="stat-value">{{ $stats['week']['conversion'] }}%</div>
            <div class="stat-label">Конверсия за неделю</div>
        </div>
    </div>
</div>

{{-- Заказы, требующие внимания --}}
@if($stats['pending_orders']->count() > 0)
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h3>{!! icon('warning') !!} Требуют внимания ({{ $stats['pending_orders']->count() }})</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Город</th>
                    <th>Источник</th>
                    <th>Клиент</th>
                    <th>Создан</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stats['pending_orders'] as $order)
                <tr onclick="window.location='{{ route('orders.show', $order->order_id) }}'" style="cursor: pointer;">
                    <td>{{ $order->order_id }}</td>
                    <td>{{ $order->address->city->city_name ?? '-' }}</td>
                    <td>{{ $order->source?->source_name ?? '—' }}</td>
                    <td>{{ $order->persons->first()?->person_name ?? '-' }}</td>
                    <td>{{ $order->order_created_at->diffForHumans() }}</td>
                    <td>
                        <span class="status-badge status-{{ $order->order_status }}">
                            {{ $order->status_label }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Последние заказы --}}
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header-flex">
        <h3>{!! icon('orders') !!} Последние заказы</h3>
        <a href="{{ route('orders.index') }}" class="btn btn-sm">Все заказы {!! icon('forward') !!}</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Город</th>
                    <th>Источник</th>
                    <th>Клиент</th>
                    <th>Мастер</th>
                    <th>Статус</th>
                    <th>Сумма</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stats['recent_orders'] as $order)
                <tr onclick="window.location='{{ route('orders.show', $order->order_id) }}'" style="cursor: pointer;">
                    <td>{{ $order->order_id }}</td>
                    <td>{{ $order->address->city->city_name ?? '-' }}</td>
                    <td>{{ $order->source?->source_name ?? '—' }}</td>
                    <td>{{ $order->persons->first()?->person_name ?? '-' }}</td>
                    <td>{{ $order->master?->user_name ?? '-' }}</td>
                    <td>
                        <span class="status-badge status-{{ $order->order_status }}">
                            {{ $order->status_label }}
                        </span>
                    </td>
                    <td>
                        @if($order->order_status === 'completed')
                            {{ number_format($order->amount_paid - $order->amount_comp, 0, '', ' ') }} ₽
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #6b7280;">Заказов пока нет</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Статистика за период --}}
<div class="stats-period-grid">
    <div class="card">
        <h3 style="margin-bottom: 1rem;">{!! icon('calendar') !!} За неделю</h3>
        <div class="stat-row">
            <span>Всего заказов:</span>
            <strong>{{ $stats['week']['total_orders'] }}</strong>
        </div>
        <div class="stat-row">
            <span>Завершено:</span>
            <strong>{{ $stats['week']['completed_orders'] }}</strong>
        </div>
        <div class="stat-row">
            <span>Выручка:</span>
            <strong>{{ number_format($stats['week']['revenue'], 0, '', ' ') }} ₽</strong>
        </div>
    </div>
    
    <div class="card">
        <h3 style="margin-bottom: 1rem;">{!! icon('calendar') !!} За месяц</h3>
        <div class="stat-row">
            <span>Всего заказов:</span>
            <strong>{{ $stats['month']['total_orders'] }}</strong>
        </div>
        <div class="stat-row">
            <span>Завершено:</span>
            <strong>{{ $stats['month']['completed_orders'] }}</strong>
        </div>
        <div class="stat-row">
            <span>Выручка:</span>
            <strong>{{ number_format($stats['month']['revenue'], 0, '', ' ') }} ₽</strong>
        </div>
    </div>
</div>
@endsection

