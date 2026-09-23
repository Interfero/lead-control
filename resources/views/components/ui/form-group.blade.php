@props([
    'label' => '',
    'name' => '',
    'required' => false,
    'id' => null,
])

@php
    $controlId = $id ?? str_replace(['[', ']'], ['_', ''], $name);
@endphp

<div {{ $attributes->class('mb-4') }}>
    @if ($label !== '')
        <x-ui.label :for="$controlId" :required="$required">{{ $label }}</x-ui.label>
    @endif

    {{ $slot }}

    @error($name)
        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
    @enderror
</div>
