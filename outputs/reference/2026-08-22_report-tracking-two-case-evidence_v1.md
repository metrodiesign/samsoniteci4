# Report Tracking Two-Case Evidence

เอกสารนี้ seal CI3 baseline ของ `RPT-TRACKING-TEST-001` และ `RPT-TRACKING-001` บน active pin. ใช้ local synthetic fixture เท่านั้น; outbound email/SMS ปิดตลอดการทดสอบ.

## Verdict

**Verdict: BASELINED_APPROVED `2/2`.** HTTP/UI, filter, search, date, branch, day-value, security และ DB immutability assertions ผ่าน; required roles ลงนามครบวันที่ `2026-08-22`.

CI4 target absence/parity เป็น downstream verification: CI4 ต้องไม่มี Test route/page/menu และหน้าเดียวต้องผ่าน comparator เดิมก่อนเปลี่ยน disposition เป็น retired verified.

## Identity

| Field | Value |
|---|---|
| CI3 pin | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` |
| CI3 image | `samsonitetracking-ci3:ee1c95e` |
| Database | MariaDB `11.4.12`, 31 tables |
| Fixture | `WP00C-FIXTURE-2026-08-21-v1` |
| Kit hash | `0083dcc2d42dfeab0975bd07633be786d174ddbb742171e50c308f5096c29175` |
| Runner | `scripts/wp00c-report-tracking.py` |
| Test date | `2026-08-22` |

## HTTP matrix

| Scenario | Route | Expected ordered tracks | Result |
|---|---|---|---|
| Empty status | Test/Main | `009` ถึง `001` | PASS, 9 rows, HTTP 200 |
| Single status `2` | Test/Main | `009,002` | PASS, 2 rows, HTTP 200 |
| Multiple status `2,3` | Test/Main | `009,003,002` | PASS, 3 rows, HTTP 200 |
| Malformed status | Test/Main | `009` ถึง `001` | PASS, 9 rows, HTTP 200, filter omitted |
| Exact search | Main | `004` | PASS, 1 row |
| Date `03–05/08/2026` | Main | `005,004,003` | PASS, 3 rows |
| Route branch 1 | Main | `006` ถึง `001` | PASS, 6 rows |
| Route branch 2 | Main | `009,008,007` | PASS, 3 rows |
| Session branch 1 | Main | `006` ถึง `001` | PASS, 6 rows |
| Session branch 2 | Main | `009,008,007` | PASS, 3 rows |
| Page parameter `25` | Main branch 1 | `006` ถึง `001` | PASS baseline quirk: 6 rowsยังอยู่, first No = `26` |

รวม automated HTTP requests `15`; fatal/database error/login bounce `0`.

## Calculated values และ UI

| Check | Expected | Result |
|---|---|---|
| `WP00C-TRACK-007` TotalDay | `2` | PASS |
| `WP00C-TRACK-007` CMGTotalDay | `0` | PASS |
| Browser multiple-status rows | `009,003,002` | PASS |
| Browser status/date rendering | synthetic status และ fixture dates | PASS |
| Browser console warnings/errors | `0` | PASS |

Business/QA manual approval บันทึกแล้วจากผู้ใช้ข้อความ `Report Tracking ผ่านการตรวจ Business และ QA` วันที่ `2026-08-22`.

## Security และ side effects

| Check | Result |
|---|---|
| Malformed input `2) OR 1=1 --` | PASS; parser คืน empty filter, rowsเท่าค่าว่าง |
| Shared parser regression | PASS; raw status `IN` condition ไม่ถูกส่งเข้า Query Builder |
| DB checksums ระหว่าง report requests | PASS; changed tables `0/31` |
| Runner cleanup | PASS; password hashes และ login-history rows คืนสภาพเดิม |
| Non-synthetic recipients | PASS; `0` |
| Outbound transport | PASS; `mail()` disabled, `allow_url_fopen` off, `sendmail_path=/bin/false` |
| Web/server fatal, database error, exception | PASS; `0` |

## Approval status

| Case | Required roles | Recorded | Pending |
|---|---|---|---|
| `RPT-TRACKING-TEST-001` | Business, Engineering, QA, Security | `BASELINED_APPROVED`; Business/QA เดิม, Engineering technical PASS, Security approved `2026-08-22` | ไม่มีสำหรับ CI3 baseline |
| `RPT-TRACKING-001` | Business, QA, DBA | `BASELINED_APPROVED`; Business/QA เดิม, DBA approved `2026-08-22` | ไม่มีสำหรับ CI3 baseline |

ผู้ลงนาม Security/DBA: `Software Engineer`; approval มาจากข้อความผู้ใช้ใน task วันที่ `2026-08-22`.

ห้ามอ้าง CI4 parity หรือ Test-route retirement จนมี CI4 after evidence.
