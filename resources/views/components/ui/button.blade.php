@props([
    'variant' => 'primary',
    'size' => 'md',
    'tag' => null,
    'href' => null,
    'type' => 'button',
])

@php
    $variantClasses = [
        'primary' => 'bg-primary hover:bg-primary/90 text-primary-foreground font-medium',
        'secondary' => 'bg-secondary hover:bg-secondary/80 text-secondary-foreground border border-border',
        'ghost' => 'hover:bg-muted text-muted-foreground hover:text-foreground',
        'destructive' => 'bg-destructive hover:bg-destructive/90 text-destructive-foreground',
        'outline' => 'border border-border bg-transparent hover:bg-muted text-foreground',
    ];

    $sizeClasses = [
        'sm' => 'px-3 py-1 text-xs rounded-md',
        'md' => 'px-4 py-2 text-sm rounded-md',
        'lg' => 'px-5 py-2.5 text-sm rounded-md',
        'icon' => 'w-9 h-9 p-0 rounded-md',
    ];

    $base =
        'ui-button inline-flex items-center justify-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 disabled:pointer-events-none';

    $merged = $base . ' ' . ($variantClasses[$variant] ?? $variantClasses['primary']) . ' ' . ($sizeClasses[$size] ?? $sizeClasses['md']);

    $useAnchor = filled($href) || $tag === 'a';
@endphp

@if ($useAnchor)
    <a
        href="{{ $href ?? '#' }}"
        {{ $attributes->merge(['class' => $merged]) }}
    >
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->merge(['class' => $merged]) }}
    >
        {{ $slot }}
    </button>
@endif
