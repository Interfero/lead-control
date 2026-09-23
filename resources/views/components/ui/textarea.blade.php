@props([
    'rows' => 4,
    'error' => false,
])

@php
    $base =
        'w-full bg-input border border-border text-foreground text-sm rounded-md px-3 py-2 outline-none placeholder:text-muted-foreground/70 focus:border-ring focus:ring-1 focus:ring-ring transition-colors resize-y';
    $errorClasses = $error
        ? ' border-destructive focus:border-destructive focus:ring-destructive'
        : '';
@endphp

<textarea
    rows="{{ $rows }}"
    {{ $attributes->merge(['class' => $base . $errorClasses]) }}
>{{ $slot }}</textarea>
