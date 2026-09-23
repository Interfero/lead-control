<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PartnerApiService;
use App\Services\SuperpartOutboxWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * FR-SRC-04: смена признака SP у источника → пересмотр связанных заявок через outbox.
 */
class EnqueueSuperpartSnapshotsForSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $sourceId) {}

    public function handle(SuperpartOutboxWriter $writer, PartnerApiService $partnerApi): void
    {
        $orderIds = Order::query()
            ->where('source_id', $this->sourceId)
            ->orderBy('order_id')
            ->pluck('order_id');

        $enqueued = 0;
        foreach ($orderIds as $orderId) {
            $order = Order::query()->whereKey((int) $orderId)->first();
            if (! $order) {
                continue;
            }

            try {
                $partnerApi->syncOrderPartnerFromSource($order);
                $order = $order->fresh();
                $writer->enqueueSnapshot($order);
                $enqueued++;
            } catch (\Throwable $e) {
                Log::warning('EnqueueSuperpartSnapshotsForSourceJob: skip order', [
                    'source_id' => $this->sourceId,
                    'order_id' => (int) $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('EnqueueSuperpartSnapshotsForSourceJob: done', [
            'source_id' => $this->sourceId,
            'orders' => $orderIds->count(),
            'enqueued' => $enqueued,
        ]);
    }
}
