#!/usr/bin/env python3
"""Fail closed unless every WP-00C case has stable evidence and approvals."""

from __future__ import annotations

import argparse
import json
import pathlib
import re


SHA256 = re.compile(r"^[0-9a-f]{64}$")


def load(path: pathlib.Path) -> dict:
    value = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(value, dict):
        raise SystemExit(f"FAIL invalid JSON object: {path}")
    return value


def indexed(records: object, key: str, label: str) -> dict[str, dict]:
    if not isinstance(records, list):
        raise SystemExit(f"FAIL {label} must be a list")
    result: dict[str, dict] = {}
    for record in records:
        if not isinstance(record, dict) or not isinstance(record.get(key), str):
            raise SystemExit(f"FAIL invalid {label} record")
        identifier = record[key]
        if identifier in result:
            raise SystemExit(f"FAIL duplicate {label}: {identifier}")
        result[identifier] = record
    return result


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--catalog", type=pathlib.Path, required=True)
    parser.add_argument("--round", type=pathlib.Path, action="append", default=[])
    parser.add_argument("--approvals", type=pathlib.Path, required=True)
    args = parser.parse_args()

    cases = indexed(load(args.catalog).get("cases"), "id", "catalog case")
    rounds = [indexed(load(path).get("cases"), "id", f"round {number}") for number, path in enumerate(args.round, 1)]
    approval_records = load(args.approvals).get("approvals")
    if not isinstance(approval_records, list):
        raise SystemExit("FAIL approvals must be a list")

    approvals: dict[str, dict[str, str]] = {}
    for record in approval_records:
        if not isinstance(record, dict):
            raise SystemExit("FAIL invalid approval record")
        case_id = record.get("case_id")
        role = record.get("role")
        approver = record.get("approver")
        if not all(isinstance(value, str) and value.strip() for value in (case_id, role, approver)):
            raise SystemExit("FAIL invalid approval identity")
        if role in approvals.setdefault(case_id, {}):
            raise SystemExit(f"FAIL duplicate approval: {case_id}/{role}")
        approvals[case_id][role] = approver

    closed = 0
    for case_id, case in cases.items():
        problems = []
        hashes = []
        if len(rounds) != 3:
            problems.append(f"rounds={len(rounds)}/3")
        for number, records in enumerate(rounds, 1):
            record = records.get(case_id)
            if record is None:
                problems.append(f"round {number}=MISSING")
                continue
            digest = record.get("semantic_sha256")
            if record.get("result") != "PASS":
                problems.append(f"round {number}={record.get('result', 'INVALID')}")
            if not isinstance(digest, str) or SHA256.fullmatch(digest) is None:
                problems.append(f"round {number}=INVALID_HASH")
            else:
                hashes.append(digest)
        if len(hashes) > 1 and len(set(hashes)) != 1:
            problems.append("determinism mismatch")

        required = case.get("approval_roles")
        if not isinstance(required, list) or not all(isinstance(role, str) for role in required):
            problems.append("invalid approval roles")
            required = []
        pending = [role for role in required if role not in approvals.get(case_id, {})]
        if pending:
            problems.append("pending approvals=" + ",".join(pending))

        if problems:
            print(f"OPEN {case_id}: {'; '.join(problems)}")
        else:
            closed += 1
            print(f"PASS {case_id}")

    if closed == len(cases):
        print(f"WP-00C CLOSED {closed}/{len(cases)}")
        return 0

    print(f"WP-00C OPEN {closed}/{len(cases)}")
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
