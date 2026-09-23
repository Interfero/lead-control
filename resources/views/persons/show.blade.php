@extends('layouts.app')

@section('title', 'Персона #' . $person->person_id)

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <div style="display: flex; align-items: center; gap: 0.5rem;">
        <span style="font-weight: 600; font-size: 1.1rem;">#{{ $person->person_id }} — {{ $person->person_name }}@if($person->person_age), {{ $person->person_age }} лет @endif</span>
        <button class="btn-icon" onclick="openEditPersonModal()">{!! icon('edit') !!}</button>
    </div>
    <a href="{{ route('persons.index') }}" class="btn btn-secondary">
        {!! icon('back') !!} Назад к поиску
    </a>
</div>

<div class="person-detail-top-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    {{-- Телефоны --}}
    <div class="card">
        <div class="card-header">
            <h3>{!! icon('phone') !!} Телефоны</h3>
            <div style="display: flex; gap: 0.5rem;">
                @if($atsConfigured && $person->phones->count() > 0)
                    <button class="btn btn-sm btn-primary" onclick="openCallModal()" title="Позвонить клиенту">
                        {!! icon('phone') !!} Позвонить
                    </button>
                @endif
                <button class="btn btn-sm" onclick="openAddPhoneModal()">{!! icon('add') !!}</button>
            </div>
        </div>
        <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Номер</th>
                    <th>Комментарий</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($person->phones as $phone)
                <tr @if(\App\Helpers\PhoneHelper::userSeesFullPhone(auth()->user())) data-phone-digits="{{ $phone->phone_number }}" @endif>
                    <td><x-phone-masked :phone="$phone" :show-adds="false" /></td>
                    <td>{{ $phone->phone_adds ?? '-' }}</td>
                    <td>
                        <button class="btn-icon" onclick="editPhone({{ $phone->phone_id }})">{!! icon('edit') !!}</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
    
    {{-- Адреса --}}
    <div class="card">
        <div class="card-header">
            <h3>{!! icon('location') !!} Адреса</h3>
            <button class="btn btn-sm" onclick="openAddAddressModal()">{!! icon('add') !!}</button>
        </div>
        <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Город</th>
                    <th>Адрес</th>
                    <th>Комментарий</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($person->addresses as $address)
                <tr>
                    <td>{{ $address->city->city_name }}</td>
                    <td>{{ $address->street }}, {{ $address->house }}{{ $address->flat ? ', кв. ' . $address->flat : '' }}</td>
                    <td>{{ $address->address_adds ?? '-' }}</td>
                    <td>
                        <button class="btn-icon" onclick="editAddress({{ $address->address_id }})">{!! icon('edit') !!}</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>

{{-- История заказов --}}
@if($canViewClientHistory)
<div class="person-orders-history-wrap">
    <div class="person-orders-history-toolbar">
        <a href="{{ route('orders.create', $person->person_id) }}" class="btn btn-sm">
            {!! icon('add') !!} Новый заказ
        </a>
    </div>
    <x-orders.client-history
        :orders="$orderHistory"
        :status-labels="$statusLabels"
        :use-modal="true"
        title="История заказов"
    />
</div>
@endif

{{-- История звонков (интеграция с Mango Office) --}}
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h3>{!! icon('phone') !!} История звонков</h3>
        @if(!$atsConfigured)
            <span style="font-size: 0.75rem; color: #9ca3af;">Интеграция не настроена</span>
        @endif
    </div>
    <div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Дата/время</th>
                <th>Направление</th>
                <th>Номер</th>
                <th>Источник</th>
                <th>Статус</th>
                <th>Длительность</th>
                <th>Оператор</th>
                <th>Запись</th>
            </tr>
        </thead>
        <tbody>
            @forelse($person->calls as $call)
            <tr>
                <td>{{ $call->call_created_at->format('d.m.Y H:i') }}</td>
                <td>
                    <span class="call-direction call-direction-{{ $call->direction }}">
                        @if($call->direction === 'in')
                            {!! icon('download') !!} Входящий
                        @else
                            {!! icon('forward') !!} Исходящий
                        @endif
                    </span>
                </td>
                <td><x-phone-masked :number="$call->phone" /></td>
                <td>{{ $call->source?->display_label ?? '—' }}</td>
                <td>
                    <span class="call-status call-status-{{ $call->status }}">
                        {{ $call->status_label }}
                    </span>
                </td>
                <td>{{ $call->formatted_duration }}</td>
                <td>{{ $call->operator?->user_name ?? '-' }}</td>
                <td>
                    @if($call->record_url)
                        <a href="{{ $call->record_url }}" target="_blank" class="btn-icon" title="Прослушать запись">
                            {!! icon('play') !!}
                        </a>
                    @else
                        <span style="color: #9ca3af;">—</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align: center; color: #6b7280; padding: 2rem;">
                    Звонков пока нет
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

