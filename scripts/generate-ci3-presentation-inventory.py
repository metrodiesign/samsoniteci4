#!/usr/bin/env python3
"""Generate a fail-closed inventory for the pinned CI3 presentation authority."""

from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
from collections import Counter, defaultdict
from pathlib import Path

CI3_PIN = "ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6"
TRACKED_TEMPLATE_DENOMINATOR = 108
VIEW_ROOT = "application/views"
PRESENTATION_ROOTS = (VIEW_ROOT, "front-update", "assets", "assets2", "cdn", "images")
ASSET_ROOTS = ("front-update", "assets", "assets2", "cdn", "images")
TEMPLATE_SUFFIXES = {".php", ".html"}
NON_RUNTIME_SUFFIXES = (".md", ".txt", ".psd", ".scss", ".map", ".less")
NON_RUNTIME_PARTS = ("/docs/", "/examples/", "/sample/", "/samples/", "/test/", "/tests/", "/demo/")

EXCLUSIONS = {
    "application/views/index.html": [
        "directory-index deny stub; git grep finds no controller load; app/Views is outside CI4 public document root",
    ],
    "application/views/errors/index.html": [
        "directory-index deny stub; no CI3 route/controller renders this file",
    ],
    "application/views/errors/cli/index.html": [
        "directory-index deny stub; CI3 exception renderer calls named error_*.php files, not index.html",
    ],
    "application/views/errors/html/index.html": [
        "directory-index deny stub; CI3 exception renderer calls named error_*.php files, not index.html",
    ],
    "application/views/pdf-form.html": [
        "standalone mockup has no CI3 controller caller; live print caller renders tracking/print_order.php",
    ],
    "application/views/welcome_message.php": [
        "caller: application/controllers/welcome.php::index",
        "route evidence: application/config/routes.php default_controller=track; ROUTE-IMPLICIT-001 retires the unapproved sample entry point in CI4",
    ],
}


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def manifest_path(path: str) -> str:
    return path.replace("@", "%40")


def git_output(root: Path, *arguments: str) -> str:
    return subprocess.run(
        ["git", "-C", str(root), *arguments],
        text=True,
        capture_output=True,
        check=True,
    ).stdout


def tracked_paths(root: Path, roots: tuple[str, ...]) -> list[Path]:
    return sorted(
        root / relative
        for relative in git_output(root, "ls-files", "-z", "--", *roots).split("\0")
        if relative
    )


def tracked_tree_is_clean(root: Path) -> bool:
    return all(
        subprocess.run(
            ["git", "-C", str(root), *arguments, "--", *PRESENTATION_ROOTS],
            capture_output=True,
            check=False,
        ).returncode == 0
        for arguments in (("diff", "--quiet"), ("diff", "--cached", "--quiet"))
    )


def category(source: str) -> str:
    if source.startswith(("application/views/includes/", "application/views/web/")):
        return "layout_or_partial"
    if source.startswith("application/views/errors/"):
        return "framework_error_view"
    if source.startswith("application/views/email/"):
        return "email_template"
    return "page_view"


def preferred_target(source: str) -> str:
    """Give every pinned CI3 template its own target; runtime adapters are verified separately."""
    relative = source.removeprefix("application/views/")
    return f"app/Views/ci3/{relative}"


def split_many_to_one(sources: list[str]) -> dict[str, str]:
    preferred = {source: preferred_target(source) for source in sources}
    grouped: dict[str, list[str]] = defaultdict(list)
    for source, target in preferred.items():
        grouped[target].append(source)
    for target, members in grouped.items():
        if len(members) > 1:
            for source in members:
                relative = source.removeprefix("application/views/")
                preferred[source] = f"app/Views/ci3/{relative}"
    if len(set(preferred.values())) != len(preferred):
        raise AssertionError("runtime CI3 views must have one-to-one CI4 targets")
    return preferred


def safe_evidence_file(ci4_root: Path, value: object) -> Path | None:
    if not isinstance(value, str) or not value.startswith("evidence/strict-parity/views/"):
        return None
    root = (ci4_root / "evidence/strict-parity/views").resolve()
    path = (ci4_root / value).resolve()
    if root not in path.parents or not path.is_file():
        return None
    return path


def verified_scenario_axes(scenario: dict[str, object], ci4_root: Path) -> set[str]:
    verified: set[str] = set()
    outputs = scenario.get("outputs")
    if not isinstance(outputs, dict):
        return verified
    left = safe_evidence_file(ci4_root, outputs.get("ci3"))
    right = safe_evidence_file(ci4_root, outputs.get("ci4"))
    if left is None or right is None or left.read_bytes() != right.read_bytes():
        return verified
    if scenario.get("runtime") == "PASS":
        verified.add("runtime")
    if scenario.get("dom") == "PASS":
        verified.add("dom")
    if scenario.get("interaction") == "PASS" and isinstance(scenario.get("interaction_evidence"), dict):
        verified.add("interaction")
    visual = scenario.get("visual_evidence")
    scenario_id = scenario.get("id")
    if scenario.get("visual") != "PASS" or not isinstance(visual, dict) or not isinstance(scenario_id, str):
        return verified
    directory = left.parent
    for viewport in ("1440x900", "390x844"):
        verdict = visual.get(viewport)
        if not isinstance(verdict, dict) or verdict.get("status") != "PASS":
            return verified
        images = [directory / f"{side}__{viewport}.png" for side in ("ci3", "ci4")]
        if not all(path.is_file() for path in images):
            return verified
        hashes = [sha256(path) for path in images]
        if hashes[0] != hashes[1] or hashes != [verdict.get("ci3_sha256"), verdict.get("ci4_sha256")]:
            return verified
    verified.add("visual")
    return verified


