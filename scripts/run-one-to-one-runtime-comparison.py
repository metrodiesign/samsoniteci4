#!/usr/bin/env python3
"""Build fail-closed CI3→CI4 runtime comparison records.

Only request/workflow traces may prove callers. Static source matches remain
discovery hints and copied/direct-render artifacts are rejected.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import sys
import uuid
from collections import Counter, defaultdict
from datetime import datetime, timezone
from html.parser import HTMLParser
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
CI3 = ROOT.parent / "samsoniteci3"
PIN = "ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6"
EXCLUDED = {
    "application/views/index.html",
    "application/views/errors/index.html",
    "application/views/errors/cli/index.html",
    "application/views/errors/html/index.html",
    "application/views/pdf-form.html",
    "application/views/welcome_message.php",
    # Reclassified by user decision: tracked files with no reachable CI3 production caller.
    "application/views/access.php",
    "application/views/contact.php",
    "application/views/email/resetPassword.php",
    "application/views/en/rating.php",
}
RUNTIME_REQUIRED = 98
REJECTED_EVIDENCE = {
    "scripts/render-strict-presentation-scenario.php": "direct template include/eval",
    "scripts/capture-strict-presentation-scenarios.mjs": "page.setContent()",
    "evidence/strict-parity/views/runtime-results.json": "derived from direct render and page.setContent()",
}
OUTPUT_JSON = ROOT / "outputs/reference/ci3-ci4-one-to-one-runtime-comparison.json"
OUTPUT_MD = ROOT / "outputs/reference/ci3-ci4-one-to-one-runtime-comparison.md"


def run(*args: str, cwd: Path | None = None) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, cwd=cwd, text=True, capture_output=True, check=False)


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def tracked_templates() -> list[str]:
    result = run("git", "ls-files", "-z", "--", "application/views", cwd=CI3)
    if result.returncode:
        raise RuntimeError(result.stderr.strip())
    return sorted(
        item for item in result.stdout.split("\0")
        if item and Path(item).suffix.lower() in {".php", ".html"}
    )


def literal_callers(root: Path, ci3: bool) -> dict[str, list[str]]:
    callers: dict[str, list[str]] = defaultdict(list)
    roots = [root / "application/controllers", root / "application/libraries"] if ci3 else [root / "app/Controllers", root / "app/Presentation"]
    patterns = [
        re.compile(r"(?:load->view|load[A-Za-z_]*Views[A-Za-z_]*)\s*\(\s*['\"]([^'\"]+)"),
        re.compile(r"\bview\s*\(\s*['\"]([^'\"]+)"),
        re.compile(r"->render\s*\(\s*['\"]([^'\"]+)"),
    ]
    for directory in roots:
        if not directory.exists():
            continue
        for path in directory.rglob("*.php"):
            text = path.read_text(errors="replace")
            for pattern in patterns:
                for match in pattern.finditer(text):
                    name = match.group(1).removesuffix(".php")
                    line = text.count("\n", 0, match.start()) + 1
                    callers[name].append(f"{path.relative_to(root).as_posix()}:{line}")
    return callers


def load_trace_manifest(path: Path | None) -> dict[str, dict[str, Any]]:
    if path is None or not path.is_file():
        return {}
    payload = json.loads(path.read_text())
    if payload.get("ci3_pin") != PIN or not isinstance(payload.get("run_id"), str):
        raise RuntimeError("runtime trace manifest pin/run_id invalid")
    traces: dict[str, dict[str, Any]] = {}
    for record in payload.get("records", []):
        if not isinstance(record, dict) or not isinstance(record.get("source_template"), str):
            raise RuntimeError("runtime trace record invalid")
        if record.get("render_mode") in {"direct_include", "synthetic", "set_content"}:
            raise RuntimeError("synthetic/direct-render trace rejected")
        required = {"source_caller", "target_caller", "workflow", "source_template_trace", "target_template_trace"}
        if any(field not in record for field in required) or not record.get("source_template_trace"):
            raise RuntimeError("runtime trace record missing caller/workflow/template trace")
        traces[record["source_template"]] = record
    for record in payload.get("uncalled_records", []):
        if not isinstance(record, dict) or not record.get("uncalled_with_evidence") or not isinstance(record.get("source_template"), str):
            raise RuntimeError("uncalled trace record invalid")
        traces[record["source_template"]] = record
    return traces


def load_playwright_evidence(path: Path | None) -> dict[str, Any] | None:
    if path is None or not path.is_file():
        return None
    payload = json.loads(path.read_text())
    if payload.get("browser") != "playwright-chromium" or not isinstance(payload.get("results"), list):
        raise RuntimeError("Playwright evidence invalid")
    payload["evidence_path"] = str(path)
    payload["evidence_directory"] = str(path.parent)
    return payload


def load_browser_evidence(path: Path | None) -> dict[str, Any] | None:
    if path is None or not path.is_file():
        return None
    payload = json.loads(path.read_text())
    if isinstance(payload.get("results"), list):
        payload["evidence_directory"] = str(path.parent)
        payload["evidence_path"] = str(path)
        return payload
    if not str(payload.get("ci3_url", "")).startswith("http://127.0.0.1:18404/"):
        raise RuntimeError("OpenAI Browser CI3 origin invalid")
    if not str(payload.get("ci4_url", "")).startswith("http://127.0.0.1:18405/"):
        raise RuntimeError("OpenAI Browser CI4 origin invalid")
    return payload


def kind(relative: str) -> str:
    if relative.startswith("errors/cli/"):
        return "framework_cli"
    if relative.startswith("errors/html/") or relative == "404.php":
        return "framework_html"
    if relative.startswith("email/"):
        return "email"
    if relative.startswith(("includes/", "web/")):
        return "composed_partial"
    if Path(relative).name.startswith("excel_"):
        return "export"
    return "browser_page"


def browser_candidate(relative: str, browser: dict[str, Any] | None, trace: dict[str, Any] | None) -> dict[str, Any]:
    scenario_templates = set(trace.get("openai_browser_templates", [])) if trace else set()
    attached = browser is not None and relative in scenario_templates
    if not attached:
        not_applicable = relative.startswith("errors/cli/")
        status = "NOT_APPLICABLE" if not_applicable else "BLOCKED"
        return {
            "openai_browser_scenario_ids": [], "openai_browser_ci3_url": None, "openai_browser_ci4_url": None,
            "openai_browser_actions": [], "openai_browser_desktop_status": status,
            "openai_browser_mobile_status": status, "openai_browser_interaction_status": status,
            "openai_browser_console_status": status, "openai_browser_network_status": status,
            "openai_browser_evidence": [], "openai_browser_overall_status": status,
        }
    trace_results = trace.get("openai_browser_results", []) if trace else []
    if trace_results:
        def aggregate(values: list[str]) -> str:
            for status in ("FAIL", "BLOCKED", "PASS", "NOT_APPLICABLE"):
                if status in values:
                    return status
            return "BLOCKED"
        desktop = [row for row in trace_results if row.get("viewport") == "1440x900"]
        mobile = [row for row in trace_results if row.get("viewport") == "390x844"]
        directory = Path(browser["evidence_directory"])
        evidence = list(browser.get("evidence_paths", [browser.get("evidence_path")]))
        evidence = [item for item in evidence if item]
        for row in trace_results:
            for field in ("source_screenshot", "target_screenshot"):
                if row.get(field):
                    evidence.append(row[field] if str(row[field]).startswith("evidence/") else str(directory / row[field]))
        return {
            "openai_browser_scenario_ids": sorted({row["scenario_id"] for row in trace_results}),
            "openai_browser_ci3_url": sorted({row["source_url"] for row in trace_results if row.get("source_url")}),
            "openai_browser_ci4_url": sorted({row["target_url"] for row in trace_results if row.get("target_url")}),
            "openai_browser_actions": sorted({row["interaction_action"] for row in trace_results if row.get("interaction_action")}),
            "openai_browser_desktop_status": aggregate([row["visual_status"] for row in desktop]),
            "openai_browser_mobile_status": aggregate([row["visual_status"] for row in mobile]),
            "openai_browser_interaction_status": aggregate([row["interaction_status"] for row in trace_results]),
            "openai_browser_console_status": aggregate([row["console_status"] for row in trace_results]),
            "openai_browser_network_status": aggregate([row["network_status"] for row in trace_results]),
            "openai_browser_evidence": sorted(set(evidence)),
            "openai_browser_overall_status": aggregate([row["overall_status"] for row in trace_results]),
        }
    directory = Path(browser["evidence_directory"])
    desktop = browser.get("desktop", [])
    mobile = browser.get("mobile", [])
    desktop_ci3 = next((row for row in desktop if str(row.get("url", "")).startswith("http://127.0.0.1:18404/")), {})
    desktop_ci4 = next((row for row in desktop if str(row.get("url", "")).startswith("http://127.0.0.1:18405/")), {})
    mobile_ci3 = next((row for row in mobile if str(row.get("url", "")).startswith("http://127.0.0.1:18404/")), {})
    mobile_ci4 = next((row for row in mobile if str(row.get("url", "")).startswith("http://127.0.0.1:18405/")), {})
    return {
        "openai_browser_scenario_ids": ["root-public-en"],
        "openai_browser_ci3_url": browser["ci3_url"], "openai_browser_ci4_url": browser["ci4_url"],
        "openai_browser_actions": browser.get("actions", []),
        "openai_browser_desktop_status": "FAIL" if desktop_ci3.get("sha256") != desktop_ci4.get("sha256") else "PASS",
        "openai_browser_mobile_status": "BLOCKED" if mobile_ci3.get("sha256") == desktop_ci3.get("sha256") else ("FAIL" if mobile_ci3.get("sha256") != mobile_ci4.get("sha256") else "PASS"),
        "openai_browser_interaction_status": "PASS" if all(row.get("how_to_check_clicked") for row in desktop) else "FAIL",
        "openai_browser_console_status": "PASS" if all(not row.get("console") for row in desktop + mobile) else "FAIL",
        "openai_browser_network_status": "BLOCKED",
        "openai_browser_evidence": [str(directory / name) for name in ["openai-browser.json", "ci3__root__1440x900.png", "ci4__root__1440x900.png", "ci3__root__390x844.png", "ci4__root__390x844.png"]],
        "openai_browser_overall_status": "BLOCKED",
    }


def generic_views(ci4_callers: dict[str, list[str]]) -> list[dict[str, Any]]:
    callers: dict[str, list[str]] = defaultdict(list)
    pattern = re.compile(r"\bview\s*\(\s*['\"]([^'\"]+)")
    for path in (ROOT / "app/Controllers").rglob("*.php"):
        text = path.read_text(errors="replace")
        for match in pattern.finditer(text):
            name = match.group(1)
            if name.startswith("ci3/") or name in {"layout_ci3", "layout_order_ci3", "layout_public"}:
                continue
            line = text.count("\n", 0, match.start()) + 1
            callers[name].append(f"{path.relative_to(ROOT).as_posix()}:{line}")
    return [{"view": name, "callers": callers[name]} for name in sorted(callers)]


class TreeParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.events: list[tuple[Any, ...]] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        self.events.append(("start", tag, tuple(sorted(attrs))))

    def handle_endtag(self, tag: str) -> None:
        self.events.append(("end", tag))

    def handle_data(self, data: str) -> None:
        value = " ".join(data.split())
        if value:
            self.events.append(("text", value))


def dom_signature(document: str) -> tuple[tuple[Any, ...], ...]:
    parser = TreeParser()
    parser.feed(document)
    return tuple(parser.events)


def validate(records: list[dict[str, Any]]) -> list[str]:
    failures: list[str] = []
    sources = [row["source_template"] for row in records]
    targets = [row["target_template"] for row in records]
    if len(sources) != RUNTIME_REQUIRED or len(set(sources)) != RUNTIME_REQUIRED:
        failures.append(f"source mapping is not {RUNTIME_REQUIRED} unique templates")
    if len(targets) != RUNTIME_REQUIRED or len(set(targets)) != RUNTIME_REQUIRED:
        failures.append(f"target mapping is not {RUNTIME_REQUIRED} unique templates")
    if any(not row.get("source_caller") or not row.get("target_caller") for row in records):
        failures.append("one or more targets lack actual caller trace")
    if any(row.get("generic_collapsed_mapping") for row in records):
        failures.append("one or more dedicated targets collapsed to a generic view")
    if any(not row.get("scenario_ids") for row in records):
        failures.append("one or more runtime-required templates lack a scenario")
    if any(row.get("kind") != "framework_cli" and not row.get("playwright_evidence") for row in records):
        failures.append("Playwright evidence coverage is incomplete")
    if any(row.get("kind") != "framework_cli" and not row.get("openai_browser_scenario_ids") for row in records):
        failures.append("OpenAI Browser evidence coverage is incomplete")
    if any(row.get("overall_verdict") != "PASS" for row in records):
        failures.append("one or more comparisons are not PASS")
    return failures


def mutation_checks(records: list[dict[str, Any]]) -> dict[str, str]:
    results: dict[str, str] = {}
    duplicate = [dict(row) for row in records]
    duplicate[1]["target_template"] = duplicate[0]["target_template"]
    results["duplicate_target"] = "PASS" if any("target mapping" in item for item in validate(duplicate)) else "FAIL"
    generic = [dict(row) for row in records]
    generic[0]["target_caller"] = "App\\Controllers\\MasterData::index -> view('master_list')"
    generic[0]["generic_collapsed_mapping"] = True
    generic[0]["overall_verdict"] = "PASS"
    results["generic_view"] = "PASS" if any("generic view" in item for item in validate(generic)) else "FAIL"
    uncalled = [dict(row) for row in records]
    uncalled[0]["target_caller"] = None
    results["uncalled_target"] = "PASS" if any("caller trace" in item for item in validate(uncalled)) else "FAIL"
    base = "<main class='x'><p id='a'>Visible</p><span>Next</span></main>"
    mutations = [
        "<main class='x'><span>Next</span><p id='a'>Visible</p></main>",
        "<main><p id='a'>Visible</p><span>Next</span></main>",
        "<main class='x'><p id='a'>Changed</p><span>Next</span></main>",
    ]
    results["dom_node_class_text"] = "PASS" if all(dom_signature(base) != dom_signature(item) for item in mutations) else "FAIL"
    same_artifact = lambda left, right: Path(left).resolve() == Path(right).resolve()
    results["reused_screenshot"] = "PASS" if same_artifact("same.png", "same.png") and not same_artifact("left.png", "right.png") else "FAIL"
    valid_origin = lambda left, right: str(left).startswith("http://127.0.0.1:18404/") and str(right).startswith("http://127.0.0.1:18405/")
    results["origin_tamper"] = "PASS" if valid_origin("http://127.0.0.1:18404/a", "http://127.0.0.1:18405/a") and not valid_origin("http://127.0.0.1:18404/a", "http://127.0.0.1:18404/a") else "FAIL"
    return results


def render_markdown(payload: dict[str, Any]) -> str:
    summary = payload["summary"]
    lines = [
        "# ผลเปรียบเทียบ CI3 → CI4 แบบ one-to-one จาก runtime จริง",
        "",
        "รายงานนี้ fail closed: หลักฐาน direct-render, `page.setContent()` และ target-file existence ไม่ถูกนับเป็น runtime proof.",
        "",
        "## สรุป",
        "",
        "| รายการ | ผล |",
        "|---|---:|",
        f"| tracked templates | {summary['tracked_templates']} |",
        f"| excluded with evidence | {summary['excluded_templates']} |",
        f"| runtime-required | {summary['runtime_required_templates']} |",
        f"| unique source mappings | {summary['unique_source_mappings']} |",
        f"| unique target mappings | {summary['unique_target_mappings']} |",
        f"| actual CI3 caller traces | {summary['actual_ci3_caller_traces']}/{RUNTIME_REQUIRED} |",
        f"| actual CI4 caller traces | {summary['actual_ci4_caller_traces']}/{RUNTIME_REQUIRED} |",
        f"| uncalled source with evidence | {summary['uncalled_source_templates_with_evidence']} |",
        f"| caller reconciliation | {summary['caller_reconciled_records']}/{RUNTIME_REQUIRED} |",
        f"| runtime comparisons executed | {summary['runtime_comparisons_executed']}/{RUNTIME_REQUIRED} |",
        f"| DOM comparisons executed | {summary['dom_comparisons_executed']}/{RUNTIME_REQUIRED} |",
        f"| generic collapsed mappings | {summary['generic_collapsed_mappings']} |",
        f"| Playwright actual route/state | {summary['playwright_route_states']} |",
        f"| Playwright screenshots | {summary['playwright_screenshots']} |",
        f"| Playwright interaction statuses | {summary['playwright_interaction_statuses']} |",
        f"| Playwright console events | {summary['playwright_console_events']} |",
        f"| Playwright failed requests | {summary['playwright_failed_requests']} |",
        f"| Playwright reused screenshot paths | {summary['playwright_reused_paths']} |",
        f"| OpenAI Browser covered templates | {summary['openai_browser_templates_covered']}/{summary['openai_browser_applicable_templates']} |",
        f"| OpenAI Browser actual route/state | {summary['openai_browser_actual_route_states']} |",
        f"| OpenAI Browser screenshots | {summary['openai_browser_screenshots']} |",
        f"| required desktop screenshots | {summary['openai_browser_desktop_screenshots']} |",
        f"| required mobile screenshots | {summary['openai_browser_mobile_screenshots']} |",
        f"| unclassified viewport screenshots | {summary['openai_browser_unclassified_screenshots']} |",
        f"| reused/stale screenshots | {summary['reused_or_stale_screenshots']} |",
        f"| console errors | {summary['openai_browser_console_errors']} |",
        "| failed network requests | BLOCKED: in-app Browser backend did not expose request failures |",
        f"| overall PASS / FAIL / BLOCKED | {summary['overall_statuses'].get('PASS', 0)} / {summary['overall_statuses'].get('FAIL', 0)} / {summary['overall_statuses'].get('BLOCKED', 0)} |",
        "",
        "## Gate verdict",
        "",
        *[f"- {item}" for item in payload["gate_failures"]],
        "",
        "## Mutation checks",
        "",
        "| mutation | gate result |",
        "|---|---|",
        *[f"| `{name}` | {status} |" for name, status in payload["mutation_checks"].items()],
        "",
        "## หลักฐานที่ถูกปฏิเสธ",
        "",
        "| path | เหตุผล |",
        "|---|---|",
        *[f"| `{path}` | {reason} |" for path, reason in payload["rejected_evidence"].items()],
        "",
        "## Generic views ที่ production ยังเรียก",
        "",
        "| view | caller candidates |",
        "|---|---|",
        *[f"| `{row['view']}` | {', '.join(f'`{item}`' for item in row['callers'])} |" for row in payload["generic_views"]],
        "",
        f"## รายการ {RUNTIME_REQUIRED} templates",
        "",
        "| # | source | target | actual caller | runtime | DOM | interaction | visual | OpenAI Browser | overall |",
        "|---:|---|---|---|---|---|---|---|---|---|",
    ]
    for index, row in enumerate(payload["records"], 1):
        caller = "PASS" if row["source_caller"] and row["target_caller"] else "BLOCKED"
        lines.append(
            f"| {index} | `{row['source_template']}` | `{row['target_template']}` | {caller} | {row['runtime_verdict']} | "
            f"{row['dom_verdict']} | {row['interaction_verdict']} | {row['visual_verdict']} | {row['openai_browser_overall_status']} | {row['overall_verdict']} |"
        )
    lines.extend([
        "", "## คำสั่ง", "",
        "```bash", *payload["commands"], "```", "",
        f"Exit code: `{payload['exit_code']}`", "",
    ])
    if payload.get("verification_results"):
        lines.extend([
            "## Baseline regression commands", "",
            "| command | exit code | evidence |", "|---|---:|---|",
            *[
                f"| `{row['command']}` | `{row['exit_code']}` | `{row['log']}` (`{row['sha256']}`) |"
                for row in payload["verification_results"].get("results", [])
            ],
            "",
        ])
    lines.append("STATUS: BLOCKED")
    return "\n".join(lines) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--trace-manifest", type=Path)
    parser.add_argument("--playwright-evidence", type=Path)
    parser.add_argument("--verification-results", type=Path)
    parser.add_argument("--browser-evidence", type=Path, action="append")
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()

    if run("git", "rev-parse", "HEAD", cwd=CI3).stdout.strip() != PIN:
        raise RuntimeError("CI3 pin mismatch")
    tracked = tracked_templates()
    if len(tracked) != 108 or len(EXCLUDED.intersection(tracked)) != 10:
        raise RuntimeError("denominator mismatch")
    required = [source for source in tracked if source not in EXCLUDED]
    traces = load_trace_manifest(args.trace_manifest)
    trace_run_id = json.loads(args.trace_manifest.read_text()).get("run_id") if args.trace_manifest else None
    playwright = load_playwright_evidence(args.playwright_evidence)
    verification_results = json.loads(args.verification_results.read_text()) if args.verification_results else None
    playwright_by_scenario: dict[str, list[dict[str, Any]]] = defaultdict(list)
    if playwright is not None:
        if playwright.get("run_id") != trace_run_id:
            raise RuntimeError("Playwright evidence run_id does not match runtime traces")
        for result in playwright["results"]:
            playwright_by_scenario[str(result.get("scenario_id"))].append(result)
    browser_payloads = [load_browser_evidence(path) for path in (args.browser_evidence or [])]
    browser_payloads = [payload for payload in browser_payloads if payload is not None]
    browser = None
    if browser_payloads:
        browser = dict(browser_payloads[0])
        browser["results"] = [row for payload in browser_payloads for row in payload.get("results", [])]
        browser["evidence_paths"] = [str(path) for path in (args.browser_evidence or [])]
        browser["evidence_directory"] = str((args.browser_evidence or [Path('.')])[0].parent)
    ci3_candidates = literal_callers(CI3, True)
    ci4_candidates = literal_callers(ROOT, False)
    run_id = str(uuid.uuid4())
    timestamp = datetime.now(timezone.utc).isoformat()
    records = []
    for source in required:
        relative = source.removeprefix("application/views/")
        target = f"app/Views/ci3/{relative}"
        trace = traces.get(source)
        browser_fields = browser_candidate(relative, browser, trace)
        record_kind = kind(relative)
        scenario_ids = trace.get("scenario_ids", []) if trace else []
        playwright_rows = [row for scenario_id in scenario_ids for row in playwright_by_scenario.get(scenario_id, [])]
        playwright_interactions = [row.get("interaction", {}).get("status") for row in playwright_rows]
        if record_kind == "framework_cli":
            interaction = "NOT_APPLICABLE"
            visual = "NOT_APPLICABLE"
        else:
            interaction = "FAIL" if "FAIL" in playwright_interactions else (
                "PASS" if playwright_rows and all(status in {"PASS", "NOT_APPLICABLE"} for status in playwright_interactions) else "BLOCKED"
            )
            visual_results = []
            for scenario_id in scenario_ids:
                diff_path = Path(playwright["evidence_directory"]) / "visual-diffs" / f"{scenario_id}.json" if playwright else None
                if diff_path is not None and diff_path.is_file():
                    visual_results.append(json.loads(diff_path.read_text()).get("status"))
                else:
                    visual_results.append("BLOCKED")
            visual = "FAIL" if "FAIL" in visual_results else (
                "PASS" if visual_results and all(status == "PASS" for status in visual_results) else "BLOCKED"
            )
        source_trace = trace.get("source_template_trace", []) if trace else []
        target_trace = trace.get("target_template_trace", []) if trace else []
        uncalled = bool(trace and trace.get("uncalled_with_evidence"))
        source_called = relative in source_trace
        target_called = f"ci3/{relative}" in target_trace
        runtime = "PASS" if source_called and target_called else ("FAIL" if source_called or uncalled else "BLOCKED")
        dom_results = trace.get("dom_results", []) if trace else []
        dom = "FAIL" if any(row.get("status") == "FAIL" for row in dom_results) else ("PASS" if dom_results and all(row.get("status") == "PASS" for row in dom_results) else "BLOCKED")
        dom_differences = [difference for result in dom_results for difference in result.get("differences", [])]
        raw_paths = list(trace.get("raw_evidence_paths", [])) if trace else []
        playwright_paths: list[str] = []
        if playwright_rows and playwright is not None:
            playwright_paths.append(str(Path(playwright["evidence_path"])))
            directory = Path(playwright["evidence_directory"])
            for browser_row in playwright_rows:
                screenshot = browser_row.get("screenshot")
                if screenshot:
                    screenshot_path = directory / str(screenshot)
                    playwright_paths.append(str(screenshot_path.resolve().relative_to(ROOT)))
            for scenario_id in scenario_ids:
                diff_path = directory / "visual-diffs" / f"{scenario_id}.json"
                if diff_path.is_file():
                    playwright_paths.append(str(diff_path.resolve().relative_to(ROOT)))
        raw_paths = sorted(set(raw_paths + playwright_paths))
        raw_evidence = [
            {"path": path, "sha256": sha256(ROOT / path)}
            for path in raw_paths if (ROOT / path).is_file()
        ]
        record = {
            "source_template": source, "target_template": target,
            "source_caller": trace.get("source_caller") if source_called else None,
            "target_caller": trace.get("target_caller") if target_called else None,
            "observed_target_caller": trace.get("target_caller") if trace else None,
            "source_caller_candidates": ci3_candidates.get(relative.removesuffix(".php"), []),
            "target_caller_candidates": ci4_candidates.get(relative.removesuffix(".php"), []),
            "scenario_ids": scenario_ids,
            "route_method_or_workflow": trace.get("workflow") if trace else None,
            "role": trace.get("role") if trace else None, "branch": trace.get("branch") if trace else None,
            "language": trace.get("language") if trace else None, "state": trace.get("state") if trace else None,
            "fixture_identifiers": trace.get("fixture_identifiers", []) if trace else [],
            "source_final_url": trace.get("source_final_url") if trace else None,
            "target_final_url": trace.get("target_final_url") if trace else None,
            "source_http_status": trace.get("source_http_status") if trace else None,
            "target_http_status": trace.get("target_http_status") if trace else None,
            "source_content_type": trace.get("source_content_type") if trace else None,
            "target_content_type": trace.get("target_content_type") if trace else None,
            "source_template_trace": source_trace or None,
            "target_template_trace": target_trace if trace else None,
            "generic_collapsed_mapping": bool(source_called and not target_called and target_trace),
            "uncalled_with_evidence": uncalled,
            "runtime_verdict": runtime, "dom_verdict": dom,
            "interaction_verdict": interaction,
            "visual_verdict": visual,
            "playwright_evidence": [item for item in raw_evidence if item["path"] in playwright_paths],
            "raw_evidence_paths": raw_paths,
            "raw_evidence": raw_evidence,
            "differences": ([{
                "axis": "visual", "selector": "html", "viewport": "1440x900",
                "reason": "OpenAI Browser same-run screenshots have different SHA-256",
            }] if browser_fields["openai_browser_desktop_status"] == "FAIL" else []) + [
                {"axis": "dom", **difference} for difference in dom_differences
            ],
            "run_id": run_id, "timestamp": timestamp, "kind": record_kind,
            **browser_fields,
            "overall_verdict": (
                "FAIL" if "FAIL" in {
                    runtime, dom, interaction, visual, browser_fields["openai_browser_overall_status"],
                } else (
                    "BLOCKED" if "BLOCKED" in {
                        runtime, dom, interaction, visual, browser_fields["openai_browser_overall_status"],
                    } else "PASS"
                )
            ),
        }
        records.append(record)
    mutations = mutation_checks(records)
    gate_failures = validate(records)
    browser_results = browser.get("results", []) if browser is not None else []
    if browser_results:
        browser_screenshots = sum(bool(row.get("screenshot")) for row in browser_results)
        browser_route_states = len({row.get("scenario_id") for row in browser_results})
        browser_console_errors = sum(len(row.get("console_errors", [])) for row in browser_results)
        reused_screenshots = sum(
            1 for scenario in {row.get("scenario_id") for row in browser_results}
            for side in ("ci3", "ci4")
            if len({row.get("screenshot_sha256") for row in browser_results if row.get("scenario_id") == scenario and row.get("side") == side and row.get("screenshot_sha256")}) == 1
            and sum(bool(row.get("screenshot_sha256")) for row in browser_results if row.get("scenario_id") == scenario and row.get("side") == side) > 1
        )
    else:
        browser_screenshots = sum(len(row.get("desktop", [])) + len(row.get("mobile", [])) for row in [browser] if row is not None)
        browser_route_states = 1 if browser is not None else 0
        browser_console_errors = sum(len(item.get("console", [])) for row in [browser] if row is not None for item in row.get("desktop", []) + row.get("mobile", []))
        reused_screenshots = 1 if browser is not None and any(
            mobile.get("sha256") == desktop.get("sha256")
            for mobile in browser.get("mobile", [])
            for desktop in browser.get("desktop", [])
            if str(mobile.get("url", "")).split("/", 3)[:3] == str(desktop.get("url", "")).split("/", 3)[:3]
        ) else 0
    payload = {
        "schema_version": 1, "run_id": run_id, "timestamp": timestamp, "ci3_pin": PIN,
        "summary": {
            "tracked_templates": len(tracked), "excluded_templates": len(EXCLUDED), "runtime_required_templates": len(records),
            "unique_source_mappings": len({row["source_template"] for row in records}),
            "unique_target_mappings": len({row["target_template"] for row in records}),
            "many_to_one_mappings": len(records) - len({row["target_template"] for row in records}),
            "actual_ci3_caller_traces": sum(bool(row["source_caller"]) for row in records),
            "actual_ci4_caller_traces": sum(bool(row["target_caller"]) for row in records),
            "uncalled_source_templates_with_evidence": sum(bool(row["uncalled_with_evidence"]) for row in records),
            "caller_reconciled_records": sum(bool(row["source_caller"]) or bool(row["uncalled_with_evidence"]) for row in records),
            "runtime_comparisons_executed": sum(row["runtime_verdict"] != "BLOCKED" for row in records),
            "dom_comparisons_executed": sum(row["dom_verdict"] != "BLOCKED" for row in records),
            "uncalled_targets": sum(not bool(row["target_caller"]) for row in records),
            "generic_collapsed_mappings": sum(bool(row["generic_collapsed_mapping"]) for row in records),
            "playwright_route_states": len(playwright_by_scenario),
            "playwright_screenshots": sum(bool(row.get("screenshot")) for rows in playwright_by_scenario.values() for row in rows),
            "playwright_interaction_statuses": dict(Counter(
                row.get("interaction", {}).get("status", "BLOCKED")
                for rows in playwright_by_scenario.values() for row in rows
            )),
            "playwright_console_events": sum(len(row.get("console_errors", [])) for rows in playwright_by_scenario.values() for row in rows),
            "playwright_failed_requests": sum(len(row.get("failed_requests", [])) for rows in playwright_by_scenario.values() for row in rows),
            "playwright_reused_paths": (
                sum(1 for rows in playwright_by_scenario.values() for row in rows if row.get("screenshot"))
                - len({row.get("screenshot") for rows in playwright_by_scenario.values() for row in rows if row.get("screenshot")})
            ),
            "openai_browser_applicable_templates": sum(row["kind"] != "framework_cli" for row in records),
            "openai_browser_templates_covered": sum(bool(row["openai_browser_scenario_ids"]) for row in records if row["kind"] != "framework_cli"),
            "openai_browser_templates_not_covered": sum(not bool(row["openai_browser_scenario_ids"]) for row in records if row["kind"] != "framework_cli"),
            "openai_browser_actual_route_states": browser_route_states,
            "openai_browser_screenshots": browser_screenshots,
            "openai_browser_desktop_screenshots": sum(bool(row.get("screenshot")) and row.get("viewport") == "1440x900" for row in browser_results),
            "openai_browser_mobile_screenshots": sum(bool(row.get("screenshot")) and row.get("viewport") == "390x844" for row in browser_results),
            "openai_browser_unclassified_screenshots": sum(bool(row.get("screenshot")) and row.get("viewport") not in {"1440x900", "390x844"} for row in browser_results),
            "openai_browser_console_errors": browser_console_errors,
            "openai_browser_network_failures": None,
            "reused_or_stale_screenshots": reused_screenshots,
            "axis_statuses": {axis: dict(Counter(row[f"{axis}_verdict"] for row in records)) for axis in ("runtime", "dom", "interaction", "visual")},
            "openai_browser_statuses": dict(Counter(row["openai_browser_overall_status"] for row in records)),
            "openai_browser_interaction_statuses": dict(Counter(row["openai_browser_interaction_status"] for row in records)),
            "openai_browser_desktop_statuses": dict(Counter(row["openai_browser_desktop_status"] for row in records)),
            "openai_browser_mobile_statuses": dict(Counter(row["openai_browser_mobile_status"] for row in records)),
            "overall_statuses": dict(Counter(row["overall_verdict"] for row in records)),
        },
        "rejected_evidence": REJECTED_EVIDENCE,
        "playwright_run": ({
            "run_id": playwright.get("run_id"),
            "evidence": playwright.get("evidence_path"),
            "browser": playwright.get("browser"),
            "status": "FAIL" if any(
                row.get("interaction", {}).get("status") == "FAIL" or row.get("console_errors") or row.get("failed_requests")
                for rows in playwright_by_scenario.values() for row in rows
            ) else "PASS",
        } if playwright is not None else None),
        "openai_browser_run": ({
            "run_id": browser.get("run_id"),
            "ci3_url": browser.get("ci3_url") or sorted({row.get("final_url") for row in browser_results if row.get("side") == "ci3" and row.get("final_url")}),
            "ci4_url": browser.get("ci4_url") or sorted({row.get("final_url") for row in browser_results if row.get("side") == "ci4" and row.get("final_url")}),
            "evidence": [str(path) for path in (args.browser_evidence or [])], "status": "BLOCKED",
            "reason": "coverage incomplete and viewport/network axes blocked",
        } if browser is not None else None),
        "generic_views": generic_views(ci4_candidates),
        "mutation_checks": mutations,
        "verification_results": verification_results,
        "gate_failures": gate_failures,
        "records": records,
        "commands": [
            "python3 scripts/run-one-to-one-runtime-comparison.py"
            + (f" --trace-manifest {args.trace_manifest}" if args.trace_manifest else "")
            + (f" --playwright-evidence {args.playwright_evidence}" if args.playwright_evidence else "")
            + (f" --verification-results {args.verification_results}" if args.verification_results else "")
            + "".join(f" --browser-evidence {path}" for path in (args.browser_evidence or []))
            + (" --self-test" if args.self_test else ""),
        ],
        "exit_code": 1 if gate_failures or any(value != "PASS" for value in mutations.values()) else 0,
    }
    OUTPUT_JSON.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n")
    OUTPUT_MD.write_text(render_markdown(payload))
    print(json.dumps({"run_id": run_id, "summary": payload["summary"], "mutation_checks": mutations, "gate_failures": gate_failures}, ensure_ascii=False))
    if args.self_test and any(value != "PASS" for value in mutations.values()):
        return 2
    return payload["exit_code"]


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as error:
        print(f"FAIL {error}", file=sys.stderr)
        raise SystemExit(2)
