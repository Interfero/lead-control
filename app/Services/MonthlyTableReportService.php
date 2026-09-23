<?php

namespace App\Services;

use App\Models\City;
use App\Models\CfmCategory;
use App\Models\CfmOperation;
use App\Models\PromPayment;
use App\Models\RouteAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Помесячная таблица по одному городу (листовки / промо / филиал).
 */
class MonthlyTableReportService
{
    private const ADS_CATEGORIES = ['HeadHunter', 'OLX', 'Объявление Авито'];

    public function __construct(
        private CityReportService $cityReport,
    ) {}

    /**
     * @return list<array{
     *   month: string,
     *   month_label: string,
     *   leaflets: int|null,
     *   chs: int|null,
     *   promo_ut: int|null,
     *   posting: int|null,
     *   ads: int|null,
     *   promo_pay: int|null,
     *   turnover: int,
     *   closed_our: int,
     *   lead_price: float|null,
     *   box_price: float|null
     * }>
     */
    public function buildRows(City $city, Carbon $fromMonth, Carbon $toMonth): array
    {
        $cityIds = [$city->city_id];
        $cursor = $fromMonth->copy()->startOfMonth();
        $end = $toMonth->copy()->startOfMonth();
        $rows = [];

        while ($cursor->lte($end)) {
            $from = $cursor->copy()->startOfMonth();
            $to = $cursor->copy()->endOfMonth();

            $orders = $this->cityReport->aggregateClosedOrdersSql($cityIds, $from, $to)->get($city->city_id) ?? [
                'turnover' => 0,
                'closed_our' => 0,
            ];

            $leaflets = $this->leafletsForCities($cityIds, $from, $to);
            $chs = $this->privateSectorLeafletsForCities($cityIds, $from, $to);
            $promoUt = $this->promoActionsForCities($cityIds, $from, $to);
            $posting = $this->postingSpendForCities($cityIds, $from, $to);
            $ads = $this->adsSpendForCities($cityIds, $from, $to);
            $promoPay = $this->promoPayForCities($cityIds, $from, $to);

            $closedOur = (int) ($orders['closed_our'] ?? 0);
            $turnover = (int) ($orders['turnover'] ?? 0);
            $marketing = ($promoPay ?? 0) + ($ads ?? 0);

            $rows[] = [
                'month' => $cursor->format('Y-m'),
                'month_label' => $this->monthLabelRu($cursor),
                'leaflets' => $leaflets,
                'chs' => $chs,
                'promo_ut' => $promoUt,
                'posting' => $posting,
                'ads' => $ads,
                'promo_pay' => $promoPay,
                'turnover' => $turnover,
                'closed_our' => $closedOur,
                'lead_price' => ($closedOur > 0 && $marketing > 0)
                    ? round($marketing / $closedOur, 2)
                    : null,
                'box_price' => ($leaflets !== null && $leaflets > 0 && $promoPay !== null && $promoPay > 0)
                    ? round($promoPay / $leaflets, 2)
                    : null,
            ];

            $cursor->addMonth();
        }

        return $rows;
    }

    private function monthLabelRu(Carbon $month): string
    {
        $map = [
            1 => 'Янв', 2 => 'Фев', 3 => 'Мар', 4 => 'Апр',
            5 => 'Май', 6 => 'Июн', 7 => 'Июл', 8 => 'Авг',
            9 => 'Сен', 10 => 'Окт', 11 => 'Ноя', 12 => 'Дек',
        ];

        return ($map[(int) $month->month] ?? $month->format('M')).' '.$month->format('y');
    }

    /**
     * @param  list<int>  $cityIds
     */
    private function leafletsForCities(array $cityIds, Carbon $from, Carbon $to): ?int
    {
        if (! Schema::hasTable('route_actions')) {
            return null;
        }

        $sum = (int) RouteAction::query()
            ->whereIn('city_id', $cityIds)
            ->whereBetween('route_action_date', [$from->toDateString(), $to->toDateString()])
            ->sum('leaflets_count');

        return $sum;
    }

