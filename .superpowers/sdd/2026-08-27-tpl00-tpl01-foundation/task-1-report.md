# รายงาน Task 1: Canonical Template Inventory

งานนี้เปลี่ยน canonical denominator ของ CI3 presentation inventory จาก PHP view 103 ไฟล์ เป็น tracked templates 108 ไฟล์ที่รวม PHP และ HTML โดยยึด CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`.

## ขอบเขตที่ทำ

- เพิ่ม `TEMPLATE_SUFFIXES` เป็น `.php` และ `.html` ใน generator
- เปลี่ยน JSON เป็น schema version `2` และใช้ keys `ci3_templates`, `ci4_templates`, `summary.ci3_templates`, `summary.ci4_templates`
- เพิ่ม `template_type` ใน template record เป็น `php` หรือ `html`
- สร้าง canonical inventory evidence v2 และ summary ที่แสดง HTML ทั้ง 5 records
- คงทุก CI3 template ที่มี target candidate ไว้เป็น `BLOCKED` ไม่มีการใช้ `PASS`

## ไฟล์ที่แก้หรือสร้าง

| ไฟล์ | การเปลี่ยน |
|---|---|
| `scripts/generate-ci3-presentation-inventory.py` | เปลี่ยน inventory เป็น tracked PHP/HTML templates และ schema v2 |
| `tests/wp00c/test_presentation_inventory.py` | เพิ่ม regression test สำหรับ 108 templates และ schema ใหม่ |
| `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json` | สร้าง canonical inventory evidence |
| `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.md` | สร้าง summary สำหรับตรวจโดยคน |
| `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-1-report.md` | รายงาน Task 1 นี้ |

## หลักฐาน TDD

### RED

คำสั่ง:

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
```

ผลลัพธ์ก่อนแก้ generator:

```text
FAIL: test_inventory_includes_php_and_html_templates
AssertionError: 2 != 1

Ran 1 test in 0.365s

FAILED (failures=1)
```

ความหมาย: test ตรวจ schema version `2` แต่ generator เดิมส่ง schema version `1` จึงล้มตาม behavior ที่ต้องเปลี่ยน

### GREEN

คำสั่ง:

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
```

ผลลัพธ์หลังแก้ generator:

```text
.
----------------------------------------------------------------------
Ran 1 test in 0.440s

OK
```

## หลักฐาน inventory

คำสั่ง:

```bash
python3 scripts/generate-ci3-presentation-inventory.py \
  --ci3-root /Users/king_developer/Desktop/Project/samsoniteci3 \
  --ci4-root /Users/king_developer/Desktop/Project/samsoniteci4 \
  --output outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json
python3 -m json.tool outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json >/dev/null
```

ผลที่ยืนยันได้:

| Invariant | ผล |
|---|---|
| CI3 pin | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` |
| Schema | `2` |
| `summary.ci3_templates` | `108` |
| HTML templates | `5` |
| `PASS` dispositions | `0` |
| Template disposition | `BLOCKED` 108 |

## ชุดทดสอบเต็มที่เกี่ยวข้อง

คำสั่ง:

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py tests/wp00c/test_closure.py tests/wp00c/test_junit_evidence.py tests/wp00c/test_route_disposition.py
```

ผลลัพธ์:

```text
..........
----------------------------------------------------------------------
Ran 10 tests in 0.901s

OK
```

## Self-review

- ใช้ suffix allowlist ที่จำกัดเฉพาะ `.php` และ `.html` ตาม contract
- JSON record ของ CI3 และ CI4 ระบุ `template_type` จึงแยกประเภทได้โดยไม่อาศัยนามสกุลจาก string ภายนอก
- `ci4_target_candidates` ยังเป็น candidate และ disposition ของ CI3 templates ถูกคงเป็น `BLOCKED`
- รัน `git diff --check` สำหรับ source และ test โดยไม่พบ whitespace error
- ตรวจ JSON parse, จำนวน templates, จำนวน HTML และ absence ของ `PASS` ด้วย assertion แยก

## Concerns

- ไม่มี concern ที่ขวาง Task 1
- Evidence นี้เป็น static inventory เท่านั้น ไม่ยืนยัน behavior, DOM, JavaScript หรือ visual parity
- ค่าจำนวน 108 ผูกกับ CI3 checkout ที่ pin ไว้ จึงต้องคง pin นี้เมื่อ regenerate evidence

## รายการยังไม่ทำ

- กำหนด disposition พร้อมหลักฐานระดับไฟล์สำหรับ templates ทั้ง 108 รายการ
- สร้าง runtime, DOM, JavaScript และ visual evidence ตาม gate ถัดไป
- Audit, verify, review และ checkpoint commit โดย agent ที่รับผิดชอบ

## รอบแก้ 1/5: Canonical source identity

รอบนี้แก้ review findings ทั้งสี่ข้อ โดยจำกัด scope ไว้ที่ generator, regression test และ generated evidence ของ Task 1

### RED

คำสั่ง:

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
```

