#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
cd "$ROOT"

fail() { echo "FAIL: $*" >&2; exit 1; }
pass() { echo "PASS $*"; }

bash -n db/dbctl.sh db/privacy-purge-local.sh scripts/ci4-concurrency-check.sh \
  scripts/wp00c-recovery-check.sh scripts/ci4-web-boundary-check.sh
pass "shell syntax"

composer validate --strict >/dev/null
composer audit --locked --no-interaction >/dev/null
if [ -f vendor/autoload.php ]; then
  php spark --version >/dev/null
  php -r 'exit(extension_loaded("mysqli") && str_contains(mysqli_get_client_info(), "mysqlnd") ? 0 : 1);' \
    || fail "PHP runtime must load mysqli built on mysqlnd"
  composer check-platform-reqs --no-interaction >/dev/null
  routes=$(php spark routes)
  vendor/bin/phpunit --configuration phpunit.xml.dist >/dev/null
else
  ci4_docker_config=${DOCKER_CONFIG:-/tmp/samsonite-ci4-docker-config}
  mkdir -p "$ci4_docker_config"
  ci4_image=samsonitetracking-ci4:4.7.4-php8.5.7
  DOCKER_CONFIG="$ci4_docker_config" docker image inspect "$ci4_image" >/dev/null 2>&1 \
    || DOCKER_CONFIG="$ci4_docker_config" docker build -f Dockerfile.ci4 -t "$ci4_image" "$ROOT" >/dev/null
  ci4_mounts=(
    -v "$ROOT/app:/app/app:ro"
    -v "$ROOT/public:/app/public:ro"
    -v "$ROOT/tests/ci4:/app/tests/ci4:ro"
    -v "$ROOT/tests/wp00c:/app/tests/wp00c:ro"
    -v "$ROOT/phpunit.xml.dist:/app/phpunit.xml.dist:ro"
    -v "$ROOT/spark:/app/spark:ro"
  )
  php_version=$(DOCKER_CONFIG="$ci4_docker_config" \
    docker run --rm "${ci4_mounts[@]}" "$ci4_image" php spark --version)
  printf '%s\n' "$php_version" | grep -Fq 'CodeIgniter v4.7.4' \
    || fail "CI4 Docker runtime version mismatch"
  DOCKER_CONFIG="$ci4_docker_config" docker run --rm "${ci4_mounts[@]}" "$ci4_image" \
    php -r 'exit(extension_loaded("mysqli") && str_contains(mysqli_get_client_info(), "mysqlnd") ? 0 : 1);' \
    || fail "CI4 image must load mysqli built on mysqlnd"
  routes=$(DOCKER_CONFIG="$ci4_docker_config" \
    docker run --rm "${ci4_mounts[@]}" "$ci4_image" php spark routes)
  DOCKER_CONFIG="$ci4_docker_config" docker run --rm "${ci4_mounts[@]}" "$ci4_image" \
    vendor/bin/phpunit --configuration phpunit.xml.dist >/dev/null
fi
printf '%s\n' "$routes" | grep -Eq 'GET.*health.*Health::index' \
  || fail "CI4 explicit health route is missing"
grep -Fq 'public bool $autoRoute = false;' app/Config/Routing.php \
  || fail "CI4 Auto Routing Legacy is enabled"
grep -Eq "'DBDebug'[[:space:]]*=> ENVIRONMENT !== 'production'," app/Config/Database.php \
  || fail "CI4 default DBDebug must be disabled in production"
pass "CI4 dependency, route and health smoke gates"
pass "PHP mysqli/mysqlnd and composer platform requirements"

ci4_compose=$(sed -n '/^  ci4:/,/^networks:/p' compose.yaml)
if grep -Eq '^[[:space:]]+(app|database|encryption)\.' <<<"$ci4_compose"; then
  fail "CI4 Compose uses Docker-incompatible dotted environment keys"
fi
for key in \
  app_baseURL \
  database_default_hostname \
  database_default_port \
  database_default_database \
  database_default_username \
  database_default_password \
  database_default_DBDriver \
  encryption_driver \
  encryption_key; do
  grep -Fq "      $key:" <<<"$ci4_compose" \
    || fail "CI4 Compose environment alias is missing: $key"
done
pass "CI4 Docker environment aliases"

grep -Fq 'database_default_database: "${CI4_DATABASE:-samsonite_ci4}"' compose.yaml \
  || fail "CI4 must use its isolated default database"
grep -Fq '  ci4-db-bootstrap) ci4_db_bootstrap ;;' db/dbctl.sh \
  || fail "CI4 synthetic database bootstrap command is missing"
pass "CI4 database isolation"

grep -Fxq 'CI4_HOST_PORT=18405' .env.example \
  || fail "CI4 loopback port placeholder is missing"
grep -Fxq 'CI4_RESET_ENCRYPTION_KEY=' .env.example \
  || fail "CI4 reset encryption-key placeholder is missing"
pass "CI4 environment placeholders"

bash scripts/ci4-concurrency-check.sh

schema_tables=$(grep -c '^CREATE TABLE' db/local-schema-only.sql || true)
[ "$schema_tables" = 31 ] || fail "schema table count is $schema_tables, expected 31"
if grep -Eiq '^[[:space:]]*(INSERT|REPLACE)[[:space:]]+INTO|^[[:space:]]*LOAD[[:space:]]+DATA' \
     db/local-schema-only.sql; then
  fail "schema-only SQL contains data statements"
fi
pass "schema-only SQL: tables=31 data statements=0"

python3 scripts/wp00c-kit.py validate-data >/dev/null
python3 -m py_compile scripts/wp00c-route-auth.py
python3 -m py_compile scripts/wp00c-closure.py
python3 -m unittest tests/wp00c/test_closure.py >/dev/null
python3 -m unittest tests/wp00c/test_junit_evidence.py tests/wp00c/test_route_disposition.py >/dev/null
for command in validate seed verify clean; do
  grep -Fq "  wp00c-fixture-$command)" db/dbctl.sh \
    || fail "WP-00C fixture command is missing: $command"
done
pass "WP-00C catalog, synthetic fixture kit and fail-closed closure gate"

if curl -s --max-time 2 -o /dev/null "${CI4_BASE_URL:-http://127.0.0.1:18405}/health" 2>/dev/null; then
  bash scripts/ci4-web-boundary-check.sh
else
  pass "web boundary skipped: CI4 runtime not listening"
fi

ci3_source_root=${CI3_SOURCE_ROOT:-$ROOT/../samsoniteci3}
if [ -d "$ci3_source_root/.git" ]; then
  CI3_SOURCE_ROOT="$ci3_source_root" php scripts/check-function-disposition.php \
    outputs/diagrams/2026-08-22_function-disposition-evidence_v3.md >/dev/null \
    || fail "function disposition ledger reconciliation failed"
  pass "function disposition ledger (WP-01I)"
else
  pass "function disposition ledger skipped: CI3 checkout unavailable"
fi

grep -Fq \
  'WP00C_CURRENT_CI3_STATE="ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6 0"' \
  db/dbctl.sh \
  || fail "WP-00C cleanup current CI3 pin is stale"
grep -Fq \
  'WP00C_LEGACY_CI3_STATE="8dad4e331a90f5c6765954454910b451eb0ff8e5 0"' \
  db/dbctl.sh \
  || fail "WP-00C cleanup lost legacy fixture-state compatibility"
if grep -Fq 'report-tracking-test-empty-status' web/Dockerfile; then
  fail "Docker build still applies obsolete Report Tracking patch"
fi
pass "CI3 PR3 repin and obsolete-patch removal"

tracked_sql=$(git ls-files '*.sql')
[ "$tracked_sql" = "db/local-schema-only.sql" ] \
  || fail "unexpected tracked SQL file"

candidate_sensitive=$(git ls-files --cached --others --exclude-standard | grep -E \
  '(^|/)\.env($|\.)|(^|/)(auth|credentials)\.json$|\.(pem|key)$|(^|/)SECRETS-LOCAL\.md$' \
  | grep -v '^\.env\.example$' || true)
[ -z "$candidate_sensitive" ] || {
  printf '%s\n' "$candidate_sensitive" >&2
  fail "sensitive filename is present in candidate tree"
}
git check-ignore -q .env || fail ".env is not ignored"
pass "secret file policy"

python3 <<'PY'
import pathlib
import re
import subprocess
import sys

email = re.compile(r"[A-Za-z0-9._%+-]+@([A-Za-z0-9.-]+\.[A-Za-z]{2,}|example\.invalid)")
phone = re.compile(r"(?<![0-9A-Fa-f])(?:\+66|0)\d{1,2}[ -]?\d{3}[ -]?\d{4,5}(?![0-9A-Fa-f])")
violations = []

for raw_path in subprocess.check_output(
    ["git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"]
).split(b"\0"):
    if not raw_path:
        continue
    path = pathlib.Path(raw_path.decode())
    # Composer lock metadata contains public upstream package-author addresses.
    if path == pathlib.Path("composer.lock"):
        continue
    try:
        lines = path.read_text(encoding="utf-8").splitlines()
    except (UnicodeDecodeError, OSError):
        continue
    for number, line in enumerate(lines, 1):
        if any(match.group(1).lower() != "example.invalid" for match in email.finditer(line)):
            violations.append(f"{path}:{number}: email-like value")
        if any(match.group(0).replace(" ", "").replace("-", "") != "0000000000"
               for match in phone.finditer(line)):
            violations.append(f"{path}:{number}: phone-like value")

if violations:
    print("\n".join(violations), file=sys.stderr)
    sys.exit(1)
PY
pass "candidate-tree PII guard"

grep -Fxq \
  'disable_functions = mail,mb_send_mail,curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,popen,proc_open,exec,shell_exec,system,passthru' \
  web/config/outbound-messaging-deny.ini \
  || fail "outbound function deny list changed"
grep -Fxq 'allow_url_fopen = Off' web/config/outbound-messaging-deny.ini \
  || fail "allow_url_fopen is not disabled"
grep -Fxq 'sendmail_path = /bin/false' web/config/outbound-messaging-deny.ini \
  || fail "sendmail path is not disabled"
grep -Fq 'COPY --from=ws config/outbound-messaging-deny.ini' web/Dockerfile \
  || fail "Docker image does not install outbound deny policy"
pass "outbound email/SMS transports denied"

grep -Fq '"[REDACTED]"' db/dbctl.sh \
  || fail "Compose evidence secret redaction is missing"
pass "Compose evidence secret redaction"

grep -Fxq '    ErrorLog /proc/self/fd/2' web/apache/ci3.conf \
  || fail "Apache error log does not reach docker logs"
grep -Fxq '    CustomLog /proc/self/fd/1 combined' web/apache/ci3.conf \
  || fail "Apache access log does not reach docker logs"
pass "Apache container logging"

safe_preview=$(sed -n '/^safe_preview_smoke()/,/^}/p' db/dbctl.sh)
preview_routes=$(printf '%s\n' "$safe_preview" \
  | grep -o '\$WEB_BASE/[A-Za-z0-9_-]*' | sort -u)
expected_routes=$(printf '%s\n' \
  '$WEB_BASE/ExcelDataAdd' \
  '$WEB_BASE/ExcelNewOrderDataAdd' \
  '$WEB_BASE/ExcelPriceDataAdd' \
  '$WEB_BASE/loginMe')
[ "$preview_routes" = "$expected_routes" ] \
  || fail "safe preview HTTP route allowlist changed"
if printf '%s\n' "$safe_preview" \
     | grep -qE '(\$WEB_BASE/|"/)(ExcelConfirm|ExcelPriceConfirm|ExcelNewOrderConfirm|forgotPassword|resetPasswordUser)'; then
  fail "safe preview invokes a confirm or messaging route"
fi
preview_urls=$(printf '%s\n' "$safe_preview" \
  | grep -oE 'https?://[^"[:space:]]+' | sort -u)
[ "$preview_urls" = 'http://127.0.0.1:$WEB_HOST_PORT' ] \
  || fail "safe preview URL allowlist changed"
grep -Fxq '  excel-preview-smoke) safe_preview_smoke ;;' db/dbctl.sh \
  || fail "legacy Excel preview command no longer uses the PII-safe gate"
pass "safe preview route allowlist"

safe_confirm=$(sed -n '/^safe_confirm_smoke()/,/^}/p' db/dbctl.sh)
confirm_routes=$(printf '%s\n' "$safe_confirm" \
  | grep -o '\$WEB_BASE/[A-Za-z0-9_-]*' | sort -u)
expected_confirm_routes=$(printf '%s\n' \
  '$WEB_BASE/ExcelConfirm' \
  '$WEB_BASE/ExcelNewOrderConfirm' \
  '$WEB_BASE/ExcelPriceConfirm')
