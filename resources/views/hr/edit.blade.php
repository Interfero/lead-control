@extends('layouts.app')

@php
    $isHrReadOnly = $isHrReadOnly ?? false;
    $isHrRoleLocked = $isHrRoleLocked ?? false;
    $canSetAccessAllCities = $canSetAccessAllCities ?? false;
    $hrCardContextLabel = $employee->user_name . ' (ID ' . $employee->user_id . ')';
@endphp

@section('title', $hrCardContextLabel)

@section('navbar_context')
    {{ $hrCardContextLabel }}
@endsection

@section('content')

    <div class="mb-4">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Сотрудники', 'url' => route('hr.index')],
                ['label' => $hrCardContextLabel, 'url' => null],
            ]"
        />
    </div>

    <div class="order-show-page">
        <div class="cfm-show-layout">
            {{-- Левая колонка: данные, комментарии, действия --}}
            <div>
                <div class="card p-4">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="m-0 text-base font-semibold">Карточка сотрудника</h3>
                        <a href="{{ route('hr.index') }}" class="btn btn-secondary shrink-0">{!! icon('back') !!} Назад</a>
                    </div>

                    @if($isHrReadOnly)
                        <div class="hr-edit-info-banner">
                            Режим просмотра: учётные записи разработчика может редактировать только разработчик.
                        </div>
                    @elseif($isHrRoleLocked)
                        <div class="hr-edit-info-banner">
                            Роль этого сотрудника изменить нельзя. Доступно редактирование учётных данных и городов.
                        </div>
                    @endif

                    <form id="employeeForm" class="hr-employee-form" onsubmit="saveEmployee(event)">
                        @csrf
                        <input type="hidden" name="_method" value="PUT">
                        <div class="hr-form-row hr-form-row--1">
                            <div class="form-group">
                                <label>ФИО <span class="required">*</span></label>
                                <input type="text" name="user_name" class="form-input" value="{{ $employee->user_name }}" required @if($isHrReadOnly) readonly @endif>
                            </div>
                        </div>
                        <div class="hr-form-row hr-form-row--2">
                            <div class="form-group">
                                <label>Email <span class="required">*</span></label>
                                <div class="flex min-w-0">
                                    <input type="text" name="email_prefix" class="form-input form-input-email-prefix" value="{{ \App\Http\Controllers\HrController::employeeEmailLocalPart($employee->email) }}" required @if($isHrReadOnly) readonly @endif>
                                    <span class="email-suffix">{{ \App\Http\Controllers\HrController::EMPLOYEE_EMAIL_SUFFIX }}</span>
                                </div>
                            </div>
                            <div class="form-group">
                                @if($isHrReadOnly)
                                    <label>Телефон</label>
                                    <div class="form-static py-2">@if($employee->user_phone)+7 {{ substr($employee->user_phone, 0, 3) }} {{ substr($employee->user_phone, 3, 3) }}-{{ substr($employee->user_phone, 6, 2) }}-{{ substr($employee->user_phone, 8, 2) }}@else — @endif</div>
                                @else
                                    <x-input-phone-ru name="user_phone" id="userPhone" label="Телефон" :value="$employee->user_phone ?? ''" />
                                @endif
                            </div>
                        </div>
                        <div class="hr-form-row hr-form-row--4">
                            <div class="form-group">
                                <label>Паспорт №</label>
                                <input type="text" name="user_passport" class="form-input" value="{{ $employee->user_passport }}" maxlength="20" inputmode="numeric"
                                       oninput="this.value = this.value.replace(/[^\d\s]/g, '')" @if($isHrReadOnly) readonly @endif>
                            </div>
                            <div class="form-group">
                                <label>ИНН</label>
                                <input type="text" name="user_inn" class="form-input" value="{{ $employee->user_inn }}" maxlength="12" inputmode="numeric"
                                       placeholder="10 или 12 цифр"
                                       oninput="this.value = this.value.replace(/\D/g, '')" @if($isHrReadOnly) readonly @endif>
                            </div>
                            <div class="form-group">
                                <label>Роль <span class="required">*</span></label>
                                @if($isHrRoleLocked)
                                    <div class="form-static py-2">{{ $employee->roles->first()?->role_name ?? '—' }}</div>
                                @else
                                    <select name="role_id" id="hrRoleSelectEdit" class="form-input" required onchange="hrOnRoleChangeEdit()">
                                        <option value="">Выберите роль</option>
                                        @foreach($creatableRoles as $role)
                                            <option value="{{ $role->role_id }}" data-role-code="{{ $role->role_code }}" {{ $employee->roles->first()?->role_id == $role->role_id ? 'selected' : '' }}>{{ $role->role_name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            <div class="form-group">
                                <label id="hrCityLabelEdit">Город <span class="required">*</span></label>
                                @if($isHrReadOnly)
                                    <div class="form-static py-2">
                                        @if($employee->hasAllCitiesAccess())
                                            все города
                                        @else
                                            {{ $employee->cities->pluck('city_name')->filter()->implode(', ') ?: '—' }}
                                        @endif
                                    </div>
                                @else
                                    @if($canSetAccessAllCities)
                                        <label class="mb-2 flex cursor-pointer items-center gap-2 text-sm" id="hrAccessAllWrapEdit" style="{{ $employee->hasRole('call_center') ? 'display:none' : '' }}">
                                            <input type="checkbox" name="access_all_cities" value="1" id="accessAllCitiesCbEdit" {{ ($employee->access_all_cities && ! $employee->hasRole('call_center')) ? 'checked' : '' }} onchange="hrToggleAccessAllCitiesEdit()">
                                            Все города
                                        </label>
                                    @endif
                                    <p class="text-muted-foreground mb-2 text-sm {{ $employee->hasRole('call_center') ? '' : 'hidden' }}" id="hrCcCityNoteEdit">все города (диспетчер КЦ)</p>
                                    <div class="multiselect" id="cityMultiselect" style="{{ ($employee->hasRole('call_center') || ($employee->access_all_cities && ($canSetAccessAllCities ?? false))) ? 'display:none' : '' }}">
                                        <div class="multiselect-selected" onclick="toggleCityDropdown()">
                                            <span class="multiselect-text text-muted-foreground" id="cityText">Выберите город</span>
                                            <span class="ml-auto">▼</span>
                                        </div>
                                        <div class="multiselect-dropdown" id="cityDropdown">
                                            <div class="multiselect-actions">
                                                <button type="button" onclick="event.preventDefault(); document.querySelectorAll('#cityDropdown input').forEach(c=>c.checked=true); updateCityText();">выбрать все</button>
                                                <button type="button" onclick="event.preventDefault(); document.querySelectorAll('#cityDropdown input').forEach(c=>c.checked=false); updateCityText();">снять все</button>
                                            </div>
                                            @foreach($cities as $city)
                                                <label><input type="checkbox" name="city_ids[]" value="{{ $city->city_id }}" {{ in_array($city->city_id, $employeeCityIds) ? 'checked' : '' }} onchange="updateCityText()"> {{ $city->city_name }}</label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="hr-form-row hr-form-row--2">
                            <div class="form-group">
                                <label>Дата рождения</label>
                                <input type="date" name="user_birth_date" class="form-input" value="{{ $employee->user_birth_date?->format('Y-m-d') }}" @if($isHrReadOnly) readonly @endif>
                            </div>
                            <div class="form-group">
                                <label>Работает с</label>
                                <input type="date" class="form-input" value="{{ $employee->user_hired_at?->format('Y-m-d') }}" readonly>
                            </div>
                        </div>

                        @if($employee->user_fired_at)
                            <div class="hr-edit-fired-banner">
                                Уволен {{ $employee->user_fired_at->format('d.m.Y') }}
                                @if($employee->is_blacklisted) — в чёрном списке ({{ $employee->blacklist_reason }}) @endif
                            </div>
                        @endif

                        <input type="hidden" name="user_note" value="{{ $employee->user_note }}">

                        @php
                            $canRemoveBlacklist = auth()->user()->hasAnyRole(['general_director', 'developer'])
                                || ($employee->blacklisted_by !== null && (int) $employee->blacklisted_by === (int) auth()->id());
                        @endphp

                        <div class="hr-edit-actions">
                            <div class="flex flex-wrap gap-2">
                                @if(auth()->user()->hasAnyRole(['branch_head', 'regional_director', 'developer', 'general_director']))
                                    @if($employee->user_fired_at)
                                        <button type="button" class="btn btn-success" onclick="restoreEmployee()">Восстановить</button>
                                    @else
                                        <button type="button" class="btn btn-danger" onclick="fireEmployee()">Уволить</button>
                                        @if(!$employee->is_blacklisted)
                                            <button type="button" class="btn btn-danger" onclick="fireWithBlacklist()">Уволить с ЧС</button>
                                        @endif
                                    @endif
                                @endif
                                @if($employee->is_blacklisted && $canRemoveBlacklist)
                                    <button type="button" class="btn btn-secondary" onclick="removeFromBlacklistCard()">Убрать из чёрного списка</button>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-3">
                                @if(auth()->user()->hasAnyRole(['general_director', 'regional_director', 'developer'])
                                    || (auth()->user()->hasRole('branch_head') && $employee->hasAnyRole(['master', 'senior_master'])))
                                    <button type="button" class="btn btn-secondary" onclick="resetPassword()">{!! icon('refresh') !!} Сбросить пароль</button>
                                @endif
                                @if((!$isHrReadOnly || $isHrRoleLocked) && auth()->user()->hasAnyRole(['branch_head', 'regional_director', 'developer', 'general_director']))
                                    <button type="submit" class="btn btn-primary" id="saveBtn">{!! icon('save') !!} Сохранить</button>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Правая колонка: документы --}}
            <div class="cfm-show-sidebar">
                <div class="card p-4">
                    <h3 class="order-documents-heading mb-3 text-base">{!! icon('document') !!} Документы сотрудника</h3>
                    <div id="documentError" class="hr-edit-doc-error">
                        <strong>Ошибка:</strong> <span id="documentErrorText"></span>
                    </div>
                    @if(!$isHrReadOnly)
                        <div class="dropzone mb-2" onclick="document.getElementById('fileInput').click()">
                            <div class="dropzone-inner hr-dropzone-inner-compact">
                                <div class="dropzone-emoji">📄</div>
                                <div>Перетащите файлы сюда или нажмите для выбора</div>
                                <div class="dropzone-inner-note">до 10 МБ</div>
                            </div>
                        </div>
                        <input type="file" id="fileInput" class="hidden" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.odt,.txt,.csv" onchange="uploadDocument(this.files[0])">
                    @endif
                    <div id="documentsList" class="files-list">
                        @foreach($documents as $doc)
                            <div class="file-item" data-document-id="{{ $doc->document_id }}">
                                @php $isViewable = str_starts_with($doc->file_mime ?? '', 'image/') || ($doc->file_mime ?? '') === 'application/pdf'; @endphp
                                <a href="{{ route('documents.show', $doc->document_id) }}{{ $isViewable ? '' : '/download' }}" class="file-name" target="_blank">{{ $doc->file_name }}</a>
                                <span class="file-size">{{ $doc->human_size ?? '0 B' }}</span>
                                @if(auth()->user()->hasRole('developer'))
                                    <span class="file-delete" onclick="deleteDocument({{ $doc->document_id }})">✕</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 border-t border-border pt-4">
                        <h3 class="order-documents-heading mb-3 text-base">Комментарии</h3>
                        <div id="commentsList" class="hr-comments-box">
                            @forelse($comments as $c)
                                <div class="hr-comment-item comment-item" data-id="{{ $c->comment_id }}">
                                    <div class="hr-comment-head">
                                        <span class="hr-comment-author">{{ $c->author?->user_name ?? 'Неизвестно' }}</span>
                                        <span class="hr-comment-meta">{{ $c->created_at?->format('d.m.Y H:i') }}
                                            @if(auth()->user()->hasRole('developer'))
                                                <span class="hr-comment-delete" onclick="deleteComment({{ $c->comment_id }})" title="Удалить">✕</span>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="hr-comment-body">{{ $c->comment_text }}</div>
                                </div>
                            @empty
                                <div class="hr-comments-empty comments-empty">Комментариев нет</div>
                            @endforelse
                        </div>
                        @if(!$isHrReadOnly)
                            <textarea id="newCommentText" class="form-input mt-2" rows="2" placeholder="Напишите комментарий..." maxlength="2000"></textarea>
                            <button type="button" class="btn btn-primary mt-2 h-8 text-xs" onclick="addComment()">
                                {!! icon('save') !!} Добавить комментарий
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

