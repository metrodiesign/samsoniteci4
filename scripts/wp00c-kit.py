#!/usr/bin/env python3
"""Validate and render the synthetic WP-00C behavior-baseline kit."""

from __future__ import annotations

import argparse
import fnmatch
import hashlib
import json
import pathlib
import re
import subprocess
import sys
from typing import Any


ROOT = pathlib.Path(__file__).resolve().parents[1]
CATALOG_PATH = ROOT / "tests/wp00c/catalog.json"
FIXTURES_PATH = ROOT / "tests/wp00c/fixtures.json"
PIN = "ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6"
ROUTES_RELATIVE_PATH = pathlib.Path("application/config/routes.php")

PRIMARY_KEYS = {
    "branch_type": "branch_type_id",
    "branch": "branch_id",
    "book": "book_id",
    "brand": "brand_id",
    "type": "type_id",
    "condition": "condition_id",
    "estimateprice": "estimateprice_id",
    "fixed": "fixed_id",
    "provider": "provider_id",
    "statusaction": "status_id",
    "tracking_status": "status_id",
    "group_type": "group_type_id",
    "tbl_menu": "id",
    "group_menu": "id",
    "tbl_roles": "roleId",
    "tbl_users": "userId",
    "request_order": "request_id",
    "status_log": "id",
    "contact": "id",
    "rating": "rating_id",
    "rating_comment": "id",
    "tbl_reset_password": "id",
    "tbl_background_web": "id",
}

REQUIRED_DOMAINS = {
    "routing",
    "auth",
    "user",
    "authorization",
    "order",
    "tracking",
    "contact",
    "rating",
    "import",
    "master",
    "report",
    "integration",
    "performance",
    "recovery",
}
REQUIRED_PATHS = {
    "happy",
    "negative",
    "security",
    "replay",
    "concurrency",
    "performance",
    "recovery",
}
REQUIRED_CASE_FIELDS = {
    "id",
    "domain",
    "title",
    "seam",
    "path",
    "fixture_sets",
    "source_trace",
    "given",
    "when",
    "expected_ci3",
    "ci4_disposition",
    "comparator",
    "approval_roles",
    "state",
}


def load_json(path: pathlib.Path) -> dict[str, Any]:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise SystemExit(f"FAIL {path}: {exc}") from exc


def run(*args: str, cwd: pathlib.Path) -> str:
    try:
        return subprocess.check_output(args, cwd=cwd, text=True).strip()
    except subprocess.CalledProcessError as exc:
        raise SystemExit(f"FAIL command exited {exc.returncode}: {' '.join(args)}") from exc


def extract_routes(source_root: pathlib.Path) -> list[dict[str, Any]]:
    route_file = source_root / ROUTES_RELATIVE_PATH
    pattern = re.compile(
        r"^\s*\$route\[['\"]([^'\"]+)['\"]\]\s*=\s*(['\"])(.*?)\2\s*;"
    )
    routes = []
    for number, line in enumerate(route_file.read_text(encoding="utf-8").splitlines(), 1):
        match = pattern.match(line)
        if match:
            routes.append(
                {"route": match.group(1), "target": match.group(3), "line": number}
            )
    return routes


def validate_source(source_root: pathlib.Path) -> list[dict[str, Any]]:
    if not source_root.is_absolute() or not source_root.is_dir():
        raise SystemExit("FAIL --source-root must be an existing absolute directory")
    head = run("git", "rev-parse", "HEAD", cwd=source_root)
    if head != PIN:
        raise SystemExit(f"FAIL CI3 pin is {head}, expected {PIN}")
    if run("git", "status", "--porcelain", cwd=source_root):
        raise SystemExit("FAIL CI3 source worktree is dirty")
    routes = extract_routes(source_root)
    if len(routes) != 178:
        raise SystemExit(f"FAIL explicit CI3 route count is {len(routes)}, expected 178")
    return routes


def validate_catalog(catalog: dict[str, Any], fixtures: dict[str, Any], routes: list[dict[str, Any]]) -> None:
    if catalog.get("schema_version") != 1 or fixtures.get("schema_version") != 1:
        raise SystemExit("FAIL unsupported WP-00C kit schema version")
    if catalog.get("ci3_source", {}).get("commit") != PIN:
        raise SystemExit("FAIL catalog CI3 pin differs from reference pin")

    seams = {item["id"] for item in catalog.get("seams", [])}
    cases = catalog.get("cases", [])
    case_ids = [case.get("id") for case in cases]
    if len(case_ids) != len(set(case_ids)):
        raise SystemExit("FAIL duplicate WP-00C case ID")

    fixture_sets = set(fixtures.get("fixture_sets", {}))
    domains = set()
    paths = set()
    for case in cases:
        missing = REQUIRED_CASE_FIELDS - set(case)
        if missing:
            raise SystemExit(f"FAIL {case.get('id', '<unknown>')} missing fields: {sorted(missing)}")
        if case["state"] != "PREPARED_NOT_RUN":
            raise SystemExit(f"FAIL {case['id']} must start PREPARED_NOT_RUN")
        if case["seam"] not in seams:
            raise SystemExit(f"FAIL {case['id']} references unknown seam {case['seam']}")
        unknown_fixtures = set(case["fixture_sets"]) - fixture_sets
        if unknown_fixtures:
            raise SystemExit(f"FAIL {case['id']} references unknown fixture sets {sorted(unknown_fixtures)}")
        if not case["source_trace"] or not case["approval_roles"]:
            raise SystemExit(f"FAIL {case['id']} lacks source trace or approval role")
        domains.add(case["domain"])
        paths.add(case["path"])

    missing_domains = REQUIRED_DOMAINS - domains
    missing_paths = REQUIRED_PATHS - paths
    if missing_domains or missing_paths:
        raise SystemExit(
            f"FAIL catalog coverage missing domains={sorted(missing_domains)} paths={sorted(missing_paths)}"
        )

    known_cases = set(case_ids)
    rules = catalog.get("explicit_route_rules", [])
    for rule in rules:
        unknown_cases = set(rule.get("case_ids", [])) - known_cases
        if unknown_cases:
            raise SystemExit(f"FAIL route rule references unknown cases {sorted(unknown_cases)}")

    uncovered = []
    for route in routes:
        matches = [
            rule
            for rule in rules
            if fnmatch.fnmatchcase(route["target"].lower(), rule["target_glob"].lower())
        ]
        if not matches:
            uncovered.append(f"{route['route']} -> {route['target']} (line {route['line']})")
    if uncovered:
        raise SystemExit("FAIL uncovered explicit routes:\n" + "\n".join(uncovered))


