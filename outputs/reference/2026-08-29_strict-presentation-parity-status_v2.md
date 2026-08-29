# รายงาน strict CI3 presentation parity รอบปัจจุบัน

## ขอบเขต authority

- CI3 source: `../samsoniteci3`
- commit: `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`
- tracked templates: `108`
- runtime-required templates: `102`
- excluded พร้อมหลักฐาน: `6`

## Mapping และ dedicated template

- mapping file: `outputs/reference/2026-08-29_ci3-ci4-view-mapping_v1.md`
- machine-readable inventory: `outputs/reference/2026-08-29_ci3-presentation-inventory_v5.json`
- mapping: `102/102`
- target path ที่ไม่ซ้ำ: `102/102`
- many-to-one target: `0`
- target ที่หาย: `0`
- target ที่ hash ต่างจาก CI3 authority: `0`
- dedicated targets อยู่ใต้ `app/Views/ci3/**` และเป็น byte-identical template copy

ผลข้างต้นพิสูจน์เฉพาะ dedicated template และ source provenance ไม่ใช่ runtime DOM parity และไม่ถูกนับเป็น `ADAPTED_FOR_CI4` จาก filename

## Runtime parity

| แกน | PASS | FAIL | NOT_VERIFIED |
|---|---:|---:|---:|
| normalized DOM | 0 | 1 | 101 |
| browser interaction | 1 | 0 | 101 |
| desktop visual `1440x900`, DPR 1 | 1 | 0 | 101 |
| mobile visual `390x844`, DPR 1 | 1 | 0 | 101 |

Dashboard DOM ยังต่างที่ visible `Last Login` text 1 จุด จึงห้ามนับ dashboard เป็น overall match แม้ screenshot เดิมของ dashboard จะ pixel-equal ทั้งสอง viewport

ผล visual ที่ระบุเป็น evidence ปัจจุบันซึ่ง inventory อ่านได้ แต่ยังไม่มี current same-run capture ครบ 102 views จึงไม่ใช้ปิดงาน

## Interaction ที่มีหลักฐาน

- dashboard sidebar toggle: CI3 และ CI4
- dashboard logout: CI3 และ CI4

หน้าอื่นยังไม่มี browser interaction scenario ราย view

## Mutation checks

`tests/wp00c/test_runtime_dom_comparator.py` ยืนยันว่า comparator fail เมื่อ:

- ย้าย node ไป parent อื่น
- ลบ class
- ลบ field
- เปลี่ยน heading
- สลับ sibling order

Comparator ใช้ `DOMDocument` สร้าง parsed tree และไม่ใช้ regex/`strpos()` พิสูจน์ hierarchy

## สรุป verdict

| MATCH | MINOR | MAJOR | BEHAVIOR | NOT_VERIFIED |
|---:|---:|---:|---:|---:|
| 0 | 0 | 1 | 0 | 101 |

`MAJOR=1` คือ dashboard normalized DOM fail จาก visible text ความต่าง 1 จุด ไม่ได้ normalize ทิ้ง

## คำสั่งและผลจริง

| คำสั่ง | exit | ผล |
|---|---:|---|
| inventory generator ไป `v5.json` | 0 | `102` runtime templates, `102` unique targets, `MIGRATED_AS_IS=102`, many-to-one `0` |
| PHP lint สำหรับ `app/Views/ci3/**/*.php` | 0 | syntax ผ่านทุกไฟล์ |
| `python3 -m unittest tests/wp00c/test_presentation_inventory.py tests/wp00c/test_runtime_dom_comparator.py` | 0 | 11 tests ผ่าน |
| `composer test` | 0 | 438 tests, 9,742 assertions |
| `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` | 0 | No errors |
| `bash scripts/ci-check.sh` | 0 | repository safety gate ผ่าน |
| `python3 -m unittest tests/wp00c/test_runtime_dom_comparator.py` | 0 | 4 mutation/comparator tests ผ่าน |
| `git diff --check` | 0 | ไม่มี whitespace error |
| CI3 presentation `git status --short` | 0 | ไม่มี output; source ไม่เปลี่ยน |

## Implementation milestone ล่าสุด

CI4 runtime ใช้ `LegacyViewRenderer` กับ dedicated CI3 templates แล้ว 12 targets ได้แก่ login, contact สองภาษา, forgot/reset/change password, access denied, reset email และ admin/order header/footer สี่ไฟล์ โดย compatibility seam ทำหน้าที่ localize pinned dependencies, inject CI4 CSRF เฉพาะ POST forms, escape dynamic values และคง CI4 authorization/menu filtering

ชุดตรวจหลัง milestone ผ่าน: `composer test` 438 tests / 10,042 assertions, PHPStan, PHP lint, WP-00C inventory/DOM comparator tests, `git diff --check`, MariaDB concurrency checks และ `scripts/ci-check.sh`

ตัวเลข parity ด้านบนยังไม่เพิ่ม เพราะยังไม่มี current same-run browser DOM/interaction/desktop/mobile evidence และอีก 90 targets ยังไม่ได้เชื่อม dedicated runtime path

## Blocker ที่เหลือ

- CI4 controllers ยัง render generic views หลายกลุ่ม เช่น `master_list`, `master_form`, `orders`, `reports/matrix`, `reports/export`, `import_form` และ `import_preview`; dedicated CI3 copies ยังไม่ได้เชื่อมด้วย CI4 view-model adapter ครบทุกหน้า
- ยังไม่มี runtime scenario อย่างน้อย 102 scenarios และ scenario เพิ่มสำหรับ role/language/branch/state
- DOM ยังไม่ผ่าน `102/102`
- browser interaction ยังไม่ผ่าน `102/102`
- desktop/mobile current same-run screenshots ยังไม่ผ่าน `102/102`
- environment มี browser และ CI3/CI4 ตอบ `200` ที่ `/login` แต่ `WP00C_TEST_PASSWORD` ไม่ได้ตั้ง จึงยัง login เพื่อสร้าง current authenticated capture run ไม่ได้
- ห้ามถือ byte-identical asset หรือ template copy เป็น DOM/visual PASS

STATUS: BLOCKED
