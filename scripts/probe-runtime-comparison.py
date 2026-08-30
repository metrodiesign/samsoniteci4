#!/usr/bin/env python3
"""Probe real CI3/CI4 routes and join them to parity-only template traces."""

from __future__ import annotations

import hashlib
import os
import json
import pathlib
import subprocess
import sys
import tempfile
import uuid
from datetime import datetime, timezone
from html.parser import HTMLParser
from urllib.parse import urlencode

ROOT = pathlib.Path(__file__).resolve().parents[1]
CI3_BASE = "http://127.0.0.1:18404"
CI4_BASE = "http://127.0.0.1:18405"
PIN = "ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6"
SCENARIOS = [
    {"id": "public-root-en", "ci3": "/", "ci4": "/", "role": "anonymous", "language": "en", "state": "empty-form"},
    {"id": "public-root-th", "ci3": "/track_th", "ci4": "/track_th", "role": "anonymous", "language": "th", "state": "empty-form"},
    {"id": "contact-en", "ci3": "/contact", "ci4": "/contact", "role": "anonymous", "language": "en", "state": "empty-form"},
    {"id": "contact-th", "ci3": "/contact_th", "ci4": "/contact-th", "role": "anonymous", "language": "th", "state": "empty-form"},
    {"id": "login", "ci3": "/login", "ci4": "/login", "role": "anonymous", "language": "en", "state": "empty-form"},
    {"id": "forgot-password", "ci3": "/forgotPassword", "ci4": "/forgot-password", "role": "anonymous", "language": "en", "state": "empty-form"},
    {"id": "framework-404", "ci3": "/parityerrors/html404", "ci4": "/__parity/error/404", "role": "anonymous", "language": "en", "state": "not-found"},
    {"id": "tracking-result-en", "ci3": "/track/trackstatus/PARITY-NOT-FOUND", "ci4": "/tracking/PARITY-NOT-FOUND", "role": "anonymous", "language": "en", "state": "not-found"},
    {"id": "tracking-result-th", "ci3": "/track_th/trackstatus", "ci4": "/tracking-th/PARITY-NOT-FOUND", "ci3_method": "POST", "ci3_data": {"searchText": "PARITY-NOT-FOUND"}, "role": "anonymous", "language": "th", "state": "not-found"},
]
AUTH_SCENARIOS = [
    {"id": "dashboard", "ci3": "/dashboard", "ci4": "/dashboard"},
    {"id": "orders-new", "ci3": "/Orders", "ci4": "/orders/new"},
    {"id": "orders-status-1", "ci3": "/ordersListing", "ci4": "/ordersListing"},
    {"id": "send-order-list", "ci3": "/sendorderListing", "ci4": "/sendorderListing"},
    {"id": "orders-status-2", "ci3": "/TrackingListing", "ci4": "/TrackingListing"},
    {"id": "orders-status-3", "ci3": "/TrackingcloseListing", "ci4": "/TrackingcloseListing"},
    {"id": "orders-status-4", "ci3": "/TrackingreturnListing", "ci4": "/TrackingreturnListing"},
    {"id": "orders-status-5", "ci3": "/TrackingcompleteListing", "ci4": "/TrackingcompleteListing"},
    {"id": "orders-status-7", "ci3": "/TrackingCompletedListing", "ci4": "/TrackingCompletedListing"},
    {"id": "report-tracking-list", "ci3": "/ReportTrackingListing", "ci4": "/ReportTrackingListing"},
    {"id": "imports-status", "ci3": "/UploadexcelListing", "ci4": "/imports/status"},
    {"id": "imports-price", "ci3": "/UploadexcelpriceListing", "ci4": "/imports/price"},
    {"id": "imports-new-order", "ci3": "/UploadneworderexcelListing", "ci4": "/imports/new-order"},
    {"id": "change-password", "ci3": "/loadChangePass", "ci4": "/change-password"},
    {"id": "login-history", "ci3": "/login-history", "ci4": "/login-history"},
    {"id": "contact-list", "ci3": "/contactListing", "ci4": "/contactListing"},
    {"id": "menu-list", "ci3": "/menuListing", "ci4": "/menu"},
    {"id": "background-list", "ci3": "/BackgroundListing", "ci4": "/backgrounds"},
    {"id": "users-list", "ci3": "/userListing", "ci4": "/users"},
    {"id": "add-user", "ci3": "/addNew", "ci4": "/users/new"},
    {"id": "add-menu", "ci3": "/addNewMenu", "ci4": "/menu/new"},
    {"id": "add-background", "ci3": "/BackgroundNew", "ci4": "/backgrounds/new"},
    {"id": "edit-user", "ci3": "/editOld/9001", "ci4": "/users/9001"},
    {"id": "edit-menu", "ci3": "/editMunuOld/1", "ci4": "/menu/1"},
    {"id": "edit-background", "ci3": "/editBackgroundOld/97001", "ci4": "/backgrounds/97001"},
    {"id": "edit-order", "ci3": "/editOrdersOld/91001", "ci4": "/orders/91001"},
    {"id": "print-order", "ci3": "/OrderPrint/91001", "ci4": "/orders/91001/print"},
    {"id": "report-ratings", "ci3": "/user/report", "ci4": "/user/report"},
    {"id": "report-jobs-day", "ci3": "/user/report_job_byday", "ci4": "/user/report_job_byday"},
    {"id": "report-pending", "ci3": "/user/report_job_pending", "ci4": "/user/report_job_pending"},
    {"id": "report-pending-total", "ci3": "/user/report_total_job_pending", "ci4": "/user/report_total_job_pending"},
    {"id": "report-progress-average", "ci3": "/user/report_in_progress_average", "ci4": "/user/report_in_progress_average"},
    {"id": "report-progress", "ci3": "/user/report_in_progress_job", "ci4": "/user/report_in_progress_job"},
    {"id": "report-summary", "ci3": "/reportsummary", "ci4": "/reportsummary"},
    {"id": "export-ratings", "ci3": "/user/excel_ratings", "ci4": "/user/excel_ratings"},
    {"id": "export-progress", "ci3": "/user/excel_in_progress_job", "ci4": "/user/excel_in_progress_job"},
    {"id": "export-tracking", "ci3": "/Order/excel_report", "ci4": "/Order/excel_report"},
    {"id": "export-summary", "ci3": "/Order/excel_report_sum", "ci4": "/Order/excel_report_sum"},
    {"id": "controller-404", "ci3": "/editConditionOld/99999999", "ci4": "/master/condition/99999999"},
    {"id": "legacy-page-not-found", "ci3": "/condition/pageNotFound", "ci4": "/condition/pageNotFound"},
]
for master, ci3_name in {
    "branch": "branch", "branchtype": "branchtype", "statustype": "statustype", "producttype": "producttype",
    "book": "book", "brand": "brand", "condition": "condition", "estimateprice": "estimateprice",
    "fixed": "fixed", "provider": "provider",
}.items():
    AUTH_SCENARIOS.append({"id": f"master-{master}-list", "ci3": f"/{ci3_name}Listing", "ci4": f"/master/{master}"})
