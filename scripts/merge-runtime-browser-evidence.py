#!/usr/bin/env python3
"""Bind in-app Browser actions to parity template traces by request ID."""

from __future__ import annotations

import argparse
import json
import subprocess
from collections import Counter, defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def container_traces(container: str, path: str) -> dict[str, list[dict[str, object]]]:
    completed = subprocess.run(
        ["docker", "exec", container, "sh", "-c", f"test ! -f {path} || cat {path}"],
        text=True, capture_output=True, check=False,
    )
    if completed.returncode:
        raise RuntimeError(completed.stderr.strip())
    grouped: dict[str, list[dict[str, object]]] = defaultdict(list)
    for line in completed.stdout.splitlines():
        if line.strip():
            record = json.loads(line)
            grouped[str(record.get("request_id"))].append(record)
    return grouped


def templates(grouped: dict[str, list[dict[str, object]]], request_id: str) -> list[str]:
    return sorted({str(name) for row in grouped.get(request_id, []) for name in row.get("templates", [])})


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("trace_manifest", type=Path)
    parser.add_argument("browser_manifest", type=Path)
    parser.add_argument("interaction_retry", type=Path)
    args = parser.parse_args()
    trace = json.loads(args.trace_manifest.read_text())
    browser = json.loads(args.browser_manifest.read_text())
    retry = json.loads(args.interaction_retry.read_text())
    ci3 = container_traces("samsonitetracking-ci4-migration-web-1", "/tmp/ci3-parity-template-trace.jsonl")
    ci4 = container_traces("samsonitetracking-ci4-migration-ci4-1", "/app/writable/parity-template-trace.jsonl")

    retry_by_key = {(row["scenario_id"], row["side"]): row for row in retry["results"]}
    rows = browser["results"]
    by_key = {(row["scenario_id"], row["viewport"], row["side"]): row for row in rows}
    stale = {
        (row["scenario_id"], row["side"])
        for row in rows if row.get("screenshot_sha256") and any(
            other.get("screenshot_sha256") == row["screenshot_sha256"] and other["viewport"] != row["viewport"]
            for other in rows if other["scenario_id"] == row["scenario_id"] and other["side"] == row["side"]
        )
    }
    records = {row["source_template"]: row for row in trace["records"]}
    for record in records.values():
        record.pop("openai_browser_templates", None)
        record.pop("openai_browser_results", None)
    bound = 0
    statuses = Counter()
    for scenario_id, viewport in sorted({(row["scenario_id"], row["viewport"]) for row in rows}):
        left = by_key[(scenario_id, viewport, "ci3")]
        right = by_key[(scenario_id, viewport, "ci4")]
        source_templates = templates(ci3, left["request_id"])
        target_templates = templates(ci4, right["request_id"])
        for relative in source_templates:
            source = "application/views/" + relative
            record = records.get(source)
            if record is None:
                continue
            expected = "ci3/" + relative
            target_called = expected in target_templates
            retry_left = retry_by_key.get((scenario_id, "ci3"), left)
            retry_right = retry_by_key.get((scenario_id, "ci4"), right)
            interaction_values = [retry_left.get("interaction", {}).get("status"), retry_right.get("interaction", {}).get("status")]
            if "FAIL" in interaction_values:
                interaction = "FAIL"
            elif interaction_values == ["PASS", "PASS"]:
                interaction = "PASS"
            elif interaction_values == ["NOT_APPLICABLE", "NOT_APPLICABLE"]:
                interaction = "NOT_APPLICABLE"
            else:
                interaction = "BLOCKED"
            if left.get("status") != "CAPTURED" or right.get("status") != "CAPTURED":
                visual = "BLOCKED"
            elif (scenario_id, "ci3") in stale or (scenario_id, "ci4") in stale:
                visual = "BLOCKED"
            else:
                visual = "PASS" if left.get("screenshot_sha256") == right.get("screenshot_sha256") else "FAIL"
            console = "PASS" if not left.get("console_errors") and not right.get("console_errors") else "FAIL"
            overall = "FAIL" if not target_called or interaction == "FAIL" or visual == "FAIL" or console == "FAIL" else "BLOCKED"
            result = {
                "scenario_id": scenario_id, "viewport": viewport,
                "source_url": left.get("final_url"), "target_url": right.get("final_url"),
                "source_request_id": left["request_id"], "target_request_id": right["request_id"],
                "source_template_trace": source_templates, "target_template_trace": target_templates,
                "target_called": target_called, "interaction_status": interaction, "visual_status": visual,
                "interaction_action": retry_left.get("interaction", {}).get("action"),
                "console_status": console, "network_status": "BLOCKED", "overall_status": overall,
                "source_screenshot": str((args.browser_manifest.parent / left["screenshot"]).resolve().relative_to(ROOT)) if left.get("screenshot") else None,
                "target_screenshot": str((args.browser_manifest.parent / right["screenshot"]).resolve().relative_to(ROOT)) if right.get("screenshot") else None,
                "source_screenshot_sha256": left.get("screenshot_sha256"), "target_screenshot_sha256": right.get("screenshot_sha256"),
            }
            record.setdefault("openai_browser_templates", []).append(relative)
            record.setdefault("openai_browser_results", []).append(result)
            bound += 1
            statuses[overall] += 1
    for record in records.values():
        if "openai_browser_templates" in record:
            record["openai_browser_templates"] = sorted(set(record["openai_browser_templates"]))
    trace["openai_browser_summary"] = {
        "bound_results": bound, "templates_covered": sum("openai_browser_templates" in row for row in records.values()),
        "statuses": dict(statuses), "stale_screenshot_pairs": len(stale),
        "browser_manifest": str(args.browser_manifest.resolve().relative_to(ROOT)),
        "interaction_retry": str(args.interaction_retry.resolve().relative_to(ROOT)),
    }
    args.trace_manifest.write_text(json.dumps(trace, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps(trace["openai_browser_summary"], ensure_ascii=False))
    return 1 if statuses.get("FAIL") or statuses.get("BLOCKED") else 0


if __name__ == "__main__":
    raise SystemExit(main())
