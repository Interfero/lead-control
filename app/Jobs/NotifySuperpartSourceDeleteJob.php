<?php

namespace App\Jobs;

use App\Services\PartnerApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifySuperpartSourceDeleteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(PartnerApiService $partnerApi): void
    {
        if (! $partnerApi->isConfigured()) {
            return;
        }

        $ok = $partnerApi->notifyReferenceSourceDeletePayload($this->payload);
        if (! $ok) {
            Log::warning('NotifySuperpartSourceDeleteJob: notify failed', [
                'payload' => $this->payload,
            ]);
        }
    }
}
