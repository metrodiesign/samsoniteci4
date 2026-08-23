import json
import pathlib
import subprocess
import unittest


ROOT = pathlib.Path(__file__).resolve().parents[2]
CI3 = ROOT.parent / "samsoniteci3"


class RouteDispositionTest(unittest.TestCase):
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
