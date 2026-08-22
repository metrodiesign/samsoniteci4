#!/usr/bin/env python3

from __future__ import annotations

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
import urllib.request
from html.parser import HTMLParser


CI3_ROOT = pathlib.Path("/Users/king_developer/Desktop/Project/samsoniteci3")
LOCK_PATH = pathlib.Path("/private/tmp/wp00c-report-tracking.lock")
BASE_URL = "http://127.0.0.1:18404/"
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
            "-i",
            DB_CONTAINER,
            "sh",
            "-lc",
            'exec mariadb --batch --raw --skip-column-names -u"$MARIADB_USER" '
            '-p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"',
        ],
        input_text=sql,
    )


def db_quote(value: str) -> str:
    return "0x" + value.encode().hex()


def table_checksums() -> dict[str, str]:
    tables = db("SHOW TABLES;").splitlines()
    if len(tables) != 31 or any(re.fullmatch(r"[A-Za-z0-9_]+", table) is None for table in tables):
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


def parse_rows(html: str) -> list[list[str]]:
    parser = ReportTableParser()
    parser.feed(html)
    return [row for row in parser.rows if len(row) >= 22]


def opener() -> urllib.request.OpenerDirector:
    jar = http.cookiejar.CookieJar()
    result = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    result.addheaders = [("User-Agent", USER_AGENT)]
    return result


def post(client: urllib.request.OpenerDirector, path: str, data: dict[str, str]) -> tuple[int, str, str]:
    request = urllib.request.Request(
        urllib.parse.urljoin(BASE_URL, path),
        data=urllib.parse.urlencode(data).encode(),
        method="POST",
    )
    with client.open(request, timeout=20) as response:
        return response.status, response.geturl(), response.read().decode("utf-8", "replace")


def login(username: str, password: str) -> urllib.request.OpenerDirector:
    client = opener()
    status, url, body = post(client, "loginMe", {"username": username, "password": password})
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
    status, url, html = post(
        client,
        path,
        {"status_id": status_id, "searchText": search, "sdate": sdate, "edate": edate},
    )
    errors = [marker for marker in ERROR_MARKERS if marker.lower() in html.lower()]
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
    # ponytail: one local runtime exists; split lock per runtime only when parallel runtimes exist.
    lock_fd = os.open(LOCK_PATH, os.O_CREAT | os.O_RDWR | os.O_NOFOLLOW, 0o600)
    lock_file = os.fdopen(lock_fd, "r+")
    try:
        fcntl.flock(lock_file, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError as exc:
        raise RuntimeError("another Report Tracking runner holds the local lock") from exc

    pin = run(["git", "-C", str(CI3_ROOT), "rev-parse", "HEAD"])
    dirty = run(["git", "-C", str(CI3_ROOT), "status", "--porcelain"])
    if pin != EXPECTED_PIN or dirty:
        raise AssertionError(f"CI3 source identity mismatch: pin={pin} dirty={bool(dirty)}")

    initial_checksums = table_checksums()
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
        admin = login("wp00c-admin", password)
        branch_a = login("wp00c-a", password)
        branch_b = login("wp00c-b", password)
        before_reports = table_checksums()

        all_tracks = [f"WP00C-TRACK-{number:03d}" for number in range(9, 0, -1)]
        status_two = ["WP00C-TRACK-009", "WP00C-TRACK-002"]
        status_two_three = ["WP00C-TRACK-009", "WP00C-TRACK-003", "WP00C-TRACK-002"]
        branch_one = [f"WP00C-TRACK-{number:03d}" for number in range(6, 0, -1)]
        branch_two = [f"WP00C-TRACK-{number:03d}" for number in range(9, 6, -1)]

        for route, prefix in (("ReportTrackingListingTest", "test"), ("ReportTrackingListing", "main")):
            results.extend(
                [
                    report_case(admin, f"{prefix}-empty", route, all_tracks),
                    report_case(admin, f"{prefix}-single", route, status_two, status_id="2"),
                    report_case(admin, f"{prefix}-multiple", route, status_two_three, status_id="2,3"),
                    report_case(admin, f"{prefix}-malformed", route, all_tracks, status_id="2) OR 1=1 --"),
                ]
            )

        results.extend(
            [
                report_case(admin, "main-search", "ReportTrackingListing", ["WP00C-TRACK-004"], search="WP00C-TRACK-004"),
                report_case(admin, "main-date", "ReportTrackingListing", ["WP00C-TRACK-005", "WP00C-TRACK-004", "WP00C-TRACK-003"], sdate="03/08/2026", edate="05/08/2026"),
                report_case(admin, "main-route-branch-1", "ReportTrackingListing/0/1", branch_one),
                report_case(admin, "main-route-branch-2", "ReportTrackingListing/0/2", branch_two),
                report_case(admin, "main-no-real-pagination", "ReportTrackingListing/25/1", branch_one),
                report_case(branch_a, "main-session-branch-1", "ReportTrackingListing", branch_one),
                report_case(branch_b, "main-session-branch-2", "ReportTrackingListing", branch_two),
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
            "ci3_pin": pin,
            "image": "samsonitetracking-ci3:ee1c95e",
            "cases": ["RPT-TRACKING-TEST-001", "RPT-TRACKING-001"],
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
        db(
            restore
            + f";DELETE FROM tbl_last_login WHERE id>{last_login_max} AND userId IN (9001,9002,9003) "
            + f"AND agentString={db_quote(USER_AGENT)};"
        )
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
