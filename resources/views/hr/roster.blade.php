@extends('layouts.app')

@section('title', 'График мастеров')

@section('content')
@php
    $rosterLegendItems = [
        ['color' => '#4ade80', 'text' => 'Работает (как «Завершён» у заказа)'],
        ['color' => '#f87171', 'text' => 'Выходной (как «Отменён» у заказа)'],
    ];
@endphp
<div class="hr-roster-page">
    <div class="mb-4 flex min-w-0 max-w-full flex-wrap items-center gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'Сотрудники', 'url' => route('hr.index')],
                    ['label' => 'График мастеров', 'url' => null],
                ]"
            />
            <x-legend :items="$rosterLegendItems" />
        </div>
    </div>

    {{-- Блок ширины таблицы: тулбар и таблица выровнены; сетка 1fr | авто | 1fr — навигация по центру, карандаш справа --}}
    <div class="hr-roster-table-block w-full min-w-0">
        <form method="GET" action="{{ route('hr.roster') }}" id="filtersForm"
            class="hr-roster-toolbar mb-4 grid w-full min-w-0 grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center sm:gap-x-3">
            <div class="min-w-0 justify-self-start">
                <div class="multiselect multiselect-compact" data-name="city_id" style="width: 140px;">
                    <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                        <span class="multiselect-text text-xs text-muted-foreground">Город</span>
                        <span style="margin-left: auto; font-size: 0.7rem;">▼</span>
                    </div>
                    <div class="multiselect-dropdown">
                        <div class="multiselect-actions">
                            <button type="button" onclick="selectAll(this)">выбрать все</button>
                            <button type="button" onclick="deselectAll(this)">снять все</button>
                        </div>
                        @foreach($cities as $city)
                            <label>
                                <input type="checkbox" name="city_id[]" value="{{ $city->city_id }}"
                                    {{ in_array($city->city_id, (array) request('city_id', [])) ? 'checked' : '' }}
                                    onchange="updateMultiselect(this); this.form.submit()">
                                {{ $city->city_name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex min-w-0 max-w-full flex-wrap items-center justify-center gap-2 justify-self-center sm:max-w-none">
                <a href="{{ route('hr.roster', ['week' => $startDate->copy()->subWeek()->format('Y-m-d'), 'city_id' => request('city_id')]) }}"
                    class="hr-roster-week-nav">
                    {!! icon('back') !!} Пред. неделя
                </a>

                <div
                    class="hr-roster-week-picker group relative inline-flex min-h-9 max-w-full items-center rounded-xl border border-border bg-card px-3 py-1.5 shadow-sm transition-colors hover:border-primary/50"
                    title="Нажмите на даты, чтобы выбрать неделю"
                >
                    <span class="week-title pointer-events-none select-none text-sm group-hover:underline group-hover:decoration-dotted group-hover:underline-offset-2">
                        {{ $startDate->format('d.m') }} — {{ $startDate->copy()->addDays(6)->format('d.m.Y') }}
                    </span>
                    <input
                        id="rosterWeekPicker"
                        type="date"
                        name="week"
                        value="{{ $startDate->format('Y-m-d') }}"
                        class="hr-roster-week-input absolute inset-0 z-[5] h-full w-full cursor-pointer opacity-0"
                        aria-label="Выбрать неделю по дате (любой день недели откроет эту календарную неделю)"
                        onchange="this.form.submit()"
                    />
                </div>

                <a href="{{ route('hr.roster', ['week' => $startDate->copy()->addWeek()->format('Y-m-d'), 'city_id' => request('city_id')]) }}"
                    class="hr-roster-week-nav">
                    След. неделя {!! icon('forward') !!}
                </a>
                <a href="{{ route('hr.roster', ['city_id' => request('city_id')]) }}" class="hr-roster-week-nav">
                    Текущая неделя
                </a>
            </div>

            <div class="justify-self-end sm:justify-self-end">
                @if(!auth()->user()->hasRole('general_director'))
                    <x-ui.button
                        type="button"
                        variant="primary"
                        size="icon"
                        id="editModeBtn"
                        onclick="toggleEditMode()"
                        title="Редактировать график"
                        aria-label="Редактировать график"
                        class="hr-roster-edit-btn shrink-0"
                    >
                        <span class="hr-roster-edit-icon inline-flex items-center justify-center" aria-hidden="true">{!! icon('edit') !!}</span>
                    </x-ui.button>
                @endif
            </div>
        </form>

        {{-- Таблица графика --}}
        <div class="card min-w-0" style="padding: 0;">
        <div class="table-responsive" style="overflow-x: auto;">
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th style="width: 200px;">Мастер</th>
                        @foreach($schedule['week_dates'] as $day)
                            <th class="day-header">
                                <div class="day-name">{{ $day['day_name'] }}</div>
                                <div class="day-number">{{ $day['day_number'] }}</div>
                                <div class="day-month">{{ $day['month'] }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedule['masters'] as $master)
                        <tr>
                            <td class="master-cell">
                                <strong>{{ $master['user_name'] }}</strong>
                                <span class="master-cities">{{ $master['cities'] }}</span>
                            </td>
                            @foreach($master['days'] as $index => $day)
                                <td class="schedule-cell {{ $day['is_working'] ? 'working status-completed' : 'off status-cancelled_cc' }}"
                                    data-user-id="{{ $master['user_id'] }}"
                                    data-date="{{ $day['date'] }}"
                                    data-is-working="{{ $day['is_working'] ? '1' : '0' }}"
                                    onclick="toggleDay(this)">
                                    <div class="cell-content">
                                        @if($day['is_working'])
                                            <span class="status-icon">{!! icon('check') !!}</span>
                                        @else
                                            <span class="status-icon off">{!! icon('close') !!}</span>
                                        @endif
                                        @if($day['note'])
                                            <span class="day-note" title="{{ $day['note'] }}">{!! icon('info_status') !!}</span>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-muted-foreground">
                                Мастера не найдены
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                {{-- Итого на линии --}}
                @if(count($schedule['masters']) > 0)
                    <tfoot>
                        <tr class="total-row">
                            <td><strong>Итого на линии</strong></td>
                            @foreach($schedule['daily_totals'] as $index => $total)
                                <td class="total-cell">
                                    <strong>{{ $total }}</strong>
                                </td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        </div>
    </div>
</div>

{{-- Модальное окно примечания --}}
<div id="noteModal" class="modal" style="display: none;">
    <div class="modal-backdrop" onclick="closeNoteModal()"></div>
    <div class="modal-content" style="max-width: 400px; position: relative; z-index: 1;">
        <div class="modal-header">
            <h3>Примечание</h3>
            <button type="button" class="modal-close" onclick="closeNoteModal()">{!! icon('close') !!}</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="noteUserId">
            <input type="hidden" id="noteDate">
            <div class="form-group">
                <label>Статус</label>
                <select id="noteStatus" class="form-input">
                    <option value="1">Работает</option>
                    <option value="0">Выходной</option>
                </select>
            </div>
            <div class="form-group" style="margin-top: 1rem;">
                <label>Примечание</label>
                <input type="text" id="noteText" class="form-input" maxlength="255" placeholder="Причина выходного...">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeNoteModal()">Отмена</button>
            <button type="button" class="btn btn-primary" onclick="saveNote()">Сохранить</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const rosterIconCheck = {!! json_encode(icon('check')) !!};
const rosterIconClose = {!! json_encode(icon('close')) !!};
let editMode = false;

// Мультиселект
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
        textElement.classList.add('text-muted-foreground');
    } else if (selected.length === 1) {
        textElement.textContent = selected[0].parentElement.textContent.trim();
        textElement.classList.remove('text-muted-foreground');
    } else {
        textElement.textContent = `Выбрано: ${selected.length}`;
        textElement.classList.remove('text-muted-foreground');
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

// Режим редактирования
function toggleEditMode() {
    editMode = !editMode;
    const cells = document.querySelectorAll('.schedule-cell');
    const btn = document.getElementById('editModeBtn');
    const page = document.querySelector('.hr-roster-page');

    cells.forEach(cell => {
        cell.classList.toggle('edit-mode', editMode);
    });
    if (page) {
        page.classList.toggle('hr-roster-editing', editMode);
    }

    if (btn) {
        const done = 'Завершить редактирование';
        const edit = 'Редактировать график';
        btn.title = editMode ? done : edit;
        btn.setAttribute('aria-label', editMode ? done : edit);
        btn.classList.toggle('btn-warning', editMode);
        btn.classList.toggle('ring-2', editMode);
        btn.classList.toggle('ring-primary', editMode);
    }
}

function toggleDay(cell) {
    if (!editMode) return;
    
    const userId = cell.dataset.userId;
    const date = cell.dataset.date;
    const currentlyWorking = cell.dataset.isWorking === '1';
    
    // При зажатом Shift открываем модалку для примечания
    if (event.shiftKey) {
        openNoteModal(userId, date, currentlyWorking);
        return;
    }
    
    // Просто переключаем статус
    const newStatus = !currentlyWorking;
    
    updateSchedule(userId, date, newStatus, null, cell);
}

function updateSchedule(userId, date, isWorking, note, cell) {
    fetch(crmUrl('/hr/roster'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
            user_id: userId,
            date: date,
            is_working: isWorking,
            note: note
        })
    })
    .then(r => {
        if (!r.ok) {
            throw new Error('Ошибка сервера: ' + r.status);
        }
        return r.json();
    })
    .then(data => {
        if (data.success) {
            // Обновляем ячейку
            cell.dataset.isWorking = isWorking ? '1' : '0';
            cell.classList.toggle('working', isWorking);
            cell.classList.toggle('off', !isWorking);
            cell.classList.toggle('status-completed', isWorking);
            cell.classList.toggle('status-cancelled_cc', !isWorking);

            const iconSpan = cell.querySelector('.status-icon');
            if (iconSpan) {
                iconSpan.innerHTML = isWorking ? rosterIconCheck : rosterIconClose;
                iconSpan.classList.toggle('off', !isWorking);
            }
            
            // Обновляем иконку примечания
            let noteSpan = cell.querySelector('.day-note');
            if (note) {
                if (!noteSpan) {
                    noteSpan = document.createElement('span');
                    noteSpan.className = 'day-note';
                    cell.querySelector('.cell-content').appendChild(noteSpan);
                }
                noteSpan.textContent = 'ℹ️';
                noteSpan.title = note;
            } else if (noteSpan) {
                noteSpan.remove();
            }
            
            // Пересчитываем итого (перезагружаем страницу для простоты)
            // В идеале нужно пересчитать через JS
        } else {
            Toast.error('Ошибка сохранения');
        }
    })
    .catch(err => {
        Toast.error('Ошибка: ' + err.message);
    });
}

