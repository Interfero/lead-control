<?php

namespace App\Services;

use App\Models\PromPayment;
use App\Models\PromPaymentDetail;
use App\Models\RouteAction;
use App\Models\CfmOperation;
use App\Models\CfmCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class PromPaymentsService
{
    /**
     * Шкала оплаты за листовки (из PromPayment::RATE_TIERS)
     */
    const RATE_TIERS = [
        ['min' => 10006, 'rate' => 12],
        ['min' => 5006, 'rate' => 9],
        ['min' => 1256, 'rate' => 6],
        ['min' => 1, 'rate' => 3],
    ];
    
    /**
     * Получить список оплат с фильтрацией
     */
    public function getPayments(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = PromPayment::with(['city', 'promoter', 'bank', 'cfmOperation', 'creator'])
            ->orderBy('created_at', 'desc');
        
        if (!empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        }
        
        if (!empty($filters['promoter_id'])) {
            $query->where('promoter_id', $filters['promoter_id']);
        }
        
        if (!empty($filters['status'])) {
            $query->where('payment_status', $filters['status']);
        }
        
        return $query->paginate($perPage);
    }
    
    /**
     * Рассчитать ставку за листовку по шкале
     */
    public static function calculateRate(int $leaflets): int
    {
        foreach (self::RATE_TIERS as $tier) {
            if ($leaflets >= $tier['min']) {
                return $tier['rate'];
            }
        }
        return 0;
    }
    
    /**
     * Получить данные для предпросмотра оплаты (AJAX)
     */
    public function getPaymentPreview(int $cityId, int $promoterId, Carbon $weekStart, Carbon $weekEnd): array
    {
        $actions = RouteAction::with('route')
            ->where('city_id', $cityId)
            ->where('promoter_id', $promoterId)
            ->whereDate('route_action_date', '>=', $weekStart)
            ->whereDate('route_action_date', '<=', $weekEnd)
            ->orderBy('route_action_date')
            ->get();
        
        $totalLeaflets = $actions->sum('leaflets_count');
        $rate = self::calculateRate($totalLeaflets);
        $amountBase = $totalLeaflets * $rate;
        
        return [
            'actions' => $actions,
            'total_leaflets' => $totalLeaflets,
            'rate' => $rate,
            'amount_base' => $amountBase,
        ];
    }
    
    /**
     * Получить последние использованные реквизиты промоутера
     * (из последней ПРОВЕДЁННОЙ оплаты)
     */
    public function getLastPaymentRequisites(int $promoterId): ?array
    {
        $lastPayment = PromPayment::where('promoter_id', $promoterId)
            ->where('payment_status', PromPayment::STATUS_PAID)
            ->whereNotNull('payment_requisites')
            ->orderBy('paid_at', 'desc')
            ->first();
        
        if (!$lastPayment) {
            return null;
        }
        
        return [
            'requisites' => $lastPayment->payment_requisites,
            'bank_id' => $lastPayment->payment_bank_id,
            'bank_name' => $lastPayment->bank?->bank_name,
        ];
    }
    
    /**
     * Создать оплату с кассовой операцией
     * 
     * Паттерн: как OrderService::complete() — транзакция, создание CFM
     */
    public function create(array $data, User $creator): PromPayment
    {
        return DB::transaction(function () use ($data, $creator) {
            $weekStart = Carbon::parse($data['week_start']);
            $weekEnd = Carbon::parse($data['week_end']);
            
            // Получаем предпросмотр для расчёта
            $preview = $this->getPaymentPreview(
                $data['city_id'],
                $data['promoter_id'],
                $weekStart,
                $weekEnd
            );
            
            $adjustment = (int) ($data['amount_adjustment'] ?? 0);
            $amountTotal = $preview['amount_base'] + $adjustment;
            
            // Создаём оплату
            $payment = new PromPayment([
                'city_id' => $data['city_id'],
                'promoter_id' => $data['promoter_id'],
                'week_start' => $weekStart,
                'week_end' => $weekEnd,
                'total_leaflets' => $preview['total_leaflets'],
                'rate_per_leaflet' => $preview['rate'],
                'amount_base' => $preview['amount_base'],
                'amount_adjustment' => $adjustment,
                'amount_total' => $amountTotal,
                'payment_requisites' => $data['payment_requisites'] ?? null,
                'payment_bank_id' => $data['payment_bank_id'] ?? null,
                'payment_comment' => $data['payment_comment'] ?? null,
                'payment_status' => PromPayment::STATUS_CREATED,
            ]);
            $payment->created_by = $creator->user_id;
            $payment->save();
            
            // Создаём детализацию
            foreach ($preview['actions'] as $action) {
                PromPaymentDetail::create([
                    'payment_id' => $payment->payment_id,
                    'route_action_id' => $action->route_action_id,
                    'action_date' => $action->route_action_date,
                    'leaflets_count' => $action->leaflets_count,
                    'route_name' => $action->route?->route_name,
                ]);
            }
            
            // Создаём кассовую операцию (НЕ проведённую)
            $cfmOperation = $this->createCfmOperation($payment, $creator);
            $payment->update(['cfm_operation_id' => $cfmOperation->cfm_id]);
            
            return $payment->fresh();
        });
    }
    
    /**
     * Обновить оплату
     */
    public function update(PromPayment $payment, array $data): PromPayment
    {
        if ($payment->payment_status === PromPayment::STATUS_PAID) {
            throw new \Exception('Нельзя редактировать оплаченную запись');
        }
        
        return DB::transaction(function () use ($payment, $data) {
            $adjustment = (int) ($data['amount_adjustment'] ?? $payment->amount_adjustment);
            $amountTotal = $payment->amount_base + $adjustment;
            
            $payment->update([
                'amount_adjustment' => $adjustment,
                'amount_total' => $amountTotal,
                'payment_requisites' => $data['payment_requisites'] ?? $payment->payment_requisites,
                'payment_bank_id' => array_key_exists('payment_bank_id', $data) 
                    ? $data['payment_bank_id'] 
                    : $payment->payment_bank_id,
                'payment_comment' => array_key_exists('payment_comment', $data) 
                    ? $data['payment_comment'] 
                    : $payment->payment_comment,
            ]);
            
            // Обновляем связанную кассовую операцию
            if ($payment->cfmOperation && !$payment->cfmOperation->cfm_closed_at) {
                $payment->cfmOperation->update([
                    'amount_cfm' => $amountTotal,
                    'cfm_adds' => $this->generateCfmComment($payment->fresh()),
                ]);
            }
            
            return $payment->fresh();
        });
    }
    
    /**
     * Удалить оплату
     */
    public function delete(PromPayment $payment): void
    {
        if ($payment->payment_status === PromPayment::STATUS_PAID) {
            throw new \Exception('Нельзя удалить оплаченную запись');
        }
        
        DB::transaction(function () use ($payment) {
            // Удаляем связанную непроведённую кассовую операцию
            if ($payment->cfmOperation && !$payment->cfmOperation->cfm_closed_at) {
                $payment->cfmOperation->delete();
            }
            
            $payment->details()->delete();
            $payment->delete();
        });
    }
    
    /**
     * Создать кассовую операцию для оплаты
     */
    private function createCfmOperation(PromPayment $payment, User $creator): CfmOperation
    {
        // Находим категорию "Зарплата промоутеров"
        $category = CfmCategory::where('cfm_cat_name', 'Зарплата промоутеров')->first();
        
        if (!$category) {
            throw new \Exception('Категория "Зарплата промоутеров" не найдена в справочнике');
        }
        
        $operation = new CfmOperation([
            'city_id' => $payment->city_id,
            'cfm_cat_id' => $category->cfm_cat_id,
            'amount_cfm' => $payment->amount_total,
            'cfm_adds' => $this->generateCfmComment($payment),
            'cfm_created_at' => now(),
            // НЕ заполняем cfm_closed_by и cfm_closed_at — операция не проведена
        ]);
        $operation->cfm_created_by = $creator->user_id;
        $operation->save();
        
        return $operation;
    }
    
    /**
     * Генерация комментария для кассовой операции
     */
    public function generateCfmComment(PromPayment $payment): string
    {
        $payment->load(['promoter', 'details', 'bank']);
        
        $lines = [
            "Оплата №{$payment->payment_id}",
            "Промоутер: {$payment->promoter?->promoter_name}",
            "Неделя: {$payment->week_start->format('d.m')} - {$payment->week_end->format('d.m.Y')}",
        ];
        
        $i = 1;
        foreach ($payment->details as $detail) {
            $date = Carbon::parse($detail->action_date)->format('d.m.y');
            $lines[] = "{$i}. {$date} - {$detail->leaflets_count} - {$detail->route_name}";
            $i++;
        }
        
        $lines[] = "Всего листовок: {$payment->total_leaflets}";
        $lines[] = "Расчётный коэффициент: {$payment->rate_per_leaflet} ₽";
        $lines[] = "Итого: {$payment->amount_base} ₽";
        $lines[] = "Корректировки: {$payment->amount_adjustment} ₽";
        $lines[] = "К оплате: {$payment->amount_total} ₽";
        
        // Добавляем реквизиты для удобства
        $lines[] = "";
        $bankName = $payment->bank?->bank_name ?? '';
        $requisites = $payment->payment_requisites ?? '';
        if ($bankName || $requisites) {
            $lines[] = "Реквизиты: {$bankName}" . ($bankName && $requisites ? ' - ' : '') . $requisites;
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Пометить оплату как "Оплачено"
     * Вызывается при проведении кассовой операции (из CfmService)
     */
    public function markAsPaid(PromPayment $payment): void
    {
        $payment->update([
            'payment_status' => PromPayment::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
    
    /**
     * Получить доступные недели для выбора
     */
    public function getAvailableWeeks(int $limit = 12): array
    {
        $weeks = [];
        $current = now()->startOfWeek();
        
        for ($i = 0; $i < $limit; $i++) {
            $weekStart = $current->copy()->subWeeks($i);
            $weekEnd = $weekStart->copy()->endOfWeek();
            
            $weeks[] = [
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'label' => $weekStart->format('d.m') . ' - ' . $weekEnd->format('d.m.Y'),
            ];
        }
        
        return $weeks;
    }
}
