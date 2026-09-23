<?php

namespace App\Http\Middleware;

use App\Services\BotGuardService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kick ботов: частые запросы / массовый доступ к телефонам → logout + временный lock.
 */
class DetectBotActivity
{
    public function __construct(
        private BotGuardService $botGuard,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (! $user) {
            return $next($request);
        }

        $reason = $this->botGuard->inspect($request, $user);
        if ($reason === null) {
            return $next($request);
        }

        // locked/inactive приходят без kick() — всё равно рвём сессию
        if (in_array($reason, ['locked', 'inactive'], true) && Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $message = match ($reason) {
            'locked' => 'Аккаунт временно заблокирован из‑за подозрительной активности. Попробуйте позже или обратитесь к руководителю.',
            'inactive' => 'Учётная запись отключена или в чёрном списке.',
            'burst', 'rate' => 'Слишком частые запросы. Сессия завершена. Повторный вход будет доступен через некоторое время.',
            'sensitive', 'search' => 'Слишком много обращений к данным клиентов. Сессия завершена для защиты базы.',
            default => 'Сессия завершена из‑за подозрительной активности.',
        };

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
                'bot_kick' => true,
                'reason' => $reason,
            ], 429);
        }

        return redirect()
            ->away(hub_login_url())
            ->with('error', $message);
    }
}