<div id="passwordModal" class="modal" style="display: none;">
    <div style="position: fixed; inset: 0; background: rgba(0,0,0,0.5);" onclick="closePasswordModal()"></div>
    <div class="modal-content" style="max-width: 400px; position: relative; z-index: 1;">
        <div class="modal-header"><h3>Новый пароль</h3></div>
        <div class="modal-body" style="text-align: center;">
            <p>Запишите или скопируйте пароль:</p>
            <div class="password-display" id="passwordDisplay"></div>
            <button type="button" class="btn btn-primary" onclick="copyPassword()">Скопировать</button>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closePasswordModal()">Закрыть</button>
        </div>
    </div>
</div>

<div id="blacklistModal" class="modal" style="display: none;">
    <div style="position: fixed; inset: 0; background: rgba(0,0,0,0.5);" onclick="document.getElementById('blacklistModal').style.display='none'"></div>
    <div class="modal-content" style="max-width: 500px; position: relative; z-index: 1;">
        <div class="modal-header"><h3>Добавить в чёрный список</h3></div>
        <div class="modal-body">
            <div class="form-group">
                <label>Причина <span class="required">*</span></label>
                <textarea id="blacklistReason" class="form-input" rows="3" required></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('blacklistModal').style.display='none'">Отмена</button>
            <button type="button" class="btn btn-danger" onclick="submitBlacklist()">Уволить с ЧС</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const employeeId = {{ $employee->user_id }};
