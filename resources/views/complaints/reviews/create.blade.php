@extends('layouts.app')

@section('title', 'Добавить обращение')

@section('content')
<div class="mb-4">
    <x-breadcrumbs
        :items="[
            ['label' => 'Главная', 'url' => route('orders.index')],
            ['label' => 'Претензии', 'url' => route('complaints.index')],
            ['label' => 'Обратная связь', 'url' => route('complaints.reviews')],
            ['label' => 'Добавить обращение', 'url' => null],
        ]"
    />
</div>

@if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
@endif

<div class="order-show-page">
    <div class="cfm-show-layout">
        <div>
            <div class="card p-4">
                <h3 class="mb-4 text-base font-semibold">Новое обращение</h3>

                <form method="POST" action="{{ route('complaints.reviews.store') }}" id="reviewForm" enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    <div class="form-group mb-0">
                        <label class="form-label">Ссылка</label>
                        <input
                            type="url"
                            name="link"
                            class="form-input"
                            value="{{ old('link') }}"
                            placeholder="https://…"
                            inputmode="url"
                            autocomplete="url"
                        >
                        <p class="mt-1 text-xs text-muted-foreground">Только URL (http/https), без пробелов и спецсимволов</p>
                        @error('link') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-row gap-4" style="margin-bottom: 0;">
                        <div class="form-group mb-0" style="flex: 1;">
                            <label class="form-label">ID партнёра</label>
                            <input type="text" name="partner_id" class="form-input" value="{{ old('partner_id') }}" placeholder="ID партнёра" oninput="this.value=this.value.replace(/[^\p{L}\p{N}]/gu,'')">
                            @error('partner_id') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group mb-0" style="flex: 1;">
                            <label class="form-label">Номер заявки</label>
                            <input type="text" name="order_number" class="form-input" value="{{ old('order_number') }}" placeholder="Номер заявки" oninput="this.value=this.value.replace(/[^\p{L}\p{N}]/gu,'')">
                            @error('order_number') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="cfm-show-sidebar">
            <div class="card p-4">
                <div class="order-show-buttons mb-4">
                    <button type="submit" form="reviewForm" class="btn btn-primary btn-order-compact">
                        {!! icon('save') !!} Сохранить
                    </button>
                    <a href="{{ route('complaints.reviews') }}" class="btn btn-secondary btn-order-compact" style="grid-column: 1 / -1;">
                        {!! icon('back') !!} Назад
                    </a>
                </div>

                <h3 class="order-documents-heading mb-3 border-t border-border pt-4" style="font-size: 1rem;">{!! icon('document') !!} Документы</h3>
                <div class="cfm-sidebar-documents-only" aria-label="Документы к отзыву">
                    <div class="dropzone mb-3" data-dropzone="review-create" onclick="reviewCreateOpenFileDialog()">
                        <div class="dropzone-inner" style="padding: 1.25rem 1rem;">
                            <div class="dropzone-emoji" style="font-size: 1.5rem;">📄</div>
                            <div>Перетащите файлы сюда</div>
                            <div class="dropzone-inner-note">или нажмите · до 10 МБ</div>
                        </div>
                    </div>
                    <input type="file" id="file-documents-review-create" class="hidden" form="reviewForm" name="documents[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.odt,.txt,.csv">

                    <div id="review-create-files-pending" class="cfm-files-list"></div>

                    @error('documents')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                    @foreach ($errors->keys() as $errKey)
                        @if (str_starts_with($errKey, 'documents.'))
                            @foreach ($errors->get($errKey) as $message)
                                <span class="form-error block">{{ $message }}</span>
                            @endforeach
                        @endif
                    @endforeach

                    <p class="order-docs-hint mt-3"><strong>Форматы:</strong> jpg, png, jpeg, gif, pdf, doc, docx, xls, xlsx, odt, txt, csv</p>
                    <p class="mt-2 text-center text-sm text-muted-foreground">Файлы будут прикреплены при нажатии «Сохранить»</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('file-documents-review-create');
    const pendingListEl = document.getElementById('review-create-files-pending');
    const filesBuffer = [];

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function syncFilesToInput() {
        const dt = new DataTransfer();
        filesBuffer.forEach(f => dt.items.add(f));
        fileInput.files = dt.files;
    }

    function renderPendingList() {
        pendingListEl.innerHTML = '';
        filesBuffer.forEach((file, index) => {
            const row = document.createElement('div');
            row.className = 'cfm-file-item file-item cfm-create-pending-item';
            row.innerHTML = `
                <div class="cfm-file-item-body" style="flex: 1;">
                    <span class="file-name cfm-file-name">${escapeHtml(file.name)}</span>
                    <div class="cfm-file-item-actions">
                        <span class="file-size">${escapeHtml(formatSize(file.size))}</span>
                        <span class="file-delete" data-index="${index}" title="Убрать из списка">✕</span>
                    </div>
                </div>
            `;
            row.querySelector('.file-delete').addEventListener('click', function() {
                const i = parseInt(this.getAttribute('data-index'), 10);
                filesBuffer.splice(i, 1);
                syncFilesToInput();
                renderPendingList();
            });
            pendingListEl.appendChild(row);
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function addFilesFromList(fileList) {
        const max = 10 * 1024 * 1024;
        for (let i = 0; i < fileList.length; i++) {
            const f = fileList[i];
            if (f.size > max) {
                if (typeof Toast !== 'undefined') {
                    Toast.warning('Файл слишком большой (макс. 10 МБ): ' + f.name);
                }
                continue;
            }
            filesBuffer.push(f);
        }
        syncFilesToInput();
        renderPendingList();
    }

    window.reviewCreateOpenFileDialog = function() {
        fileInput.click();
    };

    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length) {
            addFilesFromList(this.files);
        }
        this.value = '';
        syncFilesToInput();
    });

    document.querySelectorAll('.dropzone[data-dropzone="review-create"]').forEach(dropzone => {
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
            if (e.dataTransfer.files && e.dataTransfer.files.length) {
                addFilesFromList(e.dataTransfer.files);
            }
        });
    });
});
</script>
@endpush
