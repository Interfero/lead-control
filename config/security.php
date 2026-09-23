<?php

return [
    /*
    | Обязательная 2FA для developer / general_director (ТЗ §9.5).
    | Пока выключено — включить: TWO_FACTOR_ENFORCE=true в .env
    */
    'two_factor_enforce' => (bool) env('TWO_FACTOR_ENFORCE', false),

    /*
    | Опциональный токен для GET /api/v1/ops/health (заголовок X-Ops-Token или ?token=).
    | Пусто = публичный endpoint (только статус, без секретов).
    */
    'ops_health_token' => env('OPS_HEALTH_TOKEN', ''),

    /*
    | Требовать MANGO_WEBHOOK_SECRET в production для общего статуса health.
    | false — пока телефония отложена: проверка остаётся в checks, но не делает status=degraded.
    */
    'ops_health_require_mango' => (bool) env('OPS_HEALTH_REQUIRE_MANGO', false),
];
