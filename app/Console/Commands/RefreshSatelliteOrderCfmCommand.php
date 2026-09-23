<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class RefreshSatelliteOrderCfmCommand extends Command
{
    protected $signature = 'orders:refresh-satellite-cfm
                            {--dry-run : Только показать заказы}
                            {--order= : Конкретный order_id}';

    protected $description = 'Пересчитать кассу по проведённым заказам-спутникам, если коэффициент в CFM устарел';

    public function handle(OrderService $orderService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $orderId = $this->option('order');

        $query = Order::with(['address.city.parentCity', 'persons.addresses.city', 'cfmOperations'])
            ->whereNotNull('order_closed_at')
            ->whereNotIn('order_status', ['cancelled_cc', 'cancelled_city'])
            ->orderBy('order_id');

        if ($orderId) {
            $query->where('order_id', (int) $orderId);
        }

        $fixed = 0;

        foreach ($query->cursor() as $order) {
            if (! $order->isSatelliteCityOrder()) {
                continue;
            }

            $expectedPercent = $orderService->getMasterPercent($order);
            $cfm = $order->cfmOperations->first();
            if (! $cfm) {
                continue;
            }

            $storedPercent = null;
            if (preg_match('/коэффициент:\s*(\d+)%/u', $cfm->cfm_adds ?? '', $matches)) {
                $storedPercent = (int) $matches[1];
            }

            if ($storedPercent === null || $storedPercent === $expectedPercent) {
                continue;
            }

            $this->line("#{$order->order_id}: CFM {$storedPercent}% → {$expectedPercent}%");

            if (! $dryRun) {
                $orderService->refreshClosedOrderCfm($order, (int) ($order->order_closed_by ?: 1));
                $fixed++;
            }
        }

        $this->info($dryRun
            ? 'Dry-run завершён (без изменений).'
            : "Обновлено заказов: {$fixed}");

        return self::SUCCESS;
    }
}
