<?php

/**
 * Защита от ботов, выкачивающих данные CRM (телефоны клиентов и т.п.).
 *
 * Пороги с запасом под легитимный UI: poll уведомлений ~1 раз / 3 с,
 * навигация по заказам. Боты сыпят запросами чаще — kick + временный lock.
 */
return [

    'enabled' => (bool) env('BOT_GUARD_ENABLED', true),

    /** Не кикать роль developer (отладка). Остальные — под guard. */
    'exempt_roles' => array_filter(array_map('trim', explode(',', (string) env('BOT_GUARD_EXEMPT_ROLES', 'developer')))),

    /** Все authenticated web-запросы (кроме exclude). */
    'max_per_minute' => (int) env('BOT_GUARD_MAX_PER_MINUTE', 90),

    /** Короткий burst: больше — почти наверняка бот. */
    'max_per_10_seconds' => (int) env('BOT_GUARD_MAX_PER_10S', 28),

    /**
     * Карточки / поиск с телефонами клиентов.
     * Обычный диспетчер редко открывает >15–20 карточек в минуту подряд.
     */
    'max_sensitive_per_minute' => (int) env('BOT_GUARD_MAX_SENSITIVE_PER_MINUTE', 22),

    /** AJAX-поиск персон (JSON с полными номерами) — самый лакомый эндпоинт. */
    'max_search_per_minute' => (int) env('BOT_GUARD_MAX_SEARCH_PER_MINUTE', 12),

    /** Минут блокировки входа после kick. */
    'lock_minutes' => (int) env('BOT_GUARD_LOCK_MINUTES', 30),

    /**
     * Route names, не считающиеся в общем лимите (poll / служебные).
     * Sensitive-лимиты на них не вешаются.
     */
    'exclude_route_names' => [
        'csrf-token',
        'notifications.unseen-city-orders-count',
        'notifications.unseen-complaints-count',
        'logout',
        'two-factor.challenge',
        'two-factor.challenge.verify',
        'two-factor.setup',
        'two-factor.setup.confirm',
    ],

    /** Route names с телефонами / ПДн — отдельный счётчик. */
    'sensitive_route_names' => [
        'persons.show',
        'persons.search',
        'persons.call',
        'persons.phones.reveal',
        'orders.show',
        'orders.data',
        'orders.create',
        'complaints.show',
        'prom.journal.appointments',
        'prom.journal.meetings',
    ],

    'search_route_names' => [
        'persons.search',
        'persons.phones.reveal',
    ],
];
