#!/usr/bin/env bash
# Remove real personal data from the local migration rehearsal only.
# Keeps a validated schema-only dump, then recreates an empty database.
set -Eeuo pipefail
umask 077

PROJECT="samsonitetracking-ci4-migration"
CONFIRM_PHRASE="DELETE-LOCAL-REAL-PII"
ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
SCHEMA_OUT="$ROOT/db/local-schema-only.sql"
SCHEMA_TMP=""
RUNTIME_ROOT="$ROOT"

DUMP_FILES=(
  samsonitetracking_path_001.sql
  samsonitetracking_rating_001.sql
  samsonitetracking_request_order_001.sql
  samsonitetracking_status_log_001.sql
  samsonitetracking_tbl_last_login_001.sql
  samsonitetracking_uploadstaus_001.sql
)

die()  { echo "FAIL: $*" >&2; exit 1; }
note() { echo "==> $*"; }

usage() {
  cat <<EOF
usage: db/privacy-purge-local.sh [--runtime-root ABSOLUTE_PATH] $CONFIRM_PHRASE

Irreversible local-only purge:
  - export and validate schema only (no rows)
  - remove this Compose project's containers, logs, database volume, and backup volume
  - recreate an empty database from the schema-only dump
  - delete exact production dump files listed in this script
  - delete files under CI3 uploads/ and demo/uploads/

No production host, email, SMS, or application confirm endpoint is contacted.
EOF
}

cleanup() {
  if [[ -n "$SCHEMA_TMP" && -f "$SCHEMA_TMP" ]]; then
    rm -f -- "$SCHEMA_TMP"
  fi
  rmdir "$LOCK" 2>/dev/null || true
}

if [[ "${1:-}" == "--runtime-root" ]]; then
  [[ -n "${2:-}" ]] || die "--runtime-root requires an absolute path"
  RUNTIME_ROOT=$2
  shift 2
fi

