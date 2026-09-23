@extends('layouts.app')

@section('title', 'Настройки окладов')

@section('content')
<div class="salary-page">
    <div class="salary-page__crumbs">
        <x-breadcrumbs :items="[
            ['label' => 'Главная', 'url' => route('orders.index')],
            ['label' => 'Касса', 'url' => route('cfm.index')],
            ['label' => 'Расчёт ЗП', 'url' => route('cfm.salary.index')],
            ['label' => 'Настройки окладов', 'url' => null],
        ]" />
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif
    @if($errors->any())
        <x-ui.alert variant="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="salary-page__head">
        <div>
            <h1 class="salary-page__title">Настройки окладов</h1>
            <p class="salary-page__subtitle">Доступно генеральному директору</p>
            <p class="salary-page__lead">
                Включайте оклад для нужного города и задавайте сумму. Оклад прибавляется к доходу с инкассации и входит в лимит выплаты зарплаты.
            </p>
        </div>
        <a href="{{ route('cfm.salary.index') }}" class="salary-btn salary-btn--outline">К расчёту ЗП</a>
    </div>

    <form method="POST" action="{{ route('cfm.salary.policies.save') }}" class="salary-policies" id="salaryPoliciesForm">
        @csrf
        <div class="salary-table-wrap">
            <table class="salary-table salary-table--policies">
                <thead>
                    <tr>
                        <th>Город</th>
                        <th>Оклад включён</th>
                        <th class="is-num">Сумма, ₽ / месяц</th>
                        <th class="is-num">Будет начисляться, ₽</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $row)
                        <tr data-policy-row>
                            <td>
                                {{ $row['city']->city_name }}
                                <input type="hidden" name="cities[{{ $i }}][city_id]" value="{{ $row['city']->city_id }}">
                            </td>
                            <td>
                                <label class="salary-switch">
                                    <input type="checkbox" name="cities[{{ $i }}][enabled]" value="1" @checked($row['enabled']) data-policy-enabled>
                                    <span class="salary-switch__ui"></span>
                                    <span class="salary-switch__text" data-policy-enabled-label>{{ $row['enabled'] ? 'Включён' : 'Выключен' }}</span>
                                </label>
                            </td>
                            <td class="is-num">
                                <input type="number" name="cities[{{ $i }}][amount]" value="{{ (int)$row['amount'] }}"
                                       min="0" step="1" class="salary-amount-input" inputmode="numeric"
                                       data-policy-amount data-city-name="{{ $row['city']->city_name }}"
                                       data-initial-enabled="{{ $row['enabled'] ? '1' : '0' }}"
                                       data-initial-amount="{{ (int)$row['amount'] }}">
                            </td>
                            <td class="is-num is-dark" data-policy-will>{{ number_format((int)$row['will_accrue'], 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="salary-policy-apply">
            <label class="salary-filters__label">Применять к зарплате начиная с</label>
            <select name="effective_month" class="salary-filters__select salary-filters__select--md">
                @foreach($monthOptions as $opt)
                    <option value="{{ $opt['value'] }}" @selected($effective->format('Y-m') === $opt['value'])>{{ $opt['label'] }}</option>
                @endforeach
            </select>
            <p class="salary-metric__hint">
                Настройка применяется к зарплате за выбранный месяц и далее. Прошлые начисления сохраняются.
            </p>
            <div class="salary-policy-summary" id="salaryPolicySummary" hidden></div>
        </div>

        <div class="salary-policy-actions">
            <button type="submit" class="salary-btn salary-btn--primary">Сохранить изменения</button>
            <a href="{{ route('cfm.salary.policies', ['effective_month' => $effective->format('Y-m')]) }}" class="salary-btn salary-btn--outline">Отмена</a>
        </div>
        <p class="salary-table__foot">
            Августовская зарплата выплачивается по настройкам августа, даже если деньги выводят позже.
        </p>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function fmt(n) {
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
    function refreshRow(row) {
        var en = row.querySelector('[data-policy-enabled]');
        var amount = row.querySelector('[data-policy-amount]');
        var will = row.querySelector('[data-policy-will]');
        var label = row.querySelector('[data-policy-enabled-label]');
        var on = en && en.checked;
        if (label) label.textContent = on ? 'Включён' : 'Выключен';
        if (will && amount) {
            var v = on ? (parseInt(amount.value, 10) || 0) : 0;
            will.textContent = fmt(v);
        }
    }
    function refreshSummary() {
        var box = document.getElementById('salaryPolicySummary');
        if (!box) return;
        var monthSel = document.querySelector('[name="effective_month"]');
        var monthLabel = monthSel && monthSel.options[monthSel.selectedIndex]
            ? monthSel.options[monthSel.selectedIndex].text
            : '';
        var lines = [];
        document.querySelectorAll('[data-policy-row]').forEach(function (row) {
            var en = row.querySelector('[data-policy-enabled]');
            var amount = row.querySelector('[data-policy-amount]');
            if (!en || !amount) return;
            var wasOn = amount.getAttribute('data-initial-enabled') === '1';
            var wasAmt = parseInt(amount.getAttribute('data-initial-amount'), 10) || 0;
            var nowOn = en.checked;
            var nowAmt = parseInt(amount.value, 10) || 0;
            var before = wasOn ? wasAmt : 0;
            var after = nowOn ? nowAmt : 0;
            if (before === after && wasOn === nowOn) return;
            lines.push(
                amount.getAttribute('data-city-name') + ': ' +
                fmt(before) + ' ₽ → ' + fmt(after) + ' ₽ / месяц · ' + monthLabel
            );
        });
        if (!lines.length) {
            box.hidden = true;
            box.innerHTML = '';
            return;
        }
        box.hidden = false;
        box.innerHTML = lines.map(function (l) { return '<div>' + l + '</div>'; }).join('') +
            '<div class="salary-policy-summary__note">Процентная часть сохраняется. Завершённые расчёты не изменятся.</div>';
    }
    document.querySelectorAll('[data-policy-row]').forEach(function (row) {
        refreshRow(row);
        row.addEventListener('change', function () { refreshRow(row); refreshSummary(); });
        row.addEventListener('input', function () { refreshRow(row); refreshSummary(); });
    });
    var monthSel = document.querySelector('[name="effective_month"]');
    if (monthSel) monthSel.addEventListener('change', refreshSummary);
    refreshSummary();
})();
</script>
@endpush