ผลลัพธ์ก่อนแก้:

```text
ERROR: test_inventory_matches_pinned_tracked_ci3_templates
KeyError: 'ci3_commit_sha'

ERROR: test_tracked_template_discovery_excludes_untracked_files
AttributeError: module 'presentation_inventory' has no attribute 'tracked_template_paths'

FAIL: test_inventory_json_is_deterministic
AssertionError: ... generated_at ... != ... generated_at ...

Ran 3 tests in 0.852s

FAILED (failures=1, errors=2)
```

ความหมาย: JSON ไม่มี CI3 commit SHA, generator ยังไม่มี Git tracked discovery และ timestamp ทำให้ output เดิมไม่ deterministic

### Fix

- ใช้ `git ls-files -z -- application/views` แล้ว filter `.php` และ `.html` เป็น source list ของ CI3 templates
- อ่าน CI3 `HEAD`, ปฏิเสธการสร้าง evidence หากไม่ตรง `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` และเก็บค่าใน `ci3_commit_sha`
- ตัด `generated_at` ออกจาก canonical JSON เพื่อให้ byte output ทำซ้ำได้เมื่อ input เดิม
- test เปรียบ `ci3_templates` source set กับ Git tracked set ทั้งหมด, assert pin, และสร้าง temporary Git fixture เพื่อยืนยันว่า untracked template ไม่นับ
- summary อ่าน CI3 pin จาก contract field `ci3_commit_sha` ใน JSON

### GREEN

คำสั่ง:

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py tests/wp00c/test_closure.py tests/wp00c/test_junit_evidence.py tests/wp00c/test_route_disposition.py
git diff --check -- scripts/generate-ci3-presentation-inventory.py tests/wp00c/test_presentation_inventory.py
```

ผลลัพธ์:

```text
............
----------------------------------------------------------------------
Ran 12 tests in 1.505s

OK
```

`git diff --check` ไม่แสดง output จึงไม่พบ whitespace error

### Fix evidence

คำสั่งสร้าง JSON ส่งผล invariant ต่อไปนี้:

| Invariant | ผล |
|---|---|
| `ci3_commit_sha` | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` |
| `summary.ci3_templates` | `108` |
| HTML templates | `5` |
| `generated_at` | ไม่มี field |
| `PASS` dispositions | `0` |

## Self-review รอบแก้

- CI3 denominator ไม่ขึ้นกับ untracked files ใน working tree เพราะ source list มาจาก Git index
- generator fail ก่อนสร้าง output เมื่อ CI3 `HEAD` ไม่ตรง pin ที่กำหนด
- test รันโดยไม่ใช้ `skip` ใน project environment และตรวจ source set ทั้งหมดแทนการตรวจเพียงตัวอย่าง
- canonical JSON ตัดข้อมูลเวลาทิ้งแล้ว จึง deterministic เมื่อ root และ Git state เดิม

## รอบแก้ 2/5: Tracked CI3 tree guard

รอบนี้เพิ่ม guard เดียวที่ครอบทุก CI3 filesystem read และ hash ของ generator

### RED

คำสั่ง:

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
```

ผลลัพธ์ก่อนเพิ่ม guard:

```text
FAIL: test_rejects_staged_tracked_ci3_template_change
AssertionError: SystemExit not raised

FAIL: test_rejects_unstaged_tracked_ci3_template_change
AssertionError: SystemExit not raised

Ran 5 tests in 1.078s

