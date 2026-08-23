#!/usr/bin/env bash
# WP-01D web boundary: only public/ may be reachable through the web port.
set -Eeuo pipefail

BASE_URL=${CI4_BASE_URL:-http://127.0.0.1:18405}

fail() { echo "FAIL: $*" >&2; exit 1; }

status_of() {
  curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$BASE_URL$1"
}

health=$(status_of /health)
[ "$health" = 200 ] || fail "positive control /health returned $health (server not usable)"

denied_paths=(
  /app/Config/App.php
  /app/Config/Database.php
  /vendor/autoload.php
  /vendor/codeigniter4/framework/system/Boot.php
  /writable/logs/index.html
  /writable/uploads/index.html
  /spark
  /composer.json
  /composer.lock
  /.env
  /.env.example
  /phpunit.xml.dist
  /tests/ci4/HealthTest.php
  /scripts/ci-check.sh
  /db/dbctl.sh
)

for path in "${denied_paths[@]}"; do
  code=$(status_of "$path")
  case "$code" in
    200|301|302) fail "web boundary leak: $path returned $code" ;;
  esac
done

echo "PASS web boundary: /health=200, ${#denied_paths[@]} non-public paths denied"
