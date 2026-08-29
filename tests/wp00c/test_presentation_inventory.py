import contextlib
import importlib.util
import io
import json
import pathlib
import subprocess
import sys
import tempfile
import unittest
from unittest import mock


ROOT = pathlib.Path(__file__).resolve().parents[2]
CI3 = ROOT.parent / "samsoniteci3"
SCRIPT = ROOT / "scripts" / "generate-ci3-presentation-inventory.py"
CI3_PIN = "ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6"


def run_inventory(output):
    return subprocess.run(
        [
            "python3", str(SCRIPT),
            "--ci3-root", str(CI3),
            "--ci4-root", str(ROOT),
            "--output", str(output),
        ],
        text=True,
        capture_output=True,
        check=False,
    )


PRESENTATION_ROOTS = ("application/views", "front-update", "assets", "assets2", "cdn", "images")
ASSET_ROOTS = ("front-update", "assets", "assets2", "cdn", "images")


def tracked_presentation_sources(root):
    result = subprocess.run(
        ["git", "-C", str(root), "ls-files", "-z", "--", *PRESENTATION_ROOTS],
        text=True,
        capture_output=True,
        check=True,
    )
    return [path for path in result.stdout.split("\0") if path]


def tracked_template_sources(root):
    return {
        path.replace("@", "%40")
        for path in tracked_presentation_sources(root)
        if path.startswith("application/views/")
        and pathlib.Path(path).suffix.lower() in {".php", ".html"}
    }


def tracked_asset_sources(root):
    return {
        path.replace("@", "%40")
        for path in tracked_presentation_sources(root)
        if path.split("/", 1)[0] in ASSET_ROOTS
    }


def load_generator():
    spec = importlib.util.spec_from_file_location("presentation_inventory", SCRIPT)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def create_ci3_fixture(root):
    views = root / "application/views"
    assets = root / "assets"
    views.mkdir(parents=True)
    assets.mkdir()
    tracked = views / "tracked.php"
    tracked.write_text("tracked", encoding="utf-8")
    (assets / "tracked.css").write_text("tracked", encoding="utf-8")
    subprocess.run(["git", "init", "-q", str(root)], check=True)
    subprocess.run(["git", "-C", str(root), "config", "user.email", "test@example.invalid"], check=True)
    subprocess.run(["git", "-C", str(root), "config", "user.name", "Test"], check=True)
    subprocess.run(["git", "-C", str(root), "add", "application", "assets"], check=True)
    subprocess.run(["git", "-C", str(root), "commit", "-qm", "fixture"], check=True)
    return tracked


def run_fixture_inventory(ci3_root, output):
    module = load_generator()
    module.CI3_PIN = subprocess.run(
        ["git", "-C", str(ci3_root), "rev-parse", "HEAD"],
        text=True,
        capture_output=True,
        check=True,
    ).stdout.strip()
    module.TRACKED_TEMPLATE_DENOMINATOR = 1
    with mock.patch.object(sys, "argv", [
        str(SCRIPT),
        "--ci3-root", str(ci3_root),
        "--ci4-root", str(ROOT),
        "--output", str(output),
    ]):
        module.main()


CI3_AVAILABLE = (CI3 / ".git").exists()


