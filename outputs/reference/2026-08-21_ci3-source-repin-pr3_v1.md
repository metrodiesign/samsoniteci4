# CI3 Source Repin — PR #3

เอกสารนี้บันทึกการย้าย active CI3 source pin หลังแก้ Report Tracking status filter และระบุหลักฐานที่ต้องสร้างใหม่ก่อนปิด WP-00C, WP-00M หรือ Gate 1D.

```text
CI3_PIN=ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6
```

## Change record

| รายการ | ค่า |
|---|---|
| Repository | `metrodiesign/samsoniteci3` |
| Pull request | [#3 fix(order): validate report tracking status filter](https://github.com/metrodiesign/samsoniteci3/pull/3) |
| PR state | Merged |
| Merged at | `2026-08-21T15:19:25Z` |
| Previous pin | `8dad4e331a90f5c6765954454910b451eb0ff8e5` |
| Active pin | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` |
| Source commit | `cb1d81820d6d3188c79f99e0c17cfda195a75543` |
| GitHub required checks | ไม่มี check รายงานใน `statusCheckRollup` |

## Proven source change

PR #3 เปลี่ยน behavior ดังนี้:

- Controller แปลง `status_id` เป็น integer array ผ่าน shared parser.
- Empty, non-string และ malformed status กลายเป็น empty array.
- Model ใช้ Query Builder `where_in` แทน raw `IN` condition.
- Report routes สองจุดใช้ validation เดียวกัน.
- Regression test ครอบ empty, single, multiple และ injection-shaped input.

ผลนี้ยกเลิก temporary Docker build patch ใน migration workspace. CI4 ยังต้อง preserve corrected visible contract; ห้ามนำ SQL `IN ()` defect กลับมาเป็น parity requirement.

## Business decision: single Report Tracking page

อนุมัติวันที่ `2026-08-22`: CI4 มี `Report Tracking` route/page เดียว. `ReportTrackingListingTest` เป็น legacy characterization route ไม่ใช่หน้าธุรกิจแยก.

Approval evidence: ผู้ใช้ยืนยันข้อความ `Report Tracking ผ่านการตรวจ Business และ QA` ใน task วันที่ `2026-08-22`. Approval ครอบ business behavior และ visual/manual acceptance ของหน้า `ReportTrackingListing`; technical automation และ Gate 1D ยังต้องผ่านตาม execution order ด้านล่าง.

- Preserve corrected empty, single, multiple และ malformed status-filter behavior ในหน้า `ReportTrackingListing`.
- ไม่สร้าง route, page หรือ menu ชื่อ Test ใน CI4.
- เก็บ CI3 Test case จน parity evidence ผ่าน แล้วปิดเป็น `CONSOLIDATED_RETIRED`.
- QA ต้องตรวจว่า production page ครบ behavior และ CI4 ไม่มี Test route/menu.

## Evidence impact

| Artifact | สถานะหลัง repin | Action |
|---|---|---|
| Historical baseline/evidence ที่ pin `8dad4e3` | เก็บเป็นประวัติ | ห้ามแก้ผลย้อนหลัง |
| WP-00C catalog/runbook | Repinned | รัน suite ใหม่บน active pin |
| Docker rehearsal image | Obsolete | rebuild tag `samsonitetracking-ci3:ee1c95e` |
| Function disposition v2 | Historical | regenerate v3 เพราะมี private parser ใหม่และ line mapping เปลี่ยน |
| 34-endpoint menu smoke | Rebaselined on active pin | PASS `34/34`; HTTP 200, no login bounce/fatal/database error, DB checksums unchanged |
| Report Tracking filter evidence | Rebaselined on active pin | PASS empty `9` rows, single `2` rows, multiple `3` rows, malformed `9` rows; HTTP 200 และ DB checksums unchanged |
| Excel/Confirm evidence วันที่ 18–21 ส.ค. | Historical | rerun เฉพาะ gate ที่อ้าง active release pin |

## Execution order

1. Validate CI3 source SHA และ clean worktree.
2. Run upstream PHP lint และ `tests/report_tracking_status_regression.php`.
3. Build rehearsal image จาก active pin โดยไม่มี temporary patch.
4. Seed synthetic WP-00C fixtures.
5. Run Report Tracking empty, single, multiple และ malformed filter cases.
6. Rerun 34-endpoint GET smoke และตรวจ console/server logs.
7. Verify business DB rows ไม่เปลี่ยนจาก report requests.
8. Regenerate function disposition/workflow line mapping.
9. Rerun affected WP-00C/WP-00M gates ก่อนขอ Gate 1D approval.

## Gate status

- PR #3 source fix: `DONE`
- Migration image build: `DONE`, image ID `sha256:b1d2dc80ed680305bad8ad2769d56d483ffa976e8c3041cf6595234071851ba8`
- Runtime config repin: `PENDING`, set `CI3_WEB_IMAGE=samsonitetracking-ci3:ee1c95e` ใน local `.env`
- Active-pin behavior rebaseline: `DONE` สำหรับ Report Tracking และ 34-endpoint smoke บน synthetic fixture
- Function disposition regeneration: `DONE`, v3 live `1165/1165`, retired `247`, mismatch `0`, SHA-256 `2335b7ba5623536477597b22a3c77c5cc2c6115eb53e862b84da1289ffea4e2e`
- WP-00C, WP-00M และ Gate 1D: `OPEN`

## Verification completed

| Check | Result |
|---|---|
| CI3 source HEAD/clean | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`, clean |
| WP-00C catalog validation | PASS, routes 178, cases 53, fixture rows 116 |
| Upstream status regression | PASS บน host และ image |
| Controller/model PHP lint | PASS บน host และ image |
| Migration repository CI gate | PASS |
| Docker artifact forbidden paths | PASS |
| Report Tracking empty/single/multiple/malformed filters | PASS, HTTP 200; rows `9/2/3/9`; no fatal/database error |
| Report Tracking DB immutability | PASS, checksums ทั้ง 31 tables ไม่เปลี่ยนหลัง 4 requests |
| Authenticated menu endpoint smoke | PASS `34/34`; no login bounce/fatal/database error |
| Endpoint-smoke DB immutability | PASS, checksums ทั้ง 31 tables ไม่เปลี่ยน |
| Report Tracking manual approval | PASS, Business และ QA; user-confirmed `2026-08-22` |
| Report Tracking two-case baseline | `BASELINED_APPROVED` `2/2`; 15 HTTP requests, browser UI PASS, DB changed tables `0/31`; required roles complete |
| Function Disposition v3 | PASS static inventory; live `1165/1165`, retired `247`, missing/duplicate/hash/ID mismatch `0` |

WP-00C remaining assessment: `outputs/reference/2026-08-22_wp00c-remaining-assessment_v1.md`.

Known PHP 8.5 deprecation ที่ `Request_order_model.php:105` ยังอยู่และไม่เกี่ยวกับ PR #3; CI3 rehearsal runtime ใช้ PHP 7.4.
