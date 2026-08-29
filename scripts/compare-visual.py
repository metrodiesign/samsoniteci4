#!/usr/bin/env python3
"""Exact paired screenshot comparator; dimensions and every pixel must match."""

import argparse
import json
from pathlib import Path

from PIL import Image, ImageChops


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--directory", required=True, type=Path)
    parser.add_argument("--page", required=True)
    parser.add_argument("--viewport", action="append", required=True)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()

    rows = []
    for viewport in args.viewport:
        left_path = args.directory / f"{args.page}__ci3__{viewport}.png"
        right_path = args.directory / f"{args.page}__ci4__{viewport}.png"
        left = Image.open(left_path).convert("RGBA")
        right = Image.open(right_path).convert("RGBA")
        same_dimensions = left.size == right.size
        box = ImageChops.difference(left, right).getbbox() if same_dimensions else None
        matches = same_dimensions and box is None
        rows.append({
            "viewport": viewport,
            "ci3_dimensions": list(left.size),
            "ci4_dimensions": list(right.size),
            "matching_pixels": matches,
            "difference_bbox": list(box) if box is not None else None,
        })

    payload = {"status": "PASS" if all(row["matching_pixels"] for row in rows) else "FAIL", "rows": rows}
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(payload))
    return 0 if payload["status"] == "PASS" else 1


if __name__ == "__main__":
    raise SystemExit(main())
