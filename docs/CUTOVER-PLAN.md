# План cutover prod → REG.RU VPS (ТЗ §9.8, §10.2 ш.7)

**По ТЗ:** целевая площадка — **VPS в РФ у REG.RU** (не shared, не Selectel).  
**Prod сейчас:** REG.RU shared (`lead-control.space`, `31.31.197.5`)  
**Staging:** по ТЗ — staging на REG.RU VPS до cutover. (Любой Selectel — вне ТЗ, не цель prod.)

Полный пошаговый план работ: `docs/TZ-ACTION-PLAN.md`.

---

## 1. Цель

- Прод на **REG.RU VPS** в РФ.
- Redis, worker, firewall 80/443, §6.5 мониторинг.
- Бэкапы: `backup:encrypt` + `backup:verify` по расписанию (AEGIS/ГОСТ после PoC на этом VPS).

---

## 2. Предусловия

| # | Готово | Комментарий |
|---|--------|-------------|
| REG.RU VPS заказан + SSH | ☐ | Заказчик |
| PHP 8.2+, Nginx, MariaDB, Redis, UFW | ☐ | |
| PoC AEGIS + GOST на **этом** VPS | ☐ | Ранее PoC был на стороннем хосте — перенести/повторить |
| `backup:verify` на REG.RU VPS | ☐ | |
| Dump shared → VPS (пробный) | ☐ | |
| SSL + домен | ☐ | В день cutover / сразу после DNS |
| Окно простоя 1–2 ч | ☐ | Согласовать дату |
| Rollback ≤ 14 дней shared read-only | ☐ | §9.8 |
| Mango настроен на shared (желательно до DNS) | ☐ | §10.2.5 |

---

## 3. Шаги cutover (день X)

1. **Freeze** на shared.
2. **Бэкап shared** (ш.0): mysqldump + `storage`.
3. **Подготовка REG.RU VPS:** nginx, PHP, MariaDB, Redis, Supervisor, cron.
4. **Restore на VPS:** дамп, `storage`, `.env`.
5. **Smoke до DNS:** временный URL/hosts — логин, отчёт, health, backup:verify.
6. **Mango:** проверить webhook на том же домене после DNS.
7. **DNS** A/AAAA → REG.RU VPS; SSL.
8. **Post-cutover 48 ч:** failed_jobs, health, nightly backup.
9. **Shared:** read-only ≤ 14 дней.

**Откат:** DNS обратно на shared.

---

## 4. Площадка (только по ТЗ)

| Вариант | Статус |
|---------|--------|
| **REG.RU VPS** | **Целевой по §9.8** |
| Selectel | Не в ТЗ — не использовать как prod |

---

## 5. Ответственные

| Роль | Действие |
|------|----------|
| Заказчик | Заказ VPS, дата окна, DNS, Mango, секреты |
| Разработка | Деплой, миграция, smoke, rollback |
| Тестер | §12 после cutover |

---

## 6. Артефакты после cutover

- [ ] `docs/RESTORE-SMOKE-LOG.md` с **REG.RU VPS**
- [ ] `docs/TZ-STATUS-REPORT.md` / `docs/TZ-ACTION-PLAN.md`
- [ ] `docs/MIGRATION-102-CHECKLIST.md` шаг 7
- [ ] Cron + Supervisor checklist
