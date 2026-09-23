<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\GmUserSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncUserToGmJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 45, 120];

    public function __construct(public int $userId) {}

    public function handle(GmUserSyncService $gmUserSync): void
    {
        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        try {
            $result = $gmUserSync->sync($user);
            $gmUserSync->logResult($user, $result);
        } catch (\Throwable $e) {
            Log::warning('SyncUserToGmJob failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
