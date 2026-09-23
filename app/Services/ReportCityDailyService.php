<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\CfmCategory;
use App\Models\CfmOperation;
use App\Models\Order;
use App\Models\PromPayment;
use App\Models\ReportCityDaily;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Материализованные срезы отчётов: 1 строка = город + календарный день.
 */
class ReportCityDailyService
{
    public function tableReady(): bool
    {
        return Schema::hasTable('report_city_daily');
    }

    public function markStaleForCityDate(int $cityId, Carbon|string $date): void
    {
        if (! $this->tableReady() || $cityId <= 0) {
            return;
        }

        $day = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        $row = ReportCityDaily::query()
            ->where('city_id', $cityId)
            ->whereDate('report_date', $day)
            ->first();

        if ($row) {
            if (! $row->stale) {
                $row->update(['stale' => true]);
            }

            return;
        }

        ReportCityDaily::query()->create([
            'city_id' => $cityId,
            'report_date' => $day,
            'stale' => true,
        ]);
    }

    public function markStaleForOrder(Order $order): void
    {
        $order->loadMissing('address');
        $cityId = (int) ($order->address?->city_id ?? 0);
        if ($cityId <= 0) {
            return;
        }

        if ($order->order_closed_at) {
            $this->markStaleForCityDate($cityId, $order->order_closed_at);
        }
        if ($order->order_created_at) {
            $this->markStaleForCityDate($cityId, $order->order_created_at);
        }
    }

