@extends('layouts.app')

@section('title', 'Новое закрепление')

@section('content')
    <div class="mx-auto mb-4 max-w-3xl">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Закрепление времени', 'url' => route('city-open-times.index')],
                ['label' => 'Новое', 'url' => null],
            ]"
        />
    </div>

    <div class="mx-auto max-w-3xl">
        <x-ui.card>
            <h1 class="mb-4 text-base font-semibold">Закрепление: новое</h1>

            <form method="POST" action="{{ route('city-open-times.store') }}">
                @csrf
                @include('city-open-times._form', ['pin' => $pin, 'cities' => $cities])

                <div class="mt-4 flex flex-wrap justify-end gap-3">
                    <x-ui.button href="{{ route('city-open-times.index') }}" variant="secondary">Отмена</x-ui.button>
                    <x-ui.button type="submit" class="gap-2">{!! icon('save') !!} Сохранить</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
