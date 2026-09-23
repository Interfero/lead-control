@extends('layouts.app')

@section('title', 'Разноска')

@section('content')
<div class="container-fluid">
    {{-- Фильтры с навигацией по неделям и кнопкой добавления --}}
    <div class="filter-card">
        <form method="GET" action="{{ route('prom.actions') }}" class="filter-row" id="filterForm">
            <div class="filter-group">
                <span class="filter-label">Город</span>
                <select name="city_id" class="form-select">
                    <option value="">Все</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->city_id }}" {{ ($filters['city_id'] ?? '') == $city->city_id ? 'selected' : '' }}>
                            {{ $city->city_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Промоутер</span>
                <select name="promoter_id" class="form-select">
                    <option value="">Все</option>
                    @foreach($promoters as $promoter)
                        <option value="{{ $promoter->promoter_id }}" {{ ($filters['promoter_id'] ?? '') == $promoter->promoter_id ? 'selected' : '' }}>
                            {{ $promoter->promoter_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Маршрут</span>
                <select name="route_id" class="form-select">
                    <option value="">Все</option>
                    @foreach($routes as $route)
                        <option value="{{ $route->route_id }}" {{ ($filters['route_id'] ?? '') == $route->route_id ? 'selected' : '' }}>
                            {{ $route->route_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Неделя</span>
                <div class="week-nav-group">
                    <button type="button" class="btn btn-nav" id="prevWeek" onclick="changeWeek(-1)">←</button>
                    <span class="week-nav-label" id="weekLabel">—</span>
                    <button type="button" class="btn btn-nav" id="nextWeek" onclick="changeWeek(1)">→</button>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-outline btn-sm">Показать</button>
                <a href="{{ route('prom.actions') }}" class="btn btn-link btn-sm">Сброс</a>
            </div>
            <div class="filter-spacer"></div>
            <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                {!! icon('add') !!} Добавить
            </button>
        </form>
    </div>
    
    @if($groupedActions->count() > 0)
        {{-- Контейнеры недель --}}
        @foreach($groupedActions as $index => $weekData)
            <div class="week-container" 
                 data-week-index="{{ $loop->index }}"
                 data-week-label="{{ $weekData['week_label'] }}"
                 data-week-count="{{ $weekData['actions_count'] }}"
                 data-week-leaflets="{{ number_format($weekData['total_leaflets']) }}">
                <div class="week-card">
                    <div class="week-summary">
                        <div class="week-summary-item">
                            <span class="week-summary-label">Записей:</span>
                            <span class="week-summary-value">{{ $weekData['actions_count'] }}</span>
                        </div>
                        <div class="week-summary-item">
                            <span class="week-summary-label">Листовок:</span>
                            <span class="week-summary-value">{{ number_format($weekData['total_leaflets']) }}</span>
                        </div>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Город</th>
                                <th>Промоутер</th>
                                <th>Маршрут</th>
                                <th>Макет</th>
                                <th class="text-end">Листовок</th>
                                <th width="50"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($weekData['actions'] as $action)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($action->route_action_date)->format('d.m.Y') }}</td>
                                    <td>{{ $action->city?->city_name ?? '—' }}</td>
                                    <td class="fw-medium">{{ $action->promoter?->promoter_name ?? '—' }}</td>
                                    <td>{{ $action->route?->route_name ?? '—' }}</td>
                                    <td class="text-muted">{{ $action->maket?->maket_name ?? '—' }}</td>
                                    <td class="text-end">{{ number_format($action->leaflets_count) }}</td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-link" onclick="toggleDropdown(event)">⋮</button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="{{ route('prom.actions.edit', $action) }}">
                                                    {!! icon('edit') !!} Редактировать
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form method="POST" action="{{ route('prom.actions.destroy', $action) }}"
                                                      onsubmit="return confirm('Удалить запись?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        {!! icon('delete') !!} Удалить
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @else
        <div class="week-card">
            <div class="empty-state">
                Нет записей разноски за выбранный период
            </div>
        </div>
    @endif
</div>

{{-- Модальное окно создания --}}
<div id="createModal" class="modal">
    <div class="modal-backdrop" onclick="closeCreateModal()"></div>
    <div class="modal-content">
        <form method="POST" action="{{ route('prom.actions.store') }}">
            @csrf
            
            <div class="modal-header">
                <h5 class="modal-title">{!! icon('add') !!} Новая запись разноски</h5>
                <button type="button" class="modal-close" onclick="closeCreateModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Дата <span class="required">*</span></label>
                    <input type="date" name="route_action_date" class="form-input-lg" 
                           value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Город <span class="required">*</span></label>
                    <select name="city_id" class="form-select-lg" required>
                        <option value="">Выберите город</option>
                        @foreach($cities as $city)
                            <option value="{{ $city->city_id }}">{{ $city->city_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Промоутер <span class="required">*</span></label>
                    <select name="promoter_id" class="form-select-lg" required>
                        <option value="">Выберите промоутера</option>
                        @foreach($promoters as $promoter)
                            <option value="{{ $promoter->promoter_id }}">{{ $promoter->promoter_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Маршрут <span class="required">*</span></label>
                    <select name="route_id" class="form-select-lg" required>
                        <option value="">Выберите маршрут</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->route_id }}">{{ $route->route_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Макет</label>
                    <select name="maket_id" class="form-select-lg">
                        <option value="">Не выбран</option>
                        @foreach($makets as $maket)
                            <option value="{{ $maket->maket_id }}">{{ $maket->maket_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Листовок <span class="required">*</span></label>
                    <input type="number" name="leaflets_count" class="form-input-lg" value="0" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Примечание</label>
                    <textarea name="route_action_note" class="form-input-lg" rows="2" maxlength="500"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">{!! icon('save') !!} Сохранить</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentWeekIndex = 0;
const weekContainers = document.querySelectorAll('.week-container');
const totalWeeks = weekContainers.length;

function updateWeekDisplay() {
    // Скрываем все недели
    weekContainers.forEach(container => {
        container.classList.remove('active');
    });
    
    // Показываем текущую неделю
    if (weekContainers[currentWeekIndex]) {
        const container = weekContainers[currentWeekIndex];
        container.classList.add('active');
        
        // Обновляем лейбл в навигации
        document.getElementById('weekLabel').textContent = container.dataset.weekLabel;
    }
    
    // Обновляем состояние кнопок
    document.getElementById('prevWeek').disabled = currentWeekIndex >= totalWeeks - 1;
    document.getElementById('nextWeek').disabled = currentWeekIndex <= 0;
}

function changeWeek(direction) {
    // direction: -1 = старее (вправо по индексу), 1 = новее (влево по индексу)
    const newIndex = currentWeekIndex - direction;
    if (newIndex >= 0 && newIndex < totalWeeks) {
        currentWeekIndex = newIndex;
        updateWeekDisplay();
    }
}

// Инициализация
if (totalWeeks > 0) {
    updateWeekDisplay();
}

function toggleDropdown(event) {
    event.stopPropagation();
    event.preventDefault();
    
    document.querySelectorAll('.main-content .dropdown-menu.show').forEach(menu => {
        if (menu !== event.target.closest('.dropdown')?.querySelector('.dropdown-menu')) {
            menu.classList.remove('show');
        }
    });
    
    const dropdown = event.target.closest('.dropdown');
    if (!dropdown) return;
    
    const menu = dropdown.querySelector('.dropdown-menu');
    if (menu) {
        menu.classList.toggle('show');
    }
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.main-content .dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

function openCreateModal() {
    document.getElementById('createModal').classList.add('show');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.remove('show');
}
</script>
@endpush
