@extends('layouts.app')

@section('title', 'Макеты листовок')

@section('content')
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'Макеты листовок', 'url' => null],
                ]"
            />
        </div>
        <div class="shrink-0">
            <x-ui.button
                href="{{ route('management.flyer-makets.create') }}"
                class="gap-2 rounded-md px-4 py-1.5 text-sm"
            >
                {!! icon('add') !!}
                Добавить макет
            </x-ui.button>
        </div>
    </div>

    <x-ui.card padding="none" class="mb-4 min-w-0 max-w-full">
        @if ($makets->isEmpty())
            <div class="p-6 text-muted-foreground">Макеты пока не добавлены.</div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sticky orders-sticky-table w-full min-w-0 text-sm">
                    <thead>
                        <tr>
                            <th style="width: 72px;">ID</th>
                            <th>Название</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($makets as $maket)
                            @php
                                $editUrl = route('management.flyer-makets.edit', $maket->flyer_maket_id);
                            @endphp
                            <tr
                                class="cursor-pointer hover:bg-muted/60"
                                title="Открыть редактирование"
                                tabindex="0"
                                role="link"
                                onclick="window.location='{{ e($editUrl) }}'"
                                onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location='{{ e($editUrl) }}'; }"
                            >
                                <td>{{ $maket->flyer_maket_id }}</td>
                                <td><strong>{{ $maket->flyer_maket_name }}</strong></td>
                                <td>
                                    @if ($maket->is_active)
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

            @if ($makets->hasPages())
                <div class="border-t border-border p-4">{{ $makets->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection
