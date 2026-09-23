@extends('layouts.app')

@section('title', 'Единое окно заказов')

@section('content')
@php
    $indicatorClass = [
        'active' => 'bg-emerald-500',
        'error' => 'bg-red-500',
        'inactive' => 'bg-amber-400',
    ];
@endphp

<div class="mx-auto max-w-[1800px] min-w-0">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="m-0 text-xl font-semibold">Единое окно заказов</h1>
            <p class="mb-0 mt-1 text-sm text-muted-foreground">Тестовая версия · только заказы · CRM1</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-md border border-border bg-muted/40 px-3 py-1.5 text-sm">
                Закрыто через окно: <strong id="desk-closed-count">{{ $closedCount }}</strong>
            </span>
            @if(auth()->user()->hasRole('developer'))
                <x-ui.button href="{{ route('desk.logs') }}" variant="secondary" size="sm">Логи</x-ui.button>
            @endif
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        @foreach($connections as $crm)
            <form method="POST" action="{{ route('desk.sync', $crm->id) }}" class="inline-flex items-center gap-2">
                @csrf
                <span
                    class="inline-flex h-2.5 w-2.5 rounded-full {{ $indicatorClass[$crm->status] ?? 'bg-gray-400' }}"
                    title="{{ $crm->last_error ?: ('sync: '.($crm->last_sync_at?->format('d.m H:i') ?? 'никогда')) }}"
                ></span>
                <span class="text-sm">{{ $crm->name }}</span>
                @if($crm->type !== 'crm2_http' && $crm->status !== 'inactive')
                    <x-ui.button type="submit" variant="secondary" size="sm">Синхронизировать</x-ui.button>
                @else
                    <span class="text-xs text-muted-foreground">скоро</span>
                @endif
            </form>
        @endforeach
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach($statusLabels as $code => $label)
            <span class="rounded-md border border-border px-2.5 py-1 text-sm">
                {{ $label }}: <strong>{{ $counts[$code] ?? 0 }}</strong>
            </span>
        @endforeach
    </div>

    <x-ui.card padding="sm" class="mb-4">
        <form method="GET" action="{{ route('desk.index') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="mb-1 block text-xs text-muted-foreground">Город</label>
                <select name="city_id" class="form-control form-control-sm" style="min-width: 10rem;">
                    <option value="">Все</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->city_id }}" @selected((string) request('city_id') === (string) $city->city_id)>
                            {{ $city->city_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted-foreground">Статус</label>
                <select name="status[]" class="form-control form-control-sm" style="min-width: 10rem;">
                    <option value="">Все</option>
                    @foreach($statusLabels as $code => $label)
                        <option value="{{ $code }}" @selected(in_array($code, (array) request('status'), true))>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted-foreground">Источник</label>
                <select name="crm_id" class="form-control form-control-sm" style="min-width: 10rem;">
                    <option value="">Все CRM</option>
                    @foreach($connections as $crm)
                        <option value="{{ $crm->id }}" @selected((string) request('crm_id') === (string) $crm->id)>{{ $crm->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted-foreground">Мастер</label>
                <input type="text" name="master" value="{{ request('master') }}" class="form-control form-control-sm" placeholder="ФИО" style="min-width: 9rem;">
            </div>
            <div>
                <label class="mb-1 block text-xs text-muted-foreground">Поиск</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="адрес / телефон / ID" style="min-width: 12rem;">
            </div>
            <x-ui.button type="submit" variant="primary" size="sm">Фильтр</x-ui.button>
            <x-ui.button href="{{ route('desk.index') }}" variant="secondary" size="sm">Сброс</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card padding="none">
        <div class="table-responsive" style="max-height: calc(100vh - 22rem); overflow: auto;">
            <table class="table orders-sticky-table text-sm">
                <thead>
                    <tr>
                        <th>Источник</th>
                        <th>ID</th>
                        <th>Статус</th>
                        <th>Адрес</th>
                        <th>Мастер</th>
                        <th>Город</th>
                        <th>Тип</th>
                        <th>Создан</th>
                        <th>Вызов</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $row)
                        <tr
                            class="cursor-pointer hover:bg-muted/60 desk-row"
                            data-id="{{ $row->id }}"
                            tabindex="0"
                        >
                            <td>{{ $row->connection?->name ?? '—' }}</td>
                            <td>{{ $row->external_id }}</td>
                            <td>
                                <span class="status-cell status-{{ $row->raw_status }}">
                                    {{ $statusLabels[$row->status] ?? $row->status }}
                                </span>
                                <div class="text-xs text-muted-foreground">{{ $rawStatusLabels[$row->raw_status] ?? $row->raw_status }}</div>
                            </td>
                            <td class="max-w-[18rem] truncate" title="{{ $row->address }}">{{ $row->address ?: '—' }}</td>
                            <td>{{ $row->master_name ?: '—' }}</td>
                            <td>{{ $row->city?->city_name ?? '—' }}</td>
                            <td>{{ $typeLabels[$row->order_type] ?? ($row->order_type ?: '—') }}</td>
                            <td class="orders-datetime-cell whitespace-nowrap">
                                {{ $row->created_at_local?->format('d.m.y H:i') ?? '—' }}
                                @if($row->timezone)
                                    <span class="text-xs text-muted-foreground">{{ $row->timezone }}</span>
                                @endif
                            </td>
                            <td class="orders-datetime-cell whitespace-nowrap">
                                {{ $row->call_at_local?->format('d.m.y H:i') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-10 text-center text-muted-foreground">
                                Нет заказов в кэше. Нажмите «Синхронизировать» для Lead Control.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
            <div class="border-t border-border px-4 py-3">
                {{ $orders->links() }}
            </div>
        @endif
    </x-ui.card>
</div>

{{-- Модалка карточки --}}
<div id="deskOrderModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
    <div class="flex max-h-[90vh] w-full max-w-[1100px] flex-col overflow-hidden rounded-lg border border-border bg-background shadow-lg">
        <div class="flex items-center justify-between border-b border-border px-4 py-3">
            <h2 class="m-0 text-lg font-semibold" id="desk-modal-title">Заказ</h2>
            <button type="button" class="btn btn-sm" id="desk-modal-close" aria-label="Закрыть">&times;</button>
        </div>
        <div class="flex-1 overflow-y-auto p-4" id="desk-modal-body">
            <p class="text-muted-foreground">Загрузка…</p>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border px-4 py-3">
            <div id="desk-modal-hint" class="text-sm text-muted-foreground"></div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button type="button" variant="secondary" size="sm" id="desk-btn-save">Сохранить</x-ui.button>
                <x-ui.button type="button" variant="primary" size="sm" id="desk-btn-close-order">Закрыть заказ</x-ui.button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('deskOrderModal');
    const body = document.getElementById('desk-modal-body');
    const title = document.getElementById('desk-modal-title');
    const hint = document.getElementById('desk-modal-hint');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let currentId = null;
    let canClose = false;

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        currentId = null;
    }

    document.getElementById('desk-modal-close')?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    function toast(msg, ok) {
        const el = document.createElement('div');
        el.className = 'fixed bottom-4 right-4 z-[60] rounded-md px-4 py-2 text-sm text-white ' + (ok ? 'bg-emerald-600' : 'bg-red-600');
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3500);
    }

    function renderOrder(payload) {
        const o = payload.order;
        const labels = payload.status_labels || {};
        const types = payload.type_labels || {};
        canClose = !!payload.can_close;
        document.getElementById('desk-btn-close-order').style.display = canClose ? '' : 'none';

        title.textContent = 'Заказ #' + o.external_id + ' · ' + (o.connection?.name || '');
        hint.textContent = payload.stale
            ? 'Данные могут быть устаревшими (CRM не ответила)'
            : ('Синхр.: ' + (o.last_synced_at || '—'));

        const opts = (payload.settable_statuses || []).map(code =>
            `<option value="${code}" ${code === o.raw_status ? 'selected' : ''}>${labels[code] || code}</option>`
        ).join('');

        const docs = Array.isArray(o.documents) ? o.documents : [];
        const docsHtml = docs.length
            ? '<ul class="mb-0 pl-4">' + docs.map(d => `<li>${d.name || ('#'+d.id)}</li>`).join('') + '</ul>'
            : '<span class="text-muted-foreground">Нет документов в кэше</span>';

        body.innerHTML = `
            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-3">
                    <div><div class="text-xs text-muted-foreground">Клиент</div><div>${o.client_name || '—'}</div></div>
                    <div><div class="text-xs text-muted-foreground">Телефон</div><div>${o.phone || '—'}</div></div>
                    <div><div class="text-xs text-muted-foreground">Адрес</div><div>${o.address || '—'}</div></div>
                    <div><div class="text-xs text-muted-foreground">Город</div><div>${o.city?.city_name || '—'}</div></div>
                    <div><div class="text-xs text-muted-foreground">Тип</div><div>${types[o.order_type] || o.order_type || '—'}</div></div>
                    <div><div class="text-xs text-muted-foreground">Мастер</div><div>${o.master_name || '—'}</div></div>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs text-muted-foreground">Статус (CRM)</label>
                        <select id="desk-f-status" class="form-control">${opts}</select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-muted-foreground">Оплачено</label>
                        <input id="desk-f-paid" type="number" min="0" class="form-control" value="${o.paid_amount ?? ''}">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-muted-foreground">Запчасти</label>
                        <input id="desk-f-parts" type="number" min="0" class="form-control" value="${o.parts_amount ?? ''}">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-muted-foreground">Новый комментарий</label>
                        <textarea id="desk-f-comment" class="form-control" rows="3" placeholder="Добавится в city_adds"></textarea>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <div class="mb-1 text-xs text-muted-foreground">Комментарии / описание</div>
                <pre class="whitespace-pre-wrap rounded-md border border-border bg-muted/30 p-3 text-sm">${(o.comments || o.description || '—')}</pre>
            </div>
            <div class="mt-4">
                <div class="mb-1 text-xs text-muted-foreground">Документы</div>
                ${docsHtml}
                <p class="mt-2 mb-0 text-xs text-muted-foreground">Загрузка файлов в тестовой версии — через карточку заказа в CRM1.</p>
            </div>
        `;
    }

    async function loadOrder(id) {
        currentId = id;
        openModal();
        body.innerHTML = '<p class="text-muted-foreground">Загрузка…</p>';
        try {
            const res = await fetch(`{{ url('/desk') }}/${id}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (res.status === 410 || data.gone) {
                toast(data.error || 'Заказ недоступен', false);
                closeModal();
                const row = document.querySelector(`.desk-row[data-id="${id}"]`);
                row?.remove();
                return;
            }
            if (!res.ok) throw new Error(data.message || data.error || 'Ошибка загрузки');
            renderOrder(data);
        } catch (e) {
            body.innerHTML = `<p class="text-red-600">${e.message}</p>`;
        }
    }

    document.querySelectorAll('.desk-row').forEach(row => {
        row.addEventListener('click', () => loadOrder(row.dataset.id));
        row.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                loadOrder(row.dataset.id);
            }
        });
    });

    document.getElementById('desk-btn-save')?.addEventListener('click', async () => {
        if (!currentId) return;
        const payload = {
            raw_status: document.getElementById('desk-f-status')?.value || null,
            paid_amount: document.getElementById('desk-f-paid')?.value === '' ? null : Number(document.getElementById('desk-f-paid').value),
            parts_amount: document.getElementById('desk-f-parts')?.value === '' ? null : Number(document.getElementById('desk-f-parts').value),
            comment: document.getElementById('desk-f-comment')?.value || null,
        };
        try {
            const res = await fetch(`{{ url('/desk') }}/${currentId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!data.ok) throw new Error(data.message || 'Ошибка');
            toast(data.message || 'Сохранено', true);
            renderOrder({ ...data, order: data.order, settable_statuses: Array.from(document.querySelectorAll('#desk-f-status option')).map(o => o.value), status_labels: {}, type_labels: {}, can_close: canClose, stale: false });
            // проще перезагрузить карточку
            await loadOrder(currentId);
        } catch (e) {
            toast(e.message, false);
        }
    });

    document.getElementById('desk-btn-close-order')?.addEventListener('click', async () => {
        if (!currentId || !canClose) return;
        if (!confirm('Закрыть заказ как «Готов» в CRM1?')) return;
        try {
            const res = await fetch(`{{ url('/desk') }}/${currentId}/close`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json();
            if (!data.ok) throw new Error(data.message || 'Ошибка закрытия');
            toast(data.message || 'Закрыто', true);
            if (data.closed_count != null) {
                const c = document.getElementById('desk-closed-count');
                if (c) c.textContent = data.closed_count;
            }
            document.querySelector(`.desk-row[data-id="${currentId}"]`)?.remove();
            closeModal();
        } catch (e) {
            toast(e.message, false);
        }
    });
})();
</script>
@endpush
