# รายงาน strict CI3 presentation parity รอบล่าสุด

## ผลที่ยืนยันแล้ว

- CI3 authority: `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`
- runtime-required templates: `102`
- one-to-one dedicated copies: `102/102`
- dedicated templates ที่เชื่อมผ่าน `LegacyViewRenderer`: `13/102`
- dedicated runtime ที่ยังไม่เชื่อม: `89`
- inventory: `outputs/reference/2026-08-29_ci3-presentation-inventory_v6.json`

## Dashboard same-run

Dashboard ใช้ `app/Views/ci3/dashboard.php` ที่ runtime แล้ว ไม่ผ่าน adapted duplicate view

| แกน | ผล |
|---|---|
| normalized DOM | `PASS`, difference `0` |
| interaction: sidebar desktop/mobile | `PASS` ทั้ง CI3 และ CI4 |
| interaction: logout | `PASS` ทั้ง CI3 และ CI4 |
| desktop `1440x900`, DPR 1 | pixel-equal `PASS` |
| mobile `390x844`, DPR 1 | pixel-equal `PASS` |

สาเหตุ DOM mismatch เดิมไม่ใช่ presentation defect แต่เป็น fixture contamination: สองระบบใช้คนละฐานข้อมูลและมีค่า login history ล่าสุดต่างกัน Runner รอบใหม่สร้าง synthetic same-profile user แยกฝั่งและตรึง baseline history เท่ากัน จึงเปรียบเทียบ visible `Last Login` จริงโดยไม่ normalize text ทิ้ง

หลักฐานอยู่ที่ `evidence/strict-parity/dashboard/` และเกิดจาก capture run เดียวภายใต้ CI4 production mode เพื่อไม่ให้ debug toolbar ปน DOM

## สถานะรวม 102 views

| แกน | PASS | FAIL | NOT_VERIFIED |
|---|---:|---:|---:|
| normalized DOM | 1 | 0 | 101 |
| browser interaction | 1 | 0 | 101 |
| desktop/mobile visual | 1 | 0 | 101 |

## งานที่ยังปิดไม่ได้

- อีก `89` dedicated templates ยังไม่มี controller/view-model adapter ที่ใช้ target นั้นจริง
- อีก `101` views ยังไม่มี current same-run DOM/interaction/desktop/mobile evidence
- template denominator มี partial, email, export และ CLI/framework error views ซึ่งไม่มี browser URL อิสระ ต้องกำหนด scenario ตาม caller/runtime ชนิดนั้น ไม่สามารถอ้าง screenshot route ซ้ำเป็นหลักฐานราย target ได้
- ห้ามนับ byte-identical copy เป็น runtime, DOM หรือ visual PASS

`WP00C_TEST_PASSWORD` ไม่เป็น blocker แล้ว: runner โหลดจาก `.pipeline/wp03e-visual/.secret-env` โดยไม่พิมพ์ค่า และใช้ `scripts/prepare-parity-users.sh` เตรียม synthetic parity users

STATUS: BLOCKED
