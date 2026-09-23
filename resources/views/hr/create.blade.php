@extends('layouts.app')

@section('title', 'Создание сотрудника')

@section('content')
    <div class="mb-4">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Сотрудники', 'url' => route('hr.index')],
                ['label' => 'Создание сотрудника', 'url' => null],
            ]"
        />
    </div>

    <div class="order-show-page">
        <div class="cfm-show-layout">
            <div>
                <div class="card p-4">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="m-0 text-base font-semibold">Создание сотрудника</h3>
                        <a href="{{ route('hr.index') }}" class="btn btn-secondary shrink-0">{!! icon('back') !!} Назад</a>
                    </div>

                    <form id="employeeForm" class="hr-employee-form" onsubmit="saveEmployee(event)">
                        @csrf
                        <div class="hr-form-row hr-form-row--1">
                            <div class="form-group">
                                <label>ФИО <span class="required">*</span></label>
                                <input type="text" name="user_name" class="form-input" required>
                            </div>
                        </div>
                        <div class="hr-form-row hr-form-row--2">
                            <div class="form-group">
                                <label>Email <span class="required">*</span></label>
                                <div class="flex min-w-0">
                                    <input type="text" name="email_prefix" class="form-input form-input-email-prefix" required>
                                    <span class="email-suffix">{{ \App\Http\Controllers\HrController::EMPLOYEE_EMAIL_SUFFIX }}</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <x-input-phone-ru name="user_phone" id="userPhone" label="Телефон" />
                            </div>
                        </div>
                        <div class="hr-form-row hr-form-row--4">
                            <div class="form-group">
                                <label>Паспорт №</label>
                                <input type="text" name="user_passport" class="form-input" maxlength="20" inputmode="numeric"
                                       oninput="this.value = this.value.replace(/[^\d\s]/g, '')" onblur="checkPassport(this.value)">
                                <div id="passportWarning" class="hr-create-passport-warn hidden">
                                    ⚠️ <span id="passportWarningText"></span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>ИНН</label>
                                <input type="text" name="user_inn" class="form-input" maxlength="12" inputmode="numeric"
                                       placeholder="10 или 12 цифр"
                                       oninput="this.value = this.value.replace(/\D/g, '')">
                            </div>
                            <div class="form-group">
                                <label>Роль <span class="required">*</span></label>
                                <select name="role_id" id="hrRoleSelect" class="form-input" required onchange="hrOnRoleChangeCreate()">
                                    <option value="">Выберите роль</option>
                                    @foreach($creatableRoles as $role)
                                        <option value="{{ $role->role_id }}" data-role-code="{{ $role->role_code }}">{{ $role->role_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group" id="hrCityGroup">
                                <label id="hrCityLabel">Город <span class="required">*</span></label>
                                <div class="hr-city-inline-row flex flex-wrap items-center gap-3">
                                    @if($canSetAccessAllCities ?? false)
                                        <label class="flex shrink-0 cursor-pointer items-center gap-2 text-sm" id="hrAccessAllWrap">
                                            <input type="checkbox" name="access_all_cities" value="1" id="accessAllCitiesCb" onchange="hrToggleAccessAllCitiesCreate()">
                                            Все города
                                        </label>
                                    @endif
                                    <div class="multiselect min-w-[12rem] flex-1" id="cityMultiselect">
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
                                            <label><input type="checkbox" name="city_ids[]" value="{{ $city->city_id }}" onchange="updateCityText()"> {{ $city->city_name }}</label>
                                        @endforeach
                                    </div>
                                    </div>
                                </div>
                                <p class="text-muted-foreground mt-1 hidden text-sm" id="hrCityCallCenterNote">Все города (диспетчер / старший диспетчер)</p>
                            </div>
                        </div>
                        <div class="hr-form-row hr-form-row--2">
                            <div class="form-group">
                                <label>Дата рождения</label>
                                <input type="date" name="user_birth_date" class="form-input">
                            </div>
                            <div class="form-group">
                                <label>Работает с</label>
                                <input type="date" name="user_hired_at" class="form-input" value="{{ date('Y-m-d') }}" readonly>
                            </div>
                        </div>

                        <div class="hr-edit-actions mt-6 justify-end">
                            <a href="{{ route('hr.index') }}" class="btn btn-secondary">Отмена</a>
                            <button type="submit" class="btn btn-primary" id="saveBtn">{!! icon('save') !!} Создать сотрудника</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="cfm-show-sidebar">
                <div class="card p-4">
                    <h3 class="order-documents-heading mb-3 text-base">{!! icon('document') !!} Документы сотрудника <span class="required">*</span></h3>
                    <div id="documentError" class="hr-edit-doc-error">
                        <strong>Ошибка:</strong> <span id="documentErrorText"></span>
                    </div>
                    <div class="dropzone mb-2" onclick="document.getElementById('fileInput').click()">
                        <div class="dropzone-inner hr-dropzone-inner-compact">
                            <div class="dropzone-emoji">📄</div>
                            <div>Перетащите файлы сюда или нажмите для выбора</div>
                            <div class="dropzone-inner-note">Максимальный размер: 10 МБ</div>
                        </div>
                    </div>
                    <input type="file" id="fileInput" name="files[]" multiple class="hidden" form="employeeForm"
                           accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.odt,.txt,.csv"
                           onchange="renderSelectedFiles()">
                    <div id="filesList" class="files-list"></div>
                </div>
            </div>
        </div>
    </div>

<div id="passwordModal" class="modal" style="display: none;">
    <div style="position: fixed; inset: 0; background: rgba(0,0,0,0.5);" onclick="closePasswordModal()"></div>
    <div class="modal-content" style="max-width: 400px; position: relative; z-index: 1;">
        <div class="modal-header"><h3>Пароль сотрудника</h3></div>
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
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const employeeEmailSuffix = @json(\App\Http\Controllers\HrController::EMPLOYEE_EMAIL_SUFFIX);

function toggleCityDropdown() {
    document.getElementById('cityDropdown').classList.toggle('open');
}
function updateCityText() {
    const checked = document.querySelectorAll('#cityDropdown input:checked');
    const el = document.getElementById('cityText');
    if (checked.length === 0) { el.textContent = 'Выберите город'; el.classList.add('text-muted-foreground'); }
    else if (checked.length === 1) { el.textContent = checked[0].parentElement.textContent.trim(); el.classList.remove('text-muted-foreground'); }
    else { el.textContent = 'Выбрано: ' + checked.length; el.classList.remove('text-muted-foreground'); }
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('#cityMultiselect')) document.getElementById('cityDropdown').classList.remove('open');
});

