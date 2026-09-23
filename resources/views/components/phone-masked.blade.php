@props([
    'phone' => null,
    'number' => null,
    'adds' => null,
    'showAdds' => true,
    'class' => '',
])

@php
    use App\Helpers\PhoneHelper;

    $raw = $phone?->phone_number ?? $number;
    $phoneId = $phone?->phone_id;
    $addsText = $adds ?? $phone?->phone_adds;
    $formatted = PhoneHelper::format($raw);
    $masked = PhoneHelper::maskDisplay($raw);
    $seeFull = PhoneHelper::userSeesFullPhone(auth()->user());
    $canReveal = (bool) config('privacy.phone_reveal_enabled', true);
@endphp

@if (empty($raw))
    <span {{ $attributes->merge(['class' => trim('phone-display phone-display--empty '.$class)]) }}>—</span>
@elseif ($seeFull)
    <span {{ $attributes->merge(['class' => trim('phone-display '.$class)]) }}>
        <span class="phone-display__number">{{ $formatted ?: $raw }}</span>
        @if ($showAdds && $addsText)
            <span class="phone-display__adds"> — {{ $addsText }}</span>
        @endif
    </span>
@elseif ($canReveal && $phoneId)
    <button
        type="button"
        {{ $attributes->merge(['class' => trim('phone-masked '.$class)]) }}
        data-phone-reveal
        data-phone-id="{{ $phoneId }}"
        data-reveal-url="{{ route('persons.phones.reveal', $phoneId) }}"
        title="Нажмите, чтобы показать номер"
    >
        <span class="phone-masked__text" data-phone-text>{{ $masked }}</span>
        @if ($showAdds && $addsText)
            <span class="phone-display__adds"> — {{ $addsText }}</span>
        @endif
        <span class="phone-masked__hint" data-phone-hint>показать</span>
    </button>
@elseif ($canReveal && $raw)
    {{-- Нет phone_id (промо и т.п.): раскрытие локально, без API --}}
    <button
        type="button"
        {{ $attributes->merge(['class' => trim('phone-masked '.$class)]) }}
        data-phone-reveal-local
        data-phone-full="{{ $formatted ?: $raw }}"
        title="Нажмите, чтобы показать номер"
    >
        <span class="phone-masked__text" data-phone-text>{{ $masked }}</span>
        @if ($showAdds && $addsText)
            <span class="phone-display__adds"> — {{ $addsText }}</span>
        @endif
        <span class="phone-masked__hint" data-phone-hint>показать</span>
    </button>
@else
    <span {{ $attributes->merge(['class' => trim('phone-display '.$class)]) }}>{{ $masked }}</span>
@endif