    /**
     * Пересчитать один день одного города (идемпотентно).
     */
    public function rebuildDay(int $cityId, Carbon $day): ReportCityDaily
    {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $dateStr = $from->toDateString();

        $partnerCase = $this->partnerCaseSql();

        $closed = Order::query()
            ->from('orders')
            ->join('addresses', 'addresses.address_id', '=', 'orders.address_id')
            ->leftJoin('sources', 'sources.source_id', '=', 'orders.source_id')
            ->where('addresses.city_id', $cityId)
            ->where('orders.order_status', 'completed')
            ->whereNotNull('orders.order_closed_at')
            ->whereBetween('orders.order_closed_at', [$from, $to])
            ->selectRaw("
                COUNT(*) as closed_total,
                COALESCE(SUM(orders.amount_paid), 0) as turnover,
                COALESCE(SUM(orders.amount_paid - orders.amount_comp), 0) as net,
                COALESCE(SUM(orders.amount_comp), 0) as parts,
                COALESCE(SUM({$partnerCase}), 0) as closed_partner,
                COALESCE(SUM(CASE WHEN orders.order_type = 'warranty' THEN 1 ELSE 0 END), 0) as warranty_closed,
                COALESCE(SUM(CASE WHEN orders.order_type = 'repeat' THEN 1 ELSE 0 END), 0) as repeat_closed,
                COALESCE(SUM(CASE WHEN orders.order_core = 'non_core' THEN 1 ELSE 0 END), 0) as non_core_closed
            ")
            ->first();

        $closedTotal = (int) ($closed->closed_total ?? 0);
        $closedPartner = (int) ($closed->closed_partner ?? 0);

        $accepted = (int) Order::query()
            ->whereHas('address', fn ($q) => $q->where('city_id', $cityId))
            ->whereBetween('order_created_at', [$from, $to])
            ->count();

        $refusals = (int) Order::query()
            ->whereHas('address', fn ($q) => $q->where('city_id', $cityId))
            ->whereBetween('order_created_at', [$from, $to])
            ->whereIn('order_status', ['cancelled_cc', 'cancelled_city'])
            ->count();

        $rejected = (int) Order::query()
            ->whereHas('address', fn ($q) => $q->where('city_id', $cityId))
            ->whereBetween('order_created_at', [$from, $to])
            ->where('order_status', 'rejected')
            ->count();

        $complaints = Schema::hasTable('complaints')
            ? (int) Complaint::query()
                ->where('city_id', $cityId)
                ->whereBetween('complaint_created_at', [$from, $to])
                ->count()
            : 0;

        $promoPay = $this->promoPayForCity($cityId, $from, $to);

        return ReportCityDaily::query()->updateOrCreate(
            ['city_id' => $cityId, 'report_date' => $dateStr],
            [
                'accepted_count' => $accepted,
                'closed_total' => $closedTotal,
                'closed_our' => max(0, $closedTotal - $closedPartner),
                'closed_partner' => $closedPartner,
                'turnover' => (int) ($closed->turnover ?? 0),
                'net' => (int) ($closed->net ?? 0),
                'parts' => (int) ($closed->parts ?? 0),
                'complaints_count' => $complaints,
                'promo_pay' => $promoPay,
                'warranty_closed' => (int) ($closed->warranty_closed ?? 0),
                'repeat_closed' => (int) ($closed->repeat_closed ?? 0),
                'non_core_closed' => (int) ($closed->non_core_closed ?? 0),
                'refusals' => $refusals,
                'rejected' => $rejected,
                'stale' => false,
                'rebuilt_at' => now(),
            ]
        );
    }

    /**
     * @param  list<int>  $cityIds
     * @return int число пересчитанных дней
     */
    public function rebuildRange(array $cityIds, Carbon $from, Carbon $to, ?callable $progress = null): int
    {
        $cityIds = array_values(array_unique(array_filter(array_map('intval', $cityIds))));
        if ($cityIds === []) {
            return 0;
        }

        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $done = 0;

        while ($cursor->lte($end)) {
            foreach ($cityIds as $cityId) {
                $this->rebuildDay($cityId, $cursor->copy());
                $done++;
                if ($progress) {
                    $progress($cityId, $cursor->toDateString(), $done);
                }
            }
            $cursor->addDay();
        }

        return $done;
    }

    public function rebuildStale(?int $limit = null): int
    {
        if (! $this->tableReady()) {
            return 0;
        }

        $query = ReportCityDaily::query()->where('stale', true)->orderBy('report_date');
        if ($limit !== null) {
            $query->limit($limit);
        }

        $rows = $query->get(['city_id', 'report_date']);
        foreach ($rows as $row) {
            $this->rebuildDay((int) $row->city_id, Carbon::parse($row->report_date));
        }

        return $rows->count();
    }

    /**
     * @param  list<int>  $cityIds
     * @return array<int, array>|null keyed by city_id
     */
    public function aggregateClosedOrdersFromSlices(array $cityIds, Carbon $from, Carbon $to): ?array
    {
        if (! $this->tableReady() || $cityIds === []) {
            return null;
        }

        $fromDate = $from->toDateString();
        $toDate = $to->copy()->startOfDay()->toDateString();
        $expectedDays = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $expectedRows = count($cityIds) * $expectedDays;

        $staleOrMissing = $this->countMissingOrStale($cityIds, $fromDate, $toDate, $expectedRows);
        if ($staleOrMissing > 0) {
            $threshold = 400;
            if ($expectedRows <= $threshold && $staleOrMissing <= $threshold) {
                $this->ensureRangeBuilt($cityIds, $from, $to);
            } else {
                $this->rebuildStale(min(100, $staleOrMissing));
                $staleOrMissing = $this->countMissingOrStale($cityIds, $fromDate, $toDate, $expectedRows);
                if ($staleOrMissing > 0) {
                    Log::channel('single')->info('report_city_daily incomplete, fallback to live SQL', [
                        'missing_or_stale' => $staleOrMissing,
                        'expected' => $expectedRows,
                    ]);

                    return null;
                }
            }
        }

        $agg = ReportCityDaily::query()
            ->whereIn('city_id', $cityIds)
            ->whereBetween('report_date', [$fromDate, $toDate])
            ->selectRaw('
                city_id,
                SUM(accepted_count) as accepted_count,
                SUM(closed_total) as closed_total,
                SUM(closed_our) as closed_our,
                SUM(closed_partner) as closed_partner,
                SUM(turnover) as turnover,
                SUM(net) as net,
                SUM(parts) as parts,
                SUM(complaints_count) as complaints_count,
                SUM(promo_pay) as promo_pay
            ')
            ->groupBy('city_id')
            ->get()
            ->keyBy('city_id');

        $out = [];
        foreach ($cityIds as $cityId) {
            $row = $agg->get($cityId);
            $closed = (int) ($row->closed_total ?? 0);
            $accepted = (int) ($row->accepted_count ?? 0);
            $turnover = (int) ($row->turnover ?? 0);
            $net = (int) ($row->net ?? 0);
            $complaints = (int) ($row->complaints_count ?? 0);
            $promo = (int) ($row->promo_pay ?? 0);
            $priceOrders = $closed > 0 ? $closed : $accepted;

            $out[$cityId] = [
                'accepted_count' => $accepted,
                'closed_total' => $closed,
                'closed_our' => (int) ($row->closed_our ?? 0),
                'closed_partner' => (int) ($row->closed_partner ?? 0),
                'turnover' => $turnover,
                'net' => $net,
                'parts' => (int) ($row->parts ?? 0),
                'complaints_count' => $complaints,
                'complaints_pct' => $closed > 0 ? round($complaints / $closed * 100, 1) : 0,
                'promo_pay' => $promo,
                'avg_check' => $closed > 0 ? (int) round($turnover / $closed) : 0,
                'net_avg_check' => $closed > 0 ? (int) round($net / $closed) : 0,
                'lead_price' => $priceOrders > 0 ? (int) round($promo / $priceOrders) : 0,
                'price_order_count' => $priceOrders,
                'from_slice' => true,
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $cityIds
     */
    public function ensureRangeBuilt(array $cityIds, Carbon $from, Carbon $to): void
    {
        $fromDate = $from->toDateString();
        $toDate = $to->copy()->startOfDay()->toDateString();

        $existing = ReportCityDaily::query()
            ->whereIn('city_id', $cityIds)
            ->whereBetween('report_date', [$fromDate, $toDate])
            ->get(['city_id', 'report_date', 'stale']);

        $have = [];
        foreach ($existing as $row) {
            $key = $row->city_id.'|'.$row->report_date->toDateString();
            $have[$key] = (bool) $row->stale;
        }

        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $d = $cursor->toDateString();
            foreach ($cityIds as $cityId) {
                $key = $cityId.'|'.$d;
                if (! array_key_exists($key, $have) || $have[$key] === true) {
                    $this->rebuildDay($cityId, $cursor->copy());
                }
            }
            $cursor->addDay();
        }
    }

    /**
     * @param  list<int>  $cityIds
     */
    private function countMissingOrStale(array $cityIds, string $fromDate, string $toDate, int $expectedRows): int
    {
        $fresh = ReportCityDaily::query()
            ->whereIn('city_id', $cityIds)
            ->whereBetween('report_date', [$fromDate, $toDate])
            ->where('stale', false)
            ->count();

        return max(0, $expectedRows - $fresh);
    }

    private function partnerCaseSql(): string
    {
        return '(CASE
            WHEN orders.partner_user_id IS NOT NULL AND orders.partner_user_id > 0 THEN 1
            WHEN sources.superpart_partner_id IS NOT NULL AND sources.superpart_partner_id > 0 THEN 1
            WHEN sources.available_for_superpart = 1 THEN 1
            ELSE 0
        END)';
    }

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
}