def validate_fixtures(fixtures: dict[str, Any]) -> None:
    tables = fixtures.get("tables", {})
    if set(tables) != set(PRIMARY_KEYS):
        missing = set(PRIMARY_KEYS) - set(tables)
        extra = set(tables) - set(PRIMARY_KEYS)
        raise SystemExit(f"FAIL fixture tables mismatch missing={sorted(missing)} extra={sorted(extra)}")

    seen_keys: set[tuple[str, Any]] = set()
    for table, rows in tables.items():
        primary_key = PRIMARY_KEYS[table]
        for row in rows:
            if primary_key not in row:
                raise SystemExit(f"FAIL {table} fixture lacks primary key {primary_key}")
            key = (table, row[primary_key])
            if key in seen_keys:
                raise SystemExit(f"FAIL duplicate fixture primary key {table}.{row[primary_key]}")
            seen_keys.add(key)

    users = tables["tbl_users"]
    if not users or any(row.get("password") != {"wp00c_password_hash": True} for row in users):
        raise SystemExit("FAIL every synthetic user must use runtime password-hash marker")

    menu_counts = {
        "group_type": len(tables["group_type"]),
        "tbl_menu": len(tables["tbl_menu"]),
        "group_menu": len(tables["group_menu"]),
    }
    if menu_counts != {"group_type": 10, "tbl_menu": 35, "group_menu": 4}:
        raise SystemExit(
            "FAIL menu catalog differs from CI3 baseline counts "
            f"expected=10/35/4 actual={menu_counts['group_type']}/{menu_counts['tbl_menu']}/{menu_counts['group_menu']}"
        )
    group_type_ids = {row["group_type_id"] for row in tables["group_type"]}
    menu_group_ids = {row["group_type"] for row in tables["tbl_menu"]}
    if menu_group_ids != group_type_ids:
        raise SystemExit(
            f"FAIL menu groups differ missing={sorted(group_type_ids - menu_group_ids)} "
            f"unknown={sorted(menu_group_ids - group_type_ids)}"
        )
    if any(
        row["group_type_name"].startswith("SYNTHETIC")
        for row in tables["group_type"]
    ) or any(row["menu_name"].startswith("SYNTHETIC") for row in tables["tbl_menu"]):
        raise SystemExit("FAIL menu labels must preserve CI3 user-facing names")
    admin_mapping = next(
        (row for row in tables["group_menu"] if row["id"] == 1), None
    )
    if admin_mapping is None or {
        int(value) for value in admin_mapping["group_type"].split(",")
    } != group_type_ids:
        raise SystemExit("FAIL central admin menu must include every CI3 menu group")
    for mapping in tables["group_menu"]:
        try:
            mapped_ids = {int(value) for value in mapping["group_type"].split(",")}
        except (AttributeError, ValueError) as exc:
            raise SystemExit(f"FAIL invalid group_menu mapping {mapping['id']}") from exc
        unknown_ids = mapped_ids - group_type_ids
        if not mapped_ids or unknown_ids:
            raise SystemExit(
                f"FAIL group_menu {mapping['id']} references unknown groups {sorted(unknown_ids)}"
            )

    serialized = json.dumps(fixtures, ensure_ascii=False)
    email_pattern = re.compile(r"[A-Za-z0-9._%+-]+@([A-Za-z0-9.-]+\.[A-Za-z]{2,}|example\.invalid)")
    bad_emails = [match.group(0) for match in email_pattern.finditer(serialized) if match.group(1).lower() != "example.invalid"]
    phone_pattern = re.compile(r"(?<![0-9A-Fa-f])(?:\+66|0)(?:[ .()-]*\d){8,10}(?![0-9A-Fa-f])")
    bad_phones = []
    for match in phone_pattern.finditer(serialized):
        digits = re.sub(r"\D", "", match.group(0))
        if digits != "0000000000":
            bad_phones.append(match.group(0))
    if bad_emails or bad_phones:
        raise SystemExit(f"FAIL fixture PII guard email={bad_emails} phone={bad_phones}")

    expected_counts = fixtures.get("expected_literals", {}).get("table_rows", {})
    actual_counts = {table: len(rows) for table, rows in tables.items()}
    if expected_counts != actual_counts:
        raise SystemExit("FAIL expected table-row literals do not match fixture rows")


def sql_quote(value: Any, password_hash_hex: str) -> str:
    if value is None:
        return "NULL"
    if isinstance(value, bool):
        return "1" if value else "0"
    if isinstance(value, (int, float)):
        return str(value)
    if value == {"wp00c_password_hash": True}:
        return f"UNHEX('{password_hash_hex}')"
    if not isinstance(value, str):
        raise SystemExit(f"FAIL unsupported fixture value {value!r}")
    escaped = value.replace("\\", "\\\\").replace("'", "''")
    return f"'{escaped}'"


def validate_password_hash_hex(value: str) -> str:
    if not re.fullmatch(r"[0-9a-fA-F]+", value) or len(value) % 2:
        raise SystemExit("FAIL --password-hash-hex must be even-length hexadecimal")
    decoded = bytes.fromhex(value)
    if not decoded.startswith((b"$2y$", b"$2b$", b"$2a$")):
        raise SystemExit("FAIL password hash is not bcrypt")
    return value.lower()


