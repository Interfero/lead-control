#!/usr/bin/env bash
# =============================================================================
# Деплой 10.04.2026 (#1) — HR: карточка сотрудника (create/edit), email @lc.ru,
# сетка полей, комментарии в сайдбаре, ФИО (ID) в крошках и навбаре, layout navbar.
# Отчёты: /reports/orders, cancellations, partners — фильтры как заказы, тёмная тема,
# хлебные крошки; CHANGELOG.
#
# Файлы: HrController (суффикс email, нормализация, JSON show), hr/create, hr/edit,
# layouts/app (navbar_context), resources/css/layout.css (navbar-brand-group),
# public/css/app.css (HR-формы, email-suffix), TestogradSeeder (@lc.ru),
# reports/*.blade.php; сборка Vite после npm run build — manifest + app-CnnGuIQA.css + JS
# (для правок только отчётов npm build не обязателен, если не трогали фронт).
#
# FTP (reg.ru): домашний каталог FTP = корень Laravel. REMOTE_ROOT не задавать с /www/...
#
# Перед выгрузкой локально:
#   npm run build
#   (хеши в FILES ниже сверены с public/build/manifest.json)
#
# Пример запуска из корня репозитория:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260410_1.sh
# =============================================================================
set -euo pipefail

SCRIPT_BASENAME="$(basename "$0")"
SCRIPT_DATE="${SCRIPT_BASENAME#deploy_}"
SCRIPT_DATE="${SCRIPT_DATE%.sh}"
SCRIPT_DATE="${SCRIPT_DATE%_*}"
TODAY="$(date +%y%m%d)"
if [ "${SCRIPT_DATE}" != "${TODAY}" ]; then
  echo "ERROR: дата в имени файла (${SCRIPT_DATE}) не совпадает с сегодняшней (${TODAY}). Переименуйте скрипт или запустите в нужный день."
  exit 1
fi

FTP_HOST="31.31.197.5"
FTP_PORT=21
FTP_USER="u3398705_cursor_lc"
REMOTE_ROOT="${REMOTE_ROOT:-}"

if [ -z "${FTP_PASS:-}" ]; then
  echo "ERROR: не задан FTP_PASS."
  exit 1
fi

FILES=(
  # Бэкенд HR
  "app/Http/Controllers/HrController.php"
  # Шаблоны
  "resources/views/layouts/app.blade.php"
  "resources/views/hr/create.blade.php"
  "resources/views/hr/edit.blade.php"
  # Отчёты (статистика заявок, отмены, партнёры)
  "resources/views/reports/orders.blade.php"
  "resources/views/reports/cancellations.blade.php"
  "resources/views/reports/partners.blade.php"
  # Стили (источники + legacy public)
  "resources/css/app.css"
  "resources/css/layout.css"
  "public/css/app.css"
  # Сиды (тестовые email @lc.ru)
  "database/seeders/TestogradSeeder.php"
  # Сборка Vite (актуально после npm run build)
  "public/build/manifest.json"
  "public/build/assets/app-CnnGuIQA.css"
  "public/build/assets/app-BA6TlPUe.js"
  # Журнал
  "docs/CHANGELOG.md"
  # Скрипт выгрузки (для истории на сервере)
  "deploy/ftp/deploy_260410_1.sh"
)

echo "Connecting to ${FTP_HOST}..."
if [ -n "${REMOTE_ROOT}" ]; then
  echo "REMOTE_ROOT=${REMOTE_ROOT} (префикс путей на сервере)"
else
  echo "REMOTE_ROOT=<пусто> — пути от корня FTP (= корень Laravel)"
fi
uploaded=0
errors=0
for file in "${FILES[@]}"; do
  [[ -z "${file// }" ]] && continue
  [[ "${file}" =~ ^# ]] && continue
  if [ -n "${REMOTE_ROOT}" ]; then
    remote_path="${REMOTE_ROOT%/}/${file}"
  else
    remote_path="/${file}"
  fi
  if [ ! -f "${file}" ]; then
    echo "  SKIP (not found): ${file}"
    ((errors++)) || true
    continue
  fi
  curl -sS --ftp-create-dirs \
    -T "${file}" \
    "ftp://${FTP_HOST}:${FTP_PORT}${remote_path}" \
    --user "${FTP_USER}:${FTP_PASS}" \
    && { echo "  OK: ${file}"; ((uploaded++)) || true; } \
    || { echo "  ERROR: ${file}"; ((errors++)) || true; }
done
echo "Done! Uploaded: ${uploaded}, Errors: ${errors}"

cat <<'EOF'

--- На сервере (SSH), рабочий каталог — корень Laravel ---
php83 artisan view:clear
php83 artisan cache:clear

EOF
