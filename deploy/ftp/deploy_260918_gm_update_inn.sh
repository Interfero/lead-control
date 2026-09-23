#!/usr/bin/env bash
# =============================================================================
# Деплой 18.09.2026 — GM: PATCH /api/v1/gm/users/me/inn
#
# Запуск из корня проекта:
#   FTP_PASS='…' bash deploy/ftp/deploy_260918_gm_update_inn.sh
# =============================================================================
set -euo pipefail

SCRIPT_BASENAME="$(basename "$0")"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${ROOT}"

FTP_HOST="${FTP_HOST:-31.31.197.5}"
FTP_PORT="${FTP_PORT:-21}"
FTP_USER="${FTP_USER:-u3398705_cursor}"
REMOTE_ROOT="${REMOTE_ROOT:-/www/lead-control.space.app}"

if [ -z "${FTP_PASS:-}" ]; then
  echo "ERROR: задайте FTP_PASS" >&2
  exit 1
fi

FILES=(
  "routes/api.php"
  "app/Http/Controllers/Api/GmController.php"
  "app/Services/GmApiService.php"
  "app/Providers/AppServiceProvider.php"
  "tests/Feature/GmUpdateInnApiTest.php"
  "docs/API.md"
  "docs/gm-api-mapping.md"
  "docs/CHANGELOG.md"
  "docs/PROJECT-LOG.md"
  "deploy/ftp/deploy_260918_gm_update_inn.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — GM PATCH /users/me/inn"
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

=== На сервере CRM (SSH), корень Laravel ===

php83 artisan route:clear
php83 artisan optimize:clear

# Проверка
php83 artisan route:list | grep 'users/me/inn'
# Ожидается: PATCH api/v1/gm/users/me/inn

EOF
