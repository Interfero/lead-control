<?php

namespace App\Services;

use App\Models\SecurityAlert;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Фиксация входа (любым способом: форма или SSO хаба): запись в журнал безопасности с регионом по IP
 * и уведомление, если вход из «особого» региона (по умолчанию Приморский край).
 */
class LoginSecurityService
{
    public const ACTION_LOGIN = 'login_success';

    public const ACTION_REGION_ALERT = 'login_region_alert';

    public function __construct(
        private SecurityAuditService $audit,
        private GeoIpService $geo,
        private OpsAlertService $ops,
    ) {}

    public function recordLogin(User $user, Request $request, string $via): void
    {
        $geo = $this->geo->lookup($request->ip());
        $this->audit->log(self::ACTION_LOGIN, $user, 'user', (string) $user->user_id, [
            'email' => $user->email,
            'via' => $via,
            'country' => $geo['country'] ?? null,
            'region' => $geo['region'] ?? null,
            'city' => $geo['city'] ?? null,
        ], $request);

        if ($this->geo->isAlertRegion($geo)) {
            $this->alertRegion($user, $request, $geo, $via);
        }
    }

    private function alertRegion(User $user, Request $request, array $geo, string $via): void
    {
        $throttle = max(1, (int) config('geoip.alert_throttle_minutes', 360));
        if (! Cache::add('login_region_alert:'.$user->user_id.':'.mb_strtolower((string) $geo['region']), 1, now()->addMinutes($throttle))) {
            return;
        }

        $place = trim(($geo['city'] ?? '').', '.($geo['region'] ?? ''), ', ');
        $message = sprintf('Вход в CRM из региона «%s»: %s (№%d)', $place, $user->user_name, $user->user_id);
        $meta = ['ip' => $request->ip(), 'via' => $via, 'country' => $geo['country'] ?? null,
            'region' => $geo['region'] ?? null, 'city' => $geo['city'] ?? null, 'user_agent' => substr((string) $request->userAgent(), 0, 200)];

        $this->audit->log(self::ACTION_REGION_ALERT, $user, 'user', (string) $user->user_id, $meta, $request);
        SecurityAlert::query()->create([
            'type' => self::ACTION_REGION_ALERT,
            'user_id' => $user->user_id,
            'message' => $message,
            'meta' => $meta,
            'created_at' => now(),
        ]);
        Log::channel('single')->warning('security: '.$message, $meta);
        $this->ops->notify('Вход из особого региона', $message.' · IP '.$request->ip());   // Telegram, если настроен; иначе только лог
    }
}
