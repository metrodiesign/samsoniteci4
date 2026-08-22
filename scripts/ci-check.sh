#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
cd "$ROOT"

fail() { echo "FAIL: $*" >&2; exit 1; }
pass() { echo "PASS $*"; }

bash -n db/dbctl.sh db/privacy-purge-local.sh
pass "shell syntax"

schema_tables=$(grep -c '^CREATE TABLE' db/local-schema-only.sql || true)
[ "$schema_tables" = 31 ] || fail "schema table count is $schema_tables, expected 31"
if grep -Eiq '^[[:space:]]*(INSERT|REPLACE)[[:space:]]+INTO|^[[:space:]]*LOAD[[:space:]]+DATA' \
     db/local-schema-only.sql; then
  fail "schema-only SQL contains data statements"
fi
pass "schema-only SQL: tables=31 data statements=0"

python3 scripts/wp00c-kit.py validate-data >/dev/null
python3 -m py_compile scripts/wp00c-route-auth.py
for command in validate seed verify clean; do
  grep -Fq "  wp00c-fixture-$command)" db/dbctl.sh \
    || fail "WP-00C fixture command is missing: $command"
done
pass "WP-00C catalog and synthetic fixture kit"

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

tracked_sensitive=$(git ls-files | grep -E \
  '(^|/)\.env($|\.)|(^|/)(auth|credentials)\.json$|\.(pem|key)$|(^|/)SECRETS-LOCAL\.md$' \
  | grep -v '^\.env\.example$' || true)
[ -z "$tracked_sensitive" ] || {
  printf '%s\n' "$tracked_sensitive" >&2
  fail "sensitive filename is tracked"
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

for raw_path in subprocess.check_output(["git", "ls-files", "-z"]).split(b"\0"):
    if not raw_path:
        continue
    path = pathlib.Path(raw_path.decode())
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
pass "tracked-tree PII guard"

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
