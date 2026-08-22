#!/usr/bin/env bash
#
# dbctl.sh - lifecycle control for the samsonitetracking CI4 migration database.
#
# Host isolation rules this script enforces (see outputs/reference/*db-foundation-runbook*):
#   - dc() is the ONLY place a state-changing docker command is issued, and it always
#     passes an exact -p / -f pair. Never rely on cwd inference.
#   - Other projects on this host are read-only. No prune, no `down --volumes`, no
#     `docker volume rm`, no wildcard filters, no global stop/rm anywhere in this file.
#   - Port conflict means pick another free port, never touch the port's owner.
#
set -Eeuo pipefail
umask 077

PROJECT=samsonitetracking-ci4-migration
ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
RUNTIME_ROOT="$ROOT"
if [ "${1:-}" = "--runtime-root" ]; then
  [ -n "${2:-}" ] || { echo "FAIL: --runtime-root requires an absolute path" >&2; exit 2; }
  RUNTIME_ROOT=$2
  shift 2
fi
[ "${RUNTIME_ROOT#/}" != "$RUNTIME_ROOT" ] \
  || { echo "FAIL: runtime root must be absolute" >&2; exit 2; }
[ -d "$RUNTIME_ROOT" ] || { echo "FAIL: runtime root not found: $RUNTIME_ROOT" >&2; exit 2; }
RUNTIME_ROOT=$(cd -- "$RUNTIME_ROOT" && pwd -P)
[ "$RUNTIME_ROOT" != / ] || { echo "FAIL: runtime root resolves to root" >&2; exit 2; }
COMPOSE="$ROOT/compose.yaml"
ENVFILE="${DBCTL_ENV_FILE:-$RUNTIME_ROOT/.env}"
EV="${DBCTL_EVIDENCE_DIR:-$RUNTIME_ROOT/evidence/db-foundation-001}"
ISO="$EV/19-docker-isolation"
BASE="$EV/01-baseline"
MANIFEST="$EV/00-manifest"
# Env file identifies runtime owner. Worktrees sharing that runtime must share one lock.
LOCK="${DBCTL_LOCK_DIR:-$(dirname "$ENVFILE")/.dbctl.lock}"

# Fallback pool. 18404 and 18405-18419 are deliberately excluded: reserved as the web
# port and its fallback range by port record PORT-CI4-LOCAL-001.
PORT_POOL="13306 13307 13308 18306"

DUMP_ORDER="
samsonitetracking_path_001.sql
samsonitetracking_rating_001.sql
samsonitetracking_request_order_001.sql
samsonitetracking_status_log_001.sql
samsonitetracking_tbl_last_login_001.sql
samsonitetracking_uploadstaus_001.sql
"

[ -f "$ENVFILE" ] || { echo "missing $ENVFILE (copy .env.example)"; exit 2; }
. "$ENVFILE"
DB=${MARIADB_DATABASE:?}
DUMP_DIR=${DUMP_DIR:?}

mkdir -p "$ISO" "$BASE" "$MANIFEST"

die()  { echo "FAIL: $*" >&2; exit 1; }
note() { echo "==> $*"; }

# Re-entrant within one run: rehearsal calls backup and restore, which each want the lock.
# LOCK_HELD is inherited by subshells, so a nested call becomes a no-op instead of a failure.
LOCK_HELD=0
lock() {
  [ "$LOCK_HELD" = 1 ] && return 0
  mkdir "$LOCK" 2>/dev/null || die "another dbctl run holds $LOCK"
  LOCK_HELD=1
  trap 'rmdir "$LOCK" 2>/dev/null || true' EXIT
}

# Single choke point for docker. Always scoped to this project and this compose file.
dc() { docker compose --env-file "$ENVFILE" -p "$PROJECT" -f "$COMPOSE" "$@"; }

sql() {
  dc exec -T -e MYSQL_PWD="$MARIADB_ROOT_PASSWORD" db \
    mariadb --default-character-set=utf8mb4 --init-command="SET time_zone='+00:00'" \
            -u root -N -B "$@"
}
sqldb() { sql "$DB" "$@"; }

port_busy() {
  nc -z 127.0.0.1 "$1" >/dev/null 2>&1 && return 0
  docker ps -a --format '{{.Ports}}' | grep -q "127\.0\.0\.1:$1->" && return 0
  return 1
}

# ---------------------------------------------------------------- snapshot / diff

snapshot() {
  local tag=$1
  docker ps -a --no-trunc \
    --format '{{.ID}}|{{.Names}}|{{.Image}}|{{.State}}|{{.Ports}}|{{.Label "com.docker.compose.project"}}' \
    | sort > "$ISO/$tag-containers.txt"
  docker network ls --no-trunc --format '{{.ID}}|{{.Name}}|{{.Driver}}|{{.Scope}}' \
    | sort > "$ISO/$tag-networks.txt"
  docker volume ls --format '{{.Name}}|{{.Driver}}' | sort > "$ISO/$tag-volumes.txt"
  docker compose ls --all --format json > "$ISO/$tag-projects.json"
  # Drop the OS ephemeral range (49152-65535 on this host): those listeners belong to
  # transient host processes and churn between snapshots. A published Docker port can never
  # live there - the port protocol requires candidates outside the ephemeral range.
  netstat -an -f inet -p tcp \
    | awk '$NF=="LISTEN"{n=split($4,a,"."); if (a[n]+0 < 49152) print $4}' \
    | sort -u > "$ISO/$tag-ports.txt"
  note "snapshot '$tag' written to $ISO"
}

diff_hosts() {
  local rc=0 f
  : > "$ISO/noninterference.diff"
  # Lines this project is allowed to own: anything carrying the project name, plus the
  # loopback ports it publishes. Everything else must be byte-identical.
  local mine="$PROJECT|^127\.0\.0\.1\.$DB_HOST_PORT\$|^127\.0\.0\.1\.${WEB_HOST_PORT:-18404}\$"
  for f in containers networks volumes ports; do
    [ -f "$ISO/before-$f.txt" ] || die "missing before-$f.txt; run 'snapshot before' first"
    [ -f "$ISO/after-$f.txt" ]  || die "missing after-$f.txt; run 'snapshot after' first"
    diff -u <(grep -vE "$mine" "$ISO/before-$f.txt") \
            <(grep -vE "$mine" "$ISO/after-$f.txt") \
      >> "$ISO/noninterference.diff" 2>&1 || rc=1
  done
  if [ "$rc" -ne 0 ]; then
    echo "--- unrelated resources changed ---"
    cat "$ISO/noninterference.diff"
    die "non-interference check failed; investigate before continuing"
  fi
  note "PASS non-interference: no unrelated container/network/volume/port changed"
}

# ---------------------------------------------------------------- preflight

preflight() {
  local chosen="" p mine=""
  # A running db of ours already occupies its port, which would otherwise read as "busy"
  # and make preflight hop to the next pool entry on every re-run. Keep our own port.
  mine=$(dc port db 3306 2>/dev/null | sed 's/.*://') || true
  for p in $PORT_POOL; do
    if [ -n "$mine" ] && [ "$p" = "$mine" ]; then
      note "port $p is already published by this project, keeping it"
      chosen=$p; break
    fi
  done
  for p in $PORT_POOL; do
    [ -z "$chosen" ] || break
    if port_busy "$p"; then
      note "port $p busy, skipping (owner untouched)"
    else
      chosen=$p; break
    fi
  done
  [ -n "$chosen" ] || die "every port in pool ($PORT_POOL) is taken; stop and pick a new pool"
  if [ "$chosen" != "${DB_HOST_PORT:-}" ]; then
    note "port changed ${DB_HOST_PORT:-none} -> $chosen, rewriting .env"
    /usr/bin/sed -i '' "s/^DB_HOST_PORT=.*/DB_HOST_PORT=$chosen/" "$ENVFILE"
    DB_HOST_PORT=$chosen; export DB_HOST_PORT
  fi
  note "selected host port $DB_HOST_PORT"

  # Any resource carrying our name must actually be labelled as ours. Re-running after the
  # project exists is normal; a same-named resource owned by someone else is not.
  local r owner
  for r in $(docker volume ls -q | grep "^${PROJECT}_" || true); do
    owner=$(docker volume inspect "$r" --format '{{index .Labels "com.docker.compose.project"}}')
    [ "$owner" = "$PROJECT" ] || die "volume $r is owned by '$owner', not this project"
  done
  for r in $(docker network ls --format '{{.Name}}' | grep "^${PROJECT}_" || true); do
    owner=$(docker network inspect "$r" --format '{{index .Labels "com.docker.compose.project"}}')
    [ "$owner" = "$PROJECT" ] || die "network $r is owned by '$owner', not this project"
  done

  dc config | /usr/bin/sed -E \
    's/^([[:space:]]*[A-Z0-9_]*(PASSWORD|TOKEN|SECRET|API_KEY)[A-Z0-9_]*:).*/\1 "[REDACTED]"/' \
    > "$ISO/rendered-config.yaml"
  local secret_fields
  secret_fields=$(grep -E \
    '^[[:space:]]*[A-Z0-9_]*(PASSWORD|TOKEN|SECRET|API_KEY)[A-Z0-9_]*:' \
    "$ISO/rendered-config.yaml" | grep -vc '"\[REDACTED\]"' || true)
  [ "$secret_fields" = 0 ] || die "rendered config contains an unredacted secret field"
  local bad
  bad=$(grep -cE 'container_name|privileged|network_mode|^ *pid:|^ *ipc:|docker\.sock|external: true|type: bind' \
        "$ISO/rendered-config.yaml" || true)
  [ "$bad" = 0 ] || die "rendered config contains a forbidden construct ($bad hits)"
  [ "$(grep -c '@sha256:' "$ISO/rendered-config.yaml")" -ge 1 ] || die "image is not digest-pinned"
  local pub loop
  pub=$(grep -c 'published:' "$ISO/rendered-config.yaml")
  loop=$(grep -c 'host_ip: 127.0.0.1' "$ISO/rendered-config.yaml")
  [ "$pub" = "$loop" ] || die "$pub published port(s) but only $loop bound to loopback"

  # This script must not contain host-wide destructive commands. Comment lines and the
  # marker line below are excluded so the check does not trip over its own pattern.
  local offenders banned
  banned='prune|--volumes|volume rm|rm -f \$\(|stop \$\('   # SELFCHECK
  offenders=$(grep -nE "$banned" "$ROOT/db/dbctl.sh" | grep -v 'SELFCHECK' | grep -vE '^[0-9]+: *#' || true)
  [ -z "$offenders" ] || die "dbctl.sh contains a forbidden destructive command: $offenders"

  local f
  for f in $DUMP_ORDER; do
    [ -r "$DUMP_DIR/$f" ] || die "dump not readable: $DUMP_DIR/$f"
  done
  ( cd "$DUMP_DIR" && shasum -a 256 $DUMP_ORDER ) > "$MANIFEST/dumps.sha256"
  shasum -a 256 "$COMPOSE" "$ROOT/db/dbctl.sh" "$ISO/rendered-config.yaml" > "$MANIFEST/files.sha256"
  note "PASS preflight"
}

# ---------------------------------------------------------------- expectations from dumps

