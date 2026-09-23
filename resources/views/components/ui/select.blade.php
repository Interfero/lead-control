@props([
    'size' => 'md',
    'error' => false,
])

@php
    $sizeClasses = [
        'md' =>
            'w-full bg-input border border-border text-foreground text-sm rounded-md px-3 py-2 outline-none focus:border-ring focus:ring-1 focus:ring-ring transition-colors',
        'sm' =>
            'w-full bg-input border border-border text-foreground text-xs rounded-md px-2 py-1.5 outline-none focus:border-ring focus:ring-1 focus:ring-ring transition-colors',
    ];

    $errorClasses = $error
        ? ' border-destructive focus:border-destructive focus:ring-destructive'
        : '';
@endphp

<select {{ $attributes->merge(['class' => ($sizeClasses[$size] ?? $sizeClasses['md']) . $errorClasses]) }}>
    {{ $slot }}
</select>
