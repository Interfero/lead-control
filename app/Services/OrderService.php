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
     * Сумма, которую мастер уже отдал клиенту при возврате (доля «с мастера»)
     * по закрытым операциям возврата, привязанным к заказу. Возврат не меняет
     * amount_paid/amount_comp заказа (это отдельная, уже закрытая кассовая
     * операция) — поэтому «к сдаче» без этой поправки навсегда остаётся
     * завышенной после возврата.
     */
    public function refundedFromMasterTotal(Order $order): int
    {
        return (int) CfmOperation::query()
            ->join('cfm_categories', 'cfm_categories.cfm_cat_id', '=', 'cfm_operations.cfm_cat_id')
            ->where('cfm_operations.related_order_id', $order->order_id)
            ->where('cfm_categories.cfm_cat_name', $this->cfmService->clientRefundCategoryName())
            ->whereNotNull('cfm_operations.cfm_closed_at')
            ->sum('cfm_operations.amount_from_master');
    }

    /**
     * Расчёт суммы к сдаче (проведённая - зарплата мастера - уже отданное
     * мастером клиенту при возврате).
     */
    public function calculateAmountToPay(Order $order): int
    {
        $netAmount = $this->getNetAmount($order);
        $masterSalary = $this->calculateMasterSalary($order);
        $refundedFromMaster = $this->refundedFromMasterTotal($order);

        return $netAmount - $masterSalary - $refundedFromMaster;
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
        $check = $this->canComplete($order);
        if (! ($check['can'] ?? false)) {
            throw new \RuntimeException(implode('. ', $check['errors'] ?? ['Нельзя провести заказ']));
        }

        DB::transaction(function () use ($order, $userId) {
            $cancelledStatuses = ['cancelled_cc', 'cancelled_city'];
            $isCancelled = in_array($order->order_status, $cancelledStatuses, true);
            $paid = (int) ($order->amount_paid ?? 0);
            $isRefusal = ! $isCancelled && $paid <= 0;

            $order->order_closed_by = $userId;
            $order->order_closed_at = now();

            if (! $isCancelled) {
                $order->order_status = $isRefusal ? 'rejected' : 'completed';
                $order->status_changed_at = now();

                $netAmount = $this->getNetAmount($order);
                if ($order->order_type === 'warranty' && $netAmount > 7500) {
                    $order->order_type = 'repeat';
                }
            }

            $order->save();

            if ($isCancelled || $isRefusal) {
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
     * Снять проведение: касса по заявке удаляется, «Готов»/«Отказ» → «В работе».
     */
    public function reopen(Order $order): void
    {
        if (! $order->order_closed_at) {
            throw new \RuntimeException('Заказ не был проведён');
        }

        DB::transaction(function () use ($order) {
            CfmOperation::where('related_order_id', $order->order_id)->delete();

            $order->order_closed_at = null;
            $order->order_closed_by = null;

            if (in_array((string) $order->order_status, ['completed', 'rejected'], true)) {
                $order->order_status = 'in_progress';
                $order->status_changed_at = now();
                if ($order->in_progress_at === null) {
                    $order->in_progress_at = now();
                }
            }

            $order->save();
        });
    }

    /**
     * Пересоздать кассовую операцию по уже проведённому заказу (после правки сумм / профильности).
     */
    public function refreshClosedOrderCfm(Order $order, int $userId): void
    {
        if (! $order->order_closed_at) {
            return;
        }

        if (in_array($order->order_status, ['cancelled_cc', 'cancelled_city', 'rejected'], true)) {
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
     * Проведение доступно для любого статуса (кроме уже проведённых).
     * Оплата 0 → «Отказ»; иначе «Готов». Отмены КЦ/филиала не меняют статус.
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
        $isCancelled = in_array($order->order_status, $cancelledStatuses, true);

        if ($isCancelled) {
            if ($order->master_id) {
                $errors[] = 'Отмена: мастер не должен быть назначен (заказ не выполнялся)';
            }

            return [
                'can' => empty($errors),
                'errors' => $errors,
            ];
        }

        if (! $order->master_id) {
            $errors[] = 'Не назначен мастер';
        }

        $paid = (int) ($order->amount_paid ?? 0);
        if ($paid > 0) {
            $order->loadMissing('documents');
            if ($order->documents->count() < 1) {
                // CLOSE_DEL_ANY_PHOTO: «Готов» без фото закрыть нельзя; удалять фото можно и после закрытия.
                $errors[] = 'Загрузите хотя бы один документ. «Готов» без документов закрыть нельзя';
            }

            $comp = (int) ($order->amount_comp ?? 0);
            if ($comp > 0) {
                $hasReceipt = $order->documents->contains(
                    fn ($doc) => ($doc->document_category ?? '') === Order::DOCUMENT_CATEGORY_RECEIPTS
                );
                if (! $hasReceipt) {
                    $errors[] = 'Указан расход — приложите чек расхода. Фото запчастей недостаточно';
                }
            }
        }

        return [
            'can' => empty($errors),
            'errors' => $errors,
        ];
    }
}
