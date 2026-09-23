<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BotGuardService
{
    public function __construct(
        private SecurityAuditService $audit,
        private SessionInvalidationService $sessions,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('bot_guard.enabled', true);
    }

    public function isExempt(?User $user): bool
    {
        if (! $user) {
            return true;
        }

        $roles = config('bot_guard.exempt_roles', ['developer']);

        return $user->hasAnyRole($roles);
    }

    public function isLocked(int $userId): bool
    {
        return Cache::has($this->lockKey($userId));
    }

    public function lockRemainingSeconds(int $userId): int
    {
        $ttl = Cache::get($this->lockKey($userId).':ttl_until');
        if (! $ttl) {
            return (int) config('bot_guard.lock_minutes', 30) * 60;
        }

        return max(0, (int) $ttl - time());
    }

    /**
     * Учесть запрос. Вернуть null если всё ок, либо reason-код при kick.
     */
    public function inspect(Request $request, User $user): ?string
    {
        if (! $this->enabled() || $this->isExempt($user)) {
            return null;
        }

        if ($this->isLocked((int) $user->user_id)) {
            return 'locked';
        }

        if (! $user->is_active || $user->is_blacklisted) {
            return 'inactive';
        }

        $routeName = $request->route()?->getName() ?? '';
        $exclude = config('bot_guard.exclude_route_names', []);
        if ($routeName !== '' && in_array($routeName, $exclude, true)) {
            return null;
        }

        $uid = (int) $user->user_id;
        $now = time();

        $isSearch = $routeName !== '' && in_array($routeName, config('bot_guard.search_route_names', []), true);
        $isSensitive = $isSearch
            || ($routeName !== '' && in_array($routeName, config('bot_guard.sensitive_route_names', []), true));

        $burst = $this->hitWindow($this->burstKey($uid), $now, 10);
        $minute = $this->hitWindow($this->minuteKey($uid), $now, 60);

        $maxBurst = (int) config('bot_guard.max_per_10_seconds', 28);
        $maxMinute = (int) config('bot_guard.max_per_minute', 90);

        if ($burst > $maxBurst) {
            return $this->kick($request, $user, 'burst', [
                'burst' => $burst,
                'max_burst' => $maxBurst,
                'route' => $routeName,
            ]);
        }

        if ($minute > $maxMinute) {
            return $this->kick($request, $user, 'rate', [
                'minute' => $minute,
                'max_minute' => $maxMinute,
                'route' => $routeName,
            ]);
        }

        if ($isSensitive) {
            $sensitive = $this->hitWindow($this->sensitiveKey($uid), $now, 60);
            $maxSensitive = (int) config('bot_guard.max_sensitive_per_minute', 22);
            if ($sensitive > $maxSensitive) {
                return $this->kick($request, $user, 'sensitive', [
                    'sensitive' => $sensitive,
                    'max_sensitive' => $maxSensitive,
                    'route' => $routeName,
                ]);
            }
        }

        if ($isSearch) {
            $search = $this->hitWindow($this->searchKey($uid), $now, 60);
            $maxSearch = (int) config('bot_guard.max_search_per_minute', 12);
            if ($search > $maxSearch) {
                return $this->kick($request, $user, 'search', [
                    'search' => $search,
                    'max_search' => $maxSearch,
                    'route' => $routeName,
                ]);
            }
        }

        return null;
    }

    /**
     * Kick: logout session + temporary login lock + audit.
     */
    public function kick(Request $request, User $user, string $reason, array $meta = []): string
    {
        $minutes = max(1, (int) config('bot_guard.lock_minutes', 30));
        $uid = (int) $user->user_id;

        Cache::put($this->lockKey($uid), $reason, now()->addMinutes($minutes));
        Cache::put($this->lockKey($uid).':ttl_until', time() + ($minutes * 60), now()->addMinutes($minutes));

        $this->audit->log('bot_kick', $user, 'user', (string) $uid, array_merge([
            'reason' => $reason,
            'lock_minutes' => $minutes,
        ], $meta), $request);

        Log::warning('BotGuard kick', [
            'user_id' => $uid,
            'email' => $user->email,
            'reason' => $reason,
            'ip' => $request->ip(),
            'meta' => $meta,
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Добиваем remember-me и другие DB-сессии этого пользователя
        $this->sessions->invalidateUser($user);

        return $reason;
    }

    public function unlock(int $userId): void
    {
        Cache::forget($this->lockKey($userId));
        Cache::forget($this->lockKey($userId).':ttl_until');
    }

    /**
     * Sliding window counter in cache (list of timestamps).
     */
    private function hitWindow(string $key, int $now, int $windowSeconds): int
    {
        $hits = Cache::get($key, []);
        if (! is_array($hits)) {
            $hits = [];
        }

        $cutoff = $now - $windowSeconds;
        $hits = array_values(array_filter($hits, static fn ($t) => (int) $t >= $cutoff));
        $hits[] = $now;

        Cache::put($key, $hits, $windowSeconds + 5);

        return count($hits);
    }

    private function lockKey(int $userId): string
    {
        return 'bot_guard:lock:'.$userId;
    }

    private function burstKey(int $userId): string
    {
        return 'bot_guard:burst:'.$userId;
    }

    private function minuteKey(int $userId): string
    {
        return 'bot_guard:min:'.$userId;
    }

    private function sensitiveKey(int $userId): string
    {
        return 'bot_guard:sens:'.$userId;
    }

    private function searchKey(int $userId): string
    {
        return 'bot_guard:search:'.$userId;
    }
}