{{-- Модальное окно редактирования персоны --}}
<div id="editPersonModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Редактировать запись</h2>
            <button class="modal-close" onclick="closeEditPersonModal()">{!! icon('close') !!}</button>
        </div>
        <form id="editPersonForm" onsubmit="submitEditPerson(event)">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label class="form-label">Имя *</label>
                <input type="text" name="person_name" class="form-input" value="{{ $person->person_name }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Возраст</label>
                <input type="number" name="person_age" class="form-input" value="{{ $person->person_age }}" min="0" max="150">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditPersonModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

{{-- Модальное окно добавления телефона --}}
<div id="addPhoneModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Добавить телефон</h2>
            <button class="modal-close" onclick="closeAddPhoneModal()">{!! icon('close') !!}</button>
        </div>
        <form id="addPhoneForm" onsubmit="submitAddPhone(event)">
            @csrf
            <x-input-phone-ru name="phone_number" id="addPhoneNumber" label="Номер телефона *" :required="true" />
            <div class="form-group">
                <label class="form-label">Комментарий</label>
                <input type="text" name="phone_adds" class="form-input" 
                       placeholder="Например: рабочий, домашний">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddPhoneModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">Добавить</button>
            </div>
        </form>
    </div>
</div>

{{-- Модальное окно редактирования телефона --}}
<div id="editPhoneModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Редактировать телефон</h2>
            <button class="modal-close" onclick="closeEditPhoneModal()">{!! icon('close') !!}</button>
        </div>
        <form id="editPhoneForm" onsubmit="submitEditPhone(event)">
            @csrf
            @method('PUT')
            <input type="hidden" id="editPhoneId" name="phone_id">
            <x-input-phone-ru name="phone_number" id="editPhoneNumber" label="Номер телефона *" :required="true" />
            <div class="form-group">
                <label class="form-label">Комментарий</label>
                <input type="text" id="editPhoneAdds" name="phone_adds" class="form-input" 
                       placeholder="Например: рабочий, домашний">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditPhoneModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

{{-- Модальное окно добавления адреса --}}
<div id="addAddressModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Добавить адрес</h2>
            <button class="modal-close" onclick="closeAddAddressModal()">{!! icon('close') !!}</button>
        </div>
        <form id="addAddressForm" onsubmit="submitAddAddress(event)">
            @csrf
            <div class="form-group">
                <label class="form-label">Город *</label>
                <select name="city_id" class="form-input" required>
                    <option value="">Выберите город</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->city_id }}">{{ $city->city_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Улица</label>
                <input type="text" name="street" class="form-input">
            </div>
            <div style="display: flex; gap: 1rem;">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Дом</label>
                    <input type="text" name="house" class="form-input">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Квартира</label>
                    <input type="text" name="flat" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Комментарий</label>
                <textarea name="address_adds" class="form-input" rows="2"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddAddressModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">Добавить</button>
            </div>
        </form>
    </div>
</div>

{{-- Модальное окно редактирования адреса --}}
<div id="editAddressModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Редактировать адрес</h2>
            <button class="modal-close" onclick="closeEditAddressModal()">{!! icon('close') !!}</button>
        </div>
        <form id="editAddressForm" onsubmit="submitEditAddress(event)">
            @csrf
            @method('PUT')
            <input type="hidden" id="editAddressId" name="address_id">
            <div class="form-group">
                <label class="form-label">Город *</label>
                <select id="editAddressCityId" name="city_id" class="form-input" required>
                    <option value="">Выберите город</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->city_id }}">{{ $city->city_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Улица</label>
                <input type="text" id="editAddressStreet" name="street" class="form-input">
            </div>
            <div style="display: flex; gap: 1rem;">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Дом</label>
                    <input type="text" id="editAddressHouse" name="house" class="form-input">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Квартира</label>
                    <input type="text" id="editAddressFlat" name="flat" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Комментарий</label>
                <textarea id="editAddressAdds" name="address_adds" class="form-input" rows="2"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditAddressModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

{{-- Модальное окно инициации звонка (АТС) --}}
@if($atsConfigured && $person->phones->count() > 0)
<div id="callModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2>{!! icon('phone') !!} Позвонить клиенту</h2>
            <button class="modal-close" onclick="closeCallModal()">{!! icon('close') !!}</button>
        </div>
        <form id="callForm" onsubmit="submitCall(event)">
            @csrf
            <div class="form-group">
                <label class="form-label">Номер клиента *</label>
                <select name="phone_id" class="form-input" required>
                    @foreach($person->phones as $phone)
                        <option value="{{ $phone->phone_id }}">
                            {{ $phone->formatted_phone }}
                            @if($phone->phone_adds) ({{ $phone->phone_adds }}) @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Ваш внутренний номер *</label>
                <input type="text" name="extension" class="form-input" 
                       placeholder="Например: 101" required
                       pattern="[0-9]+"
                       title="Введите внутренний номер (только цифры)">
                <small style="color: #6b7280; font-size: 0.75rem;">
                    Внутренний номер вашего SIP-телефона в АТС
                </small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCallModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">
                    {!! icon('phone') !!} Позвонить
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Модальное окно просмотра/редактирования заказа --}}
<div id="orderModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2>Заказ #<span id="orderModalId"></span></h2>
            <button class="modal-close" onclick="closeOrderModal()">{!! icon('close') !!}</button>
        </div>
        
        {{-- Загрузка --}}
        <div id="orderModalLoading" style="padding: 3rem; text-align: center;">
            <div style="color: #6b7280;">Загрузка...</div>
        </div>
        
        {{-- Контент --}}
        <div id="orderModalContent" style="display: none; padding: 1.5rem;">
            {{-- Информация о закрытии --}}
            <div id="orderModalClosed" style="display: none; background: #f0fdf4; color: #065f46; padding: 0.75rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem;">
            </div>
            
            {{-- Форма редактирования (поля КЦ) --}}
            <form id="orderModalForm" onsubmit="submitOrderModal(event)">
                @csrf
                <input type="hidden" id="orderModalFormId" name="order_id">
                <input type="hidden" id="orderModalDatetimeHidden" name="datetime_order">
                
                <div class="order-fields-grid">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Тип заказа</label>
                        <select id="orderModalType" name="order_type" class="form-input">
                            <option value="new">Впервые</option>
                            <option value="repeat">Повтор</option>
                            <option value="warranty">Гарантия</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Профильность</label>
                        <select id="orderModalCore" name="order_core" class="form-input">
                            <option value="core">Профильный</option>
                            <option value="non_core">Непрофильный</option>
                            <option value="other">Прочий</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Статус</label>
                        <select id="orderModalStatus" name="order_status" class="form-input"></select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Вид техники</label>
                    <select id="orderModalEquipment" name="equipment_type" class="form-input">
                        <option value="">Выберите</option>
                        @foreach(\App\Models\Order::EQUIPMENT_TYPES as $eqKey => $eqLabel)
                            <option value="{{ $eqKey }}">{{ $eqLabel }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="order-fields-grid">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Дата</label>
                        <input type="date" id="orderModalDate" class="form-input" onchange="updateDatetimeHidden()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Время встречи</label>
                        <input type="time" id="orderModalTime" class="form-input" onchange="updateDatetimeHidden()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Время <span id="orderModalCityLabel" style="font-weight: normal;"></span></label>
                        <input type="text" id="orderModalLocalTime" class="form-input bg-muted text-muted-foreground" readonly>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Описание заказа</label>
                        <textarea id="orderModalAdds" name="order_adds" class="form-input" rows="5"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Переносы</label>
                        <textarea id="orderModalShift" name="shift_adds" class="form-input" rows="5"></textarea>
                    </div>
                </div>
                
                {{-- Данные города (только просмотр) --}}
                <div style="border-top: 1px solid #e5e7eb; padding-top: 1rem; margin-top: 0.5rem;">
                    <div style="font-size: 0.875rem; font-weight: 500; margin-bottom: 0.75rem; color: #6b7280;">Данные филиала (только просмотр)</div>
                    <div class="order-fields-grid order-fields-grid--readonly" style="font-size: 0.875rem;">
                        <div>
                            <div style="color: #6b7280; font-size: 0.75rem;">Мастер</div>
                            <div id="orderModalMaster" style="font-weight: 500;">—</div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 0.75rem;">Оплачено</div>
                            <div id="orderModalPaid" style="font-weight: 500;">0 ₽</div>
                        </div>
                        <div>
                            <div style="color: #6b7280; font-size: 0.75rem;">Комплектующие</div>
                            <div id="orderModalComp" style="font-weight: 500;">0 ₽</div>
                        </div>
                    </div>
                    <div style="margin-top: 0.75rem;">
                        <div style="color: #6b7280; font-size: 0.75rem;">Коммент филиала</div>
                        <div id="orderModalCityAdds" style="font-size: 0.875rem; white-space: pre-wrap;">—</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <a id="orderModalOpenFullBtn" href="#" class="btn btn-secondary" target="_blank">
                        {!! icon('forward') !!} Открыть полностью
                    </a>
                    <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">Закрыть</button>
                    <button type="submit" id="orderModalSaveBtn" class="btn btn-primary">
                        {!! icon('save') !!} Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Редактирование персоны
    function openEditPersonModal() {
        document.getElementById('editPersonModal').style.display = 'flex';
    }
    
    function closeEditPersonModal() {
        document.getElementById('editPersonModal').style.display = 'none';
    }
    
    async function submitEditPerson(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        const response = await fetch(crmUrl('/persons/{{ $person->person_id }}'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeEditPersonModal();
            location.reload();
        } else {
            Toast.error(result.message || 'Ошибка при сохранении');
        }
    }
    
    // Заглушки для остальных модальных окон
    function openAddPhoneModal() {
        document.getElementById('addPhoneModal').style.display = 'flex';
    }
    
    function closeAddPhoneModal() {
        document.getElementById('addPhoneModal').style.display = 'none';
        document.getElementById('addPhoneForm').reset();
    }
    
    async function submitAddPhone(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        const response = await fetch(crmUrl('/persons/{{ $person->person_id }}/phones'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeAddPhoneModal();
            location.reload();
        } else {
            Toast.error(result.message || 'Ошибка при добавлении телефона');
        }
    }
    
    function editPhone(phoneId) {
        const phoneRow = event.target.closest('tr');
        const fromData = (phoneRow.getAttribute('data-phone-digits') || '').replace(/\D/g, '').slice(-10);
        const fromCell = phoneRow.cells[0].textContent.replace(/\D/g, '').slice(-10);
        const phoneNumber = fromData.length === 10 ? fromData : fromCell;
        const phoneAdds = phoneRow.cells[1].textContent.trim();

        document.getElementById('editPhoneId').value = phoneId;
        const inp = document.getElementById('editPhoneNumber');
        inp.value = phoneNumber.length === 10 ? phoneNumber : '';
        document.getElementById('editPhoneAdds').value = phoneAdds === '-' ? '' : phoneAdds;

        document.getElementById('editPhoneModal').style.display = 'flex';
    }
    
    function closeEditPhoneModal() {
        document.getElementById('editPhoneModal').style.display = 'none';
        document.getElementById('editPhoneForm').reset();
    }
    
    async function submitEditPhone(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const phoneId = document.getElementById('editPhoneId').value;
        
        const response = await fetch(crmUrl(`/persons/phones/${phoneId}`), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeEditPhoneModal();
            location.reload();
        } else {
            Toast.error(result.message || 'Ошибка при обновлении телефона');
        }
    }
    
    function openAddAddressModal() {
        document.getElementById('addAddressModal').style.display = 'flex';
    }
    
    function closeAddAddressModal() {
        document.getElementById('addAddressModal').style.display = 'none';
        document.getElementById('addAddressForm').reset();
    }
    
    async function submitAddAddress(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        
        const response = await fetch(crmUrl('/persons/{{ $person->person_id }}/addresses'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeAddAddressModal();
            location.reload();
        } else {
            Toast.error(result.message || 'Ошибка при добавлении адреса');
        }
    }
    
    function editAddress(addressId) {
        // Находим строку с адресом в таблице
        const addressRow = event.target.closest('tr');
        const cityName = addressRow.cells[0].textContent.trim();
        const addressText = addressRow.cells[1].textContent.trim();
        const addressAdds = addressRow.cells[2].textContent.trim();
        
        // Получаем полные данные адреса через AJAX
        fetch(crmUrl(`/persons/addresses/${addressId}/data`), {
            headers: {
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Заполняем форму
                document.getElementById('editAddressId').value = addressId;
                document.getElementById('editAddressCityId').value = data.address.city_id;
                document.getElementById('editAddressStreet').value = data.address.street || '';
                document.getElementById('editAddressHouse').value = data.address.house || '';
                document.getElementById('editAddressFlat').value = data.address.flat || '';
                document.getElementById('editAddressAdds').value = data.address.address_adds || '';
                
                // Открываем модальное окно
                document.getElementById('editAddressModal').style.display = 'flex';
            } else {
                Toast.error('Ошибка при загрузке данных адреса');
            }
        })
        .catch(() => {
            Toast.error('Ошибка при загрузке данных адреса');
        });
    }
    
    function closeEditAddressModal() {
        document.getElementById('editAddressModal').style.display = 'none';
        document.getElementById('editAddressForm').reset();
    }
    
    async function submitEditAddress(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const addressId = document.getElementById('editAddressId').value;
        
        const response = await fetch(crmUrl(`/persons/addresses/${addressId}`), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeEditAddressModal();
            location.reload();
        } else {
            Toast.error(result.message || 'Ошибка при обновлении адреса');
        }
    }
    
    // Модальное окно заказа
    async function openOrderModal(orderId) {
        const modal = document.getElementById('orderModal');
        const form = document.getElementById('orderModalForm');
        const loading = document.getElementById('orderModalLoading');
        const content = document.getElementById('orderModalContent');
        
        // Показываем модалку с загрузкой
        modal.style.display = 'flex';
        loading.style.display = 'block';
        content.style.display = 'none';
        
        try {
            const response = await fetch(crmUrl(`/orders/${orderId}/data`), {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            
            if (!data.success) {
                Toast.error(data.message || 'Ошибка загрузки');
                closeOrderModal();
                return;
            }
            
            const order = data.order;
            const perms = data.permissions;
            
            // Заполняем данные
            document.getElementById('orderModalId').textContent = order.order_id;
            document.getElementById('orderModalFormId').value = order.order_id;
            
            // Поля формы
            const typeSelect = document.getElementById('orderModalType');
            const coreSelect = document.getElementById('orderModalCore');
            const statusSelect = document.getElementById('orderModalStatus');
            const dateInput = document.getElementById('orderModalDate');
            const timeInput = document.getElementById('orderModalTime');
            const addsInput = document.getElementById('orderModalAdds');
            const shiftInput = document.getElementById('orderModalShift');
            
            typeSelect.value = order.order_type;
            coreSelect.value = order.order_core;

            const settable = new Set(data.settableStatuses || []);
            statusSelect.innerHTML = '';
            (data.visibleStatuses || []).forEach(code => {
                const opt = document.createElement('option');
                opt.value = code;
                opt.textContent = (data.statusLabels && data.statusLabels[code]) || code;
                const canPick = settable.has(code) || code === order.order_status;
                opt.disabled = !canPick;
                statusSelect.appendChild(opt);
            });
            statusSelect.value = order.order_status;

            const equipSelect = document.getElementById('orderModalEquipment');
            if (equipSelect) {
                equipSelect.value = order.equipment_type || '';
            }
            
            // Разбиваем datetime на date и time
            const datetime = new Date(order.datetime_order);
            dateInput.value = order.datetime_order.split('T')[0];
            timeInput.value = order.datetime_order.split('T')[1];
            
            addsInput.value = order.order_adds || '';
            shiftInput.value = order.shift_adds || '';
            
            // Обновляем скрытое поле datetime
            updateDatetimeHidden();
            
            // Обновляем локальное время города
            document.getElementById('orderModalCityLabel').textContent = `(${order.city_name})`;
            document.getElementById('orderModalLocalTime').value = order.city_time;
            
            // Поля города (только просмотр для КЦ)
            document.getElementById('orderModalMaster').textContent = order.master_name || 'Не назначен';
            document.getElementById('orderModalPaid').textContent = order.amount_paid.toLocaleString('ru-RU') + ' ₽';
            document.getElementById('orderModalComp').textContent = order.amount_comp.toLocaleString('ru-RU') + ' ₽';
            document.getElementById('orderModalCityAdds').textContent = order.city_adds || '—';
            
            // Закрыт
            const closedInfo = document.getElementById('orderModalClosed');
            if (order.order_closed_at) {
                closedInfo.textContent = 'Заказ проведён: ' + order.order_closed_at;
                closedInfo.style.display = 'block';
            } else {
                closedInfo.style.display = 'none';
            }
            
            // Права на редактирование
            typeSelect.disabled = !perms.canEditCcFields;
            coreSelect.disabled = !perms.canEditCcFields;
            statusSelect.disabled = !perms.canEditCcFields;
            if (equipSelect) equipSelect.disabled = !perms.canEditCcFields;
            dateInput.disabled = !perms.canEditCcFields;
            timeInput.disabled = !perms.canEditCcFields;
            addsInput.disabled = !perms.canEditCcFields;
            shiftInput.disabled = !perms.canEditCcFields;
            
            // Кнопки
            const saveBtn = document.getElementById('orderModalSaveBtn');
            const openFullBtn = document.getElementById('orderModalOpenFullBtn');
            
            saveBtn.style.display = perms.canEditCcFields ? 'inline-flex' : 'none';
            openFullBtn.style.display = perms.isDeveloper ? 'inline-flex' : 'none';
            openFullBtn.href = crmUrl(`/orders/${orderId}`);
            
            loading.style.display = 'none';
            content.style.display = 'block';
        } catch (e) {
            console.error(e);
            Toast.error('Ошибка загрузки данных заказа');
            closeOrderModal();
        }
    }
    
    function closeOrderModal() {
        document.getElementById('orderModal').style.display = 'none';
    }
    
    // Обновление скрытого поля datetime при изменении date/time
    function updateDatetimeHidden() {
        const date = document.getElementById('orderModalDate').value;
        const time = document.getElementById('orderModalTime').value;
        if (date && time) {
            document.getElementById('orderModalDatetimeHidden').value = date + 'T' + time;
        }
    }
    
    
    // Закрытие модалок по клику вне них
    document.addEventListener('click', function(e) {
        const orderModal = document.getElementById('orderModal');
        if (orderModal && e.target === orderModal) {
            closeOrderModal();
        }
    });
    
    // Закрытие по Esc
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const orderModal = document.getElementById('orderModal');
            if (orderModal && orderModal.style.display === 'flex') {
                closeOrderModal();
            }
        }
    });
    
    async function submitOrderModal(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const orderId = document.getElementById('orderModalFormId').value;
        
        // Добавляем _method для PUT
        formData.append('_method', 'PUT');
        
        const response = await fetch(crmUrl(`/orders/${orderId}`), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            closeOrderModal();
            location.reload();
        } else {
            Toast.error(result.message || 'Ошибка при сохранении');
        }
    }
    
    // Модальное окно звонка (Mango Office)
    function openCallModal() {
        const modal = document.getElementById('callModal');
        if (modal) {
            // Загружаем сохранённый внутренний номер из localStorage
            const savedExtension = localStorage.getItem('ats_extension');
            if (savedExtension) {
                document.querySelector('#callForm input[name="extension"]').value = savedExtension;
            }
            modal.style.display = 'flex';
        }
    }
    
    function closeCallModal() {
        const modal = document.getElementById('callModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    async function submitCall(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Сохраняем внутренний номер в localStorage для удобства
        const extension = formData.get('extension');
        localStorage.setItem('ats_extension', extension);
        
        // Блокируем кнопку
        submitBtn.disabled = true;
        submitBtn.innerHTML = '{!! icon("phone") !!} Соединение...';
        
        try {
            const response = await fetch(crmUrl('/persons/{{ $person->person_id }}/call'), {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                Toast.success(result.message || 'Звонок инициирован');
                closeCallModal();
            } else {
                Toast.error(result.message || 'Ошибка при инициации звонка');
            }
        } catch (e) {
            console.error(e);
            Toast.error('Ошибка соединения с сервером');
        } finally {
            // Разблокируем кнопку
            submitBtn.disabled = false;
            submitBtn.innerHTML = '{!! icon("phone") !!} Позвонить';
        }
    }
    
    // Закрытие модального окна звонка по клику вне
    document.addEventListener('click', function(e) {
        const callModal = document.getElementById('callModal');
        if (callModal && e.target === callModal) {
            closeCallModal();
        }
    });
</script>
@endpush
