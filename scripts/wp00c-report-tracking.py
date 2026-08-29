#!/usr/bin/env python3

from __future__ import annotations

import argparse
import hashlib
import http.cookiejar
import json
import fcntl
import os
import pathlib
import re
import secrets
import subprocess
import sys
import urllib.parse
import urllib.error
import urllib.request
from html.parser import HTMLParser


CI3_ROOT = pathlib.Path("/Users/king_developer/Desktop/Project/samsoniteci3")
LOCK_PATH = pathlib.Path("/private/tmp/wp00c-report-tracking.lock")
BASE_URL = "http://127.0.0.1:18404/"
DATABASE_NAME = ""
EXPECTED_TABLE_COUNT = 31
TARGET = "ci3"
DB_CONTAINER = "samsonitetracking-ci4-migration-db-1"
WEB_CONTAINER = "samsonitetracking-ci4-migration-web-1"
EXPECTED_PIN = "ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6"
USER_AGENT = "WP00C-REPORT-TRACKING-RUNNER/1"
ERROR_MARKERS = (
    "A Database Error Occurred",
    "Error Number:",
    "Fatal error",
    "Uncaught Exception",
)


def run(command: list[str], *, input_text: str | None = None) -> str:
    result = subprocess.run(
        command,
        input=input_text,
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        check=False,
    )
    if result.returncode != 0:
        raise RuntimeError(f"command failed ({result.returncode}): {result.stderr.strip()}")
    return result.stdout.strip()


def db(sql: str) -> str:
    return run(
        [
            "docker",
            "exec",
            "-e",
            f"WP00C_DATABASE={DATABASE_NAME}",
            "-i",
            DB_CONTAINER,
            "sh",
            "-lc",
            'database_name="${WP00C_DATABASE:-$MARIADB_DATABASE}"; '
            'exec mariadb --batch --raw --skip-column-names -u"$MARIADB_USER" '
            '-p"$MARIADB_PASSWORD" "$database_name"',
        ],
        input_text=sql,
    )


def db_quote(value: str) -> str:
    return "0x" + value.encode().hex()


def table_checksums() -> dict[str, str]:
    tables = db("SHOW TABLES;").splitlines()
    if len(tables) != EXPECTED_TABLE_COUNT or any(
        re.fullmatch(r"[A-Za-z0-9_]+", table) is None for table in tables
    ):
        raise AssertionError(f"unexpected table inventory: {len(tables)}")
    checksums: dict[str, str] = {}
    for table in tables:
        fields = db(f"CHECKSUM TABLE `{table}`;").split("\t")
        checksums[table] = fields[-1]
    return checksums


class ReportTableParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.in_table = False
        self.in_body = False
        self.in_row = False
        self.in_cell = False
        self.cell_parts: list[str] = []
        self.row: list[str] = []
        self.rows: list[list[str]] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attributes = dict(attrs)
        if tag == "table" and attributes.get("id") == "examples":
            self.in_table = True
        elif self.in_table and tag == "tbody":
            self.in_body = True
        elif self.in_body and tag == "tr":
            self.in_row = True
            self.row = []
        elif self.in_row and tag == "td":
            self.in_cell = True
            self.cell_parts = []

    def handle_endtag(self, tag: str) -> None:
        if self.in_cell and tag == "td":
            self.row.append(" ".join("".join(self.cell_parts).split()))
            self.in_cell = False
        elif self.in_row and tag == "tr":
            self.rows.append(self.row)
            self.in_row = False
        elif self.in_body and tag == "tbody":
            self.in_body = False
        elif self.in_table and tag == "table":
            self.in_table = False

    def handle_data(self, data: str) -> None:
        if self.in_cell:
            self.cell_parts.append(data)


class CsrfParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.fields: dict[str, str] = {}

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if tag != "input":
            return

        attributes = dict(attrs)
        name = attributes.get("name")
        value = attributes.get("value")
        if isinstance(name, str) and name.startswith("csrf_") and isinstance(value, str):
            self.fields[name] = value


def parse_rows(html: str) -> list[list[str]]:
    parser = ReportTableParser()
    parser.feed(html)
    return [row for row in parser.rows if len(row) >= 22]


def opener() -> urllib.request.OpenerDirector:
    jar = http.cookiejar.CookieJar()
    result = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    result.addheaders = [("User-Agent", USER_AGENT)]
    return result


