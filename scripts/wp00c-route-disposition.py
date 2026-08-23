#!/usr/bin/env python3
"""Build deterministic CI3-to-CI4 route disposition records."""

from __future__ import annotations

import argparse
import json
import pathlib
import re
import subprocess


EXPECTED_PIN = "ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6"
ROUTE = re.compile(r"^\s*\$route\[['\"]([^'\"]+)['\"]\]\s*=\s*(['\"])(.*?)\2\s*;")
MASTER = {
    "branch": "branch",
    "branchtype": "branchtype",
    "statustype": "statustype",
    "producttype": "producttype",
    "book": "book",
    "brand": "brand",
    "condition": "condition",
    "estimateprice": "estimateprice",
    "fixed": "fixed",
    "provider": "provider",
}


def replacement(target: str) -> tuple[str, str, str]:
    parts = target.lower().split("/")
    controller = parts[0]
    method = parts[1] if len(parts) > 1 else "index"
    if controller == "track" and method == "rackstatus" or controller == "track_th" and method == "rackstatus":
        return "retired", "", "Legacy target is broken; public exact-ID tracking replaces it."
    if target == "track" or controller == "track":
        return "mapped", "/tracking", "CORRECT_AND_REBASELINE"
    if controller == "track_th":
        return "mapped", "/tracking-th", "CORRECT_AND_REBASELINE"
    if controller == "error":
        return "mapped", "HTTP 404", "CORRECT_AND_REBASELINE"
    if controller == "login":
        if method == "loginme":
            return "mapped", "/loginMe", "PRESERVE"
        if method in {"forgotpassword", "resetpassworduser", "resetpasswordconfirmuser", "createpassworduser"}:
            return "mapped", "/password-reset/request|complete", "REPLACE_AND_REBASELINE"
        return "mapped", "/login", "PRESERVE"
    if controller == "user":
        routes = {
            "index": "/dashboard", "logout": "/logout", "loadchangepass": "/change-password",
            "changepassword": "/change-password", "loginhistoy": "/users/{id}/history",
            "contactlisting": "/contact-list", "report": "/user/report",
            "report_job_byday": "/user/report_job_byday", "report_job_pending": "/user/report_job_pending",
            "report_total_job_pending": "/user/report_total_job_pending",
            "report_in_progress_average": "/user/report_in_progress_average",
            "report_in_progress_job": "/user/report_in_progress_job",
            "excel_ratings": "/user/excel_ratings", "excel_in_progress_job": "/user/excel_in_progress_job",
            "pagenotfound": "HTTP 404",
        }
        return "mapped", routes.get(method, "/users"), "CORRECT_AND_REBASELINE" if method == "pagenotfound" else "PRESERVE"
    if controller in MASTER:
        return "mapped", f"/master/{MASTER[controller]}", "CORRECT_AND_REBASELINE"
    if controller == "order":
        if method == "booklisting":
            return "mapped", "/master/book", "CORRECT_AND_REBASELINE"
        if method in {"reporttrackinglisting", "reporttrackinglistingtest"}:
            return "mapped", "/Order/ReportTrackingListing", "CONSOLIDATE"
        if method == "reportsummary":
            return "mapped", "/reportsummary", "CORRECT_AND_REBASELINE"
        if method in {"excel_report", "excel_report_sum"}:
            return "mapped", "/reports/{type}/export", "PRESERVE"
        if method in {"orders", "addneworders"}:
            return "mapped", "/orders/new", "PRESERVE"
        return "mapped", "/orders", "PRESERVE"
    if controller == "upload_excel":
        kind = "price" if "price" in method else "new-order" if "neworder" in method else "status"
        return "mapped", f"/imports/{kind}", "CORRECT_AND_REBASELINE"
    if controller in {"contact", "contact_th"}:
        return "mapped", "/contact-th" if controller.endswith("_th") else "/contact", "PRESERVE"
    if controller == "menu":
        return "mapped", "/menu", "CORRECT_AND_REBASELINE"
    if controller == "rating":
        return "mapped", "/rating/{trackID}", "CORRECT_AND_REBASELINE"
    if controller == "background_web":
        return "mapped", "/backgrounds", "CORRECT_AND_REBASELINE"
    raise ValueError(f"Unclassified target: {target}")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-root", type=pathlib.Path, required=True)
    args = parser.parse_args()
    root = args.source_root.resolve()
    pin = subprocess.check_output(["git", "-C", root, "rev-parse", "HEAD"], text=True).strip()
    dirty = subprocess.check_output(["git", "-C", root, "status", "--porcelain"], text=True).strip()
    if pin != EXPECTED_PIN or dirty:
        raise SystemExit(f"FAIL CI3 identity pin={pin} dirty={bool(dirty)}")
    records = []
    for line, source in enumerate((root / "application/config/routes.php").read_text(encoding="utf-8").splitlines(), 1):
        match = ROUTE.match(source)
        if not match:
            continue
        route, target = match.group(1), match.group(3)
        status, target_route, decision = replacement(target)
        records.append({
            "route": route, "target": target, "line": line, "status": status,
            "replacement": target_route, "decision": decision,
        })
    if len(records) != 178:
        raise SystemExit(f"FAIL route count={len(records)} expected=178")
    print(json.dumps({"ci3_commit": EXPECTED_PIN, "routes": records}, ensure_ascii=False, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
