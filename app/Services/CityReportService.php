<?php

namespace App\Services;

use App\Models\City;
use App\Models\Complaint;
use App\Models\CfmCategory;
use App\Models\CfmOperation;
use App\Models\Order;
use App\Models\PromPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CityReportService
{
    public function __construct(
        private OrderService $orderService,
        private ReportCityDailyService $reportCityDaily,
    ) {}

    /**
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    public function resolveDateRange(?string $period, ?string $dateFrom, ?string $dateTo): array
    {
        if ($period === 'today') {
            $d = Carbon::today();

            return ['from' => $d, 'to' => $d, 'label' => 'Сегодня ('.$d->format('d.m.Y').')'];
        }
        if ($period === 'yesterday') {
            $d = Carbon::yesterday();

            return ['from' => $d, 'to' => $d, 'label' => 'Вчера ('.$d->format('d.m.Y').')'];
        }
        if ($period === 'week') {
            return [
                'from' => Carbon::now()->startOfWeek(),
                'to' => Carbon::now()->endOfWeek(),
                'label' => 'Неделя ('.Carbon::now()->startOfWeek()->format('d.m.Y').' — '.Carbon::now()->endOfWeek()->format('d.m.Y').')',
            ];
        }
        if ($period === 'month') {
            return [
                'from' => Carbon::now()->startOfMonth(),
                'to' => Carbon::now()->endOfMonth(),
                'label' => 'С '.Carbon::now()->startOfMonth()->format('d.m.Y').' по '.Carbon::now()->endOfMonth()->format('d.m.Y'),
            ];
        }

        $from = ($dateFrom && trim($dateFrom) !== '')
            ? Carbon::parse($dateFrom)->startOfDay()
            : Carbon::now()->startOfMonth();
        $to = ($dateTo && trim($dateTo) !== '')
            ? Carbon::parse($dateTo)->endOfDay()
            : Carbon::now()->endOfMonth();

        return [
            'from' => $from,
            'to' => $to->copy()->startOfDay(),
            'label' => 'С '.$from->format('d.m.Y').' по '.$to->format('d.m.Y'),
        ];
    }

    public function cityIdsForUser(User $user): array
    {
        if ($user->hasAnyRole(['developer', 'general_director'])) {
            return City::where('is_active', true)
                ->where('city_type', 'city')
                ->pluck('city_id')
                ->all();
        }

        return $user->cities()
            ->where('is_active', true)
            ->where('city_type', 'city')
            ->pluck('cities.city_id')
            ->all();
    }

    /** @return Collection<int, City> */
    public function citiesForReport(User $user, ?int $focusCityId = null): Collection
    {
        $ids = $this->cityIdsForUser($user);
        $query = City::with('parentCity')
            ->whereIn('city_id', $ids)
            ->where('city_type', 'city')
            ->where('is_active', true)
            ->orderBy('city_name');

        if ($focusCityId && in_array($focusCityId, $ids, true)) {
            $groupIds = array_values(array_intersect(City::operationGroupIds($focusCityId), $ids));
            if ($groupIds !== []) {
                return City::with('parentCity')
                    ->whereIn('city_id', $groupIds)
                    ->orderBy('city_name')
                    ->get();
            }
        }

        return $query->get();
    }

    /**
     * Подпись для отчётов/UI: делегирует в City::displayName() (без двойных скобок).
     */
    public function displayCityName(City $city): string
    {
        return $city->displayName();
    }

    /**
     * Сводка по городу за период (для обоих отчётов).
     *
     * @param  array{order_types?: array, order_cores?: array}  $filters
     */
    public function buildCityStats(int $cityId, Carbon $dateFrom, Carbon $dateTo, array $filters = []): array
    {
        $city = City::with('parentCity')->findOrFail($cityId);
        $from = $dateFrom->copy()->startOfDay();
        $to = $dateTo->copy()->endOfDay();

        $baseCreated = Order::query()
            ->whereHas('address', fn ($q) => $q->where('city_id', $cityId))
            ->where('order_created_at', '>=', $from)
            ->where('order_created_at', '<=', $to);

        $this->applyOrderFilters($baseCreated, $filters);

        $accepted = (clone $baseCreated)->count();

        $closedAgg = $this->aggregateClosedOrdersSql([$cityId], $from, $to, $filters)->get($cityId);
        $closedCount = (int) ($closedAgg['closed_total'] ?? 0);
        $turnover = (int) ($closedAgg['turnover'] ?? 0);
        $net = (int) ($closedAgg['net'] ?? 0);
        $parts = (int) ($closedAgg['parts'] ?? 0);
        $closedOur = (int) ($closedAgg['closed_our'] ?? 0);
        $closedPartner = (int) ($closedAgg['closed_partner'] ?? 0);
        $warranty = (int) ($closedAgg['warranty_closed'] ?? 0);
        $repeat = (int) ($closedAgg['repeat_closed'] ?? 0);
        $nonCore = (int) ($closedAgg['non_core_closed'] ?? 0);

        $refusals = (clone $baseCreated)
            ->whereIn('order_status', ['cancelled_cc', 'cancelled_city'])
            ->count();

        $rejected = (clone $baseCreated)->where('order_status', 'rejected')->count();
        $inProgressSd = (clone $baseCreated)->where('order_status', 'in_progress_sd')->count();

        $promoPay = $this->promoPayForCity($cityId, $from, $to);
        $forecast = $this->forecastTurnover($net, $from, $to);
        $executionPct = $forecast > 0 ? round($net / $forecast * 100, 1) : 0;
        $priceOrderCount = $closedCount > 0 ? $closedCount : $accepted;

        return [
            'city_id' => $cityId,
            'city_name' => $this->displayCityName($city),
            'turnover' => $turnover,
            'net' => $net,
            'accepted' => $accepted,
            'closed' => $closedCount,
            'closed_our' => $closedOur,
            'closed_partner' => $closedPartner,
            'refusals' => $refusals,
            'rejected' => $rejected,
            'in_progress_sd' => $inProgressSd,
            'net_avg_check' => $closedCount > 0 ? (int) round($net / $closedCount) : 0,
            'avg_check' => $closedCount > 0 ? (int) round($turnover / $closedCount) : 0,
            'lead_cost' => $priceOrderCount > 0 ? (int) round($promoPay / $priceOrderCount) : 0,
            'ad_spend' => $promoPay,
            'conversion_pct' => $accepted > 0 ? round($closedCount / $accepted * 100, 1) : 0,
            'forecast' => $forecast,
            'execution_pct' => $executionPct,
            'non_core_closed' => $nonCore,
            'warranty' => $warranty,
            'repeat' => $repeat,
            'parts' => $parts,
        ];
    }

    public function isPartnerOrder(Order $order): bool
    {
        return $order->isPartnerOrder();
    }

    /** Наши (листовочные / не партнёрские) закрытые заявки. */
    public function isOurFlyerOrder(Order $order): bool
    {
        return $order->isFlyerOrder();
    }

    /** Выезд = профильные (core + other), стационар = непрофильные (non_core) — как в КП. */
    public function isStationaryClosedOrder(Order $order): bool
    {
        return $order->order_core === 'non_core';
    }

    /**
     * Строка отчёта «По городам» (колонки как report-by-city.xlsx в КП).
     *
     * @param  array{order_types?: array, order_cores?: array}  $filters
     * @return array<string, mixed>
     */
    public function buildByCityRow(int $cityId, Carbon $dateFrom, Carbon $dateTo, array $filters = []): array
    {
        $city = City::with('parentCity')->findOrFail($cityId);
        $from = $dateFrom->copy()->startOfDay();
        $to = $dateTo->copy()->endOfDay();

        $createdQuery = Order::query()
            ->whereHas('address', fn ($q) => $q->where('city_id', $cityId))
            ->where('order_created_at', '>=', $from)
            ->where('order_created_at', '<=', $to);
        $this->applyOrderFilters($createdQuery, $filters);

        $acceptedOpen = (clone $createdQuery)->count();
        $acceptedNonCore = (clone $createdQuery)->where('order_core', 'non_core')->count();
        $acceptancePct = $acceptedOpen > 0
            ? round($acceptedNonCore / $acceptedOpen * 100, 1)
            : 0;

        $closedQuery = Order::query()
            ->whereHas('address', fn ($q) => $q->where('city_id', $cityId))
            ->where('order_status', 'completed')
            ->whereNotNull('order_closed_at')
            ->where('order_closed_at', '>=', $from)
            ->where('order_closed_at', '<=', $to);
        $this->applyOrderFilters($closedQuery, $filters);

        $closedOrders = $closedQuery->get();

        $branchSum = 0;
        $netTotal = 0;
        $closedVisit = 0;
        $closedStationary = 0;
        $visitNetSum = 0;
        $stationaryNetSum = 0;
        $warrantySum = 0;
        $coreNet = 0;

        foreach ($closedOrders as $order) {
            $net = $this->orderService->getNetAmount($order);
            $paid = (int) $order->amount_paid;
            $branchSum += $paid;
            $netTotal += $net;

            if ($this->isStationaryClosedOrder($order)) {
                $closedStationary++;
                $stationaryNetSum += $net;
            } else {
                $closedVisit++;
                $visitNetSum += $net;
                $coreNet += $net;
            }

            if ($order->order_type === 'warranty') {
                $warrantySum += $net;
            }
        }

        $closedTotal = $closedOrders->count();
        $forecast = $this->forecastTurnover($branchSum, $from, $to);

        $refusalNf = (clone $createdQuery)
            ->whereIn('order_status', ['cancelled_cc', 'cancelled_city', 'rejected'])
            ->count();

        $takenByCc = (clone $createdQuery)
            ->whereHas('creator.roles', fn ($q) => $q->where('role_code', 'call_center'))
            ->count();

        return [
            'city_id' => $cityId,
            'city_name' => $this->displayCityName($city),
            'branch_sum' => $branchSum,
            'forecast' => $forecast,
            'acceptance_pct' => $acceptancePct,
            'sales_accepted' => $acceptedNonCore,
            'orders_open' => $acceptedOpen,
            'closed_visit' => $closedVisit,
            'closed_stationary' => $closedStationary,
            'visit_net_sum' => $visitNetSum,
            'stationary_net_sum' => $stationaryNetSum,
            'check_visit' => $visitNetSum,
            'avg_check' => $closedTotal > 0 ? (int) round($branchSum / $closedTotal) : 0,
            'check_stationary' => $closedStationary > 0
                ? round($stationaryNetSum / $closedStationary, 1)
                : 0,
            'warranty_sum' => $warrantySum,
            'refusal_nf' => $refusalNf,
            'taken_by_cc' => $takenByCc,
            'pct_p_turnover' => $netTotal > 0 ? round($coreNet / $netTotal * 100, 2) : 0,
            'pct_p_orders' => $closedTotal > 0 ? round($closedVisit / $closedTotal * 100, 2) : 0,
        ];
    }

    /**
     * @param  Collection<int, City>|list<City>  $cities
     * @param  array{order_types?: array, order_cores?: array}  $filters
     * @return list<array<string, mixed>>
     */
    public function buildByCityRows($cities, Carbon $dateFrom, Carbon $dateTo, array $filters = []): array
    {
        $rows = [];
        foreach ($cities as $city) {
            $rows[] = $this->buildByCityRow((int) $city->city_id, $dateFrom, $dateTo, $filters);
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function sumByCityRows(array $rows): array
    {
        $keys = [
            'branch_sum', 'forecast', 'sales_accepted', 'orders_open',
            'closed_visit', 'closed_stationary', 'visit_net_sum', 'stationary_net_sum',
            'check_visit', 'warranty_sum', 'refusal_nf', 'taken_by_cc',
        ];
        $totals = $this->sumRows($rows, $keys);

        $closedTotal = ($totals['closed_visit'] ?? 0) + ($totals['closed_stationary'] ?? 0);
        if ($closedTotal > 0 && isset($totals['branch_sum'])) {
            $totals['avg_check'] = (int) round($totals['branch_sum'] / $closedTotal);
        }
        $netTotal = ($totals['visit_net_sum'] ?? 0) + ($totals['stationary_net_sum'] ?? 0);
        if (($totals['closed_stationary'] ?? 0) > 0) {
            $totals['check_stationary'] = round($totals['stationary_net_sum'] / $totals['closed_stationary'], 1);
        }
        if ($netTotal > 0) {
            $totals['pct_p_turnover'] = round(($totals['visit_net_sum'] ?? 0) / $netTotal * 100, 2);
        }
        if ($closedTotal > 0) {
            $totals['pct_p_orders'] = round(($totals['closed_visit'] ?? 0) / $closedTotal * 100, 2);
        }
        if (($totals['orders_open'] ?? 0) > 0 && isset($totals['sales_accepted'])) {
            $totals['acceptance_pct'] = round($totals['sales_accepted'] / $totals['orders_open'] * 100, 1);
        }

        return $totals;
    }

    /**
     * Строки «По закрытым» для списка городов (срезы или live SQL-агрегат, без Order::get()).
     *
     * @param  Collection<int, City>|list<City>  $cities
     * @param  array{order_types?: array, order_cores?: array}  $filters
     * @return list<array>
     */
    public function buildClosedOrdersRows($cities, Carbon $dateFrom, Carbon $dateTo, array $filters = []): array
    {
        $cities = $cities instanceof Collection ? $cities : collect($cities);
        $cityIds = $cities->pluck('city_id')->map(fn ($id) => (int) $id)->all();
        $from = $dateFrom->copy()->startOfDay();
        $to = $dateTo->copy()->endOfDay();
        $hasFilters = ! empty($filters['order_types']) || ! empty($filters['order_cores']);

        $sliceData = null;
        if (! $hasFilters) {
            $sliceData = $this->reportCityDaily->aggregateClosedOrdersFromSlices($cityIds, $from, $to);
        }

        $liveAgg = $sliceData === null
            ? $this->aggregateClosedOrdersSql($cityIds, $from, $to, $filters)
            : null;

        $acceptedByCity = $sliceData === null
            ? $this->aggregateAcceptedByCity($cityIds, $from, $to, $filters)
            : [];
        $complaintsByCity = $sliceData === null
            ? $this->aggregateComplaintsByCity($cityIds, $from, $to)
            : [];

        $rows = [];
        foreach ($cities as $city) {
            $cityId = (int) $city->city_id;
            if ($sliceData !== null && isset($sliceData[$cityId])) {
                $agg = $sliceData[$cityId];
            } else {
                $base = $liveAgg?->get($cityId) ?? [
                    'closed_total' => 0,
                    'closed_our' => 0,
                    'closed_partner' => 0,
                    'turnover' => 0,
                    'net' => 0,
                    'parts' => 0,
                ];
                $accepted = (int) ($acceptedByCity[$cityId] ?? 0);
                $complaints = (int) ($complaintsByCity[$cityId] ?? 0);
                $closed = (int) ($base['closed_total'] ?? 0);
                $turnover = (int) ($base['turnover'] ?? 0);
                $net = (int) ($base['net'] ?? 0);
                $promoPay = $this->promoPayForCity($cityId, $from, $to);
                $priceOrderCount = $closed > 0 ? $closed : $accepted;
                $agg = [
                    'accepted_count' => $accepted,
                    'closed_total' => $closed,
                    'closed_our' => (int) ($base['closed_our'] ?? 0),
                    'closed_partner' => (int) ($base['closed_partner'] ?? 0),
                    'turnover' => $turnover,
                    'net' => $net,
                    'complaints_count' => $complaints,
                    'complaints_pct' => $closed > 0 ? round($complaints / $closed * 100, 1) : 0,
                    'promo_pay' => $promoPay,
                    'avg_check' => $closed > 0 ? (int) round($turnover / $closed) : 0,
                    'net_avg_check' => $closed > 0 ? (int) round($net / $closed) : 0,
                    'lead_price' => $priceOrderCount > 0 ? (int) round($promoPay / $priceOrderCount) : 0,
                    'price_order_count' => $priceOrderCount,
                    'from_slice' => false,
                ];
            }

            $rows[] = [
                'city_id' => $cityId,
                'city_name' => $this->displayCityName($city),
                'turnover' => (int) $agg['turnover'],
                'forecast' => $this->forecastTurnover((int) $agg['turnover'], $from, $to),
                'complaints_pct' => (float) $agg['complaints_pct'],
                'complaints_count' => (int) $agg['complaints_count'],
                'closed_total' => (int) $agg['closed_total'],
                'closed_our' => (int) $agg['closed_our'],
                'closed_partner' => (int) $agg['closed_partner'],
                'net' => (int) $agg['net'],
                'avg_check' => (int) $agg['avg_check'],
                'net_avg_check' => (int) $agg['net_avg_check'],
                'lead_price' => (int) $agg['lead_price'],
                'promo_pay' => (int) $agg['promo_pay'],
                'accepted_count' => (int) $agg['accepted_count'],
                'price_order_count' => (int) $agg['price_order_count'],
                'from_slice' => (bool) ($agg['from_slice'] ?? false),
            ];
        }

        return $rows;
    }

    /**
     * Строка отчёта «По закрытым заявкам».
     */
    public function buildClosedOrdersRow(int $cityId, Carbon $dateFrom, Carbon $dateTo, array $filters = []): array
    {
        $city = City::with('parentCity')->findOrFail($cityId);

        return $this->buildClosedOrdersRows(collect([$city]), $dateFrom, $dateTo, $filters)[0];
    }

    /**
     * SQL-агрегат закрытых заказов по городам (без загрузки моделей в PHP).
     *
     * @param  list<int>  $cityIds
     * @return Collection<int, array>
     */
    public function aggregateClosedOrdersSql(array $cityIds, Carbon $from, Carbon $to, array $filters = []): Collection
    {
        if ($cityIds === []) {
            return collect();
        }

        $partnerCase = '(CASE
            WHEN orders.partner_user_id IS NOT NULL AND orders.partner_user_id > 0 THEN 1
            WHEN sources.superpart_partner_id IS NOT NULL AND sources.superpart_partner_id > 0 THEN 1
            WHEN sources.available_for_superpart = 1 THEN 1
            ELSE 0
        END)';

        $query = Order::query()
            ->from('orders')
            ->join('addresses', 'addresses.address_id', '=', 'orders.address_id')
            ->leftJoin('sources', 'sources.source_id', '=', 'orders.source_id')
            ->whereIn('addresses.city_id', $cityIds)
            ->where('orders.order_status', 'completed')
            ->whereNotNull('orders.order_closed_at')
            ->where('orders.order_closed_at', '>=', $from)
            ->where('orders.order_closed_at', '<=', $to);

        $this->applyOrderFilters($query, $filters);

        $rows = $query
            ->selectRaw("
                addresses.city_id as city_id,
                COUNT(*) as closed_total,
                COALESCE(SUM(orders.amount_paid), 0) as turnover,
                COALESCE(SUM(orders.amount_paid - orders.amount_comp), 0) as net,
                COALESCE(SUM(orders.amount_comp), 0) as parts,
                COALESCE(SUM({$partnerCase}), 0) as closed_partner,
                COALESCE(SUM(CASE WHEN orders.order_type = 'warranty' THEN 1 ELSE 0 END), 0) as warranty_closed,
                COALESCE(SUM(CASE WHEN orders.order_type = 'repeat' THEN 1 ELSE 0 END), 0) as repeat_closed,
                COALESCE(SUM(CASE WHEN orders.order_core = 'non_core' THEN 1 ELSE 0 END), 0) as non_core_closed
            ")
            ->groupBy('addresses.city_id')
            ->get();

        return $rows->mapWithKeys(function ($row) {
            $closed = (int) $row->closed_total;
            $partner = (int) $row->closed_partner;

            return [
                (int) $row->city_id => [
                    'closed_total' => $closed,
                    'closed_partner' => $partner,
                    'closed_our' => max(0, $closed - $partner),
                    'turnover' => (int) $row->turnover,
                    'net' => (int) $row->net,
                    'parts' => (int) $row->parts,
                    'warranty_closed' => (int) $row->warranty_closed,
                    'repeat_closed' => (int) $row->repeat_closed,
                    'non_core_closed' => (int) $row->non_core_closed,
                ],
            ];
        });
    }

    /**
     * @param  list<int>  $cityIds
     * @return array<int, int>
     */
    private function aggregateAcceptedByCity(array $cityIds, Carbon $from, Carbon $to, array $filters = []): array
    {
        if ($cityIds === []) {
            return [];
        }

        $query = Order::query()
            ->from('orders')
            ->join('addresses', 'addresses.address_id', '=', 'orders.address_id')
            ->whereIn('addresses.city_id', $cityIds)
            ->where('orders.order_created_at', '>=', $from)
            ->where('orders.order_created_at', '<=', $to);

        $this->applyOrderFilters($query, $filters);

        return $query
            ->selectRaw('addresses.city_id as city_id, COUNT(*) as cnt')
            ->groupBy('addresses.city_id')
            ->pluck('cnt', 'city_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @param  list<int>  $cityIds
     * @return array<int, int>
     */
    private function aggregateComplaintsByCity(array $cityIds, Carbon $from, Carbon $to): array
    {
        if ($cityIds === [] || ! Schema::hasTable('complaints')) {
            return [];
        }

        return Complaint::query()
            ->whereIn('city_id', $cityIds)
            ->where('complaint_created_at', '>=', $from)
            ->where('complaint_created_at', '<=', $to)
            ->selectRaw('city_id, COUNT(*) as cnt')
            ->groupBy('city_id')
            ->pluck('cnt', 'city_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public function sumClosedOrdersRows(array $rows): array
    {
        $keys = [
            'turnover', 'forecast', 'net', 'closed_total', 'closed_our', 'closed_partner',
            'complaints_count', 'promo_pay', 'accepted_count', 'price_order_count',
        ];
        $totals = $this->sumRows($rows, $keys);

        $closed = $totals['closed_total'] ?? 0;
        if ($closed > 0) {
            $totals['avg_check'] = (int) round(($totals['turnover'] ?? 0) / $closed);
            $totals['net_avg_check'] = (int) round(($totals['net'] ?? 0) / $closed);
            $totals['complaints_pct'] = round(($totals['complaints_count'] ?? 0) / $closed * 100, 1);
        }
        $priceOrders = ($totals['closed_total'] ?? 0) > 0
            ? $totals['closed_total']
            : ($totals['accepted_count'] ?? 0);
        if ($priceOrders > 0) {
            $totals['lead_price'] = (int) round(($totals['promo_pay'] ?? 0) / $priceOrders);
            $totals['price_order_count'] = $priceOrders;
        }

        return $totals;
    }

    /**
     * Выплаты промоутерам за период: модуль «Выплаты» или касса «Зарплата промоутеров».
     */
    private function promoPayForCity(int $cityId, Carbon $from, Carbon $to): int
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        if (Schema::hasTable('prom_payments')) {
            $fromPayments = (int) PromPayment::query()
                ->where('city_id', $cityId)
                ->where('week_start', '<=', $toDate)
                ->where('week_end', '>=', $fromDate)
                ->sum('amount_total');

            if ($fromPayments > 0) {
                return $fromPayments;
            }
        }

        return $this->promoterCfmSpendForCity($cityId, $from, $to);
    }

    private function promoterCfmSpendForCity(int $cityId, Carbon $from, Carbon $to): int
    {
        $catIds = CfmCategory::query()
            ->where('cfm_cat_group', 'outflows')
            ->where(function ($q) {
                $q->where('cfm_cat_name', 'like', '%промоут%')
                    ->orWhere('cfm_cat_name', 'like', '%Промоут%');
            })
            ->pluck('cfm_cat_id');

        if ($catIds->isEmpty()) {
            return 0;
        }

        return (int) CfmOperation::query()
            ->where('city_id', $cityId)
            ->whereIn('cfm_cat_id', $catIds)
            ->whereNotNull('cfm_closed_at')
            ->where('cfm_closed_at', '>=', $from)
            ->where('cfm_closed_at', '<=', $to)
            ->sum('amount_cfm');
    }

    /**
     * Мастера города за период (закрытые заказы по дате проведения).
     */
    public function buildMasterRows(
        array $cityIds,
        Carbon $dateFrom,
        Carbon $dateTo,
        bool $showFired = false,
        ?int $minClosed = null,
    ): Collection {
        $from = $dateFrom->copy()->startOfDay();
        $to = $dateTo->copy()->endOfDay();

        if (empty($cityIds)) {
            return collect();
        }

        $mastersQuery = User::whereHas('roles', fn ($q) => $q->where('role_code', 'master'))
            ->whereHas('cities', fn ($q) => $q->whereIn('cities.city_id', $cityIds));

        if (! $showFired) {
            $mastersQuery->where('is_active', true)->whereNull('user_fired_at');
        }

        $masters = $mastersQuery->orderBy('user_name')->get();
        if ($masters->isEmpty()) {
            return collect();
        }

        $masterIds = $masters->pluck('user_id')->all();

        // Batch: все закрытые заказы мастеров за период + SD-счётчики (без N×get)
        $completedByMaster = Order::query()
            ->with(['address.city.parentCity', 'persons.addresses.city'])
            ->whereIn('master_id', $masterIds)
            ->whereHas('address', fn ($q) => $q->whereIn('city_id', $cityIds))
            ->where('order_status', 'completed')
            ->whereNotNull('order_closed_at')
            ->where('order_closed_at', '>=', $from)
            ->where('order_closed_at', '<=', $to)
            ->get()
            ->groupBy('master_id');

        $sdByMaster = Order::query()
            ->from('orders')
            ->join('addresses', 'addresses.address_id', '=', 'orders.address_id')
            ->whereIn('orders.master_id', $masterIds)
            ->whereIn('addresses.city_id', $cityIds)
            ->where('orders.order_status', 'in_progress_sd')
            ->where('orders.order_created_at', '>=', $from)
            ->where('orders.order_created_at', '<=', $to)
            ->selectRaw('orders.master_id as master_id, COUNT(*) as sd_count')
            ->groupBy('orders.master_id')
            ->pluck('sd_count', 'master_id');

        return $masters->map(function (User $master) use ($completedByMaster, $sdByMaster) {
            $completed = $completedByMaster->get($master->user_id, collect());
            $completedCount = $completed->count();
            $net = 0;
            $turnover = 0;
            $parts = 0;
            $low = 0;
            $salary = 0;

            foreach ($completed as $order) {
                $orderNet = $this->orderService->getNetAmount($order);
                $net += $orderNet;
                $turnover += (int) $order->amount_paid;
                $parts += (int) $order->amount_comp;
                $salary += $this->orderService->calculateMasterSalary($order);
                if ($orderNet < 4500) {
                    $low++;
                }
            }

            return [
                'user_id' => $master->user_id,
                'user_name' => $master->user_name,
                'is_fired' => (bool) $master->user_fired_at,
                'orders_count' => $completedCount,
                'sd_count' => (int) ($sdByMaster[$master->user_id] ?? 0),
                'net_avg_check' => $completedCount > 0 ? (int) round($net / $completedCount) : 0,
                'net_sum' => $net,
                'avg_check' => $completedCount > 0 ? (int) round($turnover / $completedCount) : 0,
                'parts' => $parts,
                'micra' => $low,
                'micra_pct' => $completedCount > 0 ? round($low / $completedCount * 100, 1) : 0,
                'salary' => $salary,
            ];
        })
            ->filter(function (array $row) use ($minClosed) {
                if ($row['orders_count'] === 0) {
                    return false;
                }
                if ($minClosed !== null && $minClosed > 0 && $row['orders_count'] < $minClosed) {
                    return false;
                }

                return true;
            })
            ->sortByDesc('net_sum')
            ->values();
    }

    public function sumRows(array $rows, array $keys): array
    {
        $totals = [];
        foreach ($keys as $key) {
            $totals[$key] = 0;
        }
        foreach ($rows as $row) {
            foreach ($keys as $key) {
                if (isset($row[$key]) && is_numeric($row[$key])) {
                    $totals[$key] += $row[$key];
                }
            }
        }

        if (isset($totals['closed']) && $totals['closed'] > 0) {
            if (isset($totals['net'])) {
                $totals['net_avg_check'] = (int) round($totals['net'] / $totals['closed']);
            }
            if (isset($totals['turnover'])) {
                $totals['avg_check'] = (int) round($totals['turnover'] / $totals['closed']);
            }
        }
        if (isset($totals['accepted']) && $totals['accepted'] > 0 && isset($totals['closed'])) {
            $totals['conversion_pct'] = round($totals['closed'] / $totals['accepted'] * 100, 1);
        }
        $priceOrders = ($totals['closed'] ?? 0) > 0 ? $totals['closed'] : ($totals['accepted'] ?? 0);
        if ($priceOrders > 0 && isset($totals['ad_spend'])) {
            $totals['lead_cost'] = (int) round($totals['ad_spend'] / $priceOrders);
        }

        return $totals;
    }

    private function applyOrderFilters($query, array $filters): void
    {
        if (! empty($filters['order_types'])) {
            $query->whereIn('orders.order_type', $filters['order_types']);
        }
        if (! empty($filters['order_cores'])) {
            $query->whereIn('orders.order_core', $filters['order_cores']);
        }
    }

    /** Прогноз оборота на конец месяца по текущему темпу (сумма филиала). */
    private function forecastTurnover(int $branchSumInPeriod, Carbon $from, Carbon $to): int
    {
        if ($branchSumInPeriod <= 0) {
            return 0;
        }

        $daysInPeriod = max(1, $from->diffInDays($to) + 1);
        $daysInMonth = $from->daysInMonth;
        $monthEnd = $from->copy()->endOfMonth();

        if ($to->gte($monthEnd) && $from->lte($from->copy()->startOfMonth())) {
            return $branchSumInPeriod;
        }

        return (int) round($branchSumInPeriod / $daysInPeriod * $daysInMonth);
    }
}
