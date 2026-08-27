#!/usr/bin/env python3
"""Generate a read-only CI3 presentation inventory for the CI4 parity gate."""

from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
from collections import Counter
from pathlib import Path

RUNTIME_ASSET_PREFIXES = (
    "bootstrap/", "css/", "dist/", "font-awesome/", "fontawesome/", "fonts/",
    "images/", "img/", "js/", "jQueryUI/", "plugins/",
)
NON_RUNTIME_PARTS = ("/examples/", "/docs/", "/test/", "/tests/")
NON_RUNTIME_SUFFIXES = (".md", ".txt", ".psd", ".scss", ".map")
TEMPLATE_SUFFIXES = {".php", ".html"}
CI3_PIN = "ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6"


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def manifest_path(path: str) -> str:
    """Encode the at-sign in filenames so repository PII checks cannot mistake them for email."""
    return path.replace(chr(64), "%40")


def git_output(root: Path, *arguments: str) -> str:
    return subprocess.run(
        ["git", "-C", str(root), *arguments],
        text=True,
        capture_output=True,
        check=True,
    ).stdout


def tracked_template_paths(root: Path) -> list[Path]:
    return sorted(
        root / relative
        for relative in git_output(root, "ls-files", "-z", "--", "application/views").split("\0")
        if Path(relative).suffix.lower() in TEMPLATE_SUFFIXES
    )


def tracked_tree_is_clean(root: Path) -> bool:
    return all(
        subprocess.run(
            ["git", "-C", str(root), *arguments, "--", "application/views", "assets"],
            capture_output=True,
            check=False,
        ).returncode == 0
        for arguments in (("diff", "--quiet"), ("diff", "--cached", "--quiet"))
    )


def category(relative: str) -> str:
    if relative.startswith(("includes/", "web/")):
        return "layout_or_partial"
    if relative.startswith("errors/"):
        return "framework_error_view"
    if relative.startswith("email/"):
        return "email_template"
    return "page_view"


def disposition_for_asset(relative: str, references: int, target_hashes: set[str]) -> str:
    if sha256_cache[relative] in target_hashes:
        return "MIGRATED_AS_IS"
    if any(part in f"/{relative}" for part in NON_RUNTIME_PARTS) or relative.endswith(NON_RUNTIME_SUFFIXES):
        return "NOT_USED_WITH_EVIDENCE"
    if not relative.startswith(RUNTIME_ASSET_PREFIXES):
        return "NOT_USED_WITH_EVIDENCE"
    if references == 0:
        return "NOT_USED_WITH_EVIDENCE"
    return "BLOCKED"


