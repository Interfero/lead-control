#!/usr/bin/env bash
# =============================================================================
# Деплой 13.04.2026 (#5) — Lead Control CRM: SuperPart — каталог источников, source_id в заказе
#
# Задача: флаг available_for_superpart, UI источников (вкладка «Для SuperPart»), API
# GET/PATCH reference/sources, опциональный source_id в POST partner-orders, миграция БД.
#
# Что заливается: миграция, модель Source, управление источниками (контроллер + Blade),
# PartnerReferenceController, PartnerOrderController, PartnerOrderIngestService, routes/api.php,
# документация.
#
# FTP CRM (reg.ru): u3398705_cursor_lc — домашний каталог = корень Laravel.
#   REMOTE_ROOT не задавать с путём вида /www/...
#
# Пример запуска из корня репозитория:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260413_5.sh
# =============================================================================
set -euo pipefail

SCRIPT_BASENAME="$(basename "$0")"
SCRIPT_DATE="${SCRIPT_BASENAME#deploy_}"
SCRIPT_DATE="${SCRIPT_DATE%.sh}"
SCRIPT_DATE="${SCRIPT_DATE%_*}"
TODAY="$(date +%y%m%d)"
if [ "${SCRIPT_DATE}" != "${TODAY}" ]; then
  echo "ERROR: дата в имени файла (${SCRIPT_DATE}) не совпадает с сегодняшней (${TODAY}). Переименуйте скрипт."
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
cd "${ROOT}"

FTP_HOST="31.31.197.5"
FTP_PORT=21
FTP_USER="u3398705_cursor_lc"
REMOTE_ROOT="${REMOTE_ROOT:-}"

if [ -z "${FTP_PASS:-}" ]; then
  echo "ERROR: не задан FTP_PASS."
  exit 1
fi

FILES=(
  # БД
  "database/migrations/2026_04_13_120000_add_available_for_superpart_to_sources_table.php"

  # Модель
  "app/Models/Source.php"

  # Управление источниками (CRM)
  "app/Http/Controllers/Management/SourceManagementController.php"
  "resources/views/management/sources/index.blade.php"
  "resources/views/management/sources/create.blade.php"
  "resources/views/management/sources/edit.blade.php"

  # API SuperPart
  "routes/api.php"
  "app/Http/Controllers/Api/PartnerReferenceController.php"
  "app/Http/Controllers/Api/PartnerOrderController.php"
  "app/Services/PartnerOrderIngestService.php"

  # Документация
  "docs/SUPERPART_SOURCES.md"
  "docs/CHANGELOG.md"

  "deploy/ftp/deploy_260413_5.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — Lead Control CRM (SuperPart: источники + source_id)"
echo "Корень проекта: ${ROOT}"
echo "============================================================================="

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
  echo "  Загрузка: ${file}"
  if curl -sS --ftp-create-dirs \
    -T "${file}" \
    "ftp://${FTP_HOST}:${FTP_PORT}${remote_path}" \
    --user "${FTP_USER}:${FTP_PASS}"; then
    echo "  OK: ${file}"
    ((uploaded++)) || true
  else
    echo "  ERROR: ${file}"
    ((errors++)) || true
  fi
done
echo "Done! Uploaded: ${uploaded}, Errors: ${errors}"

cat <<'EOF'

=== Сервер CRM Lead Control (SSH), рабочий каталог — корень Laravel ===

php83 artisan migrate --force

php83 artisan route:clear
php83 artisan optimize:clear

# Проверка маршрутов SuperPart
php83 artisan route:list | grep -E 'reference/sources|partner-orders'

# Ожидаются GET api/v1/reference/sources, PATCH api/v1/reference/sources/{source_id}, POST api/v1/partner-orders

# Документация для SuperPart (в репозитории): docs/SUPERPART_SOURCES.md

EOF
