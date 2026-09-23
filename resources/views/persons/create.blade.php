@extends('layouts.app')

@section('title', 'Создать запись и заказ')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <h2 style="margin: 0;">Создать запись и заказ</h2>
    <a href="{{ route('persons.index') }}" class="btn btn-secondary">
        {!! icon('back') !!} Назад
    </a>
</div>

<div class="card">
    <form id="createForm" class="create-person-form" onsubmit="submitCreate(event)">
        @csrf
        <div class="form-group">
            <label class="form-label">Имя *</label>
            <input type="text" name="person_name" class="form-input" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Примерный возраст (лет)</label>
                <input type="number" name="person_age" class="form-input" min="0" max="150">
            </div>
            <div class="form-group">
                <x-input-phone-ru name="phone_number" id="createPersonPhone" label="Телефон клиента *" :required="true" />
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Город клиента *</label>
            <select name="city_id" id="citySelect" class="form-input" required onchange="updateOrderTime()">
                <option value="">Выберите город</option>
                @foreach($cities as $city)
                    <option value="{{ $city->city_id }}" data-timezone="{{ $city->city_timezone }}">{{ $city->city_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="create-person-address-row">
            <div class="form-group create-person-street">
                <label class="form-label">Улица</label>
                <input type="text" name="street" class="form-input" data-address-safe>
            </div>
            <div class="form-group create-person-house-flat">
                <label class="form-label">Дом</label>
                <input type="text" name="house" class="form-input" data-address-safe>
            </div>
            <div class="form-group create-person-house-flat">
                <label class="form-label">Кв.</label>
                <input type="text" name="flat" class="form-input" data-address-safe>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Комментарий к адресу</label>
            <textarea name="address_adds" class="form-input" rows="2" data-address-safe></textarea>
        </div>

        <div id="orderFields" style="border-top: 1px solid #e5e7eb; padding-top: 1rem; margin-top: 1rem;">
            <h3 style="margin-bottom: 1rem;">Данные заказа <span id="orderCityLabel" style="font-weight: 400; font-size: 0.875rem; color: #6b7280;"></span></h3>
            <div class="order-fields-grid">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Тип заказа *</label>
                    <select name="order_type" class="form-input" required>
                        <option value="new">Впервые</option>
                        <option value="repeat">Повтор</option>
                        <option value="warranty">Гарантия</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Вид работ *</label>
                    <select name="equipment_type" id="equipmentTypeCreate" class="form-input" required onchange="updateOrderCoreCreate(this.value)">
                        <option value="">Выберите вид техники</option>
                        @foreach(\App\Models\Order::EQUIPMENT_TYPES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="order_core" id="orderCoreCreate" value="non_core">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Статус *</label>
                    <select name="order_status" class="form-input" required>
                        @foreach($statusesForCreate as $code)
                            <option value="{{ $code }}">{{ $statusLabels[$code] ?? $code }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="order-fields-grid">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Дата *</label>
                    <input type="date" id="orderDate" name="order_date" class="form-input">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Время встречи *</label>
                    <input type="time" id="orderTime" name="order_time" class="form-input">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Время в городе клиента</label>
                    <input type="text" id="orderLocalTime" class="form-input bg-muted text-muted-foreground" readonly>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Описание заказа</label>
                <textarea name="order_adds" class="form-input" rows="5"></textarea>
            </div>
            @php
                $sourceOptions = $sources->map(fn ($source) => [
                    'value' => $source->source_id,
                    'label' => $source->display_label,
                ]);
            @endphp
            <div class="form-group">
                <label class="form-label">Источник заказа *</label>
                <x-ui.searchable-select
                    name="source_id"
                    :options="$sourceOptions"
                    required
                />
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; margin-top: 1rem;">
            <a href="{{ route('persons.index') }}" class="btn btn-secondary">Отмена</a>
            <button type="submit" class="btn btn-primary">Создать</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    async function updateOrderTime() {
        const citySelect = document.getElementById('citySelect');
        const cityId = citySelect.value;

        if (!cityId) {
            document.getElementById('orderCityLabel').textContent = '';
            document.getElementById('orderLocalTime').value = '';
            const now = new Date();
            const today = now.toISOString().split('T')[0];
            document.getElementById('orderDate').value = today;
            now.setHours(now.getHours() + 1);
            document.getElementById('orderTime').value = now.toTimeString().slice(0, 5);
            return;
        }

        try {
            const response = await fetch(@json(route('persons.city.time', ['city_id' => 999999999])).replace('999999999', cityId), {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (data.success) {
                document.getElementById('orderCityLabel').textContent = `(${data.city_name})`;
                document.getElementById('orderLocalTime').value = data.time;
                const now = new Date();
                document.getElementById('orderDate').value = now.toISOString().split('T')[0];

                const parts = data.time.split(':').map(Number);
                let meetingHours = parts[0] + 1;
                if (meetingHours >= 24) meetingHours -= 24;
                document.getElementById('orderTime').value = String(meetingHours).padStart(2, '0') + ':' + String(parts[1]).padStart(2, '0');
            }
        } catch (e) {
            console.error('Ошибка загрузки времени города:', e);
        }
    }

    function updateOrderCoreCreate(equipmentType) {
        const coreTypes = @json(\App\Models\Order::CORE_EQUIPMENT);
        let core = 'non_core';
        if (equipmentType === 'other_device') core = 'other';
        else if (coreTypes.includes(equipmentType)) core = 'core';
        document.getElementById('orderCoreCreate').value = core;
    }

    async function submitCreate(e) {
        e.preventDefault();
        const form = e.target;
        const sourceId = form.querySelector('[name="source_id"]')?.value;
        if (!sourceId) {
            Toast.error('Выберите источник заказа из списка');
            form.querySelector('[data-searchable-input]')?.focus();
            return;
        }
        const formData = new FormData(form);
        const orderDate = formData.get('order_date');
        const orderTime = formData.get('order_time');
        if (orderDate && orderTime) {
            formData.set('datetime_order', orderDate + 'T' + orderTime);
            formData.delete('order_date');
            formData.delete('order_time');
        }

        const response = await fetch(@json(route('persons.store')), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        });

        let result = {};
        try {
            result = await response.json();
        } catch (err) {
            Toast.error('Ошибка ответа сервера');
            return;
        }

        if (response.ok && result.success) {
            window.location.href = @json(route('persons.show', ['person_id' => 999999999])).replace('999999999', result.person.person_id);
        } else {
            const msg = result.message
                || (result.errors ? Object.values(result.errors).flat().join(' ') : null)
                || 'Ошибка при создании';
            Toast.error(msg);
        }
    }

    updateOrderTime();
</script>
@endpush
