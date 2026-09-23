@extends('layouts.app')

@section('title', 'Журнал — Записи')

@section('content')
<div style="max-width: 1600px; margin: 0 auto;">
    @include('prom.journal._tabs')
    
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: 1rem;">
            {{ session('success') }}
        </div>
    @endif
    
    {{-- Фильтры --}}
    <div class="card" style="margin-bottom: 1rem; padding: 0.75rem;">
        <form method="GET" action="{{ route('prom.journal.appointments') }}" id="filtersForm">
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                <div class="filter-field" style="width: 150px;">
                    <input type="date" name="date_from" class="form-input" 
                           value="{{ $filters['date_from'] ?? '' }}" onchange="this.form.submit()">
                </div>
                <div class="filter-field" style="width: 150px;">
                    <input type="date" name="date_to" class="form-input" 
                           value="{{ $filters['date_to'] ?? '' }}" onchange="this.form.submit()">
                </div>
                
                {{-- Мультиселект Статус --}}
                <div class="filter-field" style="width: 160px;">
                    <div class="multiselect" data-name="status">
                        <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                            <span class="multiselect-text" style="color: #9ca3af;">Статус</span>
                            <span style="margin-left: auto;">▼</span>
                        </div>
                        <div class="multiselect-dropdown">
                            <div class="multiselect-actions">
                                <button type="button" onclick="selectAll(this)">все</button>
                                <button type="button" onclick="deselectAll(this)">сбросить</button>
                            </div>
                            @foreach($statuses as $code => $label)
                                <label>
                                    <input type="checkbox" name="status[]" value="{{ $code }}" 
                                        {{ in_array($code, (array)($filters['status'] ?? [])) ? 'checked' : '' }}
                                        onchange="updateMultiselect(this); this.form.submit()">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                {{-- Мультиселект Город --}}
                <div class="filter-field" style="width: 160px;">
                    <div class="multiselect" data-name="city_id">
                        <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                            <span class="multiselect-text" style="color: #9ca3af;">Город</span>
                            <span style="margin-left: auto;">▼</span>
                        </div>
                        <div class="multiselect-dropdown">
                            <div class="multiselect-actions">
                                <button type="button" onclick="selectAll(this)">все</button>
                                <button type="button" onclick="deselectAll(this)">сбросить</button>
                            </div>
                            @foreach($cities as $city)
                                <label>
                                    <input type="checkbox" name="city_id[]" value="{{ $city->city_id }}" 
                                        {{ in_array($city->city_id, (array)($filters['city_id'] ?? [])) ? 'checked' : '' }}
                                        onchange="updateMultiselect(this); this.form.submit()">
                                    {{ $city->city_name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <div style="flex: 1;"></div>
                <a href="{{ route('prom.journal.appointments', ['quick' => 'today']) }}" class="btn btn-filter">Сегодня</a>
                <a href="{{ route('prom.journal.appointments', ['quick' => 'tomorrow']) }}" class="btn btn-filter">Завтра</a>
                <a href="{{ route('prom.journal.appointments') }}" class="btn btn-filter">{!! icon('refresh') !!} Сбросить</a>
            </div>
        </form>
    </div>
    
    {{-- Таблица --}}
    <div class="card" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 100px;">Дата/время</th>
                    <th>Город</th>
                    <th style="width: 40px;">С</th>
                    <th>Телефон</th>
                    <th>Telegram</th>
                    <th>Имя</th>
                    <th>Статус</th>
                    <th>Район</th>
                    <th style="width: 80px;">Листовок</th>
                    <th>Комментарий</th>
                    <th style="width: 70px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($appointments as $appointment)
                    <tr>
                        <td>
                            @php
                                $weekdays = ['вс', 'пн', 'вт', 'ср', 'чт', 'пт', 'сб'];
                                $weekday = $weekdays[$appointment->appointment_datetime->dayOfWeek];
                            @endphp
                            <div style="display: flex; flex-direction: column; line-height: 1.3;">
                                <span style="font-weight: 600; font-size: 1rem;">{{ $appointment->appointment_datetime->format('H:i') }}</span>
                                <span style="font-size: 0.8125rem; color: #374151;">{{ $weekday }}</span>
                                <span style="font-size: 0.75rem; color: #374151;">{{ $appointment->appointment_datetime->format('d.m.Y') }}</span>
                            </div>
                        </td>
                        <td>{{ $appointment->city?->city_name ?? '—' }}</td>
                        <td style="text-align: center;">
                            @if($appointment->is_interview)
                                {!! icon('check') !!}
                            @endif
                        </td>
                        <td>
                            @if($appointment->person_phone)
                                <x-phone-masked :number="$appointment->person_phone" :show-adds="false" />
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $appointment->person_telegram ?? '—' }}</td>
                        <td><strong>{{ $appointment->person_name }}</strong></td>
                        <td>
                            <span class="badge badge-{{ $appointment->appointment_status }}">
                                {{ $statuses[$appointment->appointment_status] ?? $appointment->appointment_status }}
                            </span>
                        </td>
                        <td>{{ $appointment->district?->district_name ?? '—' }}</td>
                        <td>{{ $appointment->leaflets_to_prepare ?: '—' }}</td>
                        <td style="max-width: 200px;">
                            <span style="display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;" 
                                title="{{ $appointment->appointment_comment }}">
                                {{ $appointment->appointment_comment ? Str::limit($appointment->appointment_comment, 30) : '—' }}
                            </span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="{{ route('prom.appointments.edit', $appointment) }}" class="btn-icon" title="Редактировать">
                                    {!! icon('edit') !!}
                                </a>
                                <form method="POST" action="{{ route('prom.appointments.destroy', $appointment) }}" style="display: inline;"
                                      onsubmit="return confirm('Вы уверены, что хотите удалить эту запись?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Удалить">
                                        {!! icon('trash') !!}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align: center; color: #6b7280; padding: 2rem;">
                            Нет записей за выбранный период
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 1rem;">
        {{ $appointments->withQueryString()->links() }}
    </div>
