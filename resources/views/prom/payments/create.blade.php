@extends('layouts.app')

@section('title', 'Новая оплата')

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <div class="page-header">
        <h4>{!! icon('add') !!} Новая оплата</h4>
    </div>
    
    <div class="two-columns">
        {{-- Левая колонка: параметры --}}
        <div>
            <div class="card" style="margin-bottom: 1rem;">
                <div class="card-header">Параметры</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('prom.payments.store') }}" id="paymentForm">
                        @csrf
                        
                        <div class="form-group">
                            <label class="form-label">Город <span class="required">*</span></label>
                            <select name="city_id" id="city_id" class="form-select" required>
                                <option value="">Выберите город</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->city_id }}">{{ $city->city_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Промоутер <span class="required">*</span></label>
                            <select name="promoter_id" id="promoter_id" class="form-select" required>
                                <option value="">Выберите промоутера</option>
                                @foreach($promoters as $promoter)
                                    <option value="{{ $promoter->promoter_id }}">{{ $promoter->promoter_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Неделя работы <span class="required">*</span></label>
                            <select id="week_select" class="form-select" required>
                                <option value="">Выберите неделю</option>
                                @foreach($weeks as $week)
                                    <option value="{{ $week['week_start'] }}|{{ $week['week_end'] }}">
                                        {{ $week['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="week_start" id="week_start">
                            <input type="hidden" name="week_end" id="week_end">
                        </div>
                        
                        <button type="button" class="btn btn-secondary" id="checkBtn" style="margin-bottom: 1rem;">
                            {!! icon('search') !!} Проверить
                        </button>
                        
                        <div class="divider"></div>
                        
                        {{-- Блок с расчётом (скрыт до проверки) --}}
                        <div id="calculationBlock" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">Корректировки (₽)</label>
                                <input type="number" name="amount_adjustment" id="amount_adjustment" 
                                       class="form-input" value="0">
                                <div class="form-hint">Премия (+) или штраф (-)</div>
                            </div>
                            
                            <div class="form-group">
                                <div class="label-with-action">
                                    <label class="form-label">Реквизиты</label>
                                    <button type="button" class="btn-link" id="useLastRequisitesBtn">
                                        Использовать последние
                                    </button>
                                </div>
                                <input type="text" name="payment_requisites" id="payment_requisites" 
                                       class="form-input" placeholder="Номер карты/счёта">
                                <div class="form-hint" id="lastRequisitesHint" style="display: none;"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Банк</label>
                                <select name="payment_bank_id" id="payment_bank_id" class="form-select">
                                    <option value="">Не выбран</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->bank_id }}">{{ $bank->bank_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Комментарий</label>
                                <textarea name="payment_comment" class="form-input" rows="2" style="height: auto;"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                {!! icon('save') !!} Создать оплату
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        {{-- Правая колонка: предпросмотр --}}
        <div>
            <div class="card">
                <div class="card-header">Сводная таблица</div>
                <div class="card-body" id="previewBlock">
                    <p class="text-muted" style="text-align: center; padding: 2rem 0;">
                        Выберите город, промоутера и неделю, затем нажмите "Проверить"
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const weekSelect = document.getElementById('week_select');
    const weekStart = document.getElementById('week_start');
    const weekEnd = document.getElementById('week_end');
    const checkBtn = document.getElementById('checkBtn');
    const previewBlock = document.getElementById('previewBlock');
    const calculationBlock = document.getElementById('calculationBlock');
    const promoterSelect = document.getElementById('promoter_id');
    const requisitesInput = document.getElementById('payment_requisites');
    const bankSelect = document.getElementById('payment_bank_id');
    const useLastRequisitesBtn = document.getElementById('useLastRequisitesBtn');
    const lastRequisitesHint = document.getElementById('lastRequisitesHint');
    
    // Обработка выбора недели
    weekSelect.addEventListener('change', function() {
        const [start, end] = (this.value || '').split('|');
        weekStart.value = start || '';
        weekEnd.value = end || '';
    });
    
    // Кнопка "Использовать последние реквизиты"
    useLastRequisitesBtn.addEventListener('click', function() {
        const promoterId = promoterSelect.value;
        if (!promoterId) {
            Toast.warning('Сначала выберите промоутера');
            return;
        }
        
        fetch(@json(route('prom.payments.last-requisites', ['promoterId' => '__ID__'])).replace('__ID__', promoterId))
            .then(response => response.json())
            .then(data => {
                if (data.found) {
                    requisitesInput.value = data.requisites;
                    bankSelect.value = data.bank_id || '';
                    lastRequisitesHint.textContent = `Последний банк: ${data.bank_name || 'не указан'}`;
                    lastRequisitesHint.style.display = 'block';
                } else {
                    Toast.info('Нет данных о предыдущих оплатах для этого промоутера');
                }
            })
            .catch(() => Toast.error('Ошибка при загрузке реквизитов'));
    });
    
    // Кнопка "Проверить"
    checkBtn.addEventListener('click', function() {
        const cityId = document.getElementById('city_id').value;
        const promoterId = promoterSelect.value;
        const start = weekStart.value;
        const end = weekEnd.value;
        
        if (!cityId || !promoterId || !start || !end) {
            Toast.warning('Заполните все обязательные поля');
            return;
        }
        
        checkBtn.disabled = true;
        checkBtn.innerHTML = '<span class="spinner-border"></span> Загрузка...';
        
        fetch('{{ route("prom.payments.preview") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                city_id: cityId,
                promoter_id: promoterId,
                week_start: start,
                week_end: end
            })
        })
        .then(response => response.json())
        .then(data => {
            checkBtn.disabled = false;
            checkBtn.innerHTML = '{!! icon("search") !!} Проверить';
            
            if (data.actions && data.actions.length > 0) {
                let html = `
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th class="text-end">Листовок</th>
                                <th>Маршрут</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                data.actions.forEach(action => {
                    const date = new Date(action.route_action_date).toLocaleDateString('ru-RU');
                    html += `<tr>
                        <td>${date}</td>
                        <td class="text-end">${action.leaflets_count.toLocaleString()}</td>
                        <td>${action.route?.route_name || '—'}</td>
                    </tr>`;
                });
                
                html += `</tbody></table>
                    <div class="divider"></div>
                    <div class="summary-row">
                        <span>Всего листовок:</span>
                        <span class="fw-bold">${data.total_leaflets.toLocaleString()}</span>
                    </div>
                    <div class="summary-row">
                        <span>Коэффициент:</span>
                        <span>${data.rate} ₽/листовка</span>
                    </div>
                    <div class="summary-row total">
                        <span>Итого:</span>
                        <span>${data.amount_base.toLocaleString()} ₽</span>
                    </div>
                `;
                
                previewBlock.innerHTML = html;
                calculationBlock.style.display = 'block';
            } else {
                previewBlock.innerHTML = '<p class="text-muted" style="text-align: center; padding: 2rem 0;">Нет данных о работе за выбранный период</p>';
                calculationBlock.style.display = 'none';
            }
        })
        .catch(() => {
            checkBtn.disabled = false;
            checkBtn.innerHTML = '{!! icon("search") !!} Проверить';
            Toast.error('Ошибка при загрузке данных');
        });
    });
});
</script>
@endpush
@endsection
