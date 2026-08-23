import json
import pathlib
import subprocess
import tempfile
import textwrap
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "scripts" / "wp00c-junit-evidence.py"


class JunitEvidenceTest(unittest.TestCase):
    def run_adapter(self, xml, mapping):
        documents = [xml] if isinstance(xml, str) else xml
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            map_file = root / "map.json"
            output = root / "round.json"
            map_file.write_text(json.dumps(mapping), encoding="utf-8")
            junit_arguments = []
            for index, document in enumerate(documents, 1):
                junit = root / f"junit-{index}.xml"
                junit.write_text(textwrap.dedent(document), encoding="utf-8")
                junit_arguments.extend(["--junit", str(junit)])
            result = subprocess.run(
                ["python3", str(SCRIPT), *junit_arguments, "--map", str(map_file),
                 "--round", "1", "--output", str(output)],
                text=True,
                capture_output=True,
                check=False,
            )
            payload = json.loads(output.read_text(encoding="utf-8")) if output.exists() else None
            return result, payload

    def test_emits_pass_with_stable_hash_for_mapped_passing_tests(self):
        mapping = {
            "cases": {
                "AUTH-LOGIN-001": [
                    "Tests\\Ci4\\AuthTest::testLogin",
                    "Tests\\Ci4\\AuthTest::testHistory",
                ]
            }
        }
        xml = """
            <testsuites><testsuite>
              <testcase class="Tests\\Ci4\\AuthTest" name="testLogin" assertions="2" time="0.1"/>
              <testcase class="Tests\\Ci4\\AuthTest" name="testHistory" assertions="3" time="9.9"/>
            </testsuite></testsuites>
        """
        first, first_payload = self.run_adapter(xml, mapping)
        second, second_payload = self.run_adapter(xml.replace('time="0.1"', 'time="8.7"'), mapping)

        self.assertEqual(0, first.returncode, first.stdout + first.stderr)
        self.assertEqual("PASS", first_payload["cases"][0]["result"])
        self.assertEqual(
            first_payload["cases"][0]["semantic_sha256"],
            second_payload["cases"][0]["semantic_sha256"],
        )

    def test_fails_when_mapped_test_is_missing_or_failed(self):
        mapping = {
            "cases": {
                "AUTH-LOGIN-001": [
                    "Tests\\Ci4\\AuthTest::testLogin",
                    "Tests\\Ci4\\AuthTest::testHistory",
                ]
            }
        }
        xml = """
            <testsuites><testsuite>
              <testcase class="Tests\\Ci4\\AuthTest" name="testLogin" assertions="2">
                <failure message="nope"/>
              </testcase>
            </testsuite></testsuites>
        """
        result, payload = self.run_adapter(xml, mapping)

        self.assertNotEqual(0, result.returncode)
        self.assertEqual("FAIL", payload["cases"][0]["result"])
        self.assertEqual(
            ["Tests\\Ci4\\AuthTest::testHistory"],
            payload["cases"][0]["missing_tests"],
        )

    def test_combines_distinct_testcases_from_multiple_junit_documents(self):
        mapping = {"cases": {"CASE-001": [
            "Tests\\Ci4\\AuthTest::testLogin",
            "Tests\\Operational\\ConcurrencyCheck::testToken",
        ]}}
        documents = [
            r"""
                <testsuites><testsuite>
                  <testcase class="Tests\Ci4\AuthTest" name="testLogin" assertions="2"/>
                </testsuite></testsuites>
            """,
            r"""
                <testsuites><testsuite>
                  <testcase class="Tests\Operational\ConcurrencyCheck" name="testToken" assertions="4"/>
                </testsuite></testsuites>
            """,
        ]

        result, payload = self.run_adapter(documents, mapping)

        self.assertEqual(0, result.returncode, result.stdout + result.stderr)
        self.assertEqual(6, payload["cases"][0]["assertions"])

    def test_emits_blocked_even_when_readiness_tests_pass(self):
        mapping = {"cases": {"PERF-CI3-001": {
            "tests": ["Tests\\Ci4\\ReportTest::testReadiness"],
            "execution_status": "BLOCKED",
            "blocker": "approved production-like profile missing",
        }}}
        xml = r"""
            <testsuites><testsuite>
              <testcase class="Tests\Ci4\ReportTest" name="testReadiness" assertions="3"/>
            </testsuite></testsuites>
        """

        result, payload = self.run_adapter(xml, mapping)

        self.assertNotEqual(0, result.returncode)
        self.assertEqual("BLOCKED", payload["cases"][0]["result"])
        self.assertEqual("approved production-like profile missing", payload["cases"][0]["blocker"])

    def test_repository_mapping_covers_all_catalog_cases_and_sources(self):
        catalog = json.loads((ROOT / "tests/wp00c/catalog.json").read_text(encoding="utf-8"))
        mapping = json.loads((ROOT / "tests/wp00c/ci4-case-tests.json").read_text(encoding="utf-8"))["cases"]

        self.assertEqual({case["id"] for case in catalog["cases"]}, set(mapping))
        self.assertEqual(
            ["RPT-EDGE-001", "PERF-CI3-001"],
            [case_id for case_id, config in mapping.items() if config.get("execution_status") == "BLOCKED"],
        )
        for config in mapping.values():
            for source in config.get("source_files", []):
                self.assertTrue((ROOT / source).is_file(), source)


if __name__ == "__main__":
    unittest.main()
