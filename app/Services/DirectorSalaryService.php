<?php

namespace App\Services;

use App\Models\CfmCategory;
use App\Models\CfmOperation;
use App\Models\City;
use App\Models\SalaryCalculation;
use App\Models\SalaryPolicy;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Расчёт ЗП директоров филиала (Касса → Расчёт ЗП).
 * Деньги — только целые рубли; процент округляется half-up один раз от месячной базы.
 */
class DirectorSalaryService
{
    public const PAYMENT_CATEGORY = 'Зарплата Директора Филиала';

    /** Статьи сдачи инкассации: «Инкассация» (формы директоров) + «Инкас» (колонка сводки). */
    public const INCAS_CATEGORIES = ['Инкассация', 'Инкас'];

    /** v2: отдельные ставки для городов-спутников (10%). */
    public const RULES_VERSION = 2;

    /** Ставка спутника: отдельная процентовка (пример: 50 000 → 5 000). */
    public const SATELLITE_RATE_PERCENT = 10;

    public function paymentCategory(): CfmCategory
    {
        $cat = CfmCategory::where('cfm_cat_name', self::PAYMENT_CATEGORY)->first();
        if (! $cat) {
            throw new \RuntimeException('Не найдена статья кассы «'.self::PAYMENT_CATEGORY.'»');
        }

        return $cat;
    }

    public function isPaymentCategory(?CfmCategory $category): bool
    {
        return $category && $category->cfm_cat_name === self::PAYMENT_CATEGORY;
    }

    /** Месяц по умолчанию на экране «Расчёт ЗП»: текущий календарный (выплата). */
    public function defaultPeriodMonth(?Carbon $now = null): Carbon
    {
        $now = ($now ?? now())->copy()->timezone(config('app.timezone'));

        return $now->copy()->startOfMonth();
    }

    /**
     * За выбранный месяц выплаты цифры инкассации и ЗП — за предыдущий.
     * Сентябрь на экране → сдан инкасс августа и ЗП за август.
     */
    public function accrualPeriodForDisplay(Carbon $selectedMonth, ?Carbon $now = null): Carbon
    {
        $selected = $selectedMonth->copy()->startOfMonth();
        if ($this->isCurrentMonth($selected, $now)) {
            return $selected->copy()->subMonth()->startOfMonth();
        }

        return $selected;
    }

    public function isPayoutMonthView(Carbon $selectedMonth, ?Carbon $now = null): bool
    {
        return $this->isCurrentMonth($selectedMonth, $now);
    }

    public function parsePeriodMonth(?string $ym): Carbon
    {
        if ($ym === null || $ym === '') {
            return $this->defaultPeriodMonth();
        }
        try {
            return Carbon::createFromFormat('Y-m', substr($ym, 0, 7), config('app.timezone'))->startOfMonth();
        } catch (\Throwable) {
            return $this->defaultPeriodMonth();
        }
    }

    public function isCurrentMonth(Carbon $periodMonth, ?Carbon $now = null): bool
    {
        $now = ($now ?? now())->copy()->timezone(config('app.timezone'));

        return $periodMonth->format('Y-m') === $now->format('Y-m');
    }

    /**
     * Привязать выплату «Зарплата Директора Филиала» к расчёту за предыдущий месяц.
     * Если id расчёта уже задан (кнопка из «Расчёт ЗП») и город совпадает — не трогаем.
     */
    public function attachDirectorPayoutCalculation(CfmOperation $operation, ?User $actor = null): ?SalaryCalculation
    {
        $operation->loadMissing('category');
        if (! $this->isPaymentCategory($operation->category)) {
            return null;
        }

        $at = $operation->cfm_closed_at ?? now();
        $period = $this->periodMonthForPayoutClosedAt($at);
        $calc = $this->ensureCalculation((int) $operation->city_id, $period, false);
        if ($actor) {
            $this->assertCanView($actor, $calc);
        }

        $currentId = (int) ($operation->salary_calculation_id ?? 0);
        if ($currentId > 0) {
            $existing = SalaryCalculation::query()->find($currentId);
            if ($existing && (int) $existing->city_id === (int) $operation->city_id) {
                return $existing;
            }
        }

        $operation->salary_calculation_id = $calc->salary_calculation_id;
        if (! $operation->related_user_id) {
            $operation->related_user_id = $calc->recipient_user_id;
        }
        $operation->save();

        return $calc->fresh();
    }

