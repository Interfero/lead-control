<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * SSO для отдельного приложения Единое окно (вариант B).
 * Пользователь уже залогинен в CRM1 → одноразовый code → desk.
 */
class DeskSsoController extends Controller
{
    public function redirect(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->away(hub_login_url());
        }

        if (! $user->hasAnyRole([
            'developer', 'call_center', 'senior_dispatcher', 'senior_manager',
            'tech_director', 'branch_head', 'regional_director', 'general_director',
        ])) {
            abort(403, 'Нет доступа к Единому окну');
        }

        $deskUrl = rtrim((string) config('services.desk_api.public_url'), '/');
        if ($deskUrl === '') {
            abort(503, 'DESK_PUBLIC_URL не настроен');
        }

        $code = Str::random(64);
        Cache::put('desk_sso:'.$code, [
            'user_id' => $user->user_id,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(2));

        $target = $deskUrl.'/sso/callback?code='.urlencode($code);

        return redirect()->away($target);
    }
}