// Модальное окно примечания
function openNoteModal(userId, date, currentlyWorking) {
    document.getElementById('noteUserId').value = userId;
    document.getElementById('noteDate').value = date;
    document.getElementById('noteStatus').value = currentlyWorking ? '1' : '0';
    document.getElementById('noteText').value = '';
    document.getElementById('noteModal').style.display = 'flex';
}

function closeNoteModal() {
    document.getElementById('noteModal').style.display = 'none';
}

function saveNote() {
    const userId = document.getElementById('noteUserId').value;
    const date = document.getElementById('noteDate').value;
    const isWorking = document.getElementById('noteStatus').value === '1';
    const note = document.getElementById('noteText').value;
    
    const cell = document.querySelector(`.schedule-cell[data-user-id="${userId}"][data-date="${date}"]`);
    
    updateSchedule(userId, date, isWorking, note, cell);
    closeNoteModal();
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.multiselect').forEach(multiselect => {
        const firstCheckbox = multiselect.querySelector('input[type="checkbox"]');
        if (firstCheckbox) {
            updateMultiselect(firstCheckbox);
        }
    });

    // Невидимый date: в части браузеров календарь не открывается — явно showPicker() по клику
    const weekPicker = document.getElementById('rosterWeekPicker');
    if (weekPicker) {
        weekPicker.addEventListener('click', function (e) {
            if (typeof this.showPicker !== 'function') return;
            e.preventDefault();
            try {
                this.showPicker();
            } catch (_) {
                /* ограничения окружения — без preventDefault не сработает открытие */
            }
        });
    }
});
</script>
@endpush
