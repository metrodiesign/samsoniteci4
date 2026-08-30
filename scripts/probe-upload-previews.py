#!/usr/bin/env python3
"""Upload synthetic XLSX files through CI3/CI4 preview workflows."""

from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
import tempfile
import zipfile
from html import escape
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FLOWS = {
    "status": ("ExcelDataAdd", "tracking/show_upload_excel.php", ["WPA/100", "STATUS CUSTOMER", "0000000000", "22/08/2026", "SUCCESS", "20/08/2026", "250.00", "IN", "CMG-STATUS"]),
    "price": ("ExcelPriceDataAdd", "tracking/show_price_upload_excel.php", ["WPA/200", "PRICE CUSTOMER", "0000000000", "22/08/2026", "SUCCESS", "20/08/2026", "275.50", "IN", "CMG-PRICE"]),
    "new-order": ("ExcelNewOrderDataAdd", "tracking/show_upload_neworder_excel.php", ["WPA/PARITY", "NEW CUSTOMER", "0000000000", "22/08/2026", "SUCCESS", "20/08/2026", "300.00", "IN", "CMG-NEW"]),
}
LISTINGS = {"status": "UploadexcelListing", "price": "UploadexcelpriceListing", "new-order": "UploadneworderexcelListing"}
HEADERS = ["order_id", "customer_name", "telephone", "updated_at", "status", "repair_started_at", "repair_price", "warranty", "number_cmg"]


def run(*args: str, input_text: str | None = None) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, input=input_text, text=True, capture_output=True, check=False)


