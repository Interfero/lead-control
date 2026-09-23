@extends('layouts.app')

@section('title', 'Касса')

@section('content')
    @php
        $cfmLegendItems = [
            ['warning_swatch' => true, 'text' => 'Такой фон у строки — операция не проведена'],
            ['badge' => 'inflow', 'label' => 'Приход', 'text' => 'Поступление денег в кассу (зелёный)'],
            ['badge' => 'outflow', 'label' => 'Расход', 'text' => 'Выбытие денег из кассы (красный)'],
        ];
    @endphp

    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'Касса', 'url' => null],
                ]"
            />
            <x-legend :items="$cfmLegendItems" />
        </div>
        <div class="flex max-w-full shrink-0 flex-wrap items-center justify-end gap-2">
            @if(auth()->user()->hasAnyRole(['developer', 'call_center', 'senior_dispatcher', 'branch_head', 'regional_director', 'senior_manager']))
                <x-ui.button
                    href="{{ route('cfm.create', ['type' => 'client_refund']) }}"
                    class="gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm sm:px-4"
                >
                    {!! icon('add') !!}
                    Возврат клиенту
                </x-ui.button>
            @endif
            @if(auth()->user()->hasAnyRole(['developer', 'branch_head', 'regional_director', 'senior_manager']))
                <x-ui.button
                    href="{{ route('cfm.create', ['type' => 'party_payment']) }}"
                    class="gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm sm:px-4"
                >
                    {!! icon('add') !!}
                    Оплата партов
                </x-ui.button>
                <x-ui.button
                    href="{{ route('cfm.create', ['type' => 'partner_expense']) }}"
                    class="gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm sm:px-4"
                >
                    {!! icon('add') !!}
                    Расход партнерам
                </x-ui.button>
                <x-ui.button
                    href="{{ route('cfm.create', ['type' => 'level_expense']) }}"
                    class="gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm sm:px-4"
                >
                    {!! icon('add') !!}
                    Расход Уровень
                </x-ui.button>
                <x-ui.button
                    href="{{ route('cfm.create', ['type' => 'incassation']) }}"
                    class="gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm sm:px-4"
                >
                    {!! icon('add') !!}
                    Инкассация
                </x-ui.button>
                <x-ui.button
                    href="{{ route('cfm.create', ['type' => 'incas_commission']) }}"
                    class="gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm sm:px-4"
                >
                    {!! icon('add') !!}
                    Комиссия инкаса
                </x-ui.button>
            @endif
            @if((!auth()->user()->hasAnyRole(['general_director', 'call_center', 'senior_dispatcher']) || auth()->user()->hasRole('developer')))
            <x-ui.button
                href="{{ route('cfm.create', ['type' => 'expense']) }}"
                class="gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm sm:px-4"
            >
                {!! icon('add') !!}
                Расход
            </x-ui.button>
            <x-ui.button
                href="{{ route('cfm.create', ['type' => 'income']) }}"
                class="gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm sm:px-4"
            >
                {!! icon('add') !!}
                Приход
            </x-ui.button>
            <x-ui.button
                href="{{ route('cfm.create', ['type' => 'transfer']) }}"
                class="gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm sm:px-4"
            >
                {!! icon('add') !!}
                Перемещение
            </x-ui.button>
            @endif
            <x-ui.button
                href="{{ route('cfm.summary') }}"
                class="gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm sm:px-4"
            >
                {!! icon('report') !!}
                Отчёт по Кассе
            </x-ui.button>
        </div>
    </div>

    <x-ui.card padding="none" class="mb-4 min-w-0 max-w-full">
        <form method="GET" action="{{ route('cfm.index') }}" id="filtersForm" class="min-w-0 max-w-full">
            <input type="hidden" name="closed_by_me" value="{{ request('closed_by_me', '0') }}" id="closedByMeHidden">
            <input type="hidden" name="hide_auto" value="{{ request('hide_auto', '0') }}" id="hideAutoHidden">

            <div class="orders-filters-sticky flex flex-wrap items-end gap-4 border-b border-border px-4 py-2">
                <x-ui.filter-dates
                    :date-from="$dateFrom"
                    :date-to="$dateTo"
                    :closed-from="request('closed_from')"
                    :closed-to="request('closed_to')"
                />
                <div class="flex flex-wrap items-center gap-4">
                    <label class="filter-checkbox flex cursor-pointer items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            id="closedByMeCheckbox"
                            {{ request('closed_by_me', '0') === '1' ? 'checked' : '' }}
                            onchange="toggleClosedByMe()"
                        >
                        <span>Проведённые вами</span>
                    </label>
                    <label class="filter-checkbox flex cursor-pointer items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            id="hideAutoCheckbox"
                            {{ request('hide_auto', '0') === '1' ? 'checked' : '' }}
                            onchange="toggleHideAuto()"
                        >
                        <span>Скрыть автоматические</span>
                    </label>
                </div>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <x-ui.button
                        href="{{ route('cfm.index') }}"
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

            {{-- Одна таблица в .orders-table-scroll + клон thead — initOrdersStickyTable в app.js (как список заказов) --}}
            <table class="table table-sticky orders-sticky-table w-full min-w-0 text-sm">
                    <thead>
                        <tr>
                            <th class="w-[4.5rem]">
                                <a
                                    href="?{{ http_build_query(array_merge(request()->all(), ['sort' => 'cfm_id', 'dir' => request('sort') == 'cfm_id' && request('dir') == 'asc' ? 'desc' : 'asc'])) }}"
                                >ID {{ request('sort') == 'cfm_id' ? (request('dir') == 'asc' ? '▲' : '▼') : '' }}</a>
                            </th>
                            <th>
                                <a
                                    href="?{{ http_build_query(array_merge(request()->all(), ['sort' => 'cfm_created_at', 'dir' => request('sort') == 'cfm_created_at' && request('dir') == 'asc' ? 'desc' : 'asc'])) }}"
                                >Создано {{ request('sort') == 'cfm_created_at' ? (request('dir') == 'asc' ? '▲' : '▼') : '' }}</a>
                            </th>
                            <th>
                                <a
                                    href="?{{ http_build_query(array_merge(request()->all(), ['sort' => 'cfm_closed_at', 'dir' => request('sort') == 'cfm_closed_at' && request('dir') == 'asc' ? 'desc' : 'asc'])) }}"
                                >Проведено {{ request('sort') == 'cfm_closed_at' ? (request('dir') == 'asc' ? '▲' : '▼') : '' }}</a>
                            </th>
                            <th>Тип</th>
                            <th class="text-right">
                                <a
                                    href="?{{ http_build_query(array_merge(request()->all(), ['sort' => 'amount_cfm', 'dir' => request('sort') == 'amount_cfm' && request('dir') == 'asc' ? 'desc' : 'asc'])) }}"
                                >Сумма {{ request('sort') == 'amount_cfm' ? (request('dir') == 'asc' ? '▲' : '▼') : '' }}</a>
                            </th>
                            <th>Статья ДДС</th>
                            <th>Вид операции</th>
                            <th>Описание</th>
                            <th>Город</th>
                            <th>Создал</th>
                            <th>Провёл</th>
                        </tr>
                        <tr class="border-b border-border bg-muted">
                            <td class="px-2 py-2 align-top">
                                <x-ui.filter-input
                                    type="number"
                                    name="cfm_id"
                                    value="{{ request('cfm_id') }}"
                                    size="sm"
                                    class="w-full min-w-[5rem]"
                                    placeholder="ID"
                                />
                            </td>
                            <td class="px-1 py-2"></td>
                            <td class="px-1 py-2"></td>
                            <td class="min-w-[5rem] px-2 py-2 align-top">
                                <div class="multiselect multiselect-compact" data-name="cfm_cat_group">
                                    <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                                        <span class="multiselect-text text-muted-foreground">Тип</span>
                                        <span class="ml-auto shrink-0">▼</span>
                                    </div>
                                    <div class="multiselect-dropdown">
                                        <label><input type="checkbox" name="cfm_cat_group[]" value="inflows" {{ in_array('inflows', (array) request('cfm_cat_group', [])) ? 'checked' : '' }} onchange="updateMultiselect(this)"> Приход</label>
                                        <label><input type="checkbox" name="cfm_cat_group[]" value="outflows" {{ in_array('outflows', (array) request('cfm_cat_group', [])) ? 'checked' : '' }} onchange="updateMultiselect(this)"> Расход</label>
                                    </div>
                                </div>
                            </td>
                            <td class="px-1 py-2"></td>
                            <td class="min-w-[6rem] px-2 py-2 align-top">
                                <div class="multiselect multiselect-compact" data-name="cfm_cat_id">
                                    <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                                        <span class="multiselect-text text-muted-foreground">Статья</span>
                                        <span class="ml-auto shrink-0">▼</span>
                                    </div>
                                    <div class="multiselect-dropdown">
                                        <div class="multiselect-actions">
                                            <button type="button" onclick="selectAll(event, this)">выбрать все</button>
                                            <button type="button" onclick="deselectAll(event, this)">снять все</button>
                                        </div>
                                        @foreach ($categories as $cat)
                                            <label title="{{ $cat->cfm_cat_adds }}">
                                                <input
                                                    type="checkbox"
                                                    name="cfm_cat_id[]"
                                                    value="{{ $cat->cfm_cat_id }}"
                                                    {{ in_array($cat->cfm_cat_id, (array) request('cfm_cat_id', [])) ? 'checked' : '' }}
                                                    onchange="updateMultiselect(this)"
                                                >
                                                {{ $cat->cfm_cat_name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                            <td class="px-1 py-2"></td>
                            <td class="px-1 py-2"></td>
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
                                                    {{ in_array($city->city_id, (array) request('city_id', [])) ? 'checked' : '' }}
                                                    onchange="updateMultiselect(this)"
                                                >
                                                {{ $city->city_name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                            <td class="px-1 py-2"></td>
                            <td class="px-1 py-2"></td>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($operations as $op)
                            <tr
                                class="cursor-pointer {{ !$op->cfm_closed_at ? 'table-row-warning' : '' }}"
                                onclick="window.location='{{ route('cfm.show', $op->cfm_id) }}'"
                            >
                                <td>{{ $op->cfm_id }}</td>
                                <td>
                                    <div class="flex flex-col leading-snug">
                                        <span class="text-sm">{{ $op->cfm_created_at->format('d.m.Y') }}</span>
                                        <span class="text-xs text-muted-foreground">{{ $op->cfm_created_at->format('H:i') }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if ($op->cfm_closed_at)
                                        <div class="flex flex-col leading-snug">
                                            <span class="text-sm">{{ $op->cfm_closed_at->format('d.m.Y') }}</span>
                                            <span class="text-xs text-muted-foreground">{{ $op->cfm_closed_at->format('H:i') }}</span>
                                        </div>
                                    @else
                                        <span class="text-amber-700 dark:text-amber-500">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($op->category->cfm_cat_group === 'inflows')
                                        <span class="badge badge-inflow">Приход</span>
                                    @else
                                        <span class="badge badge-outflow">Расход</span>
                                    @endif
                                </td>
                                <td class="text-right font-medium">
                                    <span class="{{ $op->category->cfm_cat_group === 'inflows' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $op->category->cfm_cat_group === 'inflows' ? '+' : '-' }}{{ number_format($op->amount_cfm, 0, ',', ' ') }}
                                    </span>
                                    @if($op->amount_from_master !== null)
                                        <div class="text-[0.7rem] font-normal text-muted-foreground">
                                            касса {{ number_format($op->amount_cfm, 0, ',', ' ') }}
                                            · мастер {{ number_format($op->amount_from_master, 0, ',', ' ') }}
                                        </div>
                                    @endif
                                </td>
                                <td title="{{ $op->category->cfm_cat_adds }}">
                                    {{ $op->category->cfm_cat_name }}
                                </td>
                                <td>
                                    @switch ($op->category->cfm_cat_activities)
                                        @case ('operating')
                                            Операционная

                                            @break
                                        @case ('investing')
                                            Инвестиционная

                                            @break
                                        @case ('financing')
                                            Финансовая

                                            @break
                                        @case ('technical')
                                            Техническая

                                            @break
                                        @default
                                            {{ $op->category->cfm_cat_activities }}
                                    @endswitch
                                </td>
                                <td class="max-w-[12.5rem]">
                                    <span
                                        style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"
                                        title="{{ $op->cfm_adds }}"
                                    >{{ \Illuminate\Support\Str::limit($op->cfm_adds, 100) }}</span>
                                </td>
                                <td>{{ $op->city->city_name }}</td>
                                <td>{{ $op->createdBy->user_name ?? '—' }}</td>
                                <td>{{ $op->closedBy->user_name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="py-8 text-center text-muted-foreground">
                                    Операции не найдены
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
            </table>

            @if ($operations->total() > 0)
                <div
                    class="flex flex-col gap-2 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Показано
                        @if ($operations->firstItem())
                            <span class="font-medium text-foreground">{{ $operations->firstItem() }}</span>
                            –
                            <span class="font-medium text-foreground">{{ $operations->lastItem() }}</span>
                        @else
                            {{ $operations->count() }}
                        @endif
                        из
                        <span class="font-medium text-foreground">{{ $operations->total() }}</span>
                        @if (!$operations->hasPages())
                            <span class="text-muted-foreground">(страница 1 из 1)</span>
                        @endif
                    </p>
                    <div class="flex justify-center sm:justify-end">
                        {{ $operations->withQueryString()->links('vendor.pagination.leadcontrol') }}
                    </div>
                </div>
            @endif
        </form>
    </x-ui.card>
@endsection

@push('scripts')
    <script>
        function toggleClosedByMe() {
            const checkbox = document.getElementById('closedByMeCheckbox');
            const hidden = document.getElementById('closedByMeHidden');
            hidden.value = checkbox.checked ? '1' : '0';
            document.getElementById('filtersForm').submit();
        }

        function toggleHideAuto() {
            const checkbox = document.getElementById('hideAutoCheckbox');
            const hidden = document.getElementById('hideAutoHidden');
            hidden.value = checkbox.checked ? '1' : '0';
            document.getElementById('filtersForm').submit();
        }

        function clearOrdersMultiselectDropdownLayout(dropdown) {
            if (!dropdown) {
                return;
            }
            ['position', 'top', 'left', 'right', 'bottom', 'minWidth', 'maxWidth', 'maxHeight', 'width', 'zIndex'].forEach(
                function (k) {
                    dropdown.style[k] = '';
                }
            );
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
            const selected = Array.from(checkboxes).filter(function (cb) {
                return cb.checked;
            });
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
                textElement.textContent = selected
                    .map(function (s) {
                        return s.nextSibling.textContent.trim();
                    })
                    .join(', ');
                textElement.classList.remove('text-muted-foreground');
            } else {
                const firstTwo = selected
                    .slice(0, 2)
                    .map(function (s) {
                        return s.nextSibling.textContent.trim();
                    })
                    .join(', ');
                textElement.textContent = firstTwo + ' +' + (selected.length - 2);
                textElement.classList.remove('text-muted-foreground');
            }
        }

        window.updateMultiselect = updateMultiselect;

        function selectAll(e, button) {
            if (e) e.preventDefault();
            const multiselect = button.closest('.multiselect');
            const checkboxes = multiselect.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(function (cb) {
                cb.checked = true;
            });
            if (checkboxes[0]) {
                updateMultiselect(checkboxes[0]);
            }
        }

        function deselectAll(e, button) {
            if (e) e.preventDefault();
            const multiselect = button.closest('.multiselect');
            const checkboxes = multiselect.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(function (cb) {
                cb.checked = false;
            });
            if (checkboxes[0]) {
                updateMultiselect(checkboxes[0]);
            }
            const form = button.closest('form');
            if (form) form.submit();
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.multiselect')) {
                closeAllMultiselectDropdowns();
            }
        });

        document.addEventListener('scroll', repositionOpenOrdersTableMultiselect, true);
        window.addEventListener('resize', repositionOpenOrdersTableMultiselect);

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.multiselect').forEach(function (multiselect) {
                const firstCheckbox = multiselect.querySelector('input[type="checkbox"]');
                if (firstCheckbox) {
                    updateMultiselect(firstCheckbox);
                }
            });

            const form = document.getElementById('filtersForm');
            if (!form) return;

            form.querySelectorAll('[data-filter-input] .filter-search-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    form.submit();
                });
            });

            const inputs = form.querySelectorAll('input, select');
            inputs.forEach(function (input) {
                if (input.type === 'checkbox' && input.name) {
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

            document.querySelectorAll('.table th a').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const url = new URL(this.href);
                    const formData = new FormData(form);
                    for (const [key, value] of formData.entries()) {
                        if (value && key !== '_token') {
                            if (key.endsWith('[]')) {
                                const allValues = formData.getAll(key);
                                allValues.forEach(function (val) {
                                    if (val) url.searchParams.append(key, val);
                                });
                            } else if (!url.searchParams.has(key)) {
                                url.searchParams.set(key, value);
                            }
                        }
                    }
                    window.location.href = url.toString();
                });
            });
        });
    </script>
@endpush