expect() {
  /usr/bin/python3 - "$DUMP_DIR" "$BASE" <<'PY'
import re, sys, os
dump_dir, out_dir = sys.argv[1], sys.argv[2]
files = [
    "samsonitetracking_path_001.sql", "samsonitetracking_rating_001.sql",
    "samsonitetracking_request_order_001.sql", "samsonitetracking_status_log_001.sql",
    "samsonitetracking_tbl_last_login_001.sql", "samsonitetracking_uploadstaus_001.sql",
]
rows, autoinc, zerod, thai_seq, idx = {}, {}, {}, {}, []
for fn in files:
    path = os.path.join(dump_dir, fn)
    tables_here, cur_ins, cur_alter = [], None, None
    with open(path, encoding="utf-8") as fh:
        for line in fh:
            m = re.match(r"CREATE TABLE `([^`]+)`", line)
            if m:
                t = m.group(1); tables_here.append(t); rows.setdefault(t, 0); zerod.setdefault(t, 0)
            m = re.match(r"INSERT INTO `([^`]+)`", line)
            if m:
                cur_ins = m.group(1)
            elif line.startswith("("):
                rows[cur_ins] = rows.get(cur_ins, 0) + 1
                zerod[cur_ins] = zerod.get(cur_ins, 0) + line.count("0000-00-00")
            m = re.match(r"ALTER TABLE `([^`]+)`", line)
            if m:
                cur_alter = m.group(1)
            m = re.search(r"AUTO_INCREMENT=(\d+)", line)
            if m and cur_alter:
                autoinc[cur_alter] = int(m.group(1))
            m = re.search(r"ADD (?:(PRIMARY) KEY|(?:UNIQUE )?KEY `([^`]+)`)", line)
            if m and cur_alter:
                idx.append((cur_alter, "PRIMARY" if m.group(1) else m.group(2)))
    with open(path, "rb") as fh:
        blob = fh.read()
    thai_seq[fn] = blob.count(b"\xe0\xb8") + blob.count(b"\xe0\xb9")

def dump(name, pairs):
    with open(os.path.join(out_dir, name), "w") as fh:
        for k, v in sorted(pairs):
            fh.write(f"{k}\t{v}\n")

dump("expected-rows.tsv", rows.items())
dump("expected-autoinc.tsv", autoinc.items())
dump("expected-zerodate.tsv", [(k, v) for k, v in zerod.items() if v])
dump("expected-thaiseq.tsv", thai_seq.items())
with open(os.path.join(out_dir, "expected-index.tsv"), "w") as fh:
    for t, i in sorted(set(idx)):
        fh.write(f"{t}\t{i}\n")

print(f"tables={len(rows)} rows_total={sum(rows.values())} "
      f"autoinc={len(autoinc)} index={len(set(idx))} zerodate_total={sum(zerod.values())}")
PY
  note "expectations written to $BASE"
}

# ---------------------------------------------------------------- lifecycle

up() {
  lock
  preflight
  dc up -d --wait
  local mapped
  mapped=$(dc port db 3306)
  [ "$mapped" = "127.0.0.1:$DB_HOST_PORT" ] || die "port map is '$mapped', expected 127.0.0.1:$DB_HOST_PORT"
  note "PASS up: $mapped"
}

reset_db() {
  lock
  sql -e "DROP DATABASE IF EXISTS \`$DB\`;
          CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
  note "PASS reset: database $DB recreated (volume and container untouched)"
}

import() {
  lock
  local files=${*:-$DUMP_ORDER} f tables
  for f in $files; do
    local src="$DUMP_DIR/$f"
    [ -r "$src" ] || die "dump not readable: $src"
    # The dumps carry no DROP TABLE, so drop exactly the tables this file declares.
    # That makes a re-run of any single file safe.
    tables=$(grep -oE '^CREATE TABLE `[^`]+`' "$src" | sed 's/^CREATE TABLE //' | tr '\n' ',' | sed 's/,$//')
    [ -n "$tables" ] || die "no CREATE TABLE found in $f"
    sqldb -e "DROP TABLE IF EXISTS $tables;"
    note "importing $f"
    # Rewrite only the DDL closing line: turns MyISAM/utf8mb3/latin1 into
    # InnoDB + DYNAMIC + utf8mb4 at load time. Verified that utf8mb3/MyISAM/latin1
    # appear nowhere except these 31 lines, so no data line can be touched.
    sed 's/^) ENGINE=.*/) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;/' "$src" \
      | dc exec -T -e MYSQL_PWD="$MARIADB_ROOT_PASSWORD" db \
          mariadb --default-character-set=utf8mb4 --show-warnings -u root "$DB"
  done
  note "PASS import"
}

# ---------------------------------------------------------------- verify

ck() { # ck <label> <expected> <actual>
  if [ "$2" = "$3" ]; then echo "PASS $1 ($3)"; else echo "FAIL $1: expected '$2' got '$3'"; return 1; fi
}

verify() {
  local rc=0
  note "V1 server identity"
  sql -e "SELECT VERSION(), @@character_set_server, @@collation_server,
                 @@innodb_default_row_format, @@innodb_page_size, @@sql_mode;"
  ck "V1 sql_mode has no NO_ZERO_DATE" "0" \
     "$(sql -e "SELECT @@sql_mode LIKE '%NO_ZERO%';")" || rc=1
  # The charset's default collation lives in character_set_collations on 11.4+, not in
  # information_schema.COLLATIONS.IS_DEFAULT (which is empty for every utf8mb4 row).
  ck "V2 utf8mb4 charset default collation" "utf8mb4=utf8mb4_general_ci" \
     "$(sql -e "SELECT @@character_set_collations;")" || rc=1
  ck "V2 schema default collation" "utf8mb4_general_ci" \
     "$(sql -e "SELECT DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA
                WHERE SCHEMA_NAME='$DB';")" || rc=1

  ck "V3 table count" "31" \
     "$(sqldb -e "SELECT COUNT(*) FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA='$DB' AND TABLE_TYPE='BASE TABLE';")" || rc=1
  ck "V4 wrong engine/row_format/collation" "0" \
     "$(sqldb -e "SELECT COUNT(*) FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA='$DB' AND TABLE_TYPE='BASE TABLE'
                    AND (ENGINE<>'InnoDB' OR ROW_FORMAT<>'Dynamic'
                         OR TABLE_COLLATION<>'utf8mb4_general_ci');")" || rc=1
  ck "V5 wrong column collation" "0" \
     "$(sqldb -e "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA='$DB' AND COLLATION_NAME IS NOT NULL
                    AND COLLATION_NAME<>'utf8mb4_general_ci';")" || rc=1
  ck "V6 TEXT promoted to MEDIUMTEXT/LONGTEXT" "0" \
     "$(sqldb -e "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA='$DB' AND DATA_TYPE IN ('mediumtext','longtext');")" || rc=1

  note "V7 row counts vs dump"
  local q="" t c
  while IFS=$'\t' read -r t c; do
    q="$q SELECT '$t' t, COUNT(*) c FROM \`$t\` UNION ALL"
  done < "$BASE/expected-rows.tsv"
  q="${q% UNION ALL}"
  sqldb -e "$q" | sort > "$BASE/actual-rows.tsv"
  sort "$BASE/expected-rows.tsv" > "$BASE/.exp-rows.sorted"
  if diff -u "$BASE/.exp-rows.sorted" "$BASE/actual-rows.tsv" > "$BASE/rows.diff"; then
    echo "PASS V7 row counts ($(awk -F'\t' '{s+=$2} END{print s}' "$BASE/actual-rows.tsv") rows)"
  else
    echo "FAIL V7 row counts, see $BASE/rows.diff"; head -20 "$BASE/rows.diff"; rc=1
  fi

  note "V8 index set vs dump"
  sqldb -e "SELECT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA='$DB' GROUP BY TABLE_NAME, INDEX_NAME
            ORDER BY TABLE_NAME, INDEX_NAME;" | sort > "$BASE/actual-index.tsv"
  sort "$BASE/expected-index.tsv" > "$BASE/.exp-index.sorted"
  if diff -u "$BASE/.exp-index.sorted" "$BASE/actual-index.tsv" > "$BASE/index.diff"; then
    echo "PASS V8 index set ($(wc -l < "$BASE/actual-index.tsv" | tr -d ' ') indexes)"
  else
    echo "FAIL V8 index set, see $BASE/index.diff"; head -20 "$BASE/index.diff"; rc=1
  fi

  note "V9 AUTO_INCREMENT vs dump"
  sqldb -e "SELECT TABLE_NAME, AUTO_INCREMENT FROM information_schema.TABLES
            WHERE TABLE_SCHEMA='$DB' AND AUTO_INCREMENT IS NOT NULL
            ORDER BY TABLE_NAME;" | sort > "$BASE/actual-autoinc.tsv"
  sort "$BASE/expected-autoinc.tsv" > "$BASE/.exp-autoinc.sorted"
  if diff -u "$BASE/.exp-autoinc.sorted" "$BASE/actual-autoinc.tsv" > "$BASE/autoinc.diff"; then
    echo "PASS V9 AUTO_INCREMENT"
  else
    echo "FAIL V9 AUTO_INCREMENT, see $BASE/autoinc.diff"; head -20 "$BASE/autoinc.diff"; rc=1
  fi

  note "V10 zero dates preserved"
  ck "V10 request_order zero dates" \
     "$(awk -F'\t' '$1=="request_order"{print $2}' "$BASE/expected-zerodate.tsv")" \
     "$(sqldb -e "SELECT COUNT(*) FROM request_order WHERE detailDatePurchase='0000-00-00 00:00:00';")" || rc=1
  ck "V10 branch zero dates" "18" \
     "$(sqldb -e "SELECT COUNT(*) FROM branch WHERE udate='0000-00-00 00:00:00';")" || rc=1
  ck "V10 zero-date default kept" "1" \
     "$(sqldb -e "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA='$DB' AND TABLE_NAME='request_order'
                    AND COLUMN_NAME='detailDatePurchase'
                    AND COLUMN_DEFAULT=\"'0000-00-00 00:00:00'\";")" || rc=1

  note "V11 no mojibake / replacement characters"
  ck "V11 uploadstaus mojibake" "0" \
     "$(sqldb -e "SELECT COUNT(*) FROM uploadstaus WHERE Listname LIKE '%à¸%' OR Listname LIKE '%à¹%';")" || rc=1
  ck "V11 request_order mojibake" "0" \
     "$(sqldb -e "SELECT COUNT(*) FROM request_order WHERE customerFullname LIKE '%à¸%' OR detailCondition LIKE '%à¸%';")" || rc=1
  ck "V11 replacement char" "0" \
     "$(sqldb -e "SELECT COUNT(*) FROM uploadstaus WHERE Listname LIKE CONCAT('%',CONVERT(0xEFBFBD USING utf8mb4),'%');")" || rc=1

  note "V12 Thai data intact"
  local thai_db thai_file
  thai_db=$(sqldb -e "SELECT COALESCE(SUM(LENGTH(Listname)-CHAR_LENGTH(Listname))/2,0) FROM uploadstaus;")
  thai_file=$(awk -F'\t' '$1 ~ /uploadstaus/{print $2}' "$BASE/expected-thaiseq.tsv")
  echo "INFO V12 uploadstaus Thai chars: db=$thai_db dump_sequences=$thai_file"
  [ "${thai_db%.*}" -gt 0 ] && echo "PASS V12 Thai present" || { echo "FAIL V12 no Thai data"; rc=1; }
  ck "V12 max bytes-per-char <= 3" "1" \
     "$(sqldb -e "SELECT MAX(LENGTH(Listname)/CHAR_LENGTH(Listname))<=3.0
                  FROM uploadstaus WHERE CHAR_LENGTH(Listname)>0;")" || rc=1

  note "V13 timezone parity with dump"
  sqldb -e "SELECT id, cdate, UNIX_TIMESTAMP(cdate) FROM status_log ORDER BY id DESC LIMIT 1;"
  ck "V13 last status_log cdate (UTC)" "2026-07-31 05:09:08" \
     "$(sqldb -e "SELECT cdate FROM status_log ORDER BY id DESC LIMIT 1;")" || rc=1

  ck "V14 tbl_users+tbl_roles on InnoDB" "2" \
     "$(sqldb -e "SELECT COUNT(*) FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA='$DB' AND TABLE_NAME IN ('tbl_users','tbl_roles')
                    AND ENGINE='InnoDB';")" || rc=1
  ck "V15 tbl_reset_password non-ASCII" "0" \
     "$(sqldb -e "SELECT COUNT(*) FROM tbl_reset_password
                  WHERE CONVERT(CONCAT_WS('',email,activation_id,agent,client_ip) USING binary)
                        REGEXP '[^\\\\x00-\\\\x7F]';")" || rc=1

  note "V16 indexes still usable"
  local plans
  plans=$(sqldb -e "EXPLAIN SELECT 1 FROM uploadstaus WHERE tracking_id='X';
                    EXPLAIN SELECT 1 FROM uploadstaus WHERE Telephone='X';
                    EXPLAIN SELECT 1 FROM request_order WHERE trackID='X';
                    EXPLAIN SELECT 1 FROM request_order r JOIN uploadstaus u ON u.tracking_id=r.trackID LIMIT 1;")
  echo "$plans"
  ck "V16 no full table scan in plans" "0" \
     "$(echo "$plans" | awk -F'\t' '$4=="ALL"' | wc -l | tr -d ' ')" || rc=1

  ck "V17 host port reachable" "1" "$(nc -z 127.0.0.1 "$DB_HOST_PORT" >/dev/null 2>&1 && echo 1 || echo 0)" || rc=1

  [ "$rc" = 0 ] && note "ALL VERIFICATIONS PASSED" || note "SOME VERIFICATIONS FAILED"
  return $rc
}

