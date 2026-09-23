@props([
    'type' => 'success',
    'dismissible' => false,
])

@php
    $typeClasses = [
        'success' => 'bg-accent border border-primary/50 text-accent-foreground',
        'error' => 'bg-destructive/20 border border-destructive/50 text-destructive',
        'warning' => 'bg-chart-5/15 border border-chart-5/50 text-chart-5',
        'info' => 'bg-chart-2/15 border border-chart-2/50 text-chart-2',
    ];

    $classes =
        'px-4 py-3 rounded-md mb-4 text-sm ' . ($typeClasses[$type] ?? $typeClasses['success']);
@endphp

<div {{ $attributes->merge(['class' => $classes]) }} role="alert">
    {{ $slot }}
</div>
