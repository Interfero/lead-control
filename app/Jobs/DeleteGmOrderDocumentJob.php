<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\GmOrderSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeleteGmOrderDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 45, 120];

    public function __construct(
        public int $documentId,
        public ?int $orderId = null,
    ) {}

    public function handle(GmOrderSyncService $gmOrderSync): void
    {
        $syncResult = $gmOrderSync->deleteOrderDocument($this->documentId);
        if (($syncResult['ok'] ?? false) !== true && ! ($syncResult['skipped'] ?? false)) {
            Log::warning('DeleteGmOrderDocumentJob: delete failed', [
                'document_id' => $this->documentId,
                'gm_sync' => $syncResult,
            ]);
        }

        if ($this->orderId) {
            SyncOrderToGmJob::dispatch($this->orderId);
        }
    }
}
