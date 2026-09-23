@props([
    'route',
    'formats' => ['xlsx', 'csv'],
    'label' => 'Скачать',
])

@php
    $baseQuery = request()->except('export');
    $formatLabels = [
        'xlsx' => 'Excel (.xlsx)',
        'csv' => 'CSV',
    ];
@endphp

<div class="inline-flex flex-wrap items-center gap-2" data-export-menu>
    @foreach ($formats as $format)
        <a
            href="{{ route($route, array_merge($baseQuery, ['export' => $format])) }}"
            class="ui-button inline-flex items-center justify-center gap-2 border border-border bg-transparent px-3 py-1.5 text-xs rounded-md text-foreground hover:bg-muted shrink-0"
            title="Скачать {{ $formatLabels[$format] ?? strtoupper($format) }}"
        >
            {!! icon('download') !!}
            {{ $formatLabels[$format] ?? strtoupper($format) }}
        </a>
    @endforeach
</div>
