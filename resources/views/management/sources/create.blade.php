@extends('layouts.app')

@section('title', 'Новый источник')

@section('content')
    <div class="mx-auto mb-4 max-w-3xl">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Источники заказов', 'url' => route('management.sources.index')],
                ['label' => 'Новый источник', 'url' => null],
            ]"
        />
    </div>

    <div class="mx-auto max-w-3xl">
        <x-ui.card>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="m-0 text-base font-semibold">Новый источник заказов</h3>
                <x-ui.button href="{{ route('management.sources.index') }}" variant="secondary" class="shrink-0 gap-2">
                    {!! icon('back') !!}
                    Назад
                </x-ui.button>
            </div>

            <form method="POST" action="{{ route('management.sources.store') }}">
                @csrf

                <div class="form-group">
                    <label class="form-label">Название *</label>
                    <input type="text" name="source_name" class="form-input" value="{{ old('source_name') }}" required>
                    @error('source_name')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <x-input-phone-ru name="source_phone" id="sourcePhone" label="Телефон линии" :value="old('source_phone')" />
                    @error('source_phone')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Формат</label>
                    <select name="source_format" class="form-input">
                        <option value="">Не задан</option>
                        <option value="{{ \App\Models\Source::FORMAT_ONLINE }}" {{ old('source_format') === \App\Models\Source::FORMAT_ONLINE ? 'selected' : '' }}>Онлайн</option>
                        <option value="{{ \App\Models\Source::FORMAT_OFFLINE }}" {{ old('source_format') === \App\Models\Source::FORMAT_OFFLINE ? 'selected' : '' }}>Офлайн</option>
                    </select>
                    @error('source_format')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                @include('management.sources._kind-fields', [
                    'sourceKind' => old('source_kind', \App\Models\Source::KIND_FLYER),
                    'useSourceUrl' => old('use_source_url'),
                    'sourceUrl' => old('source_url'),
                    'availableForSuperpart' => old('available_for_superpart'),
                ])

                <div class="form-group">
                    <label class="form-label">Город</label>
                    <select name="city_id" class="form-input">
                        <option value="">Не привязан</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->city_id }}" {{ (string) old('city_id') === (string) $city->city_id ? 'selected' : '' }}>
                                {{ $city->city_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('city_id')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group" id="flyerMaketGroup">
                    <label class="form-label">Макет листовки</label>
                    <select name="flyer_maket_id" class="form-input">
                        <option value="">Не выбран</option>
                        @foreach ($flyerMakets as $maket)
                            <option value="{{ $maket->flyer_maket_id }}" {{ (string) old('flyer_maket_id') === (string) $maket->flyer_maket_id ? 'selected' : '' }}>
                                {{ $maket->flyer_maket_name }}@if (! $maket->is_active) (неактивен) @endif
                            </option>
                        @endforeach
                    </select>
                    @error('flyer_maket_id')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="filter-checkbox inline-flex cursor-pointer select-none items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <span>Активный источник</span>
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap justify-end gap-3">
                    <x-ui.button href="{{ route('management.sources.index') }}" variant="secondary">
                        Отмена
                    </x-ui.button>
                    <x-ui.button type="submit" class="gap-2">
                        {!! icon('save') !!}
                        Создать
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
