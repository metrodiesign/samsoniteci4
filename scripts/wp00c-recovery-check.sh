#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
JUNIT_OUTPUT=""
if [ "$#" -gt 0 ]; then
  [ "$#" = 2 ] && [ "$1" = --junit ] && [ -n "$2" ] \
    || { echo 'usage: scripts/wp00c-recovery-check.sh [--junit PATH]' >&2; exit 2; }
  JUNIT_OUTPUT=$2
fi

export DOCKER_CONFIG=${DOCKER_CONFIG:-/tmp/samsonite-ci4-docker-config}
DB_IMAGE='mariadb:11.4.12@sha256:67873d30a17f6a9c331f06363b2fa15f38abca415529966d67c84f87f82439fe'
CI3_SOURCE_ROOT=${CI3_SOURCE_ROOT:-"$ROOT/../samsoniteci3"}
EXPECTED_SOURCE_STATE='ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6 0'
DATABASE=samsonitetracking
PASSWORD='synthetic-recovery-only'
suffix="${BASHPID:-$$}-$(date +%s)"
container="wp00c-recovery-db-${suffix}"
scratch=$(mktemp -d "${TMPDIR:-/tmp}/wp00c-recovery.XXXXXX")
generated=(excel-status excel-price excel-new-order image-valid image-invalid)

cleanup() {
  docker rm -f "$container" >/dev/null 2>&1 || true
  find "$scratch" -mindepth 1 -type f -delete 2>/dev/null || true
  rmdir "$scratch" >/dev/null 2>&1 || true
}
trap cleanup EXIT

