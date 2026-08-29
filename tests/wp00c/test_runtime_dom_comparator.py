import json
import pathlib
import subprocess
import tempfile
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[2]
COMPARATOR = ROOT / "scripts" / "compare-runtime-dom.php"
BASE = """<!doctype html><html><body><main class="shell"><h1>Dashboard</h1><form id="search"><label>Search<input name="query" class="form-control" value=""></label><button type="submit">Go</button></form><section><p class="first">One</p><p class="second">Two</p></section></main></body></html>"""


class RuntimeDomComparatorTest(unittest.TestCase):
    def compare(self, left, right, allowlist=None):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            left_path = root / "left.html"
            right_path = root / "right.html"
            left_path.write_text(left, encoding="utf-8")
            right_path.write_text(right, encoding="utf-8")
            command = [
                "php", str(COMPARATOR), "--left", str(left_path),
                "--right", str(right_path), "--page", "mutation-fixture",
            ]
            if allowlist is not None:
                allowlist_path = root / "allowlist.json"
                allowlist_path.write_text(json.dumps(allowlist), encoding="utf-8")
                command.extend(["--allowlist", str(allowlist_path)])
            result = subprocess.run(command, text=True, capture_output=True, check=False)
            payload = json.loads(result.stdout) if result.stdout else None
            return result, payload

    def test_equal_parsed_dom_passes(self):
        result, payload = self.compare(BASE, BASE)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual("PASS", payload["status"])
        self.assertEqual(0, payload["difference_count"])

    def test_structural_and_visible_mutations_all_fail(self):
        mutations = {
            "move-node": BASE.replace(
                '<form id="search"><label>Search<input name="query" class="form-control" value=""></label><button type="submit">Go</button></form>',
                '<form id="search"><label>Search</label><input name="query" class="form-control" value=""><button type="submit">Go</button></form>',
            ),
            "remove-class": BASE.replace(' class="form-control"', ""),
            "remove-field": BASE.replace('<input name="query" class="form-control" value="">', ""),
            "change-heading": BASE.replace("<h1>Dashboard</h1>", "<h1>Reports</h1>"),
            "change-element-order": BASE.replace(
                '<p class="first">One</p><p class="second">Two</p>',
                '<p class="second">Two</p><p class="first">One</p>',
            ),
        }
        for mutation, html in mutations.items():
            with self.subTest(mutation=mutation):
                result, payload = self.compare(BASE, html)
                self.assertEqual(1, result.returncode, result.stderr)
                self.assertEqual("FAIL", payload["status"])
                self.assertGreater(payload["difference_count"], 0)
                self.assertIn("selector", payload["differences"][0])

    def test_explicit_rule_normalizes_only_approved_attribute(self):
        right = BASE.replace('id="search"', 'id="runtime-987"')
        left = BASE.replace('id="search"', 'id="runtime-123"')
        rule = [{
            "page": "mutation-fixture",
            "selector": "//form",
            "attribute": "id",
            "pattern": "/runtime-[0-9]+/",
            "replacement": "runtime-id",
            "reason": "fixture runtime identifier",
            "decision_id": "DOM-RUNTIME-ID-001",
        }]
        result, payload = self.compare(left, right, rule)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual("PASS", payload["status"])
        self.assertEqual(1, payload["allowlist_rules_used"])

    def test_explicit_remove_rule_handles_security_only_hidden_field(self):
        right = BASE.replace(
            '<form id="search">',
            '<form id="search"><input type="hidden" name="csrf_test_name" value="runtime-token">',
        )
        rule = [{
            "page": "mutation-fixture",
            "selector": "//input[@name='csrf_test_name']",
            "attribute": "#remove",
            "reason": "CI4-only CSRF field is a security adaptation and has no visual output",
            "decision_id": "DOM-CSRF-001",
        }]
        result, payload = self.compare(BASE, right, rule)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual("PASS", payload["status"])
        self.assertEqual(1, payload["allowlist_rules_used"])

    def test_unused_allowlist_rule_fails_closed(self):
        rule = [{
            "page": "mutation-fixture",
            "selector": "//form",
            "attribute": "action",
            "pattern": "/token/",
            "replacement": "approved",
            "reason": "fixture token",
            "decision_id": "DOM-TOKEN-001",
        }]
        result, payload = self.compare(BASE, BASE, rule)
        self.assertEqual(2, result.returncode)
        self.assertEqual("UNUSED_ALLOWLIST_RULE", payload["reason"])


if __name__ == "__main__":
    unittest.main()
