<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Последний вход и последняя активность сотрудников (по журналу безопасности и сессиям). */
class UserLastLoginService
{
    public function __construct(private GeoIpService $geo) {}

    /**
     * @param  iterable<int>  $userIds
     * @return array<int, array{login_at: ?Carbon, ip: ?string, place: ?string, device: ?string, activity_at: ?Carbon}>
     */
    public function forUsers(iterable $userIds): array
    {
        $ids = collect($userIds)->map(fn ($i) => (int) $i)->unique()->values()->all();
        if ($ids === [] || ! Schema::hasTable('security_audit_logs')) {
            return [];
        }

        $latestIds = SecurityAuditLog::query()->where('action', LoginSecurityService::ACTION_LOGIN)
            ->whereIn('user_id', $ids)->selectRaw('max(id) as id')->groupBy('user_id')->pluck('id');
        $logins = SecurityAuditLog::query()->whereIn('id', $latestIds)->get()->keyBy('user_id');

        $activity = Schema::hasTable('sessions')
            ? DB::table('sessions')->whereIn('user_id', $ids)->selectRaw('user_id, max(last_activity) as last_activity')
                ->groupBy('user_id')->pluck('last_activity', 'user_id')
            : collect();

        $result = [];
        foreach ($ids as $id) {
            $log = $logins->get($id);
            $act = $activity->get($id);
            $result[$id] = [
                'login_at' => $log?->created_at,
                'ip' => $log?->ip,
                'place' => $log ? $this->place($log) : null,
                'device' => $log ? $this->device((string) $log->user_agent) : null,
                'activity_at' => $act ? Carbon::createFromTimestamp((int) $act) : null,
            ];
        }

        return $result;
    }

    private function place(SecurityAuditLog $log): ?string
    {
        $meta = (array) $log->meta;
        $geo = isset($meta['region']) || isset($meta['city'])
            ? $meta
            : ($this->geo->lookup($log->ip) ?? []);
        $parts = array_filter([$geo['city'] ?? null, $geo['region'] ?? null, $geo['country'] ?? null]);

        return $parts ? implode(', ', array_unique($parts)) : null;
    }

    private function device(string $ua): ?string
    {
        if ($ua === '') {
            return null;
        }
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'YaBrowser') => 'Яндекс',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Chrome') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari') => 'Safari',
            default => null,
        };

        return implode(' · ', array_filter([$os, $browser])) ?: null;
    }
}
