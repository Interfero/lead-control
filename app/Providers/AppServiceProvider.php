<?php

namespace App\Providers;

use App\Contracts\AtsProviderInterface;
use App\Services\MangoApiService;
use App\Services\ComplaintNotificationService;
use App\Services\NavigationService;
use App\Services\OrderCityViewService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Провайдер АТС: при смене телефонии заменить MangoApiService на другую реализацию AtsProviderInterface
        $this->app->bind(AtsProviderInterface::class, MangoApiService::class);

        // Именованный throttle:* + route:cache: стандартный ThrottleRequests падает с 500
        $this->app->bind(
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \App\Http\Middleware\ThrottleRequestsFixed::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Генерация URL: роуты уже с prefix crm → APP_URL без /crm
        $root = rtrim((string) config('app.url'), '/');
        if ($root !== '' && str_starts_with($root, 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // HMAC SuperPart: не делить ведро с анонимным 60/мин — иначе сверка душит создание источников.
        RateLimiter::for('superpart-api', function (Request $request) {
            $apiKey = (string) $request->header('X-API-Key', '');
            $bucket = $apiKey !== ''
                ? 'sp-key:'.hash('sha256', $apiKey)
                : 'sp-ip:'.$request->ip();

            return Limit::perMinute((int) config('services.superpart.api_per_minute', 300))->by($bucket);
        });

        RateLimiter::for('login', function (Request $request) {
            // Только по email: общий VPN/NAT у партнёров не должен блокировать вход всем сразу.
            $key = strtolower((string) $request->input('email', ''));

            return Limit::perMinute(20)->by('login:'.$key);
        });

        // GM server-to-server проверка пароля (брутфорс)
        RateLimiter::for('gm-verify-password', function (Request $request) {
            $login = strtolower(trim((string) ($request->input('login', $request->input('email', '')))));

            return [
                Limit::perMinute(20)->by('gm-verify-ip:'.$request->ip()),
                Limit::perMinute(10)->by('gm-verify-login:'.$login.'|'.$request->ip()),
            ];
        });

        // GM server-to-server смена пароля (брутфорс currentPassword)
        RateLimiter::for('gm-change-password', function (Request $request) {
            $userId = (int) $request->input('userId', 0);
            $login = strtolower(trim((string) $request->input('login', '')));
            $subject = $userId > 0 ? 'uid:'.$userId : 'login:'.$login;

            return Limit::perMinutes(15, 5)->by('gm-change-pwd:'.$subject.'|'.$request->ip());
        });

        // Поиск клиентов (JSON с телефонами) — узкое горло против скрейперов
        RateLimiter::for('persons-search', function (Request $request) {
            $id = optional($request->user())->user_id ?: $request->ip();

            return Limit::perMinute((int) config('bot_guard.max_search_per_minute', 12))->by('persons-search:'.$id);
        });

        // Карточки заказов / клиентов
        RateLimiter::for('client-cards', function (Request $request) {
            $id = optional($request->user())->user_id ?: $request->ip();

            return Limit::perMinute((int) config('bot_guard.max_sensitive_per_minute', 22))->by('client-cards:'.$id);
        });

        // Устанавливаем длину строк по умолчанию для MySQL с utf8mb4
        // Это позволяет использовать unique() индексы на VARCHAR полях
        Builder::defaultStringLength(191);

        // Использовать Bootstrap-стиль для пагинации
        Paginator::useBootstrapFive();

        // Передаём меню во все views
        View::composer('layouts.app', function ($view) {
            if (auth()->check()) {
                $user = auth()->user();
                $navService = new NavigationService;
                $view->with('navigation', $navService->getMenuForUser($user));

                $unseenCityOrdersCount = null;
                if ($user->hasAnyRole(OrderCityViewService::NOTIFY_ROLES)) {
                    $unseenCityOrdersCount = app(OrderCityViewService::class)->countUnseenByCityStaff($user);
                }
                $view->with('unseenCityOrdersCount', $unseenCityOrdersCount);

                $unseenComplaintsCount = null;
                if ($user->hasAnyRole(ComplaintNotificationService::NOTIFY_ROLES)) {
                    $unseenComplaintsCount = app(ComplaintNotificationService::class)->countUnseen($user);
                }
                $view->with('unseenComplaintsCount', $unseenComplaintsCount);
            }
        });
    }
}