def render_seed(fixtures: dict[str, Any], password_hash_hex: str) -> None:
    print("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;")
    print("SET time_zone = '+00:00';")
    print("START TRANSACTION;")
    for table, rows in fixtures["tables"].items():
        for row in rows:
            columns = ", ".join(f"`{column}`" for column in row)
            values = ", ".join(sql_quote(value, password_hash_hex) for value in row.values())
            print(f"INSERT INTO `{table}` ({columns}) VALUES ({values});")
    print("COMMIT;")


def render_cleanup(fixtures: dict[str, Any]) -> None:
    print("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;")
    print("START TRANSACTION;")
    for table in reversed(list(fixtures["tables"])):
        rows = fixtures["tables"][table]
        if not rows:
            continue
        primary_key = PRIMARY_KEYS[table]
        keys = ", ".join(sql_quote(row[primary_key], "") for row in rows)
        print(f"DELETE FROM `{table}` WHERE `{primary_key}` IN ({keys});")
    print("COMMIT;")


def render_menu(fixtures: dict[str, Any]) -> None:
    print("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;")
    print("START TRANSACTION;")
    for table in ("group_menu", "tbl_menu", "group_type"):
        print(f"DELETE FROM `{table}`;")
    for table in ("group_type", "tbl_menu", "group_menu"):
        for row in fixtures["tables"][table]:
            columns = ", ".join(f"`{column}`" for column in row)
            values = ", ".join(sql_quote(value, "") for value in row.values())
            print(f"INSERT INTO `{table}` ({columns}) VALUES ({values});")
    print("COMMIT;")


def kit_sha256() -> str:
    digest = hashlib.sha256()
    for path in (CATALOG_PATH, FIXTURES_PATH):
        digest.update(path.relative_to(ROOT).as_posix().encode())
        digest.update(b"\0")
        digest.update(path.read_bytes())
        digest.update(b"\0")
    return digest.hexdigest()


def main() -> int:
    parser = argparse.ArgumentParser()
    subparsers = parser.add_subparsers(dest="command", required=True)

    validate_parser = subparsers.add_parser("validate")
    validate_parser.add_argument("--source-root", type=pathlib.Path, required=True)
    subparsers.add_parser("validate-data")

    seed_parser = subparsers.add_parser("render-seed-sql")
    seed_parser.add_argument("--password-hash-hex", required=True)

    subparsers.add_parser("render-cleanup-sql")
    subparsers.add_parser("render-menu-sql")
    subparsers.add_parser("expected-counts")
    subparsers.add_parser("summary")
    args = parser.parse_args()

    catalog = load_json(CATALOG_PATH)
    fixtures = load_json(FIXTURES_PATH)
    validate_fixtures(fixtures)

    if args.command == "validate":
        routes = validate_source(args.source_root.resolve())
        validate_catalog(catalog, fixtures, routes)
        print(f"PASS WP-00C kit: routes={len(routes)} cases={len(catalog['cases'])} fixture_rows={sum(map(len, fixtures['tables'].values()))}")
        print(f"KIT_SHA256={kit_sha256()}")
    elif args.command == "validate-data":
        validate_catalog(catalog, fixtures, [])
        print(f"PASS WP-00C data: cases={len(catalog['cases'])} fixture_rows={sum(map(len, fixtures['tables'].values()))}")
        print(f"KIT_SHA256={kit_sha256()}")
    elif args.command == "render-seed-sql":
        render_seed(fixtures, validate_password_hash_hex(args.password_hash_hex))
    elif args.command == "render-cleanup-sql":
        render_cleanup(fixtures)
    elif args.command == "render-menu-sql":
        render_menu(fixtures)
    elif args.command == "expected-counts":
        for table, count in fixtures["expected_literals"]["table_rows"].items():
            print(f"{table}\t{count}")
    elif args.command == "summary":
        print(json.dumps({
            "cases": len(catalog["cases"]),
            "fixture_rows": sum(map(len, fixtures["tables"].values())),
            "domains": sorted({case["domain"] for case in catalog["cases"]}),
            "kit_sha256": kit_sha256(),
        }, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
