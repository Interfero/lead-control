# Этап F: REG.RU VPS + мониторинг (ТЗ §6.5 / §9.8)

**Цель по ТЗ:** prod на **VPS REG.RU в РФ**. Selectel — не целевая площадка.

**Прод сейчас:** ✅ REG.RU VPS `194.67.92.69` (`/var/www/lead-control`, cutover 2026-07-27 re-sync; DNS A → VPS подтверждён)  
**Shared (fallback ≤14 дн.):** `31.31.197.5` · путь `www/lead-control.space.app`  
**VPS:** SSH `reg-vps` · Ubuntu 22.04 · 8 vCPU / 8 GB / 119 GB · LE SSL до ~2026-10-24

Заказ: `docs/VPS-ORDER-SPEC.md` · provision: `deploy/vps/provision-regru-ubuntu.sh` · cutover: `docs/CUTOVER-DAY-X-RUNBOOK.md`.

---

## На shared (до cutover / fallback)

### Cron

```cron
* * * * * cd /var/www/u3398705/data/www/lead-control.space.app && php artisan schedule:run >> /dev/null 2>&1
```

В расписании: `queue:work --stop-when-empty`, `reports:rebuild-city-daily`, `ops:health-check`.

### Health API

- `GET /api/v1/ops/health` — БД, диск, очередь, stale-срезы, mango secret.
- Опционально: `OPS_HEALTH_TOKEN`; Mango: `OPS_HEALTH_REQUIRE_MANGO` (по умолчанию false).

```bash
php artisan ops:health-check --json
curl -sS https://lead-control.space/api/v1/ops/health
```

### Алерты §6.5

Код готов (`docs/OPS_ALERTS.md`). Без `OPS_ALERT_TELEGRAM_*` — только лог. На VPS включить после токенов.

---

## Чеклист VPS (REG.RU)

Срок по ТЗ: **≤ 60 дней** от старта этапа A.

### 1. Хост и runtime

- [x] VPS REG.RU в РФ заказан, SSH по ключу (`194.67.92.69`, `reg-vps`)
- [x] `provision-regru-ubuntu.sh`: Nginx, PHP 8.2, MariaDB, Redis, UFW 22/80/443
- [x] Деплой Laravel prod path `/var/www/lead-control` + DNS cutover (2026-07-26)
- [x] Cron `schedule:run` + Supervisor worker RUNNING
- [x] Staging на том же VPS: `http://194.67.92.69:8081/` (UFW 8081)

### 2. Данные и PoC

- [x] Dump shared → restore на VPS (2026-07-25: 205 KB SQL + 393 MB storage)
- [x] `storage/app` синхронизирован; staging `.env` со своим URL/DB
- [x] `ops:health-check` → ok; login HTTP 200; users=138 orders=239
- [x] `backup:encrypt` + `backup:verify` на этом VPS — **AEGIS-256 + ГОСТ Р 34.10-2012** (2026-07-25, `aegis-gost-202607251343`)
- [x] Smoke: логин/health с боевыми данными
- [x] Post-cutover backup: `post-cutover-202607262324` (encrypt+verify ok)

### 3. Cutover (§10.2 ш.7)

- [x] DNS A → `194.67.92.69`; AAAA снят (auth NS + публичные резолверы)
- [x] SSL DNS-01 до смены A; HTTPS 200 на VPS; health ok
- [ ] Mango webhook: тот же URL — smoke звонком/логом после пропагации DNS
- [ ] Shared read-only ≤ 14 дней (с 2026-07-26)
- [ ] Алерты Telegram/email на VPS

### 4. Perf note (2026-07-26/27)

OpenStack VPS за DNAT: MTU 1500 → **PMTU blackhole**, CSS/JS обрывались ~16 KB. Фикс: MTU **1400** (`/etc/netplan/99-mtu.yaml`), `tcp_mtu_probing`, nginx `sendfile off` + gzip_types + cache `/build/`, opcache validate_timestamps=0, MariaDB buffer 1G, php-fpm pm↑.

После cutover `SESSION_DRIVER=redis` ломал cookie с shared (`database`) → 401 на poll/csrf. Вернули **`SESSION_DRIVER=database`** (APP_KEY совпадает, в `sessions` 40 записей).

### 5. Откат

DNS назад на shared + dump с шага бэкапа при необходимости.

---

## Связанные документы

- `docs/TZ-ACTION-PLAN.md` — блок 2
- `docs/CUTOVER-PLAN.md`
- `docs/SYNC-PROD-TO-STAGING.md` (хост staging → `reg-vps`)
- `docs/QUEUE_OUTBOUND.md`
- `docs/SECURITY_STAGE_E.md`
