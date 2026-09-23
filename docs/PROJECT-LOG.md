# Краткий журнал изменений LC

Здесь хранится по одной короткой записи на завершённую задачу, чтобы не терять контекст между чатами и деплоями. Подробные решения и исторические изменения остаются в `docs/CHANGELOG.md` и профильных документах.

## 2026-09-18 — GM PATCH /users/me/inn (prod)

- Changed: мастер из GM сохраняет свой ИНН (10/12 цифр) в `users.user_inn`; identity только из `X-GM-*`.
- Files: `routes/api.php`, `app/Http/Controllers/Api/GmController.php`, `app/Services/GmApiService.php`, `app/Providers/AppServiceProvider.php`, `tests/Feature/GmUpdateInnApiTest.php`, `docs/API.md`, `docs/CHANGELOG.md`.
- Verified: PHPUnit 12 OK; на prod после `systemctl restart php8.2-fpm` — PATCH → 401 без Bearer / 422 `invalid_inn` / 404 `user_not_found`; GET `/users/me` #146 → 200.
- Follow-up: none (выложено на VPS `/var/www/lead-control` 2026-09-18).

## 2026-09-17 — Добавлен журнал для будущих изменений

- Changed: создан компактный журнал и правило обязательной записи для Codex.
- Files: `AGENTS.md`, `/root/AGENTS.md`, `docs/PROJECT-LOG.md`, `docs/PROJECT-MAP.md`, `README.md`, `docs/CHANGELOG.md`.
- Verified: наличие файлов и `php artisan route:list --except-vendor --json` (234 маршрута).
- Follow-up: после каждой завершённой задачи добавлять одну запись по шаблону из `AGENTS.md`.
