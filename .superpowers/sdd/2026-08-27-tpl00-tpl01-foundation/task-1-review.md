# รีวิว Task 1: Canonical Template Inventory

รีวิวนี้ตรวจเฉพาะ Task 1 จาก brief, implementer report และ review package โดยตรวจ requirement, source identity, test, JSON evidence และ Markdown summary แบบ read-only

## Verdict

| แกน | Verdict | เหตุผล |
|---|---|---|
| Spec compliance | FAIL | generator และ test ยังไม่รับประกันว่า denominator มาจาก tracked CI3 templates ที่ pin กำหนด |
| Code quality | CHANGES REQUIRED | มี 2 Critical และ 2 Important ที่ทำให้ canonical evidence ตรวจย้อนกลับหรือทำซ้ำอย่างน่าเชื่อถือไม่ได้ |

## ข้อที่ผ่าน

- JSON evidence ปัจจุบันมี CI3 template 108 รายการ, HTML 5 รายการ และไม่มี `PASS`
- CI3 template ทุก record ใช้ `BLOCKED` ซึ่งเป็น disposition ที่ contract อนุญาต
- `ci4_target_candidates` ถูกอธิบายว่าเป็น candidate เท่านั้น จึงไม่อ้าง runtime, DOM หรือ visual parity เกินหลักฐาน
- ไม่มี frontend dependency change ใน diff ของ Task 1
- Markdown summary เป็นภาษาไทย มีโครงสร้างชัด และระบุข้อจำกัดของ static inventory ถูกต้อง ยกเว้นคำอ้าง CI3 pin ที่ยังพิสูจน์ไม่ได้

## Critical findings

| ระดับ | ตำแหน่ง | ปัญหาและ failure scenario | การแก้ที่แคบที่สุด |
|---|---|---|---|
| Critical | `scripts/generate-ci3-presentation-inventory.py:112-115` | ใช้ `rglob()` นับไฟล์ใน working tree ทุกไฟล์ ไม่ใช่ Git tracked files ตาม contract หากมี `application/views/local-debug.php` ที่ untracked denominator จะเปลี่ยนทันที แม้ไม่มีอยู่ใน CI3 pin | สร้างรายการจาก `git ls-files -- application/views` แล้ว filter `.php` และ `.html` ก่อนอ่านหรือ hash |
| Critical | `scripts/generate-ci3-presentation-inventory.py:101-187`, `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.md:3` | ไม่มีการอ่านหรือ validate `HEAD` กับ pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` และ JSON ไม่มี commit SHA หาก CI3 checkout เปลี่ยน branch หรือแก้ไฟล์ tracked โดยยังมี 108 ไฟล์ generator จะสร้าง evidence ใหม่ แต่ Markdown ยังอ้าง pin เดิม | ตรวจ CI3 `HEAD` ให้ตรง pin ก่อนสร้าง, บันทึก SHA ใน JSON และให้ Markdown ใช้ค่าที่พิสูจน์จาก JSON |

## Important findings

| ระดับ | ตำแหน่ง | ปัญหาและ failure scenario | การแก้ที่แคบที่สุด |
|---|---|---|---|
| Important | `scripts/generate-ci3-presentation-inventory.py:164` | `generated_at` ใช้เวลาปัจจุบัน ทำให้รันคำสั่งเดิมสองครั้งได้ JSON คนละ byte แม้ source ไม่เปลี่ยน จึงไม่ deterministic สำหรับ generated evidence | ตัด field ออกจาก canonical JSON หรือรับ timestamp ที่ caller กำหนดชัดเจนและ test ผลลัพธ์ซ้ำ |
| Important | `tests/wp00c/test_presentation_inventory.py:14-38` | test ถูก skip ได้เมื่อไม่มี CI3 checkout และเมื่อรันจริงตรวจเพียงจำนวน, 2 source และชนิดไฟล์ จึงไม่พิสูจน์ source set ว่าผูกกับ Git tracked tree หรือ pin การ implement ที่ hardcode 108 records ยังผ่านได้ | ห้าม skip gate นี้ใน environment ที่ claim ว่า Task 1 ผ่าน, เปรียบ source set กับ `git ls-files` ที่ filter suffix, และ assert CI3 `HEAD` กับ pin |

## หลักฐานที่รัน

| คำสั่งหรือการตรวจ | ผล |
|---|---|
| `git -C /Users/king_developer/Desktop/Project/samsoniteci3 rev-parse HEAD` | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` ณ เวลาตรวจ |
| Git tracked `.php` และ `.html` ใต้ `application/views` | 108 รายการ, HTML 5 รายการ |
| `python3 -m unittest tests/wp00c/test_presentation_inventory.py` | ผ่าน 1 test |
| ชุด `tests/wp00c` 4 ไฟล์ตาม brief | ผ่าน 10 tests |
| CI3 fixture ชั่วคราวที่มีไฟล์เดียว `application/views/untracked.php` | generator นับ 1 รายการ แสดงว่า path discovery ไม่ใช้ Git tracked list |
| รัน generator ซ้ำบน input เดิม | output bytes ไม่เท่ากัน เพราะ `generated_at` ต่างกัน |

## Missing และ extra behavior

### Missing behavior

- การยึด source list กับ Git tracked files
- การยืนยันและบันทึก CI3 source pin ใน generated evidence
- ผลลัพธ์ JSON ที่ทำซ้ำได้จาก input เดิม
- regression test ที่พิสูจน์ source set ทั้ง 108 รายการ ไม่ใช่เพียงจำนวนและตัวอย่าง 2 รายการ

### Extra behavior

- ไม่พบ behavior เกินขอบเขต Task 1 ที่เป็น frontend dependency change

## ขอบเขตที่ไม่ได้ตัดสิน

- รีวิวนี้ไม่ตัดสิน runtime, DOM, JavaScript หรือ visual parity เพราะ Task 1 ระบุ evidence เป็น static inventory และ JSON/Markdown ไม่ได้นำสิ่งเหล่านั้นมาอ้างเป็นผลผ่าน