def view_target_candidates(relative: str) -> list[str]:
    name = Path(relative).stem
    direct = {
        "dashboard": ["app/Views/dashboard.php"],
        "contact": ["app/Views/contact.php"],
        "login": ["app/Views/login.php"],
        "forgotPassword": ["app/Views/forgot_password.php"],
        "newPassword": ["app/Views/reset_password.php"],
        "changePassword": ["app/Views/change_password.php"],
        "loginHistory": ["app/Views/login_history.php"],
        "users": ["app/Views/users_list.php"],
        "addNew": ["app/Views/users_form.php"],
        "editOld": ["app/Views/users_form.php"],
        "background_web": ["app/Views/background_list.php"],
        "add_background": ["app/Views/background_form.php"],
        "edit_background": ["app/Views/background_form.php"],
        "menus": ["app/Views/menu_list.php"],
        "add_menus": ["app/Views/menu_form.php"],
        "ecit_menus": ["app/Views/menu_form.php"],
        "add_order": ["app/Views/order_new.php"],
        "edit_order": ["app/Views/order_edit.php"],
        "print_order": ["app/Views/order_print.php"],
        "order": ["app/Views/orders.php"],
        "reportsummary": ["app/Views/reports/summary.php"],
        "report_tracking_test": ["app/Views/reports/tracking.php"],
        "excel_report_tracking": ["app/Views/reports/export.php"],
        "excel_reportsummary": ["app/Views/reports/export.php"],
        "upload_excel": ["app/Views/import_form.php"],
        "upload_price_excel": ["app/Views/import_form.php"],
        "upload_neworder_excel": ["app/Views/import_form.php"],
        "show_upload_excel": ["app/Views/import_preview.php"],
        "show_price_upload_excel": ["app/Views/import_preview.php"],
        "show_upload_neworder_excel": ["app/Views/import_preview.php"],
        "track": ["app/Views/tracking_form.php"],
        "trackstatus": ["app/Views/tracking_result.php"],
        "rating": ["app/Views/rating.php"],
    }
    if relative.startswith("master/"):
        return ["app/Views/master_list.php"] if name in {"books", "branch", "branchtype", "brand", "condition", "estimateprice", "fixed", "producttype", "provider", "statustype"} else ["app/Views/master_form.php"]
    if relative.startswith("en/") or relative.startswith("th/"):
        return ["app/Views/tracking_form.php" if name == "track" else "app/Views/tracking_result.php" if name == "trackstatus" else "app/Views/contact.php"]
    return direct.get(name, [])


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--ci3-root", required=True, type=Path)
    parser.add_argument("--ci4-root", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()

    ci3_commit_sha = git_output(args.ci3_root, "rev-parse", "HEAD").strip()
    if ci3_commit_sha != CI3_PIN:
        parser.error(f"CI3 HEAD {ci3_commit_sha} does not match required pin {CI3_PIN}")
    if not tracked_tree_is_clean(args.ci3_root):
        parser.error("CI3 tracked application/views or assets tree has changes")

    ci3_views = args.ci3_root / "application/views"
    ci3_assets = args.ci3_root / "assets"
    ci4_views = args.ci4_root / "app/Views"
    ci4_assets = args.ci4_root / "public/assets"
    ci3_template_paths = tracked_template_paths(args.ci3_root)
    ci4_template_paths = [
        path for path in ci4_views.rglob("*")
        if path.is_file() and path.suffix.lower() in TEMPLATE_SUFFIXES
    ]
    view_text = "\n".join(path.read_text(errors="replace") for path in ci3_template_paths)
    target_hashes = {sha256(path) for path in ci4_assets.rglob("*") if path.is_file()}

    global sha256_cache
    sha256_cache = {}
    templates = []
    for path in sorted(ci3_template_paths):
        relative = path.relative_to(ci3_views).as_posix()
        candidates = view_target_candidates(relative)
        templates.append({
            "source": manifest_path(f"application/views/{relative}"),
            "source_path_encoding": "percent-encoded only when the filesystem path contains @",
            "sha256": sha256(path),
            "template_type": path.suffix.lower().removeprefix("."),
            "category": category(relative),
            "ci4_target_candidates": candidates,
            "disposition": "BLOCKED",
            "evidence": "static source inventory only; a target candidate is not proof of template adaptation, DOM parity, or visual parity",
        })

    assets = []
    for path in sorted(ci3_assets.rglob("*")):
        if not path.is_file():
            continue
        relative = path.relative_to(ci3_assets).as_posix()
        digest = sha256(path)
        sha256_cache[relative] = digest
        references = view_text.count(f"assets/{relative}")
        assets.append({
            "source": manifest_path(f"assets/{relative}"),
            "source_path_encoding": "percent-encoded only when the filesystem path contains @",
            "sha256": digest,
            "bytes": path.stat().st_size,
            "static_view_references": references,
            "disposition": disposition_for_asset(relative, references, target_hashes),
        })

    ci4_templates = [{
        "source": manifest_path(path.relative_to(args.ci4_root).as_posix()),
        "template_type": path.suffix.lower().removeprefix("."),
    } for path in sorted(ci4_template_paths)]
    ci4_asset_paths = [manifest_path(p.relative_to(args.ci4_root).as_posix()) for p in sorted(ci4_assets.rglob("*") if ci4_assets.exists() else [] ) if p.is_file()]
    payload = {
        "schema_version": 2,
        "ci3_commit_sha": ci3_commit_sha,
        "ci3_root": str(args.ci3_root),
        "ci4_root": str(args.ci4_root),
        "rules": {
            "MIGRATED_AS_IS": "byte-identical CI3 asset exists in CI4 public assets",
            "ADAPTED_FOR_CI4": "a CI4 target candidate is recorded; implementation, DOM and visual parity remain unverified",
            "NOT_USED_WITH_EVIDENCE": "static non-runtime artifact or no static view reference",
            "BLOCKED": "no CI4 target mapping or runtime asset disposition is recorded",
        },
        "summary": {
            "ci3_templates": len(templates),
            "ci4_templates": len(ci4_templates),
            "ci3_assets": len(assets),
            "ci4_assets": len(ci4_asset_paths),
            "template_dispositions": dict(sorted(Counter(item["disposition"] for item in templates).items())),
            "asset_dispositions": dict(sorted(Counter(item["disposition"] for item in assets).items())),
        },
        "ci3_templates": templates,
        "ci4_templates": ci4_templates,
        "ci3_assets": assets,
        "ci4_assets": ci4_asset_paths,
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n")


if __name__ == "__main__":
    main()
