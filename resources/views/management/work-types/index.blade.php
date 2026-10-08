@extends('layouts.app')

@section('title', 'Виды работ')

@section('content')
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'Настройки', 'url' => route('settings')],
                    ['label' => 'Виды работ', 'url' => null],
                ]"
            />
        </div>
        <div class="shrink-0">
            <x-ui.button href="{{ route('management.work-types.create') }}" class="gap-2 rounded-md px-4 py-1.5 text-sm">
                {!! icon('add') !!}
                Добавить вид работ
            </x-ui.button>
        </div>
    </div>

    @if (session('error'))
        <div class="mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-4 py-2 text-sm text-destructive">
            {{ session('error') }}
        </div>
    @endif

    <x-ui.card padding="none" class="mb-4 min-w-0 max-w-full">
        @if ($workTypes->isEmpty())
            <div class="p-6 text-muted-foreground">Виды работ пока не добавлены.</div>
        @else
            <div class="orders-filters-sticky border-b border-border px-4 py-2 text-sm text-muted-foreground">
                Список видов работ · {{ $workTypes->count() }}
            </div>
            <div class="overflow-x-auto">
                <table class="table orders-sticky-table w-full min-w-0 text-sm">
                    <thead>
                        <tr>
                            <th>Название</th>
                            <th>Код</th>
                            <th>Профильность</th>
                            <th>% партнёру</th>
                            <th>Потолок партнёру</th>
                            <th>% мастеру</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($workTypes as $workType)
                            @php $editUrl = route('management.work-types.edit', $workType->work_type_id); @endphp
                            <tr
                                class="cursor-pointer hover:bg-muted/60"
                                title="Открыть редактирование"
                                tabindex="0"
                                role="link"
                                onclick="window.location='{{ e($editUrl) }}'"
                                onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location='{{ e($editUrl) }}'; }"
                            >
                                <td><strong>{{ $workType->name }}</strong></td>
                                <td><code>{{ $workType->code }}</code></td>
                                <td>{{ \App\Models\Order::orderCoreLabel($workType->order_core) }}</td>
                                <td>{{ rtrim(rtrim(number_format($workType->partner_rate, 2, '.', ''), '0'), '.') }}%</td>
                                <td>{{ number_format($workType->partner_cap, 0, '.', ' ') }} ₽</td>
                                <td>{{ rtrim(rtrim(number_format($workType->master_rate, 2, '.', ''), '0'), '.') }}%</td>
                                <td>
                                    @if ($workType->is_active)
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
        @endif
    </x-ui.card>
@endsection