const isHrReadOnly = @json($isHrReadOnly ?? false);
const employeeEmailSuffix = @json(\App\Http\Controllers\HrController::EMPLOYEE_EMAIL_SUFFIX);
const canDeleteComment = {{ auth()->user()->hasRole('developer') ? 'true' : 'false' }};

function toggleCityDropdown() { document.getElementById('cityDropdown').classList.toggle('open'); }
function updateCityText() {
    const checked = document.querySelectorAll('#cityDropdown input:checked');
    const el = document.getElementById('cityText');
    if (checked.length === 0) { el.textContent = 'Выберите город'; el.classList.add('text-muted-foreground'); }
    else if (checked.length === 1) { el.textContent = checked[0].parentElement.textContent.trim(); el.classList.remove('text-muted-foreground'); }
    else { el.textContent = 'Выбрано: ' + checked.length; el.classList.remove('text-muted-foreground'); }
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('#cityMultiselect')) {
        const dd = document.getElementById('cityDropdown');
        if (dd) dd.classList.remove('open');
    }
});
if (document.getElementById('cityDropdown')) {
    updateCityText();
}

function hrToggleAccessAllCitiesEdit() {
    const cb = document.getElementById('accessAllCitiesCbEdit');
    const ms = document.getElementById('cityMultiselect');
    const sel = document.getElementById('hrRoleSelectEdit');
    const code = sel && sel.selectedIndex >= 0 ? (sel.options[sel.selectedIndex].dataset.roleCode || '') : '';
    if (!ms || code === 'call_center') return;
    ms.style.display = (cb && cb.checked) ? 'none' : '';
}

function hrOnRoleChangeEdit() {
    const sel = document.getElementById('hrRoleSelectEdit');
    if (!sel) return;
    const code = sel.options[sel.selectedIndex]?.dataset?.roleCode || '';
    const isCc = code === 'call_center';
    const ms = document.getElementById('cityMultiselect');
    const note = document.getElementById('hrCcCityNoteEdit');
    const wrap = document.getElementById('hrAccessAllWrapEdit');
    if (!ms) return;
    if (isCc) {
        ms.style.display = 'none';
        if (note) note.classList.remove('hidden');
        if (wrap) wrap.style.display = 'none';
        const cb = document.getElementById('accessAllCitiesCbEdit');
        if (cb) cb.checked = false;
    } else {
        if (note) note.classList.add('hidden');
        if (wrap) wrap.style.display = '';
        hrToggleAccessAllCitiesEdit();
    }
}
if (!isHrReadOnly && document.getElementById('hrRoleSelectEdit')) {
    document.addEventListener('DOMContentLoaded', function() { hrOnRoleChangeEdit(); });
}

async function saveEmployee(e) {
    e.preventDefault();
    if (isHrReadOnly) return;
    const form = e.target;
    const formData = new FormData(form);
    let prefix = String(formData.get('email_prefix') || '').trim();
    const at = prefix.indexOf('@');
    if (at !== -1) prefix = prefix.slice(0, at).trim();
    prefix = prefix.replace(/[^a-zA-Z0-9._+-]/g, '').toLowerCase();
    formData.set('email', prefix ? prefix + employeeEmailSuffix : '');
    formData.delete('email_prefix');

    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    try {
        const response = await fetch(crmUrl('/hr/' + employeeId), {
            method: 'POST', body: formData,
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const result = await response.json();
        if (result.success) { Toast.success('Сохранено'); }
        else {
            const msg = result.message || (result.errors ? Object.values(result.errors).flat().join(', ') : 'Ошибка');
            Toast.error(msg);
        }
    } catch(err) { Toast.error('Ошибка соединения'); }
    finally { btn.disabled = false; }
}

async function fireEmployee() {
    if (!confirm('Уволить сотрудника?')) return;
    const r = await fetch(crmUrl('/hr/' + employeeId + '/fire'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } });
    const d = await r.json();
    if (d.success) { Toast.success(d.message); setTimeout(() => location.reload(), 500); }
    else Toast.error(d.message || 'Ошибка');
}

async function restoreEmployee() {
    if (!confirm('Восстановить сотрудника?')) return;
    const r = await fetch(crmUrl('/hr/' + employeeId + '/restore'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } });
    const d = await r.json();
    if (d.success) { Toast.success(d.message); setTimeout(() => location.reload(), 500); }
    else Toast.error(d.message || 'Ошибка');
}

function fireWithBlacklist() {
    document.getElementById('blacklistModal').style.display = 'flex';
    document.getElementById('blacklistReason').value = '';
}

async function submitBlacklist() {
    const reason = document.getElementById('blacklistReason').value.trim();
    if (!reason) { Toast.error('Укажите причину'); return; }
    await fetch(crmUrl('/hr/' + employeeId + '/fire'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } });
    const r = await fetch(crmUrl('/hr/' + employeeId + '/blacklist'), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ reason: reason })
    });
    const d = await r.json();
    if (d.success) { Toast.success('Сотрудник уволен и добавлен в ЧС'); setTimeout(() => location.reload(), 500); }
    else Toast.error(d.message || 'Ошибка');
}

async function removeFromBlacklistCard() {
    if (!confirm('Убрать сотрудника из чёрного списка?')) return;
    const r = await fetch(crmUrl('/hr/' + employeeId + '/blacklist'), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    });
    const d = await r.json().catch(() => ({}));
    if (r.ok && d.success) {
        Toast.success(d.message || 'Готово');
        setTimeout(() => location.reload(), 500);
    } else {
        Toast.error(d.message || 'Ошибка');
    }
}

async function resetPassword() {
    if (!confirm('Сбросить пароль?')) return;
    const r = await fetch(crmUrl('/hr/' + employeeId + '/reset-password'), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } });
    const d = await r.json();
    if (d.success && d.password) {
        document.getElementById('passwordDisplay').textContent = d.password;
        document.getElementById('passwordModal').style.display = 'flex';
    } else Toast.error(d.message || 'Ошибка');
}

