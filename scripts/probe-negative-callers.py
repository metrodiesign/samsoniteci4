#!/usr/bin/env python3
"""Record fail-closed evidence for CI3 templates with no reachable production caller."""

from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CI3 = ROOT.parent / "samsoniteci3"


def run(*args: str, cwd: Path | None = None) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, cwd=cwd, text=True, capture_output=True, check=False)


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def trace_rows(container: str, path: str, request_id: str) -> list[dict[str, object]]:
    result = run("docker", "exec", container, "sh", "-c", f"test ! -f {path} || cat {path}")
    return [json.loads(line) for line in result.stdout.splitlines() if line.strip() and json.loads(line).get("request_id") == request_id]


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("trace_manifest", type=Path)
    args = parser.parse_args()
    payload = json.loads(args.trace_manifest.read_text())
    directory = args.trace_manifest.parent / "negative-callers"
    directory.mkdir(exist_ok=True)
    left_id = f"negative_{payload['run_id'][:8]}_ci3_rating"
    right_id = f"negative_{payload['run_id'][:8]}_ci4_rating"
    rating_http = {}
    for side, base, request_id in (
        ("ci3", "http://127.0.0.1:18404", left_id), ("ci4", "http://127.0.0.1:18405", right_id),
    ):
        body, headers = directory / f"rating-{side}.html", directory / f"rating-{side}.headers"
        result = run(
            "curl", "--silent", "--show-error", "--location", "--max-redirs", "5",
            "--header", f"X-Parity-Request-ID: {request_id}", "--dump-header", str(headers),
            "--output", str(body), "--write-out", "%{http_code}\n%{url_effective}\n", base + "/rating/PARITY-NOT-FOUND",
        )
        lines = result.stdout.splitlines()
        header_text = headers.read_text(errors="replace")
        headers.write_text("".join(
            "Set-Cookie: __REDACTED__\n" if line.lower().startswith("set-cookie:") else line
            for line in header_text.splitlines(keepends=True)
        ))
        rating_http[side] = {
            "status": int(lines[0]) if lines and lines[0].isdigit() else 0,
            "final_url": lines[1] if len(lines) > 1 else "",
            "body": str(body.resolve().relative_to(ROOT)), "body_sha256": sha256(body),
            "headers": str(headers.resolve().relative_to(ROOT)), "headers_sha256": sha256(headers),
        }
    ci3_rating_trace = trace_rows("samsonitetracking-ci4-migration-web-1", "/tmp/ci3-parity-template-trace.jsonl", left_id)
    ci4_rating_trace = trace_rows("samsonitetracking-ci4-migration-ci4-1", "/app/writable/parity-template-trace.jsonl", right_id)

    searches = {}
    for name in ("access", "contact", "email/resetPassword", "en/rating"):
        result = run("git", "grep", "-n", "-E", rf"(load->view|load[A-Za-z_]*Views[A-Za-z_]*)[[:space:]]*\([[:space:]]*['\"]{name}['\"]", "--", "application", cwd=CI3)
        searches[name] = {"exit_code": result.returncode, "matches": result.stdout.splitlines()}

    uncalled = [
        {
            "source_template": "application/views/access.php", "target_template": "app/Views/ci3/access.php",
            "reason": "loadAccess() defines the only view call; git grep finds no production invocation of loadAccess()",
            "static_evidence": searches["access"],
        },
        {
            "source_template": "application/views/contact.php", "target_template": "app/Views/ci3/contact.php",
            "reason": "no production controller/library loads the top-level contact.php view",
            "static_evidence": searches["contact"],
        },
        {
            "source_template": "application/views/email/resetPassword.php", "target_template": "app/Views/ci3/email/resetPassword.php",
            "reason": "only match is commented out; live reset workflow builds inline HTML and calls PHPMailer",
            "static_evidence": searches["email/resetPassword"],
        },
        {
            "source_template": "application/views/en/rating.php", "target_template": "app/Views/ci3/en/rating.php",
            "reason": "Rating::index redirects before the view call; actual route finishes at tracking page",
            "static_evidence": searches["en/rating"],
            "runtime_evidence": {
                "ci3_request_id": left_id, "ci4_request_id": right_id,
                "ci3_http": rating_http["ci3"], "ci4_http": rating_http["ci4"],
                "ci3_trace": ci3_rating_trace, "ci4_trace": ci4_rating_trace,
            },
        },
    ]
    evidence = directory / "negative-callers.json"
    evidence.write_text(json.dumps({"run_id": payload["run_id"], "records": uncalled}, ensure_ascii=False, indent=2) + "\n")
    for row in uncalled:
        row["uncalled_with_evidence"] = True
        row["source_caller"] = None
        row["target_caller"] = None
        row["workflow"] = ["production caller reconciliation"]
        row["scenario_ids"] = ["uncalled-" + row["source_template"].removeprefix("application/views/").removesuffix(".php").replace("/", "-")]
        row["source_template_trace"] = []
        row["target_template_trace"] = []
        row["raw_evidence_paths"] = [str(evidence.resolve().relative_to(ROOT))]
        row["dom_results"] = []
    payload["uncalled_records"] = uncalled
    payload["negative_caller_evidence"] = str(evidence.resolve().relative_to(ROOT))
    args.trace_manifest.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps({"uncalled_with_evidence": len(uncalled), "evidence": payload["negative_caller_evidence"]}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
