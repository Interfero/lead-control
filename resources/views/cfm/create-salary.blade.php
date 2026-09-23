@extends('layouts.app')

@section('title', $pageTitle)

@php
    $fmt = fn (int $n) => number_format($n, 0, ',', ' ');
    $available = (int) ($balances['available'] ?? 0);
@endphp

@section('content')
<div class="salary-page">
    <div class="salary-page__crumbs">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Касса', 'url' => route('cfm.index')],
                ['label' => 'Расчёт ЗП', 'url' => route('cfm.salary.index')],
                ['label' => $pageTitle, 'url' => null],
            ]"
        />
    </div>

    <div class="salary-detail">
        <h1 class="salary-page__title" style="font-size: 1.25rem;">{{ $pageTitle }}</h1>

        @if($calculation)
            <div class="salary-metrics" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                <div class="salary-metric">
                    <div class="salary-metric__label">Начислено</div>
                    <div class="salary-metric__value is-dark">{{ $fmt((int)$calculation->accrued_amount) }} ₽</div>
                </div>
                <div class="salary-metric">
                    <div class="salary-metric__label">Уже выплачено</div>
                    <div class="salary-metric__value is-dark">{{ $fmt((int)($balances['paid'] ?? 0)) }} ₽</div>
                </div>
                <div class="salary-metric">
                    <div class="salary-metric__label">Доступно к выводу</div>
                    <div class="salary-metric__value is-avail">{{ $fmt($available) }} ₽</div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('cfm.store') }}" enctype="multipart/form-data" class="salary-policies">
            @csrf
            <input type="hidden" name="director_type" value="director_salary">
            <input type="hidden" name="auto_close" value="1">
            @if($calculation)
                <input type="hidden" name="salary_calculation_id" value="{{ $calculation->salary_calculation_id }}">
                <input type="hidden" name="city_id" value="{{ $calculation->city_id }}">
                <input type="hidden" name="related_user_id" value="{{ $relatedUserId }}">
            @endif

            <div class="salary-filters__field">
                <span class="salary-filters__label">Город</span>
                <div class="salary-metric__value is-dark" style="font-size: 1rem;">{{ $calculation?->city?->city_name ?? '—' }}</div>
            </div>
            <div class="salary-filters__field">
                <span class="salary-filters__label">Расчётный месяц</span>
                <div>{{ $calculation?->period_month?->format('m.Y') ?? '—' }} · {{ $calculation?->calc_code }}</div>
            </div>
            <div class="salary-filters__field">
                <span class="salary-filters__label">Статья ДДС</span>
                <div>{{ $presetCategory->cfm_cat_name }} (Дир ЗП)</div>
            </div>

            <div class="salary-filters__field">
                <label class="salary-filters__label" for="salaryAmount">Сумма *</label>
                <input type="number" name="amount_cfm" id="salaryAmount" class="salary-amount-input" style="width: 100%; margin: 0;"
                       value="{{ old('amount_cfm') }}" required min="1" max="{{ max(1, $available) }}" step="1" placeholder="0">
                <button type="button" class="salary-btn salary-btn--outline" style="margin-top: 0.5rem; align-self: flex-start;" id="fillAllAvailable"
                        @disabled($available < 1)>Вся доступная сумма</button>
                @error('amount_cfm')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="salary-filters__field">
                <label class="salary-filters__label" for="salaryAdds">Описание *</label>
                <textarea name="cfm_adds" id="salaryAdds" class="salary-filters__select" rows="3" required maxlength="1000"
                    style="min-height: 5rem;">{{ old('cfm_adds', $calculation ? ('Дир ЗП '.$calculation->period_month->format('m.Y')) : '') }}</textarea>
                @error('cfm_adds')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="salary-filters__field">
                <label class="salary-filters__label">Документы</label>
                <input type="file" name="documents[]" class="salary-filters__select" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.odt,.txt,.csv">
            </div>

            <div class="salary-policy-actions">
                <button type="submit" class="salary-btn salary-btn--primary" @disabled($available < 1)>Провести выплату</button>
                <a href="{{ route('cfm.salary.index', ['month' => now()->timezone(config('app.timezone'))->format('Y-m'), 'calc' => $calculation?->salary_calculation_id]) }}"
                   class="salary-btn salary-btn--outline">Отмена</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var btn = document.getElementById('fillAllAvailable');
    var input = document.getElementById('salaryAmount');
    if (!btn || !input) return;
    btn.addEventListener('click', function () { input.value = {{ (int) $available }}; });
})();
</script>
@endpush
