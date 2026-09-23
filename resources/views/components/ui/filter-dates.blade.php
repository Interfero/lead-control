@props([
    'dateFrom' => null,
    'dateTo' => null,
    'closedFrom' => null,
    'closedTo' => null,
    'showClosedDates' => true,
])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
    <div class="flex items-center gap-1">
        <label class="shrink-0 text-[0.6875rem] text-muted-foreground">От</label>
        <div class="relative">
            <x-ui.input
                type="date"
                name="date_from"
                value="{{ $dateFrom }}"
                class="date-filter-input w-[8.5rem] px-2 py-1 text-xs {{ $dateFrom ? 'pr-7' : '' }}"
            />
            @if ($dateFrom)
                <button
                    type="button"
                    class="date-clear-btn absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    data-target="date_from"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            @endif
        </div>
    </div>

    <div class="flex items-center gap-1">
        <label class="shrink-0 text-[0.6875rem] text-muted-foreground">По</label>
        <div class="relative">
            <x-ui.input
                type="date"
                name="date_to"
                value="{{ $dateTo }}"
                class="date-filter-input w-[8.5rem] px-2 py-1 text-xs {{ $dateTo ? 'pr-7' : '' }}"
            />
            @if ($dateTo)
                <button
                    type="button"
                    class="date-clear-btn absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    data-target="date_to"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            @endif
        </div>
    </div>

    @if ($showClosedDates)
        <div class="flex items-center gap-1">
            <label class="shrink-0 text-[0.6875rem] text-muted-foreground">Закр. от</label>
            <div class="relative">
                <x-ui.input
                    type="date"
                    name="closed_from"
                    value="{{ $closedFrom }}"
                    class="date-filter-input w-[8.5rem] px-2 py-1 text-xs {{ $closedFrom ? 'pr-7' : '' }}"
                />
                @if ($closedFrom)
                    <button
                        type="button"
                        class="date-clear-btn absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        data-target="closed_from"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-1">
            <label class="shrink-0 text-[0.6875rem] text-muted-foreground">Закр. по</label>
            <div class="relative">
                <x-ui.input
                    type="date"
                    name="closed_to"
                    value="{{ $closedTo }}"
                    class="date-filter-input w-[8.5rem] px-2 py-1 text-xs {{ $closedTo ? 'pr-7' : '' }}"
                />
                @if ($closedTo)
                    <button
                        type="button"
                        class="date-clear-btn absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        data-target="closed_to"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>
