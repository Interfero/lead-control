@extends('layouts.app')

@section('title', $maket->flyer_maket_name)

@section('navbar_context')
    {{ $maket->flyer_maket_name }}
@endsection

@section('content')
    <div class="mx-auto mb-4 max-w-3xl">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Макеты листовок', 'url' => route('management.flyer-makets.index')],
                ['label' => $maket->flyer_maket_name . ' (ID ' . $maket->flyer_maket_id . ')', 'url' => null],
            ]"
        />
    </div>

    <div class="mx-auto max-w-3xl">
        <x-ui.card>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="m-0 text-base font-semibold">Редактирование макета</h3>
                <x-ui.button href="{{ route('management.flyer-makets.index') }}" variant="secondary" class="shrink-0 gap-2">
                    {!! icon('back') !!}
                    Назад
                </x-ui.button>
            </div>

            <form method="POST" action="{{ route('management.flyer-makets.update', $maket->flyer_maket_id) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label class="form-label">Название *</label>
                    <input type="text" name="flyer_maket_name" class="form-input" value="{{ old('flyer_maket_name', $maket->flyer_maket_name) }}" required>
                    @error('flyer_maket_name')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="filter-checkbox inline-flex cursor-pointer select-none items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $maket->is_active ? '1' : '0') ? 'checked' : '' }}>
                        <span>Активный макет</span>
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap justify-end gap-3">
                    <x-ui.button href="{{ route('management.flyer-makets.index') }}" variant="secondary">
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
