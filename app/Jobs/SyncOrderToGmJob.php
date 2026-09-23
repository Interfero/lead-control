<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\GmOrderSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncOrderToGmJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 45, 120];

    public function __construct(public int $orderId) {}

    public function handle(GmOrderSyncService $gmOrderSync): void
    {
        $order = Order::query()->find($this->orderId);
        if (! $order) {
            return;
        }

        $result = $gmOrderSync->sync($order);
        $gmOrderSync->logResult($order, $result);

        if (($result['ok'] ?? false) !== true && ! ($result['skipped'] ?? false)) {
            Log::warning('SyncOrderToGmJob: sync not ok', [
                'order_id' => $this->orderId,
                'result' => $result,
            ]);
        }
    }
}
