# Sync: prod (shared) → staging на REG.RU VPS

Цель: staging с **боевыми данными** для приёмки отчётов и cutover (§10.2, `CUTOVER-PLAN.md`).  
Целевой SSH-хост staging: **`reg-vps`** (REG.RU VPS по ТЗ §9.8). Selectel не использовать для нового sync.

## Важно

- На staging **сохраняется свой** `APP_KEY` в `.env` (не копировать prod `.env` целиком).
- После restore: `php artisan migrate --force`.
- Секреты Mango/интеграций на staging — по желанию (можно пустые для отчётов).

## Автоматически (Windows / с VPS)

Предпочтительно: **VPS тянет файлы с shared** (ключ VPS в `~/.ssh/authorized_keys` на `reg-crm`).

Скрипты: `deploy/vps/dump-prod-db.php`, `deploy/vps/restore-staging-db.sh`, `deploy/vps/staging-smoke-backup.sh`.

## Вручную на серверах

**Prod dump:**

```bash
cd ~/www/lead-control.space.app
php /path/to/dump-prod-db.php   # → /tmp/lc-prod.sql.gz
tar -czf /tmp/lc-storage.tar.gz storage/app
```

**С VPS:**

```bash
scp u3398705@31.31.197.5:/tmp/lc-prod.sql.gz /tmp/
scp u3398705@31.31.197.5:/tmp/lc-storage.tar.gz /tmp/
bash /root/restore-staging-db.sh /tmp/lc-prod.sql.gz
cd /var/www/lead-control-staging && tar -xzf /tmp/lc-storage.tar.gz && chown -R www-data:www-data storage
```

## После sync

```bash
sudo -u www-data php artisan ops:health-check
sudo -u www-data php artisan backup:encrypt --label=post-sync-smoke
sudo -u www-data php artisan backup:verify --label=post-sync-smoke
```

## Журнал прогонов

| Дата | Dump size | Restore | health | login HTTP | bench | backup:verify |
|------|-----------|---------|--------|------------|-------|---------------|
| 2026-07-20 | 197 KB | OK (Selectel) | ok | 200 | 48 cities / 265 ms PASS | OK (AEGIS+GOST) |
| 2026-07-25 | 205 KB + storage 393 MB | OK **REG.RU VPS** `194.67.92.69:8081` | ok | 200 | — | OK **AEGIS-256 + gost3410-2012-256** (`aegis-gost-202607251343`) |