for master, ci3_route in {
    "branch": "BranchNew", "branchtype": "add_new_branchtype", "statustype": "add_new_statustype",
    "producttype": "add_new_producttype", "book": "BookNew", "brand": "add_new_brand",
    "condition": "add_new_condition", "estimateprice": "add_new_estimateprice", "fixed": "add_new_fixed",
    "provider": "add_new_provider",
}.items():
    AUTH_SCENARIOS.append({"id": f"master-{master}-add", "ci3": f"/{ci3_route}", "ci4": f"/master/{master}/new"})
for master, ci3_route in {
    "branch": "editBranchOld", "branchtype": "editBranchtypeOld", "statustype": "editStatustypeOld",
    "producttype": "editProducttypeOld", "book": "editBookOld", "brand": "editBrandOld",
    "condition": "editConditionOld", "estimateprice": "editEstimatepriceOld", "fixed": "editFixedOld",
    "provider": "editProviderOld",
}.items():
    fixture_id = "9001" if master == "statustype" else "1"
    AUTH_SCENARIOS.append({"id": f"master-{master}-edit", "ci3": f"/{ci3_route}/{fixture_id}", "ci4": f"/master/{master}/{fixture_id}"})


def command(*args: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, text=True, capture_output=True, check=False)


def sha256(path: pathlib.Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


class TokenInputs(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.values: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        values = dict(attrs)
        name = values.get("name", "") or ""
        value = values.get("value", "") or ""
        if tag == "input" and value and any(marker in name.lower() for marker in ("csrf", "token")):
            self.values.append(value)


def redact_response(body: pathlib.Path, headers: pathlib.Path) -> list[str]:
    redactions: list[str] = []
    text = body.read_text(errors="replace")
    parser = TokenInputs()
    parser.feed(text)
    for value in parser.values:
        text = text.replace(value, "__REDACTED_TOKEN__")
        redactions.append("HTML token input value")
    body.write_text(text)
    header_lines = []
    for line in headers.read_text(errors="replace").splitlines(keepends=True):
        if line.lower().startswith("set-cookie:"):
            ending = "\r\n" if line.endswith("\r\n") else "\n"
            header_lines.append("Set-Cookie: __REDACTED__" + ending)
            redactions.append("Set-Cookie header")
        else:
            header_lines.append(line)
    headers.write_text("".join(header_lines))
    return sorted(set(redactions))


def curl(
    side: str, base: str, route: str, request_id: str, directory: pathlib.Path,
    cookie_jar: pathlib.Path | None = None, method: str = "GET", data: dict[str, str] | None = None,
) -> dict[str, object]:
    body = directory / f"{side}.html"
    headers = directory / f"{side}.headers"
    arguments = [
        "curl", "--silent", "--show-error", "--location", "--max-redirs", "5",
        "--header", f"X-Parity-Request-ID: {request_id}",
        "--dump-header", str(headers), "--output", str(body),
        "--write-out", "%{http_code}\n%{content_type}\n%{url_effective}\n",
    ]
    if cookie_jar is not None:
        arguments.extend(["--cookie", str(cookie_jar), "--cookie-jar", str(cookie_jar)])
    if method == "POST":
        arguments.extend(["--request", "POST", "--header", "Content-Type: application/x-www-form-urlencoded", "--data-binary", urlencode(data or {})])
    completed = command(*arguments, base + route)
    redactions = redact_response(body, headers)
    lines = completed.stdout.splitlines()
    return {
        "exit_code": completed.returncode,
        "status": int(lines[0]) if lines and lines[0].isdigit() else 0,
        "content_type": lines[1] if len(lines) > 1 else "",
        "final_url": lines[2] if len(lines) > 2 else "",
        "body": str(body.relative_to(ROOT)), "body_sha256": sha256(body),
        "headers": str(headers.relative_to(ROOT)), "headers_sha256": sha256(headers),
        "redactions": redactions,
        "stderr": completed.stderr.strip(),
    }


def authenticate(base: str, username: str, password: str, cookie_jar: pathlib.Path) -> None:
    with tempfile.TemporaryDirectory(prefix="parity-login-") as directory:
        form = pathlib.Path(directory) / "form.html"
        get = command("curl", "--silent", "--show-error", "--cookie-jar", str(cookie_jar), "--output", str(form), base + "/login")
        if get.returncode:
            raise RuntimeError("login form request failed")
        parser = TokenInputs()
        parser.feed(form.read_text(errors="replace"))
        payload = {"username": username, "password": password}
        if parser.values:
            payload["csrf_test_name"] = parser.values[0]
        post = subprocess.run(
            ["curl", "--silent", "--show-error", "--location", "--cookie", str(cookie_jar), "--cookie-jar", str(cookie_jar),
             "--header", "Content-Type: application/x-www-form-urlencoded", "--data-binary", "@-", "--output", os.devnull,
             "--write-out", "%{url_effective}", base + "/loginMe"],
            input=urlencode(payload), text=True, capture_output=True, check=False,
        )
        if post.returncode or "/dashboard" not in post.stdout:
            raise RuntimeError(f"synthetic login failed for {base}")


def bootstrap_session(base: str, side: str, cookie_jar: pathlib.Path) -> None:
    route = "/login?parity_session=admin" if side == "ci3" else "/__parity/session/admin"
    result = command(
        "curl", "--silent", "--show-error", "--location",
        "--cookie", str(cookie_jar), "--cookie-jar", str(cookie_jar),
        "--output", os.devnull, "--write-out", "%{url_effective}", base + route,
    )
    if result.returncode or "/dashboard" not in result.stdout:
        raise RuntimeError(f"supported parity session bootstrap failed for {side}")


def container_traces(container: str, path: str) -> list[dict[str, object]]:
    completed = command("docker", "exec", container, "sh", "-c", f"test ! -f {path} || cat {path}")
    if completed.returncode:
        raise RuntimeError(completed.stderr.strip())
    return [json.loads(line) for line in completed.stdout.splitlines() if line.strip()]


def compare_dom(scenario_id: str, directory: pathlib.Path) -> dict[str, object]:
    output = directory / "dom-result.json"
    completed = command(
        "php", str(ROOT / "scripts/compare-runtime-dom.php"),
        "--left", str(directory / "ci3.html"), "--right", str(directory / "ci4.html"),
        "--page", scenario_id,
    )
    if completed.stdout.strip():
        output.write_text(completed.stdout)
    result = json.loads(completed.stdout) if completed.stdout.strip() else {
        "status": "BLOCKED", "reason": completed.stderr.strip() or "DOM comparator produced no result",
    }
    result["exit_code"] = completed.returncode
    result["evidence"] = str(output.relative_to(ROOT)) if output.is_file() else None
    return result


def main() -> int:
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument("--authenticated", action="store_true")
    args = parser.parse_args()
    run_id = str(uuid.uuid4())
    root = ROOT / "evidence/runtime-comparison" / run_id
    root.mkdir(parents=True, exist_ok=True)
    scenarios = list(SCENARIOS)
    cookie_jars: dict[str, pathlib.Path] = {}
    temp_cookies: tempfile.TemporaryDirectory | None = None
    if args.authenticated:
        temp_cookies = tempfile.TemporaryDirectory(prefix="parity-cookies-")
        cookie_root = pathlib.Path(temp_cookies.name)
        cookie_jars = {side: cookie_root / f"{side}.cookies" for side in ("ci3", "ci4")}
        bootstrap_session(CI3_BASE, "ci3", cookie_jars["ci3"])
        bootstrap_session(CI4_BASE, "ci4", cookie_jars["ci4"])
        scenarios.extend({**scenario, "role": "admin", "language": "en", "state": "fixture"} for scenario in AUTH_SCENARIOS)
    probes = []
    for scenario in scenarios:
        directory = root / scenario["id"]
        directory.mkdir()
        scenario_cookies = cookie_jars if scenario.get("role") == "admin" else {}
        ci3_request_id = f"{run_id[:8]}_ci3_{scenario['id'].replace('-', '_')}"
        ci4_request_id = f"{run_id[:8]}_ci4_{scenario['id'].replace('-', '_')}"
        probes.append({
            **scenario,
            "ci3_request_id": ci3_request_id, "ci4_request_id": ci4_request_id,
            "ci3_http": curl("ci3", CI3_BASE, scenario["ci3"], ci3_request_id, directory, scenario_cookies.get("ci3"), scenario.get("ci3_method", "GET"), scenario.get("ci3_data")),
            "ci4_http": curl("ci4", CI4_BASE, scenario["ci4"], ci4_request_id, directory, scenario_cookies.get("ci4"), scenario.get("ci4_method", "GET"), scenario.get("ci4_data")),
            "dom": compare_dom(scenario["id"], directory),
        })
    if temp_cookies is not None:
        temp_cookies.cleanup()

    ci3_traces = container_traces("samsonitetracking-ci4-migration-web-1", "/tmp/ci3-parity-template-trace.jsonl")
    ci4_traces = container_traces("samsonitetracking-ci4-migration-ci4-1", "/app/writable/parity-template-trace.jsonl")
    ci3_by_id: dict[str, list[dict[str, object]]] = {}
    ci4_by_id: dict[str, list[dict[str, object]]] = {}
    for record in ci3_traces:
        ci3_by_id.setdefault(str(record.get("request_id")), []).append(record)
    for record in ci4_traces:
        ci4_by_id.setdefault(str(record.get("request_id")), []).append(record)

    by_source: dict[str, dict[str, object]] = {}
    for probe in probes:
        source_trace = ci3_by_id.get(str(probe["ci3_request_id"]), [])
        target_trace = ci4_by_id.get(str(probe["ci4_request_id"]), [])
        source_templates = sorted({str(name) for row in source_trace for name in row.get("templates", [])})
        target_templates = sorted({str(name) for row in target_trace for name in row.get("templates", [])})
        probe["ci3_trace"] = source_trace
        probe["ci4_trace"] = target_trace
        for relative in source_templates:
            source = "application/views/" + relative
            if not relative.endswith((".php", ".html")):
                continue
            record = by_source.setdefault(source, {
                "source_template": source,
                "target_template": "app/Views/ci3/" + relative,
                "source_caller": [], "target_caller": [], "workflow": [], "scenario_ids": [],
                "source_template_trace": [], "target_template_trace": [], "fixture_identifiers": [],
                "source_final_url": [], "target_final_url": [], "source_http_status": [], "target_http_status": [],
                "source_content_type": [], "target_content_type": [], "raw_evidence_paths": [],
                "dom_results": [],
                "render_mode": "actual_route", "role": probe["role"], "branch": None,
                "language": probe["language"], "state": probe["state"],
            })
            record["source_caller"].append(f"GET {probe['ci3']} request_id={probe['ci3_request_id']}")
            record["target_caller"].append(f"GET {probe['ci4']} request_id={probe['ci4_request_id']}")
            record["workflow"].append(f"GET {probe['ci3']} → GET {probe['ci4']}")
            record["scenario_ids"].append(probe["id"])
            record["fixture_identifiers"].append(f"state:{probe['state']}")
            record["dom_results"].append({
                "scenario_id": probe["id"], "status": probe["dom"].get("status"),
                "difference_count": probe["dom"].get("difference_count"),
                "differences": probe["dom"].get("differences", []), "evidence": probe["dom"].get("evidence"),
            })
            record["source_template_trace"] = sorted(set(record["source_template_trace"]) | set(source_templates))
            record["target_template_trace"] = sorted(set(record["target_template_trace"]) | set(target_templates))
            for key, side in (("source", "ci3"), ("target", "ci4")):
                http = probe[f"{side}_http"]
                record[f"{key}_final_url"].append(http["final_url"])
                record[f"{key}_http_status"].append(http["status"])
                record[f"{key}_content_type"].append(http["content_type"])
                record["raw_evidence_paths"].extend([http["body"], http["headers"]])
            if probe["dom"].get("evidence"):
                record["raw_evidence_paths"].append(probe["dom"]["evidence"])

    manifest = {
        "schema_version": 2, "run_id": run_id, "timestamp": datetime.now(timezone.utc).isoformat(),
        "ci3_pin": PIN, "records": list(by_source.values()), "probes": probes,
    }
    trace_path = root / "runtime-traces.json"
    trace_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps({
        "run_id": run_id, "scenarios": len(probes), "source_templates_traced": len(by_source),
        "trace_manifest": str(trace_path.relative_to(ROOT)),
        "http_failures": [probe["id"] for probe in probes if probe["ci3_http"]["exit_code"] or probe["ci4_http"]["exit_code"]],
    }, ensure_ascii=False))
    return 1 if any(probe["ci3_http"]["exit_code"] or probe["ci4_http"]["exit_code"] for probe in probes) else 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as error:
        print(f"FAIL {error}", file=sys.stderr)
        raise SystemExit(2)
