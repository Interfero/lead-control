#!/usr/bin/env bash
# =============================================================================
# Деплой 11.04.2026 (#1) — Претензии:
# - список /complaints (фильтры во второй строке таблицы как у заказов, даты в полосе
#   сверху), крошки на /complaints/create; ComplaintController + ComplaintService;
# - отчёт /complaints/report: крошки, карточка и таблица как у заказов, компактная сетка
#   колонок, полные подписи типов в шапке с переносом по строкам (resources/css + public/css);
# - отзывы /complaints/reviews: крошки, кнопка «Добавить отзыв», форма на /complaints/reviews/create;
#   карточка /complaints/reviews/{id}; документы как у КФМ (полиморф Document, ReviewDocumentService);
#   routes/web.php, ReviewController/ReviewService/Review, DocumentController, шаблоны reviews/*;
# - /management/cities: крошки, таблица, переход по строке в той же вкладке (index/edit/create);
# - /cfm/editor: крошки и кнопка «Новая статья» как у заказов;
# - /information (база знаний): заголовок заменён на крошки и кнопку как у заказов;
# - /settings: настройки (крошки, карточки, ID, блок разработчика), partial навбара (ID в формате «ID N»),
#   resources/css/layout.css (стили профиля в шапке);
# CHANGELOG.
#
# Перед выгрузкой выполнить в корне проекта: npm run build (обновятся public/build/…).
#
# FTP (reg.ru): домашний каталог FTP = корень Laravel. REMOTE_ROOT не задавать с /www/...
#
# Пример запуска из корня репозитория:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260411_1.sh
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
  # Бэкенд
  "routes/web.php"
  "app/Http/Controllers/ComplaintController.php"
  "app/Http/Controllers/DocumentController.php"
  "app/Http/Controllers/ReviewController.php"
  "app/Models/Review.php"
  "app/Services/ComplaintService.php"
  "app/Services/ReviewDocumentService.php"
  "app/Services/ReviewService.php"
  # Шаблоны
  "resources/views/complaints/index.blade.php"
  "resources/views/complaints/create.blade.php"
  "resources/views/complaints/report.blade.php"
  "resources/views/complaints/reviews.blade.php"
  "resources/views/complaints/reviews/create.blade.php"
  "resources/views/complaints/reviews/show.blade.php"
  # Управление городами + редактор статей КФМ
  "resources/views/management/cities/index.blade.php"
  "resources/views/management/cities/edit.blade.php"
  "resources/views/management/cities/create.blade.php"
  "resources/views/cfm/editor.blade.php"
  "resources/views/knowledge/index.blade.php"
  "resources/views/settings/index.blade.php"
  "resources/views/partials/navbar-profile.blade.php"
  # Стили (источник + legacy + сборка Vite)
  "resources/css/app.css"
  "resources/css/layout.css"
  "public/css/app.css"
  "public/build/manifest.json"
  # имя *.css сверить с ключом file в manifest после npm run build
  "public/build/assets/app-DAu5N9n3.css"
  # Журнал
  "docs/CHANGELOG.md"
  # Скрипт выгрузки
  "deploy/ftp/deploy_260411_1.sh"
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
