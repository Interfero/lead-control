<?php

namespace App\Services;

use App\Models\CfmOperation;
use App\Models\CfmCategory;
use App\Models\City;
use App\Models\User;
use App\Models\PromPayment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CfmService
{
    /** Роли с входом в Кассу (маршрут `cfm.*`). */
    public const CASH_ACCESS_ROLES = [
        'developer',
        'branch_head',
        'regional_director',
        'general_director',
        'senior_manager',
        'call_center',
        'senior_dispatcher',
    ];

    /** Кто может переоткрыть проведённую операцию (только текущий месяц). */
    public const REOPEN_ROLES = [
        'developer',
        'general_director',
    ];

    /**
     * Проведённая операция закрыта в текущем календарном месяце (часовой пояс приложения).
     */
    public function isClosedInCurrentMonth(CfmOperation $operation): bool
    {
        if (! $operation->cfm_closed_at) {
            return false;
        }

        $tz = (string) (config('app.timezone') ?: 'Europe/Moscow');
        $closed = $operation->cfm_closed_at->clone()->timezone($tz);
        $now = now()->timezone($tz);

        return $closed->isSameMonth($now);
    }

    public function userCanReopen(User $user, CfmOperation $operation): bool
    {
        if (! $operation->cfm_closed_at) {
            return false;
        }

        if (! $user->hasAnyRole(self::REOPEN_ROLES)) {
            return false;
        }

        return $this->isClosedInCurrentMonth($operation);
    }

    public function userCanAttachDocuments(User $user): bool
    {
        return $user->hasAnyRole(self::CASH_ACCESS_ROLES);
    }

    public function userCanDeleteDocuments(User $user, CfmOperation $operation): bool
    {
        if ($operation->cfm_closed_at) {
            return false;
        }

        if ($user->hasRole('general_director')) {
            return false;
        }

        return $user->hasAnyRole(['developer', 'branch_head', 'regional_director', 'senior_manager']);
    }

    /**
     * Создание операции
     *
     * @param  array<int, UploadedFile|null>  $documents
     */
    public function create(array $data, int $userId, array $documents = []): CfmOperation
    {
        return DB::transaction(function () use ($data, $userId, $documents) {
            $operation = new CfmOperation([
                'city_id' => $data['city_id'],
                'cfm_cat_id' => $data['cfm_cat_id'],
                'amount_cfm' => $data['amount_cfm'],
                'amount_from_master' => $data['amount_from_master'] ?? null,
                'cfm_adds' => $data['cfm_adds'] ?? null,
                'cfm_subcat' => $data['cfm_subcat'] ?? null,
                'cfm_payer' => $data['cfm_payer'] ?? null,
                'cfm_recipient' => $data['cfm_recipient'] ?? null,
                'external_cfm_ref' => $data['external_cfm_ref'] ?? null,
                'related_order_id' => $data['related_order_id'] ?? null,
                'related_user_id' => $data['related_user_id'] ?? null,
                'salary_calculation_id' => $data['salary_calculation_id'] ?? null,
                // Явно из app timezone — не полагаться на MySQL CURRENT_TIMESTAMP (другой TZ).
                'cfm_created_at' => $data['cfm_created_at'] ?? now(),
            ]);
            $operation->cfm_created_by = $userId;
            $operation->save();

            // Если это перемещение — создаём парную операцию
            if ($data['is_transfer'] ?? false) {
                $this->createTransferPair($operation, $data['target_city_id'], $userId);
            }

            // Если это Инкас — создаём парную операцию поступления в УК
            $category = CfmCategory::find($data['cfm_cat_id']);
            if ($category && $category->cfm_cat_name === 'Инкас') {
                $this->createIncasPair($operation, $userId);
            }

            $docService = app(CfmOperationDocumentService::class);
            foreach ($documents as $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    continue;
                }
                $docService->attachToOperation($operation, $file, $userId);
            }

            $this->bindDirectorSalaryPayout($operation->fresh(), $userId);

            return $operation->fresh();
        });
    }
    
    /**
     * Обновление операции
     */
    public function update(CfmOperation $operation, array $data): CfmOperation
    {
        // Проведённые операции редактировать нельзя
        if ($operation->cfm_closed_at) {
            throw new \Exception('Невозможно редактировать проведённую операцию');
        }

        $operation->loadMissing('category', 'relatedUser');

        if (array_key_exists('amount_cfm', $data)) {
            $operation->amount_cfm = (int) $data['amount_cfm'];
        }
        if (array_key_exists('amount_from_master', $data)) {
            $operation->amount_from_master = $data['amount_from_master'] === null
                ? null
                : (int) $data['amount_from_master'];
        }
        if (array_key_exists('cfm_adds', $data)) {
            $operation->cfm_adds = $data['cfm_adds'];
        }
        if (array_key_exists('cfm_subcat', $data)) {
            $operation->cfm_subcat = $data['cfm_subcat'];
        }
        if (array_key_exists('city_id', $data)) {
            $operation->city_id = $data['city_id'];
        }

        if ($this->isClientRefundOperation($operation) && array_key_exists('amount_from_master', $data)) {
            $operation->cfm_adds = $this->rebuildClientRefundAdds(
                $operation,
                (int) $operation->amount_cfm,
                (int) $operation->amount_from_master,
                (string) ($data['cfm_adds'] ?? $operation->cfm_adds ?? '')
            );
        }

        $operation->save();

        $this->bindDirectorSalaryPayout($operation->fresh(), $operation->cfm_created_by);

        return $operation->fresh();
    }

    public function isClientRefundOperation(CfmOperation $operation): bool
    {
        $operation->loadMissing('category');
        if ($operation->amount_from_master !== null) {
            return true;
        }

        return $operation->category?->cfm_cat_name === $this->clientRefundCategoryName();
    }

    private function bindDirectorSalaryPayout(CfmOperation $operation, ?int $userId): void
    {
        $salary = app(DirectorSalaryService::class);
        $operation->loadMissing('category');
        if (! $salary->isPaymentCategory($operation->category)) {
            return;
        }

        $salary->attachDirectorPayoutCalculation($operation, $userId ? User::find($userId) : null);
        $operation->refresh();
        $salary->assertPayoutAllowed($operation);
    }

    /**
     * Пересобрать стандартные строки возврата, сохранив свободный комментарий.
     */
    public function rebuildClientRefundAdds(CfmOperation $operation, int $fromCash, int $fromMaster, string $existingAdds): string
    {
        $total = $fromCash + $fromMaster;
        $skipPrefixes = [
            'Возврат клиенту',
            'Заказ №',
            'Итого возврат:',
            'С кассы:',
            'С мастера:',
        ];
        $masterName = $operation->relatedUser?->user_name;
        if (is_string($masterName) && $masterName !== '') {
            $skipPrefixes[] = 'Мастер:';
        }
        $keep = [];
        foreach (preg_split("/\r\n|\n|\r/", $existingAdds) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $skip = false;
            foreach ($skipPrefixes as $prefix) {
                if ($trimmed === $prefix || str_starts_with($trimmed, $prefix)) {
                    $skip = true;
                    break;
                }
            }
            if (! $skip) {
                $keep[] = $line;
            }
        }

        $lines = [
            'Возврат клиенту',
            'Заказ №'.($operation->related_order_id ?? '—'),
            'Итого возврат: '.number_format($total, 0, ',', ' ').' ₽',
            'С кассы: '.number_format($fromCash, 0, ',', ' ').' ₽',
            'С мастера: '.number_format($fromMaster, 0, ',', ' ').' ₽',
        ];
        $masterName = $operation->relatedUser?->user_name;
        if (is_string($masterName) && $masterName !== '') {
            $lines[] = 'Мастер: '.$masterName;
        }
        foreach ($keep as $extra) {
            $lines[] = $extra;
        }

        return implode("\n", $lines);
    }

    
    /**
     * Проведение операции
     */
    public function close(CfmOperation $operation, int $userId): CfmOperation
    {
        if ($operation->cfm_closed_at) {
            throw new \Exception('Операция уже проведена');
        }
        
        return DB::transaction(function () use ($operation, $userId) {
            $operation->loadMissing('category');
            if (app(DirectorSalaryService::class)->isPaymentCategory($operation->category)) {
                app(DirectorSalaryService::class)->attachDirectorPayoutCalculation(
                    $operation,
                    User::find($userId)
                );
                $operation->refresh();
                app(DirectorSalaryService::class)->assertPayoutAllowed($operation);
            }

            if ($operation->category?->cfm_cat_group === 'outflows') {
                City::query()
                    ->where('city_id', $operation->city_id)
                    ->lockForUpdate()
                    ->first();
                $this->assertCashOverdraftOnClose($operation);
            }

            $operation->cfm_closed_by = $userId;
            $operation->cfm_closed_at = now();
            $operation->save();
            
            // Если это Инкас — проводим парную операцию в УК
            $operation->load('category');
            if ($operation->category->cfm_cat_name === 'Инкас' && $operation->related_city_id) {
                $this->closeIncasPair($operation, $userId);
            }

            // Инкассация/Инкас по завершённому месяцу → пометка пересчёта ЗП
            if (in_array($operation->category->cfm_cat_name, DirectorSalaryService::INCAS_CATEGORIES, true)) {
                try {
                    app(DirectorSalaryService::class)->markNeedsRecalcForCityDate(
                        (int) $operation->city_id,
                        $operation->cfm_closed_at ?? now()
                    );
                } catch (\Throwable) {
                }
            }
            
            // Если операция связана с оплатой промоутера — пометить оплату как оплаченную
            $payment = PromPayment::where('cfm_operation_id', $operation->cfm_id)->first();
            if ($payment) {
                $payment->update([
                    'payment_status' => PromPayment::STATUS_PAID,
                    'paid_at' => now(),
                ]);
            }

            try {
                if ($operation->city_id) {
                    app(\App\Services\ReportCityDailyService::class)->markStaleForCityDate(
                        (int) $operation->city_id,
                        $operation->cfm_closed_at ?? now()
                    );
                }
            } catch (\Throwable) {
            }
            
            return $operation->fresh();
        });
    }
    
    /**
     * Проведение парной операции инкаса в УК
     */
    private function closeIncasPair(CfmOperation $incasOperation, int $userId): void
    {
        // Находим парную операцию в УК
        $inflowCategory = CfmCategory::where('cfm_cat_name', 'Перемещение (поступление)')->first();
        
        if (!$inflowCategory) {
            return;
        }
        
        $pairOperation = CfmOperation::where('city_id', $incasOperation->related_city_id)
            ->where('cfm_cat_id', $inflowCategory->cfm_cat_id)
            ->where('related_city_id', $incasOperation->city_id)
            ->where('amount_cfm', $incasOperation->amount_cfm)
            ->whereNull('cfm_closed_at')
            ->first();
        
        if ($pairOperation) {
            $pairOperation->cfm_closed_by = $userId;
            $pairOperation->cfm_closed_at = now();
            $pairOperation->save();
        }
    }
    
    /**
     * Переоткрытие парной операции инкаса в УК
     */
    private function reopenIncasPair(CfmOperation $incasOperation): void
    {
        $inflowCategory = CfmCategory::where('cfm_cat_name', 'Перемещение (поступление)')->first();
        
        if (!$inflowCategory) {
            return;
        }
        
        $pairOperation = CfmOperation::where('city_id', $incasOperation->related_city_id)
            ->where('cfm_cat_id', $inflowCategory->cfm_cat_id)
            ->where('related_city_id', $incasOperation->city_id)
            ->where('amount_cfm', $incasOperation->amount_cfm)
            ->whereNotNull('cfm_closed_at')
            ->first();
        
        if ($pairOperation) {
            $pairOperation->cfm_closed_by = null;
            $pairOperation->cfm_closed_at = null;
            $pairOperation->save();
        }
    }
    
    /**
     * Переоткрытие операции (гендиректор и разработчик, только текущий месяц)
     */
    public function reopen(CfmOperation $operation): CfmOperation
    {
        if (!$operation->cfm_closed_at) {
            throw new \Exception('Операция не проведена');
        }

        if (! $this->isClosedInCurrentMonth($operation)) {
            throw new \Exception('Переоткрыть можно только операции текущего месяца');
        }
        
        return DB::transaction(function () use ($operation) {
            $closedAt = $operation->cfm_closed_at;
            $cityId = (int) $operation->city_id;

            // Если это Инкас — переоткрываем парную операцию в УК
            $operation->load('category');
            if ($operation->category->cfm_cat_name === 'Инкас' && $operation->related_city_id) {
                $this->reopenIncasPair($operation);
            }
            
            $operation->cfm_closed_by = null;
            $operation->cfm_closed_at = null;
            $operation->save();
            
            // Если операция связана с оплатой промоутера — вернуть статус "Сформировано"
            $payment = PromPayment::where('cfm_operation_id', $operation->cfm_id)->first();
            if ($payment) {
                $payment->update([
                    'payment_status' => PromPayment::STATUS_CREATED,
                    'paid_at' => null,
                ]);
            }

            try {
                if ($cityId > 0 && $closedAt) {
                    app(\App\Services\ReportCityDailyService::class)->markStaleForCityDate($cityId, $closedAt);
                }
            } catch (\Throwable) {
            }

            return $operation->fresh();
        });
    }
    
    /**
     * Создание парной операции для перемещения
     */
    private function createTransferPair(CfmOperation $outflow, int $targetCityId, int $userId): void
    {
        $inflowCategory = CfmCategory::where('cfm_cat_name', 'Перемещение (поступление)')->first();
        
        $sourceCityName = City::find($outflow->city_id)->city_name;
        
        $pairOperation = new CfmOperation([
            'city_id' => $targetCityId,
            'cfm_cat_id' => $inflowCategory->cfm_cat_id,
            'amount_cfm' => $outflow->amount_cfm,
            'cfm_adds' => "Перемещение из {$sourceCityName}",
            // Парная операция также не проводится автоматически
            'related_city_id' => $outflow->city_id,
            'cfm_created_at' => $outflow->cfm_created_at ?? now(),
        ]);
        $pairOperation->cfm_created_by = $userId;
        $pairOperation->save();
        
        // Обновляем исходную операцию
        $targetCityName = City::find($targetCityId)->city_name;
        $outflow->update([
            'cfm_adds' => "Перемещение в {$targetCityName}",
            'related_city_id' => $targetCityId,
        ]);
    }
    
    /**
     * Создание парной операции поступления в УК при инкасе
     */
    private function createIncasPair(CfmOperation $incasOperation, int $userId): void
    {
        // Находим город "Управляющая компания"
        $mcCity = City::where('city_type', 'mc')->first();
        
        if (!$mcCity) {
            return; // УК не найдена, пропускаем
        }
        
        $inflowCategory = CfmCategory::where('cfm_cat_name', 'Перемещение (поступление)')->first();
        
        if (!$inflowCategory) {
            return;
        }
        
        $sourceCityName = City::find($incasOperation->city_id)->city_name;
        
        // Создаём поступление в УК
        $pairOperation = new CfmOperation([
            'city_id' => $mcCity->city_id,
            'cfm_cat_id' => $inflowCategory->cfm_cat_id,
            'amount_cfm' => $incasOperation->amount_cfm,
            'cfm_adds' => "Инкас из {$sourceCityName}",
            'related_city_id' => $incasOperation->city_id,
            'cfm_created_at' => $incasOperation->cfm_created_at ?? now(),
        ]);
        $pairOperation->cfm_created_by = $userId;
        $pairOperation->save();
        
        // Обновляем исходную операцию
        $incasOperation->update([
            'related_city_id' => $mcCity->city_id,
        ]);
    }
    
    /**
     * Сводный отчёт по всем городам
     * @param array $cityIds ID городов для фильтрации операций
     * @param string|null $dateFrom Дата начала периода
     * @param string|null $dateTo Дата конца периода
     * @param Collection|null $cities Коллекция городов для отображения (включая пустые)
     */
    public function getSummaryReport(array $cityIds = [], ?string $dateFrom = null, ?string $dateTo = null, ?Collection $cities = null): array
    {
        $query = CfmOperation::query()
            ->with(['category', 'city'])
            ->whereNotNull('cfm_closed_at');
        
        if (!empty($cityIds)) {
            $query->whereIn('city_id', $cityIds);
        }
        
        if ($dateFrom) {
            $query->where('cfm_closed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('cfm_closed_at', '<=', $dateTo . ' 23:59:59');
        }
        
        $operations = $query->get();
        
        $report = [];
        $totals = [
            'operating_result' => 0,
            'order_income' => 0,
            'other_inflows' => 0,
            'promo_salary' => 0,
            'incas' => 0,
            'ads_expense' => 0,
            'total_outflows' => 0,
            'balance' => 0,
        ];
        
        // Если передана коллекция городов, инициализируем отчёт для всех
        if ($cities) {
            foreach ($cities as $city) {
                $report[$city->city_id] = [
                    'city_id' => $city->city_id,
                    'city_name' => $city->city_name,
                    'operating_result' => 0,
                    'order_income' => 0,
                    'other_inflows' => 0,
                    'promo_salary' => 0,
                    'incas' => 0,
                    'ads_expense' => 0,
                    'total_outflows' => 0,
                    'balance' => 0,
                ];
            }
        }
        
        foreach ($operations->groupBy('city_id') as $cityId => $cityOps) {
            $city = $cityOps->first()->city;
            
            // Считаем все категории для отчёта
            $orderIncome = $cityOps->filter(fn($o) => $o->category->cfm_cat_name === 'Поступление с Заказов')->sum('amount_cfm');
            $promoSalary = $cityOps->filter(fn($o) => $o->category->cfm_cat_name === 'Зарплата промоутеров')->sum('amount_cfm');
            $incas = $cityOps->filter(fn($o) => $o->category->cfm_cat_name === 'Инкас')->sum('amount_cfm');
            
            // Расход на объявления (HeadHunter + OLX + Авито)
            $adsExpense = $cityOps->filter(function($o) {
                return in_array($o->category->cfm_cat_name, ['HeadHunter', 'OLX', 'Объявление Авито'], true);
            })->sum('amount_cfm');
            
            // Все поступления
            $totalInflows = $cityOps->filter(fn($o) => $o->category->cfm_cat_group === 'inflows')->sum('amount_cfm');
            // Все выбытия  
            $totalOutflows = $cityOps->filter(fn($o) => $o->category->cfm_cat_group === 'outflows')->sum('amount_cfm');
            
            $otherInflows = max(0, $totalInflows - $orderIncome);

            // Операционный результат (приход - все расходы кроме инкаса)
            $operatingResult = $orderIncome - $promoSalary - $adsExpense;

            $report[$cityId] = [
                'city_id' => $cityId,
                'city_name' => $city->city_name,
                'operating_result' => $operatingResult,
                'order_income' => $orderIncome,
                'other_inflows' => $otherInflows,
                'promo_salary' => $promoSalary,
                'incas' => $incas,
                'ads_expense' => $adsExpense,
                'total_outflows' => $totalOutflows,
                'balance' => 0,
            ];

            $totals['operating_result'] += $operatingResult;
            $totals['order_income'] += $orderIncome;
            $totals['other_inflows'] += $otherInflows;
            $totals['promo_salary'] += $promoSalary;
            $totals['incas'] += $incas;
            $totals['ads_expense'] += $adsExpense;
            $totals['total_outflows'] += $totalOutflows;
        }

        // Остаток на дату «по»: весь приход с заказов − весь расход до этой даты (не дельта периода).
        $cash = $this->cashBalanceByCity($cityIds, $dateTo);
        foreach ($cash as $cityId => $row) {
            if (! isset($report[$cityId])) {
                if ($cities) {
                    continue;
                }
                $city = City::query()->find($cityId);
                if (! $city) {
                    continue;
                }
                $report[$cityId] = [
                    'city_id' => $cityId,
                    'city_name' => $city->city_name,
                    'operating_result' => 0,
                    'order_income' => 0,
                    'other_inflows' => 0,
                    'promo_salary' => 0,
                    'incas' => 0,
                    'ads_expense' => 0,
                    'total_outflows' => 0,
                    'balance' => $row['balance'],
                ];
                continue;
            }
            $report[$cityId]['balance'] = $row['balance'];
        }

        $totals['balance'] = 0;
        foreach ($report as $row) {
            $totals['balance'] += (int) $row['balance'];
        }
        
        return [
            'cities' => $report,
            'totals' => $totals,
        ];
    }

    /**
     * Остаток кассы на дату: приход с заказов − все выбытия по проведённым операциям.
     *
     * @param  list<int>  $cityIds
     * @return array<int, array{order_income:int,total_outflows:int,balance:int}>
     */
    private function cashBalanceByCity(array $cityIds, ?string $dateTo): array
    {
        $query = DB::table('cfm_operations as o')
            ->join('cfm_categories as c', 'c.cfm_cat_id', '=', 'o.cfm_cat_id')
            ->whereNotNull('o.cfm_closed_at');

        if ($cityIds !== []) {
            $query->whereIn('o.city_id', $cityIds);
        }
        if ($dateTo) {
            $query->where('o.cfm_closed_at', '<=', $dateTo.' 23:59:59');
        }

        $rows = $query
            ->groupBy('o.city_id')
            ->selectRaw("o.city_id,
                COALESCE(SUM(CASE WHEN c.cfm_cat_group = 'inflows' THEN o.amount_cfm ELSE 0 END), 0) as all_inflows,
                COALESCE(SUM(CASE WHEN c.cfm_cat_group = 'outflows' THEN o.amount_cfm ELSE 0 END), 0) as total_outflows")
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $inflows = (int) $r->all_inflows;
            $outflows = (int) $r->total_outflows;
            $out[(int) $r->city_id] = [
                'order_income' => $inflows,
                'total_outflows' => $outflows,
                'balance' => $inflows - $outflows,
            ];
        }

        return $out;
    }

    /**
     * Остаток проведённых операций кассы города (приход − расход), без соседних городов/спутников.
     */
    public function closedBalanceForCity(int $cityId): int
    {
        $row = $this->cashBalanceByCity([$cityId], null)[$cityId] ?? null;

        return (int) ($row['balance'] ?? 0);
    }

    /**
     * Расход нельзя провести, если касса этого города уйдёт ниже −лимита.
     * Спутник и основа — разные city_id, лимиты независимы.
     */
    public function assertCashOverdraftOnClose(CfmOperation $operation): void
    {
        $operation->loadMissing(['category', 'city.parentCity']);
        if ($operation->category?->cfm_cat_group !== 'outflows') {
            return;
        }

        $amount = (int) $operation->amount_cfm;
        if ($amount <= 0) {
            return;
        }

        $limit = max(0, (int) config('cfm.overdraft_limit', 2000));
        $cityId = (int) $operation->city_id;
        $balance = $this->closedBalanceForCity($cityId);
        $projected = $balance - $amount;
        if ($projected >= -$limit) {
            return;
        }

        $cityName = $operation->city?->displayName() ?: ('город #'.$cityId);
        $kind = $operation->city?->isSatellite() ? 'спутник' : 'город';

        throw \Illuminate\Validation\ValidationException::withMessages([
            'amount_cfm' => 'Касса «'.$cityName.'» ('.$kind.') уйдёт ниже −'
                .number_format($limit, 0, ',', ' ')
                .' ₽: сейчас '.number_format($balance, 0, ',', ' ')
                .' ₽, расход '.number_format($amount, 0, ',', ' ')
                .' ₽. Кассы города и спутника считаются отдельно — проверьте, что проводите инкасс по нужной.',
        ]);
    }
    
    /**
     * Детальный отчёт по городу (группировка по видам деятельности)
     */
    public function getCityReport(int $cityId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = CfmOperation::query()
            ->with('category')
            ->where('city_id', $cityId)
            ->whereNotNull('cfm_closed_at');
        
        if ($dateFrom) {
            $query->where('cfm_closed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('cfm_closed_at', '<=', $dateTo . ' 23:59:59');
        }
        
        $operations = $query->get();
        
        $report = [
            'operating' => ['total' => 0, 'details' => []],
            'investing' => ['total' => 0, 'details' => []],
            'financing' => ['total' => 0, 'details' => []],
        ];
        
        foreach ($operations as $op) {
            $activity = $op->category->cfm_cat_activities;
            $isOutflow = $op->category->cfm_cat_group === 'outflows';
            
            // Знаковая сумма: поступления +, выбытия -
            $signedAmount = $isOutflow ? -$op->amount_cfm : $op->amount_cfm;
            
            $report[$activity]['total'] += $signedAmount;
            
            $catName = $op->category->cfm_cat_name;
            if (! isset($report[$activity]['details'][$catName])) {
                $report[$activity]['details'][$catName] = [
                    'total' => 0,
                    'operations' => [],
                ];
            }
            $report[$activity]['details'][$catName]['total'] += $signedAmount;
            $report[$activity]['details'][$catName]['operations'][] = [
                'cfm_id' => (int) $op->cfm_id,
                'amount' => $signedAmount,
                'amount_raw' => (int) $op->amount_cfm,
                'is_outflow' => $isOutflow,
                'cfm_payer' => $op->cfm_payer,
                'cfm_recipient' => $op->cfm_recipient,
                'cfm_subcat' => $op->cfm_subcat,
                'cfm_adds' => $op->cfm_adds,
                'external_cfm_ref' => $op->external_cfm_ref,
                'related_order_id' => $op->related_order_id ? (int) $op->related_order_id : null,
                'cfm_closed_at' => $op->cfm_closed_at?->format('d.m.Y H:i'),
            ];
        }
        
        // Сортировка: "Поступление с Заказов" на первое место в операционной деятельности
        if (isset($report['operating']['details']['Поступление с Заказов'])) {
            $orderIncome = $report['operating']['details']['Поступление с Заказов'];
            unset($report['operating']['details']['Поступление с Заказов']);
            $report['operating']['details'] = ['Поступление с Заказов' => $orderIncome] + $report['operating']['details'];
        }
        
        return $report;
    }

    /**
     * Отчёт «Оплата заявок» по городу: приход с заказов − ЗП мастера − партам − расходы города.
     *
     * @return array{
     *     gross_income: int,
     *     master_salary: int,
     *     party_payments: int,
     *     city_expenses: int,
     *     city_expense_details: array<string, int>,
     *     net_remainder: int,
     *     orders_count: int
     * }
     */
    public function getOrderPaymentsReport(int $cityId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $orderQuery = \App\Models\Order::query()
            ->whereHas('address', fn ($q) => $q->where('city_id', $cityId))
            ->whereNotNull('order_closed_at')
            ->where('order_status', 'completed');

        if ($dateFrom) {
            $orderQuery->where('order_closed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $orderQuery->where('order_closed_at', '<=', $dateTo.' 23:59:59');
        }

        $orders = $orderQuery->get();
        $orderService = app(OrderService::class);

        $grossIncome = 0;
        $masterSalary = 0;
        foreach ($orders as $order) {
            $grossIncome += (int) $order->amount_paid;
            $masterSalary += $orderService->calculateMasterSalary($order);
        }

        $partyCategory = 'Оплата партов';
        $partnerExpenseCategory = 'Расход партнерам';
        $cityExpenseCategories = [
            'Аренда Офиса',
            'Содержание офиса',
            'Объявление Авито',
            'Зарплата промоутеров',
            'Листовки',
            'HeadHunter',
            'OLX',
            'QR Point',
            'Подписки',
            'Расходы на персонал',
            'Командировочные расходы',
            'Закуп оборудования',
        ];

        $cfmQuery = CfmOperation::query()
            ->with('category')
            ->where('city_id', $cityId)
            ->whereNotNull('cfm_closed_at')
            ->whereHas('category', fn ($q) => $q->where('cfm_cat_group', 'outflows'));

        if ($dateFrom) {
            $cfmQuery->where('cfm_closed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $cfmQuery->where('cfm_closed_at', '<=', $dateTo.' 23:59:59');
        }

        $partyPayments = 0;
        $cityExpenseDetails = [];
        $cityExpenses = 0;

        foreach ($cfmQuery->get() as $op) {
            $catName = $op->category->cfm_cat_name;
            $amount = (int) $op->amount_cfm;

            if ($catName === $partyCategory || $catName === $partnerExpenseCategory) {
                $partyPayments += $amount;
                continue;
            }

            if (in_array($catName, $cityExpenseCategories, true)) {
                $cityExpenses += $amount;
                $cityExpenseDetails[$catName] = ($cityExpenseDetails[$catName] ?? 0) + $amount;
            }
        }

        ksort($cityExpenseDetails);

        $netRemainder = $grossIncome - $masterSalary - $partyPayments - $cityExpenses;

        return [
            'gross_income' => $grossIncome,
            'master_salary' => $masterSalary,
            'party_payments' => $partyPayments,
            'city_expenses' => $cityExpenses,
            'city_expense_details' => $cityExpenseDetails,
            'net_remainder' => $netRemainder,
            'orders_count' => $orders->count(),
        ];
    }
    
    /**
     * Гарантировать наличие обязательных статей в БД (если миграция/сидер ещё не запускались на сервере).
     */
    public function ensureDefaultCategories(): void
    {
        $required = [
            [
                'cfm_cat_name' => 'Объявление Авито',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
            ],
            [
                'cfm_cat_name' => 'Оплата партов',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
                'visible_for_roles' => 'developer,branch_head,regional_director,senior_manager,general_director',
                'cfm_cat_adds' => 'Выплата партнёру через посредника',
            ],
            [
                'cfm_cat_name' => 'Расход партнерам',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
                'visible_for_roles' => 'developer,branch_head,regional_director,senior_manager,general_director',
                'cfm_cat_adds' => 'Перевод: директор забирает деньги за партнёрские заказы без посредника',
            ],
            [
                'cfm_cat_name' => 'Расход Уровень',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
                'visible_for_roles' => 'developer,branch_head,regional_director,senior_manager,general_director',
            ],
            [
                'cfm_cat_name' => 'Инкассация',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
                'visible_for_roles' => 'developer,branch_head,regional_director,senior_manager,general_director',
            ],
            [
                'cfm_cat_name' => 'Комиссия инкаса',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
                'visible_for_roles' => 'developer,branch_head,regional_director,senior_manager,general_director',
            ],
            [
                'cfm_cat_name' => 'Возвраты клиентам',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
                'visible_for_roles' => 'developer,call_center,senior_dispatcher,branch_head,regional_director,senior_manager,general_director',
            ],
            [
                'cfm_cat_name' => 'Расход на юриста',
                'cfm_cat_group' => 'outflows',
                'cfm_cat_activities' => 'operating',
            ],
        ];

        foreach ($required as $cat) {
            CfmCategory::updateOrCreate(
                ['cfm_cat_name' => $cat['cfm_cat_name']],
                array_merge([
                    'is_auto' => false,
                    'is_visible' => true,
                    'visible_for_roles' => $cat['visible_for_roles'] ?? null,
                    'available_for_city' => true,
                    'available_for_mc' => true,
                    'available_for_df' => false,
                    'cfm_cat_adds' => $cat['cfm_cat_adds'] ?? null,
                ], [
                    'cfm_cat_group' => $cat['cfm_cat_group'],
                    'cfm_cat_activities' => $cat['cfm_cat_activities'],
                ])
            );
        }
    }

    /**
     * Типы быстрых форм для регдиров / директоров филиала.
     *
     * @return list<string>
     */
    public function directorPaymentTypes(): array
    {
        return array_keys(config('cfm.director_types', []));
    }

    public function isDirectorPaymentType(?string $type): bool
    {
        return $type !== null && in_array($type, $this->directorPaymentTypes(), true);
    }

    public function directorCategoryName(string $type): ?string
    {
        return config('cfm.director_types.'.$type);
    }

    /**
     * Плательщики для форм директоров: [Дир]/[Рег].
     * ГД и developer видят всех; остальные — только себя.
     *
     * @return list<string>
     */
    public function directorPayers(?User $viewer = null): array
    {
        $viewer ??= auth()->user();

        $roleTags = [
            'general_director' => 'Дир',
            'regional_director' => 'Рег',
        ];

        $seeAll = $viewer && $viewer->hasAnyRole(['developer', 'general_director']);

        $payers = [];
        $seen = [];

        foreach ($roleTags as $roleCode => $tag) {
            $usersQuery = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('role_code', $roleCode))
                ->orderBy('user_name');

            if (! $seeAll && $viewer) {
                $usersQuery->where('user_id', $viewer->user_id);
            }

            foreach ($usersQuery->get(['user_id', 'user_name']) as $user) {
                $short = $this->shortPersonName((string) $user->user_name);
                if ($short === '' || $this->isPlaceholderStaffName($short)) {
                    continue;
                }

                $label = '['.$tag.'] '.$short;
                $key = mb_strtolower($label);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $payers[] = $label;
            }
        }

        // Филиал / ст. менеджер без роли Рег/ГД — только себя как [Дир]
        if (
            $viewer
            && ! $seeAll
            && $payers === []
            && $viewer->hasAnyRole(['branch_head', 'senior_manager'])
        ) {
            $short = $this->shortPersonName((string) $viewer->user_name);
            if ($short !== '' && ! $this->isPlaceholderStaffName($short)) {
                $payers[] = '[Дир] '.$short;
            }
        }

        // Доп. строки вручную (редко): CFM_PAYERS="[Дир] Иванов Иван,[Рег] Петров Пётр"
        $fromConfig = config('cfm.payers', []);
        if (is_array($fromConfig) && $seeAll) {
            foreach ($fromConfig as $extra) {
                $extra = trim((string) $extra);
                if ($extra === '') {
                    continue;
                }
                $key = mb_strtolower($extra);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $payers[] = $extra;
            }
        }

        return $payers;
    }

    /** Фамилия + имя (как в старой кассе), без отчества. */
    private function shortPersonName(string $fullName): string
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', $fullName) ?? '');
        if ($fullName === '') {
            return '';
        }

        $parts = explode(' ', $fullName);
        if (count($parts) >= 2) {
            return $parts[0].' '.$parts[1];
        }

        return $fullName;
    }

    private function isPlaceholderStaffName(string $name): bool
    {
        return (bool) preg_match(
            '/^(директор|старший менеджер)\b/iu',
            $name
        );
    }

    public function directorRecipient(string $type): ?string
    {
        return match ($type) {
            'party_payment' => config('cfm.recipients.party_payment'),
            'incassation' => config('cfm.recipients.incassation'),
            default => null,
        };
    }

    public function isClientRefundType(?string $type): bool
    {
        return $type === (string) config('cfm.client_refund.type', 'client_refund');
    }

    public function clientRefundCategoryName(): string
    {
        return (string) config('cfm.client_refund.category', 'Возвраты клиентам');
    }

    /**
     * Предложить разбивку возврата пропорционально доле мастера / «к сдаче» по заказу.
     *
     * @return array{
     *     order_id: int,
     *     city_id: int|null,
     *     city_name: string|null,
     *     master_id: int|null,
     *     master_name: string|null,
     *     amount_paid: int,
     *     amount_comp: int,
     *     net: int,
     *     master_percent: int,
     *     master_salary: int,
     *     amount_to_pay: int,
     *     suggest_from_master: int,
     *     suggest_from_cash: int
     * }
     */
    public function suggestClientRefundSplit(\App\Models\Order $order, int $totalRefund): array
    {
        $orderService = app(OrderService::class);
        $order->loadMissing(['master', 'address.city']);

        $net = max(0, $orderService->getNetAmount($order));
        $masterPercent = $orderService->getMasterPercent($order);
        $masterSalary = $orderService->calculateMasterSalary($order);
        $amountToPay = $orderService->calculateAmountToPay($order);

        $totalRefund = max(0, $totalRefund);
        if ($net <= 0 || $totalRefund === 0) {
            $fromMaster = 0;
            $fromCash = $totalRefund;
        } elseif ($totalRefund >= $net) {
            $fromMaster = $masterSalary;
            $fromCash = $totalRefund - $fromMaster;
        } else {
            $fromMaster = (int) floor($totalRefund * $masterPercent / 100);
            $fromCash = $totalRefund - $fromMaster;
        }

        return [
            'order_id' => (int) $order->order_id,
            'city_id' => $order->address?->city_id ? (int) $order->address->city_id : null,
            'city_name' => $order->address?->city?->city_name,
            'master_id' => $order->master_id ? (int) $order->master_id : null,
            'master_name' => $order->master?->user_name,
            'amount_paid' => (int) $order->amount_paid,
            'amount_comp' => (int) $order->amount_comp,
            'net' => $net,
            'master_percent' => $masterPercent,
            'master_salary' => $masterSalary,
            'amount_to_pay' => $amountToPay,
            'suggest_from_master' => $fromMaster,
            'suggest_from_cash' => $fromCash,
        ];
    }

    /**
     * Создать и сразу провести возврат клиенту (касса уменьшается на amount_from_cash).
     *
     * @param  array<int, \Illuminate\Http\UploadedFile|null>  $documents
     */
    public function createClientRefund(array $data, int $userId, array $documents = []): CfmOperation
    {
        $category = CfmCategory::where('cfm_cat_name', $this->clientRefundCategoryName())->firstOrFail();

        $fromCash = (int) $data['amount_from_cash'];
        $fromMaster = (int) $data['amount_from_master'];
        $total = $fromCash + $fromMaster;

        $commentLines = [
            'Возврат клиенту',
            'Заказ №'.($data['related_order_id'] ?? '—'),
            'Итого возврат: '.number_format($total, 0, ',', ' ').' ₽',
            'С кассы: '.number_format($fromCash, 0, ',', ' ').' ₽',
            'С мастера: '.number_format($fromMaster, 0, ',', ' ').' ₽',
        ];
        if (! empty($data['master_name'])) {
            $commentLines[] = 'Мастер: '.$data['master_name'];
        }
        if (! empty($data['cfm_adds'])) {
            $commentLines[] = trim((string) $data['cfm_adds']);
        }

        return DB::transaction(function () use ($data, $userId, $documents, $category, $fromCash, $fromMaster, $commentLines) {
            $operation = $this->create([
                'city_id' => $data['city_id'],
                'cfm_cat_id' => $category->cfm_cat_id,
                'amount_cfm' => $fromCash,
                'amount_from_master' => $fromMaster,
                'cfm_adds' => implode("\n", $commentLines),
                'related_order_id' => $data['related_order_id'],
                'related_user_id' => $data['related_user_id'] ?? null,
            ], $userId, $documents);

            // Сразу проводим — баланс кассы уменьшается. Если лимит минуса — откатываем и черновик.
            return $this->close($operation->fresh(), $userId);
        });
    }

    /**
     * Статьи кассы, доступные старшему менеджеру (по PDF тестера).
     *
     * @return list<string>
     */
    public function seniorManagerCategoryWhitelist(): array
    {
        return [
            'HeadHunter',
            'OLX',
            'QR Point',
            'Аренда Офиса',
            'Закуп оборудования',
            'Зарплата промоутеров',
            'Объявление Авито',
            'Подписки',
            'Расходы на персонал',
            'Расход на юриста',
            'Содержание офиса',
        ];
    }

    /**
     * Получить список категорий для создания операций
     */
    public function getAvailableCategories(User $user): Collection
    {
        $this->ensureDefaultCategories();

        // Раньше whitelist senior_manager применялся и к branch_head → у дира был короткий список.
        // Нужен паритет с рег. директором: фильтруем только по visible_for_roles / is_visible.
        // (старый seniorManagerCategoryWhitelist оставлен в коде для справки, не применяется)

        return CfmCategory::where('is_visible', true)
            ->where('is_auto', false)
            ->orderBy('cfm_cat_name')
            ->get()
            ->filter(function ($cat) use ($user) {
                if (! $cat->visible_for_roles) {
                    return true;
                }
                $allowedRoles = array_map('trim', explode(',', $cat->visible_for_roles));

                return $user->hasAnyRole($allowedRoles);
            });
    }

    /**
     * Получить категории по типу (для кнопок быстрого создания)
     */
    public function getCategoriesByType(string $type): Collection
    {
        $mapping = [
            'incas' => ['Инкас'],
                            'expense' => ['Зарплата промоутеров', 'Листовки', 'QR Point', 'HeadHunter', 'OLX', 'Объявление Авито',
                'Содержание офиса', 'Аренда Офиса', 'Командировочные расходы', 'Расход на юриста', 'Прочее Выбытие', 'Возвраты клиентам'],
            'income' => ['Прочее Поступление'],
            'transfer' => ['Перемещение (выбытие)'],
            'party_payment' => ['Оплата партов'],
            'partner_expense' => ['Расход партнерам'],
            'level_expense' => ['Расход Уровень'],
            'incassation' => ['Инкассация'],
            'incas_commission' => ['Комиссия инкаса'],
        ];

        $names = $mapping[$type] ?? [];

        return CfmCategory::whereIn('cfm_cat_name', $names)->get();
    }
}
