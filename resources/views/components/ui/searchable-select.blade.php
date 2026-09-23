@props([
    'name',
    'options' => [],
    'value' => null,
    'placeholder' => 'Начните вводить для поиска…',
    'emptyLabel' => 'Ничего не найдено',
    'required' => false,
    'id' => null,
])

@php
    $normalizedOptions = collect($options)->map(function ($option) {
        if (is_array($option)) {
            return [
                'value' => (string) ($option['value'] ?? ''),
                'label' => (string) ($option['label'] ?? ''),
            ];
        }

        return [
            'value' => (string) ($option->value ?? $option->source_id ?? ''),
            'label' => (string) ($option->label ?? $option->display_label ?? ''),
        ];
    })->values();

    $selectedValue = old($name, $value);
    $selectedLabel = '';
    if ($selectedValue !== null && $selectedValue !== '') {
        $match = $normalizedOptions->first(fn ($opt) => (string) $opt['value'] === (string) $selectedValue);
        $selectedLabel = $match['label'] ?? '';
    }

    $inputId = $id ?? str_replace(['[', ']'], ['_', ''], $name);
@endphp

<div
    class="searchable-select"
    data-searchable-select
    data-options="{{ $normalizedOptions->toJson(JSON_UNESCAPED_UNICODE) }}"
    data-empty-label="{{ $emptyLabel }}"
>
    <input
        type="hidden"
        name="{{ $name }}"
        value="{{ $selectedValue }}"
        @if ($required) required @endif
        data-searchable-value
    >
    <div class="searchable-select__control">
        <input
            type="text"
            id="{{ $inputId }}"
            class="form-input searchable-select__input"
            value="{{ $selectedLabel }}"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            spellcheck="false"
            data-searchable-input
            @if ($required) aria-required="true" @endif
        >
        <button
            type="button"
            class="searchable-select__clear{{ $selectedValue ? '' : ' hidden' }}"
            title="Очистить"
            aria-label="Очистить"
            data-searchable-clear
        >&times;</button>
    </div>
    <div class="searchable-select__dropdown hidden" data-searchable-dropdown role="listbox"></div>
</div>

@once
    @push('scripts')
        <script src="{{ asset('js/searchable-select.js') }}?v={{ filemtime(public_path('js/searchable-select.js')) }}"></script>
    @endpush
@endonce
