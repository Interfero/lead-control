@props([
    'padding' => 'md',
    'shadow' => false,
])

@php
    $paddingClasses = [
        'none' => '',
        'sm' => 'p-4',
        'md' => 'p-6',
        'lg' => 'p-8',
    ];

    $shadowMap = [
        true => ' shadow-sm dark:shadow-xl',
        'sm' => ' shadow-sm dark:shadow-lg',
        'lg' => ' shadow-lg dark:shadow-2xl',
    ];

    $base = 'bg-card rounded-lg border border-border';
    $p = $paddingClasses[$padding] ?? $paddingClasses['md'];
    $shadowClass = is_string($shadow) || $shadow === true
        ? ($shadowMap[$shadow] ?? $shadowMap[true])
        : '';
@endphp

<div {{ $attributes->merge(['class' => $base . ' ' . $p . $shadowClass]) }}>
    {{ $slot }}
</div>
