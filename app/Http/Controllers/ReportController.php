<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Order;
use App\Services\CityReportService;
use App\Services\HrService;
use App\Services\MonthlyTableReportService;
use App\Services\ReportCsvExporter;
use App\Services\ReportXlsxExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private CityReportService $cityReport,
        private HrService $hrService,
        private MonthlyTableReportService $monthlyTableReport,
        private ReportCsvExporter $csvExporter,
        private ReportXlsxExporter $xlsxExporter,
    ) {}

    /**
     * Статистика по заявкам
     */
    public function orders(Request $request)
    {
        $user = auth()->user();
        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->endOfMonth()->format('Y-m-d'));
        $selectedSourceId = $request->get('source_id');
        $selectedSourceFormat = $request->get('source_format');
        $sources = \App\Models\Source::with('city')->where('is_active', true)->orderBy('source_name')->get();

        $cityIds = $this->cityReport->cityIdsForUser($user);
        $cities = City::whereIn('city_id', $cityIds)->orderBy('city_name')->get()->keyBy('city_id');

        $from = $dateFrom.' 00:00:00';
        $to = $dateTo.' 23:59:59';

        $query = Order::query()
            ->from('orders')
            ->join('addresses', 'addresses.address_id', '=', 'orders.address_id')
            ->whereIn('addresses.city_id', $cityIds)
            ->where('orders.order_created_at', '>=', $from)
            ->where('orders.order_created_at', '<=', $to)
            ->filterBySource($selectedSourceId, $selectedSourceFormat);

        $agg = $query
            ->selectRaw("
                addresses.city_id as city_id,
                COUNT(*) as accepted,
                SUM(CASE WHEN orders.order_status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN orders.order_status = 'cancelled_cc' THEN 1 ELSE 0 END) as cancelled_cc,
                SUM(CASE WHEN orders.order_status = 'cancelled_city' THEN 1 ELSE 0 END) as cancelled_city,
                SUM(CASE WHEN orders.order_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN orders.order_status = 'in_progress_sd' THEN 1 ELSE 0 END) as in_progress_sd,
                SUM(CASE WHEN orders.order_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN orders.order_status = 'waiting_parts' THEN 1 ELSE 0 END) as waiting_parts,
                SUM(CASE WHEN orders.order_status = 'waiting_payment' THEN 1 ELSE 0 END) as waiting_payment,
                SUM(CASE WHEN orders.order_type = 'warranty' THEN 1 ELSE 0 END) as warranty
            ")
            ->groupBy('addresses.city_id')
            ->get()
            ->keyBy('city_id');

        $report = [];
        foreach ($cities as $cityId => $city) {
            $row = $agg->get($cityId);
            $accepted = (int) ($row->accepted ?? 0);
            $cancelledCc = (int) ($row->cancelled_cc ?? 0);
            $cancelledCity = (int) ($row->cancelled_city ?? 0);
            $cancelledTotal = $cancelledCc + $cancelledCity;

            $report[] = [
                'city_name' => $city->city_name,
                'accepted' => $accepted,
                'completed' => (int) ($row->completed ?? 0),
                'cancelled_cc' => $cancelledCc,
                'cancelled_city' => $cancelledCity,
                'cancelled_total' => $cancelledTotal,
                'warranty' => (int) ($row->warranty ?? 0),
                'cancel_pct' => $accepted > 0 ? round($cancelledTotal / $accepted * 100, 1) : 0,
                'pending' => (int) ($row->pending ?? 0),
                'in_progress' => (int) ($row->in_progress ?? 0),
                'in_progress_sd' => (int) ($row->in_progress_sd ?? 0),
                'waiting_parts' => (int) ($row->waiting_parts ?? 0),
                'waiting_payment' => (int) ($row->waiting_payment ?? 0),
            ];
        }

        if ($request->get('export') === 'csv') {
            return $this->exportOrdersCsv($report, $dateFrom, $dateTo);
        }

        return view('reports.orders', compact('report', 'dateFrom', 'dateTo', 'sources', 'selectedSourceId', 'selectedSourceFormat'));
    }

    /**
     * Статистика отмен и отказов
     */
    public function cancellations(Request $request)
    {
        $user = auth()->user();
        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->endOfMonth()->format('Y-m-d'));

        $cityIds = $this->cityReport->cityIdsForUser($user);
        $cities = City::whereIn('city_id', $cityIds)->orderBy('city_name')->get()->keyBy('city_id');
        $from = $dateFrom.' 00:00:00';
        $to = $dateTo.' 23:59:59';

        $agg = Order::query()
            ->from('orders')
            ->join('addresses', 'addresses.address_id', '=', 'orders.address_id')
            ->whereIn('addresses.city_id', $cityIds)
            ->where('orders.order_created_at', '>=', $from)
            ->where('orders.order_created_at', '<=', $to)
            ->selectRaw("
                addresses.city_id as city_id,
                COUNT(*) as total,
                SUM(CASE WHEN orders.order_status = 'completed' THEN 1 ELSE 0 END) as completed,
                COALESCE(AVG(CASE WHEN orders.order_status = 'completed' THEN orders.amount_paid END), 0) as avg_check,
                SUM(CASE WHEN orders.order_status = 'cancelled_city' THEN 1 ELSE 0 END) as cancelled_city,
                SUM(CASE WHEN orders.order_status = 'cancelled_cc' THEN 1 ELSE 0 END) as cancelled_cc
            ")
            ->groupBy('addresses.city_id')
            ->get()
            ->keyBy('city_id');

        $report = [];
        foreach ($cities as $cityId => $city) {
            $row = $agg->get($cityId);
            $total = (int) ($row->total ?? 0);
            $cancelledCity = (int) ($row->cancelled_city ?? 0);
            $cancelledCc = (int) ($row->cancelled_cc ?? 0);
            $cancelledTotal = $cancelledCity + $cancelledCc;

            $report[] = [
                'city_name' => $city->city_name,
                'total' => $total,
                'completed' => (int) ($row->completed ?? 0),
                'avg_check' => (int) round((float) ($row->avg_check ?? 0)),
                'cancelled_city' => $cancelledCity,
                'cancelled_cc' => $cancelledCc,
                'cancelled_total' => $cancelledTotal,
                'cancel_city_pct' => $total > 0 ? round($cancelledCity / $total * 100, 1) : 0,
                'cancel_total_pct' => $total > 0 ? round($cancelledTotal / $total * 100, 1) : 0,
            ];
        }

        if ($request->get('export') === 'csv') {
            return $this->exportCancellationsCsv($report, $dateFrom, $dateTo);
        }

        return view('reports.cancellations', compact('report', 'dateFrom', 'dateTo'));
    }

    /**
     * Отчёт по партнёрам (источникам)
     */
    public function partners(Request $request)
    {
        $user = auth()->user();
        $dateFrom = $request->get('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->endOfMonth()->format('Y-m-d'));

        $cityIds = $this->cityReport->cityIdsForUser($user);
        $cities = City::whereIn('city_id', $cityIds)->orderBy('city_name')->get()->keyBy('city_id');
        $sources = \App\Models\Source::with('city')->where('is_active', true)->orderBy('source_name')->get();
        $selectedSourceId = $request->get('source_id');
        $selectedSourceFormat = $request->get('source_format');
        $from = $dateFrom.' 00:00:00';
        $to = $dateTo.' 23:59:59';

        $query = Order::query()
            ->from('orders')
            ->join('addresses', 'addresses.address_id', '=', 'orders.address_id')
            ->whereIn('addresses.city_id', $cityIds)
            ->where('orders.order_created_at', '>=', $from)
            ->where('orders.order_created_at', '<=', $to)
            ->filterBySource($selectedSourceId, $selectedSourceFormat);

        $agg = $query
            ->selectRaw("
                addresses.city_id as city_id,
                COUNT(*) as accepted,
                SUM(CASE WHEN orders.order_status IN ('cancelled_cc', 'cancelled_city') THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN orders.order_status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN orders.order_status IN ('in_progress', 'in_progress_sd', 'waiting_parts', 'waiting_payment') THEN 1 ELSE 0 END) as in_work,
                COALESCE(SUM(CASE WHEN orders.order_status = 'completed' THEN orders.amount_paid ELSE 0 END), 0) as total_paid,
                COALESCE(SUM(CASE WHEN orders.order_status = 'completed' THEN orders.amount_comp ELSE 0 END), 0) as total_comp
            ")
            ->groupBy('addresses.city_id')
            ->get()
            ->keyBy('city_id');

        $report = [];
        foreach ($cities as $cityId => $city) {
            $row = $agg->get($cityId);
            $completed = (int) ($row->completed ?? 0);
            $totalPaid = (int) ($row->total_paid ?? 0);
            $totalComp = (int) ($row->total_comp ?? 0);

            $report[] = [
                'city_name' => $city->city_name,
                'accepted' => (int) ($row->accepted ?? 0),
                'cancelled' => (int) ($row->cancelled ?? 0),
                'completed' => $completed,
                'in_work' => (int) ($row->in_work ?? 0),
                'total_paid' => $totalPaid,
                'total_comp' => $totalComp,
                'net' => $totalPaid - $totalComp,
                'avg_check' => $completed > 0 ? (int) round($totalPaid / $completed) : 0,
            ];
        }

        if ($request->get('export') === 'csv') {
            return $this->exportPartnersCsv($report, $dateFrom, $dateTo);
        }

        return view('reports.partners', compact('report', 'dateFrom', 'dateTo', 'sources', 'selectedSourceId', 'selectedSourceFormat'));
    }

    /**
     * Отчёт по закрытым заявкам (по городам).
     */
    public function closedOrders(Request $request)
    {
        $user = auth()->user();
        $range = $this->cityReport->resolveDateRange(
            $request->get('period'),
            $request->get('date_from'),
            $request->get('date_to'),
        );

        $filters = [
            'order_types' => array_filter((array) $request->get('order_type', [])),
            'order_cores' => array_filter((array) $request->get('order_core', [])),
        ];

        $cities = $this->cityReport->citiesForReport($user, $request->filled('city_id') ? (int) $request->city_id : null);

        $rows = $this->cityReport->buildClosedOrdersRows($cities, $range['from'], $range['to'], $filters);

        $sort = $request->get('sort', 'turnover');
        $dir = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSort = [
            'city_name', 'turnover', 'complaints_pct', 'closed_total', 'closed_our',
            'closed_partner', 'net', 'avg_check', 'net_avg_check', 'lead_price',
        ];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'turnover';
        }
        $rows = collect($rows);
        $rows = $dir === 'asc'
            ? $rows->sortBy($sort, SORT_NATURAL | SORT_FLAG_CASE)->values()
            : $rows->sortByDesc($sort, SORT_NATURAL | SORT_FLAG_CASE)->values();

        $totals = $this->cityReport->sumClosedOrdersRows($rows->all());

        $allCities = City::whereIn('city_id', $this->cityReport->cityIdsForUser($user))
            ->where('city_type', 'city')
            ->orderBy('city_name')
            ->get();

        if ($request->get('export') === 'csv') {
            return $this->exportClosedOrdersCsv($rows->all(), $totals, $range);
        }

        return view('reports.closed-orders', [
            'rows' => $rows,
            'totals' => $totals,
            'dateFrom' => $range['from']->format('Y-m-d'),
            'dateTo' => $range['to']->format('Y-m-d'),
            'periodLabel' => $range['label'],
            'cities' => $allCities,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /** Отчёт по городу — колонки как report-by-city.xlsx в КП. */
    public function byCity(Request $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\View\View
    {
        $user = auth()->user();
        $range = $this->cityReport->resolveDateRange(
            $request->get('period'),
            $request->get('date_from'),
            $request->get('date_to'),
        );

        $cities = $this->cityReport->citiesForReport($user, $request->filled('city_id') ? (int) $request->city_id : null);
        $rows = $this->cityReport->buildByCityRows($cities, $range['from'], $range['to']);
        $totals = $this->cityReport->sumByCityRows($rows);

        $allCities = City::whereIn('city_id', $this->cityReport->cityIdsForUser($user))
            ->where('city_type', 'city')
            ->orderBy('city_name')
            ->get();

        $export = $request->get('export');
        if (in_array($export, ['xlsx', 'csv'], true)) {
            return $this->exportByCity($rows, $totals, $range, $export);
        }

        return view('reports.by-city', [
            'rows' => $rows,
            'totals' => $totals,
            'dateFrom' => $range['from']->format('Y-m-d'),
            'dateTo' => $range['to']->format('Y-m-d'),
            'periodLabel' => $range['label'],
            'cities' => $allCities,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $totals
     * @param  array{from: Carbon, to: Carbon, label: string}  $range
     */
    private function exportByCity(array $rows, array $totals, array $range, string $format): \Symfony\Component\HttpFoundation\Response
    {
        $headers = [
            '№', 'Город', 'Сумма филиала', 'Прогноз оборота', '% приемки',
            'Принято продаж', 'Заявок открыто', 'Закрыто выезд', 'Закрыто стац.',
            'Чек выезд', 'Средний чек', 'Чек стационарный', 'Гарантия',
            'Отказ/НФ', 'Принял заявок', '% П.Оборот', '% П.Заявки',
        ];

        $exportRows = [];
        foreach ($rows as $i => $row) {
            $exportRows[] = [
                $i + 1,
                $row['city_name'],
                $row['branch_sum'],
                $row['forecast'],
                $row['acceptance_pct'],
                $row['sales_accepted'],
                $row['orders_open'],
                $row['closed_visit'],
                $row['closed_stationary'],
                $row['check_visit'],
                $row['avg_check'],
                $row['check_stationary'],
                $row['warranty_sum'],
                $row['refusal_nf'],
                $row['taken_by_cc'],
                $row['pct_p_turnover'].'%',
                $row['pct_p_orders'].'%',
            ];
        }

        if ($exportRows !== []) {
            $exportRows[] = [
                '',
                'Итого',
                $totals['branch_sum'] ?? 0,
                $totals['forecast'] ?? 0,
                $totals['acceptance_pct'] ?? '',
                $totals['sales_accepted'] ?? 0,
                $totals['orders_open'] ?? 0,
                $totals['closed_visit'] ?? 0,
                $totals['closed_stationary'] ?? 0,
                $totals['check_visit'] ?? 0,
                $totals['avg_check'] ?? '',
                $totals['check_stationary'] ?? '',
                $totals['warranty_sum'] ?? 0,
                $totals['refusal_nf'] ?? 0,
                $totals['taken_by_cc'] ?? 0,
                isset($totals['pct_p_turnover']) ? $totals['pct_p_turnover'].'%' : '',
                isset($totals['pct_p_orders']) ? $totals['pct_p_orders'].'%' : '',
            ];
        }

        if ($format === 'xlsx') {
            return $this->xlsxExporter->download('report-by-city.xlsx', $headers, $exportRows);
        }

        $from = $range['from']->format('Y-m-d');
        $to = $range['to']->format('Y-m-d');

        return $this->csvExporter->download(
            "report-by-city_{$from}_{$to}.csv",
            $headers,
            $exportRows,
        );
    }

    /**
     * Отчёт по городу + мастера — аналог report-city-employee.xlsx
     */
    public function cityEmployee(Request $request)
    {
        $user = auth()->user();
        $focusCityId = $request->filled('city_id') ? (int) $request->city_id : null;

        $periodRange = $this->cityReport->resolveDateRange(
            $request->get('period'),
            $request->get('date_from'),
            $request->get('date_to'),
        );

        $todayRange = $this->cityReport->resolveDateRange('today', null, null);

        $cities = $this->cityReport->citiesForReport($user, $focusCityId);
        $cityIds = $cities->pluck('city_id')->all();

        $todayRows = [];
        $periodRows = [];
        foreach ($cities as $city) {
            $todayRows[] = $this->cityReport->buildCityStats($city->city_id, $todayRange['from'], $todayRange['to']);
            $periodRows[] = $this->cityReport->buildCityStats($city->city_id, $periodRange['from'], $periodRange['to']);
        }

        $sumKeys = [
            'turnover', 'net', 'accepted', 'closed', 'closed_our', 'closed_partner',
            'refusals', 'rejected', 'ad_spend',
        ];
        $todayTotals = $this->cityReport->sumRows($todayRows, $sumKeys);
        $periodTotals = $this->cityReport->sumRows($periodRows, $sumKeys);

        $showFired = $request->boolean('show_fired');
        $masters = $this->cityReport->buildMasterRows(
            $cityIds,
            $periodRange['from'],
            $periodRange['to'],
            $showFired,
        );

        $allCities = City::whereIn('city_id', $this->cityReport->cityIdsForUser($user))
            ->where('city_type', 'city')
            ->orderBy('city_name')
            ->get();

        $focusCity = $focusCityId ? City::with('parentCity')->find($focusCityId) : null;
        $pageTitle = $focusCity
            ? 'Отчёт по городу — '.$this->cityReport->displayCityName($focusCity)
            : 'Отчёт по городу';

        if ($request->get('export') === 'csv') {
            return $this->exportCityEmployeeCsv(
                $todayRows,
                $todayTotals,
                $todayRange['label'],
                $periodRows,
                $periodTotals,
                $periodRange['label'],
                $masters,
                $periodRange,
            );
        }

        return view('reports.city-employee', [
            'pageTitle' => $pageTitle,
            'todayRows' => $todayRows,
            'periodRows' => $periodRows,
            'todayTotals' => $todayTotals,
            'periodTotals' => $periodTotals,
            'todayLabel' => $todayRange['label'],
            'periodLabel' => $periodRange['label'],
            'dateFrom' => $periodRange['from']->format('Y-m-d'),
            'dateTo' => $periodRange['to']->format('Y-m-d'),
            'cities' => $allCities,
            'focusCityId' => $focusCityId,
            'masters' => $masters,
            'showFired' => $showFired,
        ]);
    }

    /**
     * Помесячная таблица по городу (листовки / промо / филиал).
     */
    public function monthlyTable(Request $request)
    {
        $user = auth()->user();
        $allowedCityIds = $this->cityReport->cityIdsForUser($user);

        $cities = City::with('parentCity')
            ->whereIn('city_id', $allowedCityIds)
            ->where('city_type', 'city')
            ->where('is_active', true)
            ->orderBy('city_name')
            ->get();

        $cityId = $request->filled('city_id') ? (int) $request->city_id : (int) ($cities->first()?->city_id ?? 0);
        if ($cityId && ! in_array($cityId, $allowedCityIds, true)) {
            abort(403);
        }

        $toMonth = $request->filled('month_to')
            ? Carbon::parse($request->get('month_to').'-01')->startOfMonth()
            : now()->startOfMonth();
        $fromMonth = $request->filled('month_from')
            ? Carbon::parse($request->get('month_from').'-01')->startOfMonth()
            : $toMonth->copy()->subMonths(12)->startOfMonth();

        if ($fromMonth->gt($toMonth)) {
            [$fromMonth, $toMonth] = [$toMonth->copy(), $fromMonth->copy()];
        }

        // Защита от слишком длинного диапазона
        if ($fromMonth->diffInMonths($toMonth) > 35) {
            $fromMonth = $toMonth->copy()->subMonths(35)->startOfMonth();
        }

        $city = $cityId
            ? City::with('parentCity')->find($cityId)
            : null;

        $rows = $city
            ? $this->monthlyTableReport->buildRows($city, $fromMonth, $toMonth)
            : [];

        $pageTitle = $city
            ? 'Помесячная таблица — '.$this->cityReport->displayCityName($city)
            : 'Помесячная таблица';

        return view('reports.monthly-table', [
            'rows' => $rows,
            'cities' => $cities,
            'cityId' => $cityId ?: null,
            'city' => $city,
            'pageTitle' => $pageTitle,
            'monthFrom' => $fromMonth->format('Y-m'),
            'monthTo' => $toMonth->format('Y-m'),
            'fromMonth' => $fromMonth,
            'toMonth' => $toMonth,
        ]);
    }

    /**
     * Оборот мастеров (аналог report-employee-overall в старой CRM).
     */
    public function mastersTurnover(Request $request): StreamedResponse|\Illuminate\View\View
    {
        $user = auth()->user();
        $range = $this->cityReport->resolveDateRange(
            $request->get('period'),
            $request->get('date_from'),
            $request->get('date_to'),
        );

        $cityIds = $this->cityReport->cityIdsForUser($user);
        if ($request->filled('city_id')) {
            $focusId = (int) $request->city_id;
            if (in_array($focusId, $cityIds, true)) {
                $cityIds = [$focusId];
            }
        }

        $rows = $this->hrService->getMastersTurnover(
            $cityIds,
            $range['from'],
            $range['to'],
        );

        $sort = $request->get('sort', 'turnover');
        $dir = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['turnover', 'percent_4'];
        if (in_array($sort, $allowedSort, true)) {
            $rows = $dir === 'asc'
                ? $rows->sortBy($sort)->values()
                : $rows->sortByDesc($sort)->values();
        }

        if ($request->get('export') === 'csv') {
            return $this->exportMastersTurnoverCsv($rows, $range);
        }

        $cities = City::query()
            ->whereIn('city_id', $this->cityReport->cityIdsForUser($user))
            ->where('city_type', 'city')
            ->where('is_active', true)
            ->orderBy('city_name')
            ->get();

        return view('reports.masters-turnover', [
            'rows' => $rows,
            'dateFrom' => $range['from']->format('Y-m-d'),
            'dateTo' => $range['to']->format('Y-m-d'),
            'cities' => $cities,
            'sort' => $sort,
            'dir' => $dir,
            'totalCount' => $rows->count(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $report
     */
    private function exportOrdersCsv(array $report, string $dateFrom, string $dateTo): StreamedResponse
    {
        $headers = [
            'Город', 'Принято', 'Закрыто', 'Отмен КЦ', 'Отмен Филиала', 'Отмен Всего',
            'Гарантий', '% Отмен', 'Ожидает', 'В работе', 'В работе (СД)', 'Ожид. запчасти', 'Ожид. оплаты',
        ];
        $rows = [];
        $totals = array_fill_keys([
            'accepted', 'completed', 'cancelled_cc', 'cancelled_city', 'cancelled_total',
            'warranty', 'pending', 'in_progress', 'in_progress_sd', 'waiting_parts', 'waiting_payment',
        ], 0);

        foreach ($report as $row) {
            $rows[] = [
                $row['city_name'],
                $row['accepted'],
                $row['completed'],
                $row['cancelled_cc'],
                $row['cancelled_city'],
                $row['cancelled_total'],
                $row['warranty'],
                $row['cancel_pct'].'%',
                $row['pending'],
                $row['in_progress'],
                $row['in_progress_sd'],
                $row['waiting_parts'],
                $row['waiting_payment'],
            ];
            foreach ($totals as $key => $_) {
                $totals[$key] += $row[$key];
            }
        }

        $cancelPct = $totals['accepted'] > 0
            ? round($totals['cancelled_total'] / $totals['accepted'] * 100, 1).'%'
            : '0%';

        $rows[] = [
            'ИТОГО',
            $totals['accepted'],
            $totals['completed'],
            $totals['cancelled_cc'],
            $totals['cancelled_city'],
            $totals['cancelled_total'],
            $totals['warranty'],
            $cancelPct,
            $totals['pending'],
            $totals['in_progress'],
            $totals['in_progress_sd'],
            $totals['waiting_parts'],
            $totals['waiting_payment'],
        ];

        return $this->csvExporter->download(
            "statistika-zayavok_{$dateFrom}_{$dateTo}.csv",
            $headers,
            $rows,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $report
     */
    private function exportCancellationsCsv(array $report, string $dateFrom, string $dateTo): StreamedResponse
    {
        $headers = [
            'Город', 'Всего заявок', 'Закрыто', 'Ср. чек', 'Отмен Филиала', 'Отмен КЦ',
            'Отмен Всего', '% Отмен Филиала', '% Отмен Всего',
        ];
        $rows = [];
        $totals = [
            'total' => 0, 'completed' => 0, 'cancelled_city' => 0, 'cancelled_cc' => 0, 'cancelled_total' => 0,
        ];

        foreach ($report as $row) {
            $rows[] = [
                $row['city_name'],
                $row['total'],
                $row['completed'],
                $row['avg_check'],
                $row['cancelled_city'],
                $row['cancelled_cc'],
                $row['cancelled_total'],
                $row['cancel_city_pct'].'%',
                $row['cancel_total_pct'].'%',
            ];
            $totals['total'] += $row['total'];
            $totals['completed'] += $row['completed'];
            $totals['cancelled_city'] += $row['cancelled_city'];
            $totals['cancelled_cc'] += $row['cancelled_cc'];
            $totals['cancelled_total'] += $row['cancelled_total'];
        }

        $rows[] = [
            'ИТОГО',
            $totals['total'],
            $totals['completed'],
            $totals['completed'] > 0 ? round(array_sum(array_column($report, 'avg_check')) / max(count($report), 1), 0) : 0,
            $totals['cancelled_city'],
            $totals['cancelled_cc'],
            $totals['cancelled_total'],
            $totals['total'] > 0 ? round($totals['cancelled_city'] / $totals['total'] * 100, 1).'%' : '0%',
            $totals['total'] > 0 ? round($totals['cancelled_total'] / $totals['total'] * 100, 1).'%' : '0%',
        ];

        return $this->csvExporter->download(
            "otmeny-i-otkazy_{$dateFrom}_{$dateTo}.csv",
            $headers,
            $rows,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $report
     */
    private function exportPartnersCsv(array $report, string $dateFrom, string $dateTo): StreamedResponse
    {
        $headers = [
            'Город', 'Принято', 'Отмены', 'Закрыто', 'В работе',
            'Оплачено', 'Комплектующие', 'Чистыми', 'Ср. чек',
        ];
        $rows = [];
        $totals = array_fill_keys([
            'accepted', 'cancelled', 'completed', 'in_work', 'total_paid', 'total_comp', 'net',
        ], 0);

        foreach ($report as $row) {
            $rows[] = [
                $row['city_name'],
                $row['accepted'],
                $row['cancelled'],
                $row['completed'],
                $row['in_work'],
                $row['total_paid'],
                $row['total_comp'],
                $row['net'],
                $row['avg_check'],
            ];
            foreach ($totals as $key => $_) {
                $totals[$key] += $row[$key];
            }
        }

        $avgCheck = $totals['completed'] > 0 ? round($totals['total_paid'] / $totals['completed'], 0) : 0;

        $rows[] = [
            'ИТОГО',
            $totals['accepted'],
            $totals['cancelled'],
            $totals['completed'],
            $totals['in_work'],
            $totals['total_paid'],
            $totals['total_comp'],
            $totals['net'],
            $avgCheck,
        ];

        return $this->csvExporter->download(
            "partnery_{$dateFrom}_{$dateTo}.csv",
            $headers,
            $rows,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $totals
     */
    private function exportClosedOrdersCsv(array $rows, array $totals, array $range): StreamedResponse
    {
        $headers = [
            '№', 'Город', 'Сумма филиала', 'Прогноз оборота', '% претензий',
            'Заявок закрыто', 'Закрыто наши', 'Закрыто партн.', 'Чистыми',
            'Средний чек', 'Чистый средний', 'Цена заявки',
        ];
        $csvRows = [];
        foreach ($rows as $i => $row) {
            $csvRows[] = [
                $i + 1,
                $row['city_name'],
                $row['turnover'],
                $row['forecast'],
                $row['complaints_pct'].'%',
                $row['closed_total'],
                $row['closed_our'],
                $row['closed_partner'],
                $row['net'],
                $row['avg_check'],
                $row['net_avg_check'],
                ($row['price_order_count'] ?? 0) > 0 ? $row['lead_price'] : '',
            ];
        }

        if ($csvRows !== []) {
            $csvRows[] = [
                '',
                'Итого',
                $totals['turnover'] ?? 0,
                $totals['forecast'] ?? 0,
                ($totals['complaints_pct'] ?? 0).'%',
                $totals['closed_total'] ?? 0,
                $totals['closed_our'] ?? 0,
                $totals['closed_partner'] ?? 0,
                $totals['net'] ?? 0,
                $totals['avg_check'] ?? '',
                $totals['net_avg_check'] ?? '',
                ($totals['price_order_count'] ?? 0) > 0 ? ($totals['lead_price'] ?? '') : '',
            ];
        }

        $from = $range['from']->format('Y-m-d');
        $to = $range['to']->format('Y-m-d');

        return $this->csvExporter->download(
            "zakrytye-zayavki_{$from}_{$to}.csv",
            $headers,
            $csvRows,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $todayRows
     * @param  list<array<string, mixed>>  $periodRows
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $masters
     */
    private function exportCityEmployeeCsv(
        array $todayRows,
        array $todayTotals,
        string $todayLabel,
        array $periodRows,
        array $periodTotals,
        string $periodLabel,
        $masters,
        array $periodRange,
    ): StreamedResponse {
        $cityHeaders = [
            '№', 'Город', 'Оборот', 'Чистыми', 'Принято', 'Закрыто', 'Наши', 'Партнёр',
            'Отказов', 'Отказы ОФ', 'Чист. ср.чек', 'Цена заявки', 'КДВП %',
        ];

        $mapCityRow = function (array $row, int $index): array {
            return [
                $index + 1,
                $row['city_name'],
                $row['turnover'],
                $row['net'],
                $row['accepted'],
                $row['closed'],
                $row['closed_our'],
                $row['closed_partner'],
                $row['refusals'],
                $row['rejected'],
                $row['net_avg_check'],
                ($row['closed'] ?? $row['accepted'] ?? 0) > 0 ? $row['lead_cost'] : '',
                $row['conversion_pct'].'%',
            ];
        };

        $mapCityTotal = function (array $totals): array {
            return [
                '',
                'Итого',
                $totals['turnover'] ?? 0,
                $totals['net'] ?? 0,
                $totals['accepted'] ?? 0,
                $totals['closed'] ?? 0,
                $totals['closed_our'] ?? 0,
                $totals['closed_partner'] ?? 0,
                $totals['refusals'] ?? 0,
                $totals['rejected'] ?? 0,
                $totals['net_avg_check'] ?? '',
                (($totals['closed'] ?? 0) > 0 || ($totals['accepted'] ?? 0) > 0) ? ($totals['lead_cost'] ?? '') : '',
                ($totals['conversion_pct'] ?? 0).'%',
            ];
        };

        $todayCsv = [];
        foreach ($todayRows as $i => $row) {
            $todayCsv[] = $mapCityRow($row, $i);
        }
        if ($todayCsv !== []) {
            $todayCsv[] = $mapCityTotal($todayTotals);
        }

        $periodCsv = [];
        foreach ($periodRows as $i => $row) {
            $periodCsv[] = $mapCityRow($row, $i);
        }
        if ($periodCsv !== []) {
            $periodCsv[] = $mapCityTotal($periodTotals);
        }

        $masterRows = [];
        foreach ($masters as $i => $m) {
            $masterRows[] = [
                $i + 1,
                $m['user_name'].($m['is_fired'] ? ' (увол.)' : ''),
                $m['orders_count'],
                $m['sd_count'],
                $m['net_avg_check'],
                $m['net_sum'],
                $m['avg_check'],
                $m['parts'],
                $m['micra'],
                $m['micra_pct'].'%',
                $m['salary'],
            ];
        }

        $from = $periodRange['from']->format('Y-m-d');
        $to = $periodRange['to']->format('Y-m-d');

        return $this->csvExporter->downloadSections(
            "otchet-po-gorodu_{$from}_{$to}.csv",
            [
                ['title' => $todayLabel, 'headers' => $cityHeaders, 'rows' => $todayCsv],
                ['title' => $periodLabel, 'headers' => $cityHeaders, 'rows' => $periodCsv],
                [
                    'title' => 'Мастера — '.$periodLabel,
                    'headers' => [
                        '№', 'ФИО', 'Кол-во заявок', 'СД', 'Чистый ср.чек', 'Чистыми всего',
                        'Общий ср.чек', 'Зап/части', 'Микра', 'Микра %', 'Зарплата',
                    ],
                    'rows' => $masterRows,
                ],
            ],
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     */
    private function exportMastersTurnoverCsv($rows, array $range): StreamedResponse
    {
        $csvRows = [];
        foreach ($rows as $index => $row) {
            $csvRows[] = [
                $index + 1,
                $row['user_name'],
                $row['turnover'],
                $row['percent_4'],
                $row['total_turnover_temp'],
                $row['percent_4_checks'],
            ];
        }

        $from = $range['from']->format('Y-m-d');
        $to = $range['to']->format('Y-m-d');

        return $this->csvExporter->download(
            "oborot-masterov_{$from}_{$to}.csv",
            ['№', 'Мастер', 'Оборот', '4%', 'Общий оборот (временно)', '4% от чеков'],
            $csvRows,
        );
    }
}