</div>

@push('scripts')
<script>
// Мультиселект функции
function toggleMultiselect(element) {
    const dropdown = element.nextElementSibling;
    const wasOpen = dropdown.classList.contains('open');
    
    document.querySelectorAll('.multiselect-dropdown.open').forEach(d => d.classList.remove('open'));
    
    if (!wasOpen) {
        dropdown.classList.add('open');
    }
}

function updateMultiselect(checkbox) {
    const multiselect = checkbox.closest('.multiselect');
    const checkboxes = multiselect.querySelectorAll('input[type="checkbox"]');
    const selected = Array.from(checkboxes).filter(cb => cb.checked);
    const textElement = multiselect.querySelector('.multiselect-text');
    
    if (!textElement.dataset.placeholder) {
        textElement.dataset.placeholder = textElement.textContent;
    }
    
    if (selected.length === 0) {
        textElement.textContent = textElement.dataset.placeholder;
        textElement.style.color = '#9ca3af';
    } else if (selected.length === 1) {
        textElement.textContent = selected[0].parentElement.textContent.trim();
        textElement.style.color = '';
    } else {
        textElement.textContent = `Выбрано: ${selected.length}`;
        textElement.style.color = '';
    }
}

function selectAll(button) {
    event.preventDefault();
    const multiselect = button.closest('.multiselect');
    const checkboxes = multiselect.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = true);
    if (checkboxes[0]) {
        updateMultiselect(checkboxes[0]);
    }
}

function deselectAll(button) {
    event.preventDefault();
    const multiselect = button.closest('.multiselect');
    const checkboxes = multiselect.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
    if (checkboxes[0]) {
        updateMultiselect(checkboxes[0]);
    }
    const form = button.closest('form');
    if (form) form.submit();
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.multiselect')) {
        document.querySelectorAll('.multiselect-dropdown.open').forEach(d => d.classList.remove('open'));
    }
});

// Инициализация мультиселектов
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.multiselect').forEach(multiselect => {
        const firstCheckbox = multiselect.querySelector('input[type="checkbox"]');
        if (firstCheckbox) {
            updateMultiselect(firstCheckbox);
        }
    });
});
</script>
@endpush
@endsection
