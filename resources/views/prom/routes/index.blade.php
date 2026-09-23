@extends('layouts.app')

@section('title', 'Маршруты')

@section('content')
<div class="container-fluid">
    {{-- Фильтр по городу и кнопка добавления --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <form method="GET" action="{{ route('prom.routes') }}" style="display: flex; align-items: center; gap: 0.5rem; flex: 1;">
                    <label class="form-label mb-0" style="font-size: 0.875rem; color: #6b7280;">Город:</label>
                    <select name="city_id" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto; min-width: 150px;">
                        @foreach($cities as $city)
                            <option value="{{ $city->city_id }}" {{ $cityId == $city->city_id ? 'selected' : '' }}>
                                {{ $city->city_name }}
                            </option>
                        @endforeach
                    </select>
                </form>
                @if($cityId)
                    <button type="button" class="btn btn-primary" onclick="openCreateModal()">
                        {!! icon('add') !!} Добавить
                    </button>
                @endif
            </div>
        </div>
    </div>
    
    {{-- Таблица маршрутов --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Маршрут</th>
                        <th>Район</th>
                        <th>Комментарий</th>
                        <th class="text-end">Квартир</th>
                        <th class="text-end">Подъездов</th>
                        <th class="text-end">К. сложности</th>
                        <th class="text-end">Пройдено</th>
                        <th>Последний раз</th>
                        <th class="text-end">К. разноски</th>
                        <th width="50"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($routes as $route)
                        <tr>
                            <td class="fw-medium">{{ $route->route_name }}</td>
                            <td>{{ $route->district?->district_name ?? '—' }}</td>
                            <td class="text-muted">{{ Str::limit($route->route_note, 25) ?? '—' }}</td>
                            <td class="text-end">{{ number_format($route->route_apartments_count) }}</td>
                            <td class="text-end">{{ number_format($route->route_entrances_count) }}</td>
                            <td class="text-end">{{ $route->complexity_coefficient }}</td>
                            <td class="text-end">{{ $route->passes_count }}</td>
                            <td>
                                @if($route->last_pass_date)
                                    {{ \Carbon\Carbon::parse($route->last_pass_date)->format('d.m.Y') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $route->delivery_coefficient }}%</td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-link" onclick="toggleDropdown(event)">
                                        ⋮
                                    </button>
                                    <ul class="dropdown-menu" onclick="event.stopPropagation()">
                                        <li style="list-style: none;">
                                            <button type="button" class="dropdown-item" 
                                                    onclick="openEditModal({{ $route->route_id }})">
                                                {!! icon('edit') !!} Редактировать
                                            </button>
                                        </li>
                                        <li style="list-style: none;">
                                            <button type="button" class="dropdown-item"
                                                    onclick="openHistoryModal({{ $route->route_id }})">
                                                {!! icon('history') !!} История
                                            </button>
                                        </li>
                                        <li style="list-style: none;"><div class="dropdown-divider"></div></li>
                                        <li style="list-style: none;">
                                            <form method="POST" action="{{ route('prom.routes.destroy', $route) }}"
                                                  onsubmit="return confirm('Удалить маршрут {{ $route->route_name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    {!! icon('delete') !!} Удалить
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                Нет маршрутов для выбранного города
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    {{-- Пагинация --}}
    @if($routes->hasPages())
        <div class="mt-3">
            {{ $routes->withQueryString()->links() }}
        </div>
    @endif
</div>

{{-- Модальное окно создания --}}
<div id="createModal" class="modal">
    <div class="modal-backdrop" onclick="closeCreateModal()"></div>
    <div class="modal-content">
        <form method="POST" action="{{ route('prom.routes.store') }}">
            @csrf
            <input type="hidden" name="city_id" value="{{ $cityId }}">
            
            <div class="modal-header">
                <h5 class="modal-title">{!! icon('add') !!} Новый маршрут</h5>
                <button type="button" class="modal-close" onclick="closeCreateModal()">{!! icon('close') !!}</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Название <span class="required">*</span></label>
                    <input type="text" name="route_name" class="form-input" required 
                           placeholder="Например: АЛМ-1">
                </div>
                <div class="form-group">
                    <label class="form-label">Район</label>
                    <select name="district_id" class="form-select">
                        <option value="">Не выбран</option>
                        @foreach($districts as $district)
                            <option value="{{ $district->district_id }}">{{ $district->district_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Квартир</label>
                        <input type="number" name="route_apartments_count" class="form-input" 
                               value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Подъездов</label>
                        <input type="number" name="route_entrances_count" class="form-input" 
                               value="0" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Комментарий</label>
                    <textarea name="route_note" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">{!! icon('save') !!} Сохранить</button>
            </div>
        </form>
    </div>
</div>

{{-- Модальное окно редактирования --}}
<div id="editModal" class="modal">
    <div class="modal-backdrop" onclick="closeEditModal()"></div>
    <div class="modal-content">
        <form method="POST" id="editForm">
            @csrf
            @method('PUT')
            
            <div class="modal-header">
                <h5 class="modal-title">{!! icon('edit') !!} Редактировать маршрут</h5>
                <button type="button" class="modal-close" onclick="closeEditModal()">{!! icon('close') !!}</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Название <span class="required">*</span></label>
                    <input type="text" name="route_name" id="edit_route_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Район</label>
                    <select name="district_id" id="edit_district_id" class="form-select">
                        <option value="">Не выбран</option>
                        @foreach($districts as $district)
                            <option value="{{ $district->district_id }}">{{ $district->district_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Квартир</label>
                        <input type="number" name="route_apartments_count" id="edit_apartments" 
                               class="form-input" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Подъездов</label>
                        <input type="number" name="route_entrances_count" id="edit_entrances" 
                               class="form-input" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Комментарий</label>
                    <textarea name="route_note" id="edit_note" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">{!! icon('save') !!} Сохранить</button>
            </div>
        </form>
    </div>
</div>

{{-- Модальное окно истории --}}
<div id="historyModal" class="modal">
    <div class="modal-backdrop" onclick="closeHistoryModal()"></div>
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h5 class="modal-title">{!! icon('history') !!} История прохождения</h5>
            <button type="button" class="modal-close" onclick="closeHistoryModal()">{!! icon('close') !!}</button>
        </div>
        <div class="modal-body" id="historyContent">
            <div class="text-center py-4">
                <div class="spinner-border text-primary"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeHistoryModal()">Закрыть</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Данные маршрутов для редактирования
const routesData = @json($routes->getCollection()->keyBy('route_id'));

function toggleDropdown(event) {
    event.stopPropagation();
    event.preventDefault();
    
    // Закрываем все другие dropdown
    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
        if (menu !== event.target.closest('.dropdown')?.querySelector('.dropdown-menu')) {
            menu.classList.remove('show');
        }
    });
    
    const dropdown = event.target.closest('.dropdown');
    if (!dropdown) return;
    
    const menu = dropdown.querySelector('.dropdown-menu');
    if (!menu) return;
    
    const isOpen = menu.classList.contains('show');
    
    if (isOpen) {
        menu.classList.remove('show');
    } else {
        // Вычисляем позицию для fixed позиционирования
        const button = event.target.closest('button') || event.target;
        const buttonRect = button.getBoundingClientRect();
        
        // Позиционируем меню справа от кнопки (открывается влево)
        const menuWidth = 180; // min-width из CSS
        const rightPosition = window.innerWidth - buttonRect.right;
        
        menu.style.top = (buttonRect.bottom + 4) + 'px';
        menu.style.right = rightPosition + 'px';
        menu.style.left = 'auto';
        
        // Проверяем, не выходит ли меню за правый край экрана
        if (buttonRect.right - menuWidth < 0) {
            // Если выходит, позиционируем слева от кнопки
            menu.style.left = buttonRect.left + 'px';
            menu.style.right = 'auto';
        }
        
        menu.classList.add('show');
    }
}

// Закрытие dropdown при клике вне его
document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

// Закрытие dropdown при прокрутке
window.addEventListener('scroll', function() {
    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
        menu.classList.remove('show');
    });
}, true);

// Обновление позиции dropdown при изменении размера окна
window.addEventListener('resize', function() {
    document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
        const dropdown = menu.closest('.dropdown');
        if (dropdown) {
            const button = dropdown.querySelector('button');
            if (button) {
                const buttonRect = button.getBoundingClientRect();
                const menuWidth = 180;
                const rightPosition = window.innerWidth - buttonRect.right;
                
                menu.style.top = (buttonRect.bottom + 4) + 'px';
                menu.style.right = rightPosition + 'px';
                menu.style.left = 'auto';
                
                if (buttonRect.right - menuWidth < 0) {
                    menu.style.left = buttonRect.left + 'px';
                    menu.style.right = 'auto';
                }
            }
        }
    });
});

function openCreateModal() {
    document.getElementById('createModal').classList.add('show');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.remove('show');
}

function openEditModal(routeId) {
    const route = routesData[routeId];
    if (!route) return;
    
    document.getElementById('editForm').action = crmUrl(`/prom/routes/${routeId}`);
    document.getElementById('edit_route_name').value = route.route_name;
    document.getElementById('edit_district_id').value = route.district_id || '';
    document.getElementById('edit_apartments').value = route.route_apartments_count;
    document.getElementById('edit_entrances').value = route.route_entrances_count;
    document.getElementById('edit_note').value = route.route_note || '';
    
    document.getElementById('editModal').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('show');
}

function openHistoryModal(routeId) {
    const historyContent = document.getElementById('historyContent');
    historyContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    
    document.getElementById('historyModal').classList.add('show');
    
    fetch(crmUrl(`/prom/routes/${routeId}/history`))
        .then(response => response.json())
        .then(data => {
            if (data.history.length === 0) {
                historyContent.innerHTML = '<p class="text-center text-muted py-4">Маршрут ещё не проходили</p>';
                return;
            }
            
            let html = `
                <p class="mb-3"><strong>Маршрут:</strong> ${data.route.route_name}</p>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Промоутер</th>
                                <th>Макет</th>
                                <th class="text-end">Листовок</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            data.history.forEach(action => {
                const date = new Date(action.route_action_date).toLocaleDateString('ru-RU');
                html += `
                    <tr>
                        <td>${date}</td>
                        <td>${action.promoter?.promoter_name || '—'}</td>
                        <td>${action.maket?.maket_name || '—'}</td>
                        <td class="text-end">${action.leaflets_count.toLocaleString()}</td>
                        <td class="text-end">${action.pass_percentage}%</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            historyContent.innerHTML = html;
        })
        .catch(error => {
            historyContent.innerHTML = '<p class="text-center text-danger py-4">Ошибка загрузки</p>';
        });
}

function closeHistoryModal() {
    document.getElementById('historyModal').classList.remove('show');
}
</script>
@endpush
@endsection
