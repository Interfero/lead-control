# Security Stage E (без AEGIS)

## 2FA

Принудительная 2FA **сейчас выключена** (`TWO_FACTOR_ENFORCE` по умолчанию `false`).

Включить снова:

```
TWO_FACTOR_ENFORCE=true
```

в `.env` на проде + `php artisan config:clear`.

Код (TOTP, маршруты, middleware, настройки) остаётся на месте.

## Что включено всегда

| Механизм | Деталь |
|----------|--------|
| Lockout входа | 5 неудач → блок 15 мин (email+IP), плюс throttle 10/мин |
| Audit | `security_audit_logs`: login_*, two_factor_*, export_csv, logout |
| Mango webhook | пустой secret в production → отказ |

Crypto AEGIS/ГОСТ — этап F / PoC на VPS (ТЗ §9.3.1).

## Прод .env (рекомендуется)

```
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
# TWO_FACTOR_ENFORCE=true   # когда вернёмся к 2FA
# HASH_DRIVER=argon2id
```
