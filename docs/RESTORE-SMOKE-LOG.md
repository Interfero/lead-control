# Restore smoke (бэкап) — журнал

| Дата | Среда | Команда | Результат |
|------|--------|---------|-----------|
| 2026-07-20 | staging `selectel-crm` | `backup:encrypt --label=restore-smoke-2026-07-20-v2` + `backup:verify` | **OK** — AEGIS decrypt, GOST manifest, `gzip -t` |

**Исправление:** подпись манифеста не перезаписывается вторым `manifest.json` (`BackupEncryptionService`).

**Повтор на staging:**

```bash
cd /var/www/lead-control-staging
sudo -u www-data php artisan backup:encrypt --label=manual-DATE
sudo -u www-data php artisan backup:verify --label=manual-DATE
```

См. `docs/BACKUP_ENCRYPT.md`, §12.B п.12 ТЗ.
