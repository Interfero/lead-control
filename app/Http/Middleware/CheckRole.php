<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Проверка наличия у пользователя одной из указанных ролей
     *
     * Использование в routes:
     * ->middleware('role:developer,branch_head')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return redirect()->away(hub_login_url());
        }
        
        // Разработчик имеет доступ везде
        if ($user->hasRole('developer')) {
            return $next($request);
        }
        
        // Проверяем наличие любой из указанных ролей
        if ($user->hasAnyRole($roles)) {
            return $next($request);
        }
        
        // Нет доступа
        abort(403, 'У вас нет доступа к этой странице');
    }
}
