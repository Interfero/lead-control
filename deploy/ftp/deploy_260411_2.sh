#!/usr/bin/env bash
# =============================================================================
# Деплой 11.04.2026 (#2) — расширение источников заказов (Source):
# - миграции: flyer_makets, расширение sources (снят UNIQUE с source_name, телефон,
#   формат online/offline, city_id, flyer_maket_id, superpart_partner_id);
# - модели FlyerMaket, Source, City; контроллеры Order/Person/Report;
# - шаблоны заказов/клиентов/отчёта партнёров (display_label);
# - SourceSeeder, CHANGELOG.
#
# FTP (reg.ru): домашний каталог FTP = корень Laravel. REMOTE_ROOT не задавать с /www/...
#
# Пример запуска из корня репозитория:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260411_2.sh
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
  # Миграции
  "database/migrations/2026_04_11_120000_create_flyer_makets_table.php"
  "database/migrations/2026_04_11_120001_extend_sources_table.php"
  # Модели
  "app/Models/FlyerMaket.php"
  "app/Models/Source.php"
  "app/Models/City.php"
  # Контроллеры
  "app/Http/Controllers/OrderController.php"
  "app/Http/Controllers/PersonController.php"
  "app/Http/Controllers/ReportController.php"
  # Шаблоны
  "resources/views/orders/create.blade.php"
  "resources/views/orders/show.blade.php"
  "resources/views/orders/index.blade.php"
  "resources/views/persons/create.blade.php"
  "resources/views/persons/show.blade.php"
  "resources/views/reports/partners.blade.php"
  # Сидер
  "database/seeders/SourceSeeder.php"
  # Журнал
  "docs/CHANGELOG.md"
  # Скрипт выгрузки
  "deploy/ftp/deploy_260411_2.sh"
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
php83 artisan migrate --force
php83 artisan view:clear
php83 artisan cache:clear

EOF
