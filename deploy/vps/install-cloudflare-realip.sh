#!/usr/bin/env bash
# Установка Cloudflare real_ip на REG.RU VPS (lead-control)
set -euo pipefail

SNIPPET=/etc/nginx/snippets/cloudflare-realip.conf
SITE=/etc/nginx/sites-enabled/lead-control
SRC_DIR="$(cd "$(dirname "$0")" && pwd)"

install -d /etc/nginx/snippets
cp -f "$SRC_DIR/nginx-cloudflare-realip.conf" "$SNIPPET"

if ! grep -q 'cloudflare-realip.conf' "$SITE"; then
  # После ssl_dhparam — подключаем real IP
  sed -i '/ssl_dhparam/a\    include /etc/nginx/snippets/cloudflare-realip.conf;' "$SITE"
fi

nginx -t
systemctl reload nginx
echo CLOUDFLARE_REALIP_OK
