@props([
    'name' => 'phone_number',
    'id' => null,
    'value' => '',
    'required' => false,
    'label' => null,
    'form' => null,
])

@php
    $fieldId = $id ?? $name;
@endphp
<div class="phone-input-ru-wrapper">
    @if($label)
        <x-ui.label :for="$fieldId" :required="$required">{{ $label }}</x-ui.label>
    @endif
    <div {{ $attributes->class(['phone-input-ru']) }}>
        <span class="phone-input-ru-prefix">+7</span>
        <input
            type="text"
            name="{{ $name }}"
            id="{{ $fieldId }}"
            value="{{ old($name, $value) }}"
            @if($required) required @endif
            @if($form) form="{{ $form }}" @endif
            autocomplete="tel-national"
            placeholder="9001234567"
            maxlength="10"
            inputmode="numeric"
            data-phone-mask="split"
            class="form-input phone-input-ru-field"
        />
    </div>
</div>
