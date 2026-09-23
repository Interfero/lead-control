#!/usr/bin/env bash
set -euo pipefail
APP=/var/www/lead-control
cd "$APP"

TOKEN=$(grep -E '^GM_API_BEARER_TOKEN=' .env | cut -d= -f2- | tr -d '\r"' | tr -d "'")
if [[ -z "$TOKEN" ]]; then
  echo "ERROR: GM_API_BEARER_TOKEN missing in .env"
  exit 1
fi

echo "==> CLI org tree"
sudo -u www-data php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$t = app(App\Services\OrgTreeService::class)->tree();
echo json_encode([
    "cities" => count($t["cities"]),
    "all_cities" => $t["scope"]["all_cities"],
    "company_gd" => count($t["company"]["general_directors"]),
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
'

echo "==> HTTP org/tree"
CODE=$(curl -sk -o /tmp/org-tree.json -w '%{http_code}' \
  --resolve lead-control.space:443:127.0.0.1 \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" \
  "https://lead-control.space/api/v1/gm/org/tree")
echo "HTTP=${CODE}"
head -c 300 /tmp/org-tree.json
echo
test "$CODE" = "200"
echo SMOKE_OK
