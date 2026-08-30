#!/usr/bin/env python3
"""Compare the CI4 ratings export caller and response with the pinned CI3 runtime."""

from __future__ import annotations

import re
import subprocess
import sys
import tempfile
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlsplit

BASES = {"ci3": "http://127.0.0.1:18404", "ci4": "http://127.0.0.1:18405"}
BOOTS = {"ci3": "/login?parity_session=admin", "ci4": "/__parity/session/admin"}
EXPECTED_PATH = "/user/excel_ratings/30-07-2026/30-08-2026"
FILTERED_ALL_PATH = "/user/excel_ratings/0/30-07-2026/30-08-2026"
FILTERED_BRANCH_PATH = "/user/excel_ratings/1/30-07-2026/30-08-2026"


class ExportLinkParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__()
        self.hrefs: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        values = dict(attrs)
        href = values.get("href")
        if tag == "a" and href and "/user/excel_ratings" in href:
            self.hrefs.append(href)


def request(base: str, path: str, cookie: Path, body: Path, headers: Path) -> None:
    result = subprocess.run(
        [
            "curl", "--silent", "--show-error", "--location",
            "--cookie", str(cookie), "--cookie-jar", str(cookie),
            "--dump-header", str(headers), "--output", str(body),
            "--write-out", "%{http_code}", base + path,
        ],
        text=True,
        capture_output=True,
        check=False,
    )
    if result.returncode != 0 or result.stdout.strip() != "200":
        raise RuntimeError(f"GET {base + path} failed: curl={result.returncode} HTTP={result.stdout.strip()}")


def final_headers(path: Path) -> dict[str, str]:
    blocks = re.split(r"\r?\n\r?\n", path.read_text(errors="replace").strip())
    lines = next((block.splitlines() for block in reversed(blocks) if block.startswith("HTTP/")), [])
    result: dict[str, str] = {}
    for line in lines[1:]:
        if ":" in line:
            name, value = line.split(":", 1)
            result[name.lower()] = value.strip()
    return result


def main() -> int:
    failures: list[str] = []
    with tempfile.TemporaryDirectory(prefix="rating-export-parity-") as directory:
        root = Path(directory)
        cookies = {side: root / f"{side}.cookies" for side in BASES}
        for side, base in BASES.items():
            request(base, BOOTS[side], cookies[side], root / f"{side}-boot.body", root / f"{side}-boot.headers")

        report_paths: dict[str, list[str]] = {}
        for side, base in BASES.items():
            report_body = root / f"{side}-report.html"
            request(base, "/user/report", cookies[side], report_body, root / f"{side}-report.headers")
            parser = ExportLinkParser()
            parser.feed(report_body.read_text(errors="replace"))
            report_paths[side] = [urlsplit(href).path for href in parser.hrefs]
        if report_paths["ci4"] != [EXPECTED_PATH]:
            failures.append(
                f"CI4 report must use one slash: expected {[EXPECTED_PATH]!r}, got {report_paths['ci4']!r}"
            )

        export_cases = {
            "direct-url": {"ci3": EXPECTED_PATH, "ci4": EXPECTED_PATH},
            "filtered-all": {"ci3": FILTERED_ALL_PATH, "ci4": FILTERED_ALL_PATH},
            "filtered-branch": {"ci3": FILTERED_BRANCH_PATH, "ci4": FILTERED_BRANCH_PATH},
        }
        if len(report_paths["ci3"]) == 1 and len(report_paths["ci4"]) == 1:
            export_cases["report-caller"] = {
                "ci3": report_paths["ci3"][0],
                "ci4": report_paths["ci4"][0],
            }
        else:
            failures.append("cannot execute each report Export caller because its unique path was not proven")

        for case, paths in export_cases.items():
            for side, base in BASES.items():
                request(
                    base, paths[side], cookies[side],
                    root / f"{case}-{side}.xls", root / f"{case}-{side}.headers",
                )

            ci3_body = (root / f"{case}-ci3.xls").read_bytes()
            ci4_body = (root / f"{case}-ci4.xls").read_bytes()
            if ci4_body != ci3_body:
                failures.append(
                    f"{case} export bytes differ: CI3={len(ci3_body)}, CI4={len(ci4_body)}"
                )

            ci3_headers = final_headers(root / f"{case}-ci3.headers")
            ci4_headers = final_headers(root / f"{case}-ci4.headers")
            ci3_type = ci3_headers.get("content-type", "")
            ci4_type = ci4_headers.get("content-type", "")
            ci3_disposition = ci3_headers.get("content-disposition", "")
            ci4_disposition = ci4_headers.get("content-disposition", "")
            expected_type = re.fullmatch(r'application/x-msexcel; name="Rating_Report_\d+\.xls"', ci4_type)
            expected_disposition = re.fullmatch(r'inline; filename="Rating_Report_\d+\.xls"', ci4_disposition)
            if expected_type is None:
                failures.append(
                    f"{case} CI4 Content-Type does not match CI3 contract: "
                    f"{ci4_type!r} (CI3={ci3_type!r})"
                )
            if expected_disposition is None:
                failures.append(
                    f"{case} CI4 Content-Disposition does not match CI3 contract: "
                    f"{ci4_disposition!r} (CI3={ci3_disposition!r})"
                )
            type_name = re.search(r'Rating_Report_(\d+)\.xls', ci4_type)
            disposition_name = re.search(r'Rating_Report_(\d+)\.xls', ci4_disposition)
            if type_name and disposition_name and type_name.group(1) != disposition_name.group(1):
                failures.append(f"{case} CI4 Content-Type and Content-Disposition use different filenames")

    if failures:
        print("FAIL rating export parity")
        for failure in failures:
            print(f"- {failure}")
        return 1
    print("PASS rating export parity: caller path, response bytes, MIME type, and filename")
    return 0


if __name__ == "__main__":
    sys.exit(main())