[[ "$#" -eq 1 && "${1:-}" == "$CONFIRM_PHRASE" ]] || { usage; exit 2; }
[[ "$RUNTIME_ROOT" == /* ]] || die "runtime root must be absolute"
[[ -d "$RUNTIME_ROOT" ]] || die "runtime root not found: $RUNTIME_ROOT"
RUNTIME_ROOT=$(cd -- "$RUNTIME_ROOT" && pwd -P)
[[ "$RUNTIME_ROOT" != "/" ]] || die "runtime root resolves to root"
COMPOSE="$RUNTIME_ROOT/compose.yaml"
ENVFILE="$RUNTIME_ROOT/.env"
LOCK="$RUNTIME_ROOT/.dbctl.lock"

[[ -f "$COMPOSE" ]] || die "missing $COMPOSE"
[[ -f "$ENVFILE" ]] \
  || die "missing $ENVFILE; pass --runtime-root for the dedicated runtime worktree"

. "$ENVFILE"

: "${COMPOSE_PROJECT_NAME:?COMPOSE_PROJECT_NAME is required}"
: "${MARIADB_DATABASE:?MARIADB_DATABASE is required}"
: "${MARIADB_ROOT_PASSWORD:?MARIADB_ROOT_PASSWORD is required}"
: "${DUMP_DIR:?DUMP_DIR is required}"
: "${CI3_SOURCE_ROOT:?CI3_SOURCE_ROOT is required}"

[[ "$COMPOSE_PROJECT_NAME" == "$PROJECT" ]] \
  || die "COMPOSE_PROJECT_NAME must be $PROJECT"
[[ "$MARIADB_DATABASE" =~ ^[A-Za-z0-9_]+$ ]] \
  || die "MARIADB_DATABASE contains unsafe characters"
[[ "$DUMP_DIR" == /* ]] || die "DUMP_DIR must be absolute"
[[ "$CI3_SOURCE_ROOT" == /* ]] || die "CI3_SOURCE_ROOT must be absolute"

command -v docker >/dev/null || die "docker not found"
command -v git >/dev/null || die "git not found"
command -v realpath >/dev/null || die "realpath not found"
docker_context=$(docker context show)
docker_endpoint=${DOCKER_HOST:-$(docker context inspect "$docker_context" \
  --format '{{.Endpoints.docker.Host}}')}
[[ "$docker_endpoint" == unix://* ]] \
  || die "Docker endpoint is not local Unix socket: $docker_endpoint"
docker info >/dev/null 2>&1 || die "Docker is unavailable"

mkdir "$LOCK" 2>/dev/null || die "another dbctl/privacy purge run holds $LOCK"
trap cleanup EXIT

dc() {
  docker compose --env-file "$ENVFILE" -p "$PROJECT" -f "$COMPOSE" "$@"
}

sql() {
  dc exec -T db sh -c '
    export MYSQL_PWD="$MARIADB_ROOT_PASSWORD"
    exec mariadb --default-character-set=utf8mb4 -u root -N -B "$@"
  ' sh "$@"
}

validate_project_resources() {
  local resource owner container_id container_name

  while read -r container_id container_name; do
    case "$container_name" in
      "$PROJECT"-*|"$PROJECT"_*)
        owner=$(docker container inspect "$container_id" \
          --format '{{index .Config.Labels "com.docker.compose.project"}}')
        [[ "$owner" == "$PROJECT" ]] \
          || die "container $container_name is owned by '$owner', not $PROJECT"
        ;;
    esac
  done < <(docker ps -a --format '{{.ID}} {{.Names}}')

  for resource in $(docker volume ls -q | grep "^${PROJECT}_" || true); do
    owner=$(docker volume inspect "$resource" \
      --format '{{index .Labels "com.docker.compose.project"}}')
    [[ "$owner" == "$PROJECT" ]] \
      || die "volume $resource is owned by '$owner', not $PROJECT"
  done

  for resource in $(docker network ls --format '{{.Name}}' | grep "^${PROJECT}_" || true); do
    owner=$(docker network inspect "$resource" \
      --format '{{index .Labels "com.docker.compose.project"}}')
    [[ "$owner" == "$PROJECT" ]] \
      || die "network $resource is owned by '$owner', not $PROJECT"
  done
}

validate_upload_root() {
  local expected=$1 resolved symlinks
  [[ -d "$expected" ]] || return 0
  resolved=$(realpath "$expected")
  [[ "$resolved" == "$expected" ]] || die "upload root resolves outside exact target: $expected"
  symlinks=$(find -P "$resolved" -type l -print | wc -l | tr -d ' ')
  [[ "$symlinks" == "0" ]] || die "symlink found under upload root: $expected"
}

delete_upload_files() {
  local target=$1 before after
  [[ -d "$target" ]] || return 0
  before=$(find -P "$target" -type f -print | wc -l | tr -d ' ')
  find -P "$target" -type f -delete
  after=$(find -P "$target" -type f -print | wc -l | tr -d ' ')
  [[ "$after" == "0" ]] || die "files remain under $target"
  note "deleted $before upload file(s) under $target"
}

note "validating local-only targets"
dc config --quiet
validate_project_resources

rendered=$(mktemp "$ROOT/db/.privacy-compose.XXXXXX")
SCHEMA_TMP=$rendered
dc config --no-interpolate > "$rendered"
published_ports=$(grep -c 'published:' "$rendered" || true)
loopback_ports=$(grep -c 'host_ip: 127.0.0.1' "$rendered" || true)
[[ "$published_ports" -gt 0 && "$published_ports" == "$loopback_ports" ]] \
  || die "every published port must bind to 127.0.0.1"
if grep -Eq 'container_name:|external: true|type: bind|docker\.sock|privileged: true|network_mode:' "$rendered"; then
  die "Compose contains forbidden external or privileged configuration"
fi
rm -f -- "$rendered"
SCHEMA_TMP=""

CI3_REAL=$(realpath "$CI3_SOURCE_ROOT")
CI3_GIT_ROOT=$(git -C "$CI3_REAL" rev-parse --show-toplevel)
[[ "$CI3_REAL" == "$CI3_GIT_ROOT" ]] || die "CI3_SOURCE_ROOT is not a repository root"
UPLOAD_ROOT="$CI3_REAL/uploads"
DEMO_UPLOAD_ROOT="$CI3_REAL/demo/uploads"
validate_upload_root "$UPLOAD_ROOT"
validate_upload_root "$DEMO_UPLOAD_ROOT"

DUMP_REAL=$(realpath "$DUMP_DIR")
[[ "$DUMP_REAL" != "/" && "$DUMP_REAL" != "$ROOT" && "$DUMP_REAL" != "$CI3_REAL" ]] \
  || die "DUMP_DIR resolves to a forbidden broad target"
USER_HOME_REAL=$(realpath "${HOME:?HOME is required}")
[[ "$USER_HOME_REAL" != "/" ]] || die "HOME resolves to root"
case "$DUMP_REAL" in
  "$USER_HOME_REAL"/*) ;;
  *) die "DUMP_DIR must be under current user home" ;;
esac

for dump_file in "${DUMP_FILES[@]}"; do
  [[ "$dump_file" =~ ^[A-Za-z0-9_]+\.sql$ ]] || die "unsafe dump filename"
  dump_path="$DUMP_REAL/$dump_file"
  [[ -f "$dump_path" ]] || die "missing expected dump: $dump_path"
  [[ ! -L "$dump_path" ]] || die "expected dump is a symlink: $dump_path"
  [[ "$(realpath "$dump_path")" == "$dump_path" ]] \
    || die "expected dump resolves outside exact target: $dump_path"
done

potential_artifacts=$(find -P "$DUMP_REAL" -type f \( \
  -iname '*.sql' -o -iname '*.sql.gz' -o -iname '*.csv' -o \
  -iname '*.xls' -o -iname '*.xlsx' -o -iname '*.zip' -o \
  -iname '*.tar' -o -iname '*.tgz' -o -iname '*.7z' -o -iname '*.rar' \
  \) -print | wc -l | tr -d ' ')
[[ "$potential_artifacts" == "${#DUMP_FILES[@]}" ]] \
  || die "DUMP_DIR has $potential_artifacts potential data artifacts; expected exactly ${#DUMP_FILES[@]}"

note "starting only local database long enough to export schema"
dc up -d --wait db
live_tables=$(sql -e "SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA='$MARIADB_DATABASE' AND TABLE_TYPE='BASE TABLE';")
[[ "$live_tables" =~ ^[0-9]+$ && "$live_tables" -gt 0 ]] \
  || die "live database has no base tables"
non_table_objects=$(sql -e "SELECT
  (SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA='$MARIADB_DATABASE') +
  (SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$MARIADB_DATABASE') +
  (SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='$MARIADB_DATABASE') +
  (SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA='$MARIADB_DATABASE');")
[[ "$non_table_objects" == "0" ]] \
  || die "database has $non_table_objects view/trigger/routine/event object(s); manual schema review required"

SCHEMA_TMP=$(mktemp "$ROOT/db/.local-schema-only.XXXXXX")
dc exec -T db sh -c '
  export MYSQL_PWD="$MARIADB_ROOT_PASSWORD"
  exec mariadb-dump -u root --default-character-set=utf8mb4 --no-data --skip-comments --skip-triggers \
    --databases "$MARIADB_DATABASE"
' > "$SCHEMA_TMP"

schema_tables=$(grep -c '^CREATE TABLE' "$SCHEMA_TMP" || true)
[[ "$schema_tables" == "$live_tables" ]] \
  || die "schema export has $schema_tables tables; live database has $live_tables"
if grep -Eiq '^(INSERT|REPLACE)[[:space:]]+INTO|^LOAD[[:space:]]+DATA' "$SCHEMA_TMP"; then
  die "schema export unexpectedly contains data statements"
fi
if grep -Eiq '[[:alnum:]._%+-]+@[[:alnum:].-]+\.[[:alpha:]]{2,}' "$SCHEMA_TMP"; then
  die "schema export unexpectedly contains an email-like value"
fi
chmod 600 "$SCHEMA_TMP"
mv -f -- "$SCHEMA_TMP" "$SCHEMA_OUT"
SCHEMA_TMP=""
schema_hash=$(shasum -a 256 "$SCHEMA_OUT" | awk '{print $1}')
note "schema-only dump validated: tables=$schema_tables sha256=${schema_hash:0:16}..."

note "removing only $PROJECT containers, logs, database volume, and backup volume"
dc down --volumes --remove-orphans

remaining_containers=$(docker ps -aq \
  --filter "label=com.docker.compose.project=$PROJECT" | wc -l | tr -d ' ')
remaining_volumes=$(docker volume ls -q \
  --filter "label=com.docker.compose.project=$PROJECT" | wc -l | tr -d ' ')
remaining_networks=$(docker network ls -q \
  --filter "label=com.docker.compose.project=$PROJECT" | wc -l | tr -d ' ')
[[ "$remaining_containers" == "0" && "$remaining_volumes" == "0" \
   && "$remaining_networks" == "0" ]] \
  || die "project resources remain: containers=$remaining_containers volumes=$remaining_volumes networks=$remaining_networks"

delete_upload_files "$UPLOAD_ROOT"
delete_upload_files "$DEMO_UPLOAD_ROOT"

note "recreating empty local database from schema only"
dc up -d --wait db
dc exec -T db sh -c '
  export MYSQL_PWD="$MARIADB_ROOT_PASSWORD"
  exec mariadb --default-character-set=utf8mb4 -u root
' < "$SCHEMA_OUT"

rebuilt_tables=$(sql -e "SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA='$MARIADB_DATABASE' AND TABLE_TYPE='BASE TABLE';")
[[ "$rebuilt_tables" == "$live_tables" ]] \
  || die "rebuilt database has $rebuilt_tables tables; expected $live_tables"

total_rows=0
while IFS= read -r table_name; do
  [[ "$table_name" =~ ^[A-Za-z0-9_]+$ ]] || die "unsafe table name from information_schema"
  row_count=$(sql "$MARIADB_DATABASE" -e "SELECT COUNT(*) FROM \`$table_name\`;")
  [[ "$row_count" =~ ^[0-9]+$ ]] || die "invalid row count for $table_name"
  total_rows=$((total_rows + row_count))
done < <(sql -e "SELECT TABLE_NAME FROM information_schema.TABLES
  WHERE TABLE_SCHEMA='$MARIADB_DATABASE' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;")
[[ "$total_rows" == "0" ]] || die "rebuilt database still contains $total_rows row(s)"

backup_files=$(dc exec -T db sh -c \
  "find /backup -type f -print | wc -l" | tr -d '[:space:]')
[[ "$backup_files" == "0" ]] || die "new backup volume contains $backup_files file(s)"

note "empty schema verified; deleting exact production source dumps"
for dump_file in "${DUMP_FILES[@]}"; do
  rm -f -- "$DUMP_REAL/$dump_file"
  [[ ! -e "$DUMP_REAL/$dump_file" ]] || die "failed to delete $DUMP_REAL/$dump_file"
done

remaining_artifacts=$(find -P "$DUMP_REAL" -type f \( \
  -iname '*.sql' -o -iname '*.sql.gz' -o -iname '*.csv' -o \
  -iname '*.xls' -o -iname '*.xlsx' -o -iname '*.zip' -o \
  -iname '*.tar' -o -iname '*.tgz' -o -iname '*.7z' -o -iname '*.rar' \
  \) -print | wc -l | tr -d ' ')
[[ "$remaining_artifacts" == "0" ]] \
  || die "DUMP_DIR still contains $remaining_artifacts potential data artifact(s)"

note "PASS local PII purge: schema=$rebuilt_tables tables rows=0 backups=0 uploads=0 dumps=0"
note "web service remains stopped; no login account was created"
