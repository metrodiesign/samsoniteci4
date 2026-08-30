#!/usr/bin/env python3
"""Merge authenticated in-app Browser routes into runtime trace records."""

from __future__ import annotations

import argparse
import json
import subprocess
from collections import Counter, defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def traces(container: str, path: str) -> dict[str, list[str]]:
    result = subprocess.run(
        ["docker", "exec", container, "sh", "-c", f"test ! -f {path} || cat {path}"],
        text=True, capture_output=True, check=False,
    )
    if result.returncode:
        raise RuntimeError(result.stderr.strip())
    grouped: dict[str, set[str]] = defaultdict(set)
    for line in result.stdout.splitlines():
        if line.strip():
            row = json.loads(line)
            grouped[str(row.get("request_id"))].update(str(name) for name in row.get("templates", []))
    return {request_id: sorted(names) for request_id, names in grouped.items()}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("trace_manifest", type=Path)
    parser.add_argument("browser_manifests", type=Path, nargs="+")
    parser.add_argument("--append", action="store_true")
    args = parser.parse_args()
    payload = json.loads(args.trace_manifest.read_text())
    rows = []
    for manifest_path in args.browser_manifests:
        manifest = json.loads(manifest_path.read_text())
        rows.extend({**row, "_manifest_dir": str(manifest_path.parent)} for row in manifest["results"])
    ci3 = traces("samsonitetracking-ci4-migration-web-1", "/tmp/ci3-parity-template-trace.jsonl")
    ci4 = traces("samsonitetracking-ci4-migration-ci4-1", "/app/writable/parity-template-trace.jsonl")
    records = {row["source_template"]: row for row in payload["records"]}
    if not args.append:
        for record in records.values():
            record.pop("openai_browser_templates", None)
            record.pop("openai_browser_results", None)
    pairs = defaultdict(dict)
    for row in rows:
        pairs[row["scenario_id"]][row["side"]] = row
    statuses = Counter()
    bound = 0
    for scenario_id, sides in pairs.items():
        if set(sides) != {"ci3", "ci4"}:
            continue
        left, right = sides["ci3"], sides["ci4"]
        source_templates = ci3.get(left["request_id"], [])
        target_templates = ci4.get(right["request_id"], [])
        for relative in source_templates:
            record = records.get("application/views/" + relative)
            if record is None:
                continue
            target_called = "ci3/" + relative in target_templates
            console = "PASS" if not left.get("console_errors") and not right.get("console_errors") else "FAIL"
            captured = left.get("status") == "CAPTURED" and right.get("status") == "CAPTURED"
            overall = "FAIL" if not target_called or console == "FAIL" else "BLOCKED"
            result = {
                "scenario_id": scenario_id, "viewport": "UNAVAILABLE",
                "source_url": left.get("final_url"), "target_url": right.get("final_url"),
                "source_request_id": left["request_id"], "target_request_id": right["request_id"],
                "source_template_trace": source_templates, "target_template_trace": target_templates,
                "target_called": target_called, "interaction_status": "BLOCKED", "interaction_action": None,
                "visual_status": "BLOCKED", "console_status": console, "network_status": "BLOCKED",
                "overall_status": overall if captured else "BLOCKED",
                "source_screenshot": str((Path(left["_manifest_dir"]) / left["screenshot"]).resolve().relative_to(ROOT)) if left.get("screenshot") else None,
                "target_screenshot": str((Path(right["_manifest_dir"]) / right["screenshot"]).resolve().relative_to(ROOT)) if right.get("screenshot") else None,
                "source_screenshot_sha256": left.get("screenshot_sha256"), "target_screenshot_sha256": right.get("screenshot_sha256"),
            }
            record.setdefault("openai_browser_templates", []).append(relative)
            record.setdefault("openai_browser_results", []).append(result)
            bound += 1
            statuses[result["overall_status"]] += 1
    for record in records.values():
        if "openai_browser_templates" in record:
            record["openai_browser_templates"] = sorted(set(record["openai_browser_templates"]))
    payload["openai_browser_authenticated_summary"] = {
        "bound_results": bound,
        "templates_covered_total": sum(bool(row.get("openai_browser_templates")) for row in records.values()),
        "statuses": dict(statuses),
        "browser_manifests": [str(path.resolve().relative_to(ROOT)) for path in args.browser_manifests],
    }
    args.trace_manifest.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps(payload["openai_browser_authenticated_summary"], ensure_ascii=False))
    return 1 if statuses.get("FAIL") or statuses.get("BLOCKED") else 0


if __name__ == "__main__":
    raise SystemExit(main())
