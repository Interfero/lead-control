@props([
    'label' => null,
    'id' => null,
])

@php
    $checkboxId = $id ?? $attributes->get('id') ?? 'checkbox-' . uniqid();
@endphp

<div class="flex items-center gap-2">
    <input
        type="checkbox"
        id="{{ $checkboxId }}"
        {{ $attributes->except('id')->merge([
            'class' =>
                'w-4 h-4 rounded border-border bg-input text-primary focus:ring-ring focus:ring-offset-0 focus:ring-2 shrink-0',
        ]) }}
    />
    @if ($label)
        <label for="{{ $checkboxId }}" class="text-sm text-muted-foreground cursor-pointer select-none">
            {{ $label }}
        </label>
    @endif
</div>
