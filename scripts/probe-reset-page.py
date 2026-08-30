#!/usr/bin/env python3
"""Exercise CI3/CI4 new-password pages with temporary synthetic reset rows."""

from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import quote

ROOT = Path(__file__).resolve().parents[1]
ACTIVATION = "PARITYRESET2026"
TOKEN = "d" * 64
REQUEST_ID = "parity-reset-page-2026"


def run(*args: str, input_text: str | None = None) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, input=input_text, text=True, capture_output=True, check=False)


def database(sql: str, ci4: bool = False) -> subprocess.CompletedProcess[str]:
    schema = "samsonite_ci4" if ci4 else '"$MARIADB_DATABASE"'
    return run(
        "docker", "exec", "-i", "samsonitetracking-ci4-migration-db-1", "sh", "-c",
        f'mariadb -N -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" {schema}', input_text=sql,
    )


class SensitiveInputs(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.values: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        values = dict(attrs)
        name, value = (values.get("name") or "").lower(), values.get("value") or ""
        if tag == "input" and value and name in {"email", "activation_code", "password", "cpassword", "csrf_test_name"}:
            self.values.append(value)


def redact(path: Path, known: list[str]) -> None:
    text = path.read_text(errors="replace")
    parser = SensitiveInputs()
    parser.feed(text)
    for value in known + parser.values:
        if value:
            text = text.replace(value, "__REDACTED_RESET_VALUE__")
    path.write_text(text)


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def traces(container: str, path: str, request_id: str) -> list[dict[str, object]]:
    result = run("docker", "exec", container, "sh", "-c", f"test ! -f {path} || cat {path}")
    return [row for line in result.stdout.splitlines() if line.strip() for row in [json.loads(line)] if row.get("request_id") == request_id]


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("trace_manifest", type=Path)
    args = parser.parse_args()
    payload = json.loads(args.trace_manifest.read_text())
    directory = args.trace_manifest.parent / "reset-page"
    directory.mkdir(exist_ok=True)
    email_result = database("SELECT email FROM tbl_users WHERE username='wp00c-admin' AND isDeleted=0 LIMIT 1;\n")
    email = email_result.stdout.strip()
    if email_result.returncode or not email:
        raise RuntimeError("synthetic CI3 reset user unavailable")
    escaped_email = email.replace("'", "''")
    token_hash = hashlib.sha256(TOKEN.encode()).hexdigest()
    seed_ci3 = database(
        f"DELETE FROM tbl_reset_password WHERE activation_id='{ACTIVATION}';\n"
        f"INSERT INTO tbl_reset_password (email, activation_id, createdDtm, agent, client_ip) VALUES ('{escaped_email}','{ACTIVATION}',NOW(),'Parity','127.0.0.1');\n"
    )
    seed_ci4 = database(
        f"DELETE FROM ci4_password_reset_tokens WHERE request_id='{REQUEST_ID}';\n"
        "INSERT INTO ci4_password_reset_tokens (user_id,purpose,request_id,token_hash,created_at,expires_at,consumed_at,revoked_at) "
        f"SELECT id,'password_reset','{REQUEST_ID}','{token_hash}',NOW(),DATE_ADD(NOW(), INTERVAL 30 MINUTE),NULL,NULL "
        "FROM ci4_users WHERE username='wp00c-admin' AND is_active=1 LIMIT 1;\n",
        ci4=True,
    )
    if seed_ci3.returncode or seed_ci4.returncode:
        raise RuntimeError("cannot seed synthetic reset rows")
    try:
        requests = []
        for side, url, request_id in (
            ("ci3", "http://127.0.0.1:18404/resetPasswordConfirmUser/" + ACTIVATION + "/" + quote(email, safe=""), f"reset_{payload['run_id'][:8]}_ci3"),
            ("ci4", "http://127.0.0.1:18405/reset-password?token=" + TOKEN, f"reset_{payload['run_id'][:8]}_ci4"),
        ):
            body, headers = directory / f"{side}.html", directory / f"{side}.headers"
            result = run(
                "curl", "--silent", "--show-error", "--header", f"X-Parity-Request-ID: {request_id}",
                "--dump-header", str(headers), "--output", str(body),
                "--write-out", "%{http_code}\n%{content_type}\n%{url_effective}\n", url,
            )
            lines = result.stdout.splitlines()
            redact(body, [email, ACTIVATION, TOKEN])
            headers.write_text("".join(
                "Set-Cookie: __REDACTED__\n" if line.lower().startswith("set-cookie:") else line.replace(TOKEN, "__REDACTED_RESET_VALUE__").replace(ACTIVATION, "__REDACTED_RESET_VALUE__")
                for line in headers.read_text(errors="replace").splitlines(keepends=True)
            ))
            requests.append({
                "side": side, "request_id": request_id, "status": int(lines[0]) if lines and lines[0].isdigit() else 0,
                "content_type": lines[1] if len(lines) > 1 else "", "final_url": "__REDACTED_RESET_URL__",
                "body": str(body.resolve().relative_to(ROOT)), "body_sha256": sha256(body),
                "headers": str(headers.resolve().relative_to(ROOT)), "headers_sha256": sha256(headers),
            })
        dom = run(
            "php", str(ROOT / "scripts/compare-runtime-dom.php"), "--left", str(directory / "ci3.html"),
            "--right", str(directory / "ci4.html"), "--page", "new-password",
        )
        dom_path = directory / "dom-result.json"
        dom_path.write_text(dom.stdout)
        dom_result = json.loads(dom.stdout)
        ci3_trace = traces("samsonitetracking-ci4-migration-web-1", "/tmp/ci3-parity-template-trace.jsonl", requests[0]["request_id"])
        ci4_trace = traces("samsonitetracking-ci4-migration-ci4-1", "/app/writable/parity-template-trace.jsonl", requests[1]["request_id"])
        for trace in ci3_trace + ci4_trace:
            trace["path"] = "__REDACTED_RESET_PATH__"
        source_templates = sorted({str(name) for row in ci3_trace for name in row.get("templates", [])})
        target_templates = sorted({str(name) for row in ci4_trace for name in row.get("templates", [])})
        record = {
            "source_template": "application/views/newPassword.php", "target_template": "app/Views/ci3/newPassword.php",
            "source_caller": ["GET resetPasswordConfirmUser/{activation}/{synthetic-email}"],
            "target_caller": ["GET reset-password?token={synthetic-token}"],
            "workflow": ["synthetic reset rows → production reset confirmation routes"],
            "scenario_ids": ["new-password-valid"], "source_template_trace": source_templates,
            "target_template_trace": target_templates, "fixture_identifiers": ["synthetic-reset-row"],
            "source_final_url": ["__REDACTED_RESET_URL__"], "target_final_url": ["__REDACTED_RESET_URL__"],
            "source_http_status": [requests[0]["status"]], "target_http_status": [requests[1]["status"]],
            "source_content_type": [requests[0]["content_type"]], "target_content_type": [requests[1]["content_type"]],
            "raw_evidence_paths": [requests[0]["body"], requests[0]["headers"], requests[1]["body"], requests[1]["headers"], str(dom_path.resolve().relative_to(ROOT))],
            "dom_results": [{"scenario_id": "new-password-valid", "status": dom_result.get("status"), "difference_count": dom_result.get("difference_count"), "differences": dom_result.get("differences", []), "evidence": str(dom_path.resolve().relative_to(ROOT))}],
            "render_mode": "actual_route", "role": "anonymous", "branch": None, "language": "en", "state": "valid-reset-token",
        }
        records = {row["source_template"]: row for row in payload["records"]}
        records[record["source_template"]] = record
        payload["records"] = sorted(records.values(), key=lambda row: row["source_template"])
        payload["reset_page_evidence"] = {"requests": requests, "ci3_trace": ci3_trace, "ci4_trace": ci4_trace}
        args.trace_manifest.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n")
        print(json.dumps({"source_trace": source_templates, "target_trace": target_templates, "dom": dom_result.get("status")}))
    finally:
        database(f"DELETE FROM tbl_reset_password WHERE activation_id='{ACTIVATION}';\n")
        database(f"DELETE FROM ci4_password_reset_tokens WHERE request_id='{REQUEST_ID}';\n", ci4=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
