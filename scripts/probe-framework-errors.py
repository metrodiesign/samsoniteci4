#!/usr/bin/env python3
"""Trigger CI3/CI4 framework error renderers through HTTP and CLI workflows."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
KINDS = ["404", "db", "exception", "general", "php"]


def run(*args: str, env: dict[str, str] | None = None) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, text=True, capture_output=True, check=False, env=env)


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def relative(path: Path) -> str:
    return str(path.resolve().relative_to(ROOT))


def traces(container: str, path: str) -> dict[str, list[dict[str, object]]]:
    result = run("docker", "exec", container, "sh", "-c", f"test ! -f {path} || cat {path}")
    grouped: dict[str, list[dict[str, object]]] = {}
    for line in result.stdout.splitlines():
        if line.strip():
            row = json.loads(line)
            grouped.setdefault(str(row.get("request_id")), []).append(row)
    return grouped


def template_names(rows: list[dict[str, object]]) -> list[str]:
    return sorted({str(name) for row in rows for name in row.get("templates", [])})


def http(side: str, kind: str, request_id: str, directory: Path) -> dict[str, object]:
    base = "http://127.0.0.1:18404" if side == "ci3" else "http://127.0.0.1:18405"
    route = f"/parityerrors/html{kind}" if side == "ci3" else f"/__parity/error/{kind}"
    body, headers = directory / f"{side}.html", directory / f"{side}.headers"
    result = run(
        "curl", "--silent", "--show-error", "--header", f"X-Parity-Request-ID: {request_id}",
        "--dump-header", str(headers), "--output", str(body),
        "--write-out", "%{http_code}\n%{content_type}\n%{url_effective}\n", base + route,
    )
    lines = result.stdout.splitlines()
    header_text = headers.read_text(errors="replace")
    headers.write_text("".join(
        "Set-Cookie: __REDACTED__\n" if line.lower().startswith("set-cookie:") else line
        for line in header_text.splitlines(keepends=True)
    ))
    return {
        "exit_code": result.returncode, "status": int(lines[0]) if lines and lines[0].isdigit() else 0,
        "content_type": lines[1] if len(lines) > 1 else "", "final_url": lines[2] if len(lines) > 2 else "",
        "body": relative(body), "body_sha256": sha256(body),
        "headers": relative(headers), "headers_sha256": sha256(headers),
    }


def cli(side: str, kind: str, request_id: str, directory: Path) -> dict[str, object]:
    if side == "ci3":
        args = ["docker", "exec", "-e", f"PARITY_REQUEST_ID={request_id}", "samsonitetracking-ci4-migration-web-1",
                "php", "/var/www/html/index.php", "parityerrors", f"html{kind}"]
    else:
        args = ["docker", "exec", "-e", f"PARITY_REQUEST_ID={request_id}", "samsonitetracking-ci4-migration-ci4-1",
                "php", "/app/spark", "parity:error", kind, "--no-header"]
    result = run(*args)
    output = directory / f"{side}.txt"
    text = result.stdout + result.stderr
    output.write_text(text)
    normalized = text.replace("/var/www/html", "__APP__").replace("/app", "__APP__")
    normalized = re.sub(r"Filename:\s+[^\n]*(?:Parityerrors|ParityError)\.php", "Filename:    __APP__/parity-error-entry.php", normalized)
    normalized = re.sub(r"Line Number:\s+[0-9]+", "Line Number: __LINE__", normalized)
    return {
        "exit_code": result.returncode, "output": relative(output),
        "output_sha256": sha256(output), "normalized_sha256": hashlib.sha256(normalized.encode()).hexdigest(),
        "normalized_text": normalized,
    }


def compare_dom(scenario: str, directory: Path) -> dict[str, object]:
    result = run(
        "php", str(ROOT / "scripts/compare-runtime-dom.php"),
        "--left", str(directory / "ci3.html"), "--right", str(directory / "ci4.html"), "--page", scenario,
    )
    output = directory / "dom-result.json"
    if result.stdout.strip():
        output.write_text(result.stdout)
        payload = json.loads(result.stdout)
    else:
        payload = {"status": "BLOCKED", "reason": result.stderr.strip()}
    payload["evidence"] = relative(output) if output.is_file() else None
    return payload


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("trace_manifest", type=Path)
    args = parser.parse_args()
    payload = json.loads(args.trace_manifest.read_text())
    root = args.trace_manifest.parent / "framework-errors"
    root.mkdir(exist_ok=True)
    workflows = []
    for mode in ("html", "cli"):
        for kind in KINDS:
            directory = root / f"{mode}-{kind}"
            directory.mkdir(exist_ok=True)
            left_id = f"fw_{payload['run_id'][:8]}_ci3_{mode}_{kind}"
            right_id = f"fw_{payload['run_id'][:8]}_ci4_{mode}_{kind}"
            row = {"mode": mode, "kind": kind, "ci3_request_id": left_id, "ci4_request_id": right_id}
            if mode == "html":
                row["ci3"] = http("ci3", kind, left_id, directory)
                row["ci4"] = http("ci4", kind, right_id, directory)
                row["dom"] = compare_dom(f"framework-html-{kind}", directory)
            else:
                row["ci3"] = cli("ci3", kind, left_id, directory)
                row["ci4"] = cli("ci4", kind, right_id, directory)
                row["text_status"] = "PASS" if row["ci3"]["normalized_sha256"] == row["ci4"]["normalized_sha256"] else "FAIL"
            workflows.append(row)

    ci3_trace = traces("samsonitetracking-ci4-migration-web-1", "/tmp/ci3-parity-template-trace.jsonl")
    ci4_trace = traces("samsonitetracking-ci4-migration-ci4-1", "/app/writable/parity-template-trace.jsonl")
    records = {row["source_template"]: row for row in payload["records"]}
    for workflow in workflows:
        source_templates = template_names(ci3_trace.get(workflow["ci3_request_id"], []))
        target_templates = template_names(ci4_trace.get(workflow["ci4_request_id"], []))
        for relative in source_templates:
            if not relative.startswith("errors/"):
                continue
            source = "application/views/" + relative
            target = "app/Views/ci3/" + relative
            raw = []
            if workflow["mode"] == "html":
                raw = [workflow["ci3"]["body"], workflow["ci3"]["headers"], workflow["ci4"]["body"], workflow["ci4"]["headers"]]
                if workflow["dom"].get("evidence"):
                    raw.append(workflow["dom"]["evidence"])
                dom_results = [{
                    "scenario_id": f"framework-html-{workflow['kind']}", "status": workflow["dom"].get("status"),
                    "difference_count": workflow["dom"].get("difference_count"),
                    "differences": workflow["dom"].get("differences", []), "evidence": workflow["dom"].get("evidence"),
                }]
            else:
                raw = [workflow["ci3"]["output"], workflow["ci4"]["output"]]
                dom_results = [{
                    "scenario_id": f"framework-cli-{workflow['kind']}", "status": workflow["text_status"],
                    "difference_count": 0 if workflow["text_status"] == "PASS" else 1,
                    "differences": [] if workflow["text_status"] == "PASS" else [{"selector": "CLI:text", "kind": "normalized_text"}],
                    "evidence": workflow["ci3"]["output"],
                }]
            records[source] = {
                "source_template": source, "target_template": target,
                "source_caller": [f"framework {workflow['mode']} renderer request_id={workflow['ci3_request_id']}"],
                "target_caller": [f"framework {workflow['mode']} renderer request_id={workflow['ci4_request_id']}"],
                "workflow": [f"framework-{workflow['mode']}-{workflow['kind']}"],
                "scenario_ids": [f"framework-{workflow['mode']}-{workflow['kind']}"],
                "source_template_trace": source_templates, "target_template_trace": target_templates,
                "fixture_identifiers": ["synthetic-error"], "source_final_url": [], "target_final_url": [],
                "source_http_status": [workflow["ci3"].get("status")], "target_http_status": [workflow["ci4"].get("status")],
                "source_content_type": [workflow["ci3"].get("content_type")], "target_content_type": [workflow["ci4"].get("content_type")],
                "raw_evidence_paths": raw, "dom_results": dom_results, "render_mode": f"framework_{workflow['mode']}",
                "role": "framework", "branch": None, "language": "en", "state": workflow["kind"],
            }
    payload["records"] = sorted(records.values(), key=lambda row: row["source_template"])
    payload["framework_error_workflows"] = workflows
    args.trace_manifest.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps({
        "workflows": len(workflows), "records": len(payload["records"]),
        "source_error_templates": sum(row["source_template"].startswith("application/views/errors/") for row in payload["records"]),
    }))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
