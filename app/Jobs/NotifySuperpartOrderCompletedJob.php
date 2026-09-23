<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PartnerApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifySuperpartOrderCompletedJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public function __construct(public int $orderId) {}

    public function handle(PartnerApiService $partnerApi): void
    {
        if (! $partnerApi->isConfigured()) {
            return;
        }

        $order = Order::query()->with(['source', 'address'])->find($this->orderId);
        if (! $order) {
            return;
        }

        $ok = $partnerApi->notifyOrderCompleted($order);
        if (! $ok) {
            // Частый кейс: заказ закрыли до появления в SP → 404. Создаём, затем начисляем.
            $created = $partnerApi->notifyPartnerOrderCreated($order);
            if ($created) {
                $ok = $partnerApi->notifyOrderCompleted($order);
            }
        }

        if (! $ok) {
            Log::warning('NotifySuperpartOrderCompletedJob: notify failed or skipped', [
                'order_id' => $this->orderId,
            ]);
        }
    }
}
