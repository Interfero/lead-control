@extends('layouts.app')

@section('title', 'Претензии')

@section('content')
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'Претензии', 'url' => null],
                ]"
            />
        </div>
        @if (auth()->user()->hasAnyRole(['developer', 'call_center']))
            <div class="shrink-0">
                <x-ui.button
                    href="{{ route('complaints.create') }}"
                    class="gap-2 rounded-md px-4 py-1.5 text-sm"
                >
                    {!! icon('add') !!}
                    Создать претензию
                </x-ui.button>
            </div>
        @endif
    </div>

    <x-ui.card padding="none" class="mb-4 min-w-0 max-w-full">
        <form method="GET" action="{{ route('complaints.index') }}" id="filtersForm" class="min-w-0 max-w-full">
            <div class="orders-filters-sticky flex flex-wrap items-end gap-4 border-b border-border px-4 py-2">
                <x-ui.filter-dates
                    :date-from="$dateFrom"
                    :date-to="$dateTo"
                    :show-closed-dates="false"
                />
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <x-ui.button
                        href="{{ route('complaints.index') }}"
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
                        <th style="width: 72px;">ID</th>
                        <th>Город</th>
                        <th>Клиент</th>
                        <th>Заказ</th>
                        <th>Тип</th>
                        <th>Описание</th>
                        <th>Статус</th>
                        <th>Создана</th>
                        <th>Автор</th>
                    </tr>
                    <tr class="border-b border-border bg-muted">
                        <td class="px-2 py-2 align-top">
                            <x-ui.filter-input
                                type="number"
                                name="search_id"
                                value="{{ request('search_id') }}"
                                size="sm"
                                class="w-full max-w-[4.5rem] min-w-0"
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
                                                {{ in_array($city->city_id, (array) request('city_id', [])) ? 'checked' : '' }}
                                                onchange="updateMultiselect(this)"
                                            >
                                            {{ $city->city_name }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                        <td class="min-w-[6rem] px-2 py-2 align-top">
                            <x-ui.filter-input
                                type="text"
                                name="search"
                                value="{{ request('search') }}"
                                size="sm"
                                placeholder="Имя, ID…"
                            />
                        </td>
                        <td class="min-w-[4.5rem] px-2 py-2 align-top">
                            <x-ui.filter-input
                                type="number"
                                name="search_order"
                                value="{{ request('search_order') }}"
                                size="sm"
                                class="w-full max-w-[5rem] min-w-0"
                                placeholder="№"
                            />
                        </td>
                        <td class="min-w-[7rem] max-w-[14rem] px-2 py-2 align-top">
                            <div class="multiselect multiselect-compact" data-name="type">
                                <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                                    <span class="multiselect-text text-muted-foreground">Тип</span>
                                    <span class="ml-auto shrink-0">▼</span>
                                </div>
                                <div class="multiselect-dropdown">
                                    <div class="multiselect-actions">
                                        <button type="button" onclick="selectAll(event, this)">выбрать все</button>
                                        <button type="button" onclick="deselectAll(event, this)">снять все</button>
                                    </div>
                                    @foreach (\App\Models\Complaint::TYPES as $code => $label)
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="type[]"
                                                value="{{ $code }}"
                                                {{ in_array($code, (array) request('type', [])) ? 'checked' : '' }}
                                                onchange="updateMultiselect(this)"
                                            >
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                        <td class="px-1 py-2"></td>
                        <td class="min-w-[5.5rem] px-2 py-2 align-top">
                            <div class="multiselect multiselect-compact" data-name="status">
                                <div class="multiselect-selected" onclick="toggleMultiselect(this)">
                                    <span class="multiselect-text text-muted-foreground">Статус</span>
                                    <span class="ml-auto shrink-0">▼</span>
                                </div>
                                <div class="multiselect-dropdown">
                                    <div class="multiselect-actions">
                                        <button type="button" onclick="selectAll(event, this)">выбрать все</button>
                                        <button type="button" onclick="deselectAll(event, this)">снять все</button>
                                    </div>
                                    @foreach (\App\Models\Complaint::STATUSES as $code => $label)
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="status[]"
                                                value="{{ $code }}"
                                                {{ in_array($code, (array) request('status', [])) ? 'checked' : '' }}
                                                onchange="updateMultiselect(this)"
                                            >
                                            {{ $label }}
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
                    @forelse ($complaints as $complaint)
                        <tr
                            class="cursor-pointer"
                            onclick="window.location='{{ route('complaints.show', $complaint->complaint_id) }}'"
                        >
                            <td>{{ $complaint->complaint_id }}</td>
                            <td>{{ $complaint->city->city_name ?? '—' }}</td>
                            <td>{{ $complaint->person->person_name ?? '—' }}</td>
                            <td>
                                @if ($complaint->order_id)
                                    <a
                                        href="{{ route('orders.show', $complaint->order_id) }}"
                                        class="text-primary hover:underline"
                                        onclick="event.stopPropagation();"
                                    >
                                        #{{ $complaint->order_id }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $complaint->type_label }}</td>
                            <td class="max-w-[250px]">
                                <span class="line-clamp-2">
                                    {{ \Illuminate\Support\Str::limit($complaint->complaint_text, 80) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-complaint-{{ $complaint->complaint_status }}">
                                    {{ $complaint->status_label }}
                                </span>
                            </td>
                            <td>{{ $complaint->complaint_created_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $complaint->createdBy?->user_name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-muted-foreground">
                                Претензий не найдено
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($complaints->total() > 0)
                <div
                    class="flex flex-col gap-2 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Показано
                        @if ($complaints->firstItem())
                            <span class="font-medium text-foreground">{{ $complaints->firstItem() }}</span>
                            –
                            <span class="font-medium text-foreground">{{ $complaints->lastItem() }}</span>
                        @else
                            {{ $complaints->count() }}
                        @endif
                        из
                        <span class="font-medium text-foreground">{{ $complaints->total() }}</span>
                        @if (!$complaints->hasPages())
                            <span class="text-muted-foreground">(страница 1 из 1)</span>
                        @endif
                    </p>
                    <div class="flex justify-center sm:justify-end">
                        {{ $complaints->withQueryString()->links('vendor.pagination.leadcontrol') }}
                    </div>
                </div>
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
        });
    </script>
@endpush