# ---------------------------------------------------------------- CI3 rehearsal (WP-00K)

CI3_REPO=${CI3_SOURCE_ROOT:-}
WEB_BASE="http://127.0.0.1:${WEB_HOST_PORT:-18404}"

ci3_repo_state() { echo "$(git -C "$CI3_REPO" rev-parse HEAD) $(git -C "$CI3_REPO" status --short | wc -l | tr -d ' ')"; }

web_build() {
  lock
  : "${CI3_REPO:?CI3_SOURCE_ROOT is required}" "${CI3_WEB_IMAGE:?}"
  local before after
  before=$(ci3_repo_state)
  [ "${before##* }" = 0 ] || die "CI3 repo is dirty before build; refusing to touch it"

  # Not routed through dc(): this is `docker build`, not a compose command, and it needs an
  # exact -f plus two contexts. The exclude list rides along as web/Dockerfile.dockerignore
  # because the context root (the pinned repo) is read-only.
  DOCKER_BUILDKIT=1 docker build \
    -f "$ROOT/web/Dockerfile" --build-context ws="$ROOT/web" \
    -t "$CI3_WEB_IMAGE" "$CI3_REPO"

  after=$(ci3_repo_state)
  [ "$before" = "$after" ] || die "CI3 repo changed during build: '$before' -> '$after'"
  note "PASS build, CI3 repo untouched (${before% *})"

  # WP-00F: the release artifact must carry no admin tool, no secrets, no customer data.
  local p
  for p in SECRETS-LOCAL.md tools demo lib/ApnsPHP-master lib/mysqli.php; do
    docker run --rm "$CI3_WEB_IMAGE" test -e "/var/www/html/$p" \
      && die "forbidden path leaked into the image: $p" || true
  done
  note "PASS artifact clean (no secrets, no phpMyAdmin, no demo copy, no dead libs)"
}

web_up() {
  lock
  preflight
  dc up -d --wait
  local mapped
  mapped=$(dc port web 80)
  [ "$mapped" = "127.0.0.1:$WEB_HOST_PORT" ] || die "web port map is '$mapped'"
  note "PASS web up: $mapped"
}

# One HTTP assertion. `hs <label> <expected-status> <path> [curl args...]`
hs() {
  local label=$1 want=$2 path=$3; shift 3
  local got
  got=$(curl --disable --noproxy '*' -s -o "$SMOKE_BODY" -w '%{http_code}' \
             --max-time 30 "$@" "$WEB_BASE$path")
  if [ "$got" = "$want" ]; then echo "PASS $label ($got)"; else echo "FAIL $label: want $want got $got  [$path]"; return 1; fi
}
body_has() {
  local label=$1 needle=$2
  if grep -q -- "$needle" "$SMOKE_BODY"; then echo "PASS $label"; else echo "FAIL $label: body missing '$needle'"; return 1; fi
}
body_lacks() {
  local label=$1 needle=$2
  if grep -q -- "$needle" "$SMOKE_BODY"; then echo "FAIL $label: body contains '$needle'"; return 1; else echo "PASS $label"; fi
}

database_row_count() {
  local table rows total=0 tables
  tables=$(sqldb -e "SELECT TABLE_NAME FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA='$DB' AND TABLE_TYPE='BASE TABLE'
                     ORDER BY TABLE_NAME;")
  while IFS= read -r table; do
    [ -n "$table" ] || continue
    case "$table" in *[!A-Za-z0-9_]*) die "unsafe table name from information_schema: $table" ;; esac
    # mariadb reads stdin even with -e; isolate it or it consumes the remaining loop input.
    rows=$(sqldb -e "SELECT COUNT(*) FROM \`$table\`;" </dev/null)
    case "$rows" in ''|*[!0-9]*) die "invalid row count for $table" ;; esac
    total=$((total + rows))
  done <<< "$tables"
  echo "$total"
}

assert_outbound_messaging_denied() {
  dc exec -T web php -r '
    $required = array(
      "mail", "mb_send_mail", "curl_exec", "curl_multi_exec", "fsockopen",
      "pfsockopen", "stream_socket_client", "popen", "proc_open", "exec",
      "shell_exec", "system", "passthru"
    );
    $disabled = array_map("trim", explode(",", ini_get("disable_functions")));
    $missing = array_diff($required, $disabled);
    if ($missing || ini_get("allow_url_fopen") || ini_get("sendmail_path") !== "/bin/false") {
      fwrite(STDERR, "outbound messaging deny policy is incomplete".PHP_EOL);
      exit(1);
    }
  '
}

