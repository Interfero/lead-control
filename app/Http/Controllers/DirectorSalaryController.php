<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\SalaryCalculation;
use App\Services\DirectorSalaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DirectorSalaryController extends Controller
{
    public function __construct(
        private DirectorSalaryService $salaryService,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $period = $this->salaryService->parsePeriodMonth($request->get('month'));
        $accrualPeriod = $this->salaryService->accrualPeriodForDisplay($period);
        $isPayoutMonthView = $this->salaryService->isPayoutMonthView($period);
        $filterCityId = $request->filled('city_id') ? (int) $request->get('city_id') : null;

        $this->salaryService->seedInitialPolicies(
            Carbon::create(2026, 8, 1, 0, 0, 0, config('app.timezone'))
        );

        $rows = $this->salaryService->listForUser($user, $accrualPeriod, $filterCityId);

        $isBranchOnly = $user->hasRole('branch_head')
            && ! $user->hasAnyRole(['developer', 'general_director', 'regional_director']);

        $cities = $isBranchOnly
            ? collect()
            : $user->accessibleCities()->orderBy('city_name')->get();

        $canManagePolicies = $user->hasAnyRole(['developer', 'general_director']);
        $isPreliminary = $this->salaryService->isCurrentMonth($accrualPeriod);

        $totals = [
            'incas_base' => 0,
            'commission_amount' => 0,
            'salary_amount' => 0,
            'accrued_amount' => 0,
            'paid' => 0,
            'available' => 0,
        ];
        foreach ($rows as $row) {
            /** @var SalaryCalculation $c */
            $c = $row['calculation'];
            $totals['incas_base'] += (int) $c->incas_base;
            $totals['commission_amount'] += (int) $c->commission_amount;
            $totals['salary_amount'] += (int) $c->salary_amount;
            $totals['accrued_amount'] += (int) $c->accrued_amount;
            $totals['paid'] += (int) $row['paid'];
            $totals['available'] += (int) $row['available'];
        }

        $detailId = $request->get('calc');
        $detail = null;
        $detailBalances = null;
        $detailPayments = collect();
        $detailCluster = collect();

        // Как в макете: нижняя карточка всегда есть — либо выбранный город, либо первый в списке.
        if (! $detailId && $rows->isNotEmpty()) {
            $detailId = $rows->first()['calculation']->salary_calculation_id;
        }

        if ($detailId) {
            $detail = SalaryCalculation::with(['city', 'recipient'])->findOrFail((int) $detailId);
            $this->salaryService->assertCanView($user, $detail);
            if ($isPayoutMonthView && ! Carbon::parse($detail->period_month)->isSameMonth($accrualPeriod)) {
                $mapped = $rows->first(
                    fn ($row) => (int) $row['calculation']->city_id === (int) $detail->city_id
                );
                if ($mapped) {
                    $detail = $mapped['calculation'];
                }
            }
            $fromRows = $rows->first(
                fn ($row) => (int) $row['calculation']->salary_calculation_id === (int) $detail->salary_calculation_id
            );
            $detailBalances = $fromRows
                ? [
                    'paid' => $fromRows['paid'],
                    'available' => $fromRows['available'],
                    'overpayment' => $fromRows['overpayment'],
                    'balance' => $fromRows['balance'],
                ]
                : $this->salaryService->balances($detail);
            $detailPayments = $detail->payments()
                ->with(['category', 'createdBy', 'closedBy'])
                ->whereHas('category', fn ($q) => $q->where('cfm_cat_name', DirectorSalaryService::PAYMENT_CATEGORY))
                ->orderByDesc('cfm_closed_at')
                ->orderByDesc('cfm_created_at')
                ->get();

            // Нижний блок: выбранный город + спутники / мать того же кластера
            $groupIds = City::operationGroupIds((int) $detail->city_id);
            $clusterByCity = [];
            foreach ($rows as $row) {
                $cid = (int) $row['calculation']->city_id;
                if (in_array($cid, $groupIds, true)) {
                    $clusterByCity[$cid] = $row;
                }
            }
            foreach ($groupIds as $gid) {
                if (isset($clusterByCity[$gid])) {
                    continue;
                }
                if (
                    ! $user->hasAnyRole(['developer', 'general_director'])
                    && ! $user->hasAccessToCity($gid)
                ) {
                    continue;
                }
                try {
                    $extraCalc = $this->salaryService->ensureCalculation($gid, $accrualPeriod, false);
                    $this->salaryService->assertCanView($user, $extraCalc);
                } catch (\Throwable) {
                    continue;
                }
                $extraCalc->loadMissing(['city', 'recipient']);
                $bal = $this->salaryService->balances($extraCalc);
                $clusterByCity[$gid] = [
                    'calculation' => $extraCalc,
                    'paid' => $bal['paid'],
                    'available' => $bal['available'],
                    'overpayment' => $bal['overpayment'],
                    'balance' => $bal['balance'],
                ];
            }
            // Выбранный город первым, остальные по имени
            $detailCluster = collect($clusterByCity)
                ->sortBy(function (array $row) use ($detail) {
                    $c = $row['calculation'];
                    $selected = (int) $c->salary_calculation_id === (int) $detail->salary_calculation_id ? 0 : 1;
                    $name = mb_strtolower((string) ($c->city->city_name ?? ''));

                    return sprintf('%d-%s', $selected, $name);
                })
                ->values();

            $paymentCat = DirectorSalaryService::PAYMENT_CATEGORY;
            $detailCluster = $detailCluster->map(function (array $clusterRow) use ($detail, $detailPayments, $paymentCat) {
                /** @var SalaryCalculation $calcRow */
                $calcRow = $clusterRow['calculation'];
                if ((int) $calcRow->salary_calculation_id === (int) $detail->salary_calculation_id) {
                    $clusterRow['payments'] = $detailPayments;

                    return $clusterRow;
                }
                $clusterRow['payments'] = $calcRow->payments()
                    ->with(['category', 'createdBy', 'closedBy'])
                    ->whereHas('category', fn ($q) => $q->where('cfm_cat_name', $paymentCat))
                    ->orderByDesc('cfm_closed_at')
                    ->orderByDesc('cfm_created_at')
                    ->get();

                return $clusterRow;
            })->values();
        }

        $monthOptions = $this->monthOptions();
        $incasMonthPrep = $this->ruMonthPrep($accrualPeriod);
        $accrualPeriodLabel = $this->ruMonth($accrualPeriod).' '.$accrualPeriod->format('Y');

        return view('cfm.salary.index', compact(
            'rows',
            'period',
            'accrualPeriod',
            'filterCityId',
            'cities',
            'isBranchOnly',
            'canManagePolicies',
            'isPreliminary',
            'isPayoutMonthView',
            'incasMonthPrep',
            'accrualPeriodLabel',
            'totals',
            'detail',
            'detailBalances',
            'detailPayments',
            'detailCluster',
            'monthOptions',
        ));
    }

    public function payoutCap(Request $request)
    {
        $user = auth()->user();
        $validated = $request->validate([
            'city_id' => 'required|integer|exists:cities,city_id',
        ]);
        $cityId = (int) $validated['city_id'];
        if (! $user->hasRole('developer') && ! $user->hasAccessToCity($cityId)) {
            abort(403);
        }

        try {
            return response()->json($this->salaryService->payoutCapSnapshot($cityId, $user));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            abort($e->getStatusCode(), $e->getMessage());
        }
    }

    public function policies(Request $request)
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['developer', 'general_director'])) {
            abort(403);
        }

        $effective = $this->salaryService->parsePeriodMonth(
            $request->get('effective_month', now()->timezone(config('app.timezone'))->format('Y-m'))
        );
        if ($effective->lt(now()->timezone(config('app.timezone'))->startOfMonth())) {
            $effective = now()->timezone(config('app.timezone'))->startOfMonth();
        }

        $cities = City::query()->where('is_active', true)->orderBy('city_name')->get();
        $rows = $cities->map(function (City $city) use ($effective) {
            $p = $this->salaryService->policyForCityMonth((int) $city->city_id, $effective);

            return [
                'city' => $city,
                'enabled' => $p['enabled'],
                'amount' => $p['enabled'] ? $p['amount'] : (int) ($p['policy']->salary_amount ?? 0),
                'will_accrue' => $p['amount'],
            ];
        });

        $monthOptions = $this->futureMonthOptions();

        return view('cfm.salary.policies', compact('rows', 'effective', 'monthOptions'));
    }

    public function savePolicies(Request $request)
    {
        $user = auth()->user();
        if (! $user->hasAnyRole(['developer', 'general_director'])) {
            abort(403);
        }

        $validated = $request->validate([
            'effective_month' => 'required|date_format:Y-m',
            'cities' => 'required|array',
            'cities.*.city_id' => 'required|exists:cities,city_id',
            'cities.*.enabled' => 'nullable',
            'cities.*.amount' => 'nullable|integer|min:0',
        ], [
            'cities.*.amount.integer' => 'Укажите сумму в целых рублях',
        ]);

        $effective = Carbon::createFromFormat('Y-m', $validated['effective_month'], config('app.timezone'))->startOfMonth();

        $rows = [];
        foreach ($validated['cities'] as $row) {
            $rows[] = [
                'city_id' => (int) $row['city_id'],
                'enabled' => ! empty($row['enabled']),
                'amount' => (int) ($row['amount'] ?? 0),
            ];
        }

        try {
            $this->salaryService->savePolicies($user, $effective, $rows);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('cfm.salary.policies', ['effective_month' => $effective->format('Y-m')])
            ->with('success', 'Настройки окладов сохранены');
    }

    public function recalc(Request $request, int $salary_calculation_id)
    {
        $user = auth()->user();
        $calc = SalaryCalculation::findOrFail($salary_calculation_id);
        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:1000',
        ]);

        try {
            $this->salaryService->confirmRecalc($calc, $user, $validated['reason']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('cfm.salary.index', [
                'month' => Carbon::parse($calc->period_month)->format('Y-m'),
                'calc' => $calc->salary_calculation_id,
            ])
            ->with('success', 'Пересчёт выполнен');
    }

    /** @return list<array{value:string,label:string}> */
    private function monthOptions(): array
    {
        $options = [];
        $cursor = now()->timezone(config('app.timezone'))->startOfMonth();
        for ($i = 0; $i < 18; $i++) {
            $m = $cursor->copy()->subMonths($i);
            $options[] = [
                'value' => $m->format('Y-m'),
                'label' => $this->ruMonth($m).' '.$m->format('Y'),
            ];
        }

        return $options;
    }

    /** @return list<array{value:string,label:string}> */
    private function futureMonthOptions(): array
    {
        $options = [];
        $cursor = now()->timezone(config('app.timezone'))->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $m = $cursor->copy()->addMonths($i);
            $options[] = [
                'value' => $m->format('Y-m'),
                'label' => $this->ruMonth($m).' '.$m->format('Y'),
            ];
        }

        return $options;
    }

    private function ruMonth(Carbon $m): string
    {
        $names = [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
            5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
            9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
        ];

        return $names[(int) $m->format('n')] ?? $m->format('F');
    }

    private function ruMonthPrep(Carbon $m): string
    {
        $names = [
            1 => 'январе', 2 => 'феврале', 3 => 'марте', 4 => 'апреле',
            5 => 'мае', 6 => 'июне', 7 => 'июле', 8 => 'августе',
            9 => 'сентябре', 10 => 'октябре', 11 => 'ноябре', 12 => 'декабре',
        ];

        return $names[(int) $m->format('n')] ?? $m->format('F');
    }
}
