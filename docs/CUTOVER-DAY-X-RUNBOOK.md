# Cutover day-X — runbook (REG.RU VPS)

**Площадка по ТЗ §9.8:** REG.RU VPS в РФ (не Selectel).  
**Prod сейчас:** REG.RU shared `lead-control.space` (`31.31.197.5`)  
**VPS:** ✅ IP `194.67.92.69` · SSH host `reg-vps` · дата окна ______

Перед днём X: свежий dump shared→VPS, TTL DNS снижен (например 300 с за сутки), Mango webhook на том же path (после DNS тот же URL).

Полный план этапа F: `docs/TZ-ACTION-PLAN.md` блок 2, заказ VPS: `docs/VPS-ORDER-SPEC.md`.

---

## T−24 ч

- [ ] Снизить TTL у `lead-control.space` (A/AAAA)
- [ ] Уведомить пользователей о окне (1–2 ч)
- [ ] Проверить SSH `reg-vps`, диск, Redis, MariaDB
- [ ] На VPS: Supervisor `queue:work`, cron `schedule:run`, UFW 22/80/443
- [ ] Пробный `backup:encrypt` + `backup:verify` на VPS
- [ ] Staging smoke уже зелёный (логин, отчёт, health)

## День X (порядок)

1. **Freeze** — maintenance на shared (или объявить «не трогать»)
2. **Бэкап shared** — mysqldump + `storage/app`; копия локально / на VPS (`docs/BACKUP-STEP0-CHECKLIST.md`)
3. **Sync на VPS** — dump/restore; `storage` rsync/scp (`docs/SYNC-PROD-TO-STAGING.md`, хост `reg-vps`)
4. **`.env` prod на VPS** — `APP_URL=https://lead-control.space`, секреты, `QUEUE_CONNECTION=redis` при готовности
5. **Smoke до DNS** — hosts/`server_name` на IP VPS: логин, отчёт, `/api/v1/ops/health`, касса, заказ
6. **SSL** — `certbot --nginx -d lead-control.space` (при/после смены DNS)
7. **DNS** — A/AAAA → IP REG.RU VPS
8. **Mango** — webhook `https://lead-control.space/api/mango/webhook` (тот же path)
9. **Post** — 48 ч: health, failed_jobs, nightly backup+verify
10. **Shared** — read-only / offline, держать ≤ 14 дней

## Откат

DNS A обратно на shared `31.31.197.5`; при необходимости restore dump шага 2 на shared.

## После cutover — обновить

- `docs/TZ-STATUS-REPORT.md`
- `docs/RESTORE-SMOKE-LOG.md`
- `docs/MIGRATION-102-CHECKLIST.md` шаг 7
- `docs/OPS_STAGE_F.md`
