<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class SyncOrderCoreFromEquipmentCommand extends Command
{
    protected $signature = 'orders:sync-order-core
                            {--dry-run : Только показать изменения}
                            {--refresh-cfm : Пересчитать кассу у уже проведённых заказов}
                            {--null-equipment-non-core : Без вида техники → непрофильный}';

    protected $description = 'Выставить order_core по виду техники (после правки CORE_EQUIPMENT)';

    public function handle(OrderService $orderService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $refreshCfm = (bool) $this->option('refresh-cfm');
        $nullEquipmentNonCore = (bool) $this->option('null-equipment-non-core');

        $updated = 0;
        $cfmRefreshed = 0;

        $process = function (Order $order) use ($dryRun, $refreshCfm, $orderService, &$updated, &$cfmRefreshed): void {
            if ($order->order_core === ($expected = $this->expectedOrderCore($order))) {
                return;
            }

            $label = $order->equipment_type
                ? (Order::EQUIPMENT_TYPES[$order->equipment_type] ?? $order->equipment_type)
                : '—';

            $this->line(sprintf(
                '#%d: %s → %s (%s)',
                $order->order_id,
                $order->order_core,
                $expected,
                $label
            ));

            if ($dryRun) {
                $updated++;

                return;
            }

            $order->order_core = $expected;
            $order->save();
            $updated++;

            if ($refreshCfm && $order->order_closed_at && $order->order_closed_by) {
                try {
                    $orderService->refreshClosedOrderCfm($order->fresh(), (int) $order->order_closed_by);
                    $cfmRefreshed++;
                } catch (\Throwable $e) {
                    $this->warn("  CFM #{$order->order_id}: {$e->getMessage()}");
                }
            }
        };

        Order::query()
            ->whereNotNull('equipment_type')
            ->orderBy('order_id')
            ->chunkById(200, function ($orders) use ($process) {
                foreach ($orders as $order) {
                    $process($order);
                }
            }, 'order_id');

        if ($nullEquipmentNonCore) {
            Order::query()
                ->whereNull('equipment_type')
                ->orderBy('order_id')
                ->chunkById(200, function ($orders) use ($process) {
                    foreach ($orders as $order) {
                        $process($order);
                    }
                }, 'order_id');
        }

        $this->info($dryRun
            ? "Будет обновлено заказов: {$updated}"
            : "Обновлено заказов: {$updated}, CFM пересчитано: {$cfmRefreshed}");

        return self::SUCCESS;
    }

    private function expectedOrderCore(Order $order): string
    {
        if (! $order->equipment_type) {
            return 'non_core';
        }

        return Order::getOrderCoreByEquipment($order->equipment_type);
    }
}
