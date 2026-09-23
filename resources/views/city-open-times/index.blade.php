@extends('layouts.app')

@section('title', 'Закрепление времени работы')

@section('content')
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Закрепление времени', 'url' => null],
            ]"
        />
        <x-ui.button href="{{ route('city-open-times.create') }}" class="gap-2">
            {!! icon('add') !!}
            Создать
        </x-ui.button>
    </div>

    <x-ui.card padding="none" class="mb-4">
        <form method="GET" action="{{ route('city-open-times.index') }}" class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
            <div class="form-group mb-0">
                <label class="form-label text-xs">Год</label>
                <select name="year" class="form-input text-sm" onchange="this.form.submit()">
                    @for ($y = now()->year - 2; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" @selected((int) ($filters['year'] ?? now()->year) === $y)>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label text-xs">Месяц</label>
                <select name="month" class="form-input text-sm" onchange="this.form.submit()">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int) ($filters['month'] ?? now()->month) === $m)>{{ $m }}</option>
                    @endfor
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label text-xs">Дата от</label>
                <input type="date" name="date_from" class="form-input text-sm" value="{{ $filters['date_from'] ?? '' }}" onchange="this.form.submit()">
            </div>
            <div class="form-group mb-0">
                <label class="form-label text-xs">Дата до</label>
                <input type="date" name="date_to" class="form-input text-sm" value="{{ $filters['date_to'] ?? '' }}" onchange="this.form.submit()">
            </div>
            <div class="form-group mb-0">
                <label class="form-label text-xs">Город</label>
                <select name="city_id" class="form-input text-sm" onchange="this.form.submit()">
                    <option value="">Все</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city->city_id }}" @selected((string) ($filters['city_id'] ?? '') === (string) $city->city_id)>{{ $city->city_name }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.button href="{{ route('city-open-times.index') }}" variant="secondary" size="sm">Сбросить</x-ui.button>
        </form>

        <div class="overflow-x-auto">
            <table class="table w-full text-sm">
                <thead>
                    <tr>
                        <th>Дата, от</th>
                        <th>Дата, по</th>
                        <th>Город</th>
                        <th>Начало работы</th>
                        <th>Автор изменения</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pins as $pin)
                        @php $editUrl = route('city-open-times.edit', $pin->city_open_time_id); @endphp
                        <tr
                            class="cursor-pointer hover:bg-muted/60"
                            onclick="window.location='{{ $editUrl }}'"
                        >
                            <td>{{ $pin->begin_date->format('d.m.y') }}</td>
                            <td>{{ $pin->end_date->format('d.m.y') }}</td>
                            <td>{{ $pin->city?->city_name ?? '—' }}</td>
                            <td>{{ $pin->time_from }}</td>
                            <td>{{ $pin->authorLabel() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-muted-foreground">Записей не найдено</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pins->hasPages())
            <div class="border-t border-border p-4">{{ $pins->links() }}</div>
        @endif
    </x-ui.card>
@endsection