class Inputs(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.values: dict[str, str] = {}

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        values = dict(attrs)
        if tag == "input" and values.get("name") and values.get("value"):
            self.values[str(values["name"])] = str(values["value"])


def xlsx(path: Path, row: list[str]) -> None:
    rows = [HEADERS, row]
    sheet = ['<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>']
    for row_index, values in enumerate(rows, 1):
        sheet.append(f'<row r="{row_index}">')
        for column, value in enumerate(values):
            letter = chr(65 + column)
            sheet.append(f'<c r="{letter}{row_index}" t="inlineStr"><is><t>{escape(value)}</t></is></c>')
        sheet.append('</row>')
    sheet.append('</sheetData></worksheet>')
    with zipfile.ZipFile(path, "w") as archive:
        archive.writestr('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>')
        archive.writestr('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>')
        archive.writestr('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>')
        archive.writestr('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>')
        archive.writestr('xl/worksheets/sheet1.xml', ''.join(sheet))


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def bootstrap(base: str, side: str, jar: Path) -> None:
    url = base + ("/login?parity_session=branch" if side == "ci3" else "/__parity/session/branch")
    result = run("curl", "--silent", "--show-error", "--location", "--cookie", str(jar), "--cookie-jar", str(jar), "--output", "/dev/null", "--write-out", "%{url_effective}", url)
    if result.returncode or "/dashboard" not in result.stdout:
        raise RuntimeError(f"{side} session bootstrap failed")


def trace_rows(container: str, path: str, request_id: str) -> list[dict[str, object]]:
    result = run("docker", "exec", container, "sh", "-c", f"test ! -f {path} || cat {path}")
    return [row for line in result.stdout.splitlines() if line.strip() for row in [json.loads(line)] if row.get("request_id") == request_id]


def redact(path: Path) -> None:
    text = path.read_text(errors="replace")
    parser = Inputs()
    parser.feed(text)
    for name, value in parser.values.items():
        if any(marker in name.lower() for marker in ("csrf", "token", "batch", "confirm")):
            text = text.replace(value, "__REDACTED_RUNTIME_VALUE__")
    for marker in ("STATUS CUSTOMER", "PRICE CUSTOMER", "NEW CUSTOMER", "0000000000"):
        text = text.replace(marker, "__SYNTHETIC_FIXTURE__")
    path.write_text(text)


def database_cleanup(file_hashes: list[str]) -> None:
    values = ",".join("'" + value + "'" for value in file_hashes)
    sql = (
        "DELETE FROM ci4_import_rows WHERE batch_id IN (SELECT batch_id FROM ci4_import_batches WHERE file_sha256 IN (" + values + "));\n"
        "DELETE FROM ci4_import_batches WHERE file_sha256 IN (" + values + ");\n"
    )
    run("docker", "exec", "-i", "samsonitetracking-ci4-migration-db-1", "sh", "-c",
        'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" samsonite_ci4', input_text=sql)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("trace_manifest", type=Path)
    args = parser.parse_args()
    payload = json.loads(args.trace_manifest.read_text())
    directory = args.trace_manifest.parent / "upload-previews"
    directory.mkdir(exist_ok=True)
    fixture_directory = directory / "fixtures"
    fixture_directory.mkdir(exist_ok=True)
    records = {row["source_template"]: row for row in payload["records"]}
    file_hashes = []
    with tempfile.TemporaryDirectory(prefix="parity-upload-") as temporary:
        temp = Path(temporary)
        jars = {side: temp / f"{side}.cookies" for side in ("ci3", "ci4")}
        bootstrap("http://127.0.0.1:18404", "ci3", jars["ci3"])
        bootstrap("http://127.0.0.1:18405", "ci4", jars["ci4"])
        try:
            for kind, (endpoint, source_relative, row) in FLOWS.items():
                workbook = fixture_directory / f"{kind}.xlsx"
                xlsx(workbook, row)
                file_hashes.append(sha256(workbook))
                scenario_dir = directory / kind
                scenario_dir.mkdir(exist_ok=True)
                sides = {}
                for side, base in (("ci3", "http://127.0.0.1:18404"), ("ci4", "http://127.0.0.1:18405")):
                    request_id = f"upload_{payload['run_id'][:8]}_{side}_{kind.replace('-', '_')}"
                    csrf = ""
                    if side == "ci4":
                        listing = run("curl", "--silent", "--show-error", "--cookie", str(jars[side]), "--cookie-jar", str(jars[side]), base + "/" + LISTINGS[kind])
                        parser_inputs = Inputs()
                        parser_inputs.feed(listing.stdout)
                        csrf = parser_inputs.values.get("csrf_test_name", "")
                        if not csrf:
                            raise RuntimeError(f"CI4 CSRF field missing for {kind}")
                    body, headers = scenario_dir / f"{side}.html", scenario_dir / f"{side}.headers"
                    command = ["curl", "--silent", "--show-error", "--location", "--header", f"X-Parity-Request-ID: {request_id}",
                               "--cookie", str(jars[side]), "--cookie-jar", str(jars[side]), "--dump-header", str(headers),
                               "--output", str(body), "--write-out", "%{http_code}\n%{content_type}\n%{url_effective}\n",
                               "--form", f"file=@{workbook};filename={kind}.xlsx"]
                    if csrf:
                        command.extend(["--form", f"csrf_test_name={csrf}"])
                    result = run(*command, base + "/" + endpoint)
                    lines = result.stdout.splitlines()
                    redact(body)
                    headers.write_text("".join(
                        "Set-Cookie: __REDACTED__\n" if line.lower().startswith("set-cookie:") else line
                        for line in headers.read_text(errors="replace").splitlines(keepends=True)
                    ))
                    sides[side] = {
                        "request_id": request_id, "status": int(lines[0]) if lines and lines[0].isdigit() else 0,
                        "content_type": lines[1] if len(lines) > 1 else "", "final_url": lines[2] if len(lines) > 2 else "",
                        "body": str(body.resolve().relative_to(ROOT)), "body_sha256": sha256(body),
                        "headers": str(headers.resolve().relative_to(ROOT)), "headers_sha256": sha256(headers),
                    }
                dom = run("php", str(ROOT / "scripts/compare-runtime-dom.php"), "--left", str(scenario_dir / "ci3.html"), "--right", str(scenario_dir / "ci4.html"), "--page", "upload-preview-" + kind)
                dom_path = scenario_dir / "dom-result.json"
                dom_path.write_text(dom.stdout)
                dom_result = json.loads(dom.stdout)
                ci3_trace = trace_rows("samsonitetracking-ci4-migration-web-1", "/tmp/ci3-parity-template-trace.jsonl", sides["ci3"]["request_id"])
                ci4_trace = trace_rows("samsonitetracking-ci4-migration-ci4-1", "/app/writable/parity-template-trace.jsonl", sides["ci4"]["request_id"])
                source_templates = sorted({str(name) for trace in ci3_trace for name in trace.get("templates", [])})
                target_templates = sorted({str(name) for trace in ci4_trace for name in trace.get("templates", [])})
                source = "application/views/" + source_relative
                records[source] = {
                    "source_template": source, "target_template": "app/Views/ci3/" + source_relative,
                    "source_caller": ["POST /" + endpoint + " multipart synthetic XLSX"],
                    "target_caller": ["POST /" + endpoint + " multipart synthetic XLSX"],
                    "workflow": ["actual upload preview workflow"], "scenario_ids": ["upload-preview-" + kind],
                    "source_template_trace": source_templates, "target_template_trace": target_templates,
                    "fixture_identifiers": ["synthetic-xlsx:" + kind],
                    "source_final_url": [sides["ci3"]["final_url"]], "target_final_url": [sides["ci4"]["final_url"]],
                    "source_http_status": [sides["ci3"]["status"]], "target_http_status": [sides["ci4"]["status"]],
                    "source_content_type": [sides["ci3"]["content_type"]], "target_content_type": [sides["ci4"]["content_type"]],
                    "raw_evidence_paths": [sides["ci3"]["body"], sides["ci3"]["headers"], sides["ci4"]["body"], sides["ci4"]["headers"], str(dom_path.resolve().relative_to(ROOT))],
                    "dom_results": [{"scenario_id": "upload-preview-" + kind, "status": dom_result.get("status"), "difference_count": dom_result.get("difference_count"), "differences": dom_result.get("differences", []), "evidence": str(dom_path.resolve().relative_to(ROOT))}],
                    "render_mode": "actual_route", "role": "admin", "branch": None, "language": "en", "state": "preview",
                }
        finally:
            database_cleanup(file_hashes)
    payload["records"] = sorted(records.values(), key=lambda value: value["source_template"])
    payload["upload_preview_evidence"] = "evidence/runtime-comparison/" + payload["run_id"] + "/upload-previews"
    args.trace_manifest.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps({
        "records": len(payload["records"]),
        "preview_templates": [source for source in records if source.startswith("application/views/tracking/show_")],
    }))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
