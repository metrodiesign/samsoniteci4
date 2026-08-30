#!/usr/bin/env bash
set -Eeuo pipefail

ROOT=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
JUNIT_OUTPUT=""
if [ "$#" -gt 0 ]; then
  [ "$#" = 2 ] && [ "$1" = --junit ] && [ -n "$2" ] \
    || { echo 'usage: scripts/ci4-concurrency-check.sh [--junit PATH]' >&2; exit 2; }
  JUNIT_OUTPUT=$2
fi
export DOCKER_CONFIG=${DOCKER_CONFIG:-/tmp/samsonite-ci4-docker-config}
CI4_IMAGE=samsonitetracking-ci4:4.7.4-php8.5.7
DB_IMAGE='mariadb:11.4.12@sha256:67873d30a17f6a9c331f06363b2fa15f38abca415529966d67c84f87f82439fe'
suffix="${BASHPID:-$$}-$(date +%s)"
network="ci4-concurrency-${suffix}"
database="ci4-concurrency-db-${suffix}"
worker_one="ci4-concurrency-a-${suffix}"
worker_two="ci4-concurrency-b-${suffix}"
tracking_server="ci4-tracking-server-${suffix}"
tracking_one="ci4-tracking-a-${suffix}"
tracking_two="ci4-tracking-b-${suffix}"
order_one="ci4-order-a-${suffix}"
order_two="ci4-order-b-${suffix}"
import_one="ci4-import-a-${suffix}"
import_two="ci4-import-b-${suffix}"
isolation_one="ci4-isolation-a-${suffix}"
isolation_two="ci4-isolation-b-${suffix}"
password='synthetic-concurrency-only'
encryption_key='hex2bin:2424242424242424242424242424242424242424242424242424242424242424'

cleanup() {
  docker rm -f \
    "$worker_one" "$worker_two" "$order_one" "$order_two" "$import_one" "$import_two" "$isolation_one" "$isolation_two" "$tracking_one" "$tracking_two" "$tracking_server" "$database" \
    >/dev/null 2>&1 || true
  docker network rm "$network" >/dev/null 2>&1 || true
}
trap cleanup EXIT

mkdir -p "$DOCKER_CONFIG"
ci4_image_fresh=0
if docker image inspect "$CI4_IMAGE" >/dev/null 2>&1; then
  image_lock=$(docker run --rm "$CI4_IMAGE" cksum composer.lock | awk '{print $1" "$2}')
  repo_lock=$(cksum "$ROOT/composer.lock" | awk '{print $1" "$2}')
  [ "$image_lock" = "$repo_lock" ] && ci4_image_fresh=1
fi
[ "$ci4_image_fresh" = 1 ] \
  || docker build -f "$ROOT/Dockerfile.ci4" --tag "$CI4_IMAGE" "$ROOT" >/dev/null
docker network create "$network" >/dev/null
docker run --detach --rm \
  --name "$database" \
  --network "$network" \
  --tmpfs /var/lib/mysql:rw,noexec,nosuid,size=512m \
  --env MARIADB_ROOT_PASSWORD="$password" \
  --env MARIADB_DATABASE=ci4_concurrency \
  --env MARIADB_USER=ci4 \
  --env MARIADB_PASSWORD="$password" \
  "$DB_IMAGE" >/dev/null

ready=0
for _ in $(seq 1 45); do
  if docker exec "$database" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; then
    ready=1
    break
  fi
  sleep 1
done
[ "$ready" = 1 ] || { echo 'FAIL: isolated MariaDB did not become ready' >&2; exit 1; }

runtime=(
  --network "$network"
  --env CI_ENVIRONMENT=development
  --env app_baseURL=http://example.invalid/
  --env database_default_hostname="$database"
  --env database_default_port=3306
  --env database_default_database=ci4_concurrency
  --env database_default_username=ci4
  --env database_default_password="$password"
  --env database_default_DBDriver=MySQLi
  --env encryption_driver=Sodium
  --env encryption_key="$encryption_key"
  --volume "$ROOT/app:/app/app:ro"
  --volume "$ROOT/spark:/app/spark:ro"
)
probe_runtime=(
  "${runtime[@]}"
  --volume "$ROOT/tests/ci4/support/Commands:/app/app/Commands:ro"
)

