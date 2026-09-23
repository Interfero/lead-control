<?php

use App\Support\TrustedProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        using: function () {
            Route::middleware('web')
                ->prefix('crm')
                ->group(base_path('routes/web.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TRUSTED_PROXIES=ip1,ip2 или * (shared hosting / VPS behind proxy).
        $middleware->trustProxies(at: TrustedProxies::resolve());
        $middleware->web(replace: [
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
        ]);
        $middleware->redirectGuestsTo(function () {
            return rtrim((string) (config('services.hub.public_url') ?: env('HUB_PUBLIC_URL', 'https://lead-control.space')), '/').'/login';
        });
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            // Именованные throttle:* ломаются со стандартным классом при route:cache (Laravel func_num_args)
            'throttle' => \App\Http\Middleware\ThrottleRequestsFixed::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'city' => \App\Http\Middleware\CheckCity::class,
            'superpart.api' => \App\Http\Middleware\VerifySuperPartApi::class,
            '2fa' => \App\Http\Middleware\EnsureTwoFactorSatisfied::class,
            'bot.guard' => \App\Http\Middleware\DetectBotActivity::class,
            'desk.api' => \App\Http\Middleware\VerifyDeskApiBearer::class,
        ]);
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\EnforceAbsoluteSessionTimeout::class,
            \App\Http\Middleware\EnsureTwoFactorSatisfied::class,
            \App\Http\Middleware\DetectBotActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Обработка ошибки 419 - редирект на login без показа ошибки
        $exceptions->render(function (TokenMismatchException $e) {
            $hub = rtrim((string) config('services.hub.public_url'), '/');

            return redirect()->away($hub.'/login')
                ->with('warning', 'Сессия истекла. Пожалуйста, войдите снова.');
        });
    })->create();
