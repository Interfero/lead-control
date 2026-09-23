@extends('layouts.app')

@section('title', 'Импорт источников')

@section('content')
    <div class="mx-auto mb-4 max-w-3xl">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Источники заказов', 'url' => route('management.sources.index')],
                ['label' => 'Импорт из файла', 'url' => null],
            ]"
        />
    </div>

    <div class="mx-auto max-w-3xl">
        <x-ui.card>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="m-0 text-base font-semibold">Импорт источников из CSV</h3>
                <x-ui.button href="{{ route('management.sources.index') }}" variant="secondary" class="shrink-0 gap-2">
                    {!! icon('back') !!}
                    Назад
                </x-ui.button>
            </div>

            <p class="mb-4 text-sm text-muted-foreground">
                Разделитель — точка с запятой или запятая. Кодировка UTF-8.
                Колонки: <strong>Название</strong>, Телефон, Формат, Город, <strong>Тип</strong> (flyer / party), URL, Ссылка (0/1), SuperPart, Активен, Макет.
            </p>

            <p class="mb-4">
                <a href="{{ route('management.sources.import.template') }}" class="text-primary hover:underline">Скачать шаблон CSV</a>
            </p>

            <form method="POST" action="{{ route('management.sources.import.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label class="form-label">Файл CSV *</label>
                    <input type="file" name="file" class="form-input" accept=".csv,text/csv" required>
                    @error('file')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="mt-4 flex flex-wrap justify-end gap-3">
                    <x-ui.button href="{{ route('management.sources.index') }}" variant="secondary">Отмена</x-ui.button>
                    <x-ui.button type="submit" class="gap-2">
                        {!! icon('upload') !!}
                        Загрузить
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
@endsection
