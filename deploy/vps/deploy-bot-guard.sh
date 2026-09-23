#!/usr/bin/env bash
set -euo pipefail
APP=/var/www/lead-control
cd "$APP"

# ensure env knobs (idempotent)
if ! grep -q '^BOT_GUARD_ENABLED=' .env; then
  cat >> .env <<'EOF'

# BotGuard (anti-scrape kick)
BOT_GUARD_ENABLED=true
BOT_GUARD_EXEMPT_ROLES=developer
BOT_GUARD_MAX_PER_MINUTE=90
BOT_GUARD_MAX_PER_10S=28
BOT_GUARD_MAX_SENSITIVE_PER_MINUTE=22
BOT_GUARD_MAX_SEARCH_PER_MINUTE=12
BOT_GUARD_LOCK_MINUTES=30
EOF
fi

sudo -u www-data php -l app/Services/BotGuardService.php
sudo -u www-data php -l app/Http/Middleware/DetectBotActivity.php
sudo -u www-data php -l app/Http/Controllers/Auth/LoginController.php
sudo -u www-data php -l bootstrap/app.php
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
# Не route:cache: именованный throttle:client-cards даёт 500 без ThrottleRequestsFixed
sudo -u www-data php artisan list | grep bot-guard || true
curl -sk -o /dev/null -w "login=%{http_code}\n" --resolve lead-control.space:443:127.0.0.1 https://lead-control.space/login
curl -sk --resolve lead-control.space:443:127.0.0.1 https://lead-control.space/api/v1/ops/health | head -c 200; echo
echo BOT_GUARD_DEPLOYED
