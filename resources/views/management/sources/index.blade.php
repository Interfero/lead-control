@extends('layouts.app')

@section('title', 'Источники заказов')

@section('content')
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'Источники заказов', 'url' => null],
                ]"
            />
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <nav class="flex flex-wrap gap-1 rounded-md border border-border bg-muted/40 p-1 text-sm" aria-label="Фильтр списка источников">
                <a
                    href="{{ route('management.sources.index') }}"
                    class="rounded px-3 py-1.5 font-medium no-underline transition-colors {{ ($groupedView ?? false) ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}"
                >Все</a>
                <a
                    href="{{ route('management.sources.index', ['kind' => \App\Models\Source::KIND_FLYER]) }}"
                    class="rounded px-3 py-1.5 font-medium no-underline transition-colors {{ ($kind ?? null) === \App\Models\Source::KIND_FLYER ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}"
                >Листовки</a>
                <a
                    href="{{ route('management.sources.index', ['superpart' => 1]) }}"
                    class="rounded px-3 py-1.5 font-medium no-underline transition-colors {{ ($superpartOnly ?? false) ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}"
                >SuperPart</a>
                <a
                    href="{{ route('management.sources.index', ['without_phone' => 1]) }}"
                    class="rounded px-3 py-1.5 font-medium no-underline transition-colors {{ ($withoutPhone ?? false) ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}"
                    title="Боевые линии без DID-телефона (SuperPart-парты не входят)"
                >Без телефона@if(($gapStats['without_phone'] ?? 0) > 0) ({{ $gapStats['without_phone'] }})@endif</a>
                <a
                    href="{{ route('orders.index', ['without_source' => 1]) }}"
                    class="rounded px-3 py-1.5 font-medium no-underline transition-colors text-muted-foreground hover:text-foreground"
                    title="Заказы без привязанного источника"
                >Без источника@if(($ordersWithoutSource ?? 0) > 0) ({{ $ordersWithoutSource }})@endif</a>
            </nav>
            @if (! ($groupedView ?? false))
                <form method="GET" action="{{ route('management.sources.index') }}" class="flex items-center gap-2">
                    @if ($superpartOnly ?? false)
                        <input type="hidden" name="superpart" value="1">
                    @endif
                    @if ($kind ?? null)
                        <input type="hidden" name="kind" value="{{ $kind }}">
                    @endif
                    @if ($withoutPhone ?? false)
                        <input type="hidden" name="without_phone" value="1">
                    @endif
                    <select name="city_id" class="form-input text-sm" onchange="this.form.submit()">
                        <option value="">Все города</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->city_id }}" {{ (string) ($cityFilterId ?? '') === (string) $city->city_id ? 'selected' : '' }}>
                                {{ $city->city_name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
            <x-ui.button
                href="{{ route('management.sources.import') }}"
                variant="secondary"
                class="gap-2 rounded-md px-4 py-1.5 text-sm"
            >
                {!! icon('upload') !!}
                Импорт
            </x-ui.button>
            <x-ui.button
                href="{{ route('management.sources.create') }}"
                class="gap-2 rounded-md px-4 py-1.5 text-sm"
            >
                {!! icon('add') !!}
                Добавить
            </x-ui.button>
        </div>
    </div>


    <x-ui.card padding="none" class="mb-4 min-w-0 max-w-full">
        @if ($groupedView ?? false)
            @php
                $hasAny = ($superpartSources ?? collect())->isNotEmpty() || ($sourcesByCity ?? collect())->isNotEmpty();
            @endphp
            @if (! $hasAny)
                <div class="p-6 text-muted-foreground">Источники пока не добавлены.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-sticky orders-sticky-table w-full min-w-0 text-sm">
                        @include('management.sources._index-head')
                        <tbody>
                            @if (($superpartSources ?? collect())->isNotEmpty())
                                <tr class="bg-muted/70">
                                    <td colspan="10" class="py-2 pl-3 text-sm font-semibold">Партнёры (SuperPart, онлайн)</td>
                                </tr>
                                @foreach ($superpartSources as $source)
                                    @include('management.sources._index-row', ['source' => $source])
                                @endforeach
                            @endif

                            @foreach ($sourcesByCity as $cityName => $citySources)
                                <tr class="bg-muted/70">
                                    <td colspan="10" class="py-2 pl-3 text-sm font-semibold">{{ $cityName }}</td>
                                </tr>
                                @foreach ($citySources as $source)
                                    @include('management.sources._index-row', ['source' => $source])
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @elseif (($sources ?? null)?->isEmpty())
            <div class="p-6 text-muted-foreground">Источники пока не добавлены.</div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sticky orders-sticky-table w-full min-w-0 text-sm">
                    @include('management.sources._index-head')
                    <tbody>
                        @foreach ($sources as $source)
                            @include('management.sources._index-row', ['source' => $source])
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($sources->hasPages())
                <div class="border-t border-border p-4">{{ $sources->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection
