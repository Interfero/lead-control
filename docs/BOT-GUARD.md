# BotGuard — защита от скрейпинга телефонов / kick ботов

## Что сделано (база)

1. **Middleware `DetectBotActivity`** на всех `web`-запросах после логина.
2. Счётчики в Redis/Cache на пользователя:
   - burst: >28 запросов / 10 с → kick
   - общий: >90 / мин → kick
   - sensitive (карточки заказов/клиентов): >22 / мин → kick
   - `persons.search` (JSON с номерами): >12 / мин → kick
3. **Kick** = logout + invalidate session + временный lock входа (30 мин по умолчанию) + запись `bot_kick` в `security_audit_logs`.
4. Laravel `throttle:persons-search` / `throttle:client-cards` — второй слой.
5. Вход запрещён при active bot-lock / blacklist.
6. Poll уведомлений и `/csrf-token` **не** считаются в лимитах.

## Конфиг (.env на VPS)

```env
BOT_GUARD_ENABLED=true
BOT_GUARD_EXEMPT_ROLES=developer
BOT_GUARD_MAX_PER_MINUTE=90
BOT_GUARD_MAX_PER_10S=28
BOT_GUARD_MAX_SENSITIVE_PER_MINUTE=22
BOT_GUARD_MAX_SEARCH_PER_MINUTE=12
BOT_GUARD_LOCK_MINUTES=30
```

## Разблокировка

```bash
cd /var/www/lead-control
sudo -u www-data php artisan bot-guard:unlock EMAIL_OR_USER_ID
```

## Дальше (прокачка)

- ~~Маскировка телефонов в UI~~ — `x-phone-masked`: полный номер сразу у ролей `PHONE_FULL_ROLES` (по умолчанию developer/КЦ/ст.диспетчер); остальные — маска + клик → `GET /persons/phones/{id}/reveal` (+ audit `phone_reveal`). Телефоны также убраны из JSON `/persons/search`.
- ~~Kill sessions on fire/blacklist~~ — `SessionInvalidationService`
- Fingerprint сессии (User-Agent + IP drift)
- Авто-blacklist после N kick подряд
- CAPTCHA на login при lock
- Cloudflare / WAF поверх (для VPN/сканеров без учётки)
- Ужесточить GM API `clientPhone` / pageSize (отдельный контур Bearer)

## Важно

DDoS на уровне VPS не заменяет kick: боты ходят **под чужими логинами**. Этот слой режет именно сессионный скрейпинг.
