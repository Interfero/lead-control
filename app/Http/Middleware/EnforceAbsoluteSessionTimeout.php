<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAbsoluteSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $startedAt = $request->session()->get('auth_started_at');
            if (! is_numeric($startedAt)) {
                $request->session()->put('auth_started_at', time());
            } else {
                $maxSeconds = (int) config('session.absolute_lifetime', 60 * 24 * 7) * 60;
                if ($maxSeconds > 0 && (time() - (int) $startedAt) > $maxSeconds) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    $hub = rtrim((string) (config('services.hub.public_url') ?: env('HUB_PUBLIC_URL', '')), '/');
                    $login = $hub !== '' ? $hub.'/login' : '/login';

                    if ($request->expectsJson()) {
                        return response()->json(['message' => 'Сессия истекла. Войдите снова.'], 401);
                    }

                    return redirect()->away($login);
                }
            }
        }

        return $next($request);
    }
}
