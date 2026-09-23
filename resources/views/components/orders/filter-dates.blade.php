@props([
    'dateFrom' => null,
    'dateTo' => null,
    'closedFrom' => null,
    'closedTo' => null,
    /** active — только «Дата от/по»; closed — только «Закрыто от/по» */
    'mode' => 'active',
])

<div {{ $attributes->merge(['class' => 'orders-date-filters flex flex-wrap items-end gap-3']) }}>
    @if ($mode !== 'closed')
        <div>
            <label class="mb-1 block text-xs text-muted-foreground">Дата от</label>
            <div class="relative">
                <x-ui.input
                    type="date"
                    name="date_from"
                    value="{{ $dateFrom }}"
                    class="date-filter-input w-[11rem] {{ $dateFrom ? 'pr-8' : '' }}"
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

        <div>
            <label class="mb-1 block text-xs text-muted-foreground">Дата по</label>
            <div class="relative">
                <x-ui.input
                    type="date"
                    name="date_to"
                    value="{{ $dateTo }}"
                    class="date-filter-input w-[11rem] {{ $dateTo ? 'pr-8' : '' }}"
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
    @endif

    @if ($mode !== 'active')
        <div>
            <label class="mb-1 block text-xs text-muted-foreground">Закрыто от</label>
            <div class="relative">
                <x-ui.input
                    type="date"
                    name="closed_from"
                    value="{{ $closedFrom }}"
                    class="date-filter-input w-[11rem] {{ $closedFrom ? 'pr-8' : '' }}"
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

        <div>
            <label class="mb-1 block text-xs text-muted-foreground">Закрыто по</label>
            <div class="relative">
                <x-ui.input
                    type="date"
                    name="closed_to"
                    value="{{ $closedTo }}"
                    class="date-filter-input w-[11rem] {{ $closedTo ? 'pr-8' : '' }}"
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
