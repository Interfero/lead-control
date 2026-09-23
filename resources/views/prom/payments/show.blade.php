@extends('layouts.app')

@section('title', 'Оплата №' . $payment->payment_id)

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <div class="page-header">
        <h4>{!! icon('wallet') !!} Оплата №{{ $payment->payment_id }}</h4>
        <a href="{{ route('prom.payments') }}" class="btn btn-secondary">
            {!! icon('back') !!} К списку
        </a>
    </div>
    
    <div class="two-columns">
        {{-- Левая колонка: информация --}}
        <div>
            <div class="card" style="margin-bottom: 1rem;">
                <div class="card-header">
                    <span>Информация</span>
                    @if($payment->payment_status === 'created')
                        <span class="badge badge-warning">Сформировано</span>
                    @else
                        <span class="badge badge-success">Оплачено</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <div class="info-label">Город:</div>
                        <div class="info-value">{{ $payment->city?->city_name }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Промоутер:</div>
                        <div class="info-value">{{ $payment->promoter?->promoter_name }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Неделя:</div>
                        <div class="info-value">{{ $payment->week_start->format('d.m') }} - {{ $payment->week_end->format('d.m.Y') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Листовок:</div>
                        <div class="info-value">{{ number_format($payment->total_leaflets) }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Коэффициент:</div>
                        <div class="info-value">{{ $payment->rate_per_leaflet }} ₽</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Итого:</div>
                        <div class="info-value">{{ number_format($payment->amount_base) }} ₽</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Корректировки:</div>
                        <div class="info-value {{ $payment->amount_adjustment != 0 ? ($payment->amount_adjustment > 0 ? 'text-success' : 'text-danger') : '' }}">
                            {{ $payment->amount_adjustment > 0 ? '+' : '' }}{{ number_format($payment->amount_adjustment) }} ₽
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label fw-bold">К оплате:</div>
                        <div class="info-value fw-bold fs-lg">{{ number_format($payment->amount_total) }} ₽</div>
                    </div>
                    
                    @if($payment->payment_status === 'created')
                        <div class="divider"></div>
                        
                        <form method="POST" action="{{ route('prom.payments.update', $payment) }}">
                            @csrf
                            @method('PUT')
                            
                            <div class="form-group">
                                <label class="form-label">Корректировки (₽)</label>
                                <input type="number" name="amount_adjustment" class="form-input" 
                                       value="{{ $payment->amount_adjustment }}">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Реквизиты</label>
                                <input type="text" name="payment_requisites" class="form-input" 
                                       value="{{ $payment->payment_requisites }}">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Банк</label>
                                <select name="payment_bank_id" class="form-select">
                                    <option value="">Не выбран</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->bank_id }}" {{ $payment->payment_bank_id == $bank->bank_id ? 'selected' : '' }}>
                                            {{ $bank->bank_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Комментарий</label>
                                <textarea name="payment_comment" class="form-input" rows="2" style="height: auto;">{{ $payment->payment_comment }}</textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                {!! icon('save') !!} Сохранить
                            </button>
                        </form>
                        
                        <div class="divider"></div>
                        
                        <form method="POST" action="{{ route('prom.payments.destroy', $payment) }}" 
                              onsubmit="return confirm('Удалить оплату?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                {!! icon('delete') !!} Удалить оплату
                            </button>
                        </form>
                        
                        @if($payment->cfmOperation)
                            <div class="cfm-link">
                                Кассовая операция: 
                                <a href="{{ route('cfm.show', $payment->cfmOperation) }}">
                                    №{{ $payment->cfmOperation->cfm_id }}
                                </a>
                                — провести для завершения оплаты
                            </div>
                        @endif
                    @else
                        <div class="divider"></div>
                        
                        <div class="info-row">
                            <div class="info-label">Реквизиты:</div>
                            <div class="info-value">{{ $payment->payment_requisites ?? '—' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Банк:</div>
                            <div class="info-value">{{ $payment->bank?->bank_name ?? '—' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Оплачено:</div>
                            <div class="info-value">{{ $payment->paid_at?->format('d.m.Y H:i') }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        {{-- Правая колонка: детализация --}}
        <div>
            <div class="card">
                <div class="card-header">Детализация работы</div>
                <div style="padding: 0;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th class="text-end">Листовок</th>
                                <th>Маршрут</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payment->details as $detail)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($detail->action_date)->format('d.m.Y') }}</td>
                                    <td class="text-end">{{ number_format($detail->leaflets_count) }}</td>
                                    <td>{{ $detail->route_name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Итого:</td>
                                <td class="text-end">{{ number_format($payment->total_leaflets) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
