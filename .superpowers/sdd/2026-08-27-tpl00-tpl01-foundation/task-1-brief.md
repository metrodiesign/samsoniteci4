## Task 1: แก้ canonical template denominator

**Files:**

- Modify: `scripts/generate-ci3-presentation-inventory.py:34-41,107-128,147-169`
- Create: `tests/wp00c/test_presentation_inventory.py`
- Create: `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json`
- Create: `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.md`

**Interfaces:**

- Consumes: CI3 root, CI4 root และ output path จาก CLI เดิม
- Produces: JSON schema version 2 ที่ `summary.ci3_templates == 108` และ `ci3_templates` มีทั้ง PHP/HTML

- [ ] **Step 1: เขียน failing test สำหรับ 108 templates**

```python
from pathlib import Path
import json
import subprocess
import tempfile
import unittest


class PresentationInventoryTest(unittest.TestCase):
    def test_inventory_includes_php_and_html_templates(self):
        repo = Path(__file__).resolve().parents[2]
        ci3 = repo.parent / "samsoniteci3"
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / "inventory.json"
            subprocess.run([
                "python3", str(repo / "scripts/generate-ci3-presentation-inventory.py"),
                "--ci3-root", str(ci3),
                "--ci4-root", str(repo),
                "--output", str(output),
            ], check=True)
            payload = json.loads(output.read_text())

        self.assertEqual(108, payload["summary"]["ci3_templates"])
        sources = {row["source"] for row in payload["ci3_templates"]}
        self.assertIn("application/views/index.html", sources)
        self.assertIn("application/views/pdf-form.html", sources)
```

- [ ] **Step 2: รัน test ให้เห็น failure**

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
```

Expected: FAIL เพราะ payload ปัจจุบันมี `ci3_views == 103` และไม่มี `ci3_templates`

- [ ] **Step 3: เปลี่ยน generator ให้ inventory tracked template suffix ครบ**

ใช้ suffix allowlist:

```python
TEMPLATE_SUFFIXES = {".php", ".html"}

template_paths = [
    path for path in ci3_views.rglob("*")
    if path.is_file() and path.suffix.lower() in TEMPLATE_SUFFIXES
]
```

เปลี่ยน payload keys เป็น `ci3_templates`, `ci4_templates`, `summary.ci3_templates`, `summary.ci4_templates` และเพิ่ม `template_type` เป็น `php` หรือ `html`

- [ ] **Step 4: รัน test ให้ผ่าน**

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
```

Expected: `OK`

- [ ] **Step 5: regenerate evidence v2**

```bash
python3 scripts/generate-ci3-presentation-inventory.py \
  --ci3-root /Users/king_developer/Desktop/Project/samsoniteci3 \
  --ci4-root /Users/king_developer/Desktop/Project/samsoniteci4 \
  --output outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json
```

สร้าง summary Markdown จาก JSON โดยรายงาน denominator, disposition counts, HTML 5 records และห้ามเปลี่ยน target candidate เป็น PASS

- [ ] **Step 6: รัน WP00C tests**

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py tests/wp00c/test_closure.py tests/wp00c/test_junit_evidence.py tests/wp00c/test_route_disposition.py
```

Expected: PASS ทั้งหมด

- [ ] **Step 7: review และ checkpoint commit**

หลัง auditor/verifier/reviewer ผ่าน ให้ gitops commit:

```text
wip(strict-template): t1 inventory denominator passed
```

