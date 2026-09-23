<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Вход в CRM из хаба проектов (SSO code).
 */
class HubSsoController extends Controller
{
    public function callback(Request $request)
    {
        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->away(rtrim((string) config('services.hub.public_url'), '/').'/login');
        }

        $payload = Cache::pull('hub_sso:'.$code);
        if (! is_array($payload) || empty($payload['user_id'])) {
            return redirect()->away(rtrim((string) config('services.hub.public_url'), '/').'/login')
                ->with('error', 'SSO-код недействителен или истёк');
        }

        $user = User::query()->find($payload['user_id']);
        if (! $user || ! $user->is_active || $user->is_blacklisted) {
            return redirect()->away(rtrim((string) config('services.hub.public_url'), '/').'/login');
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('two_factor_passed', true);

        return redirect()->route('orders.index');
    }
}
