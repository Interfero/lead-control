@extends('layouts.app')

@section('title', 'Расчёт ЗП')

@php
    $fmt = fn (int $n) => number_format($n, 0, ',', ' ');
    $ruMonths = [1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',5=>'Май',6=>'Июнь',7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь'];
    $periodLabel = ($ruMonths[(int)$period->format('n')] ?? '').' '.$period->format('Y');
    $periodFrom = ($isPayoutMonthView ? $accrualPeriod : $period)->copy()->startOfMonth();
    $periodTo = $periodFrom->copy()->endOfMonth();
    $incasTitle = $isPayoutMonthView ? ('Сдано в '.$incasMonthPrep) : 'Сдано инкассации';
    $statusLabel = $isPayoutMonthView
        ? ('Выплата в '.$periodLabel.' · ЗП за '.$accrualPeriodLabel)
        : ($isPreliminary ? 'Предварительный расчёт' : 'Расчёт зафиксирован');
@endphp

@section('content')
<div class="salary-page">
    <div class="salary-page__crumbs">
        <x-breadcrumbs :items="[
            ['label' => 'Главная', 'url' => route('orders.index')],
            ['label' => 'Касса', 'url' => route('cfm.index')],
            ['label' => 'Расчёт ЗП', 'url' => null],
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
            <h1 class="salary-page__title">Расчёт ЗП</h1>
            <p class="salary-page__subtitle">
                @if($isPayoutMonthView)
                    Сейчас {{ mb_strtolower($periodLabel) }}: сколько инкассации сдано в {{ $incasMonthPrep }} и какая ЗП к выводу
                @elseif($isBranchOnly)
                    Ваша зарплата по итогам месяца
                @else
                    Зарплата директоров по итогам месяца
                @endif
            </p>
        </div>
        @if($canManagePolicies)
            <a href="{{ route('cfm.salary.policies') }}" class="salary-btn salary-btn--outline">Настройки окладов</a>
        @endif
    </div>

    <form method="GET" action="{{ route('cfm.salary.index') }}" class="salary-filters">
        <div class="salary-filters__field">
            <label class="salary-filters__label">{{ $isPayoutMonthView ? 'Месяц' : 'Расчётный месяц' }}</label>
            <select name="month" class="salary-filters__select">
                @foreach($monthOptions as $opt)
                    <option value="{{ $opt['value'] }}" @selected($period->format('Y-m') === $opt['value'])>{{ $opt['label'] }}</option>
                @endforeach
            </select>
        </div>
        @unless($isBranchOnly)
            <div class="salary-filters__field">
                <label class="salary-filters__label">Город</label>
                <select name="city_id" class="salary-filters__select">
                    <option value="">Все города</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->city_id }}" @selected($filterCityId === (int)$city->city_id)>{{ $city->city_name }}</option>
                    @endforeach
                </select>
            </div>
        @endunless
        <button type="submit" class="salary-btn salary-btn--primary">Показать</button>
        <p class="salary-filters__status">
            {{ $periodFrom->format('d.m') }}—{{ $periodTo->format('d.m.Y') }} · {{ $statusLabel }}
        </p>
    </form>

    <div class="salary-rates">
        Ставка на всю инкассацию:
        до 100&nbsp;000&nbsp;₽ — <strong>15%</strong>
        <span class="salary-rates__sep">·</span>
        свыше 100&nbsp;000 до 250&nbsp;000&nbsp;₽ — <strong>20%</strong>
        <span class="salary-rates__sep">·</span>
        свыше 250&nbsp;000&nbsp;₽ — <strong>25%</strong>
        <span class="salary-rates__sep">·</span>
        спутники — <strong>10%</strong>
    </div>

    <div class="salary-table-wrap">
        <table class="salary-table">
            <thead>
                <tr>
                    <th>Город</th>
                    <th class="is-num">{{ $incasTitle }}, ₽</th>
                    <th class="is-num">Ставка</th>
                    <th class="is-num">Доход с инкассации, ₽</th>
                    <th class="is-num">Оклад, ₽</th>
                    <th class="is-num">Начислено, ₽</th>
                    <th class="is-num">Выплачено, ₽</th>
                    <th class="is-num">Доступно, ₽</th>
                </tr>
            </thead>
            <tbody>
                @unless($isBranchOnly)
                <tr class="salary-table__total">
                    <td>Итого</td>
                    <td class="is-num is-incas">{{ $fmt($totals['incas_base']) }}</td>
                    <td class="is-num">—</td>
                    <td class="is-num is-income">{{ $fmt($totals['commission_amount']) }}</td>
                    <td class="is-num is-dark">{{ $fmt($totals['salary_amount']) }}</td>
                    <td class="is-num is-dark">{{ $fmt($totals['accrued_amount']) }}</td>
                    <td class="is-num">{{ $fmt($totals['paid']) }}</td>
                    <td class="is-num is-avail">{{ $fmt($totals['available']) }}</td>
                </tr>
                @endunless
                @forelse($rows as $row)
                    @php
                        $c = $row['calculation'];
                        $groupIds = \App\Models\City::operationGroupIds((int) $c->city_id);
                        $inSelectedCluster = $detail && in_array((int) $detail->city_id, $groupIds, true);
                        $isSelected = $detail && $detail->salary_calculation_id === $c->salary_calculation_id;
                    @endphp
                    <tr class="salary-table__row {{ $isSelected ? 'is-active' : ($inSelectedCluster ? 'is-cluster' : '') }}"
                        onclick="window.location='{{ route('cfm.salary.index', array_filter(['month' => $period->format('Y-m'), 'city_id' => $filterCityId, 'calc' => $c->salary_calculation_id])) }}'">
                        <td>
                            <span class="salary-table__city">{{ $c->city->city_name ?? '—' }}</span>
                            @if($c->city?->isSatellite())
                                <span class="salary-tag">спутник</span>
                            @endif
                            @if($c->status === 'needs_recalc')
                                <span class="salary-tag salary-tag--warn">Требуется пересчёт</span>
                            @elseif($c->status === 'error')
                                <span class="salary-tag salary-tag--warn">Ошибка</span>
                            @elseif($row['overpayment'] > 0)
                                <span class="salary-tag salary-tag--warn">Переплата</span>
                            @elseif($row['available'] === 0 && $c->accrued_amount > 0)
                                <span class="salary-tag">Выплачено полностью</span>
                            @endif
                        </td>
                        <td class="is-num is-incas">{{ $fmt((int)$c->incas_base) }}</td>
                        <td class="is-num">{{ (int)$c->rate_percent }}%</td>
                        <td class="is-num is-income">{{ $fmt((int)$c->commission_amount) }}</td>
                        <td class="is-num is-dark">{{ $fmt((int)$c->salary_amount) }}</td>
                        <td class="is-num is-dark">{{ $fmt((int)$c->accrued_amount) }}</td>
                        <td class="is-num">{{ $fmt((int)$row['paid']) }}</td>
                        <td class="is-num is-avail">{{ $fmt((int)$row['available']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="salary-table__empty">Нет данных за выбранный месяц</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="salary-table__foot salary-table__foot--split">
            <span>{{ $isBranchOnly ? 'Доступны только ваши начисления и выплаты' : 'Выберите город, чтобы посмотреть расчёт и выплаты' }}</span>
            <span class="salary-table__foot-muted">Клик по строке открывает город вместе со спутниками ниже</span>
        </div>
    </div>

    @if($detail && $detailCluster->isNotEmpty())
        @php
            $clusterNames = $detailCluster->map(fn ($r) => $r['calculation']->city->city_name ?? '—')->filter()->values();
            $clusterTitle = $clusterNames->count() > 1
                ? $clusterNames->implode(' · ')
                : ($detail->city->city_name ?? '—');
        @endphp
        <div class="salary-detail">
            <div class="salary-detail__head">
                <div>
                    <div class="salary-detail__title-row">
                        <h2 class="salary-detail__city">{{ $clusterTitle }}</h2>
                        <span class="salary-badge">{{ $periodLabel }}</span>
                    </div>
                    <p class="salary-detail__meta">
                        @if($detailCluster->count() > 1)
                            Кластер: материнский город и спутники · отдельные расчёты и ставки
                        @else
                            Получатель: {{ $detail->recipient->user_name ?? 'директор города' }}
                            · Расчёт № {{ $detail->calc_code ?? ('ЗП-'.$detail->salary_calculation_id) }}
                        @endif
                    </p>
                </div>
            </div>

            @foreach($detailCluster as $clusterRow)
                @php
                    $item = $clusterRow['calculation'];
                    $itemBal = [
                        'paid' => $clusterRow['paid'],
                        'available' => $clusterRow['available'],
                        'overpayment' => $clusterRow['overpayment'],
                    ];
                    $isPrimary = (int) $item->salary_calculation_id === (int) $detail->salary_calculation_id;
                    $canPayItem = $item->status === 'fixed'
                        && ! $isPreliminary
                        && $itemBal['available'] > 0
                        && auth()->user()->hasAnyRole(['developer', 'branch_head', 'regional_director', 'senior_manager']);
                    $payBlockedReason = match (true) {
                        $item->status === 'needs_recalc' => 'Требуется пересчёт',
                        $item->status === 'error' => $item->error_message ?: 'Ошибка расчёта',
                        $item->status === 'preliminary' || $isPreliminary => 'Предварительный месяц — выплата недоступна',
                        $itemBal['available'] <= 0 && $itemBal['overpayment'] > 0 => 'Переплата '.$fmt($itemBal['overpayment']).' ₽',
                        $itemBal['available'] <= 0 => 'Доступно 0 ₽',
                        default => null,
                    };
                    $itemPayments = $clusterRow['payments'] ?? collect();
                @endphp

                <div class="salary-detail__block {{ $isPrimary ? 'is-primary' : '' }}">
                    <div class="salary-detail__block-head">
                        <div>
                            <h3 class="salary-detail__block-title">
                                {{ $item->city->city_name ?? '—' }}
                                @if($item->city?->isSatellite())
                                    <span class="salary-tag">спутник · {{ (int)$item->rate_percent }}%</span>
                                @else
                                    <span class="salary-tag">{{ (int)$item->rate_percent }}%</span>
                                @endif
                            </h3>
                            <p class="salary-detail__meta">
                                Получатель: {{ $item->recipient->user_name ?? 'директор города' }}
                                · Расчёт № {{ $item->calc_code ?? ('ЗП-'.$item->salary_calculation_id) }}
                            </p>
                        </div>
                        <div class="salary-detail__actions">
                            @if($canPayItem)
                                <a class="salary-btn salary-btn--primary"
                                   href="{{ route('cfm.create', [
                                        'type' => 'director_salary',
                                        'city_id' => $item->city_id,
                                        'salary_calculation_id' => $item->salary_calculation_id,
                                        'related_user_id' => $item->recipient_user_id,
                                   ]) }}">Выплатить ЗП</a>
                            @elseif($payBlockedReason)
                                <button type="button" class="salary-btn salary-btn--primary is-disabled" disabled title="{{ $payBlockedReason }}">Выплатить ЗП</button>
                                <span class="salary-detail__hint">{{ $payBlockedReason }}</span>
                            @endif
                        </div>
                    </div>

                    @if($item->status === 'needs_recalc' && $canManagePolicies)
                        <form method="POST" action="{{ route('cfm.salary.recalc', $item->salary_calculation_id) }}" class="salary-recalc">
                            @csrf
                            <input type="text" name="reason" class="salary-filters__select" placeholder="Причина пересчёта" required maxlength="1000">
                            <button type="submit" class="salary-btn salary-btn--outline">Пересчитать</button>
                        </form>
                    @endif

                    <div class="salary-metrics">
                        <div class="salary-metric">
                            <div class="salary-metric__label">{{ $incasTitle }}</div>
                            <div class="salary-metric__value">{{ $fmt((int)$item->incas_base) }} ₽</div>
                            <div class="salary-metric__hint">{{ $isPayoutMonthView ? 'Инкассация за '.$accrualPeriodLabel : 'Ставка '.(int)$item->rate_percent.'% на всю сумму' }}</div>
                        </div>
                        <div class="salary-metric">
                            <div class="salary-metric__label">Доход с инкассации</div>
                            <div class="salary-metric__value is-income">{{ $fmt((int)$item->commission_amount) }} ₽</div>
                            <div class="salary-metric__hint">{{ $isPayoutMonthView ? 'ЗП за '.$accrualPeriodLabel : 'Процентная часть зарплаты' }}</div>
                        </div>
                        <div class="salary-metric">
                            <div class="salary-metric__label">Оклад</div>
                            <div class="salary-metric__value is-dark">{{ $fmt((int)$item->salary_amount) }} ₽</div>
                            <div class="salary-metric__hint">{{ (int)$item->salary_amount > 0 ? 'Включён для этого месяца' : 'Выключен' }}</div>
                        </div>
                        <div class="salary-metric">
                            <div class="salary-metric__label">Доступно к выводу</div>
                            <div class="salary-metric__value is-avail">{{ $fmt((int)$itemBal['available']) }} ₽</div>
                            <div class="salary-metric__hint">
                                @if($itemBal['overpayment'] > 0)
                                    Переплата {{ $fmt((int)$itemBal['overpayment']) }} ₽
                                @else
                                    С учётом проведённых выплат
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="salary-detail__totals">
                        <span>Начислено <strong>{{ $fmt((int)$item->accrued_amount) }} ₽</strong></span>
                        <span>Выплачено <strong>{{ $fmt((int)$itemBal['paid']) }} ₽</strong></span>
                    </div>

                    <div class="salary-history">
                        <h3 class="salary-history__title">История выплат</h3>
                        <table class="salary-history__table">
                            <thead>
                                <tr>
                                    <th>Операция</th>
                                    <th>Дата</th>
                                    <th class="is-num">Сумма, ₽</th>
                                    <th>Статус</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($itemPayments as $pay)
                                    <tr>
                                        <td><a href="{{ route('cfm.show', $pay->cfm_id) }}" class="salary-table__city">№{{ $pay->cfm_id }}</a></td>
                                        <td>{{ $pay->cfm_closed_at?->format('d.m.Y H:i') ?? $pay->cfm_created_at?->format('d.m.Y H:i') }}</td>
                                        <td class="is-num">{{ $fmt((int)$pay->amount_cfm) }}</td>
                                        <td>{{ $pay->cfm_closed_at ? 'Проведена' : 'Черновик' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="salary-table__empty">Выплат за этот месяц пока нет</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
