import json
import os
import pathlib
import subprocess
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[2]
CI3 = pathlib.Path(os.environ.get("CI3_SOURCE_ROOT", ROOT.parent / "samsoniteci3"))


class RouteDispositionTest(unittest.TestCase):
    @unittest.skipUnless((CI3 / ".git").exists(), "CI3 source checkout unavailable (e.g. CI runner)")
    def test_snapshot_matches_active_ci3_pin_and_all_routes_are_classified(self):
        result = subprocess.run(
            ["python3", str(ROOT / "scripts/wp00c-route-disposition.py"), "--source-root", str(CI3)],
            text=True,
            capture_output=True,
            check=False,
        )
        self.assertEqual(0, result.returncode, result.stdout + result.stderr)
        actual = json.loads(result.stdout)
        expected = json.loads((ROOT / "tests/wp00c/ci4-route-disposition.json").read_text(encoding="utf-8"))
        self.assertEqual(expected, actual)
        self.assertEqual(178, len(actual["routes"]))
        self.assertTrue(all(row["status"] in {"mapped", "retired"} for row in actual["routes"]))
        self.assertTrue(all(row["replacement"] or row["status"] == "retired" for row in actual["routes"]))


if __name__ == "__main__":
    unittest.main()
