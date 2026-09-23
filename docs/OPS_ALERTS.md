# Ops-алерты (§6.5, минимум)

## Поведение

| Событие | Команда | Действие |
|---------|---------|----------|
| Health degraded | `ops:health-check` (каждые 5 мин) | log + **Telegram** (если настроен) |
| Бэкап не создался | `backup:encrypt` | log + Telegram |
| Бэкап не прошёл verify | `backup:verify` (03:10) | log + Telegram |

## .env

```env
OPS_ALERT_TELEGRAM_BOT_TOKEN=
OPS_ALERT_TELEGRAM_CHAT_ID=
```

Пустые значения — только запись в `storage/logs/laravel.log` (как раньше).

## Настройка Telegram

1. Создать бота через @BotFather → токен в `OPS_ALERT_TELEGRAM_BOT_TOKEN`.
2. Узнать `chat_id` (личный или группа) → `OPS_ALERT_TELEGRAM_CHAT_ID`.
3. На prod/staging: `php artisan config:clear` после правки `.env`.
4. Тест: временно сломать порог (или `ops:health-check` на копии с пустой БД) — **лучше** отправить вручную:

```bash
php artisan tinker --execute="app(\App\Services\OpsAlertService::class)->notify('test', 'Lead Control alert OK');"
```

См. `config/ops.php`.
