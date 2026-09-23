@extends('layouts.app')

@section('title', 'Кассовая операция #' . $operation->cfm_id)

@section('content')
    @php
        $isOrderIncome = $operation->category->cfm_cat_name === 'Поступление с Заказов';
        $cfmBreadcrumbLabel = 'Операция №' . $operation->cfm_id;
        if ($operation->cfm_closed_at && $cfmClosedAtForCity) {
            $cfmBreadcrumbLabel .= ' (Проведено ' . $cfmClosedAtForCity->format('d.m.Y H:i') . ' / ' . ($operation->closedBy->user_name ?? '—') . ')';
        }
    @endphp
    <div class="mb-4">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Касса', 'url' => route('cfm.index')],
                ['label' => $cfmBreadcrumbLabel, 'url' => null],
            ]"
        />
    </div>

    <div class="order-show-page">
        <div class="cfm-show-layout">
            {{-- Левая колонка: реквизиты --}}
            <div>
                <div class="card p-4">
                    <h3 class="mb-4 text-base font-semibold">Реквизиты операции</h3>

                    @if(! $canEditCfm)
                        {{-- Режим просмотра --}}
                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">Сумма</label>
                                <div class="form-static">{{ number_format($operation->amount_cfm, 0, ',', ' ') }} р.</div>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">Город</label>
                                <div class="form-static">{{ $operation->city->city_name }}</div>
                            </div>
                        </div>

                        @if($isClientRefund)
                            <div class="mb-4 rounded-md border border-border bg-muted/30 p-3 text-sm">
                                <div class="mb-1 font-medium">Возврат клиенту</div>
                                <div>С кассы: <strong>{{ number_format($operation->amount_cfm, 0, ',', ' ') }}</strong> ₽</div>
                                <div>С мастера: <strong>{{ number_format((int) $operation->amount_from_master, 0, ',', ' ') }}</strong> ₽</div>
                                <div class="mt-1 border-t border-border pt-1">Всего: <strong>{{ number_format((int) $operation->amount_cfm + (int) $operation->amount_from_master, 0, ',', ' ') }}</strong> ₽</div>
                                @if($operation->related_order_id)
                                    <div class="mt-1">
                                        Заказ:
                                        <a href="{{ route('orders.show', $operation->related_order_id) }}" class="text-primary hover:underline">
                                            №{{ $operation->related_order_id }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">Тип</label>
                                <div class="form-static">
                                    @if($operation->category->cfm_cat_group === 'inflows')
                                        Приход
                                    @else
                                        Расход
                                    @endif
                                </div>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">Статья ДДС</label>
                                <div class="form-static">{{ $operation->category->cfm_cat_name }}</div>
                            </div>
                        </div>

                        @if($operation->relatedCity)
                            <div class="form-group">
                                <label class="form-label">Город назначения</label>
                                <div class="form-static">{{ $operation->relatedCity->city_name }}</div>
                            </div>
                        @endif

                        @if($operation->cfm_subcat)
                            <div class="form-group">
                                <label class="form-label">
                                    @if($operation->category->cfm_cat_name === 'Выдача Дивидендов')
                                        Учредитель
                                    @else
                                        Подстатья
                                    @endif
                                </label>
                                <div class="form-static">{{ $operation->cfm_subcat }}</div>
                            </div>
                        @endif

                        @include('cfm.partials.director-payment-fields')

                        @if($operation->relatedOrder)
                            <div class="form-group">
                                <label class="form-label">Связанный заказ</label>
                                <div class="form-static">
                                    <a href="{{ route('orders.show', $operation->relatedOrder->order_id) }}" class="text-primary hover:underline">
                                        Заказ #{{ $operation->relatedOrder->order_id }}
                                    </a>
                                </div>
                            </div>
                        @endif

                        <div class="form-group">
                            <label class="form-label">Описание</label>
                            <div class="form-static whitespace-pre-line">{{ $operation->cfm_adds ?: '—' }}</div>
                        </div>

                    @else
                        {{-- Режим редактирования --}}
                        <form method="POST" action="{{ route('cfm.update', $operation->cfm_id) }}" id="cfmForm">
                            @csrf
                            @method('PUT')

                            <div class="form-row">
                                @if($isClientRefund)
                                    <div class="form-group" style="flex: 1;">
                                        <label class="form-label">С кассы <span class="text-destructive">*</span></label>
                                        <input type="number" name="amount_from_cash" id="amount_from_cash" class="form-input" value="{{ old('amount_from_cash', $operation->amount_cfm) }}" required min="0" placeholder="0" oninput="updateRefundTotal()">
                                        @error('amount_from_cash')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="form-group" style="flex: 1;">
                                        <label class="form-label">С мастера <span class="text-destructive">*</span></label>
                                        <input type="number" name="amount_from_master" id="amount_from_master" class="form-input" value="{{ old('amount_from_master', (int) $operation->amount_from_master) }}" required min="0" placeholder="0" oninput="updateRefundTotal()">
                                        @error('amount_from_master')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                @else
                                    <div class="form-group" style="flex: 1;">
                                        <label class="form-label">Сумма @if(!$isOrderIncome)<span class="text-destructive">*</span>@endif</label>
                                        @if($isOrderIncome)
                                            <div class="form-static">{{ number_format($operation->amount_cfm, 0, ',', ' ') }} р.</div>
                                        @else
                                            <input type="number" name="amount_cfm" class="form-input" value="{{ old('amount_cfm', $operation->amount_cfm) }}" required min="1" placeholder="0" @if(!empty($salaryPayoutHint['available'])) max="{{ (int) $salaryPayoutHint['available'] }}" @endif>
                                            @error('amount_cfm')
                                                <span class="form-error">{{ $message }}</span>
                                            @enderror
                                            @if(!empty($salaryPayoutHint))
                                                <p class="mt-2 text-sm text-muted-foreground">
                                                    ЗП за {{ $salaryPayoutHint['period_month'] }}:
                                                    доступно {{ number_format((int) $salaryPayoutHint['available'], 0, ',', ' ') }} ₽
                                                    из начисленных {{ number_format((int) $salaryPayoutHint['accrued'], 0, ',', ' ') }} ₽.
                                                    Больше провести нельзя.
                                                    @if(!empty($salaryPayoutHint['blocked']))
                                                        {{ $salaryPayoutHint['blocked'] }}
                                                    @endif
                                                </p>
                                            @endif
                                        @endif
                                    </div>
                                @endif

                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">Город @if(!$isOrderIncome)<span class="text-destructive">*</span>@endif</label>
                                    @if($isOrderIncome)
                                        <div class="form-static">{{ $operation->city->city_name }}</div>
                                    @else
                                        <select name="city_id" class="form-input" required>
                                            @foreach($cities as $city)
                                                <option value="{{ $city->city_id }}" {{ old('city_id', $operation->city_id) == $city->city_id ? 'selected' : '' }}>
                                                    {{ $city->city_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('city_id')
                                            <span class="form-error">{{ $message }}</span>
                                        @enderror
                                    @endif
                                </div>
                            </div>
                            @if($isClientRefund)
                                <div class="mb-4 text-sm text-muted-foreground">Всего возврат: <strong id="refund-total">{{ number_format((int) $operation->amount_cfm + (int) $operation->amount_from_master, 0, ',', ' ') }}</strong> ₽</div>
                            @endif

                            <div class="form-row">
                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">Тип</label>
                                    <div class="form-static">
                                        @if($operation->category->cfm_cat_group === 'inflows')
                                            Приход
                                        @else
                                            Расход
                                        @endif
                                    </div>
                                </div>

                                <div class="form-group" style="flex: 1;">
                                    <label class="form-label">Статья ДДС</label>
                                    <div class="form-static">{{ $operation->category->cfm_cat_name }}</div>
                                </div>
                            </div>

                            @if($operation->relatedCity)
                                <div class="form-group">
                                    <label class="form-label">Город назначения</label>
                                    <div class="form-static">{{ $operation->relatedCity->city_name }}</div>
                                </div>
                            @endif

                            @if($operation->cfm_subcat)
                                <div class="form-group">
                                    <label class="form-label">
                                        @if($operation->category->cfm_cat_name === 'Выдача Дивидендов')
                                            Учредитель
                                        @else
                                            Подстатья
                                        @endif
                                    </label>
                                    <div class="form-static">{{ $operation->cfm_subcat }}</div>
                                </div>
                            @endif

                            @include('cfm.partials.director-payment-fields')

                            @if($operation->relatedOrder)
                                <div class="form-group">
                                    <label class="form-label">Связанный заказ</label>
                                    <div class="form-static">
                                        <a href="{{ route('orders.show', $operation->relatedOrder->order_id) }}" class="text-primary hover:underline">
                                            Заказ #{{ $operation->relatedOrder->order_id }}
                                        </a>
                                    </div>
                                </div>
                            @endif

                            <div class="form-group">
                                <label class="form-label">Описание</label>
                                @if($isOrderIncome)
                                    <div class="form-static whitespace-pre-line">{{ $operation->cfm_adds ?: '—' }}</div>
                                @else
                                    <textarea name="cfm_adds" class="form-input" rows="3" required placeholder="Комментарий к операции">{{ old('cfm_adds', $operation->cfm_adds) }}</textarea>
                                    @error('cfm_adds')
                                        <span class="form-error">{{ $message }}</span>
                                    @enderror
                                @endif
                            </div>
                        </form>
                    @endif

                    <div class="mt-6 border-t border-border pt-4">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Создано</label>
                            <div class="form-static">
                                {{ $cfmCreatedAtForCity->format('d.m.Y H:i') }}
                                @if($operation->createdBy)
                                    <span class="text-muted-foreground"> ({{ $operation->createdBy->user_name }})</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Правая колонка (~600px): действия над документами --}}
            <div class="cfm-show-sidebar">
                <div class="card p-4">
                    @if($operation->cfm_closed_at)
                        <div class="order-show-buttons mb-4">
                            @if($canReopen)
                                <button type="button" class="btn btn-warning btn-order-compact" style="grid-column: 1 / -1;" onclick="confirmReopen()">
                                    {!! icon('edit') !!} Переоткрыть
                                </button>
                            @endif
                            <a href="{{ route('cfm.index') }}" class="btn btn-secondary btn-order-compact" style="grid-column: 1 / -1;">
                                {!! icon('back') !!} Назад
                            </a>
                        </div>
                        @if($canReopen)
                            <form method="POST" action="{{ route('cfm.reopen', $operation->cfm_id) }}" id="reopenForm" class="hidden">
                                @csrf
                            </form>
                        @endif
                    @elseif($canEditCfm)
                        @if($operation->category->cfm_cat_group === 'outflows')
                            <div class="mb-4 rounded-md border p-3 text-sm {{ $cityCashWouldBreach ? 'border-destructive bg-destructive/10' : 'border-border bg-muted/30' }}">
                                <div class="font-medium">Касса «{{ $cityCashName }}» ({{ $cityCashKind }})</div>
                                <div>Сейчас: <strong>{{ number_format($cityCashBalance, 0, ',', ' ') }}</strong> ₽</div>
                                <div>После расхода: <strong>{{ number_format($cityCashProjected, 0, ',', ' ') }}</strong> ₽</div>
                                @if($cityCashWouldBreach)
                                    <div class="mt-1 text-destructive">Нельзя провести: касса уйдёт ниже −{{ number_format($overdraftLimit, 0, ',', ' ') }} ₽.</div>
                                @else
                                    <div class="mt-1 text-muted-foreground">Лимит минуса: −{{ number_format($overdraftLimit, 0, ',', ' ') }} ₽.</div>
                                @endif
                                <div class="mt-1 text-muted-foreground">Кассы города и спутника считаются отдельно.</div>
                            </div>
                        @endif
                        <div class="order-show-buttons mb-4">
                            @if(!$isOrderIncome)
                                <button type="submit" form="cfmForm" name="action" value="save" class="btn btn-secondary btn-order-compact">
                                    {!! icon('save') !!} Сохранить
                                </button>
                            @endif
                            <button type="button" class="btn btn-primary btn-order-compact" onclick="confirmClose()" title="Провести операцию — после этого она повлияет на остаток">
                                {!! icon('check') !!} Провести и закрыть
                            </button>
                            <a href="{{ route('cfm.index') }}" class="btn btn-secondary btn-order-compact" style="grid-column: 1 / -1;">
                                {!! icon('back') !!} Назад
                            </a>
                        </div>
                        <form method="POST" action="{{ route('cfm.close', $operation->cfm_id) }}" id="closeForm" class="hidden">
                            @csrf
                        </form>
                    @else
                        <a href="{{ route('cfm.index') }}" class="btn btn-secondary btn-order-compact mb-4" style="display: inline-flex; width: 100%; justify-content: center;">
                            {!! icon('back') !!} Назад
                        </a>
                    @endif

                    <h3 class="order-documents-heading mb-3 border-t border-border pt-4" style="font-size: 1rem;">{!! icon('document') !!} Документы</h3>
                    <div class="cfm-sidebar-documents-only" aria-label="Документы операции">

                        @if($canAttachDocuments)
                            <div class="dropzone mb-3" data-category="general" onclick="openFileDialog('general')">
                                <div class="dropzone-inner" style="padding: 1.25rem 1rem;">
                                    <div class="dropzone-emoji" style="font-size: 1.5rem;">📄</div>
                                    <div>Перетащите файлы сюда</div>
                                    <div class="dropzone-inner-note">или нажмите · до 10 МБ</div>
                                </div>
                            </div>
                            <input type="file" id="file-general" class="hidden" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.odt,.txt,.csv" onchange="uploadDocument('general', this.files[0])">
                        @endif

                        <div id="files-general" class="cfm-files-list">
                            @foreach($operation->documents as $doc)
                                @php
                                    $isImage = str_starts_with($doc->file_mime, 'image/');
                                    $viewUrl = $isImage || $doc->file_mime === 'application/pdf'
                                        ? route('documents.show', $doc->document_id)
                                        : route('documents.download', $doc->document_id);
                                @endphp
                                <div class="cfm-file-item file-item" data-document-id="{{ $doc->document_id }}">
                                    @if($isImage)
                                        <a href="{{ $viewUrl }}" target="_blank" class="cfm-file-thumb file-thumb">
                                            <img src="{{ route('documents.show', $doc->document_id) }}" alt="{{ $doc->file_name }}">
                                        </a>
                                    @else
                                        <a href="{{ $viewUrl }}" target="_blank" class="cfm-file-thumb cfm-file-thumb--placeholder" title="Открыть файл">📄</a>
                                    @endif
                                    <div class="cfm-file-item-body">
                                        <a href="{{ $viewUrl }}" class="file-name cfm-file-name" target="_blank" title="Открыть файл">{{ $doc->file_name }}</a>
                                        <div class="cfm-file-item-actions">
                                            <span class="file-size">{{ $doc->human_size }}</span>
                                            @if($canDeleteDocuments)
                                                <span class="file-delete" onclick="deleteDocument({{ $doc->document_id }}, 'general')" title="Удалить">✕</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if($operation->cfm_closed_at && $canAttachDocuments)
                            <div class="mt-3 rounded-md border border-border bg-muted p-3 text-center text-sm text-muted-foreground">
                                Операция проведена. Можно приложить недостающие файлы.
                            </div>
                        @endif

                        @if($canAttachDocuments)
                            <p class="order-docs-hint mt-3"><strong>Форматы:</strong> jpg, png, jpeg, gif, pdf, doc, docx, xls, xlsx, odt, txt, csv</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

{{-- Модальное окно подтверждения проведения --}}
@if($canEditCfm)
<div id="confirmModal" style="display: none; position: fixed; inset: 0; z-index: 1000; align-items: center; justify-content: center; background: rgba(0,0,0,0.5);">
    <div class="card mx-4 w-full max-w-md p-6 shadow-lg">
        <h3 class="mb-4 text-lg font-semibold">Подтверждение проведения</h3>
        <p class="mb-4 text-sm text-muted-foreground">
            Вы уверены, что хотите провести эту операцию? После проведения редактирование будет недоступно.
        </p>
        @if($operation->category->cfm_cat_group === 'outflows')
            <p class="mb-6 text-sm {{ $cityCashWouldBreach ? 'text-destructive' : 'text-muted-foreground' }}">
                Касса «{{ $cityCashName }}» ({{ $cityCashKind }}): сейчас {{ number_format($cityCashBalance, 0, ',', ' ') }} ₽,
                после расхода {{ number_format($cityCashProjected, 0, ',', ' ') }} ₽
                (лимит −{{ number_format($overdraftLimit, 0, ',', ' ') }} ₽).
                Кассы города и спутника считаются отдельно.
            </p>
        @endif
        <div class="flex justify-end gap-3">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Отмена</button>
            <button type="button" class="btn btn-primary" onclick="submitClose()">Провести</button>
        </div>
    </div>
</div>
@endif

{{-- Модальное окно подтверждения переоткрытия --}}
@if($canReopen)
<div id="reopenModal" style="display: none; position: fixed; inset: 0; z-index: 1000; align-items: center; justify-content: center; background: rgba(0,0,0,0.5);">
    <div class="card mx-4 w-full max-w-md p-6 shadow-lg">
        <h3 class="mb-4 text-lg font-semibold">Подтверждение переоткрытия</h3>
        <p class="mb-6 text-sm text-muted-foreground">
            Вы уверены, что хотите переоткрыть эту операцию? Операция станет непроведённой и её можно будет редактировать.
        </p>
        <div class="flex justify-end gap-3">
            <button type="button" class="btn btn-secondary" onclick="closeReopenModal()">Отмена</button>
            <button type="button" class="btn btn-warning" onclick="submitReopen()">Переоткрыть</button>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function updateRefundTotal() {
    const cashEl = document.getElementById('amount_from_cash');
    const masterEl = document.getElementById('amount_from_master');
    const totalEl = document.getElementById('refund-total');
    if (!cashEl || !masterEl || !totalEl) return;
    const cash = parseInt(cashEl.value || '0', 10) || 0;
    const master = parseInt(masterEl.value || '0', 10) || 0;
    totalEl.textContent = (cash + master).toLocaleString('ru-RU');
}

function openFileDialog(category) {
    document.getElementById(`file-${category}`).click();
}

async function uploadDocument(category, file) {
    if (!file) return;

    @if(! $canAttachDocuments)
    Toast.error('Нет права добавлять документы к этой операции');
    return;
    @endif

    if (file.size > 10 * 1024 * 1024) {
        Toast.warning('Файл слишком большой! Максимальный размер: 10 МБ');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch(crmUrl('/cfm/{{ $operation->cfm_id }}/documents'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            }
        });

        const result = await response.json();

        if (result.success) {
            addFileToList(category, result.document);
            document.getElementById(`file-${category}`).value = '';
            Toast.success('Файл загружен');
        } else {
            Toast.error(result.message || 'Ошибка при загрузке файла');
        }
    } catch (e) {
        console.error(e);
        Toast.error('Ошибка при загрузке файла');
    }
}

