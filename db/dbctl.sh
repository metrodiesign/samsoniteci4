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

PROJECT=samsonitetracking-ci4-migration
ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
COMPOSE="$ROOT/compose.yaml"
ENVFILE="$ROOT/.env"
EV="$ROOT/evidence/db-foundation-001"
ISO="$EV/19-docker-isolation"
BASE="$EV/01-baseline"
MANIFEST="$EV/00-manifest"
LOCK="$ROOT/.dbctl.lock"

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
set -a; . "$ENVFILE"; set +a
DB=${MARIADB_DATABASE:?}
DUMP_DIR=${DUMP_DIR:?}

mkdir -p "$ISO" "$BASE" "$MANIFEST"

die()  { echo "FAIL: $*" >&2; exit 1; }
note() { echo "==> $*"; }

lock() {
  mkdir "$LOCK" 2>/dev/null || die "another dbctl run holds $LOCK"
  trap 'rmdir "$LOCK" 2>/dev/null || true' EXIT
}

# Single choke point for docker. Always scoped to this project and this compose file.
dc() { docker compose -p "$PROJECT" -f "$COMPOSE" "$@"; }

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
  netstat -an -f inet -p tcp | awk '$NF=="LISTEN"{print $4}' | sort -u > "$ISO/$tag-ports.txt"
  note "snapshot '$tag' written to $ISO"
}

diff_hosts() {
  local rc=0 f
  : > "$ISO/noninterference.diff"
  # Lines this project is allowed to own: anything carrying the project name, plus the
  # single loopback port it publishes. Everything else must be byte-identical.
  local mine="$PROJECT|^127\.0\.0\.1\.$DB_HOST_PORT\$"
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

  dc config > "$ISO/rendered-config.yaml"
  local bad
  bad=$(grep -cE 'container_name|privileged|network_mode|^ *pid:|^ *ipc:|docker\.sock|external: true|type: bind' \
        "$ISO/rendered-config.yaml" || true)
  [ "$bad" = 0 ] || die "rendered config contains a forbidden construct ($bad hits)"
  [ "$(grep -c '@sha256:' "$ISO/rendered-config.yaml")" -ge 1 ] || die "image is not digest-pinned"
  [ "$(grep -c 'published:' "$ISO/rendered-config.yaml")" = 1 ] || die "expected exactly one published port"
  grep -q 'host_ip: 127.0.0.1' "$ISO/rendered-config.yaml" || die "published port is not loopback-only"

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

# ---------------------------------------------------------------- entrypoint

case "${1:-}" in
  preflight) preflight ;;
  snapshot)  snapshot "${2:?usage: snapshot before|after}" ;;
  diff)      diff_hosts ;;
  expect)    expect ;;
  up)        up ;;
  import)    shift; import "$@" ;;
  verify)    verify ;;
  reset)     reset_db ;;
  status)    dc ps -a; dc port db 3306 || true ;;
  down)      lock; dc ps -a; dc down ;;
  *) echo "usage: db/dbctl.sh <preflight|snapshot before|snapshot after|diff|expect|up|import [file..]|verify|reset|status|down>"; exit 2 ;;
esac