def get(client: urllib.request.OpenerDirector, path: str) -> tuple[int, str, str]:
    request = urllib.request.Request(urllib.parse.urljoin(BASE_URL, path), method="GET")
    try:
        with client.open(request, timeout=20) as response:
            return response.status, response.geturl(), response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as exc:
        return exc.code, exc.geturl(), exc.read().decode("utf-8", "replace")


def post(client: urllib.request.OpenerDirector, path: str, data: dict[str, str]) -> tuple[int, str, str]:
    request = urllib.request.Request(
        urllib.parse.urljoin(BASE_URL, path),
        data=urllib.parse.urlencode(data).encode(),
        method="POST",
    )
    try:
        with client.open(request, timeout=20) as response:
            return response.status, response.geturl(), response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as exc:
        return exc.code, exc.geturl(), exc.read().decode("utf-8", "replace")


def csrf_fields(html: str) -> dict[str, str]:
    parser = CsrfParser()
    parser.feed(html)
    return parser.fields


def login(username: str, password: str) -> urllib.request.OpenerDirector:
    client = opener()
    login_status, _, login_html = get(client, "login")
    if login_status != 200:
        raise AssertionError(f"login page failed for {username}: status={login_status}")
    payload = {"username": username, "password": password}
    payload.update(csrf_fields(login_html))
    status, url, body = post(client, "loginMe", payload)
    if status != 200 or not url.rstrip("/").endswith("/dashboard") or "SYNTHETIC" not in body:
        raise AssertionError(f"login failed for {username}: status={status} url={url}")
    return client


def report_case(
    client: urllib.request.OpenerDirector,
    case: str,
    path: str,
    expected_tracks: list[str],
    *,
    status_id: str = "",
    search: str = "",
    sdate: str = "01/08/2026",
    edate: str = "31/08/2026",
) -> dict[str, object]:
    preflight_status, _, preflight_html = get(client, path)
    if preflight_status != 200:
        raise AssertionError(f"{case} preflight failed: status={preflight_status} path={path}")
    payload = {"status_id": status_id, "searchText": search, "sdate": sdate, "edate": edate}
    payload.update(csrf_fields(preflight_html))
    status, url, html = post(
        client,
        path,
        payload,
    )
    errors = [marker for marker in ERROR_MARKERS if marker.lower() in html.lower()]
    if TARGET == "ci4" and "ReportTrackingListingTest" in html:
        errors.append("legacy Test page/menu reference")
    rows = parse_rows(html)
    tracks = [row[7] if len(row) >= 23 else row[6] for row in rows]
    if status != 200 or "/login" in url or errors or tracks != expected_tracks:
        raise AssertionError(
            f"{case} failed: status={status} url={url} errors={errors} tracks={tracks} expected={expected_tracks}"
        )
    return {
        "case": case,
        "path": path,
        "status": status,
        "tracks": tracks,
        "row_count": len(rows),
        "first_number": rows[0][0] if rows else None,
        "row_by_track": {(row[7] if len(row) >= 23 else row[6]): row for row in rows},
        "body_sha256": hashlib.sha256(html.encode()).hexdigest(),
    }


