# CI3 Template Inventory v2

เอกสารนี้สรุป canonical template inventory จากค่า `ci3_commit_sha` ใน JSON คือ CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` โดยนับเฉพาะ Git tracked files ใต้ `application/views/**` ที่เป็น `.php` และ `.html`

## ขอบเขตและสถานะ

- **CI3 source**: `/Users/king_developer/Desktop/Project/samsoniteci3`
- **CI4 target**: `/Users/king_developer/Desktop/Project/samsoniteci4`
- **Schema**: version `2`
- **CI3 commit SHA**: `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` จาก `ci3_commit_sha` ใน JSON
- **หลักฐาน canonical**: `2026-08-27_ci3-presentation-inventory_v2.json`
- **ข้อจำกัด**: inventory เป็น static source evidence ไม่ใช่หลักฐาน parity ของ route, DOM, JavaScript หรือภาพ

## Canonical denominator

| รายการ | จำนวน | สถานะ |
|---|---:|---|
| CI3 templates | 108 | PHP 103, HTML 5 |
| CI4 templates | 41 | เป็น target inventory เท่านั้น |
| CI3 static assets | 997 | `MIGRATED_AS_IS` 76, `BLOCKED` 38, `NOT_USED_WITH_EVIDENCE` 883 |
| CI4 static assets | 107 | เป็น target inventory เท่านั้น |

ทุก CI3 template อยู่ใน `ci3_templates` พร้อม source, SHA-256, `template_type`, category, target candidates, disposition และ evidence. Disposition ของ template ทั้ง 108 รายการเป็น `BLOCKED`.

## HTML template records

| CI3 source | Category | CI4 target candidates | Disposition |
|---|---|---|---|
| `application/views/errors/cli/index.html` | `framework_error_view` | ไม่มี | `BLOCKED` |
| `application/views/errors/html/index.html` | `framework_error_view` | ไม่มี | `BLOCKED` |
| `application/views/errors/index.html` | `framework_error_view` | ไม่มี | `BLOCKED` |
| `application/views/index.html` | `page_view` | ไม่มี | `BLOCKED` |
| `application/views/pdf-form.html` | `page_view` | ไม่มี | `BLOCKED` |

## กติกา target candidate

`ci4_target_candidates` เป็นเพียง candidate จากชื่อหรือหน้าที่ของ template จึงไม่ใช่ migration proof และไม่เปลี่ยนสถานะเป็น `PASS`. ต้องมีหลักฐานการ adapt, DOM parity และ visual parity ก่อนกำหนด disposition ที่ปิดงานได้

## สร้างและตรวจสอบ

```bash
python3 scripts/generate-ci3-presentation-inventory.py \
  --ci3-root /Users/king_developer/Desktop/Project/samsoniteci3 \
  --ci4-root /Users/king_developer/Desktop/Project/samsoniteci4 \
  --output outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json
python3 -m json.tool outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json >/dev/null
```