make_synthetic_preview_fixture() {
  local target=$1 order_id=$2 cmg=$3
  python3 - "$target" "$order_id" "$cmg" <<'PY'
import sys
import zipfile
from xml.sax.saxutils import escape

target, order_id, cmg = sys.argv[1:]
rows = [
    ["Order", "Name", "Telephone", "Update", "Status", "Received", "Price", "Warranty", "CMG"],
    [order_id, "SYNTHETIC CUSTOMER - NOT REAL", "0000000000", "01/08/2026",
     "SYNTHETIC STATUS", "31/07/2026", "1234", "IN", cmg],
]

def column(number):
    result = ""
    while number:
        number, remainder = divmod(number - 1, 26)
        result = chr(65 + remainder) + result
    return result

sheet_rows = []
for row_number, values in enumerate(rows, 1):
    cells = []
    for column_number, value in enumerate(values, 1):
        reference = f"{column(column_number)}{row_number}"
        cells.append(f'<c r="{reference}" t="inlineStr"><is><t>{escape(value)}</t></is></c>')
    sheet_rows.append(f'<row r="{row_number}">{"".join(cells)}</row>')

parts = {
    "[Content_Types].xml": """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>""",
    "_rels/.rels": """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>""",
    "xl/workbook.xml": """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Preview" sheetId="1" r:id="rId1"/></sheets>
</workbook>""",
    "xl/_rels/workbook.xml.rels": """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>""",
    "xl/worksheets/sheet1.xml": f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>{''.join(sheet_rows)}</sheetData>
</worksheet>""",
}

with zipfile.ZipFile(target, "w") as workbook:
    for name in sorted(parts):
        info = zipfile.ZipInfo(name, (1980, 1, 1, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o600 << 16
        workbook.writestr(info, parts[name].encode("utf-8"))
PY
}

# ---------------------------------------------------------- WP-00C fixture kit

WP00C_KIT="$ROOT/scripts/wp00c-kit.py"
WP00C_FIXTURE_STATE="$BASE/wp00c-fixture-state.json"
WP00C_CURRENT_CI3_STATE="ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6 0"
WP00C_LEGACY_CI3_STATE="8dad4e331a90f5c6765954454910b451eb0ff8e5 0"

wp00c_kit_hash() {
  python3 "$WP00C_KIT" summary \
    | python3 -c 'import json,sys; print(json.load(sys.stdin)["kit_sha256"])'
}

wp00c_state_field() {
  python3 - "$WP00C_FIXTURE_STATE" "$1" <<'PY'
import json
import pathlib
import sys

try:
    state = json.loads(pathlib.Path(sys.argv[1]).read_text(encoding="utf-8"))
    value = state[sys.argv[2]]
except (OSError, KeyError, json.JSONDecodeError) as exc:
    raise SystemExit(f"invalid WP-00C fixture state: {exc}") from exc
if not isinstance(value, str):
    raise SystemExit(f"invalid WP-00C fixture state field: {sys.argv[2]}")
print(value)
PY
}

wp00c_state_write() {
  local status=$1 kit_hash=$2 source_state=$3 image_id=$4
  python3 - "$WP00C_FIXTURE_STATE" "$status" "$kit_hash" "$source_state" \
    "$image_id" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" <<'PY'
import json
import pathlib
import sys

target = pathlib.Path(sys.argv[1])
state = {
    "status": sys.argv[2],
    "kit_sha256": sys.argv[3],
    "ci3_state": sys.argv[4],
    "image_id": sys.argv[5],
    "updated_at": sys.argv[6],
}
target.write_text(json.dumps(state, sort_keys=True) + "\n", encoding="utf-8")
PY
}

wp00c_fixture_validate() {
  : "${CI3_REPO:?CI3_SOURCE_ROOT is required}"
  python3 "$WP00C_KIT" validate --source-root "$CI3_REPO"
}

wp00c_fixture_verify() {
  local table expected actual rc=0
  while IFS=$'\t' read -r table expected; do
    case "$table" in ''|*[!A-Za-z0-9_]*) die "unsafe WP-00C fixture table name: $table" ;; esac
    actual=$(sqldb -e "SELECT COUNT(*) FROM \`$table\`;" </dev/null)
    ck "WP-00C fixture $table rows" "$expected" "$actual" || rc=1
  done < <(python3 "$WP00C_KIT" expected-counts)

  ck "WP-00C fixture total rows" "116" "$(database_row_count)" || rc=1
  ck "WP-00C active users" "3" \
    "$(sqldb -e "SELECT COUNT(*) FROM tbl_users WHERE userId BETWEEN 9001 AND 9004 AND isDeleted=0;")" || rc=1
  ck "WP-00C deleted users" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM tbl_users WHERE userId BETWEEN 9001 AND 9004 AND isDeleted=1;")" || rc=1
  ck "WP-00C branch 1 orders" "6" \
    "$(sqldb -e "SELECT COUNT(*) FROM request_order WHERE trackID LIKE 'WP00C-%' AND branchID=1;")" || rc=1
  ck "WP-00C branch 2 orders" "3" \
    "$(sqldb -e "SELECT COUNT(*) FROM request_order WHERE trackID LIKE 'WP00C-%' AND branchID=2;")" || rc=1
  ck "WP-00C status 2 orders" "2" \
    "$(sqldb -e "SELECT COUNT(*) FROM request_order WHERE trackID LIKE 'WP00C-%' AND action_status=2;")" || rc=1
  ck "WP-00C tracking timeline rows" "4" \
    "$(sqldb -e "SELECT COUNT(*) FROM status_log WHERE order_id='WP00C-TRACK-005';")" || rc=1
  ck "WP-00C rating rows" "8" \
    "$(sqldb -e "SELECT COUNT(*) FROM rating WHERE order_id='WP00C-TRACK-007';")" || rc=1
  ck "WP-00C rating score sum" "27" \
    "$(sqldb -e "SELECT COALESCE(SUM(rating),0) FROM rating WHERE order_id='WP00C-TRACK-007';")" || rc=1
  return "$rc"
}

wp00c_fixture_seed() {
  lock
  : "${CI3_REPO:?CI3_SOURCE_ROOT is required}" "${CI3_WEB_IMAGE:?}"
  : "${WP00C_TEST_PASSWORD:?set WP00C_TEST_PASSWORD in the command environment; never commit or log it}"
  [ "${#WP00C_TEST_PASSWORD}" -ge 12 ] && [ "${#WP00C_TEST_PASSWORD}" -le 32 ] \
    || die "WP00C_TEST_PASSWORD must contain 12-32 characters"
  [ ! -e "$WP00C_FIXTURE_STATE" ] \
    || die "WP-00C fixture state already exists; inspect or clean it before seeding"

  wp00c_fixture_validate
  dc up -d --wait db
  ck "WP-00C schema table count" "31" \
    "$(sqldb -e "SELECT COUNT(*) FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA='$DB' AND TABLE_TYPE='BASE TABLE';")" \
    || die "WP-00C schema differs from approved baseline"
  ck "WP-00C database starts empty" "0" "$(database_row_count)" \
    || die "WP-00C fixtures require an empty isolated database"
  [ -z "$(dc ps -aq web)" ] \
    || die "web container already exists; stop and inspect it before WP-00C seed"

  if [ "${WP00C_REUSE_CI3_IMAGE:-0}" = 1 ]; then
    docker image inspect "$CI3_WEB_IMAGE" >/dev/null 2>&1 \
      || die "WP00C_REUSE_CI3_IMAGE=1 but image is unavailable: $CI3_WEB_IMAGE"
    note "explicitly reusing local CI3 rehearsal image $CI3_WEB_IMAGE"
  else
    web_build
  fi
  dc up -d --wait db web
  assert_outbound_messaging_denied \
    || die "outbound messaging deny policy is not active"

  local hash hash_hex kit_hash source_state image_id
  hash=$(printf '%s' "$WP00C_TEST_PASSWORD" \
    | dc exec -T web php -r '$p = stream_get_contents(STDIN); echo password_hash($p, PASSWORD_BCRYPT);')
  [ -n "$hash" ] || die "failed to generate WP-00C bcrypt hash"
  hash_hex=$(printf '%s' "$hash" | od -An -v -tx1 | tr -d ' \n')
  kit_hash=$(wp00c_kit_hash)
  source_state=$(ci3_repo_state)
  image_id=$(docker image inspect --format '{{.Id}}' "$CI3_WEB_IMAGE")
  [ -n "$image_id" ] || die "failed to resolve WP-00C image ID"
  wp00c_state_write "SEEDING" "$kit_hash" "$source_state" "$image_id"
  python3 "$WP00C_KIT" render-seed-sql --password-hash-hex "$hash_hex" | sqldb
  unset hash hash_hex

  wp00c_fixture_verify || die "WP-00C fixture verification failed"
  wp00c_state_write "READY" "$kit_hash" "$source_state" "$image_id"
  note "PASS WP-00C synthetic fixtures ready: users=4 orders=9 total_rows=116"
  note "login users: wp00c-admin, wp00c-a, wp00c-b; password stays only in caller environment"
}

wp00c_fixture_clean() {
  lock
  [ -f "$WP00C_FIXTURE_STATE" ] \
    || die "missing WP-00C fixture state; refusing broad synthetic cleanup"
  local recorded_status recorded_hash recorded_source current_hash
  recorded_status=$(wp00c_state_field status) \
    || die "cannot read WP-00C fixture state status"
  case "$recorded_status" in
    SEEDING|READY) ;;
    *) die "unexpected WP-00C fixture state status: $recorded_status" ;;
  esac
  recorded_hash=$(wp00c_state_field kit_sha256) \
    || die "cannot read WP-00C fixture state kit hash"
  current_hash=$(wp00c_kit_hash)
  [ "$recorded_hash" = "$current_hash" ] \
    || [ "$recorded_hash" = "f56c54075651c34cffc69e46c3028ad0efa5e976a37629427af562337236c554" ] \
    || die "WP-00C kit changed after seed; preserve state and inspect before cleanup"
  recorded_source=$(wp00c_state_field ci3_state) \
    || die "cannot read WP-00C fixture source state"
  [ "$recorded_source" = "$WP00C_CURRENT_CI3_STATE" ] \
    || [ "$recorded_source" = "$WP00C_LEGACY_CI3_STATE" ] \
    || die "fixture state was not created from approved clean CI3 source"
  dc up -d --wait db

  # Seed is allowed only on an empty isolated DB and creates the state file above. These
  # predicates remove test-created side effects while avoiding any production identifier.
  sqldb -e "
    DELETE FROM tbl_last_login WHERE userId BETWEEN 9000 AND 9999;
    DELETE FROM ci_sessions;
    DELETE FROM rating WHERE order_id LIKE 'WP00C-%';
    DELETE FROM rating_comment WHERE track_id LIKE 'WP00C-%';
    DELETE FROM uploadstaus WHERE tracking_id LIKE 'WP00C-%';
    DELETE FROM temp_updatestatus_order
      WHERE temp_trackID LIKE 'WP00C-%' OR temp_orderIDShow LIKE 'WPC/%';
    DELETE FROM temp_updatestatus_neworder
      WHERE temp_trackID LIKE 'WP00C-%' OR temp_orderIDShow LIKE 'WPC/%';
    DELETE FROM temp_updatestatus_price_order
      WHERE temp_trackID LIKE 'WP00C-%' OR temp_orderIDShow LIKE 'WPC/%';
    DELETE FROM status_log WHERE order_id LIKE 'WP00C-%';
    DELETE FROM request_order_delete WHERE trackID LIKE 'WP00C-%';
    DELETE FROM request_order WHERE trackID LIKE 'WP00C-%';
    DELETE FROM contact WHERE email LIKE 'wp00c-%@example.invalid';
    DELETE FROM tbl_reset_password WHERE email LIKE 'wp00c-%@example.invalid';
    DELETE FROM tbl_users WHERE username LIKE 'wp00c-%' AND email LIKE '%@example.invalid';
  "
  python3 "$WP00C_KIT" render-cleanup-sql | sqldb

  local rows source_state
  rows=$(database_row_count)
  source_state=$(ci3_repo_state)
  [ "$rows" = 0 ] || die "WP-00C cleanup left $rows database rows; preserve state and inspect"
  [ "$source_state" = "$recorded_source" ] \
    || { [ "$recorded_source" = "$WP00C_LEGACY_CI3_STATE" ] \
      && [ "$source_state" = "$WP00C_CURRENT_CI3_STATE" ]; } \
    || die "CI3 source changed during WP-00C fixture lifecycle: $source_state"
  dc stop web >/dev/null 2>&1 || true
  dc rm -f web >/dev/null 2>&1 || true
  rm -f -- "$WP00C_FIXTURE_STATE"
  note "PASS WP-00C cleanup: database rows=0, web container removed, CI3 source unchanged"
}

SAFE_PREVIEW_ARMED=0
SAFE_PREVIEW_WEB=0
SAFE_PREVIEW_BODY=""
SAFE_PREVIEW_JAR=""
SAFE_PREVIEW_JAR_B=""
SAFE_PREVIEW_EXISTING=""
SAFE_PREVIEW_NEW=""
SAFE_PREVIEW_NEW_B=""
SAFE_PREVIEW_LOG=""

safe_preview_cleanup() {
  local rc=$? post_rows cleanup_rc=0
  trap - EXIT
  set +e

  if [ "$SAFE_PREVIEW_ARMED" = 1 ]; then
    sqldb -e "
      DELETE FROM tbl_last_login
        WHERE userId IN (SELECT userId FROM tbl_users
                         WHERE username IN ('synthetic-preview','synthetic-preview-b')
                           AND email LIKE 'synthetic-preview%@example.invalid');
      DELETE FROM uploadstaus
        WHERE Telephone='0000000000'
          AND Listname='SYNTHETIC CUSTOMER - NOT REAL';
      DELETE FROM status_log
        WHERE order_id LIKE 'SYNTHETIC-%'
           OR order_id IN (SELECT trackID FROM request_order
                           WHERE customerFullname='SYNTHETIC CUSTOMER - NOT REAL'
                             AND customerTel='0000000000');
      DELETE FROM temp_updatestatus_order
        WHERE temp_orderIDShow IN ('SYN/0001','SYN/NEW-001')
          AND temp_customerTel='0000000000';
      DELETE FROM temp_updatestatus_price_order
        WHERE temp_number_cmg IN ('SYNTHETIC-CMG-001','SYNTHETIC-CMG-NEW');
      DELETE FROM temp_updatestatus_neworder
        WHERE temp_orderIDShow IN ('SYN/0001','SYN/NEW-001','SYN/NEW-B01')
          AND temp_customerTel='0000000000';
      DELETE FROM request_order
        WHERE customerFullname='SYNTHETIC CUSTOMER - NOT REAL'
          AND customerTel='0000000000';
      DELETE FROM tracking_status
        WHERE status_id=9001
          AND description_en='SYNTHETIC STATUS';
      DELETE FROM tbl_users
        WHERE username IN ('synthetic-preview','synthetic-preview-b')
          AND email LIKE 'synthetic-preview%@example.invalid';
      DELETE FROM branch
        WHERE branch_id=1 AND branch_name='SYNTHETIC BRANCH - NOT REAL';
      DELETE FROM tbl_roles
        WHERE roleId=1 AND role='SYNTHETIC PREVIEW';
    " || cleanup_rc=1
  fi

  if [ "$SAFE_PREVIEW_WEB" = 1 ]; then
    dc logs web > "${SAFE_PREVIEW_LOG:-$BASE/safe-preview-web.log}" 2>&1 || true
    dc stop web >/dev/null 2>&1 || cleanup_rc=1
    dc rm -f web >/dev/null 2>&1 || cleanup_rc=1
  fi

  [ -z "$SAFE_PREVIEW_BODY" ] || rm -f -- "$SAFE_PREVIEW_BODY"
  [ -z "$SAFE_PREVIEW_JAR" ] || rm -f -- "$SAFE_PREVIEW_JAR"
  [ -z "$SAFE_PREVIEW_JAR_B" ] || rm -f -- "$SAFE_PREVIEW_JAR_B"
  [ -z "$SAFE_PREVIEW_EXISTING" ] || rm -f -- "$SAFE_PREVIEW_EXISTING"
  [ -z "$SAFE_PREVIEW_NEW" ] || rm -f -- "$SAFE_PREVIEW_NEW"
  [ -z "$SAFE_PREVIEW_NEW_B" ] || rm -f -- "$SAFE_PREVIEW_NEW_B"

  if [ "$SAFE_PREVIEW_ARMED" = 1 ]; then
    post_rows=$(database_row_count) || cleanup_rc=1
    if [ "${post_rows:-invalid}" = 0 ]; then
      note "PASS cleanup: database rows=0"
    else
      echo "FAIL cleanup: database rows=${post_rows:-unknown}" >&2
      cleanup_rc=1
    fi
  fi
  [ "$cleanup_rc" = 0 ] || rc=1
  rmdir "$LOCK" 2>/dev/null || true
  exit "$rc"
}

safe_preview_smoke() {
  lock
  local rc=0 temp_pw hash hash_hex got mapped started_at logs source_uploads backup_files
  local docker_context docker_endpoint
  : "${CI3_REPO:?CI3_SOURCE_ROOT is required}" "${CI3_WEB_IMAGE:?}"
  [ "${COMPOSE_PROJECT_NAME:-}" = "$PROJECT" ] \
    || die "COMPOSE_PROJECT_NAME must be $PROJECT"
  case "$DB" in ''|*[!A-Za-z0-9_]*) die "MARIADB_DATABASE contains unsafe characters" ;; esac
  case "${WEB_HOST_PORT:-}" in ''|*[!0-9]*) die "WEB_HOST_PORT must be numeric" ;; esac
  [ "$WEB_HOST_PORT" -ge 1 ] && [ "$WEB_HOST_PORT" -le 65535 ] \
    || die "WEB_HOST_PORT must be between 1 and 65535"
  [ "$WEB_BASE" = "http://127.0.0.1:$WEB_HOST_PORT" ] \
    || die "web base must resolve to loopback"
  docker_context=$(docker context show)
  docker_endpoint=${DOCKER_HOST:-$(docker context inspect "$docker_context" \
    --format '{{.Endpoints.docker.Host}}')}
  case "$docker_endpoint" in unix://*) ;; *) die "Docker endpoint is not a local Unix socket" ;; esac
  docker info >/dev/null 2>&1 || die "Docker is unavailable"
  [ -z "$(dc ps -aq web)" ] \
    || die "web container already exists; stop and inspect it before safe preview smoke"
  dc up -d --wait db
  ck "P0 schema table count" "31" \
    "$(sqldb -e "SELECT COUNT(*) FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA='$DB' AND TABLE_TYPE='BASE TABLE';")" || return 1
  ck "P0 database starts empty" "0" "$(database_row_count)" || return 1
  backup_files=$(dc exec -T db sh -c 'find /backup -type f -print | wc -l' | tr -d '[:space:]')
  ck "P0 backup volume starts empty" "0" "$backup_files" || return 1
  source_uploads=0
  for path in "$CI3_REPO/uploads" "$CI3_REPO/demo/uploads"; do
    [ ! -d "$path" ] || source_uploads=$((source_uploads + $(find -P "$path" -type f -print | wc -l | tr -d ' ')))
  done
  ck "P0 CI3 source uploads empty" "0" "$source_uploads" || return 1

  web_build
  SAFE_PREVIEW_WEB=1
  trap safe_preview_cleanup EXIT
  dc up -d --wait db web
  mapped=$(dc port web 80)
  [ "$mapped" = "127.0.0.1:$WEB_HOST_PORT" ] || die "web port map is '$mapped'"
  assert_outbound_messaging_denied || die "outbound messaging deny policy is not active"
  note "PASS outbound email/SMS transports disabled"
  ck "P0 database remains empty before synthetic seed" "0" "$(database_row_count)" \
    || die "database changed during build; refusing to seed"

  SAFE_PREVIEW_BODY=$(mktemp -t ci3-safe-preview-body)
  SAFE_PREVIEW_JAR=$(mktemp -t ci3-safe-preview-cookie)
  SAFE_PREVIEW_EXISTING=$(mktemp -t ci3-safe-preview-existing)
  SAFE_PREVIEW_NEW=$(mktemp -t ci3-safe-preview-new)
  SAFE_PREVIEW_LOG="$BASE/safe-preview-web.log"
  SMOKE_BODY=$SAFE_PREVIEW_BODY
  make_synthetic_preview_fixture "$SAFE_PREVIEW_EXISTING" "SYN/0001" "SYNTHETIC-CMG-001"
  make_synthetic_preview_fixture "$SAFE_PREVIEW_NEW" "SYN/NEW-001" "SYNTHETIC-CMG-NEW"

  SAFE_PREVIEW_ARMED=1
  temp_pw=$(od -An -N16 -tx1 /dev/urandom | tr -d ' \n')
  [ "${#temp_pw}" = 32 ] || die "failed to generate temporary password"
  hash=$(printf '%s' "$temp_pw" \
    | dc exec -T web php -r '$p = stream_get_contents(STDIN); echo password_hash($p, PASSWORD_BCRYPT);')
  [ -n "$hash" ] || die "failed to generate temporary password hash"
  hash_hex=$(printf '%s' "$hash" | od -An -v -tx1 | tr -d ' \n')

  sqldb -e "
    INSERT INTO tbl_roles (roleId,role) VALUES (1,'SYNTHETIC PREVIEW');
    INSERT INTO branch
      (branch_id,branch_type,branch_user_name,branch_name,branch_details,
       default_suffix,book_order,customer_ref,cdate)
    VALUES
      (1,1,'synthetic-preview','SYNTHETIC BRANCH - NOT REAL','SYNTHETIC ONLY',
       'SYN','SYN','SYNTHETIC','2026-08-19 00:00:00');
    INSERT INTO tbl_users
      (email,username,password,name,mobile,group_id,roleId,branch_id,branch_type_id,
       isDeleted,createdBy,createdDtm)
    VALUES
      ('synthetic-preview@example.invalid','synthetic-preview',UNHEX('$hash_hex'),
       'SYNTHETIC OPERATOR - NOT REAL','0000000000',4,1,1,NULL,0,0,
       '2026-08-19 00:00:00');
    INSERT INTO request_order
      (requestDate,trackID,numberID,orderID,orderIDShow,waranty_cmg,
       customerFullname,customerTel,branchID,action_status,RepairPrice,number_cmg,date_create)
    VALUES
      ('2026-07-31 00:00:00','SYNTHETIC-TRACK-001','SYNTHETIC-001','SYN0001',
       'SYN/0001','IN','SYNTHETIC CUSTOMER - NOT REAL','0000000000',1,2,
       1234.00,'SYNTHETIC-CMG-001','2026-07-31 00:00:00');
  "
  ck "P1 synthetic operator" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM tbl_users WHERE username='synthetic-preview'
                   AND email LIKE '%@example.invalid';")" || rc=1
  ck "P1 synthetic order" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM request_order
                   WHERE trackID='SYNTHETIC-TRACK-001' AND customerTel='0000000000';")" || rc=1
  [ "$rc" = 0 ] || return "$rc"

  started_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)
  printf 'username=synthetic-preview&password=%s' "$temp_pw" \
    | curl --disable --noproxy '*' -sS -o "$SAFE_PREVIEW_BODY" \
        -c "$SAFE_PREVIEW_JAR" -b "$SAFE_PREVIEW_JAR" \
        --max-time 30 --data-binary @- "$WEB_BASE/loginMe"
  temp_pw=""
  hs "P2 login session reaches Status listing" 200 "/UploadexcelListing" \
    -b "$SAFE_PREVIEW_JAR" -c "$SAFE_PREVIEW_JAR" || rc=1
  body_has "P2 Status upload form" "ExcelDataAdd" || rc=1
  body_lacks "P2 not returned to login" 'name="password"' || rc=1

  got=$(curl --disable --noproxy '*' -sS -o "$SAFE_PREVIEW_BODY" \
    -w '%{http_code}' --max-time 60 \
    -b "$SAFE_PREVIEW_JAR" -c "$SAFE_PREVIEW_JAR" \
    -F "file=@$SAFE_PREVIEW_EXISTING;filename=synthetic-status.xlsx;type=application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" \
    "$WEB_BASE/ExcelDataAdd")
  ck "P3 Status preview HTTP" "200" "$got" || rc=1
  body_has "P3 Status preview rendered" "Data Upload file Management" || rc=1
  body_has "P3 Status preview synthetic row" "SYNTHETIC CUSTOMER - NOT REAL" || rc=1
  body_has "P3 Status preview valid" "ข้อมูลถูกต้อง กรุณากด Comfirm เพื่ออัพโหลดสถานะ" || rc=1
  ck "P3 Status temp row" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM temp_updatestatus_order
                   WHERE temp_orderIDShow='SYN/0001' AND temp_customerTel='0000000000';")" || rc=1

  got=$(curl --disable --noproxy '*' -sS -o "$SAFE_PREVIEW_BODY" \
    -w '%{http_code}' --max-time 60 \
    -b "$SAFE_PREVIEW_JAR" -c "$SAFE_PREVIEW_JAR" \
    -F "file=@$SAFE_PREVIEW_EXISTING;filename=synthetic-price.xlsx;type=application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" \
    "$WEB_BASE/ExcelPriceDataAdd")
  ck "P4 Price preview HTTP" "200" "$got" || rc=1
  body_has "P4 Price preview rendered" "Data Upload file Price Management" || rc=1
  body_has "P4 Price preview valid" "ข้อมูลถูกต้อง กรุณากด Comfirm เพื่ออัพโหลดราคา" || rc=1
  ck "P4 Price temp row" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM temp_updatestatus_price_order
                   WHERE temp_number_cmg='SYNTHETIC-CMG-001';")" || rc=1

  got=$(curl --disable --noproxy '*' -sS -o "$SAFE_PREVIEW_BODY" \
    -w '%{http_code}' --max-time 60 \
    -b "$SAFE_PREVIEW_JAR" -c "$SAFE_PREVIEW_JAR" \
    -F "file=@$SAFE_PREVIEW_NEW;filename=synthetic-new-order.xlsx;type=application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" \
    "$WEB_BASE/ExcelNewOrderDataAdd")
  ck "P5 New Order preview HTTP" "200" "$got" || rc=1
  body_has "P5 New Order preview rendered" "Data Upload New REQUEST file Management" || rc=1
  body_has "P5 New Order preview synthetic row" "SYN/NEW-001" || rc=1
  body_lacks "P5 New Order is not duplicate" "ซ้ำในระบบ" || rc=1
  ck "P5 New Order temp row" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM temp_updatestatus_neworder
                   WHERE temp_orderIDShow='SYN/NEW-001' AND temp_customerTel='0000000000';")" || rc=1

  ck "P6 Confirm table untouched" "0" "$(sqldb -e 'SELECT COUNT(*) FROM uploadstaus;')" || rc=1
  ck "P6 status log untouched" "0" "$(sqldb -e 'SELECT COUNT(*) FROM status_log;')" || rc=1
  ck "P6 no order created by New Order preview" "1" "$(sqldb -e 'SELECT COUNT(*) FROM request_order;')" || rc=1
  ck "P6 no password-reset record" "0" "$(sqldb -e 'SELECT COUNT(*) FROM tbl_reset_password;')" || rc=1

  logs=$(dc logs --since "$started_at" web 2>&1)
  for route in ExcelDataAdd ExcelPriceDataAdd ExcelNewOrderDataAdd; do
    if printf '%s\n' "$logs" | grep -q "POST /$route "; then
      echo "PASS P7 $route request observed"
    else
      echo "FAIL P7 $route request missing from access log"
      rc=1
    fi
  done
  if printf '%s\n' "$logs" \
       | grep -qiE 'POST /(ExcelConfirm|ExcelPriceConfirm|ExcelNewOrderConfirm|forgotPassword|resetPasswordUser)|PHPMailer|SMTP|send_?sms|curl_exec.*disabled'; then
    echo "FAIL P7 confirm/email/SMS activity found in web log"
    rc=1
  else
    echo "PASS P7 confirm calls=0 messaging calls=0"
  fi
  if printf '%s\n' "$logs" | grep -qiE 'PHP Fatal|Uncaught|A Database Error Occurred'; then
    echo "FAIL P7 fatal/database error found in web log"
    rc=1
  else
    echo "PASS P7 no fatal/database error"
  fi

  backup_files=$(dc exec -T db sh -c 'find /backup -type f -print | wc -l' | tr -d '[:space:]')
  ck "P8 backup volume remains empty" "0" "$backup_files" || rc=1
  [ "$rc" = 0 ] && note "SAFE SYNTHETIC PREVIEW SMOKE PASSED" \
                    || note "SAFE SYNTHETIC PREVIEW SMOKE FAILED"
  return "$rc"
}

safe_confirm_smoke() {
  local rc=0 got logs started_at new_track backup_files

  safe_preview_smoke || return 1
  SAFE_PREVIEW_LOG="$BASE/safe-confirm-web.log"
  assert_outbound_messaging_denied || die "outbound messaging deny policy is not active"
  note "PASS confirm precondition: outbound email/SMS transports disabled"

  sqldb -e "
    INSERT INTO tracking_status
      (status_id,description_th,description_en,success,cdate)
    VALUES
      (9001,'SYNTHETIC STATUS - NOT REAL','SYNTHETIC STATUS',0,
       '2026-08-19 00:00:00');
  "
  started_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)

  got=$(curl --disable --noproxy '*' -sS -L -o "$SAFE_PREVIEW_BODY" \
    -w '%{http_code}' --max-time 30 \
    -b "$SAFE_PREVIEW_JAR" -c "$SAFE_PREVIEW_JAR" \
    --data 'count_ex=1' "$WEB_BASE/ExcelConfirm")
  ck "C1 Status confirm HTTP" "200" "$got" || rc=1
  body_has "C1 Status confirm success" "Upload updated successfully" || rc=1
  ck "C1 Status upload row" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM uploadstaus
                   WHERE tracking_id='SYN0001'
                     AND Telephone='0000000000'
                     AND tracking_status=9001;")" || rc=1
  ck "C1 Status log row" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM status_log
                   WHERE order_id='SYNTHETIC-TRACK-001' AND update_id=9001;")" || rc=1
  ck "C1 Status order updated" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM request_order
                   WHERE trackID='SYNTHETIC-TRACK-001'
                     AND customerTel='0000000000'
                     AND action_status=3
                     AND date_update_status='2026-08-01 00:00:00';")" || rc=1

  sqldb -e "UPDATE request_order SET RepairPrice=321.00
             WHERE trackID='SYNTHETIC-TRACK-001'
               AND customerTel='0000000000';"
  got=$(curl --disable --noproxy '*' -sS -L -o "$SAFE_PREVIEW_BODY" \
    -w '%{http_code}' --max-time 30 \
    -b "$SAFE_PREVIEW_JAR" -c "$SAFE_PREVIEW_JAR" \
    --data 'count_ex=1' "$WEB_BASE/ExcelPriceConfirm")
  ck "C2 Price confirm HTTP" "200" "$got" || rc=1
  body_has "C2 Price confirm success" "Upload updated successfully" || rc=1
  ck "C2 Price order updated" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM request_order
                   WHERE trackID='SYNTHETIC-TRACK-001'
                     AND customerTel='0000000000'
                     AND RepairPrice=1234.00;")" || rc=1
  ck "C2 Price leaves Status upload row unchanged" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM uploadstaus
                   WHERE Telephone='0000000000';")" || rc=1
  ck "C2 Price leaves Status log unchanged" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM status_log
                   WHERE order_id='SYNTHETIC-TRACK-001';")" || rc=1

  got=$(curl --disable --noproxy '*' -sS -L -o "$SAFE_PREVIEW_BODY" \
    -w '%{http_code}' --max-time 30 \
    -b "$SAFE_PREVIEW_JAR" -c "$SAFE_PREVIEW_JAR" \
    --data 'count_ex=1' "$WEB_BASE/ExcelNewOrderConfirm")
  cp "$SAFE_PREVIEW_BODY" "$BASE/safe-confirm-new-order-response.html"
  # CI3 characterization only. CI4 acceptance must replace both warnings with a redirect
  # and rendered success page; this assertion must not become the migrated contract.
  ck "C3 New Order legacy warning HTTP" "200" "$got" || rc=1
  ck "C3 New Order legacy warning count" "2" \
    "$(grep -c 'A PHP Error was encountered' "$SAFE_PREVIEW_BODY" || true)" || rc=1
  body_has "C3 first monthly track warning captured" \
    "A non-numeric value encountered" || rc=1
  body_has "C3 redirect failure captured" \
    "Cannot modify header information" || rc=1
  body_lacks "C3 success page not rendered" "Upload updated successfully" || rc=1
  ck "C3 New Order created once" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM request_order
                   WHERE orderIDShow='SYN/NEW-001'
                     AND customerFullname='SYNTHETIC CUSTOMER - NOT REAL'
                     AND customerTel='0000000000'
                     AND action_status=2
                     AND RepairPrice=1234.00
                     AND requestDate='2026-08-01 00:00:00'
                     AND date_repair='2026-07-31 00:00:00';")" || rc=1
  new_track=$(sqldb -e "SELECT trackID FROM request_order
                         WHERE orderIDShow='SYN/NEW-001'
                           AND customerTel='0000000000'
                         ORDER BY request_id DESC LIMIT 1;")
  [[ "$new_track" =~ ^G[0-9]{8}$ ]] \
    && echo "PASS C3 generated synthetic track format" \
    || { echo "FAIL C3 generated synthetic track format"; rc=1; }
  ck "C3 New Order status log" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM status_log
                   WHERE order_id='$new_track' AND action_id=2;")" || rc=1
  ck "C3 total synthetic orders" "2" \
    "$(sqldb -e "SELECT COUNT(*) FROM request_order
                   WHERE customerFullname='SYNTHETIC CUSTOMER - NOT REAL'
                     AND customerTel='0000000000';")" || rc=1
  ck "C3 request-order recipients are synthetic only" "0" \
    "$(sqldb -e "SELECT COUNT(*) FROM request_order
                   WHERE customerFullname<>'SYNTHETIC CUSTOMER - NOT REAL'
                      OR customerTel<>'0000000000'
                      OR customerTel IS NULL
                      OR customerEmail IS NOT NULL;")" || rc=1
  ck "C3 operator identity is synthetic only" "0" \
    "$(sqldb -e "SELECT COUNT(*) FROM tbl_users
                   WHERE username<>'synthetic-preview'
                      OR email<>'synthetic-preview@example.invalid'
                      OR mobile<>'0000000000';")" || rc=1

  logs=$(dc logs --since "$started_at" web 2>&1)
  local route
  for route in ExcelConfirm ExcelPriceConfirm ExcelNewOrderConfirm; do
    if printf '%s\n' "$logs" | grep -q "POST /$route "; then
      echo "PASS C4 $route request observed"
    else
      echo "FAIL C4 $route request missing from access log"
      rc=1
    fi
  done
  if printf '%s\n' "$logs" | grep -q '"POST /ExcelNewOrderConfirm HTTP/1.1" 200 ' \
     && ! printf '%s\n' "$logs" | grep -q 'GET /UploadneworderexcelListing '; then
    echo "PASS C4 known legacy New Order redirect defect reproduced"
  else
    echo "FAIL C4 New Order redirect behavior changed"
    rc=1
  fi
  if printf '%s\n' "$logs" \
       | grep -qiE 'PHPMailer|SMTP|send_?sms|mail\(\).*disabled|curl_exec.*disabled'; then
    echo "FAIL C4 email/SMS attempt found in web log"
    rc=1
  else
    echo "PASS C4 outbound attempts=0"
  fi
  if printf '%s\n' "$logs" | grep -qiE 'PHP Fatal|Uncaught|A Database Error Occurred'; then
    echo "FAIL C4 fatal/database error found in web log"
    rc=1
  else
    echo "PASS C4 no fatal/database error"
  fi
  assert_outbound_messaging_denied || rc=1
  backup_files=$(dc exec -T db sh -c 'find /backup -type f -print | wc -l' | tr -d '[:space:]')
  ck "C4 backup volume remains empty" "0" "$backup_files" || rc=1

  {
    printf 'check\tobserved\n'
    printf 'confirm_routes\t3\n'
    printf 'upload_status_rows\t%s\n' \
      "$(sqldb -e "SELECT COUNT(*) FROM uploadstaus WHERE Telephone='0000000000';")"
    printf 'status_log_rows\t%s\n' \
      "$(sqldb -e "SELECT COUNT(*) FROM status_log
                     WHERE order_id IN ('SYNTHETIC-TRACK-001','$new_track');")"
    printf 'synthetic_orders\t%s\n' \
      "$(sqldb -e "SELECT COUNT(*) FROM request_order
                     WHERE customerFullname='SYNTHETIC CUSTOMER - NOT REAL'
                       AND customerTel='0000000000';")"
    printf 'outbound_attempts\t0\n'
    printf 'new_order_redirect\tblocked_by_known_legacy_warning\n'
  } > "$BASE/safe-confirm-observed.tsv"

  [ "$rc" = 0 ] \
    && note "SAFE SYNTHETIC CONFIRM CHARACTERIZATION PASSED WITH KNOWN LEGACY REDIRECT DEFECT" \
    || note "SAFE SYNTHETIC CONFIRM CHARACTERIZATION FAILED"
  return "$rc"
}

safe_confirm_negative_smoke() {
  local rc=0 got logs started_at backup_files route attempts
  local temp_pw_b hash_b hash_b_hex
  local csrf_form_token=present isolation_reproduced=no
  local operator_a_b_rows operator_a_a_rows outbound_attempts

  safe_preview_smoke || return 1
  SAFE_PREVIEW_LOG="$BASE/safe-confirm-negative-web.log"
  SAFE_PREVIEW_JAR_B=$(mktemp -t ci3-safe-preview-cookie-b)
  SAFE_PREVIEW_NEW_B=$(mktemp -t ci3-safe-preview-new-b)
  make_synthetic_preview_fixture "$SAFE_PREVIEW_NEW_B" \
    "SYN/NEW-B01" "SYNTHETIC-CMG-B"
  assert_outbound_messaging_denied || die "outbound messaging deny policy is not active"
  note "PASS negative precondition: outbound email/SMS transports disabled"

  sqldb -e "
    INSERT INTO tracking_status
      (status_id,description_th,description_en,success,cdate)
    VALUES
      (9001,'SYNTHETIC STATUS - NOT REAL','SYNTHETIC STATUS',0,
       '2026-08-20 00:00:00');
    UPDATE request_order SET RepairPrice=321.00
      WHERE trackID='SYNTHETIC-TRACK-001'
        AND customerTel='0000000000';
  "
  started_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)

  for attempts in 1 2; do
    got=$(curl --disable --noproxy '*' -sS -L -o "$SAFE_PREVIEW_BODY" \
      -w '%{http_code}' --max-time 30 \
      -b "$SAFE_PREVIEW_JAR" -c "$SAFE_PREVIEW_JAR" \
      --data 'count_ex=1' "$WEB_BASE/ExcelConfirm")
    ck "N1 Status confirm attempt $attempts HTTP" "200" "$got" || rc=1
    body_has "N1 Status confirm attempt $attempts accepted" \
      "Upload updated successfully" || rc=1
  done
  ck "N1 Status replay creates duplicate upload rows" "2" \
    "$(sqldb -e "SELECT COUNT(*) FROM uploadstaus
                   WHERE tracking_id='SYN0001'
                     AND Telephone='0000000000'
                     AND tracking_status=9001;")" || rc=1
  ck "N1 Status replay creates duplicate log rows" "2" \
    "$(sqldb -e "SELECT COUNT(*) FROM status_log
                   WHERE order_id='SYNTHETIC-TRACK-001' AND update_id=9001;")" || rc=1

  for attempts in 1 2; do
    got=$(curl --disable --noproxy '*' -sS -L -o "$SAFE_PREVIEW_BODY" \
      -w '%{http_code}' --max-time 30 \
      -b "$SAFE_PREVIEW_JAR" -c "$SAFE_PREVIEW_JAR" \
      --data 'count_ex=1' "$WEB_BASE/ExcelPriceConfirm")
    ck "N2 Price confirm attempt $attempts HTTP" "200" "$got" || rc=1
    body_has "N2 Price confirm attempt $attempts accepted" \
      "Upload updated successfully" || rc=1
  done
  ck "N2 Price replay leaves final value deterministic" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM request_order
                   WHERE trackID='SYNTHETIC-TRACK-001'
                     AND customerTel='0000000000'
                     AND RepairPrice=1234.00;")" || rc=1
  ck "N2 Price replay adds no Status rows" "2" \
    "$(sqldb -e "SELECT COUNT(*) FROM uploadstaus WHERE Telephone='0000000000';")" || rc=1

  temp_pw_b=$(od -An -N16 -tx1 /dev/urandom | tr -d ' \n')
  [ "${#temp_pw_b}" = 32 ] || die "failed to generate operator B password"
  hash_b=$(printf '%s' "$temp_pw_b" \
    | dc exec -T web php -r '$p = stream_get_contents(STDIN); echo password_hash($p, PASSWORD_BCRYPT);')
  [ -n "$hash_b" ] || die "failed to generate operator B password hash"
  hash_b_hex=$(printf '%s' "$hash_b" | od -An -v -tx1 | tr -d ' \n')
  sqldb -e "
    INSERT INTO tbl_users
      (email,username,password,name,mobile,group_id,roleId,branch_id,branch_type_id,
       isDeleted,createdBy,createdDtm)
    VALUES
      ('synthetic-preview-b@example.invalid','synthetic-preview-b',UNHEX('$hash_b_hex'),
       'SYNTHETIC OPERATOR B - NOT REAL','0000000000',4,1,1,NULL,0,0,
       '2026-08-20 00:00:00');
  "
  ck "N3 synthetic operator B" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM tbl_users
                   WHERE username='synthetic-preview-b'
                     AND email='synthetic-preview-b@example.invalid';")" || rc=1

  printf 'username=synthetic-preview-b&password=%s' "$temp_pw_b" \
    | curl --disable --noproxy '*' -sS -o "$SAFE_PREVIEW_BODY" \
        -c "$SAFE_PREVIEW_JAR_B" -b "$SAFE_PREVIEW_JAR_B" \
        --max-time 30 --data-binary @- "$WEB_BASE/loginMe"
  temp_pw_b=""
  hs "N3 operator B reaches New Order listing" 200 "/UploadneworderexcelListing" \
    -b "$SAFE_PREVIEW_JAR_B" -c "$SAFE_PREVIEW_JAR_B" || rc=1
  body_lacks "N3 operator B not returned to login" 'name="password"' || rc=1

  got=$(curl --disable --noproxy '*' -sS -o "$SAFE_PREVIEW_BODY" \
    -w '%{http_code}' --max-time 60 \
    -b "$SAFE_PREVIEW_JAR_B" -c "$SAFE_PREVIEW_JAR_B" \
    -F "file=@$SAFE_PREVIEW_NEW_B;filename=synthetic-new-order-b.xlsx;type=application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" \
    "$WEB_BASE/ExcelNewOrderDataAdd")
  ck "N3 operator B preview HTTP" "200" "$got" || rc=1
  body_has "N3 operator B preview row" "SYN/NEW-B01" || rc=1
  body_lacks "N3 operator A pending row overwritten" "SYN/NEW-001" || rc=1
  ck "N3 global temp contains operator B row" "1" \
    "$(sqldb -e "SELECT COUNT(*) FROM temp_updatestatus_neworder
                   WHERE temp_orderIDShow='SYN/NEW-B01'
                     AND temp_customerTel='0000000000';")" || rc=1
  ck "N3 global temp lost operator A row" "0" \
    "$(sqldb -e "SELECT COUNT(*) FROM temp_updatestatus_neworder
                   WHERE temp_orderIDShow='SYN/NEW-001'
                     AND temp_customerTel='0000000000';")" || rc=1

  if grep -qiE "name=[\"'][^\"']*csrf|csrf_test_name" "$SAFE_PREVIEW_BODY"; then
    echo "FAIL N3 preview form unexpectedly contains a CSRF token"
    rc=1
  else
    csrf_form_token=absent
    echo "PASS N3 CSRF token absent from preview form"
  fi

  got=$(curl --disable --noproxy '*' -sS -L -o "$SAFE_PREVIEW_BODY" \
    -w '%{http_code}' --max-time 30 \
    -b "$SAFE_PREVIEW_JAR" -c "$SAFE_PREVIEW_JAR" \
    --data 'count_ex=1' "$WEB_BASE/ExcelNewOrderConfirm")
  ck "N3 operator A confirms operator B batch HTTP" "200" "$got" || rc=1
  operator_a_b_rows=$(sqldb -e "SELECT COUNT(*) FROM request_order
                                  WHERE orderIDShow='SYN/NEW-B01'
                                    AND customerFullname='SYNTHETIC CUSTOMER - NOT REAL'
                                    AND customerTel='0000000000';")
  operator_a_a_rows=$(sqldb -e "SELECT COUNT(*) FROM request_order
                                  WHERE orderIDShow='SYN/NEW-001'
                                    AND customerTel='0000000000';")
  ck "N3 operator A created operator B row" "1" \
    "$operator_a_b_rows" || rc=1
  ck "N3 operator A row was not created" "0" \
    "$operator_a_a_rows" || rc=1
  if [ "$operator_a_b_rows" = 1 ] && [ "$operator_a_a_rows" = 0 ]; then
    isolation_reproduced=yes
    echo "PASS N3 two-user overlapping-session isolation defect reproduced"
  else
    echo "FAIL N3 two-user overlapping-session isolation result changed"
    rc=1
  fi

  got=$(curl --disable --noproxy '*' -sS -L -o "$SAFE_PREVIEW_BODY" \
    -w '%{http_code}' --max-time 30 \
    -b "$SAFE_PREVIEW_JAR_B" -c "$SAFE_PREVIEW_JAR_B" \
    --data 'count_ex=1' "$WEB_BASE/ExcelNewOrderConfirm")
  ck "N3 operator B replays shared batch HTTP" "200" "$got" || rc=1
  ck "N3 cross-session replay creates duplicate orders" "2" \
    "$(sqldb -e "SELECT COUNT(*) FROM request_order
                   WHERE orderIDShow='SYN/NEW-B01'
                     AND customerFullname='SYNTHETIC CUSTOMER - NOT REAL'
                     AND customerTel='0000000000';")" || rc=1
  ck "N3 cross-session replay creates duplicate logs" "2" \
    "$(sqldb -e "SELECT COUNT(*) FROM status_log
                   WHERE order_id IN
                     (SELECT trackID FROM request_order
                       WHERE orderIDShow='SYN/NEW-B01'
                         AND customerTel='0000000000')
                     AND action_id=2;")" || rc=1

  logs=$(dc logs --since "$started_at" web 2>&1)
  for route in ExcelConfirm ExcelPriceConfirm ExcelNewOrderConfirm; do
    attempts=$(printf '%s\n' "$logs" | grep -c "POST /$route " || true)
    ck "N4 $route replay requests observed" "2" "$attempts" || rc=1
  done
  ck "N4 operator B preview request observed" "1" \
    "$(printf '%s\n' "$logs" | grep -c 'POST /ExcelNewOrderDataAdd ' || true)" || rc=1
  if printf '%s\n' "$logs" \
       | grep -qiE 'PHPMailer|SMTP|send_?sms|mail\(\).*disabled|curl_exec.*disabled'; then
    echo "FAIL N4 email/SMS attempt found in web log"
    rc=1
  else
    echo "PASS N4 outbound attempts=0"
  fi
  outbound_attempts=$(printf '%s\n' "$logs" \
    | grep -ciE 'PHPMailer|SMTP|send_?sms|mail\(\).*disabled|curl_exec.*disabled' || true)
  if printf '%s\n' "$logs" | grep -qiE 'PHP Fatal|Uncaught|A Database Error Occurred'; then
    echo "FAIL N4 fatal/database error found in web log"
    rc=1
  else
    echo "PASS N4 no fatal/database error"
  fi
  ck "N4 request-order recipients are synthetic only" "0" \
    "$(sqldb -e "SELECT COUNT(*) FROM request_order
                   WHERE customerFullname<>'SYNTHETIC CUSTOMER - NOT REAL'
                      OR customerTel<>'0000000000'
                      OR customerTel IS NULL
                      OR customerEmail IS NOT NULL;")" || rc=1
  ck "N4 operator identities are synthetic only" "0" \
    "$(sqldb -e "SELECT COUNT(*) FROM tbl_users
                   WHERE username NOT IN ('synthetic-preview','synthetic-preview-b')
                      OR email NOT LIKE 'synthetic-preview%@example.invalid'
                      OR mobile<>'0000000000';")" || rc=1
  assert_outbound_messaging_denied || rc=1
  backup_files=$(dc exec -T db sh -c 'find /backup -type f -print | wc -l' | tr -d '[:space:]')
  ck "N4 backup volume remains empty" "0" "$backup_files" || rc=1

  {
    printf 'check\tobserved\n'
    printf 'status_confirm_requests\t%s\n' \
      "$(printf '%s\n' "$logs" | grep -c 'POST /ExcelConfirm ' || true)"
    printf 'status_upload_rows\t%s\n' \
      "$(sqldb -e "SELECT COUNT(*) FROM uploadstaus WHERE Telephone='0000000000';")"
    printf 'status_log_rows\t%s\n' \
      "$(sqldb -e "SELECT COUNT(*) FROM status_log
                     WHERE order_id='SYNTHETIC-TRACK-001' AND update_id=9001;")"
    printf 'price_confirm_requests\t%s\n' \
      "$(printf '%s\n' "$logs" | grep -c 'POST /ExcelPriceConfirm ' || true)"
    printf 'price_final_value\t%s\n' \
      "$(sqldb -e "SELECT RepairPrice FROM request_order
                     WHERE trackID='SYNTHETIC-TRACK-001';")"
    printf 'new_order_confirm_requests\t%s\n' \
      "$(printf '%s\n' "$logs" | grep -c 'POST /ExcelNewOrderConfirm ' || true)"
    printf 'new_order_rows\t%s\n' \
      "$(sqldb -e "SELECT COUNT(*) FROM request_order
                     WHERE orderIDShow='SYN/NEW-B01'
                       AND customerTel='0000000000';")"
    printf 'new_order_log_rows\t%s\n' \
      "$(sqldb -e "SELECT COUNT(*) FROM status_log
                     WHERE order_id IN
                       (SELECT trackID FROM request_order
                         WHERE orderIDShow='SYN/NEW-B01'
                           AND customerTel='0000000000')
                       AND action_id=2;")"
    printf 'new_order_batch_owner_columns\t%s\n' \
      "$(sqldb -e "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA='$DB'
                       AND TABLE_NAME='temp_updatestatus_neworder'
                       AND COLUMN_NAME IN ('batch_id','owner_id','user_id');")"
    printf 'operator_a_confirmed_operator_b_batch\t%s\n' "$isolation_reproduced"
    printf 'operator_a_pending_rows_after_b_preview\t%s\n' "$operator_a_a_rows"
    printf 'csrf_form_token\t%s\n' "$csrf_form_token"
    printf 'csrf_tokenless_confirm_routes\taccepted_3_of_3\n'
    printf 'outbound_attempts\t%s\n' "$outbound_attempts"
  } > "$BASE/safe-confirm-negative-observed.tsv"

  [ "$rc" = 0 ] \
    && note "SAFE SYNTHETIC CONFIRM NEGATIVE/ISOLATION CHARACTERIZATION PASSED WITH KNOWN CI3 DEFECTS" \
    || note "SAFE SYNTHETIC CONFIRM NEGATIVE/ISOLATION CHARACTERIZATION FAILED"
  return "$rc"
}

smoke() {
  lock
  local rc=0 id user_id username temp_pw hash track
  # Globals, not locals: the EXIT trap outlives this function's scope.
  SMOKE_BODY=$(mktemp); SMOKE_JAR=$(mktemp)
  trap 'rm -f "$SMOKE_BODY" "$SMOKE_JAR"; rmdir "$LOCK" 2>/dev/null || true' EXIT

  local repo_before; repo_before=$(ci3_repo_state)

  note "taking a restore point before touching any data"
  id=$(backup "pre-smoke" | tail -1)

  # Login matches tbl_users.username (not email, despite the form field naming) and joins
  # tbl_roles, so pick a user that satisfies both.
  read -r user_id username <<<"$(sqldb -e "
    SELECT u.userId, u.username FROM tbl_users u JOIN tbl_roles r ON r.roleId=u.roleId
    WHERE u.isDeleted=0 AND u.username IS NOT NULL AND u.username<>'' ORDER BY u.userId LIMIT 1;")"
  [ -n "$user_id" ] || die "no loginable user found"
  track=$(sqldb -e "SELECT trackID FROM request_order WHERE trackID IS NOT NULL AND trackID<>'' ORDER BY request_id DESC LIMIT 1;")
  [ -n "$track" ] || die "no trackID found"
  note "using userId=$user_id trackID=$track"

  # Generate the hash with the same PHP that will verify it. Password rule caps at 32 chars
  # (Login.php:55).
  temp_pw="smoke-$(date +%s)"
  hash=$(dc exec -T web php -r 'echo password_hash($argv[1], PASSWORD_BCRYPT);' "$temp_pw")
  [ -n "$hash" ] || die "failed to generate bcrypt hash"
  sqldb -e "UPDATE tbl_users SET password='$hash' WHERE userId=$user_id;"

  note "--- public pages ---"
  hs "S1 GET / (track)" 200 "/" || rc=1
  hs "S2 GET /track_th/index" 200 "/track_th/index" || rc=1
  body_lacks "S2 no mojibake in Thai page" "à¸" || rc=1
  grep -qE $'[฀-๿]' "$SMOKE_BODY" && echo "PASS S2 Thai characters present" || { echo "FAIL S2: no Thai characters"; rc=1; }
  hs "S3 GET /track/trackstatus/$track" 200 "/track/trackstatus/$track" || rc=1
  hs "S4 GET /login" 200 "/login" || rc=1

  note "--- authenticated flow ---"
  # loginMe redirects to /dashboard on success and back to /login on failure.
  curl --disable --noproxy '*' -s -o "$SMOKE_BODY" \
       -c "$SMOKE_JAR" -b "$SMOKE_JAR" --max-time 30 \
       -d "username=$username" -d "password=$temp_pw" "$WEB_BASE/loginMe" >/dev/null
  hs "S5 GET /dashboard after login" 200 "/dashboard" -b "$SMOKE_JAR" -c "$SMOKE_JAR" || rc=1
  body_lacks "S5 not bounced back to the login form" 'name="password"' || rc=1
  hs "S6 GET /order" 200 "/order" -b "$SMOKE_JAR" -c "$SMOKE_JAR" || rc=1

  note "--- WP-00F deny tests ---"
  local d code
  for d in /application/config/config.php /system/core/CodeIgniter.php /SECRETS-LOCAL.md; do
    code=$(curl --disable --noproxy '*' -s -o /dev/null -w '%{http_code}' \
                --max-time 15 "$WEB_BASE$d")
    if [ "$code" = 200 ]; then echo "FAIL S7 $d served with 200"; rc=1; else echo "PASS S7 $d denied ($code)"; fi
  done
  # tools/ never entered the image, so this must not return admin-tool content. It answers
  # 200 only because the app's 404 handler is broken - see the S7b note below.
  curl --disable --noproxy '*' -s -o "$SMOKE_BODY" \
       --max-time 15 "$WEB_BASE/tools/pma/" >/dev/null
  body_lacks "S7 phpMyAdmin not served" "phpMyAdmin" || rc=1

  # Known legacy defect, not a migration regression: application/controllers/Error.php is
  # never loaded because PHP 7 has a built-in class named Error, so 404_override resolves to
  # the built-in and every missing route answers 200 with a PHP warning instead of 404.
  # Production runs PHP 7.4.32, so this is live there too. Reported, not asserted.
  if grep -q "does not have a method 'index'" "$SMOKE_BODY"; then
    echo "INFO S7b known defect confirmed: 404 handler broken (controller Error collides with PHP 7 built-in Error)"
  fi

  note "--- server logs ---"
  if dc logs web 2>&1 | grep -iE 'PHP Fatal|Uncaught|Unable to connect to your database|A Database Error Occurred' | head -5 | grep -q .; then
    dc logs web 2>&1 | grep -iE 'PHP Fatal|Uncaught|Unable to connect|A Database Error Occurred' | head -5
    echo "FAIL S8 web log contains fatal/database errors"; rc=1
  else
    echo "PASS S8 no fatal or database errors in web log"
  fi
  if dc logs db 2>&1 | grep -qE 'Illegal mix of collations|ERROR 1267'; then
    echo "FAIL S9 collation mix error in database log"; rc=1
  else
    echo "PASS S9 no collation mix errors"
  fi

  note "--- restoring the pre-smoke state ---"
  restore "$id"
  verify > "$BASE/smoke-postrestore-verify.txt" 2>&1 \
    && echo "PASS S10 full verification passed after restore" \
    || { tail -20 "$BASE/smoke-postrestore-verify.txt"; echo "FAIL S10 verification failed after restore"; rc=1; }
  ck "S10 temp password rolled back" "0" \
     "$(sqldb -e "SELECT COUNT(*) FROM tbl_users WHERE userId=$user_id AND password='$hash';")" || rc=1

  local repo_after; repo_after=$(ci3_repo_state)
  ck "S11 CI3 repo untouched" "$repo_before" "$repo_after" || rc=1

  [ "$rc" = 0 ] && note "CI3 SMOKE SUITE PASSED" || note "CI3 SMOKE SUITE FAILED"
  return $rc
}

# ---------------------------------------------------------------- backup / restore (BLK-010)

# Backups live on the owned `backup_data` volume, never a bind mount or a host path:
# Gate 1D fails on shared or external backup storage.
BK=/backup

# Run a shell inside the db container with the root password available.
dbsh() { dc exec -T -e MYSQL_PWD="$MARIADB_ROOT_PASSWORD" db bash -c "$1"; }

backup() {
  lock
  local id started ended secs bytes sum
  id="bk-$(date -u +%Y%m%dT%H%M%SZ)${1:+-$1}"
  started=$(date +%s)
  # --single-transaction gives a consistent snapshot without locking now that every table
  # is InnoDB. --add-drop-database makes the restore self-contained and re-runnable.
  dbsh "set -o pipefail; mariadb-dump -u root --single-transaction --quick \
        --default-character-set=utf8mb4 --add-drop-database --databases '$DB' \
        | gzip -6 > '$BK/$id.sql.gz'"
  ended=$(date +%s); secs=$((ended - started))
  bytes=$(dbsh "stat -c %s '$BK/$id.sql.gz'" | tr -d '\r')
  sum=$(dbsh "sha256sum '$BK/$id.sql.gz' | cut -d' ' -f1" | tr -d '\r')
  dbsh "printf '%s  %s\n' '$sum' '$id.sql.gz' > '$BK/$id.sha256'"
  printf '%s\t%s\t%s\t%s\n' "$id" "$bytes" "$secs" "$sum" >> "$BASE/backup-manifest.tsv"
  note "PASS backup id=$id bytes=$bytes seconds=$secs sha256=${sum:0:16}..."
  echo "$id"
}

backups() {
  note "backups on owned volume $BK"
  dbsh "ls -l $BK/*.sql.gz 2>/dev/null || echo '(none)'"
  [ -f "$BASE/backup-manifest.tsv" ] && { echo "id/bytes/seconds/sha256:"; cat "$BASE/backup-manifest.tsv"; } || true
}

restore() {
  lock
  local id=${1:?usage: restore <backup-id>} started ended secs
  # Never restore an archive that fails its own checksum or gzip integrity.
  dbsh "cd $BK && sha256sum -c '$id.sha256'" >/dev/null || die "checksum mismatch for $id"
  dbsh "gzip -t '$BK/$id.sql.gz'" || die "corrupt archive $id"
  note "restoring $id"
  started=$(date +%s)
  dbsh "set -o pipefail; gunzip -c '$BK/$id.sql.gz' | mariadb -u root --default-character-set=utf8mb4"
  ended=$(date +%s); secs=$((ended - started))
  note "PASS restore id=$id seconds=$secs"
  printf 'restore\t%s\t%s\n' "$id" "$secs" >> "$BASE/restore-log.tsv"
}

upgrade_check() {
  # Proves the 10.6-era schema needs no upgrade action on 11.4. CHECK TABLE ... FOR UPGRADE
  # under the hood, so it reads rather than rewrites system tables.
  local out bad total
  out=$(dbsh "mariadb-check -u root --check-upgrade --all-databases" 2>&1)
  total=$(printf '%s\n' "$out" | grep -c . || true)
  # Every checked table reports "<db>.<table>   OK". Anything else is a finding.
  bad=$(printf '%s\n' "$out" | grep -vE 'OK$' | grep -c . || true)
  if [ "$bad" != 0 ]; then
    printf '%s\n' "$out" | grep -vE 'OK$'
    echo "FAIL upgrade check: $bad line(s) did not report OK"
    return 1
  fi
  note "PASS upgrade check: $total tables OK, none needs upgrading on 11.4"
}

# BLK-010 wants timed restore and rollback logs over two rounds, and treats "no successful
# restore/rollback" as a No-Go. This drives the whole loop and fails loudly.
rehearsal() {
  lock
  local round id rows_before rows_after
  for round in 1 2; do
    note "=== rehearsal round $round/2 ==="
    id=$(backup "r$round" | tail -1)
    rows_before=$(sqldb -e "SELECT COUNT(*) FROM rating;")

    # Rollback drill: damage the database, then prove the backup brings it back.
    note "round $round: simulating data loss (DROP TABLE rating)"
    sqldb -e "DROP TABLE rating;"
    [ "$(sqldb -e "SELECT COUNT(*) FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA='$DB' AND TABLE_NAME='rating';")" = 0 ] \
      || die "damage step did not take effect; the drill would prove nothing"

    restore "$id"
    rows_after=$(sqldb -e "SELECT COUNT(*) FROM rating;")
    ck "round $round rollback restored row count" "$rows_before" "$rows_after" || die "rollback failed"
    upgrade_check || die "upgrade check failed in round $round"
    verify > "$BASE/rehearsal-round$round-verify.txt" 2>&1 \
      || { tail -20 "$BASE/rehearsal-round$round-verify.txt"; die "verify failed after restore in round $round"; }
    note "round $round: full verification passed after restore"
  done
  note "BLK-010 REHEARSAL COMPLETE (2 rounds)"
  cat "$BASE/backup-manifest.tsv" "$BASE/restore-log.tsv"
}

# ---------------------------------------------------------------- collation parity (BLK-001)

# Proves the chosen utf8mb4 collation behaves exactly like the legacy utf8mb3_general_ci.
# Works inside one server because the data holds no 4-byte characters, so
# CONVERT(col USING utf8mb3) is lossless and the legacy collation stays available.
collation() {
  local out="$BASE/collation-parity.txt" rc=0 gen
  : > "$out"

  note "C1 weight parity across every string column"
  gen=$(sqldb -e "
    SELECT CONCAT('SELECT ''', TABLE_NAME, '.', COLUMN_NAME, ''' AS col, COUNT(*) AS n, ',
      'SUM(WEIGHT_STRING(CONVERT(\`', COLUMN_NAME, '\` USING utf8mb3) COLLATE utf8mb3_general_ci) <=> ',
      'WEIGHT_STRING(\`', COLUMN_NAME, '\` COLLATE utf8mb4_general_ci)) AS same FROM \`', TABLE_NAME, '\` UNION ALL')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA='$DB' AND DATA_TYPE IN ('char','varchar','text','tinytext','mediumtext','longtext')
    ORDER BY TABLE_NAME, ORDINAL_POSITION;")
  gen="${gen% UNION ALL};"
  # Empty tables yield n=0 and same=NULL, which is not a mismatch.
  local mismatched
  mismatched=$(printf '%s\n' "$gen" | sqldb | awk -F'\t' '$2>0 && $2!=$3' | tee -a "$out" | wc -l | tr -d ' ')
  ck "C1 columns whose weights differ from legacy" "0" "$mismatched" || rc=1

  note "C2 whole-table ordering and distinctness vs legacy"
  local res
  res=$(sqldb -e "
    WITH a AS (SELECT id, ROW_NUMBER() OVER (ORDER BY CONVERT(Listname USING utf8mb3) COLLATE utf8mb3_general_ci, id) rn FROM uploadstaus),
         b AS (SELECT id, ROW_NUMBER() OVER (ORDER BY Listname COLLATE utf8mb4_general_ci, id) rn FROM uploadstaus)
    SELECT COUNT(*) FROM a JOIN b USING (id) WHERE a.rn <> b.rn;")
  ck "C2 rows out of place under utf8mb4_general_ci" "0" "$res" || rc=1
  res=$(sqldb -e "
    SELECT COUNT(DISTINCT Listname COLLATE utf8mb4_general_ci)
           - COUNT(DISTINCT CONVERT(Listname USING utf8mb3) COLLATE utf8mb3_general_ci) FROM uploadstaus;")
  ck "C2 distinct-value drift under utf8mb4_general_ci" "0" "$res" || rc=1

  note "C3 contrast: the rejected candidate must actually differ"
  res=$(sqldb -e "
    WITH a AS (SELECT id, ROW_NUMBER() OVER (ORDER BY CONVERT(Listname USING utf8mb3) COLLATE utf8mb3_general_ci, id) rn FROM uploadstaus),
         c AS (SELECT id, ROW_NUMBER() OVER (ORDER BY Listname COLLATE utf8mb4_unicode_ci, id) rn FROM uploadstaus)
    SELECT COUNT(*) FROM a JOIN c USING (id) WHERE a.rn <> c.rn;")
  echo "INFO C3 rows out of place under utf8mb4_unicode_ci: $res" | tee -a "$out"
  [ "$res" -gt 0 ] || { echo "FAIL C3 contrast collation is indistinguishable; the choice is unproven"; rc=1; }
  res=$(sqldb -e "
    SELECT COUNT(DISTINCT CONVERT(Listname USING utf8mb3) COLLATE utf8mb3_general_ci)
           - COUNT(DISTINCT Listname COLLATE utf8mb4_unicode_ci) FROM uploadstaus;")
  echo "INFO C3 distinct values lost under utf8mb4_unicode_ci: $res" | tee -a "$out"

  [ "$rc" = 0 ] && note "COLLATION PARITY PROVEN (evidence: $out)" || note "COLLATION PARITY FAILED"
  return $rc
}

# ---------------------------------------------------------------- entrypoint

case "${1:-}" in
  preflight) preflight ;;
  snapshot)  snapshot "${2:?usage: snapshot before|after}" ;;
  diff)      diff_hosts ;;
  expect)    expect ;;
  up)        up ;;
  import)    shift; import "$@" ;;
  verify)    verify ;;
  collation) collation ;;
  backup)    shift; backup "${1:-}" ;;
  backups)   backups ;;
  restore)   shift; restore "${1:-}" ;;
  upgrade-check) upgrade_check ;;
  rehearsal) rehearsal ;;
  web-build) web_build ;;
  web-up)    web_up ;;
  wp00c-fixture-validate) wp00c_fixture_validate ;;
  wp00c-fixture-seed) wp00c_fixture_seed ;;
  wp00c-fixture-verify) wp00c_fixture_verify ;;
  wp00c-fixture-clean) wp00c_fixture_clean ;;
  safe-preview-smoke) safe_preview_smoke ;;
  safe-confirm-smoke) safe_confirm_smoke ;;
  safe-confirm-negative-smoke) safe_confirm_negative_smoke ;;
  smoke)     smoke ;;
  # Backward-compatible name from WP-00C. Route through the stricter empty-DB,
  # zero-backup, loopback-only gate so this command cannot preserve real PII.
  excel-preview-smoke) safe_preview_smoke ;;
  reset)     reset_db ;;
  status)    dc ps -a; dc port db 3306 || true ;;
  down)      lock; dc ps -a; dc down ;;
  *) echo "usage: db/dbctl.sh [--runtime-root ABSOLUTE_PATH] <preflight|snapshot before|snapshot after|diff|expect|up|import [file..]|verify|collation|backup [label]|backups|restore <id>|upgrade-check|rehearsal|web-build|web-up|wp00c-fixture-validate|wp00c-fixture-seed|wp00c-fixture-verify|wp00c-fixture-clean|safe-preview-smoke|safe-confirm-smoke|safe-confirm-negative-smoke|excel-preview-smoke|smoke|reset|status|down>"; exit 2 ;;
esac