fail() { echo "FAIL: $*" >&2; exit 1; }
source_state() {
  printf '%s %s\n' \
    "$(git -C "$CI3_SOURCE_ROOT" rev-parse HEAD)" \
    "$(git -C "$CI3_SOURCE_ROOT" status --short | wc -l | tr -d ' ')"
}
db_query() {
  docker exec "$container" sh -c \
    'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb -uroot -N -B samsonitetracking -e "$1"' \
    sh "$1"
}
server_query() {
  docker exec "$container" sh -c \
    'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb -uroot -N -B -e "$1"' \
    sh "$1"
}
db_import() {
  docker exec -i "$container" sh -c \
    'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb -uroot samsonitetracking'
}
exact_rows() {
  local table rows total=0 tables
  tables=$(db_query "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DATABASE' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;")
  while IFS= read -r table; do
    [ -n "$table" ] || continue
    case "$table" in *[!A-Za-z0-9_]*) fail "unsafe table name: $table" ;; esac
    rows=$(db_query "SELECT COUNT(*) FROM \`$table\`;")
    case "$rows" in ''|*[!0-9]*) fail "invalid row count for $table" ;; esac
    total=$((total + rows))
  done <<< "$tables"
  echo "$total"
}
verify_schema() {
  [ "$(db_query "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DATABASE' AND TABLE_TYPE='BASE TABLE';")" = 31 ] \
    || fail 'schema table count differs from 31'
  [ "$(db_query "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DATABASE' AND TABLE_TYPE='BASE TABLE' AND (ENGINE<>'InnoDB' OR ROW_FORMAT<>'Dynamic' OR TABLE_COLLATION<>'utf8mb4_general_ci');")" = 0 ] \
    || fail 'schema engine, row format, or collation drift'
  [ "$(db_query "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DATABASE' AND COLLATION_NAME IS NOT NULL AND COLLATION_NAME<>'utf8mb4_general_ci';")" = 0 ] \
    || fail 'column collation drift'
}

mkdir -p "$DOCKER_CONFIG"
[ -d "$CI3_SOURCE_ROOT/.git" ] || fail "CI3 source missing: $CI3_SOURCE_ROOT"
before=$(source_state)
[ "$before" = "$EXPECTED_SOURCE_STATE" ] || fail "CI3 source state differs: $before"
python3 "$ROOT/scripts/wp00c-kit.py" validate --source-root "$CI3_SOURCE_ROOT" >/dev/null

docker run --detach --rm \
  --name "$container" \
  --tmpfs /var/lib/mysql:rw,noexec,nosuid,size=512m \
  --env MARIADB_ROOT_PASSWORD="$PASSWORD" \
  --env MARIADB_DATABASE="$DATABASE" \
  "$DB_IMAGE" \
  --character-set-server=utf8mb4 \
  --collation-server=utf8mb4_general_ci \
  --character-set-collations=utf8mb4=utf8mb4_general_ci \
  --sql-mode=NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION \
  --innodb-default-row-format=dynamic >/dev/null

ready=0
for _ in $(seq 1 45); do
  if docker exec "$container" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then
    ready=1
    break
  fi
  sleep 1
done
[ "$ready" = 1 ] || fail 'isolated MariaDB did not become ready'

db_import < "$ROOT/db/local-schema-only.sql"
verify_schema
[ "$(exact_rows)" = 0 ] || fail 'isolated database did not start empty'

docker exec "$container" sh -c \
  'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec mariadb-dump --no-data --skip-comments --no-tablespaces -uroot samsonitetracking' \
  > "$scratch/empty-schema.sql"
backup_id="wp00c-empty-$(shasum -a 256 "$scratch/empty-schema.sql" | awk '{print substr($1,1,16)}')"

for file in "${generated[@]}"; do
  touch "$scratch/$file"
done
seed_hash_hex='243279243132244141414141414141414141414141414141414141414141414141414141414141414141414141414141414141414141414141414141'
python3 "$ROOT/scripts/wp00c-kit.py" render-seed-sql --password-hash-hex "$seed_hash_hex" | db_import

while IFS=$'\t' read -r table expected; do
  actual=$(db_query "SELECT COUNT(*) FROM \`$table\`;")
  [ "$actual" = "$expected" ] || fail "$table rows: expected $expected got $actual"
done < <(python3 "$ROOT/scripts/wp00c-kit.py" expected-counts)
[ "$(exact_rows)" = 116 ] || fail 'fixture total differs from 116 rows'

for file in "${generated[@]}"; do
  unlink "$scratch/$file"
done
python3 "$ROOT/scripts/wp00c-kit.py" render-cleanup-sql | db_import
[ "$(exact_rows)" = 0 ] || fail 'fixture cleanup left database rows'
[ "$(find "$scratch" -maxdepth 1 -type f \( -name 'excel-*' -o -name 'image-*' \) | wc -l | tr -d ' ')" = 0 ] \
  || fail 'fixture cleanup left generated files'

server_query "DROP DATABASE \`$DATABASE\`; CREATE DATABASE \`$DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
db_import < "$scratch/empty-schema.sql"
verify_schema
[ "$(exact_rows)" = 0 ] || fail 'isolated restore did not return to zero rows'
after=$(source_state)
[ "$after" = "$before" ] || fail "CI3 source changed: $before -> $after"

if [ -n "$JUNIT_OUTPUT" ]; then
  python3 - "$JUNIT_OUTPUT" <<'PY'
import pathlib
import sys
import xml.etree.ElementTree as ET

target = pathlib.Path(sys.argv[1])
target.parent.mkdir(parents=True, exist_ok=True)
suite = ET.Element("testsuite", name="WP-00C recovery", tests="1", failures="0")
ET.SubElement(
    suite,
    "testcase",
    {
        "class": "Tests\\Operational\\RecoveryCheck",
        "name": "testFixtureSeedCleanupAndRestore",
        "assertions": "12",
    },
)
root = ET.Element("testsuites")
root.append(suite)
ET.indent(root)
ET.ElementTree(root).write(target, encoding="utf-8", xml_declaration=True)
PY
fi

echo "PASS recovery source_state=$after"
echo "PASS recovery backup_id=$backup_id seed_rows=116 cleanup_rows=0 restore_tables=31 restore_rows=0 generated_files=0"