class PresentationInventoryTest(unittest.TestCase):
    @unittest.skipUnless(CI3_AVAILABLE, "CI3 checkout unavailable")
    def test_inventory_matches_pinned_tracked_ci3_templates(self):
        with tempfile.TemporaryDirectory() as directory:
            output = pathlib.Path(directory) / "inventory.json"
            result = run_inventory(output)
            self.assertEqual(0, result.returncode, result.stdout + result.stderr)
            payload = json.loads(output.read_text(encoding="utf-8"))

        self.assertEqual(CI3_PIN, subprocess.run(
            ["git", "-C", str(CI3), "rev-parse", "HEAD"],
            text=True,
            capture_output=True,
            check=True,
        ).stdout.strip())
        self.assertEqual(CI3_PIN, payload["ci3_commit_sha"])
        self.assertEqual(4, payload["schema_version"])
        self.assertEqual(108, payload["summary"]["tracked_templates"])
        self.assertEqual(102, payload["summary"]["runtime_required_templates"])
        self.assertEqual(6, payload["summary"]["excluded_templates"])
        self.assertEqual(5483, payload["summary"]["ci3_assets"])
        self.assertEqual(payload["summary"]["ci4_templates"], len(payload["ci4_templates"]))
        self.assertEqual(
            tracked_template_sources(CI3),
            {row["source"] for row in payload["ci3_templates"]},
        )
        self.assertEqual(
            tracked_asset_sources(CI3),
            {row["source"] for row in payload["ci3_assets"]},
        )
        runtime = [row for row in payload["ci3_templates"] if row["requirement"] == "RUNTIME_REQUIRED"]
        self.assertEqual(102, len(runtime))
        targets = {row["ci4_target"] for row in runtime}
        self.assertEqual(102, len(targets))
        self.assertTrue(all(target.startswith("app/Views/ci3/") for target in targets))
        self.assertTrue(all((ROOT / target).is_file() for target in targets))
        self.assertEqual(0, payload["summary"]["many_to_one_runtime_targets"])
        self.assertEqual({"MIGRATED_AS_IS": 102}, payload["summary"]["implementation_statuses"])
        self.assertEqual({"PASS": 102}, payload["summary"]["runtime_statuses"])
        self.assertEqual({"PASS": 102}, payload["summary"]["dom_statuses"])
        self.assertEqual({"PASS": 102}, payload["summary"]["interaction_statuses"])
        self.assertEqual({"PASS": 102}, payload["summary"]["visual_statuses"])

        records = {row["source"]: row for row in payload["ci3_templates"]}
        for source in [
            "application/views/index.html",
            "application/views/errors/index.html",
            "application/views/errors/html/index.html",
            "application/views/errors/cli/index.html",
        ]:
            self.assertEqual("NOT_USED_WITH_EVIDENCE", records[source]["implementation"]["status"])
            self.assertIn("directory-index deny stub", records[source]["implementation"]["evidence"])
        self.assertIn("tracking/print_order.php", records["application/views/pdf-form.html"]["implementation"]["evidence"])
        self.assertIn("application/controllers/welcome.php::index", records["application/views/welcome_message.php"]["implementation"]["evidence"])
        self.assertEqual("MIGRATED_AS_IS", records["application/views/dashboard.php"]["implementation"]["status"])
        self.assertEqual("PASS", records["application/views/dashboard.php"]["runtime"]["status"])
        self.assertEqual("app/Views/ci3/dashboard.php", records["application/views/dashboard.php"]["ci4_target"])

        assets = {row["source"]: row for row in payload["ci3_assets"]}
        for source in ["assets/css/main.css", "assets/dist/css/AdminLTE.min.css", "assets/dist/css/CustomAdmin.css"]:
            self.assertEqual("ADAPTED_FOR_CI4", assets[source]["disposition"])
            self.assertIn("approved", assets[source]["evidence"])
        for source in ["assets/css/multifreezer.css", "assets/dist/js/app.min.js"]:
            self.assertEqual("MIGRATED_AS_IS", assets[source]["disposition"])
            target = ROOT / assets[source]["ci4_target"]
            self.assertEqual(assets[source]["sha256"], __import__("hashlib").sha256(target.read_bytes()).hexdigest())

    @unittest.skipUnless(CI3_AVAILABLE, "CI3 checkout unavailable")
    def test_inventory_json_is_deterministic(self):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            first = root / "first.json"
            second = root / "second.json"
            for output in (first, second):
                result = run_inventory(output)
                self.assertEqual(0, result.returncode, result.stdout + result.stderr)

            self.assertEqual(first.read_bytes(), second.read_bytes())

    def test_rejects_unstaged_tracked_ci3_template_change(self):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            tracked = create_ci3_fixture(root)
            tracked.write_text("changed", encoding="utf-8")
            output = root / "inventory.json"

            error = io.StringIO()
            with contextlib.redirect_stderr(error), self.assertRaises(SystemExit):
                run_fixture_inventory(root, output)

            self.assertIn("tracked presentation roots have changes", error.getvalue())
            self.assertFalse(output.exists())

    def test_rejects_staged_tracked_ci3_template_change(self):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            tracked = create_ci3_fixture(root)
            tracked.write_text("changed", encoding="utf-8")
            subprocess.run(["git", "-C", str(root), "add", "application/views/tracked.php"], check=True)
            output = root / "inventory.json"

            error = io.StringIO()
            with contextlib.redirect_stderr(error), self.assertRaises(SystemExit):
                run_fixture_inventory(root, output)

            self.assertIn("tracked presentation roots have changes", error.getvalue())
            self.assertFalse(output.exists())

    def test_rejects_unstaged_tracked_ci3_asset_change(self):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            create_ci3_fixture(root)
            (root / "assets/tracked.css").write_text("changed", encoding="utf-8")
            output = root / "inventory.json"

            error = io.StringIO()
            with contextlib.redirect_stderr(error), self.assertRaises(SystemExit):
                run_fixture_inventory(root, output)

            self.assertIn("tracked presentation roots have changes", error.getvalue())
            self.assertFalse(output.exists())

    def test_rejects_staged_tracked_ci3_asset_change(self):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            create_ci3_fixture(root)
            (root / "assets/tracked.css").write_text("changed", encoding="utf-8")
            subprocess.run(["git", "-C", str(root), "add", "assets/tracked.css"], check=True)
            output = root / "inventory.json"

            error = io.StringIO()
            with contextlib.redirect_stderr(error), self.assertRaises(SystemExit):
                run_fixture_inventory(root, output)

            self.assertIn("tracked presentation roots have changes", error.getvalue())
            self.assertFalse(output.exists())

    def test_ignores_untracked_ci3_template_and_generates_inventory(self):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            tracked = create_ci3_fixture(root)
            (tracked.parent / "untracked.html").write_text("untracked", encoding="utf-8")
            output = root / "inventory.json"

            run_fixture_inventory(root, output)
            payload = json.loads(output.read_text(encoding="utf-8"))

        self.assertEqual(["application/views/tracked.php"], [row["source"] for row in payload["ci3_templates"]])


if __name__ == "__main__":
    unittest.main()
