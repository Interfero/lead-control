@extends('layouts.app')

@section('title', $workType->name)

@section('content')
    <div class="mx-auto mb-4 max-w-3xl">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Настройки', 'url' => route('settings')],
                ['label' => 'Виды работ', 'url' => route('management.work-types.index')],
                ['label' => $workType->name, 'url' => null],
            ]"
        />
    </div>

    <div class="mx-auto max-w-3xl">
        <x-ui.card>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="m-0 text-base font-semibold">{{ $workType->name }}</h3>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-danger gap-2" onclick="confirmDeleteWorkType()">
                        {!! icon('trash') !!}
                        Удалить
                    </button>
                    <x-ui.button href="{{ route('management.work-types.index') }}" variant="secondary" class="shrink-0 gap-2">
                        {!! icon('back') !!}
                        Назад
                    </x-ui.button>
                </div>
            </div>

            <form method="POST" action="{{ route('management.work-types.update', $workType->work_type_id) }}">
                @csrf
                @method('PUT')
                @include('management.work-types._form')

                <div class="mt-4 flex flex-wrap justify-end gap-3">
                    <x-ui.button href="{{ route('management.work-types.index') }}" variant="secondary">
                        Отмена
                    </x-ui.button>
                    <x-ui.button type="submit" class="gap-2">
                        {!! icon('save') !!}
                        Сохранить
                    </x-ui.button>
                </div>
            </form>

            <form id="delete-work-type-form" action="{{ route('management.work-types.destroy', $workType->work_type_id) }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        </x-ui.card>
    </div>
@endsection

@push('scripts')
    <script>
        function confirmDeleteWorkType() {
            if (confirm(@json('Удалить вид работ «' . $workType->name . '»? Если по нему есть заявки, сервер откажет и предложит деактивировать вместо удаления.'))) {
                document.getElementById('delete-work-type-form').submit();
            }
        }
    </script>
@endpush