function hrSelectedRoleCode() {
    const sel = document.getElementById('hrRoleSelect');
    if (!sel || sel.selectedIndex < 0) return '';
    return sel.options[sel.selectedIndex].dataset.roleCode || '';
}

function hrIsDispatcherRole(code) {
    return code === 'call_center' || code === 'senior_dispatcher';
}

function hrOnRoleChangeCreate() {
    const isCc = hrIsDispatcherRole(hrSelectedRoleCode());
    const ms = document.getElementById('cityMultiselect');
    const note = document.getElementById('hrCityCallCenterNote');
    const wrap = document.getElementById('hrAccessAllWrap');
    const lbl = document.getElementById('hrCityLabel');
    if (isCc) {
        ms.style.display = 'none';
        if (note) note.classList.remove('hidden');
        if (wrap) wrap.style.display = 'none';
        if (lbl) lbl.innerHTML = 'Город';
        document.querySelectorAll('#cityDropdown input').forEach(c => { c.checked = false; });
        updateCityText();
        const cb = document.getElementById('accessAllCitiesCb');
        if (cb) cb.checked = false;
    } else {
        ms.style.display = '';
        if (note) note.classList.add('hidden');
        if (wrap) wrap.style.display = '';
        if (lbl) lbl.innerHTML = 'Город <span class="required">*</span>';
        hrToggleAccessAllCitiesCreate();
    }
}

function hrToggleAccessAllCitiesCreate() {
    const cb = document.getElementById('accessAllCitiesCb');
    const ms = document.getElementById('cityMultiselect');
    if (!cb || !ms || hrIsDispatcherRole(hrSelectedRoleCode())) return;
    ms.style.display = cb.checked ? 'none' : '';
}

document.addEventListener('DOMContentLoaded', function() { hrOnRoleChangeCreate(); });

function renderSelectedFiles() {
    const input = document.getElementById('fileInput');
    const list = document.getElementById('filesList');
    list.innerHTML = '';
    Array.from(input.files).forEach((f, i) => {
        const size = f.size < 1024 ? f.size + ' B' : f.size < 1024*1024 ? (f.size/1024).toFixed(1) + ' KB' : (f.size/(1024*1024)).toFixed(1) + ' MB';
        const item = document.createElement('div');
        item.className = 'file-item';
        item.innerHTML = '<span class="file-name">' + f.name + '</span><span class="file-size">' + size + '</span>';
        list.appendChild(item);
    });
}

async function checkPassport(value) {
    const warn = document.getElementById('passportWarning');
    if (!value || value.length < 5) { warn.classList.add('hidden'); return; }
    try {
        const r = await fetch(crmUrl('/hr/check-passport?passport=' + encodeURIComponent(value)), { headers: { 'Accept': 'application/json' } });
        const data = await r.json();
        if (data.blacklisted) {
            warn.classList.remove('hidden');
            document.getElementById('passportWarningText').textContent = 'Этот паспорт в чёрном списке! ' + data.user_name + ', причина: ' + data.reason;
        } else {
            warn.classList.add('hidden');
        }
    } catch(e) {}
}

async function saveEmployee(e) {
    e.preventDefault();
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
    btn.textContent = 'Сохранение...';

    try {
        const response = await fetch(crmUrl('/hr'), {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const result = await response.json();
        if (result.success) {
            if (result.password) {
                document.getElementById('passwordDisplay').textContent = result.password;
                document.getElementById('passwordModal').style.display = 'flex';
            } else {
                window.location.href = crmUrl('/hr');
            }
        } else {
            const msg = result.message || (result.errors ? Object.values(result.errors).flat().join(', ') : 'Ошибка');
            Toast.error(msg);
        }
    } catch(err) {
        console.error(err);
        Toast.error('Ошибка соединения');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Создать сотрудника';
    }
}

function copyPassword() {
    const pw = document.getElementById('passwordDisplay').textContent;
    navigator.clipboard.writeText(pw).then(() => Toast.success('Скопировано')).catch(() => {});
}
function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
    window.location.href = crmUrl('/hr');
}
</script>
@endpush
