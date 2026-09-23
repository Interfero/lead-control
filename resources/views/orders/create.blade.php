@extends('layouts.app')

@section('title', 'Создание заказа')

@section('content')
<div class="max-w-[700px] mx-auto">
    <x-ui.card>
        <h1 class="text-xl font-semibold mb-6">Создание заказа</h1>

        <div class="p-3 bg-muted rounded-md mb-4">
            <strong>Клиент:</strong> {{ $person->person_name }}
            @if($person->phones->count())
                — <x-phone-masked :phone="$person->phones->first()" :show-adds="false" />
            @endif
        </div>

        <div id="cityOpenTimePanel" class="mb-6 hidden rounded-lg border-2 border-primary/40 bg-primary/10 p-4 shadow-sm">
            <div class="flex items-start gap-2">
                <span class="text-lg" aria-hidden="true">📌</span>
                <div class="min-w-0">
                    <div class="font-semibold text-primary">Закрепление города</div>
                    <div id="cityOpenTimeCity" class="mt-0.5 text-sm font-medium text-foreground"></div>
                    <div id="cityOpenTimeSummary" class="mt-2 text-sm text-muted-foreground whitespace-pre-wrap"></div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('orders.store') }}">
            @csrf
            <input type="hidden" name="person_id" value="{{ $person->person_id }}">
            <input type="hidden" name="order_core" id="orderCoreHidden" value="core">

            <x-ui.form-group label="Адрес заказа" name="address_id" required class="mb-4">
                <x-ui.select name="address_id" required id="addressSelect" onchange="updateCityTime()">
                    <option value="">Выберите адрес</option>
                    @foreach($person->addresses as $address)
                        <option value="{{ $address->address_id }}" data-city-id="{{ $address->city_id }}">
                            {{ $address->city->city_name }} — {{ $address->street }}, {{ $address->house }}{{ $address->flat ? ', кв. ' . $address->flat : '' }}
                        </option>
                    @endforeach
                </x-ui.select>
            </x-ui.form-group>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <x-ui.form-group label="Вид техники" name="equipment_type" required class="mb-0">
                    <x-ui.select name="equipment_type" id="equipmentTypeSelect" required onchange="updateOrderCore(this)">
                        <option value="">Выберите</option>
                        @foreach(\App\Models\Order::EQUIPMENT_TYPES as $code => $label)
                            @php $core = in_array($code, \App\Models\Order::CORE_EQUIPMENT) ? 'core' : 'non_core'; @endphp
                            <option value="{{ $code }}" data-core="{{ $core }}" {{ old('equipment_type') == $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.form-group>
                <x-ui.form-group label="Профильность" name="order_core_display" class="mb-0">
                    <x-ui.input
                        type="text"
                        id="orderCoreDisplay"
                        readonly
                        class="bg-muted cursor-default"
                        value="{{ old('equipment_type') ? (in_array(old('equipment_type'), \App\Models\Order::CORE_EQUIPMENT) ? 'Профильный' : 'Непрофильный') : '—' }}"
                        placeholder="Выберите вид техники"
                    />
                </x-ui.form-group>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <x-ui.form-group label="Тип заказа" name="order_type" required class="mb-0">
                    <x-ui.select name="order_type" required>
                        <option value="new" {{ old('order_type') == 'new' ? 'selected' : '' }}>Впервые</option>
                        <option value="repeat" {{ old('order_type') == 'repeat' ? 'selected' : '' }}>Повтор</option>
                        <option value="warranty" {{ old('order_type') == 'warranty' ? 'selected' : '' }}>Гарантия</option>
                    </x-ui.select>
                </x-ui.form-group>
                <x-ui.form-group label="Статус" name="order_status" required class="mb-0">
                    <x-ui.select name="order_status" required>
                        @foreach($statusesForCreate as $code)
                            <option value="{{ $code }}" {{ old('order_status') == $code ? 'selected' : '' }}>{{ $statusLabels[$code] ?? $code }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.form-group>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <x-ui.form-group label="Дата" name="order_date" required class="mb-0">
                    <x-ui.input type="date" name="order_date" required value="{{ old('order_date', now()->format('Y-m-d')) }}" />
                </x-ui.form-group>
                <x-ui.form-group label="Время" name="order_time" required class="mb-0">
                    <x-ui.input type="time" name="order_time" required value="{{ old('order_time') }}" />
                </x-ui.form-group>
            </div>

            <x-ui.form-group label="Описание заказа" name="order_adds" class="mb-4">
                <x-ui.textarea name="order_adds" rows="5" placeholder="Опишите проблему клиента...">{{ old('order_adds') }}</x-ui.textarea>
            </x-ui.form-group>

            @if(auth()->user()->hasAnyRole(['call_center', 'senior_dispatcher']))
                <x-ui.form-group label="Дальний выезд" name="is_long_trip" class="mb-4">
                    <x-ui.select name="is_long_trip">
                        <option value="0" {{ (string) old('is_long_trip', '0') === '0' ? 'selected' : '' }}>Нет</option>
                        <option value="1" {{ (string) old('is_long_trip', '0') === '1' ? 'selected' : '' }}>Да</option>
                    </x-ui.select>
                </x-ui.form-group>
            @endif

            @php
                $sourceOptions = $sources->map(fn ($source) => [
                    'value' => $source->source_id,
                    'label' => $source->display_label,
                ]);
            @endphp
            <x-ui.form-group label="Источник заказа" name="source_id" required class="mb-4">
                <x-ui.searchable-select
                    name="source_id"
                    :options="$sourceOptions"
                    :value="old('source_id')"
                    required
                />
            </x-ui.form-group>

            <x-ui.form-group label="ID партнёра (SuperPart), опционально" name="partner_user_id" class="mb-4">
                <x-ui.input type="number" name="partner_user_id" min="1" value="{{ old('partner_user_id') }}" placeholder="Например 1" />
            </x-ui.form-group>

            <div class="flex flex-col sm:flex-row gap-4 mt-6 max-sm:w-full">
                <x-ui.button type="submit">{!! icon('add') !!} Создать заказ</x-ui.button>
                <x-ui.button href="{{ route('persons.show', $person->person_id) }}" variant="secondary" class="sm:ml-auto max-sm:w-full justify-center">
                    {!! icon('back') !!} Назад
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
@endsection

@push('scripts')
<script>
window.ORDER_CORE_EQUIPMENT = @json(\App\Models\Order::CORE_EQUIPMENT);
window.CITY_OPEN_TIMES_BY_CITY = @json($cityOpenTimesByCityId ?? []);
window.PERSON_CITY_TIME_URL = @json(route('persons.city.time', ['city_id' => 999999999])).replace('999999999', '');

function updateOrderCore(select) {
    const opt = select.options[select.selectedIndex];
    const hidden = document.getElementById('orderCoreHidden');
    const display = document.getElementById('orderCoreDisplay');
    if (opt && opt.value) {
        let core = opt.dataset.core;
        if (!core) {
            if (opt.value === 'other_device') core = 'other';
            else core = window.ORDER_CORE_EQUIPMENT.includes(opt.value) ? 'core' : 'non_core';
        }
        hidden.value = core;
        display.value = core === 'core' ? 'Профильный' : (core === 'other' ? 'Прочий (наш 50% / партнёр 40%)' : 'Непрофильный');
    } else {
        hidden.value = 'core';
        display.value = '—';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('equipmentTypeSelect');
    if (sel) updateOrderCore(sel);

    document.querySelector('input[name="order_date"]')?.addEventListener('change', function() {
        this.dataset.userEdited = '1';
        updateCityTime();
    });
    document.querySelector('input[name="order_time"]')?.addEventListener('change', function() {
        this.dataset.userEdited = '1';
    });

    const addressSelect = document.getElementById('addressSelect');
    if (addressSelect?.value) {
        updateCityTime();
    } else if (addressSelect && addressSelect.options.length === 2) {
        addressSelect.selectedIndex = 1;
        updateCityTime();
    }
});

function renderCityOpenTime(openTime) {
    const panel = document.getElementById('cityOpenTimePanel');
    const summary = document.getElementById('cityOpenTimeSummary');
    const cityLabel = document.getElementById('cityOpenTimeCity');
    if (!panel || !summary) return;

    if (openTime) {
        panel.classList.remove('hidden');
        if (cityLabel) {
            cityLabel.textContent = openTime.city_name || '';
        }
        summary.textContent = openTime.summary || '';
        const dateInput = document.querySelector('input[name="order_date"]');
        const timeInput = document.querySelector('input[name="order_time"]');
        if (openTime.time_from_label) {
            if (dateInput && !dateInput.dataset.userEdited) {
                dateInput.value = openTime.begin_date;
            }
            if (timeInput && !timeInput.dataset.userEdited) {
                timeInput.value = openTime.time_from_label;
            }
        }
    } else {
        panel.classList.add('hidden');
        summary.textContent = '';
        if (cityLabel) cityLabel.textContent = '';
    }
}

function updateCityTime() {
    const select = document.getElementById('addressSelect');
    const option = select?.options[select.selectedIndex];
    if (!option || !option.dataset.cityId) {
        renderCityOpenTime(null);
        return;
    }

    const cityId = option.dataset.cityId;
    const orderDate = document.querySelector('input[name="order_date"]')?.value;
    const cached = window.CITY_OPEN_TIMES_BY_CITY?.[cityId];
    if (cached && (!orderDate || (orderDate >= cached.begin_date && orderDate <= cached.end_date))) {
        renderCityOpenTime(cached);
    }

    const params = new URLSearchParams();
    if (orderDate) params.set('date', orderDate);
    const url = `${window.PERSON_CITY_TIME_URL}/${cityId}/time` + (params.toString() ? `?${params}` : '');

    fetch(url)
        .then(r => r.json())
        .then(data => {
            renderCityOpenTime(data.open_time || null);
        })
        .catch(() => {
            if (!cached) renderCityOpenTime(null);
        });
}
</script>
@endpush
