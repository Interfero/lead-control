@extends('layouts.app')

@php
    use App\Helpers\TimezoneHelper;
@endphp

@section('title', $city->city_name)

@section('navbar_context')
    {{ $city->city_name }}
@endsection

@section('content')
    <div class="mx-auto mb-4 max-w-3xl">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Города', 'url' => route('management.cities.index')],
                ['label' => $city->city_name . ' (ID ' . $city->city_id . ')', 'url' => null],
            ]"
        />
    </div>

    <div class="mx-auto max-w-3xl">
        <x-ui.card>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="m-0 text-base font-semibold">Редактирование города</h3>
                <x-ui.button href="{{ route('management.cities.index') }}" variant="secondary" class="shrink-0 gap-2">
                    {!! icon('back') !!}
                    Назад
                </x-ui.button>
            </div>

            <form method="POST" action="{{ route('management.cities.update', $city->city_id) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label class="form-label">Название *</label>
                    <input type="text" name="city_name" class="form-input" value="{{ old('city_name', $city->city_name) }}" required>
                    @error('city_name')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Часовой пояс *</label>
                    <select name="city_timezone" class="form-input" required>
                        <option value="">Выберите часовой пояс</option>
                        @foreach ($timezones as $timezone)
                            <option value="{{ $timezone }}" {{ old('city_timezone', $city->city_timezone) === $timezone ? 'selected' : '' }}>
                                {{ TimezoneHelper::mskOffsetLabel($timezone) }}
                            </option>
                        @endforeach
                    </select>
                    @error('city_timezone')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">ИНН</label>
                    <input type="text" name="city_inn" class="form-input" value="{{ old('city_inn', $city->city_inn) }}" maxlength="12" inputmode="numeric" placeholder="10 или 12 цифр">
                    <small class="text-muted-foreground">Для подстановки в шаблоны документов</small>
                    @error('city_inn')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Материнский город (для спутников)</label>
                    <select name="parent_city_id" class="form-input">
                        <option value="">Без материнского города</option>
                        @foreach ($parentCities as $parentCity)
                            <option value="{{ $parentCity->city_id }}" {{ (string) old('parent_city_id', $city->parent_city_id) === (string) $parentCity->city_id ? 'selected' : '' }}>
                                {{ $parentCity->city_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('parent_city_id')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="filter-checkbox inline-flex cursor-pointer select-none items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $city->is_active ? '1' : '0') ? 'checked' : '' }}>
                        <span>Активный город</span>
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap justify-end gap-3">
                    <x-ui.button href="{{ route('management.cities.index') }}" variant="secondary">
                        Отмена
                    </x-ui.button>
                    <x-ui.button type="submit" class="gap-2">
                        {!! icon('save') !!}
                        Сохранить
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