docker exec -i "$database" mariadb --batch -uci4 -p"$password" ci4_concurrency <<'SQL'
CREATE TABLE book (
  book_id INT PRIMARY KEY,
  branch_id INT NOT NULL,
  book_detail VARCHAR(3) NOT NULL,
  status INT NOT NULL
);
CREATE TABLE branch (
  branch_id INT PRIMARY KEY,
  branch_type INT NOT NULL,
  default_suffix VARCHAR(10) NOT NULL
);
CREATE TABLE `type` (type_id INT PRIMARY KEY, type_details VARCHAR(250) NOT NULL);
CREATE TABLE brand (brand_id INT PRIMARY KEY, brand_details VARCHAR(250) NOT NULL);
CREATE TABLE `condition` (condition_id INT PRIMARY KEY, condition_details VARCHAR(250) NOT NULL);
CREATE TABLE estimateprice (estimateprice_id INT PRIMARY KEY, estimateprice_details VARCHAR(250) NOT NULL);
CREATE TABLE `fixed` (fixed_id INT PRIMARY KEY, fixed_details VARCHAR(250) NOT NULL);
CREATE TABLE request_order (
  request_id INT AUTO_INCREMENT PRIMARY KEY,
  requestDate DATETIME NOT NULL,
  trackID VARCHAR(100) NOT NULL UNIQUE,
  bookID VARCHAR(100), numberID VARCHAR(100), orderID VARCHAR(100), orderIDShow VARCHAR(100),
  customerFullname VARCHAR(250), customerTel VARCHAR(100), customerTel2 VARCHAR(100), customerEmail VARCHAR(100),
  detailTypeId INT, detailBrandId INT, detailAgent INT, detailSKUName VARCHAR(100),
  warantyType INT, detailNumberWaranty VARCHAR(100), detailDatePurchase DATETIME,
  detailCondition VARCHAR(250), detailConditionOther VARCHAR(250),
  detailEstimatePrice VARCHAR(250), detailEstimatePriceOther VARCHAR(250),
  detailFixed VARCHAR(250), detailFixedOther VARCHAR(250), detailEquipment TEXT,
  detailNote TEXT, detailImage VARCHAR(500),
  branchID INT, branch_type_id INT, UserID INT, action_status INT, RepairPrice DECIMAL(8,2),
  number_cmg VARCHAR(100), create_by_user VARCHAR(250)
);
CREATE TABLE status_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id VARCHAR(100) NOT NULL,
  action_id INT, update_id INT, cdate DATETIME NOT NULL
);
INSERT INTO book VALUES (1, 1, 'ABC', 1);
INSERT INTO branch VALUES (1, 1, 'WPA');
INSERT INTO `type` VALUES (1, 'TYPE A');
INSERT INTO brand VALUES (1, 'BRAND A');
INSERT INTO `condition` VALUES (1, 'CONDITION A');
INSERT INTO estimateprice VALUES (1, 'ESTIMATE A');
INSERT INTO `fixed` VALUES (1, 'FIXED A');
INSERT INTO request_order (
  requestDate, trackID, bookID, numberID, orderID, orderIDShow, customerFullname, customerTel,
  branchID, branch_type_id, action_status
) VALUES (
  UTC_TIMESTAMP(), CONCAT('WPA', DATE_FORMAT(UTC_DATE(), '%y%m'), '0042'), '1', 'LEGACY-42',
  'ABCLEGACY-42', 'ABC/LEGACY-42', 'LEGACY SEQUENCE', '0000000000', 1, 1, 1
);
SQL

docker run --rm "${runtime[@]}" "$CI4_IMAGE" php spark migrate --all >/dev/null
docker run --rm "${probe_runtime[@]}" "$CI4_IMAGE" php spark concurrency:probe order-migration-verify \
  | grep -Fq 'ORDER_MIGRATION_VERIFIED'
docker run --rm "${probe_runtime[@]}" "$CI4_IMAGE" php spark concurrency:probe seed >/dev/null

barrier=$(( $(date +%s) + 3 ))
docker create --name "$worker_one" "${probe_runtime[@]}" \
  --env PROBE_START_EPOCH="$barrier" "$CI4_IMAGE" php spark concurrency:probe consume >/dev/null
docker create --name "$worker_two" "${probe_runtime[@]}" \
  --env PROBE_START_EPOCH="$barrier" "$CI4_IMAGE" php spark concurrency:probe consume >/dev/null
docker start "$worker_one" "$worker_two" >/dev/null
status_one=$(docker wait "$worker_one")
status_two=$(docker wait "$worker_two")
output=$(docker logs "$worker_one" 2>&1; docker logs "$worker_two" 2>&1)

