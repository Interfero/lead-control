#!/usr/bin/env bash
# =============================================================================
# Деплой 11.04.2026 (#4) — Lead Control CRM: полная выгрузка входящего API SuperPart
#
# Задача: на проде появились маршруты api/v1/* (POST api/v1/partner-orders и справочники).
# Ранее выгружался только PartnerOrderController без routes/api.php и bootstrap/app.php —
# маршруты не регистрировались.
#
# Что заливается: маршруты API, bootstrap, middleware, контроллеры API (в т.ч. Mango webhook
# из того же routes/api.php), сервис приёма заявок, модель логов, миграция (если ещё не на сервере),
# config/services.php (блок superpart), модель Order (partner_user_id в fillable).
#
# .env на сервере (не в репозитории): SUPERPART_API_KEY/SECRET совпадают с LEVELION_* в SuperPart;
# после правок .env: php83 artisan config:clear
#
# FTP CRM (reg.ru): u3398705_cursor_lc — домашний каталог = корень Laravel.
#   REMOTE_ROOT не задавать с путём вида /www/...
#
# Пример запуска из корня репозитория Levelion_dev:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260411_4.sh
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
  # Регистрация маршрутов API
  "routes/api.php"
  "bootstrap/app.php"
  "config/services.php"

  # SuperPart + общий api.php (Mango)
  "app/Http/Middleware/VerifySuperPartApi.php"
  "app/Http/Controllers/Api/IntegrationPingController.php"
  "app/Http/Controllers/Api/PartnerOrderController.php"
  "app/Http/Controllers/Api/PartnerReferenceController.php"
  "app/Http/Controllers/Api/MangoWebhookController.php"

  "app/Services/PartnerOrderIngestService.php"
  "app/Models/IntegrationApiLog.php"
  "app/Models/Order.php"

  "database/migrations/2026_04_06_120000_partner_superpart_integration.php"

  "deploy/ftp/deploy_260411_4.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — Lead Control CRM (SuperPart API: маршруты + код)"
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

=== Сервер CRM Lead Control (SSH), рабочий каталог — корень Laravel (lead-control.space.app) ===

# Очистка кэша маршрутов и оптимизаций (обязательно после выгрузки routes/api.php / bootstrap)
php83 artisan route:clear
php83 artisan optimize:clear
php83 artisan config:clear

# Если миграция SuperPart ещё не применялась на этом инстансе:
# php83 artisan migrate --force

# Проверка маршрутов
php83 artisan route:list | grep partner-orders

# Ожидается строка с POST и api/v1/partner-orders

# Проверка curl (замените BASE и подписи; без ключей в production GET /api/v1/ping даст 503 JSON)
# curl -sS -o /dev/null -w "%{http_code}\n" "https://BASE/api/v1/ping"

# .env: SUPERPART_API_KEY, SUPERPART_API_SECRET (как LEVELION_* в SuperPart),
# SUPERPART_ORDER_AUTHOR_USER_ID, SUPERPART_DEFAULT_SOURCE_ID, при необходимости SUPERPART_BASE_URL

EOF
