# ADR: Crypto for backups (Lead Control) — PoC result

**Дата:** 2026-07-19 (обновлено: GOST)  
**Статус:** Accepted for staging  
**Площадка PoC:** REG.RU VPS `194.67.92.69` (Ubuntu 22.04) — 2026-07-25; ранее Selectel `selectel-crm` (2026-07-19)

## Context

ТЗ §9.3.1 требует PoC AEGIS-256 + подпись манифеста ГОСТ Р 34.10-2012 до боевых бэкапов.

## Decision

| Слой | Выбор | Почему |
|------|--------|--------|
| AEAD архива | **AEGIS-256** через **libaegis** + CLI `aegis256-file` | PHP 8.4 Ondřej: sodium=1.0.18 → нет `sodium_crypto_aead_aegis256_*`. libaegis даёт AEGIS-256 на CPU VPS. |
| Оркестрация | PHP `tools/crypto-poc/run-poc.php` | Дамп → encrypt → decrypt → манифест → подпись |
| Fallback AEAD | AES-256-GCM (OpenSSL) | Если CLI недоступен |
| Подпись манифеста | **ГОСТ Р 34.10-2012 (256) + Стрибог (`md_gost12_256`)** | `gost-engine` **v3.0.3** под OpenSSL 3.0.2; conf: `/opt/lead-control-crypto-poc/openssl-gost.cnf` |

> Master gost-engine требует OpenSSL ≥ 3.4 — на Ubuntu 22.04 зафиксирован тег **v3.0.3**.

## PoC measurements

| Размер | encrypt AEGIS | decrypt+verify | sign GOST |
|--------|---------------|----------------|-----------|
| 32 MiB | ~113–130 ms | ~476–481 ms | ~39 ms (**Verified OK**) |
| 128 MiB | ~983 ms | ~2120 ms | (ранее HMAC; GOST smoke отдельно) |

Критерий ТЗ («минуты») — **выполнен**.

Артефакты: `/opt/lead-control-crypto-poc/out-gost/poc-report.json`.

## Staging ops (2026-07-19)

- App: `/var/www/lead-control-staging`, HTTP `:8081`
- Cron www-data: `schedule:run` каждую минуту
- Supervisor: `lead-control-staging-worker` (`queue:work redis`)

## Consequences

1. Боевые бэкапы на VPS: AEGIS-256 (CLI/Artisan) + GOST-подпись манифеста через `OPENSSL_CONF=…/openssl-gost.cnf`.
2. Ключи AEAD и GOST — вне архива, права 0600, ротация ≥ 12 мес.
3. Когда PHP/sodium ≥ 1.0.19 с AEGIS — можно убрать CLI.
4. Shared REG.RU без AEGIS до cutover.

## Follow-ups

- [x] GOST engine + Verified OK
- [x] Cron + Supervisor на staging
- [x] Artisan `backup:encrypt` + schedule `03:00` (smoke: AEGIS + GOST)
- [x] Artisan `backup:verify` (decrypt + gzip -t + подпись манифеста)
- [ ] Домен + SSL staging
- [ ] Dump shared → staging smoke / cutover DNS