[ "$status_one" = 0 ] && [ "$status_two" = 0 ] \
  || { printf '%s\n' "$output" >&2; exit 1; }
[ "$(grep -c 'TOKEN_WIN' <<<"$output")" = 1 ]
[ "$(grep -c 'TOKEN_DENY' <<<"$output")" = 1 ]
[ "$(grep -c 'RATE_ALLOW' <<<"$output")" = 1 ]
[ "$(grep -c 'RATE_DENY' <<<"$output")" = 1 ]
docker run --rm "${probe_runtime[@]}" "$CI4_IMAGE" php spark concurrency:probe verify \
  | grep -Fq 'VERIFIED token_consumed=1 rate_count=2'
docker run --rm "${runtime[@]}" "$CI4_IMAGE" \
  php spark reset:delivery-work --transport loopback --limit 10 \
  | grep -Fq 'reset_delivery sent=1 retry=0 idle=1 released=0'
docker run --rm "${runtime[@]}" "$CI4_IMAGE" \
  php spark reset:delivery-work --transport loopback --limit 10 \
  | grep -Fq 'reset_delivery sent=0 retry=0 idle=1 released=0'

run_order_scenario() {
  local scenario=$1
  local expected_created=$2
  local expected_duplicate=$3
  local expected_orders=$4
  local label=${scenario^^}
  local barrier=$(( $(date +%s) + 3 ))
  docker create --name "$order_one" "${probe_runtime[@]}" \
    --env PROBE_START_EPOCH="$barrier" --env PROBE_SLOT=1 --env PROBE_SCENARIO="$scenario" \
    "$CI4_IMAGE" php spark concurrency:probe order-create >/dev/null
  docker create --name "$order_two" "${probe_runtime[@]}" \
    --env PROBE_START_EPOCH="$barrier" --env PROBE_SLOT=2 --env PROBE_SCENARIO="$scenario" \
    "$CI4_IMAGE" php spark concurrency:probe order-create >/dev/null
  docker start "$order_one" "$order_two" >/dev/null
  local status_one status_two output
  status_one=$(docker wait "$order_one")
  status_two=$(docker wait "$order_two")
  output=$(docker logs "$order_one" 2>&1; docker logs "$order_two" 2>&1)
  [ "$status_one" = 0 ] && [ "$status_two" = 0 ] \
    || { printf '%s\n' "$output" >&2; exit 1; }
  [ "$(grep -c "ORDER_${label}_CREATED" <<<"$output" || true)" = "$expected_created" ]
  [ "$(grep -c "ORDER_${label}_DUPLICATE" <<<"$output" || true)" = "$expected_duplicate" ]
  docker run --rm "${probe_runtime[@]}" --env PROBE_SCENARIO="$scenario" \
    "$CI4_IMAGE" php spark concurrency:probe order-verify \
    | grep -Fq "ORDER_${label}_VERIFIED orders=${expected_orders} logs=${expected_orders} sms=${expected_orders}"
  docker rm -f "$order_one" "$order_two" >/dev/null
}

run_order_scenario business 1 1 1
run_order_scenario submission 1 1 1
run_order_scenario distinct 2 0 2
migration_cycle_output=$(docker run --rm "${probe_runtime[@]}" "$CI4_IMAGE" php spark concurrency:probe order-migration-cycle 2>&1) \
  || { printf '%s\n' "$migration_cycle_output" >&2; exit 1; }
grep -Eq 'ORDER_MIGRATION_CYCLED rows=[0-9]+' <<<"$migration_cycle_output" \
  || { printf '%s\n' "$migration_cycle_output" >&2; exit 1; }
docker run --rm "${runtime[@]}" "$CI4_IMAGE" php spark sms:delivery-work --transport loopback --limit 10 \
  | grep -Fq 'sms_delivery sent=4 retry=0 idle=1 released=0'
docker run --rm "${runtime[@]}" "$CI4_IMAGE" php spark sms:delivery-work --transport loopback --limit 10 \
  | grep -Fq 'sms_delivery sent=0 retry=0 idle=1 released=0'

docker run --rm "${probe_runtime[@]}" "$CI4_IMAGE" php spark concurrency:probe import-seed \
  | grep -Fq 'IMPORT_SEEDED'
