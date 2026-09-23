@extends('layouts.app')

@section('title', 'Новая кассовая операция')

@section('content')
    <div class="mb-4">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Касса', 'url' => route('cfm.index')],
                ['label' => 'Новая операция', 'url' => null],
            ]"
        />
    </div>

    <div class="order-show-page">
        <div class="cfm-show-layout">
            <div>
                <div class="card p-4">
                    <h3 class="mb-4 text-base font-semibold">Реквизиты операции</h3>

                    <form method="POST" action="{{ route('cfm.store') }}" id="cfmForm" enctype="multipart/form-data">
                        @csrf

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">Сумма <span class="text-destructive">*</span></label>
                                <input type="number" name="amount_cfm" class="form-input" value="{{ old('amount_cfm') }}" required min="1" placeholder="0">
                                @error('amount_cfm')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">Город <span class="text-destructive">*</span></label>
                                <select name="city_id" class="form-input" id="citySelect" required>
                                    @if($cities->count() === 1)
                                        <option value="{{ $cities->first()->city_id }}" selected>{{ $cities->first()->city_name }}</option>
                                    @else
                                        <option value="">Выберите город</option>
                                        @foreach($cities as $city)
                                            <option value="{{ $city->city_id }}" {{ old('city_id') == $city->city_id ? 'selected' : '' }}>
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

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">Тип <span class="text-destructive">*</span></label>
                                <select class="form-input" id="typeSelect" required>
                                    <option value="">Выберите тип</option>
                                    <option value="inflows" {{ old('cfm_cat_group', $presetCategory->cfm_cat_group ?? '') === 'inflows' ? 'selected' : '' }}>Приход</option>
                                    <option value="outflows" {{ old('cfm_cat_group', $presetCategory->cfm_cat_group ?? '') === 'outflows' ? 'selected' : '' }}>Расход</option>
                                </select>
                            </div>

                            <div class="form-group" style="flex: 1;">
                                <label class="form-label">Статья ДДС <span class="text-destructive">*</span></label>
                                <select name="cfm_cat_id" class="form-input" id="categorySelect" required>
                                    <option value="">Сначала выберите тип</option>
                                </select>
                                @error('cfm_cat_id')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group" id="subcatField" style="display: none;">
                            <label class="form-label">Подстатья <span class="text-destructive">*</span></label>
                            <select name="cfm_subcat" class="form-input" id="subcatSelect">
                                <option value="">Выберите подстатью</option>
                            </select>
                            @error('cfm_subcat')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group" id="founderField" style="display: none;">
                            <label class="form-label">Учредитель <span class="text-destructive">*</span></label>
                            <select name="cfm_subcat" class="form-input" id="founderSelect">
                                <option value="">Выберите учредителя</option>
                                <option value="Евгений" {{ old('cfm_subcat') === 'Евгений' ? 'selected' : '' }}>Евгений</option>
                                <option value="Никита" {{ old('cfm_subcat') === 'Никита' ? 'selected' : '' }}>Никита</option>
                            </select>
                        </div>

                        <div class="form-group" id="targetCityField" style="display: none;">
                            <label class="form-label">Город назначения <span class="text-destructive">*</span></label>
                            <select name="target_city_id" class="form-input" id="targetCitySelect">
                                <option value="">Выберите город</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->city_id }}" {{ old('target_city_id') == $city->city_id ? 'selected' : '' }}>
                                        {{ $city->city_name }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="is_transfer" id="isTransferField" value="0">
                            @error('target_city_id')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Описание *</label>
                            <textarea name="cfm_adds" class="form-input" rows="3" required placeholder="Комментарий к операции">{{ old('cfm_adds') }}</textarea>
                            @error('cfm_adds')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                            <p id="directorSalaryHint" class="mt-2 text-sm text-muted-foreground" hidden></p>
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
                        <div class="dropzone mb-3" data-dropzone="create" onclick="cfmCreateOpenFileDialog()">
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
    const typeSelect = document.getElementById('typeSelect');
    const categorySelect = document.getElementById('categorySelect');
    const subcatField = document.getElementById('subcatField');
    const subcatSelect = document.getElementById('subcatSelect');
    const founderField = document.getElementById('founderField');
    const founderSelect = document.getElementById('founderSelect');
    const targetCityField = document.getElementById('targetCityField');
    const targetCitySelect = document.getElementById('targetCitySelect');
    const isTransferField = document.getElementById('isTransferField');
    const citySelect = document.getElementById('citySelect');

    const subcategories = {
        'Листовки': ['Печать листовок', 'Доставка листовок'],
        'Аренда Офиса': ['Аренда', 'К/У'],
        'Аренда Квартиры': ['Аренда', 'К/У']
    };

    const categoriesByType = {
        inflows: [
            @foreach($categories->where('cfm_cat_group', 'inflows') as $cat)
            { id: {{ $cat->cfm_cat_id }}, name: "{{ $cat->cfm_cat_name }}" },
            @endforeach
        ],
        outflows: [
            @foreach($categories->where('cfm_cat_group', 'outflows') as $cat)
            { id: {{ $cat->cfm_cat_id }}, name: "{{ $cat->cfm_cat_name }}" },
            @endforeach
        ]
    };

    const presetCategoryId = {{ $presetCategory ? $presetCategory->cfm_cat_id : 'null' }};

    function updateCategories() {
        const type = typeSelect.value;
        categorySelect.innerHTML = '<option value="">Выберите статью</option>';

        if (type && categoriesByType[type]) {
            categoriesByType[type].forEach(cat => {
                const option = document.createElement('option');
                option.value = cat.id;
                option.textContent = cat.name;
                option.dataset.name = cat.name;

                if (presetCategoryId && cat.id === presetCategoryId) {
                    option.selected = true;
                }

                categorySelect.appendChild(option);
            });
        }

        updateAdditionalFields();
    }

    function updateAdditionalFields() {
        const selected = categorySelect.options[categorySelect.selectedIndex];
        const catName = selected ? selected.dataset.name : '';

        subcatField.style.display = 'none';
        subcatSelect.required = false;
        subcatSelect.name = '';

        founderField.style.display = 'none';
        founderSelect.required = false;
        founderSelect.name = '';

        targetCityField.style.display = 'none';
        targetCitySelect.required = false;
        isTransferField.value = '0';

        if (!catName) return;

        if (subcategories[catName]) {
            subcatField.style.display = 'block';
            subcatSelect.required = true;
            subcatSelect.name = 'cfm_subcat';

            subcatSelect.innerHTML = '<option value="">Выберите подстатью</option>';
            subcategories[catName].forEach(subcat => {
                const option = document.createElement('option');
                option.value = subcat;
                option.textContent = subcat;
                subcatSelect.appendChild(option);
            });
        } else if (catName === 'Выдача Дивидендов') {
            founderField.style.display = 'block';
            founderSelect.required = true;
            founderSelect.name = 'cfm_subcat';
        } else if (catName === 'Перемещение (выбытие)') {
            targetCityField.style.display = 'block';
            targetCitySelect.required = true;
            isTransferField.value = '1';
        }

        refreshDirectorSalaryHint();
    }

    const directorSalaryCategoryId = {{ (int) ($directorSalaryCategoryId ?? 0) }};
    const directorSalaryCapUrl = @json(route('cfm.salary.payout-cap'));
    const directorSalaryHint = document.getElementById('directorSalaryHint');
    const amountInput = document.querySelector('#cfmForm input[name="amount_cfm"]');

    function formatRub(n) {
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽';
    }

    function refreshDirectorSalaryHint() {
        if (!directorSalaryHint) return;
        const catId = parseInt(categorySelect.value, 10);
        const cityId = citySelect ? citySelect.value : '';
        if (!directorSalaryCategoryId || catId !== directorSalaryCategoryId) {
            directorSalaryHint.hidden = true;
            directorSalaryHint.textContent = '';
            if (amountInput) amountInput.removeAttribute('max');
            return;
        }
        if (!cityId) {
            directorSalaryHint.hidden = false;
            directorSalaryHint.textContent = 'Выберите город — сумма ЗП не может быть больше расчёта за прошлый месяц.';
            return;
        }
        directorSalaryHint.hidden = false;
        directorSalaryHint.textContent = 'Считаем доступный остаток ЗП…';
        fetch(directorSalaryCapUrl + '?city_id=' + encodeURIComponent(cityId), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (!res.ok) throw new Error('cap');
            return res.json();
        }).then(function (data) {
            const month = data.period_month || '';
            let text = 'ЗП за ' + month + ': начислено ' + formatRub(data.accrued || 0)
                + ', уже выплачено ' + formatRub(data.paid || 0)
                + ', доступно ' + formatRub(data.available || 0) + '. Больше провести нельзя.';
            if (data.blocked) {
                text = data.blocked + ' ' + text;
            }
            directorSalaryHint.textContent = text;
            if (amountInput && data.available > 0) {
                amountInput.max = data.available;
            }
        }).catch(function () {
            directorSalaryHint.textContent = 'Не удалось получить лимит ЗП. Провести больше начисленного всё равно нельзя.';
        });
    }

    typeSelect.addEventListener('change', updateCategories);
    categorySelect.addEventListener('change', updateAdditionalFields);

    citySelect.addEventListener('change', function() {
        const selectedCity = this.value;
        Array.from(targetCitySelect.options).forEach(option => {
            if (option.value === selectedCity && selectedCity !== '') {
                option.disabled = true;
            } else {
                option.disabled = false;
            }
        });
        refreshDirectorSalaryHint();
    });

    updateCategories();
    citySelect.dispatchEvent(new Event('change'));

    // Вложения до сохранения: буфер File → input documents[]
    const fileInput = document.getElementById('file-documents-create');
    const pendingListEl = document.getElementById('cfm-create-files-pending');
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

    window.cfmCreateOpenFileDialog = function() {
        fileInput.click();
    };

    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length) {
            addFilesFromList(this.files);
        }
        this.value = '';
        syncFilesToInput();
    });

    document.querySelectorAll('.dropzone[data-dropzone="create"]').forEach(dropzone => {
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
