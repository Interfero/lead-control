<?php

namespace App\Services;

use App\Models\Order;
use App\Models\CfmOperation;
use App\Models\CfmCategory;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private CfmService $cfmService,
    ) {}

    /**
     * Расчёт проведённой суммы (оплачено - комплектующие)
     */
    public function getNetAmount(Order $order): int
    {
        return $order->amount_paid - $order->amount_comp;
    }
    
    /**
     * Получить процент мастера
     */
    public function getMasterPercent(Order $order): int
    {
        $netAmount = $this->getNetAmount($order);

        // Гарантия до 7500 включительно: 50/50.
        // Свыше 7500 при проведении тип станет «повтор» — здесь считаем уже как обычный заказ.
        if ($order->order_type === 'warranty' && $netAmount <= 7500) {
            return 50;
        }

        // Дальний выезд: 50/50
        if ($order->is_long_trip) {
            return 50;
        }

        // Город-спутник: 50/50
        if ($order->isSatelliteCityOrder()) {
            return 50;
        }

        // Прочий: наш — 50%, партнёрский — мастер 40% / компания 60%
        if ($order->order_core === 'other') {
            $order->loadMissing('source');

            return $order->isPartnerOrder() ? 40 : 50;
        }

        // Непрофиль: 40%, но если > 7500 — как профиль
        if ($order->order_core === 'non_core') {
            if ($netAmount <= 7500) {
                return 40;
            }
            // Дальше расчёт как по профилю
        }

        // Профиль (и непрофиль > 7500; гарантия > 7500 до смены типа)
        return match (true) {
            $netAmount <= 2500 => 25,
            $netAmount <= 4500 => 30,
            $netAmount <= 7500 => 35,
            $netAmount <= 10500 => 40,
            $netAmount <= 17000 => 45,
            default => 50,
        };
    }
    
    /**
     * Расчёт зарплаты мастера
     * Округление в пользу компании (floor)
     */
    public function calculateMasterSalary(Order $order): int
    {
        $netAmount = $this->getNetAmount($order);
        $percent = $this->getMasterPercent($order);
        
        return (int) floor($netAmount * $percent / 100);
    }
    
    /**
     * Расчёт суммы к сдаче (проведённая - зарплата мастера)
     */
    public function calculateAmountToPay(Order $order): int
    {
        $netAmount = $this->getNetAmount($order);
        $masterSalary = $this->calculateMasterSalary($order);
        
        return $netAmount - $masterSalary;
    }

    /**
     * Поля для Desk API / единого окна: профильность и расчётка как в CRM.
     *
     * @return array<string, mixed>
     */
    public function deskSerializationExtras(Order $order): array
    {
        $order->loadMissing(['address.city.parentCity', 'persons.addresses.city', 'source']);

        $paid = (int) ($order->amount_paid ?? 0);
        $parts = (int) ($order->amount_comp ?? 0);

        $extras = [
            'order_core' => $order->order_core,
            'is_noncore' => $order->order_core === 'non_core',
            'is_long_trip' => (bool) $order->is_long_trip,
            'is_satellite' => $order->isSatelliteCityOrder(),
            'is_partner_order' => $order->isPartnerOrder(),
            'partner_user_id' => $order->partner_user_id ? (int) $order->partner_user_id : null,
            'order_core_label' => Order::orderCoreLabel($order->order_core),
        ];

        if ($order->amount_paid !== null) {
            $net = max(0, $paid - $parts);
            $salary = $this->calculateMasterSalary($order);
            $extras['calculation'] = [
                'paid' => $paid,
                'parts' => $parts,
                'net_amount' => $net,
                'master_percent' => $this->getMasterPercent($order),
                'master_salary' => $salary,
                'amount_to_pay' => $net - $salary,
                'source' => 'crm1',
            ];
        }

        return $extras;
    }
    
    /**
     * Проведение заказа
     */
    public function complete(Order $order, int $userId): void
    {
        DB::transaction(function () use ($order, $userId) {
            $cancelledStatuses = ['cancelled_cc', 'cancelled_city'];
            $isCancelled = in_array($order->order_status, $cancelledStatuses);
            
            // Для не-отменённых заказов ВСЕГДА устанавливаем статус completed
            $order->order_closed_by = $userId;
            $order->order_closed_at = now();
            
            if (!$isCancelled) {
                $order->order_status = 'completed'; // Автоматически меняем статус
                $order->status_changed_at = now();
                
                // Гарантия свыше 7500 р. → переводим в повтор
                $netAmount = $this->getNetAmount($order);
                if ($order->order_type === 'warranty' && $netAmount > 7500) {
                    $order->order_type = 'repeat';
                }
            }
            
            $order->save();
            
            // Для отменённых заказов финансовые операции НЕ создаются
            // (заказ не был выполнен, денег не поступило)
            if ($isCancelled) {
                return;
            }
            
            $this->postIncomeToCashbox($order, $userId);
        });

        $order->refresh();
        try {
            app(ReportCityDailyService::class)->markStaleForOrder($order);
        } catch (\Throwable) {
            // срез не должен ломать проведение
        }
        try {
            $partnerApi = app(PartnerApiService::class);
            $partnerApi->syncOrderPartnerFromSource($order);
            \App\Jobs\NotifySuperpartOrderCompletedJob::dispatchSync((int) $order->order_id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OrderService::complete: SuperPart notify failed', [
                'order_id' => $order->order_id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Пересоздать кассовую операцию по уже проведённому заказу (после правки сумм / профильности).
     */
    public function refreshClosedOrderCfm(Order $order, int $userId): void
    {
        if (! $order->order_closed_at) {
            return;
        }

        if (in_array($order->order_status, ['cancelled_cc', 'cancelled_city'], true)) {
            return;
        }

        DB::transaction(function () use ($order, $userId) {
            CfmOperation::where('related_order_id', $order->order_id)->delete();
            $this->postIncomeToCashbox($order->fresh(['master', 'address']), $userId);
        });

        try {
            app(ReportCityDailyService::class)->markStaleForOrder($order->fresh(['address']));
        } catch (\Throwable) {
        }
    }

    private function postIncomeToCashbox(Order $order, int $userId): void
    {
        $order->loadMissing(['address.city.parentCity', 'master', 'persons.addresses.city']);
        $cityId = $order->address?->city_id;
        if (! $cityId) {
            throw new \RuntimeException('У заказа не указан город');
        }

        $netAmount = $this->getNetAmount($order);
        $masterPercent = $this->getMasterPercent($order);
        $masterSalary = $this->calculateMasterSalary($order);
        $amountToPay = $this->calculateAmountToPay($order);

        $masterName = $order->master ? $order->master->user_name : 'не назначен';
        $comment = "Заказ №{$order->order_id}\n"
                 . "Мастер: {$masterName}\n"
                 . "Проведённая сумма: ".number_format($netAmount, 0, ',', ' ')." ₽\n"
                 . "Расчётный коэффициент: {$masterPercent}%\n"
                 . "ЗП мастера: ".number_format($masterSalary, 0, ',', ' ')." ₽";

        $incomeCategory = CfmCategory::where('cfm_cat_name', 'Поступление с Заказов')->first();
        if (! $incomeCategory) {
            throw new \RuntimeException('Не найдена категория CFM «Поступление с Заказов»');
        }

        $postedAt = now();
        $cfmOperation = new CfmOperation([
            'city_id' => $cityId,
            'cfm_cat_id' => $incomeCategory->cfm_cat_id,
            'amount_cfm' => $amountToPay,
            'cfm_adds' => $comment,
            'related_order_id' => $order->order_id,
            'cfm_created_at' => $postedAt,
        ]);
        $cfmOperation->cfm_created_by = $userId;
        $cfmOperation->save();
        // Проведение той же меткой времени — без сдвига месяца из‑за TZ MySQL vs app.
        $cfmOperation->cfm_closed_by = $userId;
        $cfmOperation->cfm_closed_at = $postedAt;
        $cfmOperation->save();
        try {
            app(\App\Services\ReportCityDailyService::class)->markStaleForCityDate(
                (int) $cityId,
                $postedAt
            );
        } catch (\Throwable) {
        }
    }
    
    /**
     * Проверка возможности проведения
     * Проведение доступно для любого статуса (кроме уже проведённых)
     * При проведении статус автоматически меняется на "completed" (кроме отменённых)
     */
    public function canComplete(Order $order): array
    {
        $errors = [];
        
        // Проверка: заказ уже проведён?
        if ($order->order_closed_at) {
            return [
                'can' => false, 
                'errors' => ['Заказ уже проведён']
            ];
        }
        
        $cancelledStatuses = ['cancelled_cc', 'cancelled_city'];
        $isCancelled = in_array($order->order_status, $cancelledStatuses);
        
        // Для отменённых заказов: мастер НЕ должен быть назначен
        if ($isCancelled) {
            if ($order->master_id) {
                $errors[] = 'Отмена: мастер не должен быть назначен (заказ не выполнялся)';
            }
            return [
                'can' => empty($errors),
                'errors' => $errors,
            ];
        }
        
        // Для обычных заказов требуем мастера
        if (! $order->master_id) {
            $errors[] = 'Не назначен мастер';
        }

        // Документы: обязательны при «Оплачено» >= 3000 ₽ (как в Levelion / Едином окне)
        $paid = (int) ($order->amount_paid ?? 0);
        if (Order::documentsRequiredForPaidAmount($paid)) {
            $order->loadMissing('documents');
            if ($order->documents->count() < 1) {
                $errors[] = 'При сумме от '.Order::DOCUMENTS_REQUIRED_FROM_PAID.' ₽ загрузите хотя бы один документ';
            }
        }

        return [
            'can' => empty($errors),
            'errors' => $errors,
        ];
    }
}
