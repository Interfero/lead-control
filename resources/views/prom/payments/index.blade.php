@extends('layouts.app')

@section('title', 'Оплата промоутеров')

@section('content')
<div style="max-width: 1600px; margin: 0 auto;">
    {{-- Заголовок --}}
    <div class="page-header">
        <h4>{!! icon('wallet') !!} Оплата промоутеров</h4>
        <a href="{{ route('prom.payments.create') }}" class="btn btn-primary">
            {!! icon('add') !!} Добавить
        </a>
    </div>
    
    {{-- Фильтры --}}
    <div class="card" style="margin-bottom: 1rem; padding: 0.75rem;">
        <form method="GET" action="{{ route('prom.payments') }}">
            <div class="filter-row">
                <div class="filter-field">
                    <span class="filter-label">Город</span>
                    <select name="city_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Все</option>
                        @foreach($cities as $city)
                            <option value="{{ $city->city_id }}" {{ ($filters['city_id'] ?? '') == $city->city_id ? 'selected' : '' }}>
                                {{ $city->city_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-field">
                    <span class="filter-label">Промоутер</span>
                    <select name="promoter_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Все</option>
                        @foreach($promoters as $promoter)
                            <option value="{{ $promoter->promoter_id }}" {{ ($filters['promoter_id'] ?? '') == $promoter->promoter_id ? 'selected' : '' }}>
                                {{ $promoter->promoter_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-field">
                    <span class="filter-label">Статус</span>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Все</option>
                        <option value="created" {{ ($filters['status'] ?? '') === 'created' ? 'selected' : '' }}>Сформировано</option>
                        <option value="paid" {{ ($filters['status'] ?? '') === 'paid' ? 'selected' : '' }}>Оплачено</option>
                    </select>
                </div>
                <div style="flex: 1;"></div>
                <a href="{{ route('prom.payments') }}" class="btn btn-link">{!! icon('refresh') !!} Сбросить</a>
            </div>
        </form>
    </div>
    
    {{-- Таблица --}}
    <div class="card" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 70px;"></th>
                    <th>Создано</th>
                    <th>Проведено</th>
                    <th>Неделя</th>
                    <th>Промоутер</th>
                    <th class="text-end">Листовок</th>
                    <th class="text-end">Коэфф.</th>
                    <th class="text-end">Итого</th>
                    <th class="text-end">Корр.</th>
                    <th class="text-end">К оплате</th>
                    <th>Статус</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                    <tr>
                        <td>
                            <div class="actions-cell">
                                <a href="{{ route('prom.payments.show', $payment) }}" class="btn-icon" title="Открыть">
                                    {!! icon('forward') !!}
                                </a>
                                <button type="button" class="btn-icon" onclick="copyPaymentInfo({{ $payment->payment_id }})" title="Копировать">
                                    {!! icon('copy') !!}
                                </button>
                            </div>
                        </td>
                        <td style="white-space: nowrap;">
                            <div style="line-height: 1.3;">
                                <div>{{ $payment->created_at->format('d.m.Y') }}</div>
                                <div class="text-muted" style="font-size: 0.75rem;">{{ $payment->created_at->format('H:i') }}</div>
                            </div>
                        </td>
                        <td>
                            @if($payment->paid_at)
                                <div style="line-height: 1.3;" class="text-success">
                                    <div>{{ $payment->paid_at->format('d.m.Y') }}</div>
                                    <div style="font-size: 0.75rem;">{{ $payment->paid_at->format('H:i') }}</div>
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            {{ $payment->week_start->format('d.m') }} - {{ $payment->week_end->format('d.m.Y') }}
                        </td>
                        <td class="fw-medium">{{ $payment->promoter?->promoter_name }}</td>
                        <td class="text-end">{{ number_format($payment->total_leaflets) }}</td>
                        <td class="text-end">{{ $payment->rate_per_leaflet }} ₽</td>
                        <td class="text-end">{{ number_format($payment->amount_base) }} ₽</td>
                        <td class="text-end {{ $payment->amount_adjustment != 0 ? ($payment->amount_adjustment > 0 ? 'text-success' : 'text-danger') : '' }}">
                            {{ $payment->amount_adjustment > 0 ? '+' : '' }}{{ number_format($payment->amount_adjustment) }} ₽
                        </td>
                        <td class="text-end fw-bold">{{ number_format($payment->amount_total) }} ₽</td>
                        <td>
                            @if($payment->payment_status === 'created')
                                <span class="badge badge-warning">Сформировано</span>
                            @else
                                <span class="badge badge-success">Оплачено</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align: center; color: #6b7280; padding: 2rem;">
                            Нет записей оплат
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($payments->hasPages())
        <div style="margin-top: 1rem;">
            {{ $payments->withQueryString()->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
function copyPaymentInfo(paymentId) {
    fetch(crmUrl(`/prom/pays/${paymentId}/copy-text`))
        .then(response => response.json())
        .then(data => {
            navigator.clipboard.writeText(data.text).then(() => {
                Toast.success('Информация скопирована в буфер обмена');
            }).catch(() => {
                // Fallback для старых браузеров
                const textarea = document.createElement('textarea');
                textarea.value = data.text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                Toast.success('Информация скопирована в буфер обмена');
            });
        })
        .catch(() => {
            Toast.error('Ошибка при копировании');
        });
}
</script>
@endpush
@endsection
