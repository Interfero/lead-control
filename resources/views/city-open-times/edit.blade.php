@extends('layouts.app')

@section('title', 'Закрепление #'.$pin->city_open_time_id)

@section('content')
    <div class="mx-auto mb-4 max-w-3xl">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Закрепление времени', 'url' => route('city-open-times.index')],
                ['label' => 'Редактирование', 'url' => null],
            ]"
        />
    </div>

    <div class="mx-auto max-w-3xl">
        <x-ui.card>
            <h1 class="mb-4 text-base font-semibold">Закрепление времени работы</h1>

            <form method="POST" action="{{ route('city-open-times.update', $pin->city_open_time_id) }}">
                @csrf
                @method('PUT')
                @include('city-open-times._form', ['pin' => $pin, 'cities' => $cities])

                <div class="mt-4 flex flex-wrap justify-between gap-3">
                    <button type="button" class="btn btn-danger gap-2" onclick="confirmDeletePin()">
                        {!! icon('delete') !!}
                        Удалить
                    </button>
                    <div class="flex flex-wrap gap-3">
                        <x-ui.button href="{{ route('city-open-times.index') }}" variant="secondary">Закрыть</x-ui.button>
                        <x-ui.button type="submit" class="gap-2">{!! icon('save') !!} Сохранить</x-ui.button>
                    </div>
                </div>
            </form>

            <form id="delete-pin-form" action="{{ route('city-open-times.destroy', $pin->city_open_time_id) }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        </x-ui.card>
    </div>
@endsection

@push('scripts')
    <script>
        function confirmDeletePin() {
            if (confirm('Удалить закрепление навсегда?')) {
                document.getElementById('delete-pin-form').submit();
            }
        }
    </script>
@endpush
