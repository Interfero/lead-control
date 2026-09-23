<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GmUserSyncService
{
    public function credentials(User $user): array
    {
        $login = $this->normalizeLogin($user->email, $user->user_id);

        return [
            'login' => $login,
            'password' => $this->buildPassword($user),
        ];
    }

    public function sync(User $user): array
    {
        $baseUrl = (string) config('services.gm_api.base_url');
        $bearerToken = (string) config('services.gm_api.bearer_token');
        $path = (string) config('services.gm_api.user_sync_path', '/api/integrations/users/upsert');

        if ($baseUrl === '' || $bearerToken === '') {
            return [
                'ok' => false,
                'skipped' => true,
                'reason' => 'GM API is not configured.',
            ];
        }

        $user->loadMissing(['roles', 'cities']);
        $payload = $this->buildPayload($user);

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->asJson()
                ->withToken($bearerToken)
                ->timeout((int) config('services.gm_api.timeout', 10))
                ->withOptions([
                    'verify' => (bool) config('services.gm_api.verify_tls', true),
                ])
                ->post($path, $payload);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'reason' => $response->json('error') ?: $response->body(),
                    'payload' => $this->sanitizePayload($payload),
                ];
            }

            return [
                'ok' => true,
                'status' => $response->status(),
                'payload' => $this->sanitizePayload($payload),
                'response' => $response->json(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'reason' => $e->getMessage(),
                'payload' => $this->sanitizePayload($payload),
            ];
        }
    }

    public function syncMany(iterable $users): array
    {
        $results = [];
        $ok = 0;
        $failed = 0;

        foreach ($users as $user) {
            $result = $this->sync($user);
            $this->logResult($user, $result);

            if (($result['ok'] ?? false) === true) {
                $ok++;
            } else {
                $failed++;
            }

            $results[] = [
                'user_id' => $user->user_id,
                'email' => $user->email,
                'ok' => $result['ok'] ?? false,
                'status' => $result['status'] ?? null,
                'reason' => $result['reason'] ?? null,
                'gm_user_id' => $result['response']['user']['id'] ?? null,
                'gm_role' => $result['response']['user']['role'] ?? null,
            ];
        }

        return [
            'ok' => $failed === 0,
            'total' => $ok + $failed,
            'synced' => $ok,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    public function logResult(User $user, array $result): void
    {
        $context = [
            'user_id' => $user->user_id,
            'email' => $user->email,
            'gm_sync' => $result,
        ];

        if (($result['ok'] ?? false) === true) {
            Log::info('GM user sync succeeded.', $context);

            return;
        }

        Log::warning('GM user sync failed.', $context);
    }

    private function buildPayload(User $user): array
    {
        $primaryRole = $user->roles->sortByDesc(fn ($role) => $this->rolePriority($role->role_code))->first();
        $city = $user->cities->first()?->city_name;
        $credentials = $this->credentials($user);

        return [
            'city' => $city ?: 'Unknown',
            'email' => $credentials['login'],
            'externalId' => (string) $user->user_id,
            'isActive' => (bool) $user->is_active,
            'login' => $credentials['login'],
            'name' => $user->user_name ?: ($user->email ?: 'LC User #'.$user->user_id),
            'password' => $credentials['password'],
            'role' => $this->mapRole($primaryRole?->role_code),
            'title' => $primaryRole?->role_name ?: 'LC User',
        ];
    }

    private function buildPassword(User $user): string
    {
        $secret = (string) config('services.gm_api.password_secret', config('app.key', 'lc-gm-local-secret'));
        $prefix = (string) config('services.gm_api.password_prefix', 'GM');
        $fingerprint = strtolower(trim((string) $user->email)).'|'.$user->user_id;
        $hash = strtoupper(substr(hash_hmac('sha256', $fingerprint, $secret), 0, 10));

        return $prefix.$hash.'!';
    }

    private function normalizeLogin(?string $email, int|string $userId): string
    {
        $normalized = strtolower(trim((string) $email));

        if ($normalized !== '' && str_contains($normalized, '@')) {
            return $normalized;
        }

        return 'lc-user-'.$userId.'@lc.ru';
    }

    private function sanitizePayload(array $payload): array
    {
        if (array_key_exists('password', $payload)) {
            $payload['password'] = '***';
        }

        return $payload;
    }

    public function mapRole(?string $roleCode): string
    {
        return match ($roleCode) {
            'developer' => 'ADMIN',
            'general_director' => 'GENERAL_DIRECTOR',
            'regional_director' => 'REGIONAL_DIRECTOR',
            'tech_director' => 'DIRECTOR',
            'call_center', 'senior_dispatcher' => 'DISPATCHER',
            'senior_master' => 'SENIOR_MASTER',
            default => 'MASTER',
        };
    }

    private function rolePriority(?string $roleCode): int
    {
        return match ($roleCode) {
            'developer' => 700,
            'general_director' => 600,
            'regional_director' => 500,
            'tech_director' => 400,
            'senior_dispatcher' => 300,
            'call_center' => 200,
            'senior_master' => 150,
            'master' => 100,
            default => 0,
        };
    }
}
