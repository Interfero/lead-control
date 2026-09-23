@extends('layouts.app')

@section('title', 'Дашборд диспетчеров')

@section('content')
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('dispatcher.dashboard')],
                ['label' => 'Дашборд диспетчеров', 'url' => null],
            ]"
        />
    </div>

    @if ($showCallCenterActiveBoard ?? false)
        @include('dispatcher.partials.active-orders-board', [
            'activeOrders' => $activeOrders,
            'statusLabels' => $statusLabels,
        ])
    @endif

    <p class="mb-2 text-sm font-medium text-foreground">Закрытые за 7 дней</p>
    <p class="mb-4 text-sm text-muted-foreground">
        Скользящее окно: с {{ $stats['window_from'] }} по {{ $stats['window_to'] }}.
        Суммы по закрытым заказам старше 7 суток здесь не показываются.
    </p>

    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-icon">{!! icon('orders') !!}</div>
            <div class="stat-content">
                <div class="stat-value">{{ $stats['accepted_count'] }}</div>
                <div class="stat-label">Принято заявок за 7 дней</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">{!! icon('success') !!}</div>
            <div class="stat-content">
                <div class="stat-value">{{ $stats['closed_count'] }}</div>
                <div class="stat-label">Закрыто за 7 дней</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">{!! icon('money') !!}</div>
            <div class="stat-content">
                <div class="stat-value">{{ number_format($stats['closed_net_sum'], 0, ',', ' ') }} ₽</div>
                <div class="stat-label">Сумма по закрытым (нетто)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">{!! icon('report') !!}</div>
            <div class="stat-content">
                <div class="stat-value">{{ number_format($stats['closed_avg_net'], 0, ',', ' ') }} ₽</div>
                <div class="stat-label">Средний чек по закрытым</div>
            </div>
        </div>
    </div>

    <x-ui.card padding="none" class="mb-4">
        <form method="GET" action="{{ route('dispatcher.dashboard') }}" class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
            <div class="form-group mb-0">
                <label class="form-label text-xs">Город</label>
                <select name="city_id" class="form-input text-sm">
                    <option value="">Все</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city->city_id }}" @selected($filterCityId == $city->city_id)>{{ $city->city_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label text-xs">Мастер</label>
                <select name="master_id" class="form-input text-sm">
                    <option value="">Все</option>
                    @foreach ($masters as $master)
                        <option value="{{ $master->user_id }}" @selected($filterMasterId == $master->user_id)>{{ $master->user_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label text-xs">Формат источника</label>
                <select name="source_format" class="form-input text-sm">
                    <option value="">Все</option>
                    <option value="{{ \App\Models\Source::FORMAT_ONLINE }}" @selected(($filterSourceFormat ?? '') === \App\Models\Source::FORMAT_ONLINE)>Онлайн</option>
                    <option value="{{ \App\Models\Source::FORMAT_OFFLINE }}" @selected(($filterSourceFormat ?? '') === \App\Models\Source::FORMAT_OFFLINE)>Офлайн</option>
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label text-xs">Источник</label>
                <select name="source_id" class="form-input text-sm">
                    <option value="">Все</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->source_id }}" @selected((string) ($filterSourceId ?? '') === (string) $source->source_id)>{{ $source->display_label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label text-xs invisible select-none" aria-hidden="true">&nbsp;</label>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button type="submit" variant="primary" size="sm">Применить</x-ui.button>
                    <x-ui.button href="{{ route('dispatcher.dashboard') }}" variant="secondary" size="sm">Сбросить</x-ui.button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table text-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Город</th>
                        <th>Источник</th>
                        <th>Мастер</th>
                        <th>Создал</th>
                        <th>Закрыл</th>
                        <th>Закрыто</th>
                        <th>Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($closedOrders as $order)
                        @php $net = $order->amount_paid - $order->amount_comp; @endphp
                        <tr class="cursor-pointer" onclick="window.location='{{ route('orders.show', $order->order_id) }}'">
                            <td>{{ $order->order_id }}</td>
                            <td>{{ $order->address->city->city_name ?? '—' }}</td>
                            <td>{{ $order->source?->source_name ?? '—' }}</td>
                            <td>{{ $order->master->user_name ?? '—' }}</td>
                            <td>{{ $order->creator->user_name ?? '—' }}</td>
                            <td>{{ $order->closedBy->user_name ?? '—' }}</td>
                            <td>{{ $order->order_closed_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            <td>{{ number_format($net, 0, ',', ' ') }} ₽</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-muted-foreground">Нет закрытых заказов за последние 7 дней</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
@endsection
