# Очереди outbound (этап D)

CRM кладёт HTTP к SuperPart / GM в таблицу `jobs` (`QUEUE_CONNECTION=database`).

## Прод (REG.RU shared)

В cron ISPManager добавьте **одну** строку (если ещё нет `schedule:run`):

```cron
* * * * * cd /var/www/u3398705/data/www/lead-control.space.app && php artisan schedule:run >> /dev/null 2>&1
```

В `routes/console.php` уже есть:
- `queue:work --stop-when-empty` каждую минуту
- пересчёт `report_city_daily` (ночь + stale hourly)

Проверка:

```bash
php artisan queue:failed
php artisan tinker --execute="echo DB::table('jobs')->count();"
```

## Что в очереди

| Job | Когда |
|-----|--------|
| `ExportReportCsvJob` | Кнопка «Скачать» на отчётах |
| `NotifySuperpartOrderCompletedJob` | проведение заказа |
| `NotifySuperpartPartnerOrderCreatedJob` | создание / смена source |
| `NotifySuperpartSourceUpsertJob` | CRUD/импорт источников |
| `NotifySuperpartSourceDeleteJob` | удаление источника |
| `SyncOrderToGmJob` | update/complete/reopen заказа |
| `DeleteGmOrderDocumentJob` | удаление документа заказа |
| `SyncUserToGmJob` | HR create/update/fire/restore |

## CSV отчётов

Кнопка «Скачать» на отчётах ставит `ExportReportCsvJob` в очередь (не стримит в браузер сразу).
Скачивание: `/reports/exports/{uuid}` (ссылка в flash после клика; готовность ~1 мин через `schedule:run`).

Устаревшие файлы: `reports:cleanup-exports --days=2` ежедневно в 03:15.
