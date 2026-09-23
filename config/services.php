<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mango Office (IP-телефония)
    |--------------------------------------------------------------------------
    |
    | Настройки интеграции с виртуальной АТС Mango Office.
    | Получить ключи можно в личном кабинете Mango Office:
    | Настройки → Интеграция → API
    |
    */
    'mango' => [
        'api_url' => env('MANGO_API_URL', 'https://app.mango-office.ru/vpbx'),
        'api_key' => env('MANGO_API_KEY', ''),
        'api_salt' => env('MANGO_API_SALT', ''),
        // В production без MANGO_WEBHOOK_SECRET вебхук не обрабатывается (защита от посторонних POST).
        'webhook_secret' => env('MANGO_WEBHOOK_SECRET', ''),
        // Временный fallback DID→source_id (канон — sources.source_phone + source_phone_aliases).
        // В .env: MANGO_LINE_1_SOURCE_ID=5. Использование логируется как warning.
        'line_sources' => [
            '1' => (int) env('MANGO_LINE_1_SOURCE_ID', 0),
            '2' => (int) env('MANGO_LINE_2_SOURCE_ID', 0),
            '101' => (int) env('MANGO_LINE_101_SOURCE_ID', 0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SuperPart (партнёрский портал)
    |--------------------------------------------------------------------------
    |
    | SERVER-TO-SERVER: SuperPart вызывает CRM с X-API-Key + X-Signature;
    | CRM вызывает webhook'и на SUPERPART_BASE_URL с тем же ключом и секретом.
    | SUPERPART_ORDER_AUTHOR_USER_ID — пользователь CRM (order_created_by) для заказов из API.
    | SUPERPART_DEFAULT_SOURCE_ID — источник для входящих партнёрских заказов.
    |
    */
    'superpart' => [
        'api_key' => env('SUPERPART_API_KEY', ''),
        'api_secret' => env('SUPERPART_API_SECRET', ''),
        // С VPS прямой HTTPS на testir.space режется — мост promo-agent.ru/sp-lc-gw/sp
        // Пока SuperPart на другом хосте — мост. После переезда на этот VPS: https://<домен-портала>
        // или http://127.0.0.1, запасной URL — мост.
        'base_url' => rtrim((string) env('SUPERPART_BASE_URL', ''), '/'),
        'fallback_url' => rtrim((string) env('SUPERPART_FALLBACK_URL', ''), '/'),
        'api_per_minute' => (int) env('SUPERPART_API_PER_MINUTE', 300),
        'order_author_user_id' => (int) env('SUPERPART_ORDER_AUTHOR_USER_ID', 0),
        'default_source_id' => (int) env('SUPERPART_DEFAULT_SOURCE_ID', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Guild of Masters API
    |--------------------------------------------------------------------------
    */
    'gm_api' => [
        'service_name' => env('GM_API_SERVICE_NAME', 'lead-control'),
        'base_url' => rtrim((string) env('GM_API_BASE_URL', ''), '/'),
        'bearer_token' => env('GM_API_BEARER_TOKEN', ''),
        'order_upsert_path' => env('GM_API_ORDER_UPSERT_PATH', '/api/integrations/orders/upsert'),
        'user_sync_path' => env('GM_API_USER_SYNC_PATH', '/api/integrations/users/upsert'),
        'password_secret' => env('GM_API_PASSWORD_SECRET', env('APP_KEY', 'lc-gm-local-secret')),
        'password_prefix' => env('GM_API_PASSWORD_PREFIX', 'GM'),
        'timeout' => (int) env('GM_API_TIMEOUT_SECONDS', 10),
        'verify_tls' => filter_var(env('GM_API_VERIFY_TLS', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Единое окно (отдельное приложение lead-desk)
    |--------------------------------------------------------------------------
    */
    'desk_api' => [
        'bearer_token' => env('DESK_API_TOKEN', ''),
        'public_url' => rtrim((string) env('DESK_PUBLIC_URL', ''), '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Хаб проектов (единый логин)
    |--------------------------------------------------------------------------
    */
    'hub' => [
        'public_url' => rtrim((string) env('HUB_PUBLIC_URL', 'https://lead-control.space'), '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Единый хаб заказов: агрегация LC + KP-Lead внутри Lead Control
    |--------------------------------------------------------------------------
    |
    | Lead Control читает кэш заказов Единого окна (lead-desk) и отдаёт GM уже
    | объединённую выборку через /api/v1/gm/*. GM никогда не ходит в Desk сам
    | и не получает DESK_API_TOKEN.
    |
    */
    'unified_hub' => [
        'enabled' => filter_var(env('UNIFIED_HUB_ENABLED', true), FILTER_VALIDATE_BOOL),
        'connection' => env('UNIFIED_HUB_CONNECTION', 'hub'),
        // Часовой пояс приложения Единого окна: серверные метки (last_synced_at,
        // crm_connections.last_sync_at) пишутся в нём, а не в TZ Lead Control.
        'timezone' => env('UNIFIED_HUB_TIMEZONE', 'UTC'),
        // crm_connections.id в lead-desk
        'lc_crm_id' => (int) env('UNIFIED_HUB_LC_CRM_ID', 1),
        'kp_crm_id' => (int) env('UNIFIED_HUB_KP_CRM_ID', 2),
        // Сколько минут без успешной синхронизации считать источник «протухшим»
        'stale_after_minutes' => (int) env('UNIFIED_HUB_STALE_MINUTES', 180),
        // Запись статусов KP: http → Desk internal API; cache → только локальный кэш (тесты)
        'write_driver' => env('UNIFIED_HUB_WRITE_DRIVER', 'http'),
        'write_url' => rtrim((string) env('UNIFIED_HUB_WRITE_URL', env('DESK_PUBLIC_URL', 'https://lead-control.space')), '/'),
        'write_token' => (string) env('UNIFIED_HUB_WRITE_TOKEN', env('DESK_INTERNAL_TOKEN', '')),
        'write_timeout' => (int) env('UNIFIED_HUB_WRITE_TIMEOUT', 45),
    ],

];