FAILED (failures=2)
```

ความหมาย: generator ยอมสร้าง inventory จาก tracked template ที่ dirty ทั้ง staged และ unstaged แม้ `HEAD` จะตรง CI3 pin

### Fix

- เพิ่ม `tracked_tree_is_clean()` ที่เรียก `git diff --quiet -- application/views assets` และ `git diff --cached --quiet -- application/views assets` ด้วย subprocess argument list
- เรียก guard หลัง validate CI3 `HEAD` และก่อนกำหนด CI3 path, discovery, read หรือ SHA-256
- หาก tracked tree dirty ให้ `argparse` ปฏิเสธก่อนสร้าง output
- เพิ่ม temporary Git fixture 3 กรณี: unstaged tracked template, staged tracked template และ untracked template

### GREEN

คำสั่ง:

```bash
python3 scripts/generate-ci3-presentation-inventory.py \
  --ci3-root /Users/king_developer/Desktop/Project/samsoniteci3 \
  --ci4-root /Users/king_developer/Desktop/Project/samsoniteci4 \
  --output outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json
python3 -m json.tool outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json >/dev/null
python3 -m unittest tests/wp00c/test_presentation_inventory.py tests/wp00c/test_closure.py tests/wp00c/test_junit_evidence.py tests/wp00c/test_route_disposition.py
git diff --check -- scripts/generate-ci3-presentation-inventory.py tests/wp00c/test_presentation_inventory.py
```

ผลลัพธ์:

```text
..............
----------------------------------------------------------------------
Ran 14 tests in 1.569s

OK
```

`git diff --check` ไม่แสดง output จึงไม่พบ whitespace error

### Fix evidence

| กรณีใน temporary Git fixture | ผล |
|---|---|
| Tracked template แก้แบบ unstaged | generator ปฏิเสธและไม่สร้าง output |
| Tracked template แก้แบบ staged | generator ปฏิเสธและไม่สร้าง output |
| Untracked template เพิ่มใหม่ | generator สร้าง output และไม่นับไฟล์นั้น |

## Self-review รอบแก้ 2

- Guard ครอบทั้ง `application/views` และ `assets` เพราะ generator อ่านและ hash จากทั้งสอง directory
- Untracked files ไม่ปรากฏใน `git diff` หรือ `git diff --cached` จึงยังสร้าง inventory ได้ตาม contract
- ไม่มี `shell=True`; Git ทุก invocation ส่งเป็น argument list

## รอบแก้ 3/5: Tracked asset regression coverage

รอบนี้เพิ่ม test coverage สำหรับสอง dirty branches ของ tracked asset โดยไม่แก้ generator เพราะ guard เดิมครอบ `assets` อยู่แล้ว

### RED

mutation check แทน RED จาก production code ที่ทำงานถูกต้องอยู่แล้ว โดยบังคับ guard ให้ยอมผ่าน asset-only dirty tree ใน temporary Git fixture

```text
MUTATION_DETECTED: asset-only dirty tree generated output when guard ignored assets
```

ความหมาย: หาก pathspec ของ guard ไม่ตรวจ `assets` generator จะสร้าง output จาก CI3 asset ที่ dirty และ regression test ใหม่ต้องป้องกันการถดถอยนี้

### GREEN

คำสั่ง:

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
python3 -m unittest tests/wp00c/test_presentation_inventory.py tests/wp00c/test_closure.py tests/wp00c/test_junit_evidence.py tests/wp00c/test_route_disposition.py
git diff --check -- scripts/generate-ci3-presentation-inventory.py tests/wp00c/test_presentation_inventory.py
```

ผลลัพธ์:

```text
.......
----------------------------------------------------------------------
Ran 7 tests in 1.192s

OK

................
----------------------------------------------------------------------
Ran 16 tests in 1.678s

OK
```

`git diff --check` ไม่แสดง output จึงไม่พบ whitespace error

### Fix evidence

| กรณีใน temporary Git fixture | ผล |
|---|---|
| Tracked `assets/tracked.css` แก้แบบ unstaged | `SystemExit` และไม่มี output file |
| Tracked `assets/tracked.css` แก้แบบ staged | `SystemExit` และไม่มี output file |

## Self-review รอบแก้ 3

- ใช้ fixture เดิมจึงขับ branch ของ guard จริง โดยไม่เพิ่ม production abstraction หรือ dependency
- test ใหม่ครอบ representative path ของ `assets` ทั้ง staged และ unstaged ตาม pathspec ของ generator