    /**
     * ЧС — листовки по маршрутам «частный сектор» (route_type=private).
     *
     * @param  list<int>  $cityIds
     */
    private function privateSectorLeafletsForCities(array $cityIds, Carbon $from, Carbon $to): ?int
    {
        if (! Schema::hasTable('route_actions') || ! Schema::hasTable('routes')) {
            return null;
        }

        return (int) RouteAction::query()
            ->whereIn('route_actions.city_id', $cityIds)
            ->whereBetween('route_actions.route_action_date', [$from->toDateString(), $to->toDateString()])
            ->join('routes', 'routes.route_id', '=', 'route_actions.route_id')
            ->where('routes.route_type', 'private')
            ->sum('route_actions.leaflets_count');
    }

    /**
     * Промоутеры — число выходов промо (действий на маршрутах) за месяц.
     *
     * @param  list<int>  $cityIds
     */
    private function promoActionsForCities(array $cityIds, Carbon $from, Carbon $to): ?int
    {
        if (! Schema::hasTable('route_actions')) {
            return null;
        }

        return (int) RouteAction::query()
            ->whereIn('city_id', $cityIds)
            ->whereBetween('route_action_date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }

    /**
     * Расклейка — статья ДДС с «раскл» в названии (если заведена в кассе).
     *
     * @param  list<int>  $cityIds
     */
    private function postingSpendForCities(array $cityIds, Carbon $from, Carbon $to): ?int
    {
        if (! Schema::hasTable('cfm_operations') || ! Schema::hasTable('cfm_categories')) {
            return null;
        }

        $catIds = CfmCategory::query()
            ->where('cfm_cat_group', 'outflows')
            ->where(function ($q) {
                $q->where('cfm_cat_name', 'like', '%раскл%')
                    ->orWhere('cfm_cat_name', 'like', '%Раскл%');
            })
            ->pluck('cfm_cat_id');

        if ($catIds->isEmpty()) {
            return null;
        }

        $sum = (int) CfmOperation::query()
            ->whereIn('city_id', $cityIds)
            ->whereIn('cfm_cat_id', $catIds)
            ->whereNotNull('cfm_closed_at')
            ->where('cfm_closed_at', '>=', $from)
            ->where('cfm_closed_at', '<=', $to)
            ->sum('amount_cfm');

        return $sum > 0 ? $sum : null;
    }

    /**
     * @param  list<int>  $cityIds
     */
    private function adsSpendForCities(array $cityIds, Carbon $from, Carbon $to): ?int
    {
        if (! Schema::hasTable('cfm_operations') || ! Schema::hasTable('cfm_categories')) {
            return null;
        }

        $catIds = CfmCategory::query()
            ->where('cfm_cat_group', 'outflows')
            ->whereIn('cfm_cat_name', self::ADS_CATEGORIES)
            ->pluck('cfm_cat_id');

        if ($catIds->isEmpty()) {
            return null;
        }

        return (int) CfmOperation::query()
            ->whereIn('city_id', $cityIds)
            ->whereIn('cfm_cat_id', $catIds)
            ->whereNotNull('cfm_closed_at')
            ->where('cfm_closed_at', '>=', $from)
            ->where('cfm_closed_at', '<=', $to)
            ->sum('amount_cfm');
    }

    /**
     * @param  list<int>  $cityIds
     */
    private function promoPayForCities(array $cityIds, Carbon $from, Carbon $to): ?int
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $total = 0;

        if (Schema::hasTable('prom_payments')) {
            $fromPayments = (int) PromPayment::query()
                ->whereIn('city_id', $cityIds)
                ->where('week_start', '<=', $toDate)
                ->where('week_end', '>=', $fromDate)
                ->sum('amount_total');

            if ($fromPayments > 0) {
                return $fromPayments;
            }
        }

        if (! Schema::hasTable('cfm_operations') || ! Schema::hasTable('cfm_categories')) {
            return 0;
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

        $total = (int) CfmOperation::query()
            ->whereIn('city_id', $cityIds)
            ->whereIn('cfm_cat_id', $catIds)
            ->whereNotNull('cfm_closed_at')
            ->where('cfm_closed_at', '>=', $from)
            ->where('cfm_closed_at', '<=', $to)
            ->sum('amount_cfm');

        return $total;
    }
}
