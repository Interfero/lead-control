# T−24 / cutover prep — REG.RU VPS

**VPS:** `194.67.92.69` · staging http://194.67.92.69:8081/  
**Prod сейчас:** shared `31.31.197.5` / `lead-control.space`  
**Полный runbook дня X:** `docs/CUTOVER-DAY-X-RUNBOOK.md`

## Сделать до окна (заказчик + разработка)

1. [ ] Снизить **TTL** A/AAAA `lead-control.space` (ориентир **300 с**, за ≥24 ч)
2. [ ] Согласовать окно **1–2 ч** и уведомить пользователей
3. [ ] Проверить SSH `root@194.67.92.69`, диск, Redis, MariaDB
4. [ ] На VPS: подготовить prod path `/var/www/lead-control` (копия staging или свежий sync)
5. [ ] Свежий dump shared + storage в день X
6. [ ] Certbot готов (`certbot --nginx -d lead-control.space` после DNS)
7. [ ] Mango: после DNS тот же URL webhook
8. [ ] Откат: DNS назад на `31.31.197.5`

## Уже готово (2026-07-25)

- [x] Runtime Nginx/PHP 8.2/MariaDB/Redis/UFW
- [x] Staging с боевыми данными (138 users / 239 orders)
- [x] `backup:encrypt` + `backup:verify` = **AEGIS-256 + gost3410-2012-256**
- [x] health ok, login 200

## Не делать в день X без окна

- Не менять DNS «на пробу»
- Не удалять shared до истечения ≤14 дней rollback
