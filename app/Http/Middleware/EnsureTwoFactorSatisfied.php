<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Для ролей с обязательной 2FA: после пароля нужен TOTP (или настройка).
 */
class EnsureTwoFactorSatisfied
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->requiresTwoFactor()) {
            return $next($request);
        }

        if ($request->routeIs([
            'two-factor.*',
            'logout',
            'csrf-token',
            'login',
        ])) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()
                ->route('two-factor.setup')
                ->with('warning', 'Для вашей роли нужно включить двухфакторную аутентификацию.');
        }

        if (! $request->session()->get('two_factor_passed')) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
