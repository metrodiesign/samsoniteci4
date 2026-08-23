#!/usr/bin/env python3
"""Convert passing mapped PHPUnit cases into deterministic WP-00C evidence."""

from __future__ import annotations

import argparse
import hashlib
import json
import pathlib
import xml.etree.ElementTree as ET


def load_mapping(path: pathlib.Path) -> dict[str, object]:
    value = json.loads(path.read_text(encoding="utf-8"))
    cases = value.get("cases") if isinstance(value, dict) else None
    if not isinstance(cases, dict):
        raise SystemExit("FAIL mapping cases must be an object")
    return cases


def test_results(path: pathlib.Path) -> dict[str, dict[str, int | str]]:
    result: dict[str, dict[str, int | str]] = {}
    for node in ET.parse(path).getroot().iter("testcase"):
        class_name = node.get("class")
        name = node.get("name")
        if not class_name or not name:
            raise SystemExit("FAIL JUnit testcase identity missing")
        identity = f"{class_name}::{name}"
        if identity in result:
            raise SystemExit(f"FAIL duplicate JUnit testcase: {identity}")
        failed = any(node.find(tag) is not None for tag in ("failure", "error", "skipped"))
        result[identity] = {
            "assertions": int(node.get("assertions", "0")),
            "result": "FAIL" if failed else "PASS",
        }
    return result


def case_config(value: object) -> tuple[list[str], list[str], str, str]:
    if isinstance(value, list):
        tests, sources, execution_status, blocker = value, [], "PASS", ""
    elif isinstance(value, dict):
        tests = value.get("tests")
        sources = value.get("source_files", [])
        execution_status = value.get("execution_status", "PASS")
        blocker = value.get("blocker", "")
    else:
        raise SystemExit("FAIL invalid case mapping")
    if not isinstance(tests, list) or not tests or not all(isinstance(item, str) for item in tests):
        raise SystemExit("FAIL mapped tests must be a non-empty string list")
    if not isinstance(sources, list) or not all(isinstance(item, str) for item in sources):
        raise SystemExit("FAIL source_files must be a string list")
    if execution_status not in ("PASS", "BLOCKED"):
        raise SystemExit("FAIL execution_status must be PASS or BLOCKED")
    if not isinstance(blocker, str) or (execution_status == "BLOCKED" and not blocker.strip()):
        raise SystemExit("FAIL BLOCKED mapping requires blocker")
    return tests, sources, execution_status, blocker


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--junit", type=pathlib.Path, action="append", required=True)
    parser.add_argument("--map", type=pathlib.Path, required=True)
    parser.add_argument("--round", type=int, choices=(1, 2, 3), required=True)
    parser.add_argument("--source-root", type=pathlib.Path, default=pathlib.Path.cwd())
    parser.add_argument("--output", type=pathlib.Path, required=True)
    args = parser.parse_args()

    junit = {}
    for path in args.junit:
        results = test_results(path)
        duplicates = sorted(set(junit) & set(results))
        if duplicates:
            raise SystemExit(f"FAIL duplicate JUnit testcase across inputs: {duplicates[0]}")
        junit.update(results)
    records = []
    failed = False
    for case_id, raw_config in load_mapping(args.map).items():
        tests, sources, execution_status, blocker = case_config(raw_config)
        missing = sorted(identity for identity in tests if identity not in junit)
        failing = sorted(
            identity for identity in tests if identity in junit and junit[identity]["result"] != "PASS"
        )
        source_hashes = {}
        for source in sorted(sources):
            path = args.source_root / source
            if not path.is_file():
                raise SystemExit(f"FAIL mapped source file missing: {source}")
            source_hashes[source] = hashlib.sha256(path.read_bytes()).hexdigest()
        semantic = {
            "blocker": blocker,
            "case_id": case_id,
            "execution_status": execution_status,
            "source_sha256": source_hashes,
            "tests": {identity: junit[identity] for identity in sorted(tests) if identity in junit},
        }
        result = "FAIL" if missing or failing else execution_status
        failed = failed or result != "PASS"
        record = {
                "id": case_id,
                "result": result,
                "semantic_sha256": hashlib.sha256(
                    json.dumps(semantic, sort_keys=True, separators=(",", ":")).encode()
                ).hexdigest(),
                "mapped_tests": len(tests),
                "assertions": sum(int(junit[test]["assertions"]) for test in tests if test in junit),
                "missing_tests": missing,
                "failing_tests": failing,
            }
        if blocker:
            record["blocker"] = blocker
        records.append(record)

    payload = {"round": args.round, "source": "PHPUnit JUnit", "cases": records}
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(payload, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    print(f"WP-00C JUnit evidence: cases={len(records)} failed={sum(r['result'] != 'PASS' for r in records)}")
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