function copyPassword() {
    navigator.clipboard.writeText(document.getElementById('passwordDisplay').textContent).then(() => Toast.success('Скопировано'));
}
function closePasswordModal() { document.getElementById('passwordModal').style.display = 'none'; }

function showDocumentError(msg) {
    const box = document.getElementById('documentError');
    document.getElementById('documentErrorText').textContent = msg;
    box.style.display = 'block';
}
function hideDocumentError() {
    document.getElementById('documentError').style.display = 'none';
}

async function uploadDocument(file) {
    if (!file) return;
    hideDocumentError();
    const formData = new FormData();
    formData.append('file', file);
    formData.append('documentable_type', 'App\\Models\\User');
    formData.append('documentable_id', employeeId);
    try {
        const r = await fetch(crmUrl('/hr/' + employeeId + '/documents'), {
            method: 'POST', body: formData,
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const result = await r.json();
        if (result.success) {
            const doc = result.document;
            const list = document.getElementById('documentsList');
            const mime = doc.file_mime || '';
            const isViewable = mime.startsWith('image/') || mime === 'application/pdf';
            const url = crmUrl('/documents/' + doc.document_id + (isViewable ? '' : '/download'));
            const canDelete = {{ auth()->user()->hasRole('developer') ? 'true' : 'false' }};
            const delBtn = canDelete ? '<span class="file-delete" onclick="deleteDocument(' + doc.document_id + ')">✕</span>' : '';
            const item = document.createElement('div');
            item.className = 'file-item';
            item.dataset.documentId = doc.document_id;
            item.innerHTML = '<a href="' + url + '" class="file-name" target="_blank">' + (doc.file_name || 'Файл') + '</a><span class="file-size">' + (doc.human_size || '') + '</span>' + delBtn;
            list.appendChild(item);
            document.getElementById('fileInput').value = '';
            Toast.success('Файл загружен');
        } else {
            showDocumentError(result.message || 'Ошибка загрузки');
            Toast.error(result.message || 'Ошибка загрузки');
        }
    } catch(e) {
        showDocumentError('Ошибка соединения');
        Toast.error('Ошибка загрузки');
    }
}

async function deleteDocument(id) {
    if (!confirm('Удалить файл?')) return;
    const r = await fetch(crmUrl('/documents/' + id), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } });
    const d = await r.json();
    if (d.success) { document.querySelector('[data-document-id="' + id + '"]')?.remove(); Toast.success('Удалён'); }
    else Toast.error(d.message || 'Ошибка');
}

async function addComment() {
    const text = document.getElementById('newCommentText').value.trim();
    if (!text) { Toast.error('Введите текст'); return; }
    const r = await fetch(crmUrl('/hr/' + employeeId + '/comments'), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ comment_text: text })
    });
    const d = await r.json();
    if (d.success) {
        const list = document.getElementById('commentsList');
        list.querySelector('.hr-comments-empty')?.remove();
        const c = d.comment;
        const el = document.createElement('div');
        el.className = 'hr-comment-item comment-item';
        el.dataset.id = c.comment_id;
        let metaHtml = escapeHtml(c.created_at);
        if (canDeleteComment) {
            metaHtml += '<span class="hr-comment-delete" onclick="deleteComment(' + c.comment_id + ')" title="Удалить">✕</span>';
        }
        el.innerHTML = '<div class="hr-comment-head"><span class="hr-comment-author">' + escapeHtml(c.author_name) + '</span><span class="hr-comment-meta">' + metaHtml + '</span></div><div class="hr-comment-body"></div>';
        el.querySelector('.hr-comment-body').textContent = c.comment_text;
        list.prepend(el);
        document.getElementById('newCommentText').value = '';
        Toast.success('Комментарий добавлен');
    } else Toast.error(d.message || 'Ошибка');
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

async function deleteComment(id) {
    if (!confirm('Удалить комментарий?')) return;
    const r = await fetch(crmUrl('/hr/comments/' + id), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } });
    const d = await r.json();
    if (d.success) { document.querySelector('.hr-comment-item[data-id="' + id + '"]')?.remove(); Toast.success('Удалён'); }
    else Toast.error(d.message || 'Ошибка');
}
</script>
@endpush