    /**
     * Снимок лимита выплаты для города (форма кассы / AJAX).
     *
     * @return array{salary_calculation_id:int,available:int,accrued:int,paid:int,period_month:string,status:string,blocked:?string}
     */
    public function payoutCapSnapshot(int $cityId, User $user, ?Carbon $now = null, ?int $exceptCfmId = null): array
    {
        $period = $this->accrualPeriodForDisplay($this->defaultPeriodMonth($now), $now);
        $calc = $this->ensureCalculation($cityId, $period, false);
        $this->assertCanView($user, $calc);
        $bal = $this->balances($calc);
        $available = max(0, (int) $bal['available'] - $this->reservedDraftAmount($calc, $exceptCfmId));

        $blocked = match (true) {
            $calc->status === SalaryCalculation::STATUS_NEEDS_RECALC => 'Требуется пересчёт расчёта ЗП',
            $calc->status === SalaryCalculation::STATUS_ERROR => $calc->error_message ?: 'Ошибка расчёта ЗП',
            $calc->status === SalaryCalculation::STATUS_PRELIMINARY || $this->isCurrentMonth(Carbon::parse($calc->period_month), $now) => 'По текущему месяцу выплата ещё недоступна',
            $bal['available'] <= 0 && $bal['overpayment'] > 0 => 'Переплата '.$this->formatMoney($bal['overpayment']),
            $available <= 0 => 'Доступно 0 ₽',
            default => null,
        };

        return [
            'salary_calculation_id' => (int) $calc->salary_calculation_id,
            'available' => $available,
            'accrued' => (int) $calc->accrued_amount,
            'paid' => (int) $bal['paid'],
            'period_month' => $period->format('Y-m'),
            'status' => (string) $calc->status,
            'blocked' => $blocked,
        ];
    }

    /**
     * Ставка на всю сумму S.
     * Материнский город: 15 / 20 / 25% (ТЗ).
     * Спутник: фиксированные 10% (отдельная процентовка).
     */
    public function ratePercentForBase(int $s, bool $isSatellite = false): int
    {
        if ($isSatellite) {
            return self::SATELLITE_RATE_PERCENT;
        }

        if ($s <= 100_000) {
            return 15;
        }
        if ($s <= 250_000) {
            return 20;
        }

        return 25;
    }

    /**
     * commission = (S × p + 50) div 100 — half-up для неотрицательного S.
     */
    public function commissionFromBase(int $s, ?int $ratePercent = null, bool $isSatellite = false): int
    {
        if ($s < 0) {
            throw new \InvalidArgumentException('Отрицательная база инкассации');
        }
        $p = $ratePercent ?? $this->ratePercentForBase($s, $isSatellite);

        return intdiv(($s * $p) + 50, 100);
    }

    public function formatMoney(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' ₽';
    }

    /**
     * База инкассации города за месяц [from, to).
     *
     * @return array{base: int, ok: bool, error: ?string}
     */
    public function sumIncasBase(int $cityId, Carbon $periodMonth): array
    {
        $from = $periodMonth->copy()->startOfMonth();
        $to = $periodMonth->copy()->startOfMonth()->addMonth();

        try {
            $sum = (int) CfmOperation::query()
                ->where('city_id', $cityId)
                ->whereNotNull('cfm_closed_at')
                ->where('cfm_closed_at', '>=', $from->toDateTimeString())
                ->where('cfm_closed_at', '<', $to->toDateTimeString())
                ->whereHas('category', function ($q) {
                    $q->whereIn('cfm_cat_name', self::INCAS_CATEGORIES);
                })
                ->sum('amount_cfm');

            if ($sum < 0) {
                return [
                    'base' => $sum,
                    'ok' => false,
                    'error' => 'Итоговая инкассация отрицательна — расчёт невозможен',
                ];
            }

            return ['base' => $sum, 'ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            return [
                'base' => 0,
                'ok' => false,
                'error' => 'Не удалось получить данные инкассации',
            ];
        }
    }

