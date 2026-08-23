#!/usr/bin/env python3
"""Render deterministic, synthetic-only SQL for WP-00C benchmark runs."""

from __future__ import annotations

import argparse
import hashlib
import json
from datetime import UTC, datetime, timedelta
from pathlib import Path


USERS = 5_000
BRANCHES = 20
ORDERS = 100_000
LOGS_PER_ORDER = 4
START = datetime(2024, 1, 1, tzinfo=UTC)
END = datetime(2026, 8, 23, tzinfo=UTC)


def quote(value: object) -> str:
    if value is None:
        return "NULL"
    if isinstance(value, int):
        return str(value)
    return "'" + str(value).replace("\\", "\\\\").replace("'", "''") + "'"


def write_rows(out, table: str, columns: tuple[str, ...], rows) -> None:
    values = ",".join(f"`{column}`" for column in columns)
    batch = []
    for row in rows:
        batch.append("(" + ",".join(quote(value) for value in row) + ")")
        if len(batch) == 500:
            out.write(f"INSERT INTO `{table}` ({values}) VALUES " + ",".join(batch) + ";\n")
            batch.clear()
    if batch:
        out.write(f"INSERT INTO `{table}` ({values}) VALUES " + ",".join(batch) + ";\n")


def timestamp(number: int) -> str:
    seconds = int((END - START).total_seconds())
    return (START + timedelta(seconds=(number * 86_407) % seconds)).strftime("%Y-%m-%d %H:%M:%S")


def render(output: Path, password_hash_hex: str) -> dict[str, object]:
    if len(password_hash_hex) % 2 or any(char not in "0123456789abcdef" for char in password_hash_hex.lower()):
        raise SystemExit("FAIL --password-hash-hex must be even-length hexadecimal")
    password = bytes.fromhex(password_hash_hex)
    if not password.startswith((b"$2a$", b"$2b$", b"$2y$")):
        raise SystemExit("FAIL --password-hash-hex must encode bcrypt")

    output.parent.mkdir(parents=True, exist_ok=True)
    with output.open("w", encoding="utf-8", newline="\n") as out:
        out.write("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;\nSET time_zone = '+00:00';\nSTART TRANSACTION;\n")
        write_rows(out, "branch_type", ("branch_type_id", "branch_type_details", "branch_type_image"), ((1, "SYNTHETIC BENCHMARK", "synthetic.png"),))
        write_rows(out, "branch", ("branch_id", "branch_type", "branch_user_name", "branch_name", "branch_details", "default_suffix", "book_order", "customer_ref", "cdate"), ((branch, 1, f"bench-{branch:02d}", f"SYNTHETIC BRANCH {branch:02d}", "SYNTHETIC ONLY", f"B{branch:02d}", f"B{branch:02d}", f"B{branch:02d}", "2024-01-01 00:00:00") for branch in range(1, BRANCHES + 1)))
        write_rows(out, "book", ("book_id", "branch_id", "book_detail", "status", "bunber_limit", "cdate"), ((branch, branch, f"B{branch:02d}", 1, 100_000, "2024-01-01 00:00:00") for branch in range(1, BRANCHES + 1)))
        write_rows(out, "brand", ("brand_id", "brand_details", "cdate"), ((1, "SYNTHETIC BRAND", "2024-01-01 00:00:00"),))
        write_rows(out, "type", ("type_id", "type_details"), ((1, "SYNTHETIC TYPE"),))
        write_rows(out, "condition", ("condition_id", "condition_details"), ((1, "SYNTHETIC CONDITION"),))
        write_rows(out, "estimateprice", ("estimateprice_id", "estimateprice_details"), ((1, "SYNTHETIC ESTIMATE"),))
        write_rows(out, "fixed", ("fixed_id", "fixed_details"), ((1, "SYNTHETIC FIX"),))
        write_rows(out, "provider", ("provider_id", "provider_name", "provider_tel", "provider_datail"), ((1, "SYNTHETIC PROVIDER", "0000000000", "SYNTHETIC ONLY"),))
        write_rows(out, "statusaction", ("status_id", "status_name", "status_name_th"), ((status, f"SYNTHETIC STATUS {status}", f"สถานะทดสอบ {status}") for status in range(1, 9)))
        write_rows(out, "tbl_roles", ("roleId", "role"), ((1, "SYNTHETIC ADMIN"), (2, "SYNTHETIC OPERATOR"), (3, "SYNTHETIC VIEWER")))
        write_rows(out, "tbl_users", ("userId", "email", "username", "password", "name", "mobile", "group_id", "roleId", "branch_id", "branch_type_id", "isDeleted", "createdBy", "createdDtm"), ((user, f"bench-{user:05d}@example.invalid", f"bench-{user:05d}", password.decode(), f"SYNTHETIC USER {user:05d}", "0000000000", 1, 2, ((user - 1) % BRANCHES) + 1, 1, 0, 1, "2024-01-01 00:00:00") for user in range(1, USERS + 1)))
        write_rows(out, "request_order", ("request_id", "requestDate", "trackID", "bookID", "numberID", "orderID", "orderIDShow", "warantyType", "waranty_cmg", "customerFullname", "customerTel", "customerEmail", "detailTypeId", "detailBrandId", "detailCondition", "detailEstimatePrice", "detailFixed", "branchID", "branch_type_id", "UserID", "provider_id", "date_create", "date_update_status", "action_status", "RepairPrice", "number_cmg", "create_by_user"), ((order, timestamp(order), f"BENCH-{order:06d}", f"B{((order - 1) % BRANCHES) + 1:02d}", f"N-{order:06d}", f"O-{order:06d}", f"B/{order:06d}", 1, "IN", f"SYNTHETIC CUSTOMER {order:06d}", "0000000000", f"customer-{order:06d}@example.invalid", 1, 1, "1", "1", "1", ((order - 1) % BRANCHES) + 1, 1, ((order - 1) % USERS) + 1, 1, timestamp(order), timestamp(order + 1), ((order - 1) % 8) + 1, "100.00", f"CMG-{order:06d}", "SYNTHETIC BENCHMARK") for order in range(1, ORDERS + 1)))
        write_rows(out, "status_log", ("id", "order_id", "action_id", "update_id", "cdate"), ((log, f"BENCH-{((log - 1) // LOGS_PER_ORDER) + 1:06d}", ((log - 1) % LOGS_PER_ORDER) + 1, None, timestamp(log)) for log in range(1, ORDERS * LOGS_PER_ORDER + 1)))
        out.write("COMMIT;\n")
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    return {"users": USERS, "branches": BRANCHES, "orders": ORDERS, "status_logs": ORDERS * LOGS_PER_ORDER, "fixture_sha256": digest}


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--metadata", type=Path, required=True)
    parser.add_argument("--password-hash-hex", required=True)
    args = parser.parse_args()
    metadata = render(args.output, args.password_hash_hex)
    args.metadata.write_text(json.dumps(metadata, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    print(json.dumps(metadata, sort_keys=True))


if __name__ == "__main__":
    main()
