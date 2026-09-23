@extends('layouts.app')

@section('title', 'Редактировать встречу')

@section('content')
<div style="max-width: 800px; margin: 0 auto;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h1 style="font-size: 1.25rem; font-weight: 600; margin: 0;">
                {!! icon('edit') !!} Редактировать встречу
            </h1>
        </div>
        
        <form method="POST" action="{{ route('prom.meetings.update', $meeting) }}">
            @csrf
            @method('PUT')
            
            {{-- Дата и время --}}
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Дата <span style="color: #dc2626;">*</span></label>
                    <input type="date" name="meeting_date" class="form-input" 
                           value="{{ old('meeting_date', $meeting->meeting_datetime->format('Y-m-d')) }}" required>
                    @error('meeting_date')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Время <span style="color: #dc2626;">*</span></label>
                    <input type="time" name="meeting_time" class="form-input" 
                           value="{{ old('meeting_time', $meeting->meeting_datetime->format('H:i')) }}" required>
                    @error('meeting_time')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            
            {{-- Город --}}
            <div class="form-group">
                <label class="form-label">Город <span style="color: #dc2626;">*</span></label>
                <select name="city_id" class="form-input" required>
                    <option value="">Выберите город</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->city_id }}" {{ old('city_id', $meeting->city_id) == $city->city_id ? 'selected' : '' }}>
                            {{ $city->city_name }}
                        </option>
                    @endforeach
                </select>
                @error('city_id')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>
            
            <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid #e5e7eb;">
            <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Данные человека</h3>
            
            {{-- ФИО --}}
            <div class="form-group">
                <label class="form-label">ФИО <span style="color: #dc2626;">*</span></label>
                <input type="text" name="person_name" class="form-input" 
                       value="{{ old('person_name', $meeting->person_name) }}" required>
                @error('person_name')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>
            
            {{-- Телефон, Telegram, Возраст --}}
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Телефон</label>
                    <div class="phone-input-ru">
                        <span class="phone-input-ru-prefix">+7</span>
                        <input type="text" name="person_phone" class="form-input phone-input-ru-field"
                               value="{{ old('person_phone', $meeting->person_phone) }}" maxlength="10" placeholder="9001234567"
                               inputmode="numeric" data-phone-mask="split">
                    </div>
                    @error('person_phone')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Telegram</label>
                    <input type="text" name="person_telegram" class="form-input" 
                           value="{{ old('person_telegram', $meeting->person_telegram) }}" placeholder="@username">
                    @error('person_telegram')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Возраст</label>
                    <input type="number" name="person_age" class="form-input" 
                           value="{{ old('person_age', $meeting->person_age) }}" min="14" max="99">
                    @error('person_age')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            
            {{-- Адрес --}}
            <div class="form-group">
                <label class="form-label">Адрес</label>
                <textarea name="person_address" class="form-input" rows="2">{{ old('person_address', $meeting->person_address) }}</textarea>
                @error('person_address')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>
            
            {{-- Собеседование и Источник --}}
            @php
                $isInterviewValue = old('is_interview', $meeting->is_interview ? 'yes' : 'no');
            @endphp
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Собеседование <span style="color: #dc2626;">*</span></label>
                    <select name="is_interview" class="form-input" required>
                        <option value="no" {{ $isInterviewValue === 'no' ? 'selected' : '' }}>Нет</option>
                        <option value="yes" {{ $isInterviewValue === 'yes' ? 'selected' : '' }}>Да</option>
                    </select>
                    <small style="display: block; color: #6b7280; font-size: 0.75rem; margin-top: 0.25rem;">
                        При "Да" будет создан промоутер
                    </small>
                    @error('is_interview')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Источник (откуда пришёл)</label>
                    <select name="person_source" class="form-input">
                        <option value="">Не указан</option>
                        <option value="head_hunter" {{ old('person_source', $meeting->person_source) === 'head_hunter' ? 'selected' : '' }}>Head Hunter</option>
                        <option value="olx" {{ old('person_source', $meeting->person_source) === 'olx' ? 'selected' : '' }}>OLX</option>
                        <option value="recommendation" {{ old('person_source', $meeting->person_source) === 'recommendation' ? 'selected' : '' }}>Рекомендация</option>
                    </select>
                    @error('person_source')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            
            <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid #e5e7eb;">
            <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Работа</h3>
            
            {{-- Маршрут и Макет --}}
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Маршрут</label>
                    <select name="route_id" class="form-input">
                        <option value="">Не выбран</option>
                        @foreach($routes as $route)
                            <option value="{{ $route->route_id }}" {{ old('route_id', $meeting->route_id) == $route->route_id ? 'selected' : '' }}>
                                {{ $route->route_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('route_id')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Макет</label>
                    <select name="maket_id" class="form-input">
                        <option value="">Не выбран</option>
                        @foreach($makets as $maket)
                            <option value="{{ $maket->maket_id }}" {{ old('maket_id', $meeting->maket_id) == $maket->maket_id ? 'selected' : '' }}>
                                {{ $maket->maket_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('maket_id')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            
            {{-- Листовок выдано и Статус --}}
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Листовок выдано</label>
                    <input type="number" name="leaflets_issued" class="form-input" 
                           value="{{ old('leaflets_issued', $meeting->leaflets_issued) }}" min="0">
                    @error('leaflets_issued')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Статус <span style="color: #dc2626;">*</span></label>
                    <select name="meeting_status" class="form-input" required>
                        @foreach($statuses as $code => $label)
                            <option value="{{ $code }}" {{ old('meeting_status', $meeting->meeting_status) === $code ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('meeting_status')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>
            
            {{-- Комментарий --}}
            <div class="form-group">
                <label class="form-label">Комментарий</label>
                <textarea name="meeting_comment" class="form-input" rows="2">{{ old('meeting_comment', $meeting->meeting_comment) }}</textarea>
                @error('meeting_comment')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>
            
            {{-- Кнопки --}}
            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    {!! icon('save') !!} Сохранить
                </button>
                <a href="{{ route('prom.journal.meetings') }}" class="btn btn-secondary" style="margin-left: auto;">
                    {!! icon('back') !!} Назад
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
