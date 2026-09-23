@extends('layouts.app')

@section('title', 'Сотрудники')

@section('content')
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'Сотрудники', 'url' => null],
                ]"
            />
            <x-legend
                :items="[
                    ['color' => 'rgba(0,0,0,0.5)', 'text' => 'Полупрозрачная строка — уволенный сотрудник'],
                    ['color' => '#dc2626', 'text' => 'Красная дата — дата увольнения'],
                ]"
            />
        </div>
        @if (auth()->user()->hasAnyRole(['branch_head', 'regional_director', 'developer', 'general_director']))
            <div class="shrink-0">
                <x-ui.button
                    href="{{ route('hr.create') }}"
                    class="gap-2 rounded-md px-4 py-1.5 text-sm"
                >
                    {!! icon('add') !!}
                    Создать сотрудника
                </x-ui.button>
            </div>
        @endif
    </div>

    <x-ui.card padding="none" class="mb-4 min-w-0 max-w-full">
        <form method="GET" action="{{ route('hr.index') }}" id="filtersForm" class="min-w-0 max-w-full">
            <div class="orders-filters-sticky flex flex-wrap items-end gap-4 border-b border-border px-4 py-2">
                <div class="flex flex-wrap items-center gap-4 pb-0.5">
                    <label class="flex cursor-pointer select-none items-center gap-2">
                        <input
                            type="checkbox"
                            name="show_fired"
                            value="1"
                            class="h-4 w-4 shrink-0 rounded border-border bg-input text-primary focus:ring-2 focus:ring-ring focus:ring-offset-0"
                            {{ ($filters['show_fired'] ?? '') ? 'checked' : '' }}
                        >
                        <span class="text-sm text-muted-foreground">Показать уволенных</span>
                    </label>
                </div>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <x-ui.button
                        href="{{ route('hr.index') }}"
                        variant="primary"
                        size="icon"
                        class="shrink-0"
                        title="Сбросить фильтры"
                    >
                        {!! icon('refresh') !!}
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        variant="primary"
                        size="sm"
                        id="filtersFormSubmit"
                        class="shrink-0"
                        title="Применить фильтр"
                    >
                        Найти
                    </x-ui.button>
                </div>
            </div>

            <table class="table table-sticky orders-sticky-table w-full min-w-0 text-sm">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Город</th>
                        <th>ФИО</th>
                        <th>Роль</th>
                        <th>Телефон</th>
                        <th>Работает с</th>
                        <th>Уволен с</th>
                        <th>Комментарий</th>
                    </tr>
                    <tr class="border-b border-border bg-muted">
                        <td class="px-2 py-2 align-top">
                            <x-ui.filter-input
                                type="number"
                                name="search_id"
                                value="{{ request('search_id') }}"
                                size="sm"
                                class="w-full min-w-[5rem]"
                                placeholder="ID"
                            />
                        </td>
                        <td class="min-w-[5.5rem] px-2 py-2 align-top">
                            <div class="multiselect multiselect-compact" data-name="city_id">
                                <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                                    <span class="multiselect-text text-muted-foreground">Город</span>
                                    <span class="ml-auto shrink-0">▼</span>
                                </div>
                                <div class="multiselect-dropdown">
                                    <div class="multiselect-actions">
                                        <button type="button" onclick="selectAll(event, this)">выбрать все</button>
                                        <button type="button" onclick="deselectAll(event, this)">снять все</button>
                                    </div>
                                    @foreach ($cities as $city)
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="city_id[]"
                                                value="{{ $city->city_id }}"
                                                {{ in_array((string) $city->city_id, array_map('strval', (array) request('city_id', [])), true) ? 'checked' : '' }}
                                                onchange="updateMultiselect(this)"
                                            >
                                            {{ $city->city_name }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                        <td class="min-w-[5rem] px-2 py-2 align-top">
                            <x-ui.filter-input
                                type="text"
                                name="search"
                                value="{{ request('search') }}"
                                size="sm"
                                placeholder="ФИО"
                            />
                        </td>
                        <td class="min-w-[5.5rem] px-2 py-2 align-top">
                            <div class="multiselect multiselect-compact" data-name="role">
                                <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                                    <span class="multiselect-text text-muted-foreground">Роль</span>
                                    <span class="ml-auto shrink-0">▼</span>
                                </div>
                                <div class="multiselect-dropdown">
                                    <div class="multiselect-actions">
                                        <button type="button" onclick="selectAll(event, this)">выбрать все</button>
                                        <button type="button" onclick="deselectAll(event, this)">снять все</button>
                                    </div>
                                    @foreach ($roles as $role)
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="role[]"
                                                value="{{ $role->role_code }}"
                                                {{ in_array($role->role_code, (array) request('role', []), true) ? 'checked' : '' }}
                                                onchange="updateMultiselect(this)"
                                            >
                                            {{ $role->role_name }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                        <td class="min-w-[5rem] px-2 py-2 align-top">
                            <x-ui.filter-input
                                type="text"
                                name="search_phone"
                                value="{{ request('search_phone') }}"
                                size="sm"
                                placeholder="Телефон"
                            />
                        </td>
                        <td class="px-1 py-2"></td>
                        <td class="px-1 py-2"></td>
                        <td class="min-w-[6rem] px-2 py-2 align-top">
                            <x-ui.filter-input
                                type="text"
                                name="search_note"
                                value="{{ request('search_note') }}"
                                size="sm"
                                placeholder="Коммент."
                            />
                        </td>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr
                            class="cursor-pointer"
                            style="{{ $employee->user_fired_at ? 'opacity: 0.5;' : '' }}"
                            onclick="window.location='{{ route('hr.edit', $employee->user_id) }}'"
                        >
                            <td>{{ $employee->user_id }}</td>
                            <td>{{ $employee->hasAllCitiesAccess() ? 'все города' : $employee->cities->pluck('city_name')->join(', ') }}</td>
                            <td>
                                <strong>{{ $employee->user_name }}</strong>
                            </td>
                            <td>{{ $employee->roles->pluck('role_name')->join(', ') }}</td>
                            <td>{{ $employee->user_phone ? '+7' . $employee->user_phone : '—' }}</td>
                            <td>{{ $employee->user_hired_at?->format('d.m.Y') ?? '—' }}</td>
                            <td>
                                @if ($employee->user_fired_at)
                                    <span class="text-destructive">{{ $employee->user_fired_at->format('d.m.Y') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="max-w-[200px]">
                                <span class="line-clamp-2" title="{{ $employee->user_note }}">{{ \Illuminate\Support\Str::limit($employee->user_note, 50) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-8 text-center text-muted-foreground" colspan="8">
                                Сотрудники не найдены
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if ($employees->hasPages())
                <div class="p-4">{{ $employees->withQueryString()->links() }}</div>
            @endif
        </form>
    </x-ui.card>
@endsection

@push('scripts')
    <script>
        function clearOrdersMultiselectDropdownLayout(dropdown) {
            if (!dropdown) {
                return;
            }
            ['position', 'top', 'left', 'right', 'bottom', 'minWidth', 'maxWidth', 'maxHeight', 'width', 'zIndex'].forEach(function (k) {
                dropdown.style[k] = '';
            });
            delete dropdown.dataset.ordersFixed;
        }

        function positionOrdersMultiselectDropdownFixed(selectedEl, dropdown) {
            const r = selectedEl.getBoundingClientRect();
            const margin = 4;
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const minW = Math.max(Math.ceil(r.width), 256);
            const maxW = Math.min(384, Math.floor(vw * 0.92) - 16);
            let left = r.left;
            if (left + minW > vw - 8) {
                left = Math.max(8, vw - minW - 8);
            }
            const spaceBelow = vh - r.bottom - margin - 8;
            const spaceAbove = r.top - margin - 8;
            const preferUp = spaceBelow < 180 && spaceAbove > spaceBelow;

            dropdown.style.position = 'fixed';
            dropdown.style.left = left + 'px';
            dropdown.style.right = 'auto';
            dropdown.style.minWidth = Math.min(minW, maxW) + 'px';
            dropdown.style.maxWidth = maxW + 'px';
            dropdown.style.width = 'auto';
            dropdown.style.zIndex = '6000';

            if (preferUp) {
                const maxH = Math.min(300, Math.max(120, spaceAbove));
                dropdown.style.top = 'auto';
                dropdown.style.bottom = vh - r.top + margin + 'px';
                dropdown.style.maxHeight = maxH + 'px';
            } else {
                const maxH = Math.min(300, Math.max(120, spaceBelow));
                dropdown.style.top = r.bottom + margin + 'px';
                dropdown.style.bottom = 'auto';
                dropdown.style.maxHeight = maxH + 'px';
            }
            dropdown.dataset.ordersFixed = '1';
        }

        function repositionOpenOrdersTableMultiselect() {
            document.querySelectorAll('.multiselect-dropdown.open').forEach(function (open) {
                if (!open.closest('.orders-sticky-table, .table-sticky-clone')) {
                    return;
                }
                const sel = open.previousElementSibling;
                if (sel && sel.classList.contains('multiselect-selected')) {
                    positionOrdersMultiselectDropdownFixed(sel, open);
                }
            });
        }

        function closeAllMultiselectDropdowns() {
            document.querySelectorAll('.multiselect-dropdown.open').forEach(function (d) {
                d.classList.remove('open');
                clearOrdersMultiselectDropdownLayout(d);
            });
        }

        function toggleMultiselect(element) {
            const dropdown = element.nextElementSibling;
            const wasOpen = dropdown.classList.contains('open');
            closeAllMultiselectDropdowns();
            if (!wasOpen) {
                dropdown.classList.add('open');
                if (
                    element.closest('.orders-sticky-table, .table-sticky-clone') &&
                    dropdown.classList.contains('multiselect-dropdown')
                ) {
                    positionOrdersMultiselectDropdownFixed(element, dropdown);
                }
            }
        }

        function updateMultiselect(checkbox) {
            const multiselect = checkbox.closest('.multiselect');
            const checkboxes = multiselect.querySelectorAll('input[type="checkbox"]');
            const selected = Array.from(checkboxes).filter((cb) => cb.checked);
            const textElement = multiselect.querySelector('.multiselect-text');
            const total = checkboxes.length;
            if (!textElement) return;
            if (!textElement.dataset.placeholder) {
                textElement.dataset.placeholder = textElement.textContent;
            }
            if (selected.length === 0 || selected.length === total) {
                textElement.textContent = textElement.dataset.placeholder;
                textElement.classList.add('text-muted-foreground');
            } else if (selected.length === 1) {
                textElement.textContent = selected[0].nextSibling.textContent.trim();
                textElement.classList.remove('text-muted-foreground');
            } else if (selected.length === 2) {
                textElement.textContent = selected.map((s) => s.nextSibling.textContent.trim()).join(', ');
                textElement.classList.remove('text-muted-foreground');
            } else {
                const firstTwo = selected
                    .slice(0, 2)
                    .map((s) => s.nextSibling.textContent.trim())
                    .join(', ');
                textElement.textContent = `${firstTwo} +${selected.length - 2}`;
                textElement.classList.remove('text-muted-foreground');
            }
        }

        function selectAll(e, button) {
            if (e) e.preventDefault();
            const multiselect = button.closest('.multiselect');
            const checkboxes = multiselect.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach((cb) => (cb.checked = true));
            if (checkboxes[0]) {
                updateMultiselect(checkboxes[0]);
            }
        }

        function deselectAll(e, button) {
            if (e) e.preventDefault();
            const multiselect = button.closest('.multiselect');
            const checkboxes = multiselect.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach((cb) => (cb.checked = false));
            if (checkboxes[0]) {
                updateMultiselect(checkboxes[0]);
            }
            const form = button.closest('form');
            if (form) form.submit();
        }

        window.toggleMultiselect = toggleMultiselect;
        window.updateMultiselect = updateMultiselect;

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.multiselect')) {
                closeAllMultiselectDropdowns();
            }
        });

        document.addEventListener('scroll', repositionOpenOrdersTableMultiselect, true);
        window.addEventListener('resize', repositionOpenOrdersTableMultiselect);

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.multiselect').forEach((multiselect) => {
                const firstCheckbox = multiselect.querySelector('input[type="checkbox"]');
                if (firstCheckbox) {
                    updateMultiselect(firstCheckbox);
                }
            });

            const form = document.getElementById('filtersForm');
            if (!form) return;

            form.querySelectorAll('[data-filter-input] .filter-search-btn').forEach((btn) => {
                btn.addEventListener('click', function () {
                    form.submit();
                });
            });

            const inputs = form.querySelectorAll('input, select');
            inputs.forEach((input) => {
                if (input.type === 'checkbox') {
                    input.addEventListener('change', function () {
                        form.submit();
                    });
                } else if (input.type === 'date' || input.type === 'number' || input.type === 'text') {
                    input.addEventListener('blur', function () {
                        if (this.value !== this.defaultValue) {
                            form.submit();
                        }
                    });
                    input.addEventListener('keypress', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            form.submit();
                        }
                    });
                }
            });
        });
    </script>
@endpush