function cfmEscapeHtml(text) {
    if (text == null) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function addFileToList(category, docData) {
    const filesList = document.getElementById(`files-${category}`);

    const fileItem = document.createElement('div');
    fileItem.className = 'cfm-file-item file-item';
    fileItem.dataset.documentId = docData.document_id;

    const isImage = docData.file_mime && docData.file_mime.startsWith('image/');
    const viewUrl = isImage || docData.file_mime === 'application/pdf'
        ? crmUrl(`/documents/${docData.document_id}`)
        : crmUrl(`/documents/${docData.document_id}/download`);

    let thumbHtml = '';
    if (isImage) {
        thumbHtml = `<a href="${viewUrl}" target="_blank" class="cfm-file-thumb file-thumb"><img src="${crmUrl('/documents/' + docData.document_id)}" alt="${cfmEscapeHtml(docData.file_name)}"></a>`;
    } else {
        thumbHtml = `<a href="${viewUrl}" target="_blank" class="cfm-file-thumb cfm-file-thumb--placeholder" title="Открыть файл">📄</a>`;
    }

    @if($canDeleteDocuments)
    const deleteButton = `<span class="file-delete" onclick="deleteDocument(${docData.document_id}, '${category}')" title="Удалить">✕</span>`;
    @else
    const deleteButton = '';
    @endif

    const nameSafe = cfmEscapeHtml(docData.file_name);
    fileItem.innerHTML = `
        ${thumbHtml}
        <div class="cfm-file-item-body">
            <a href="${viewUrl}" class="file-name cfm-file-name" target="_blank" title="Открыть файл">${nameSafe}</a>
            <div class="cfm-file-item-actions">
                <span class="file-size">${cfmEscapeHtml(docData.human_size)}</span>
                ${deleteButton}
            </div>
        </div>
    `;

    filesList.appendChild(fileItem);
}

async function deleteDocument(documentId, category) {
    @if(! $canDeleteDocuments)
    Toast.error('Нельзя удалять документы из проведённой операции');
    return;
    @endif

    if (!confirm('Удалить файл?')) return;

    try {
        const response = await fetch(crmUrl(`/documents/${documentId}`), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            }
        });

        const result = await response.json();

        if (result.success) {
            const fileItem = document.querySelector(`[data-document-id="${documentId}"]`);
            if (fileItem) fileItem.remove();
        } else {
            Toast.error(result.message || 'Ошибка при удалении файла');
        }
    } catch (e) {
        console.error(e);
        Toast.error('Ошибка при удалении файла');
    }
}

// Drag & Drop
@if($canAttachDocuments)
document.querySelectorAll('.dropzone').forEach(dropzone => {
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
    });

    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('dragover');
    });

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');

        const category = dropzone.dataset.category;
        const file = e.dataTransfer.files[0];

        if (file) {
            uploadDocument(category, file);
        }
    });
});
@endif

@if($canEditCfm)
function confirmClose() {
    document.getElementById('confirmModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
}

function submitClose() {
    document.getElementById('closeForm').submit();
}

document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
@endif

@if($canReopen)
function confirmReopen() {
    document.getElementById('reopenModal').style.display = 'flex';
}

function closeReopenModal() {
    document.getElementById('reopenModal').style.display = 'none';
}

function submitReopen() {
    document.getElementById('reopenForm').submit();
}

document.getElementById('reopenModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReopenModal();
    }
});
@endif
</script>
@endpush
