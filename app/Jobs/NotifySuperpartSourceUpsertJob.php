<?php

namespace App\Jobs;

use App\Models\Source;
use App\Services\PartnerApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifySuperpartSourceUpsertJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public function __construct(public int $sourceId) {}

    public function handle(PartnerApiService $partnerApi): void
    {
        if (! $partnerApi->isConfigured()) {
            return;
        }

        $source = Source::query()->with('city')->find($this->sourceId);
        if (! $source) {
            return;
        }

        $ok = $partnerApi->notifyReferenceSourceUpsert($source);
        if (! $ok) {
            Log::warning('NotifySuperpartSourceUpsertJob: notify failed', [
                'source_id' => $this->sourceId,
            ]);
        }
    }
}
