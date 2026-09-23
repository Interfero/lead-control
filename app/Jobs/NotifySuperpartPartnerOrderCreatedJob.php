<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PartnerApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifySuperpartPartnerOrderCreatedJob implements ShouldQueue
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

        $order = Order::query()
            ->with(['address.city', 'persons.phones', 'source'])
            ->find($this->orderId);

        if (! $order || ! $partnerApi->resolvePartnerUserId($order)) {
            Log::info('NotifySuperpartPartnerOrderCreatedJob: skip (нет партнёра SP)', [
                'order_id' => $this->orderId,
                'source_id' => $order?->source_id,
            ]);

            return;
        }

        $ok = $partnerApi->notifyPartnerOrderCreated($order);
        if (! $ok) {
            Log::warning('NotifySuperpartPartnerOrderCreatedJob: notify failed', [
                'order_id' => $this->orderId,
            ]);
        }
    }
}