    /**
     * Действующий оклад города на расчётный месяц.
     *
     * @return array{enabled: bool, amount: int, version: ?int, policy: ?SalaryPolicy}
     */
    public function policyForCityMonth(int $cityId, Carbon $periodMonth): array
    {
        $policy = SalaryPolicy::query()
            ->where('city_id', $cityId)
            ->where('effective_month', '<=', $periodMonth->toDateString())
            ->orderByDesc('effective_month')
            ->orderByDesc('salary_policy_id')
            ->first();

        if (! $policy) {
            return ['enabled' => false, 'amount' => 0, 'version' => null, 'policy' => null];
        }

        return [
            'enabled' => (bool) $policy->salary_enabled,
            'amount' => $policy->salary_enabled ? (int) $policy->salary_amount : 0,
            'version' => (int) $policy->version,
            'policy' => $policy,
        ];
    }

    public function resolveRecipientForCity(int $cityId): ?User
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('role_code', 'branch_head'))
            ->whereHas('cities', fn ($q) => $q->where('cities.city_id', $cityId))
            ->orderBy('user_id')
            ->first();
    }

    public function paidAmount(SalaryCalculation $calc): int
    {
        $catId = $this->paymentCategory()->cfm_cat_id;

        return (int) CfmOperation::query()
            ->where('salary_calculation_id', $calc->salary_calculation_id)
            ->where('cfm_cat_id', $catId)
            ->whereNotNull('cfm_closed_at')
            ->sum('amount_cfm');
    }

    public function reservedDraftAmount(SalaryCalculation $calc, ?int $exceptCfmId = null): int
    {
        $catId = $this->paymentCategory()->cfm_cat_id;

        $q = CfmOperation::query()
            ->where('salary_calculation_id', $calc->salary_calculation_id)
            ->where('cfm_cat_id', $catId)
            ->whereNull('cfm_closed_at');
        if ($exceptCfmId) {
            $q->where('cfm_id', '!=', $exceptCfmId);
        }

        return (int) $q->sum('amount_cfm');
    }

    /**
     * @return array{paid: int, balance: int, available: int, overpayment: int}
     */
    public function balances(SalaryCalculation $calc): array
    {
        $paid = $this->paidAmount($calc);
        $balance = (int) $calc->accrued_amount - $paid;

        return [
            'paid' => $paid,
            'balance' => $balance,
            'available' => max(0, $balance),
            'overpayment' => max(0, -$balance),
        ];
    }

    /**
     * Создать/обновить расчёт за месяц. Фиксированные не перезаписываются без force/recalc.
     */
    public function ensureCalculation(int $cityId, Carbon $periodMonth, bool $forceRecalc = false): SalaryCalculation
    {
        $periodMonth = $periodMonth->copy()->startOfMonth();
        $isCurrent = $this->isCurrentMonth($periodMonth);

        return DB::transaction(function () use ($cityId, $periodMonth, $forceRecalc, $isCurrent) {
            $calc = SalaryCalculation::query()
                ->where('city_id', $cityId)
                ->whereDate('period_month', $periodMonth->toDateString())
                ->lockForUpdate()
                ->first();

            if ($calc && $calc->status === SalaryCalculation::STATUS_FIXED && ! $forceRecalc) {
                return $calc;
            }
            if ($calc && $calc->status === SalaryCalculation::STATUS_NEEDS_RECALC && ! $forceRecalc) {
                return $calc;
            }

            $baseInfo = $this->sumIncasBase($cityId, $periodMonth);
            $policy = $this->policyForCityMonth($cityId, $periodMonth);
            $recipient = $calc?->recipient_user_id
                ? User::find($calc->recipient_user_id)
                : $this->resolveRecipientForCity($cityId);

            if (! $baseInfo['ok']) {
                $attrs = [
                    'city_id' => $cityId,
                    'recipient_user_id' => $recipient?->user_id,
                    'period_month' => $periodMonth->toDateString(),
                    'incas_base' => $baseInfo['base'],
                    'rate_percent' => 0,
                    'commission_amount' => 0,
                    'salary_amount' => 0,
                    'accrued_amount' => 0,
                    'policy_version' => $policy['version'],
                    'status' => SalaryCalculation::STATUS_ERROR,
                    'error_message' => $baseInfo['error'],
                    'calc_code' => $calc?->calc_code,
                ];
                if ($calc) {
                    $calc->fill($attrs)->save();

                    return $calc->fresh();
                }

                return SalaryCalculation::create($attrs);
            }

            $s = $baseInfo['base'];
            $city = City::query()->find($cityId);
            $isSatellite = (bool) ($city?->isSatellite());
            $rate = $this->ratePercentForBase($s, $isSatellite);
            $commission = $this->commissionFromBase($s, $rate, $isSatellite);
            $salary = $policy['amount'];
            $accrued = $commission + $salary;

            $status = $isCurrent
                ? SalaryCalculation::STATUS_PRELIMINARY
                : SalaryCalculation::STATUS_FIXED;

            $attrs = [
                'city_id' => $cityId,
                'recipient_user_id' => $recipient?->user_id,
                'period_month' => $periodMonth->toDateString(),
                'incas_base' => $s,
                'rate_percent' => $rate,
                'commission_amount' => $commission,
                'salary_amount' => $salary,
                'accrued_amount' => $accrued,
                'policy_version' => $policy['version'],
                'status' => $status,
                'error_message' => null,
                'fixed_at' => $status === SalaryCalculation::STATUS_FIXED ? ($calc?->fixed_at ?? now()) : null,
                'recalc_requested_at' => null,
            ];

            if ($calc) {
                if ($forceRecalc) {
                    $attrs['calculation_version'] = (int) $calc->calculation_version + 1;
                }
                $calc->fill($attrs)->save();
                $calc = $calc->fresh();
            } else {
                $attrs['calculation_version'] = 1;
                $attrs['calc_code'] = $this->makeCalcCode($periodMonth, $cityId);
                $calc = SalaryCalculation::create($attrs);
            }

            $this->linkHistoricalPayments($calc);

            return $calc->fresh();
        });
    }

    public function makeCalcCode(Carbon $periodMonth, int $cityId): string
    {
        return sprintf('ЗП-%02d-%03d', (int) $periodMonth->format('m'), $cityId % 1000);
    }

    /**
     * Проводка ЗП в кассе в месяце M относится к расчёту за M−1.
     * Инкасс августа → начисление за август → выплата в сентябре.
     *
     * @return array{0: Carbon, 1: Carbon} [from, to)
     */
    public function payoutWindowForPeriod(Carbon $periodMonth): array
    {
        $from = $periodMonth->copy()->startOfMonth()->addMonth()->startOfMonth();
        $to = $from->copy()->addMonth();

        return [$from, $to];
    }

    /** Расчётный месяц для проведённой выплаты: предыдущий календарный месяц даты проведения. */
    public function periodMonthForPayoutClosedAt(Carbon $closedAt): Carbon
    {
        $tz = (string) (config('app.timezone') ?: 'Europe/Moscow');

        return $closedAt->copy()->timezone($tz)->startOfMonth()->subMonth()->startOfMonth();
    }

    /**
     * Привязать уже проведённые «Зарплата Директора Филиала» без расчёта.
     * Ищем проводки в следующем календарном месяце после периода расчёта.
     */
    public function linkHistoricalPayments(SalaryCalculation $calc): void
    {
        [$from, $to] = $this->payoutWindowForPeriod(Carbon::parse($calc->period_month));
        $catId = $this->paymentCategory()->cfm_cat_id;

        CfmOperation::query()
            ->where('city_id', $calc->city_id)
            ->where('cfm_cat_id', $catId)
            ->whereNotNull('cfm_closed_at')
            ->where('cfm_closed_at', '>=', $from->toDateTimeString())
            ->where('cfm_closed_at', '<', $to->toDateTimeString())
            ->whereNull('salary_calculation_id')
            ->update(['salary_calculation_id' => $calc->salary_calculation_id]);
    }

    /**
     * Старые проводки ошибочно висели на том же месяце, что и дата в кассе.
     * 31.08 выплата → расчёт июля, не августа.
     *
     * @return list<array{cfm_id: int, from: int, to: int, city_id: int}>
     */
    public function relinkSameMonthPayoutsToPreviousPeriod(): array
    {
        $catId = $this->paymentCategory()->cfm_cat_id;
        $moved = [];

        $ops = CfmOperation::query()
            ->with('salaryCalculation')
            ->where('cfm_cat_id', $catId)
            ->whereNotNull('cfm_closed_at')
            ->whereNotNull('salary_calculation_id')
            ->orderBy('cfm_id')
            ->get();

        foreach ($ops as $op) {
            $calc = $op->salaryCalculation;
            if (! $calc || ! $op->cfm_closed_at) {
                continue;
            }

            $closedMonth = $op->cfm_closed_at->copy()
                ->timezone((string) (config('app.timezone') ?: 'Europe/Moscow'))
                ->format('Y-m');
            $periodMonth = Carbon::parse($calc->period_month)->format('Y-m');
            if ($closedMonth !== $periodMonth) {
                continue;
            }

            $targetPeriod = $this->periodMonthForPayoutClosedAt($op->cfm_closed_at);
            $target = $this->ensureCalculation((int) $op->city_id, $targetPeriod, false);
            if ((int) $target->salary_calculation_id === (int) $op->salary_calculation_id) {
                continue;
            }

            $fromId = (int) $op->salary_calculation_id;
            $op->salary_calculation_id = $target->salary_calculation_id;
            $op->save();

            $moved[] = [
                'cfm_id' => (int) $op->cfm_id,
                'from' => $fromId,
                'to' => (int) $target->salary_calculation_id,
                'city_id' => (int) $op->city_id,
            ];

            DB::table('salary_audit_logs')->insert([
                'action' => 'relink_payout_previous_month',
                'city_id' => $op->city_id,
                'salary_calculation_id' => $target->salary_calculation_id,
                'actor_user_id' => null,
                'reason' => 'Проводка #'.$op->cfm_id.' '. $closedMonth.' относится к расчёту предыдущего месяца',
                'before_json' => json_encode(['salary_calculation_id' => $fromId], JSON_UNESCAPED_UNICODE),
                'after_json' => json_encode(['salary_calculation_id' => (int) $target->salary_calculation_id], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        }

        return $moved;
    }

    /**
     * @param  array<int>|null  $cityIds  null = все активные города
     * @return Collection<int, SalaryCalculation>
     */
    public function ensureMonth(?array $cityIds, Carbon $periodMonth, bool $forceRecalc = false): Collection
    {
        $query = City::query()->where('is_active', true)->orderBy('city_name');
        if ($cityIds !== null) {
            $query->whereIn('city_id', $cityIds);
        }

        $out = collect();
        foreach ($query->pluck('city_id') as $cityId) {
            $out->push($this->ensureCalculation((int) $cityId, $periodMonth, $forceRecalc));
        }

        return $out;
    }

    /**
     * Список расчётов для пользователя с учётом прав.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listForUser(User $user, Carbon $periodMonth, ?int $filterCityId = null): Collection
    {
        $scope = $user->cityIdsForScope();
        $cityIds = $scope;
        if ($filterCityId) {
            if ($scope !== null && ! in_array($filterCityId, $scope, true) && ! $user->hasAccessToCity($filterCityId)) {
                abort(403);
            }
            // Фильтр по городу → весь кластер (мать + спутники)
            $groupIds = City::operationGroupIds($filterCityId);
            if ($scope === null) {
                $cityIds = $groupIds;
            } else {
                $cityIds = array_values(array_intersect($groupIds, $scope));
                if ($cityIds === []) {
                    $cityIds = [$filterCityId];
                }
            }
        }

        $onlyOwn = $user->hasRole('branch_head')
            && ! $user->hasAnyRole(['developer', 'general_director', 'regional_director']);

        $calcs = $this->ensureMonth($cityIds, $periodMonth);

        if ($onlyOwn) {
            $calcs = $calcs->filter(fn (SalaryCalculation $c) => (int) $c->recipient_user_id === (int) $user->user_id);
        }

        return $calcs->map(function (SalaryCalculation $c) {
            $c->loadMissing(['city', 'recipient']);
            $bal = $this->balances($c);

            return [
                'calculation' => $c,
                'paid' => $bal['paid'],
                'available' => $bal['available'],
                'overpayment' => $bal['overpayment'],
                'balance' => $bal['balance'],
            ];
        })->values();
    }

    public function assertCanView(User $user, SalaryCalculation $calc): void
    {
        if ($user->hasAnyRole(['developer', 'general_director'])) {
            return;
        }
        if (! $user->hasAccessToCity((int) $calc->city_id)) {
            abort(403, 'Нет доступа к городу');
        }
        if ($user->hasRole('branch_head')
            && ! $user->hasAnyRole(['regional_director', 'developer', 'general_director'])
            && (int) $calc->recipient_user_id !== (int) $user->user_id) {
            abort(403, 'Доступна только собственная зарплата');
        }
    }

    /**
     * Проверка лимита перед проведением выплаты «Дир ЗП».
     *
     * @throws ValidationException
     */
    public function assertPayoutAllowed(CfmOperation $operation, ?int $amountOverride = null): void
    {
        $operation->loadMissing('category');
        if (! $this->isPaymentCategory($operation->category)) {
            return;
        }

        $amount = $amountOverride ?? (int) $operation->amount_cfm;
        if ($amount < 1 || (string) (int) $amount !== (string) $amount) {
            throw ValidationException::withMessages([
                'amount_cfm' => 'Укажите сумму в целых рублях',
            ]);
        }

        $calcId = $operation->salary_calculation_id;
        if (! $calcId) {
            throw ValidationException::withMessages([
                'salary_calculation_id' => 'Укажите расчётный месяц зарплаты директора',
            ]);
        }

        DB::transaction(function () use ($calcId, $amount, $operation) {
            $calc = SalaryCalculation::query()
                ->whereKey($calcId)
                ->lockForUpdate()
                ->first();

            if (! $calc) {
                throw ValidationException::withMessages([
                    'salary_calculation_id' => 'Расчёт ЗП не найден',
                ]);
            }

            if ($calc->status === SalaryCalculation::STATUS_NEEDS_RECALC) {
                throw ValidationException::withMessages([
                    'salary_calculation_id' => 'Расчёт требует пересчёта — выплата временно запрещена',
                ]);
            }
            if ($calc->status === SalaryCalculation::STATUS_ERROR) {
                throw ValidationException::withMessages([
                    'salary_calculation_id' => 'Ошибка расчёта — выплата запрещена',
                ]);
            }
            if ($calc->status === SalaryCalculation::STATUS_PRELIMINARY || $this->isCurrentMonth(Carbon::parse($calc->period_month))) {
                throw ValidationException::withMessages([
                    'salary_calculation_id' => 'По предварительному (текущему) месяцу выплата недоступна',
                ]);
            }
            if ((int) $operation->city_id !== (int) $calc->city_id) {
                throw ValidationException::withMessages([
                    'city_id' => 'Город операции не совпадает с расчётом ЗП',
                ]);
            }

            $bal = $this->balances($calc);
            if ($operation->cfm_closed_at) {
                $bal['available'] += (int) $operation->amount_cfm;
            } else {
                $bal['available'] = max(0, $bal['available'] - $this->reservedDraftAmount($calc, $operation->cfm_id ? (int) $operation->cfm_id : null));
            }

            if ($amount > $bal['available']) {
                throw ValidationException::withMessages([
                    'amount_cfm' => 'Сумма превышает доступный остаток. Доступно: '.$this->formatMoney($bal['available']),
                ]);
            }
        });
    }

    public function markNeedsRecalcForCityDate(int $cityId, Carbon $closedAt): void
    {
        $month = $closedAt->copy()->timezone(config('app.timezone'))->startOfMonth();
        if ($this->isCurrentMonth($month)) {
            // текущий месяц — просто пересоберём при открытии
            return;
        }

        $calc = SalaryCalculation::query()
            ->where('city_id', $cityId)
            ->whereDate('period_month', $month->toDateString())
            ->where('status', SalaryCalculation::STATUS_FIXED)
            ->first();

        if ($calc) {
            $calc->update([
                'status' => SalaryCalculation::STATUS_NEEDS_RECALC,
                'recalc_requested_at' => now(),
            ]);
        }
    }

    /**
     * Подтверждённый пересчёт (гендир).
     */
    public function confirmRecalc(SalaryCalculation $calc, User $actor, string $reason): SalaryCalculation
    {
        if (! $actor->hasAnyRole(['developer', 'general_director'])) {
            abort(403);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Укажите причину пересчёта']);
        }

        $before = $calc->only([
            'incas_base', 'rate_percent', 'commission_amount', 'salary_amount', 'accrued_amount', 'status',
        ]);

        $fresh = $this->ensureCalculation((int) $calc->city_id, Carbon::parse($calc->period_month), true);

        DB::table('salary_audit_logs')->insert([
            'action' => 'recalc',
            'city_id' => $fresh->city_id,
            'salary_calculation_id' => $fresh->salary_calculation_id,
            'actor_user_id' => $actor->user_id,
            'reason' => $reason,
            'before_json' => json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_json' => json_encode($fresh->only([
                'incas_base', 'rate_percent', 'commission_amount', 'salary_amount', 'accrued_amount', 'status',
            ]), JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);

        return $fresh;
    }

    /**
     * Начальные политики: НН 50 000 с месяца запуска.
     */
    public function seedInitialPolicies(Carbon $startMonth, ?int $actorId = null): void
    {
        $nn = City::query()->where('city_name', 'Нижний Новгород')->first();
        if (! $nn) {
            return;
        }

        $exists = SalaryPolicy::query()
            ->where('city_id', $nn->city_id)
            ->whereDate('effective_month', $startMonth->toDateString())
            ->exists();

        if ($exists) {
            return;
        }

        SalaryPolicy::create([
            'city_id' => $nn->city_id,
            'salary_enabled' => true,
            'salary_amount' => 50_000,
            'effective_month' => $startMonth->toDateString(),
            'version' => 1,
            'created_by' => $actorId,
        ]);
    }

    /**
     * Сохранить пакет настроек окладов с месяца effectiveMonth.
     *
     * @param  array<int, array{city_id:int, enabled:bool, amount:int}>  $rows
     */
    public function savePolicies(User $actor, Carbon $effectiveMonth, array $rows): void
    {
        if (! $actor->hasAnyRole(['developer', 'general_director'])) {
            abort(403);
        }

        $effectiveMonth = $effectiveMonth->copy()->startOfMonth();
        $nowMonth = now()->timezone(config('app.timezone'))->startOfMonth();
        if ($effectiveMonth->lt($nowMonth)) {
            throw ValidationException::withMessages([
                'effective_month' => 'Доступны текущий и будущие расчётные месяцы',
            ]);
        }

        DB::transaction(function () use ($actor, $effectiveMonth, $rows) {
            foreach ($rows as $row) {
                $cityId = (int) $row['city_id'];
                $enabled = (bool) $row['enabled'];
                $amount = (int) ($row['amount'] ?? 0);

                if ($enabled && $amount < 1) {
                    throw ValidationException::withMessages([
                        'salary_amount' => 'При включении оклада укажите положительную сумму в целых рублях',
                    ]);
                }

                $current = $this->policyForCityMonth($cityId, $effectiveMonth);
                $beforeAmount = $current['enabled'] ? $current['amount'] : 0;
                $afterAmount = $enabled ? $amount : 0;
                if ($beforeAmount === $afterAmount && (bool) $current['enabled'] === $enabled) {
                    continue;
                }

                $prevVersion = (int) (SalaryPolicy::query()->where('city_id', $cityId)->max('version') ?? 0);

                $policy = SalaryPolicy::query()->updateOrCreate(
                    [
                        'city_id' => $cityId,
                        'effective_month' => $effectiveMonth->toDateString(),
                    ],
                    [
                        'salary_enabled' => $enabled,
                        'salary_amount' => $enabled ? $amount : max(0, $amount),
                        'version' => $prevVersion + 1,
                        'created_by' => $actor->user_id,
                    ]
                );

                DB::table('salary_audit_logs')->insert([
                    'action' => 'policy_save',
                    'city_id' => $cityId,
                    'salary_calculation_id' => null,
                    'actor_user_id' => $actor->user_id,
                    'reason' => null,
                    'before_json' => json_encode([
                        'enabled' => $current['enabled'],
                        'amount' => $beforeAmount,
                    ], JSON_UNESCAPED_UNICODE),
                    'after_json' => json_encode([
                        'enabled' => $enabled,
                        'amount' => $afterAmount,
                        'effective_month' => $effectiveMonth->toDateString(),
                        'version' => $policy->version,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ]);
            }
        });
    }
}
