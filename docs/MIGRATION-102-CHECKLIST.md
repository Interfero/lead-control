# §10.2 Миграция данных — чеклист (журнал)

Порядок по ТЗ обязателен. Отмечайте дату и артефакт (файл/лог).

| Шаг | Действие | Статус | Дата | Артефакт / примечание |
|-----|----------|--------|------|------------------------|
| 0 | Полный бэкап БД + `storage` | ☐ prod ☐ staging | | Форма: `docs/BACKUP-STEP0-CHECKLIST.md`; staging restore smoke OK 2026-07-20 |
| 1 | Инвентаризация дыр (DID, sources без телефона) | ☑ | 2026-07-20 | `sources:attribution-gaps`; снимок `docs/ATTRIBUTION-GAPS-2026-07-20.md` + CSV |
| 2 | Миграция схемы | ☑ | | `php artisan migrate` на prod/staging |
| 3 | Нормализация телефонов (`sources` + `person_phones`) | ☑ | 2026-07-20 | `sources:normalize-phones` — 3; `persons:normalize-phones --dry-run` — 0 к правке |
| 4 | РК на все боевые линии (DID) | ☑ | 2026-07-20 | ADR: SuperPart-парты без DID-телефона OK; блокеров DID = 0 |
| 5 | Mango webhook, без `.env`-канона | ☐ | | стоп: доступы есть, в `.env`/кабинете ещё не включено |
| 6 | Backfill `report_city_daily` | ☑ | | backfill на prod |
| 7 | Cutover на **REG.RU VPS** (§9.8) | ☑ | 2026-07-26 | prod `194.67.92.69`; SSL LE; health/backup ok; shared ≤14д |

## Очистка тестовых данных (вне шагов ТЗ, prod)

| Дата | Действие |
|------|----------|
| 2026-07-20 | `app:prune-february-2026-test` — февраль |
| 2026-07-20 | `app:prune-test-residuals` — остатки тестов |

## Следующие действия

1. ~~Dump prod → staging~~ — **сделано 2026-07-20** (`docs/SYNC-PROD-TO-STAGING.md`).
2. ~~Smoke на staging~~ — health/login/bench/`backup:verify` **OK**.
3. ~~Заказчик: 15 парт-источников~~ — **решение 2026-07-20 вариант 2:** SuperPart-парты не требуют DID-телефона (`docs/ADR-superpart-party-no-did-phone.md`).
4. ~~Прогнать `persons:normalize-phones`~~ — dry-run 0 изменений (уже нормализованы).
5. **Mango** (§10.2 ш.5) — ключи в `.env`, webhook, тест-звонки.
6. ~~**REG.RU VPS** (§9.8 / §10.2 ш.7)~~ — cutover 2026-07-26 (`docs/OPS_STAGE_F.md`).
7. Этап **G** — решение A/B/C письменно.
8. Закрыть §12 п.10–13 по мере готовности (тестер UI, smoke, доступы подрядчика).
