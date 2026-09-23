@extends('layouts.app')

@php
    use App\Helpers\TimezoneHelper;
@endphp

@section('title', 'Города')

@section('content')
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'Города', 'url' => null],
                ]"
            />
        </div>
        <div class="shrink-0">
            <x-ui.button
                href="{{ route('management.cities.create') }}"
                class="gap-2 rounded-md px-4 py-1.5 text-sm"
            >
                {!! icon('add') !!}
                Добавить город
            </x-ui.button>
        </div>
    </div>

    <x-ui.card padding="none" class="mb-4 min-w-0 max-w-full">
        @if ($cities->isEmpty())
            <div class="p-6 text-muted-foreground">Города пока не добавлены.</div>
        @else
            <div class="orders-filters-sticky border-b border-border px-4 py-2 text-sm text-muted-foreground">
                Список городов · {{ $cities->count() }}
            </div>
            <div class="overflow-x-auto">
                <table class="table orders-sticky-table w-full min-w-0 text-sm">
                    <thead>
                        <tr>
                            <th style="width: 72px;">ID</th>
                            <th>Название</th>
                            <th>Часовой пояс</th>
                            <th>Материнский город</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cities as $city)
                            @php
                                $cityEditUrl = route('management.cities.edit', $city->city_id);
                            @endphp
                            <tr
                                class="cursor-pointer hover:bg-muted/60"
                                title="Открыть редактирование"
                                tabindex="0"
                                role="link"
                                onclick="window.location='{{ e($cityEditUrl) }}'"
                                onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location='{{ e($cityEditUrl) }}'; }"
                            >
                                <td>{{ $city->city_id }}</td>
                                <td><strong>{{ $city->city_name }}</strong></td>
                                <td>{{ TimezoneHelper::mskOffsetLabel($city->city_timezone) }}</td>
                                <td>{{ $city->parentCity?->city_name ?? '—' }}</td>
                                <td>
                                    @if ($city->is_active)
                                        <span class="inline-flex items-center rounded-md bg-primary/15 px-2 py-0.5 text-xs font-medium text-primary">
                                            Активен
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                            Неактивен
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($cities->hasPages())
                <div class="border-t border-border p-4">{{ $cities->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection
