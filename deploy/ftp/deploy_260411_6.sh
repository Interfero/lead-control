#!/usr/bin/env bash
# =============================================================================
# Деплой 11.04.2026 (#6) — PartnerOrderController: JSON при 500 + подсказки по миграциям
#
# Пример: FTP_PASS='…' bash ./deploy/ftp/deploy_260411_6.sh
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
  "app/Http/Controllers/Api/PartnerOrderController.php"
  "deploy/ftp/deploy_260411_6.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — PartnerOrderController (обработка 500 / БД)"
echo "Корень проекта: ${ROOT}"
echo "============================================================================="

uploaded=0
errors=0
for file in "${FILES[@]}"; do
  [[ -z "${file// }" ]] && continue
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

=== Сервер CRM (SSH), корень Laravel ===

php83 artisan optimize:clear

# Если в ответе API было про partner_user_id или partner_order_idempotency:
# php83 artisan migrate --force

EOF
