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
                    <h3 class="mb-4 text-base font-semibold">{{ $pageTitle }}</h3>

                    <form method="POST" action="{{ route('cfm.store') }}" id="cfmForm" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="director_type" value="{{ $presetType }}">

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">Сумма <span class="text-destructive">*</span></label>
                                <input type="number" name="amount_cfm" class="form-input" value="{{ old('amount_cfm') }}" required min="1" placeholder="0">
                                @error('amount_cfm')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">Город (касса) <span class="text-destructive">*</span></label>
                                <select name="city_id" class="form-input" required>
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
                        </div>

                        <div class="form-group">
                            <label class="form-label">Статья ДДС</label>
                            <div class="form-static">{{ $presetCategory->cfm_cat_name }}</div>
                        </div>

                        @if($recipient)
                            <div class="form-group">
                                <label class="form-label">Получатель</label>
                                <div class="form-static">{{ $recipient }}</div>
                            </div>
                        @endif

                        @if($requiresPayer)
                            <div class="form-group">
                                <label class="form-label">Плательщик <span class="text-destructive">*</span></label>
                                <select name="cfm_payer" class="form-input" required>
                                    <option value="">Выберите плательщика</option>
                                    @foreach($payers as $payer)
                                        <option value="{{ $payer }}" {{ old('cfm_payer') === $payer ? 'selected' : '' }}>
                                            {{ $payer }}
                                        </option>
                                    @endforeach
                                </select>
                                @if($presetType === 'partner_expense')
                                    <small class="text-muted-foreground">Кто забирает деньги с кассы (без посредника)</small>
                                @endif
                                @error('cfm_payer')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </div>
                        @endif

                        @if($requiresExternalRef)
                            <div class="form-group">
                                <label class="form-label">№ кассовой операции Уровня <span class="text-destructive">*</span></label>
                                <input
                                    type="text"
                                    name="external_cfm_ref"
                                    class="form-input"
                                    value="{{ old('external_cfm_ref') }}"
                                    required
                                    maxlength="64"
                                    placeholder="Номер прихода в кассе Уровня"
                                >
                                <small class="text-muted-foreground">Обязательно: номер операции из базы Уровня по приходу денег в кассу</small>
                                @error('external_cfm_ref')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </div>
                        @endif

                        <div class="form-group">
                            <label class="form-label">Описание <span class="text-destructive">*</span></label>
                            <textarea name="cfm_adds" class="form-input" rows="3" required placeholder="Комментарий к операции">{{ old('cfm_adds') }}</textarea>
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
                        <button type="submit" form="cfmForm" name="action" value="save" class="btn btn-primary btn-order-compact">
                            {!! icon('save') !!} Сохранить
                        </button>
                        <a href="{{ route('cfm.index') }}" class="btn btn-secondary btn-order-compact" style="grid-column: 1 / -1;">
                            {!! icon('back') !!} Назад
                        </a>
                    </div>

                    <h3 class="order-documents-heading mb-3 border-t border-border pt-4" style="font-size: 1rem;">{!! icon('document') !!} Документы</h3>
                    <div class="cfm-sidebar-documents-only" aria-label="Документы к операции">
                        <div class="dropzone mb-3" data-dropzone="create" onclick="document.getElementById('file-documents-create').click()">
                            <div class="dropzone-inner" style="padding: 1.25rem 1rem;">
                                <div class="dropzone-emoji" style="font-size: 1.5rem;">📄</div>
                                <div>Перетащите файлы сюда</div>
                                <div class="dropzone-inner-note">или нажмите · до 10 МБ</div>
                            </div>
                        </div>
                        <input type="file" id="file-documents-create" class="hidden" form="cfmForm" name="documents[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.odt,.txt,.csv">
                        <div id="cfm-create-files-pending" class="cfm-files-list"></div>
                        @error('documents')
                            <span class="form-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('file-documents-create');
    const pendingListEl = document.getElementById('cfm-create-files-pending');
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
