# Единое окно заказов (тестовая версия)

Модуль внутри Lead Control: `/desk`. Только заказы, без кассы.

## Что умеет v0 (CRM1)

- Кэш активных заказов CRM1 (`orders_cache`) через Eloquent
- Список с фильтрами, счётчиками, индикаторами CRM
- Модалка: статус, оплачено, запчасти, комментарий → write-back в CRM1
- Закрытие → `completed` в CRM1, удаление из кэша
- Кнопка «Синхронизировать», крон `desk:sync` раз в минуту
- Логи `/desk/logs` (developer)
- CRM2 — заглушка (`inactive`)

## Деплой на VPS

```bash
cd /var/www/lead-control
php artisan migrate --force
php artisan db:seed --class=DeskSeeder --force
php artisan desk:sync --full
php artisan optimize:clear
# при необходимости: systemctl restart php8.2-fpm
```

Открыть: `https://lead-control.space/desk` (под своей сессией CRM).

## Дальше

1. HTTP-адаптер CRM2 + маппинг городов
2. Загрузка документов из модалки
3. Поддомен `desk.lead-control.space` (тот же app, `SESSION_DOMAIN`)
