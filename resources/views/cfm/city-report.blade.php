@extends('layouts.app')

@section('title', 'Отчёт по городу: ' . $city->city_name)

@section('content')
<div style="max-width: 960px; margin: 0 auto;">
    <div class="card" style="margin-bottom: 1rem; padding: 0.75rem;">
        <form method="GET" action="{{ route('cfm.summary.city', $city->city_id) }}" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            <h2 style="margin: 0; font-size: 1.125rem; font-weight: 600;">{{ $city->city_name }}</h2>

            <div style="flex: 1;"></div>

            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="date" name="date_from" class="form-input" value="{{ $dateFrom }}" style="width: 140px;">
                <span class="text-muted-foreground">-</span>
                <input type="date" name="date_to" class="form-input" value="{{ $dateTo }}" style="width: 140px;">
                <button type="submit" class="btn btn-primary btn-sm">
                    {!! icon('search') !!} Показать
                </button>
            </div>

            @php
                $today = now()->format('Y-m-d');
                $yesterday = now()->subDay()->format('Y-m-d');
                $monthStart = now()->startOfMonth()->format('Y-m-d');
            @endphp
            <div style="display: flex; gap: 0.5rem;">
                <a href="{{ route('cfm.summary.city', ['city_id' => $city->city_id, 'date_from' => $monthStart, 'date_to' => $today]) }}" class="btn btn-secondary btn-sm">
                    С начала месяца
                </a>
                <a href="{{ route('cfm.summary.city', ['city_id' => $city->city_id, 'date_from' => $yesterday, 'date_to' => $yesterday]) }}" class="btn btn-secondary btn-sm">
                    Вчера
                </a>
                <a href="{{ route('cfm.summary.city', ['city_id' => $city->city_id, 'date_from' => $today, 'date_to' => $today]) }}" class="btn btn-secondary btn-sm">
                    Сегодня
                </a>
            </div>
        </form>
    </div>

    <div class="card" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Статья ДДС / проводка</th>
                    <th style="text-align: right; width: 150px;">Значение</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $activityNames = [
                        'operating' => 'Операционная деятельность',
                        'investing' => 'Инвестиционная деятельность',
                        'financing' => 'Финансовая деятельность',
                    ];
                    $grandTotal = 0;
                @endphp

                @foreach(['operating', 'investing', 'financing'] as $activity)
                    @if(isset($report[$activity]) && ($report[$activity]['total'] != 0 || !empty($report[$activity]['details'])))
                        <tr class="bg-muted">
                            <td style="font-weight: 600;">{{ $activityNames[$activity] }}</td>
                            <td style="text-align: right; font-weight: 600;">
                                <span class="{{ $report[$activity]['total'] >= 0 ? 'text-[color:var(--success)]' : 'text-destructive' }}">
                                    {{ $report[$activity]['total'] >= 0 ? '+' : '' }}{{ number_format($report[$activity]['total'], 0, ',', ' ') }}
                                </span>
                            </td>
                        </tr>

                        @foreach($report[$activity]['details'] as $catName => $catData)
                            @php
                                $catTotal = is_array($catData) ? ($catData['total'] ?? 0) : (int) $catData;
                                $operations = is_array($catData) ? ($catData['operations'] ?? []) : [];
                            @endphp
                            <tr>
                                <td style="padding-left: 2rem; font-weight: 500;">{{ $catName }}</td>
                                <td style="text-align: right; font-weight: 500;">
                                    <span class="{{ $catTotal >= 0 ? 'text-[color:var(--success)]' : 'text-destructive' }}">
                                        {{ $catTotal >= 0 ? '+' : '' }}{{ number_format($catTotal, 0, ',', ' ') }}
                                    </span>
                                </td>
                            </tr>
                            @foreach ($operations as $op)
                                <tr class="text-muted-foreground" style="font-size: 0.8125rem;">
                                    <td style="padding-left: 3.5rem;">
                                        <a href="{{ route('cfm.show', $op['cfm_id']) }}" class="text-primary hover:underline">
                                            №{{ $op['cfm_id'] }}
                                        </a>
                                        @if ($op['cfm_closed_at'])
                                            · {{ $op['cfm_closed_at'] }}
                                        @endif
                                        @if ($op['related_order_id'])
                                            · <a href="{{ route('orders.show', $op['related_order_id']) }}" class="text-primary hover:underline">заказ №{{ $op['related_order_id'] }}</a>
                                        @endif
                                        @if ($op['cfm_payer'])
                                            <br><span class="text-foreground/80">Плательщик:</span> {{ $op['cfm_payer'] }}
                                        @endif
                                        @if ($op['cfm_recipient'])
                                            <br><span class="text-foreground/80">Получатель:</span> {{ $op['cfm_recipient'] }}
                                        @endif
                                        @if ($op['external_cfm_ref'])
                                            <br><span class="text-foreground/80">№ Уровня:</span> {{ $op['external_cfm_ref'] }}
                                        @endif
                                        @if ($op['cfm_subcat'])
                                            <br><span class="text-foreground/80">Подстатья:</span> {{ $op['cfm_subcat'] }}
                                        @endif
                                        @if ($op['cfm_adds'])
                                            <br><span class="text-foreground/80">Комментарий:</span> {{ \Illuminate\Support\Str::limit($op['cfm_adds'], 200) }}
                                        @endif
                                    </td>
                                    <td style="text-align: right; vertical-align: top;">
                                        <span class="{{ $op['amount'] >= 0 ? 'text-[color:var(--success)]' : 'text-destructive' }}">
                                            {{ $op['amount'] >= 0 ? '+' : '' }}{{ number_format($op['amount'], 0, ',', ' ') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach

                        @php $grandTotal += $report[$activity]['total']; @endphp
                    @endif
                @endforeach

                <tr class="bg-sidebar text-sidebar-foreground">
                    <td style="font-weight: 600;">ИТОГО</td>
                    <td style="text-align: right; font-weight: 600; font-size: 1rem;">
                        <span class="{{ $grandTotal >= 0 ? 'text-[color:var(--success)]' : 'text-destructive' }}">
                            {{ $grandTotal >= 0 ? '+' : '' }}{{ number_format($grandTotal, 0, ',', ' ') }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1rem;">
        <a href="{{ route('cfm.summary', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="btn btn-secondary">
            {!! icon('back') !!} Назад к сводному отчёту
        </a>
    </div>
</div>
@endsection