barrier=$(( $(date +%s) + 3 ))
docker create --name "$import_one" "${probe_runtime[@]}" \
  --env PROBE_START_EPOCH="$barrier" "$CI4_IMAGE" php spark concurrency:probe import-confirm >/dev/null
docker create --name "$import_two" "${probe_runtime[@]}" \
  --env PROBE_START_EPOCH="$barrier" "$CI4_IMAGE" php spark concurrency:probe import-confirm >/dev/null
docker start "$import_one" "$import_two" >/dev/null
import_status_one=$(docker wait "$import_one")
import_status_two=$(docker wait "$import_two")
import_output=$(docker logs "$import_one" 2>&1; docker logs "$import_two" 2>&1)
[ "$import_status_one" = 0 ] && [ "$import_status_two" = 0 ] \
  || { printf '%s\n' "$import_output" >&2; exit 1; }
[ "$(grep -c 'IMPORT_CONFIRMED' <<<"$import_output")" = 1 ]
[ "$(grep -c 'IMPORT_REPLAYED' <<<"$import_output")" = 1 ]
docker run --rm "${probe_runtime[@]}" "$CI4_IMAGE" php spark concurrency:probe import-verify \
  | grep -Fq 'IMPORT_VERIFIED confirmed=1 rows=1 price=222.00'

docker run --rm "${probe_runtime[@]}" "$CI4_IMAGE" php spark concurrency:probe import-isolation-seed \
  | grep -Fq 'IMPORT_ISOLATION_SEEDED owners=2 branches=2 batches=2'
barrier=$(( $(date +%s) + 3 ))
docker create --name "$isolation_one" "${probe_runtime[@]}" \
  --env PROBE_START_EPOCH="$barrier" --env PROBE_SLOT=1 \
  "$CI4_IMAGE" php spark concurrency:probe import-isolation-confirm >/dev/null
docker create --name "$isolation_two" "${probe_runtime[@]}" \
  --env PROBE_START_EPOCH="$barrier" --env PROBE_SLOT=2 \
  "$CI4_IMAGE" php spark concurrency:probe import-isolation-confirm >/dev/null
docker start "$isolation_one" "$isolation_two" >/dev/null
isolation_status_one=$(docker wait "$isolation_one")
isolation_status_two=$(docker wait "$isolation_two")
isolation_output=$(docker logs "$isolation_one" 2>&1; docker logs "$isolation_two" 2>&1)
[ "$isolation_status_one" = 0 ] && [ "$isolation_status_two" = 0 ] \
  || { printf '%s\n' "$isolation_output" >&2; exit 1; }
[ "$(grep -c 'IMPORT_ISOLATION_[AB]_CONFIRMED' <<<"$isolation_output")" = 2 ]
docker run --rm "${probe_runtime[@]}" "$CI4_IMAGE" php spark concurrency:probe import-isolation-verify \
  | grep -Fq 'IMPORT_ISOLATION_VERIFIED owners=2 branches=2 batches=2 prices=111.00,222.00'
docker run --rm "${probe_runtime[@]}" "$CI4_IMAGE" php spark concurrency:probe order-migration-duplicate \
  | grep -Eq 'ORDER_MIGRATION_DUPLICATE_ABORTED rows=[0-9]+'

docker exec -i "$database" mariadb --batch -uci4 -p"$password" ci4_concurrency <<'SQL'
DROP TABLE request_order;
DROP TABLE status_log;
DROP TABLE branch;
DROP TABLE `type`;
DROP TABLE brand;
CREATE TABLE request_order (
  request_id INT PRIMARY KEY,
  trackID VARCHAR(100) NOT NULL UNIQUE,
  customerFullname VARCHAR(250),
  customerEmail VARCHAR(100)
);
CREATE TABLE statusaction (
  status_id INT PRIMARY KEY,
  status_name VARCHAR(128) NOT NULL,
  status_name_th VARCHAR(128) NOT NULL
);
CREATE TABLE status_log (
  id INT PRIMARY KEY,
  order_id VARCHAR(100) NOT NULL,
  action_id INT,
  update_id INT,
  cdate DATETIME NOT NULL
);
INSERT INTO request_order VALUES
  (1, 'WP00C-CONCURRENT-A', 'SYNTHETIC A', 'a@example.invalid'),
  (2, 'WP00C-CONCURRENT-B', 'SYNTHETIC B', 'b@example.invalid');
