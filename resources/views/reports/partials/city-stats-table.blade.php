@php
    $money = fn ($v) => number_format((int) $v, 0, ',', ' ') . ' ₽';
@endphp
<div class="border-b border-border last:border-b-0">
    <h3 class="px-4 py-2 text-sm font-semibold">{{ $title }}</h3>
    <div class="overflow-x-auto">
        <table class="table w-full text-sm">
            <thead>
                <tr>
                    <th>№</th>
                    <th>Город</th>
                    <th>Оборот</th>
                    <th>Чистыми</th>
                    <th>Принято</th>
                    <th>Закрыто</th>
                    <th>Наши</th>
                    <th>Партнёр</th>
                    <th>Отказов</th>
                    <th>Отказы ОФ</th>
                    <th>Чист. ср.чек</th>
                    <th>Цена заявки</th>
                    <th>КДВП %</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="font-semibold">{{ $row['city_name'] }}</td>
                        <td>{{ $money($row['turnover']) }}</td>
                        <td>{{ $money($row['net']) }}</td>
                        <td>{{ $row['accepted'] }}</td>
                        <td>{{ $row['closed'] }}</td>
                        <td>{{ $row['closed_our'] }}</td>
                        <td>{{ $row['closed_partner'] }}</td>
                        <td>{{ $row['refusals'] }}</td>
                        <td>{{ $row['rejected'] }}</td>
                        <td>{{ $money($row['net_avg_check']) }}</td>
                        <td>{{ ($row['closed'] ?? $row['accepted'] ?? 0) > 0 ? $money($row['lead_cost']) : '—' }}</td>
                        <td>{{ $row['conversion_pct'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
            @if (count($rows) > 0)
            <tfoot>
                <tr class="bg-muted font-bold">
                    <td></td>
                    <td>Итого</td>
                    <td>{{ $money($totals['turnover'] ?? 0) }}</td>
                    <td>{{ $money($totals['net'] ?? 0) }}</td>
                    <td>{{ $totals['accepted'] ?? 0 }}</td>
                    <td>{{ $totals['closed'] ?? 0 }}</td>
                    <td>{{ $totals['closed_our'] ?? 0 }}</td>
                    <td>{{ $totals['closed_partner'] ?? 0 }}</td>
                    <td>{{ $totals['refusals'] ?? 0 }}</td>
                    <td>{{ $totals['rejected'] ?? 0 }}</td>
                    <td>{{ isset($totals['net_avg_check']) ? $money($totals['net_avg_check']) : '—' }}</td>
                    <td>{{ (($totals['closed'] ?? 0) > 0 || ($totals['accepted'] ?? 0) > 0) ? $money($totals['lead_cost'] ?? 0) : '—' }}</td>
                    <td>{{ $totals['conversion_pct'] ?? 0 }}%</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