[ "$confirm_routes" = "$expected_confirm_routes" ] \
  || fail "safe confirm HTTP route allowlist changed"
printf '%s\n' "$safe_confirm" | grep -Fq 'safe_preview_smoke' \
  || fail "safe confirm no longer reuses synthetic preview setup"
printf '%s\n' "$safe_confirm" | grep -Fq 'assert_outbound_messaging_denied' \
  || fail "safe confirm does not enforce outbound deny policy"
if printf '%s\n' "$safe_confirm" \
     | grep -qiE 'forgotPassword|resetPasswordUser|THAIBULK'; then
  fail "safe confirm contains a messaging path"
fi
confirm_urls=$(printf '%s\n' "$safe_confirm" \
  | grep -oE 'https?://[^"[:space:]]+' | sort -u || true)
[ -z "$confirm_urls" ] || fail "safe confirm contains an external URL"
grep -Fxq '  safe-confirm-smoke) safe_confirm_smoke ;;' db/dbctl.sh \
  || fail "safe confirm command is not wired to its isolated gate"
pass "safe confirm route and outbound allowlist"

grep -Fxq '  safe-confirm-negative-smoke) safe_confirm_negative_smoke ;;' db/dbctl.sh \
  || fail "safe confirm negative command is not wired to its isolated gate"
safe_confirm_negative=$(sed -n '/^safe_confirm_negative_smoke()/,/^}/p' db/dbctl.sh)
negative_routes=$(printf '%s\n' "$safe_confirm_negative" \
  | grep -o '\$WEB_BASE/[A-Za-z0-9_-]*' | sort -u)
expected_negative_routes=$(printf '%s\n' \
  '$WEB_BASE/ExcelConfirm' \
  '$WEB_BASE/ExcelNewOrderConfirm' \
  '$WEB_BASE/ExcelNewOrderDataAdd' \
  '$WEB_BASE/ExcelPriceConfirm' \
  '$WEB_BASE/loginMe')
[ "$negative_routes" = "$expected_negative_routes" ] \
  || fail "safe confirm negative HTTP route allowlist changed"
printf '%s\n' "$safe_confirm_negative" | grep -Fq 'safe_preview_smoke' \
  || fail "safe confirm negative no longer reuses synthetic preview setup"
printf '%s\n' "$safe_confirm_negative" | grep -Fq 'assert_outbound_messaging_denied' \
  || fail "safe confirm negative does not enforce outbound deny policy"
if printf '%s\n' "$safe_confirm_negative" \
     | grep -qiE 'forgotPassword|resetPasswordUser|THAIBULK'; then
  fail "safe confirm negative contains a messaging path"
fi
negative_urls=$(printf '%s\n' "$safe_confirm_negative" \
  | grep -oE 'https?://[^"[:space:]]+' | sort -u || true)
[ -z "$negative_urls" ] || fail "safe confirm negative contains an external URL"
printf '%s\n' "$safe_confirm_negative" | grep -Fq 'CSRF token absent from preview form' \
  || fail "safe confirm negative does not characterize missing CSRF protection"
printf '%s\n' "$safe_confirm_negative" \
  | grep -Fq 'two-user overlapping-session isolation defect reproduced' \
  || fail "safe confirm negative does not characterize two-user batch isolation"
pass "safe confirm negative route and outbound allowlist"

disabled_tests=$(git grep -n -I -E '\.(only|skip)\(' -- '*.php' '*.js' '*.mjs' '*.ts' 2>/dev/null || true)
[ -z "$disabled_tests" ] || fail "committed test contains .only or .skip"

case "$#" in
  0)
    git diff --check
    ;;
  2)
    [[ "$1" =~ ^[0-9a-f]{40,64}$ && "$2" =~ ^[0-9a-f]{40,64}$ ]] \
      || fail "CI diff bounds must be commit SHAs"
    git cat-file -e "$2^{commit}" || fail "CI head commit is unavailable"
    if [[ "$1" =~ ^0+$ ]]; then
      git show --check --format= "$2"
    else
      git cat-file -e "$1^{commit}" || fail "CI base commit is unavailable"
      git diff --check "$1...$2"
    fi
    ;;
  *)
    fail "usage: scripts/ci-check.sh [BASE_SHA HEAD_SHA]"
    ;;
esac
pass "repository safety gate"
