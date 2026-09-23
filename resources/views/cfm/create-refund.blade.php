@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
    <div class="mb-4">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Касса', 'url' => route('cfm.index')],
                ['label' => $pageTitle, 'url' => null],
            ]"
        />
    </div>

    <div class="order-show-page">
        <div class="cfm-show-layout">
            <div>
                <div class="card p-4">
                    <h3 class="mb-2 text-base font-semibold">{{ $pageTitle }}</h3>
                    <p class="mb-4 text-sm text-muted-foreground">
                        Как возврат в Уровне: в кассу уходит только доля «с кассы», доля «с мастера» учитывается отдельно.
                        Операция сразу проводится — баланс города уменьшается на сумму с кассы.
                    </p>

                    <form method="POST" action="{{ route('cfm.store') }}" id="cfmRefundForm" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="director_type" value="client_refund">

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">№ заказа <span class="text-destructive">*</span></label>
                                <input
                                    type="number"
                                    name="related_order_id"
                                    id="refundOrderId"
                                    class="form-input"
                                    value="{{ old('related_order_id', $prefillOrderId) }}"
                                    required
                                    min="1"
                                    placeholder="Например 1234"
                                >
                                @error('related_order_id')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group" style="flex: 1; display: flex; align-items: flex-end;">
                                <button type="button" id="refundLoadOrderBtn" class="btn btn-secondary btn-order-compact w-full">
                                    Подставить по заказу
                                </button>
                            </div>
                        </div>

                        <div id="refundOrderMeta" class="mb-4 hidden rounded-md border border-border bg-muted/40 p-3 text-sm">
                            <div><span class="text-muted-foreground">Город:</span> <span data-meta="city">—</span></div>
                            <div><span class="text-muted-foreground">Мастер:</span> <span data-meta="master">—</span></div>
                            <div><span class="text-muted-foreground">Проведено (нетто):</span> <span data-meta="net">—</span></div>
                            <div><span class="text-muted-foreground">ЗП мастера / к сдаче:</span> <span data-meta="split">—</span></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Итого возврат клиенту, ₽</label>
                            <input type="number" id="refundTotal" class="form-input" value="{{ old('amount_from_cash', 0) + old('amount_from_master', 0) ?: '' }}" min="0" placeholder="Общая сумма">
                            <small class="text-muted-foreground">Можно ввести итог — разбивка подставится по % мастера; потом поправьте вручную.</small>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">С кассы, ₽ <span class="text-destructive">*</span></label>
                                <input type="number" name="amount_from_cash" id="refundFromCash" class="form-input" value="{{ old('amount_from_cash', 0) }}" required min="0" placeholder="0">
                                <small class="text-muted-foreground">Уменьшает баланс кассы города</small>
                                @error('amount_from_cash')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">С мастера, ₽ <span class="text-destructive">*</span></label>
                                <input type="number" name="amount_from_master" id="refundFromMaster" class="form-input" value="{{ old('amount_from_master', 0) }}" required min="0" placeholder="0">
                                <small class="text-muted-foreground">Учёт удержания с мастера (в кассу не пишется)</small>
                                @error('amount_from_master')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Город (касса) <span class="text-destructive">*</span></label>
                            <select name="city_id" id="refundCityId" class="form-input" required>
                                @if($cities->count() === 1)
                                    <option value="{{ $cities->first()->city_id }}" selected>{{ $cities->first()->city_name }}</option>
                                @else
                                    <option value="">Выберите город</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city->city_id }}" {{ (string) old('city_id') === (string) $city->city_id ? 'selected' : '' }}>
                                            {{ $city->city_name }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            @error('city_id')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Статья ДДС</label>
                            <div class="form-static">{{ $presetCategory->cfm_cat_name }}</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Комментарий <span class="text-destructive">*</span></label>
                            <textarea name="cfm_adds" class="form-input" rows="3" required placeholder="Причина возврата, № претензии ОКК…">{{ old('cfm_adds') }}</textarea>
                            @error('cfm_adds')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </form>
                </div>
            </div>

            <div class="cfm-show-sidebar">
                <div class="card p-4">
                    <div class="order-show-buttons mb-4">
                        <button type="submit" form="cfmRefundForm" class="btn btn-primary btn-order-compact">
                            {!! icon('save') !!} Провести возврат
                        </button>
                        <a href="{{ route('cfm.index') }}" class="btn btn-secondary btn-order-compact" style="grid-column: 1 / -1;">
                            {!! icon('back') !!} Назад
                        </a>
                    </div>

                    <div class="mb-4 rounded-md border border-border p-3 text-sm">
                        <div class="mb-1 font-medium">Итого к учёту</div>
                        <div>С кассы: <strong id="refundPreviewCash">0</strong> ₽</div>
                        <div>С мастера: <strong id="refundPreviewMaster">0</strong> ₽</div>
                        <div class="mt-1 border-t border-border pt-1">Всего клиенту: <strong id="refundPreviewTotal">0</strong> ₽</div>
                    </div>

                    <h3 class="order-documents-heading mb-3 border-t border-border pt-4" style="font-size: 1rem;">{!! icon('document') !!} Документы</h3>
                    <div class="cfm-sidebar-documents-only" aria-label="Документы к операции">
                        <div class="dropzone mb-3" data-dropzone="create" onclick="document.getElementById('file-documents-refund').click()">
                            <div class="dropzone-inner" style="padding: 1.25rem 1rem;">
                                <div class="dropzone-emoji" style="font-size: 1.5rem;">📄</div>
                                <div>Перетащите файлы сюда</div>
                                <div class="dropzone-inner-note">или нажмите · до 10 МБ</div>
                            </div>
                        </div>
                        <input type="file" id="file-documents-refund" class="hidden" form="cfmRefundForm" name="documents[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.odt,.txt,.csv">
                        <div id="cfm-refund-files-pending" class="cfm-files-list"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const suggestUrl = @json($suggestUrl);
    const orderInput = document.getElementById('refundOrderId');
    const totalInput = document.getElementById('refundTotal');
    const cashInput = document.getElementById('refundFromCash');
    const masterInput = document.getElementById('refundFromMaster');
    const citySelect = document.getElementById('refundCityId');
    const metaBox = document.getElementById('refundOrderMeta');
    const loadBtn = document.getElementById('refundLoadOrderBtn');

    function fmt(n) {
        return Number(n || 0).toLocaleString('ru-RU');
    }

    function updatePreview() {
        const cash = parseInt(cashInput.value || '0', 10) || 0;
        const master = parseInt(masterInput.value || '0', 10) || 0;
        document.getElementById('refundPreviewCash').textContent = fmt(cash);
        document.getElementById('refundPreviewMaster').textContent = fmt(master);
        document.getElementById('refundPreviewTotal').textContent = fmt(cash + master);
        if (!totalInput.dataset.manual) {
            totalInput.value = cash + master > 0 ? String(cash + master) : '';
        }
    }

    async function loadSuggest(applySplit) {
        const orderId = parseInt(orderInput.value || '0', 10);
        if (!orderId) {
            Toast.error('Укажите номер заказа');
            return;
        }
        const total = parseInt(totalInput.value || '0', 10) || 0;
        const qs = new URLSearchParams({ order_id: String(orderId) });
        if (total > 0) qs.set('total', String(total));

        loadBtn.disabled = true;
        try {
            const res = await fetch(suggestUrl + '?' + qs.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (!res.ok) {
                Toast.error(data.message || 'Не удалось загрузить заказ');
                return;
            }
            metaBox.classList.remove('hidden');
            metaBox.querySelector('[data-meta="city"]').textContent = data.city_name || '—';
            metaBox.querySelector('[data-meta="master"]').textContent = data.master_name || 'не назначен';
            metaBox.querySelector('[data-meta="net"]').textContent = fmt(data.net) + ' ₽';
            metaBox.querySelector('[data-meta="split"]').textContent =
                fmt(data.master_salary) + ' ₽ / ' + fmt(data.amount_to_pay) + ' ₽ (' + data.master_percent + '%)';

            if (data.city_id && citySelect.querySelector('option[value="' + data.city_id + '"]')) {
                citySelect.value = String(data.city_id);
            }
            if (applySplit) {
                if (!totalInput.value) {
                    totalInput.value = String(data.net || 0);
                }
                cashInput.value = String(data.suggest_from_cash ?? 0);
                masterInput.value = String(data.suggest_from_master ?? 0);
                totalInput.dataset.manual = '';
                updatePreview();
            }
            Toast.success('Данные заказа подставлены');
        } catch (e) {
            Toast.error('Ошибка загрузки заказа');
        } finally {
            loadBtn.disabled = false;
        }
    }

    loadBtn.addEventListener('click', function () { loadSuggest(true); });

    totalInput.addEventListener('change', async function () {
        totalInput.dataset.manual = '1';
        if (orderInput.value) {
            await loadSuggest(true);
        }
    });

    cashInput.addEventListener('input', updatePreview);
    masterInput.addEventListener('input', updatePreview);
    updatePreview();

    @if($prefillOrderId)
        loadSuggest(true);
    @endif

    const fileInput = document.getElementById('file-documents-refund');
    const pendingListEl = document.getElementById('cfm-refund-files-pending');
    const filesBuffer = [];

    function syncFilesToInput() {
        const dt = new DataTransfer();
        filesBuffer.forEach(f => dt.items.add(f));
        fileInput.files = dt.files;
    }

    function renderPendingList() {
        pendingListEl.innerHTML = '';
        filesBuffer.forEach((file, index) => {
            const row = document.createElement('div');
            row.className = 'cfm-file-item file-item';
            row.innerHTML = `<div class="cfm-file-item-body" style="flex:1"><span class="file-name">${file.name}</span>
                <span class="file-delete" data-index="${index}" title="Убрать">✕</span></div>`;
            row.querySelector('.file-delete').addEventListener('click', function () {
                filesBuffer.splice(parseInt(this.dataset.index, 10), 1);
                syncFilesToInput();
                renderPendingList();
            });
            pendingListEl.appendChild(row);
        });
    }

    fileInput.addEventListener('change', function () {
        if (this.files) {
            Array.from(this.files).forEach(f => filesBuffer.push(f));
            syncFilesToInput();
            renderPendingList();
        }
        this.value = '';
        syncFilesToInput();
    });

    document.querySelectorAll('.dropzone[data-dropzone="create"]').forEach(dropzone => {
        dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('dragover'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
        dropzone.addEventListener('drop', e => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            if (e.dataTransfer.files) {
                Array.from(e.dataTransfer.files).forEach(f => filesBuffer.push(f));
                syncFilesToInput();
                renderPendingList();
            }
        });
    });
});
</script>
@endpush
