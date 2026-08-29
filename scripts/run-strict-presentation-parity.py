#!/usr/bin/env python3
"""Run every pinned CI3 presentation target in a caller-specific same-run scenario."""

from __future__ import annotations

import hashlib
import json
import pathlib
import re
import subprocess
import sys
import uuid
from datetime import datetime, timezone

ROOT = pathlib.Path(__file__).resolve().parents[1]
CI3 = ROOT.parent / "samsoniteci3"
PIN = "ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6"
INVENTORY = ROOT / "outputs/reference/2026-08-29_ci3-presentation-inventory_v6.json"
EVIDENCE = ROOT / "evidence/strict-parity/views"
RENDERER = ROOT / "scripts/render-strict-presentation-scenario.php"


def sha256(path: pathlib.Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def kind(template: str) -> str:
    if template.startswith("errors/cli/"):
        return "framework_cli"
    if template.startswith("errors/html/") or template == "404.php":
        return "framework_html"
    if template.startswith("email/"):
        return "email"
    if template.startswith(("includes/", "web/")):
        return "composed_partial"
    if pathlib.PurePosixPath(template).name.startswith("excel_"):
        return "export"
    return "browser_page"


def caller_index() -> dict[str, list[str]]:
    files = list((CI3 / "application/controllers").glob("*.php")) + [CI3 / "application/libraries/BaseController.php"]
    result: dict[str, list[str]] = {}
    for path in files:
        text = path.read_text(errors="replace")
        for match in re.finditer(r'(?:load[A-Za-z_]*Views[A-Za-z_]*|load->view)\s*\(\s*["\']([^"\']+)', text):
            name = match.group(1) + ".php"
            line = text.count("\n", 0, match.start()) + 1
            result.setdefault(name, []).append(f"{path.relative_to(CI3).as_posix()}:{line}")
    # Framework and composed callers are selected by CI3 itself rather than controllers.
    for name in ["includes/header.php", "includes/footer.php", "includes/header_order.php", "includes/footer_order.php"]:
        result.setdefault(name, []).append("application/libraries/BaseController.php:loadViews composition")
    for name in ["web/header.php", "web/header_th.php", "web/footer.php"]:
        result.setdefault(name, []).append("application/libraries/BaseController.php:public composition")
    for directory in ("errors/cli", "errors/html"):
        for path in (CI3 / "application/views" / directory).glob("*.php"):
            result.setdefault(f"{directory}/{path.name}", []).append("system/core/Exceptions.php:framework error renderer")
    result.setdefault("email/resetPassword.php", []).append("application/controllers/Login.php:reset delivery composition")
    return result


def run(command: list[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, text=True, capture_output=True, check=False)


def main() -> int:
    pin = run(["git", "-C", str(CI3), "rev-parse", "HEAD"]).stdout.strip()
    if pin != PIN:
        raise RuntimeError(f"CI3 pin mismatch: {pin}")
    payload = json.loads(INVENTORY.read_text())
    rows = [row for row in payload["ci3_templates"] if row["requirement"] == "RUNTIME_REQUIRED"]
    callers = caller_index()
    EVIDENCE.mkdir(parents=True, exist_ok=True)
    run_id = str(uuid.uuid4())
    started_at = datetime.now(timezone.utc).isoformat()
    scenarios = []
    failures = []
    for row in rows:
        template = row["source"].removeprefix("application/views/").replace("%40", "@")
        scenario_id = template.removesuffix(".php").replace("/", "__")
        directory = EVIDENCE / scenario_id
        directory.mkdir(parents=True, exist_ok=True)
        left = directory / "ci3.out"
        right = directory / "ci4.out"
        render_results = {}
        for target, output in (("ci3", left), ("ci4", right)):
            completed = run(["php", str(RENDERER), target, template, str(output)])
            render_results[target] = {"exit_code": completed.returncode, "stderr": completed.stderr.strip()}
        status = "PASS"
        reason = "same-run caller fixture rendered byte-identical output"
        if any(value["exit_code"] != 0 for value in render_results.values()):
            status, reason = "FAIL", "one or both dedicated runtimes failed"
        elif left.read_bytes() != right.read_bytes():
            status, reason = "FAIL", "runtime outputs differ"
        scenario = {
            "id": scenario_id,
            "source": row["source"],
            "target": row["ci4_target"],
            "kind": kind(template),
            "callers": callers.get(template, ["caller reconciliation: implicit CI3 runtime"]),
            "same_run": True,
            "run_id": run_id,
            "runtime": status,
            "dom": status,
            "interaction": "PENDING" if status == "PASS" else "FAIL",
            "visual": "PENDING" if status == "PASS" else "FAIL",
            "reason": reason,
            "outputs": {"ci3": str(left.relative_to(ROOT)), "ci4": str(right.relative_to(ROOT))},
            "render": render_results,
        }
        scenarios.append(scenario)
        if status != "PASS":
            failures.append(scenario_id)
    manifest = {
        "schema_version": 1, "run_id": run_id, "started_at": started_at,
        "finished_at": datetime.now(timezone.utc).isoformat(),
        "ci3_pin": PIN, "scenario_count": len(scenarios), "scenarios": scenarios,
    }
    (EVIDENCE / "runtime-results.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps({"scenarios": len(scenarios), "runtime_pass": len(scenarios) - len(failures), "failures": failures}, ensure_ascii=False))
    return 1 if failures else 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as error:
        print(f"FAIL {error}", file=sys.stderr)
        raise SystemExit(2)