-- Pinned CI3 en/trackstatus.php renders status_name_th, so keep the isolation
-- discriminator in both language columns.
INSERT INTO statusaction VALUES
  (1, 'TRACK-A-ONLY', 'TRACK-A-ONLY'),
  (2, 'TRACK-B-ONLY', 'TRACK-B-ONLY');
INSERT INTO status_log VALUES
  (1, 'WP00C-CONCURRENT-A', 1, NULL, '2026-08-01 00:00:00'),
  (2, 'WP00C-CONCURRENT-B', 2, NULL, '2026-08-02 00:00:00');
SQL

docker run --detach --rm --name "$tracking_server" "${runtime[@]}" \
  --env app_baseURL="http://${tracking_server}:8080/" \
  "$CI4_IMAGE" php spark serve --host 0.0.0.0 --port 8080 >/dev/null
tracking_ready=0
for _ in $(seq 1 30); do
  if docker run --rm --network "$network" "$CI4_IMAGE" php -r \
    "exit(@file_get_contents('http://${tracking_server}:8080/health') === false ? 1 : 0);" \
    >/dev/null 2>&1; then
    tracking_ready=1
    break
  fi
done
[ "$tracking_ready" = 1 ] || { echo 'FAIL: public tracking server did not become ready' >&2; exit 1; }

tracking_probe='$start=(int)getenv("PROBE_START_EPOCH"); while(time()<$start){usleep(10000);} '
tracking_probe+='for($i=0;$i<20;$i++){ $body=file_get_contents($argv[1]); '
tracking_probe+='if(!str_contains($body,$argv[2])||str_contains($body,$argv[3])){exit(1);} } echo $argv[2],"_OK\n";'
barrier=$(( $(date +%s) + 2 ))
docker create --name "$tracking_one" --network "$network" --env PROBE_START_EPOCH="$barrier" \
  "$CI4_IMAGE" php -r "$tracking_probe" \
  "http://${tracking_server}:8080/tracking/WP00C-CONCURRENT-A" TRACK-A-ONLY TRACK-B-ONLY >/dev/null
docker create --name "$tracking_two" --network "$network" --env PROBE_START_EPOCH="$barrier" \
  "$CI4_IMAGE" php -r "$tracking_probe" \
  "http://${tracking_server}:8080/tracking/WP00C-CONCURRENT-B" TRACK-B-ONLY TRACK-A-ONLY >/dev/null
docker start "$tracking_one" "$tracking_two" >/dev/null
tracking_status_one=$(docker wait "$tracking_one")
tracking_status_two=$(docker wait "$tracking_two")
tracking_output=$(docker logs "$tracking_one" 2>&1; docker logs "$tracking_two" 2>&1)
[ "$tracking_status_one" = 0 ] && [ "$tracking_status_two" = 0 ] \
  || { printf '%s\n' "$tracking_output" >&2; exit 1; }
[ "$(grep -c '_OK' <<<"$tracking_output")" = 2 ]

if [ -n "$JUNIT_OUTPUT" ]; then
  python3 - "$JUNIT_OUTPUT" <<'PY'
import pathlib
import sys
import xml.etree.ElementTree as ET

target = pathlib.Path(sys.argv[1])
target.parent.mkdir(parents=True, exist_ok=True)
suite = ET.Element("testsuite", name="WP-00C operational concurrency", tests="11", failures="0")
cases = {
    "testPasswordResetAtomicity": 6,
    "testOrderBusinessKeyRace": 8,
    "testOrderSubmissionReplayRace": 8,
    "testOrderDistinctBusinessKeys": 8,
    "testOrderBusinessKeyMigrationCycle": 6,
    "testOrderBusinessKeyMigrationDuplicatePreflight": 4,
    "testImportConfirmIdempotency": 4,
    "testImportBatchOwnershipIsolation": 8,
    "testPublicTrackingIsolation": 40,
    "testEmailDeliveryWorkerIdempotency": 4,
    "testSmsDeliveryWorkerIdempotency": 4,
}
for name, assertions in cases.items():
    ET.SubElement(
        suite,
        "testcase",
        {"class": "Tests\\Operational\\ConcurrencyCheck", "name": name, "assertions": str(assertions)},
    )
root = ET.Element("testsuites")
root.append(suite)
ET.indent(root)
ET.ElementTree(root).write(target, encoding="utf-8", xml_declaration=True)
PY
fi

echo 'PASS MariaDB token/rate/order-business-key/order-submission/order-distinct/migration/import-confirm/import-isolation/public-tracking concurrency + loopback email/SMS delivery workers'
