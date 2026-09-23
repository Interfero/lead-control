<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCity
{
    /**
     * Проверка доступа пользователя к городу
     *
     * Использование: при работе с данными конкретного города
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $cityId = $request->route('city_id') ?? $request->input('city_id');
        
        if ($cityId && !$user->hasAccessToCity($cityId)) {
            abort(403, 'У вас нет доступа к этому городу');
        }
        
        return $next($request);
    }
}