def implementation_status(source: str, source_hash: str, target: str, ci4_root: Path) -> tuple[str, str]:
    target_path = ci4_root / target
    if not target_path.is_file():
        return "BLOCKED", "one-to-one CI4 target does not exist"
    if sha256(target_path) == source_hash:
        return "MIGRATED_AS_IS", "target is byte-identical to the pinned CI3 source"
    marker = f"CI3 source: {source} @ {CI3_PIN}"
    if marker in target_path.read_text(errors="replace"):
        return "ADAPTED_FOR_CI4", "target carries a pinned CI3 provenance marker; runtime verification axes remain independent"
    return "BLOCKED", "target existence is not implementation proof; missing pinned CI3 provenance marker or byte identity"


def public_target(source: str) -> str:
    return f"public/{source}"


def asset_disposition(source: str, source_hash: str, references: int | None, ci4_root: Path) -> tuple[str, str]:
    target = ci4_root / public_target(source)
    if target.is_file() and sha256(target) == source_hash:
        return "MIGRATED_AS_IS", "byte-identical CI3 asset exists at the same public-relative path"
    glue = {
        "assets/css/main.css": "approved path-only local fallback; dependency and CSS rule remain CI3-derived",
        "assets/dist/css/AdminLTE.min.css": "approved URL-only local font mirror; AdminLTE version remains unchanged",
        "assets/dist/css/CustomAdmin.css": "approved URL-only local Font Awesome mirror; dependency version remains unchanged",
    }
    if source in glue and target.is_file():
        return "ADAPTED_FOR_CI4", glue[source]
    if any(part in f"/{source}" for part in NON_RUNTIME_PARTS) or source.endswith(NON_RUNTIME_SUFFIXES):
        return "NOT_USED_WITH_EVIDENCE", "package documentation/example/source artifact; no application-view runtime caller"
    if references == 0:
        return "NOT_USED_WITH_EVIDENCE", "no static reference in pinned views/controllers/config/CSS/JS closure"
    return "BLOCKED", "runtime-referenced asset is missing or differs without approved path glue"


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--ci3-root", required=True, type=Path)
    parser.add_argument("--ci4-root", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()

    ci3_sha = git_output(args.ci3_root, "rev-parse", "HEAD").strip()
    if ci3_sha != CI3_PIN:
        parser.error(f"CI3 HEAD {ci3_sha} does not match required pin {CI3_PIN}")
    if not tracked_tree_is_clean(args.ci3_root):
        parser.error("CI3 tracked presentation roots have changes")

    view_paths = [
        path for path in tracked_paths(args.ci3_root, (VIEW_ROOT,))
        if path.suffix.lower() in TEMPLATE_SUFFIXES
    ]
    if len(view_paths) != TRACKED_TEMPLATE_DENOMINATOR:
        parser.error(f"pinned CI3 tracked template denominator changed: expected {TRACKED_TEMPLATE_DENOMINATOR}, got {len(view_paths)}")

    sources = [path.relative_to(args.ci3_root).as_posix() for path in view_paths]
    runtime_sources = [source for source in sources if source not in EXCLUSIONS]
    targets = split_many_to_one(runtime_sources)

    scenario_evidence_path = args.ci4_root / "evidence/strict-parity/views/runtime-results.json"
    scenario_results: dict[str, dict[str, object]] = {}
    scenario_run_id: str | None = None
    if scenario_evidence_path.is_file():
        decoded = json.loads(scenario_evidence_path.read_text())
        if isinstance(decoded, dict) and decoded.get("ci3_pin") == CI3_PIN and isinstance(decoded.get("run_id"), str):
            scenario_run_id = decoded["run_id"]
            for scenario in decoded.get("scenarios", []):
                if (isinstance(scenario, dict) and isinstance(scenario.get("source"), str)
                        and scenario.get("run_id") == scenario_run_id):
                    scenario_results[scenario["source"]] = scenario

    templates = []
    for path, source in zip(view_paths, sources, strict=True):
        digest = sha256(path)
        if source in EXCLUSIONS:
            templates.append({
                "source": manifest_path(source),
                "sha256": digest,
                "category": category(source),
                "requirement": "EXCLUDED",
                "caller_route_evidence": EXCLUSIONS[source],
                "ci4_target": None,
                "implementation": {"status": "NOT_USED_WITH_EVIDENCE", "evidence": "; ".join(EXCLUSIONS[source])},
                "dom": {"status": "NOT_APPLICABLE", "evidence": "excluded from runtime denominator"},
                "interaction": {"status": "NOT_APPLICABLE", "evidence": "excluded from runtime denominator"},
                "visual": {"status": "NOT_APPLICABLE", "evidence": "excluded from runtime denominator"},
            })
            continue
        target = targets[source]
        status, evidence = implementation_status(source, digest, target, args.ci4_root)
        axes = {
            "runtime": {"status": "NOT_VERIFIED", "evidence": "no dedicated caller-scenario runtime result attached"},
            "dom": {"status": "NOT_VERIFIED", "evidence": "no current same-state normalized runtime DOM result attached"},
            "interaction": {"status": "NOT_VERIFIED", "evidence": "no current caller-specific interaction result attached"},
            "visual": {"status": "NOT_VERIFIED", "evidence": "no current same-run desktop/mobile screenshot verdict attached"},
        }
        scenario = scenario_results.get(source)
        if scenario is not None and scenario.get("target") == target and scenario.get("same_run") is True:
            scenario_id = scenario.get("id")
            evidence_reference = f"evidence/strict-parity/views/runtime-results.json#{scenario_id}"
            verified_axes = verified_scenario_axes(scenario, args.ci4_root)
            for axis in axes:
                result_status = scenario.get(axis)
                if axis in verified_axes and result_status == "PASS":
                    axes[axis] = {"status": "PASS", "evidence": evidence_reference}
                elif result_status in {"FAIL", "BLOCKED"}:
                    axes[axis] = {"status": result_status, "evidence": evidence_reference}
        templates.append({
            "source": manifest_path(source),
            "sha256": digest,
            "category": category(source),
            "requirement": "RUNTIME_REQUIRED",
            "caller_route_evidence": ["runtime-required under the pinned CI3 view/caller reconciliation"],
            "ci4_target": target,
            "implementation": {"status": status, "evidence": evidence},
            **axes,
        })

    reference_files = tracked_paths(args.ci3_root, ("application/views", "application/controllers", "application/config"))
    reference_text = "\n".join(
        path.read_text(errors="replace") for path in reference_files
        if path.is_file() and path.stat().st_size < 1_000_000
    )
    asset_paths = tracked_paths(args.ci3_root, ASSET_ROOTS)
    assets = []
    for path in asset_paths:
        source = path.relative_to(args.ci3_root).as_posix()
        digest = sha256(path)
        target = args.ci4_root / public_target(source)
        byte_identical = target.is_file() and sha256(target) == digest
        if byte_identical:
            references = None
        else:
            short = source.split("/", 1)[-1]
            references = reference_text.count(source) + reference_text.count(short)
        status, evidence = asset_disposition(source, digest, references, args.ci4_root)
        assets.append({
            "source": manifest_path(source),
            "sha256": digest,
            "bytes": path.stat().st_size,
            "static_reference_count": references,
            "ci4_target": manifest_path(public_target(source)),
            "disposition": status,
            "evidence": evidence,
        })

    ci4_templates = [
        manifest_path(path.relative_to(args.ci4_root).as_posix())
        for path in sorted((args.ci4_root / "app/Views").rglob("*"))
        if path.is_file() and path.suffix.lower() in TEMPLATE_SUFFIXES
    ]
    public_files = [
        manifest_path(path.relative_to(args.ci4_root).as_posix())
        for path in sorted((args.ci4_root / "public").rglob("*"))
        if path.is_file()
    ]

    runtime_rows = [row for row in templates if row["requirement"] == "RUNTIME_REQUIRED"]
    payload = {
        "schema_version": 4,
        "ci3_commit_sha": ci3_sha,
        "ci3_root": str(args.ci3_root),
        "ci4_root": str(args.ci4_root),
        "denominator_rule": "git-tracked .php/.html files under application/views at the required CI3 pin",
        "summary": {
            "tracked_templates": len(templates),
            "runtime_required_templates": len(runtime_rows),
            "excluded_templates": len(templates) - len(runtime_rows),
            "ci4_templates": len(ci4_templates),
            "ci3_assets": len(assets),
            "ci4_public_files": len(public_files),
            "implementation_statuses": dict(sorted(Counter(row["implementation"]["status"] for row in runtime_rows).items())),
            "runtime_statuses": dict(sorted(Counter(row["runtime"]["status"] for row in runtime_rows).items())),
            "dom_statuses": dict(sorted(Counter(row["dom"]["status"] for row in runtime_rows).items())),
            "interaction_statuses": dict(sorted(Counter(row["interaction"]["status"] for row in runtime_rows).items())),
            "visual_statuses": dict(sorted(Counter(row["visual"]["status"] for row in runtime_rows).items())),
            "asset_dispositions": dict(sorted(Counter(row["disposition"] for row in assets).items())),
            "many_to_one_runtime_targets": 0,
        },
        "ci3_templates": templates,
        "ci4_templates": ci4_templates,
        "ci3_assets": assets,
        "ci4_public_files": public_files,
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n")


if __name__ == "__main__":
    main()
