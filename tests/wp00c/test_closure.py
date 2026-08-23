import json
import pathlib
import subprocess
import tempfile
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "scripts" / "wp00c-closure.py"


class ClosureGateTest(unittest.TestCase):
    def run_gate(self, cases, rounds, approvals):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            catalog = root / "catalog.json"
            approval_file = root / "approvals.json"
            catalog.write_text(json.dumps({"cases": cases}), encoding="utf-8")
            approval_file.write_text(json.dumps({"approvals": approvals}), encoding="utf-8")
            command = [
                "python3",
                str(SCRIPT),
                "--catalog",
                str(catalog),
                "--approvals",
                str(approval_file),
            ]
            for number, records in enumerate(rounds, 1):
                path = root / f"round-{number}.json"
                path.write_text(json.dumps({"cases": records}), encoding="utf-8")
                command.extend(["--round", str(path)])
            return subprocess.run(command, text=True, capture_output=True, check=False)

    def test_rejects_pass_without_three_rounds_and_required_approvals(self):
        cases = [{"id": "CASE-001", "approval_roles": ["Business", "QA"]}]
        result = self.run_gate(
            cases,
            [[{"id": "CASE-001", "result": "PASS", "semantic_sha256": "a" * 64}]],
            [{"case_id": "CASE-001", "role": "QA", "approver": "qa-1"}],
        )

        self.assertNotEqual(0, result.returncode)
        self.assertIn("rounds=1/3", result.stdout)
        self.assertIn("pending approvals=Business", result.stdout)

    def test_accepts_only_matching_three_rounds_and_all_required_approvals(self):
        cases = [{"id": "CASE-001", "approval_roles": ["Business", "QA"]}]
        record = {"id": "CASE-001", "result": "PASS", "semantic_sha256": "b" * 64}
        result = self.run_gate(
            cases,
            [[record], [record], [record]],
            [
                {"case_id": "CASE-001", "role": "Business", "approver": "business-1"},
                {"case_id": "CASE-001", "role": "QA", "approver": "qa-1"},
            ],
        )

        self.assertEqual(0, result.returncode, result.stdout + result.stderr)
        self.assertIn("WP-00C CLOSED 1/1", result.stdout)

    def test_rejects_semantic_drift_between_rounds(self):
        cases = [{"id": "CASE-001", "approval_roles": ["QA"]}]
        records = [
            [{"id": "CASE-001", "result": "PASS", "semantic_sha256": value * 64}]
            for value in ("a", "b", "a")
        ]
        result = self.run_gate(
            cases,
            records,
            [{"case_id": "CASE-001", "role": "QA", "approver": "qa-1"}],
        )

        self.assertNotEqual(0, result.returncode)
        self.assertIn("determinism mismatch", result.stdout)


if __name__ == "__main__":
    unittest.main()
