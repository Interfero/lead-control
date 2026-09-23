<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// TZ outbox → SuperPart (локально/после согласования деплоя).
Schedule::command('superpart:deliver-outbox --limit=80')
    ->everyMinute()
    ->withoutOverlapping();

// Догон CRM→SuperPart (страховка от забытых webhook на create)
Schedule::command('superpart:catch-up-orders --hours=72 --limit=100')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Ночной пересчёт срезов отчётов + догон stale (ТЗ §6.4)
Schedule::command('reports:rebuild-city-daily --stale --limit=500')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('reports:rebuild-city-daily')
    ->dailyAt('02:30')
    ->withoutOverlapping();

// Очередь outbound (SuperPart / GM) на shared: разбор jobs раз в минуту
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

// Единое окно: фоновая синхронизация кэша заказов (CRM1)
Schedule::command('desk:sync')
    ->everyMinute()
    ->withoutOverlapping();

// Единый хаб: обновление карты соответствий мастеров KP-Lead ↔ пользователей CRM
Schedule::command('hub:link-masters')
    ->hourly()
    ->withoutOverlapping();

// Мониторинг §6.5 — лог при degraded
Schedule::command('ops:health-check')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Чистка CSV-экспортов отчётов (ТЗ §6.2 очередь)
Schedule::command('reports:cleanup-exports --days=2')
    ->dailyAt('03:15')
    ->withoutOverlapping();

// Ночной бэкап БД: AEGIS + ГОСТ-манифест (ТЗ §9.3 / ADR-crypto-backup)
Schedule::command('backup:encrypt')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('backup:verify')
    ->dailyAt('03:10')
    ->withoutOverlapping();
