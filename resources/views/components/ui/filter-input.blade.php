@props([
    'type' => 'text',
    'size' => 'sm',
    'error' => false,
])

<div class="relative min-w-0" data-filter-input>
    <x-ui.input
        :type="$type"
        :size="$size"
        :error="$error"
        {{ $attributes->merge(['class' => 'pr-8 table-filter']) }}
    />
    <button
        type="button"
        class="filter-search-btn absolute right-1.5 top-1/2 -translate-y-1/2 p-0.5 rounded text-muted-foreground hover:text-primary transition-colors bg-transparent border-0 cursor-pointer shrink-0"
        title="Применить фильтр"
        aria-label="Применить фильтр"
    >
        <svg class="w-3 h-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </button>
</div>
