@extends('layouts.app')

@section('title', 'Редактировать запись')

@section('content')
<div style="max-width: 800px; margin: 0 auto;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h1 style="font-size: 1.25rem; font-weight: 600; margin: 0;">
                {!! icon('edit') !!} Редактировать запись
            </h1>
        </div>
        
        <form method="POST" action="{{ route('prom.appointments.update', $appointment) }}">
            @csrf
            @method('PUT')
            
            {{-- Дата и время --}}
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Дата <span style="color: #dc2626;">*</span></label>
                    <input type="date" name="appointment_date" class="form-input" 
                           value="{{ old('appointment_date', $appointment->appointment_datetime->format('Y-m-d')) }}" required>
                    @error('appointment_date')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Время <span style="color: #dc2626;">*</span></label>
                    <input type="time" name="appointment_time" class="form-input" 
                           value="{{ old('appointment_time', $appointment->appointment_datetime->format('H:i')) }}" required>
                    @error('appointment_time')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            
            {{-- Город и Собеседование --}}
            @php
                $isInterviewValue = old('is_interview', $appointment->is_interview ? 'yes' : 'no');
            @endphp
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Город <span style="color: #dc2626;">*</span></label>
                    <select name="city_id" id="city_id" class="form-input" required>
                        <option value="">Выберите город</option>
                        @foreach($cities as $city)
                            <option value="{{ $city->city_id }}" {{ old('city_id', $appointment->city_id) == $city->city_id ? 'selected' : '' }}>
                                {{ $city->city_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('city_id')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Собеседование <span style="color: #dc2626;">*</span></label>
                    <select name="is_interview" class="form-input" required>
                        <option value="no" {{ $isInterviewValue === 'no' ? 'selected' : '' }}>Нет</option>
                        <option value="yes" {{ $isInterviewValue === 'yes' ? 'selected' : '' }}>Да</option>
                    </select>
                    @error('is_interview')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            
            {{-- Имя --}}
            <div class="form-group">
                <label class="form-label">Имя <span style="color: #dc2626;">*</span></label>
                <input type="text" name="person_name" class="form-input" 
                       value="{{ old('person_name', $appointment->person_name) }}" required>
                @error('person_name')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>
            
            {{-- Телефон и Telegram --}}
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Телефон</label>
                    <div class="phone-input-ru">
                        <span class="phone-input-ru-prefix">+7</span>
                        <input type="text" name="person_phone" class="form-input phone-input-ru-field"
                               value="{{ old('person_phone', $appointment->person_phone) }}" maxlength="10" placeholder="9001234567"
                               inputmode="numeric" data-phone-mask="split">
                    </div>
                    @error('person_phone')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Telegram</label>
                    <input type="text" name="person_telegram" class="form-input" 
                           value="{{ old('person_telegram', $appointment->person_telegram) }}" placeholder="@username">
                    @error('person_telegram')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            
            {{-- Район и Листовок --}}
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Район</label>
                    <select name="district_id" id="district_id" class="form-input">
                        <option value="">Не выбран</option>
                    </select>
                    @error('district_id')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Листовок подготовить</label>
                    <input type="number" name="leaflets_to_prepare" class="form-input" 
                           value="{{ old('leaflets_to_prepare', $appointment->leaflets_to_prepare) }}" min="0">
                    @error('leaflets_to_prepare')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            
            {{-- Статус --}}
            <div class="form-group">
                <label class="form-label">Статус <span style="color: #dc2626;">*</span></label>
                <select name="appointment_status" class="form-input" required>
                    @foreach($statuses as $code => $label)
                        <option value="{{ $code }}" {{ old('appointment_status', $appointment->appointment_status) === $code ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('appointment_status')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>
            
            {{-- Комментарий --}}
            <div class="form-group">
                <label class="form-label">Комментарий</label>
                <textarea name="appointment_comment" class="form-input" rows="2">{{ old('appointment_comment', $appointment->appointment_comment) }}</textarea>
                @error('appointment_comment')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>
            
            {{-- Кнопки --}}
            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    {!! icon('save') !!} Сохранить
                </button>
                <a href="{{ route('prom.journal.appointments') }}" class="btn btn-secondary" style="margin-left: auto;">
                    {!! icon('back') !!} Назад
                </a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Фильтрация районов по городу
    const districts = @json($districts);
    const currentCityId = {{ $appointment->city_id }};
    const currentDistrictId = {{ $appointment->district_id ?? 'null' }};
    
    function updateDistricts() {
        const cityId = document.getElementById('city_id').value;
        const districtSelect = document.getElementById('district_id');
        
        districtSelect.innerHTML = '<option value="">Не выбран</option>';
        
        if (cityId && districts[cityId]) {
            districts[cityId].forEach(function(district) {
                const option = document.createElement('option');
                option.value = district.district_id;
                option.textContent = district.district_name;
                if (currentDistrictId && district.district_id == currentDistrictId) {
                    option.selected = true;
                }
                districtSelect.appendChild(option);
            });
        }
    }
    
    document.getElementById('city_id').addEventListener('change', updateDistricts);
    
    // Инициализация при загрузке
    updateDistricts();
</script>
@endpush
@endsection
