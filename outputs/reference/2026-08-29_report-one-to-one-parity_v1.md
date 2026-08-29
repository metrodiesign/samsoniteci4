# หลักฐาน one-to-one parity หน้า `/user/report`

## Target เฉพาะ

| รายการ | ค่า |
|---|---|
| CI3 authority | `application/views/report.php` @ `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` |
| CI4 target | `app/Views/ci3/report.php` |
| template identity | SHA-256 ตรงกัน |
| CI4 runtime caller | `Reports::matrix('ratings')` ผ่าน `LegacyViewRenderer::render('report')` |
| route | `GET/POST /user/report` |

`report.php` ไม่แชร์ target กับ report view อื่น และ `reports/matrix.php` ไม่ได้ render หน้า ratings อีกต่อไป

## Same-run scenario

ทั้งสองระบบ login ด้วย synthetic same-profile user แล้ว POST caller จริงที่ `/user/report` ด้วยข้อมูลเดียวกัน:

- `branch_id=0`
- `start_date=01/01/2099`
- `end_date=02/01/2099`

ช่วงอนาคตทำให้ data panel และ comment table อยู่ใน empty-state เดียวกันโดยไม่แก้ฐานข้อมูล หลัง submit ตรวจ interaction contract ได้แก่ form action, date values, export path, rating panels, comments table และ sidebar toggle

## ผล

| แกน | ผล |
|---|---|
| dedicated runtime target | PASS |
| normalized rendered DOM | PASS, difference `0` |
| interaction | PASS ทั้ง CI3 และ CI4 |
| visual `1440x900`, DPR 1 | pixel-equal PASS, ภาพเต็มหน้า `1440x1884` |
| visual `390x844`, DPR 1 | pixel-equal PASS, ภาพเต็มหน้า `390x3540` |

หลักฐานอยู่ที่ `evidence/strict-parity/report/`:

- `report__ci3.html`, `report__ci4.html`
- `report__ci3__1440x900.png`, `report__ci4__1440x900.png`
- `report__ci3__390x844.png`, `report__ci4__390x844.png`
- `dom-result.json`, `interaction.json`, `visual-result.json`

DOM allowlist ที่ `scripts/report-dom-allowlist.json` จำกัดเฉพาะ loopback port, local mirror ของ DataTables/FixedColumns, hidden CI4 CSRF field และ width ที่ DataTables คำนวณหลัง layout เท่านั้น ทุก rule ต้องถูกใช้ ไม่เช่นนั้น comparator fail closed

## คำสั่งทำซ้ำ

```bash
docker compose -f compose.yaml -f compose.parity.yaml build ci4
docker compose -f compose.yaml -f compose.parity.yaml up -d --force-recreate ci4
set -a
source .pipeline/wp03e-visual/.secret-env
set +a
bash scripts/prepare-parity-users.sh
node scripts/capture-report-parity.mjs
php scripts/compare-runtime-dom.php \
  --left evidence/strict-parity/report/report__ci3.html \
  --right evidence/strict-parity/report/report__ci4.html \
  --page report \
  --allowlist scripts/report-dom-allowlist.json
python3 scripts/compare-visual.py \
  --directory evidence/strict-parity/report \
  --page report \
  --viewport 1440x900 \
  --viewport 390x844 \
  --output evidence/strict-parity/report/visual-result.json
```

STATUS: DONE
