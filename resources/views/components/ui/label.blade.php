@props([
    'required' => false,
    'for' => null,
])

<label
    @if ($for) for="{{ $for }}" @endif
    {{ $attributes->merge(['class' => 'block text-sm text-muted-foreground mb-1']) }}
>
    {{ $slot }}
    @if ($required)
        <span class="text-destructive">*</span>
    @endif
</label>
