# Backup encrypt (AEGIS + ГОСТ)

```bash
php artisan backup:encrypt
php artisan backup:encrypt --label=manual-2026-07-19
php artisan backup:verify
php artisan backup:verify --label=manual-2026-07-19
php artisan backup:verify --keep
```

Конфиг: `config/backup.php` / `.env`:

| Переменная | Смысл |
|------------|--------|
| `BACKUP_PATH` | Каталог архивов |
| `BACKUP_KEYS_PATH` | Ключи AEAD/GOST **вне** архива |
| `BACKUP_OPENSSL_GOST_CONF` | conf gost-engine |
| `BACKUP_AEGIS_CLI` | `aegis256-file` |
| `BACKUP_RETAIN_DAYS` | по умолчанию 30 |

Schedule: ежедневно **03:00** (`routes/console.php`).

На staging VPS ключи: `/etc/lead-control/backup` (группа `www-data`, mode 640).