def main() -> int:
    global BASE_URL, DATABASE_NAME, EXPECTED_TABLE_COUNT, TARGET

    parser = argparse.ArgumentParser()
    parser.add_argument("--target", choices=("ci3", "ci4"), default="ci3")
    args = parser.parse_args()
    TARGET = args.target
    if TARGET == "ci4":
        BASE_URL = "http://127.0.0.1:18405/"
        DATABASE_NAME = "samsonite_ci4"
        EXPECTED_TABLE_COUNT = 36

    # Both targets mutate and restore the same synthetic tbl_users rows while logging in,
    # even though their report tables and ports differ. Serialize them on one local lock.
    lock_path = LOCK_PATH
    lock_fd = os.open(lock_path, os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
    lock_file = os.fdopen(lock_fd, "r+")
    try:
        fcntl.flock(lock_file, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError as exc:
        holder = lock_file.read().strip() or "unknown pid"
        raise RuntimeError(f"another Report Tracking runner ({TARGET}, pid {holder}) holds {lock_path}") from exc
    lock_file.seek(0)
    lock_file.truncate()
    lock_file.write(str(os.getpid()))
    lock_file.flush()

    pin = run(["git", "-C", str(CI3_ROOT), "rev-parse", "HEAD"])
    dirty = run(["git", "-C", str(CI3_ROOT), "status", "--porcelain"])
    if pin != EXPECTED_PIN or dirty:
        raise AssertionError(f"CI3 source identity mismatch: pin={pin} dirty={bool(dirty)}")

    initial_checksums = table_checksums()
    ci4_hashes: dict[str, str] = {}
    initial_rate_rows: dict[str, tuple[str, str, str, str]] = {}
    if TARGET == "ci4":
        target_mutation_rows = db(
            "SELECT "
            "(SELECT COUNT(*) FROM ci4_password_reset_tokens) + "
            "(SELECT COUNT(*) FROM ci4_delivery_intents);"
        )
        if target_mutation_rows != "0":
            raise AssertionError("CI4 reset-token and delivery-intent tables must be empty before comparator")
        initial_rate_rows = {
            fields[0]: (fields[1], fields[2], fields[3], fields[4])
            for line in db(
                "SELECT bucket_key,request_count,window_id,"
                "DATE_FORMAT(expires_at,'%Y-%m-%d %H:%i:%s'),"
                "DATE_FORMAT(created_at,'%Y-%m-%d %H:%i:%s') "
                "FROM ci4_rate_limit_buckets ORDER BY bucket_key;"
            ).splitlines()
            if len(fields := line.split("\t")) == 5
        }
        ci4_hashes = dict(
            line.split("\t", 1)
            for line in db(
                "SELECT id,password_hash FROM ci4_users "
                "WHERE id IN (9001,9002,9003) AND is_active=1 ORDER BY id;"
            ).splitlines()
        )
        if len(ci4_hashes) != 3:
            raise AssertionError("CI4 imported synthetic users missing")
    original_hashes = dict(
        line.split("\t", 1)
        for line in db("SELECT userId,password FROM tbl_users WHERE userId IN (9001,9002,9003) ORDER BY userId;").splitlines()
    )
    if len(original_hashes) != 3:
        raise AssertionError("synthetic users missing")
    last_login_max = int(db("SELECT COALESCE(MAX(id),0) FROM tbl_last_login;") or "0")
    password = secrets.token_urlsafe(18)[:24]
    password_hash = run(
        ["docker", "exec", "-i", WEB_CONTAINER, "php", "-r", "echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT);"],
        input_text=password,
    )
    results: list[dict[str, object]] = []
    output: dict[str, object] | None = None

    try:
        db(f"UPDATE tbl_users SET password={db_quote(password_hash)} WHERE userId IN (9001,9002,9003);")
        if TARGET == "ci4":
            db(
                f"UPDATE ci4_users SET password_hash={db_quote(password_hash)} "
                "WHERE id IN (9001,9002,9003) AND is_active=1;"
            )
        admin = login("wp00c-admin", password)
        branch_a = login("wp00c-a", password)
        branch_b = login("wp00c-b", password)
        before_reports = table_checksums()

        all_tracks = [f"WP00C-TRACK-{number:03d}" for number in range(9, 0, -1)]
        status_two = ["WP00C-TRACK-009", "WP00C-TRACK-002"]
        status_two_three = ["WP00C-TRACK-009", "WP00C-TRACK-003", "WP00C-TRACK-002"]
        branch_one = [f"WP00C-TRACK-{number:03d}" for number in range(6, 0, -1)]
        branch_two = [f"WP00C-TRACK-{number:03d}" for number in range(9, 6, -1)]

        test_route_absent = False
        if TARGET == "ci3":
            routes = (("ReportTrackingListingTest", "test"), ("ReportTrackingListing", "main"))
        else:
            for test_route in ("ReportTrackingListingTest", "Order/ReportTrackingListingTest"):
                status, _, _ = get(admin, test_route)
                if status != 404:
                    raise AssertionError(f"CI4 legacy Test route is exposed: {test_route} status={status}")
            test_route_absent = True
            routes = (("Order/ReportTrackingListing", "main"),)

        for route, prefix in routes:
            results.extend(
                [
                    report_case(admin, f"{prefix}-empty", route, all_tracks),
                    report_case(admin, f"{prefix}-single", route, status_two, status_id="2"),
                    report_case(admin, f"{prefix}-multiple", route, status_two_three, status_id="2,3"),
                    report_case(admin, f"{prefix}-malformed", route, all_tracks, status_id="2) OR 1=1 --"),
                ]
            )

        main_route = "ReportTrackingListing" if TARGET == "ci3" else "Order/ReportTrackingListing"
        results.extend(
            [
                report_case(admin, "main-search", main_route, ["WP00C-TRACK-004"], search="WP00C-TRACK-004"),
                report_case(admin, "main-date", main_route, ["WP00C-TRACK-005", "WP00C-TRACK-004", "WP00C-TRACK-003"], sdate="03/08/2026", edate="05/08/2026"),
                report_case(admin, "main-route-branch-1", f"{main_route}/0/1", branch_one),
                report_case(admin, "main-route-branch-2", f"{main_route}/0/2", branch_two),
                report_case(admin, "main-no-real-pagination", f"{main_route}/25/1", branch_one),
                report_case(branch_a, "main-session-branch-1", main_route, branch_one),
                report_case(branch_b, "main-session-branch-2", main_route, branch_two),
            ]
        )

        page_case = next(result for result in results if result["case"] == "main-no-real-pagination")
        if page_case["first_number"] != "26":
            raise AssertionError(f"route page parameter mismatch: {page_case['first_number']}")
        main_all = next(result for result in results if result["case"] == "main-empty")
        order_seven = main_all["row_by_track"]["WP00C-TRACK-007"]
        if order_seven[3] != "2" or order_seven[4] != "0":
            raise AssertionError(f"day-value mismatch for WP00C-TRACK-007: {order_seven[3:5]}")

        after_reports = table_checksums()
        if before_reports != after_reports:
            changed = sorted(table for table in before_reports if before_reports[table] != after_reports[table])
            raise AssertionError(f"report requests changed database tables: {changed}")

        output = {
            "verdict": "PASS",
            "target": TARGET,
            "ci3_pin": pin,
            "image": "samsonitetracking-ci3:ee1c95e"
            if TARGET == "ci3"
            else "samsonitetracking-ci4:4.7.4-php8.5.7",
            "cases": ["RPT-TRACKING-TEST-001", "RPT-TRACKING-001"],
            "test_route_absent": test_route_absent,
            "requests": len(results),
            "results": [{key: value for key, value in result.items() if key != "row_by_track"} for result in results],
            "database_tables": len(before_reports),
            "database_changed_tables": 0,
            "outbound_calls": 0,
        }
    finally:
        restore = ";".join(
            f"UPDATE tbl_users SET password={db_quote(password_hash)} WHERE userId={int(user_id)}"
            for user_id, password_hash in original_hashes.items()
        )
        if TARGET == "ci4":
            restore += ";" + ";".join(
                f"UPDATE ci4_users SET password_hash={db_quote(password_hash)} WHERE id={int(user_id)}"
                for user_id, password_hash in ci4_hashes.items()
            )
        restore += (
            f";DELETE FROM tbl_last_login WHERE id>{last_login_max} AND userId IN (9001,9002,9003) "
            f"AND agentString={db_quote(USER_AGENT)};"
        )
        if TARGET == "ci4":
            current_rate_keys = set(db("SELECT bucket_key FROM ci4_rate_limit_buckets;").splitlines())
            new_rate_keys = sorted(current_rate_keys - set(initial_rate_rows))
            if new_rate_keys:
                restore += ";DELETE FROM ci4_rate_limit_buckets WHERE bucket_key IN (" + ",".join(
                    db_quote(key) for key in new_rate_keys
                ) + ")"
            for key, (count, window, expires_at, created_at) in initial_rate_rows.items():
                restore += (
                    ";REPLACE INTO ci4_rate_limit_buckets "
                    "(bucket_key,request_count,window_id,expires_at,created_at) VALUES ("
                    f"{db_quote(key)},{int(count)},{int(window)},{db_quote(expires_at)},{db_quote(created_at)})"
                )
        db(restore + ";")
        final_checksums = table_checksums()
        if initial_checksums != final_checksums:
            changed = sorted(table for table in initial_checksums if initial_checksums[table] != final_checksums[table])
            raise AssertionError(f"cleanup failed; changed tables: {changed}")

    if output is None:
        raise AssertionError("runner produced no result")
    print(json.dumps(output, ensure_ascii=False, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(f"FAIL {exc}", file=sys.stderr)
        raise SystemExit(1)
