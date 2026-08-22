# WP-00C Full Behavior Catalog — Execution Runbook v1

เอกสารนี้อธิบายขั้นตอนสร้าง CI3 behavior baseline แบบรันซ้ำได้ พร้อม synthetic fixture, scenario catalog, safety gate, evidence และ approval. ชุดข้อมูลพร้อม seed/verify/cleanup แล้ว แต่ behavior runner ต้องเริ่มหลังยืนยัน test seams.

## สถานะพร้อมใช้

| รายการ | ค่า |
|---|---|
| CI3 source | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` |
| Source change | PR [metrodiesign/samsoniteci3#3](https://github.com/metrodiesign/samsoniteci3/pull/3), merged 2026-08-21 |
| Explicit routes | 178 routes ครบ coverage rule |
| Scenario groups | 53 cases |
| Domains | 14 domains |
| Synthetic DB rows | 116 rows |
| Fixture users | 4 users: active 3, deleted 1 |
| Fixture orders | 9 orders, statuses 1–8 ครบ |
| Branches | 2 branches |
| Production PII | ไม่มี |
| Production credential | ไม่มี |
| Email/SMS | production transport ถูกปิด; ใช้ loopback stub เท่านั้น |
| Fixture lifecycle | seed → verify → clean ทดสอบผ่าน |

ไฟล์หลัก:

- `tests/wp00c/catalog.json` — cases, routes, expected result และ approval role
- `tests/wp00c/fixtures.json` — synthetic rows, files, provider states และ literal totals
- `scripts/wp00c-kit.py` — validate และ render fixture SQL
- `db/dbctl.sh` — isolated seed/verify/cleanup commands

## Test seams ที่ต้องยืนยัน

TDD เริ่ม behavior tests หลังผู้ใช้ยืนยัน seams ต่อไปนี้:

| Seam | สิ่งที่ทดสอบผ่าน | สิ่งที่ไม่นับเป็นผลผ่าน |
|---|---|---|
| HTTP/UI | CI3 route, method, session, redirect, DOM และข้อความที่ผู้ใช้เห็น | เรียก controller/model method ตรง |
| DB side effect | query reconciliation หลัง real HTTP request | INSERT/UPDATE model ตรงแทน request |
| File | upload/download bytes, headers, rows, cells และ cleanup | screenshot อย่างเดียว |
| Outbound stub | Email/SMS request ไป loopback stub พร้อม redacted payload | ส่ง provider/recipient จริง |
| Performance | request profile, p50/p95, memory, query count/plan | timing ครั้งเดียว |
| Recovery | cleanup, restore, source SHA และ dirty count | ลบข้อมูลกว้างโดยไม่มี fixture state |

ข้อความอนุมัติที่ใช้ได้:

```text
ยืนยัน WP-00C test seams: HTTP/UI จริง, session/redirect/DOM, DB/file side effects, Email/SMS ผ่าน loopback stub, performance observation และ cleanup/restore; ห้ามเรียก controller/model ตรงและห้ามใช้ข้อมูลหรือ provider จริง
```

## ชุดข้อมูลที่เตรียมแล้ว

### ผู้ใช้ทดสอบ

| Username | Role | Branch | ใช้ทดสอบ |
|---|---:|---:|---|
| `wp00c-admin` | 1 | กลาง | admin, all-branch, reports, master data |
| `wp00c-a` | 2 | 1 | branch A, own-data paths |
| `wp00c-b` | 2 | 2 | branch B, isolation paths |
| `wp00c-deleted` | 3 | 1 | deleted-user login denial |

รหัสผ่านไม่เก็บใน repo, evidence หรือ log. ผู้รันส่งค่าผ่าน `WP00C_TEST_PASSWORD` ตอน seed; fixture users ทั้งหมดใช้ hash จากค่านั้น.

### ข้อมูลธุรกิจ

| กลุ่ม | ข้อมูล |
|---|---|
| Branch/master | branch type, branch, book, brand, product type, condition, estimate, fixed, provider อย่างละ 2 |
| Status | `statusaction` 1–8 และ import status 2 ค่า |
| Orders | 9 แถว; status 1–8, branch 1/2, valid master และ missing-master edge |
| Timeline | 10 status logs; `WP00C-TRACK-005` มี 4 rows |
| Contact | 1 synthetic contact |
| Rating | 8 scores, sum 27 และ 1 comment |
| Reset password | valid synthetic token 1 และ old synthetic token 1 |
| CMS | background record 1 |
| Files | valid/malformed Excel profiles และ valid/invalid image profiles |
| Provider | success, reject, timeout และ duplicate stub states |
| Menu | 10 groups, 35 links และ 4 role mappings ตาม CI3 code/baseline counts |

Menu labels, links และ order อ้างอิง CI3 source กับ visual baseline. Role mappings ทั้ง 4 แถวเป็น synthetic test scope ไม่ใช่หลักฐาน production authorization; authorization cases ต้องตรวจ server-side access แยกจาก sidebar visibility.

### Expected literals

| Assertion | Expected |
|---|---:|
| Total fixture rows | 116 |
| Active/deleted users | 3 / 1 |
| Branch 1/2 orders | 6 / 3 |
| Status-2 orders | 2 |
| Tracking timeline rows | 4 |
| Rating rows/score sum | 8 / 27 |
| Import temp/final rows ก่อนทดสอบ | 0 |
| Email/SMS attempts ก่อนทดสอบ | 0 |

## ขั้นตอนเตรียม environment

### 1. ตรวจ source และ catalog

รันจาก repository root:

```bash
python3 scripts/wp00c-kit.py validate \
  --source-root /Users/king_developer/Desktop/Project/samsoniteci3
```

ต้องได้:

```text
PASS WP-00C kit: routes=178 cases=53 fixture_rows=116
```

Source pin นี้แก้ Report Tracking status filter แล้ว. Baseline รอบใหม่ต้องทดสอบค่าว่าง, ค่าเดี่ยว, หลายค่า และ malformed input ตาม case `RPT-TRACKING-TEST-001`; ห้ามใช้ผล `ROUTE-DEAD-001` จาก pin เดิมแทน. ตาม Business decision วันที่ 2026-08-22 CI4 ต้องรวม behavior นี้ไว้ใน `ReportTrackingListing` หน้าเดียว และไม่มี Test route/page/menu.

คำสั่งหยุดทันทีเมื่อ CI3 SHA เปลี่ยน, worktree dirty, route count ต่าง, case ID ซ้ำ, fixture reference หาย หรือพบค่าเหมือน PII.

### 2. กำหนด runtime และ evidence

```bash
RUNTIME_ROOT=/Users/king_developer/Desktop/Project/samsonitetracking-ci4-migration
export DBCTL_ENV_FILE="$RUNTIME_ROOT/.env"
export DBCTL_EVIDENCE_DIR="$RUNTIME_ROOT/evidence/db-foundation-001"
```

ห้ามพิมพ์ค่า `.env` หรือส่ง credential ผ่าน command argument.

### 3. รับ test password โดยไม่แสดงบนหน้าจอ

```bash
WP00C_TEST_PASSWORD="$(python3 -c 'import getpass; print(getpass.getpass("WP-00C test password: "))')"
export WP00C_TEST_PASSWORD
```

ข้อกำหนด: 12–32 characters, ใช้เฉพาะ isolated WP-00C runtime, ห้าม reuse production password.

### 4. Seed synthetic fixtures

```bash
./db/dbctl.sh --runtime-root "$RUNTIME_ROOT" wp00c-fixture-seed
```

Safety gate ก่อน seed:

1. CI3 source ตรง pin และ clean.
2. Schema มี 31 tables.
3. Database row count เท่ากับ 0.
4. Web container ไม่มีอยู่ก่อนเริ่ม.
5. CI3 image ถูก build ใหม่จาก source pin โดยค่าเริ่มต้น.
6. Outbound Email/SMS functions ถูก disable.
7. Password ถูก hash ใน container; plaintext ไม่ถูกเขียนลงไฟล์.

กรณี sandbox build image ไม่ได้ อนุญาต reuse image local แบบ explicit เท่านั้น:

```bash
export WP00C_REUSE_CI3_IMAGE=1
```

ใช้ได้เมื่อผู้ทดสอบตรวจ image source เองแล้ว. State file จะบันทึก image ID สำหรับ evidence. ห้ามตั้งค่านี้ใน CI ปกติ.

### 5. Verify fixtures

```bash
./db/dbctl.sh --runtime-root "$RUNTIME_ROOT" wp00c-fixture-verify
```

ต้องผ่าน table counts 23 ตาราง, total 116 rows และ literal assertions ทั้งหมด. Fail หนึ่งข้อให้หยุด behavior test.

## ลำดับรัน behavior catalog

ทำ vertical slice ทีละกลุ่ม. ทุก slice ใช้ real HTTP/UI seam, เก็บผล แล้ว cleanup/restore ก่อน slice ที่ mutation ใหญ่.

### Slice 1: Routing และ Authentication

Case groups:

- `ROUTE-EXPLICIT-001` — 178 route reconciliation
- `ROUTE-IMPLICIT-001` — implicit/default-route discovery
- `ROUTE-404-001` และ `ROUTE-DEFECT-001` — known route defects
- `AUTH-LOGIN-001/002` — valid/invalid/deleted login
- `AUTH-SESSION-001` — anonymous, timeout, logout
- `AUTH-RESET-001/002` — password reset success/failure/old/reused token

ตรวจ HTTP status, redirect, session keys, `tbl_last_login`, reset rows และ email-stub attempts.

### Slice 2: User และ Authorization

Case groups:

- `USER-CRUD-001/002` — list/add/edit/delete/validation
- `USER-PASSWORD-001` — password outcomes
- `USER-HISTORY-001` — login history และ AJAX lists
- `AUTHZ-ROUTE-001` — route × role
- `AUTHZ-BRANCH-001` — route/object × branch

ห้ามใช้การซ่อนเมนูแทน authorization assertion. Direct URL และ direct object ID ต้องอยู่ใน matrix.

### Slice 3: Order lifecycle

Case groups:

- `ORDER-LIST-001` — status listings 1–8
- `ORDER-CREATE-001/002` — valid/invalid/upload create
- `ORDER-LIFECYCLE-001/002` — normal/direct/partial transitions
- `ORDER-EDIT-001` — print/edit/delete
- `ORDER-CONCURRENCY-001` — simultaneous tracking allocation

ตรวจ order row, status/date fields, status log, generated ID, file effect และ SMS-stub attempts.

### Slice 4: Public site

Case groups:

- `TRACK-PUBLIC-001`, `TRACK-NEGATIVE-001`, `TRACK-CONCURRENCY-001`
- `CONTACT-PUBLIC-001`, `CONTACT-FAILURE-001`, `CONTACT-LIST-001`
- `RATING-PAGE-001`, `RATING-SUBMIT-001`, `RATING-NEGATIVE-001`

ตรวจ EN/TH DOM, timeline order, not-found result, contact/email side effects, rating rows และ status 5→7.

### Slice 5: Excel import

Case groups:

- `XLS-PREVIEW-001`
- `XLS-CONFIRM-001`
- `XLS-NEGATIVE-001`
- `XLS-REPLAY-001`
- `XLS-ISOLATION-001`
- `XLS-CSRF-001`

Preview/Confirm success evidence เดิม reuse ได้เมื่อ fixture/source/runtime identity ตรง. Malformed, partial, simultaneous และ CSRF cases ต้องรันเพิ่ม.

### Slice 6: Master data และ CMS

Case groups:

- `MASTER-CRUD-001` — parameterized 10 controllers
- `MASTER-REFERENCE-001` — duplicate/referenced deletion
- `MASTER-UPLOAD-001` — upload validation
- `MASTER-MENU-001` — group/menu/sidebar
- `MASTER-BACKGROUND-001` — DB/file/public-view behavior

ทุก controller ต้องผ่าน list/add/edit/delete/invalid form. Upload test ห้าม execute fixture file.

### Slice 7: Reports และ exports

Case groups:

- `RPT-DASHBOARD-001`
- `RPT-MATRIX-001`
- `RPT-TRACKING-001`
- `RPT-SUMMARY-001`
- `RPT-EXPORT-001`
- `RPT-EDGE-001`

ตรวจ exact rows/totals/order/filename/headers/cells. Screenshot ไม่แทน machine-readable totals.

### Slice 8: Integrations, Performance และ Recovery

Case groups:

- `INTEGRATION-EMAIL-001`
- `INTEGRATION-SMS-001`
- `PERF-CI3-001`
- `RECOVERY-FIXTURE-001`

Provider ใช้ loopback stub เท่านั้น. Performance ใช้ approved sanitized volume profile; synthetic 116 rows ใช้ functional correctness ไม่ใช่ capacity benchmark.

## Evidence ต่อ test case

ทุก case ต้องสร้าง record ต่อไปนี้:

| Field | บังคับ |
|---|---|
| Identity | Test ID, CI3 SHA, image ID, DB identity, fixture version/hash |
| Given | actor, role, branch, DB/file/stub state |
| When | method, URL, input และ request order |
| HTTP/UI | status, redirect, normalized DOM/message |
| Data | exact before/after rows และ business totals |
| File | checksum, headers, rows/cells, cleanup |
| Integration | redacted payload, request ID, attempts, timeout/retry |
| Comparator | exact/semantic/ordered rule |
| Result | PASS, FAIL, BLOCKED หรือ NOT_RUN |
| Approval | tester, timestamp, Business/QA/Security/DBA ตาม impact |

Release baseline ยอมรับเฉพาะ PASS. BLOCKED/NOT_RUN ใช้ระบุงานค้าง ห้ามนับผ่าน.

## Determinism protocol

1. Seed fixture versionเดียวกัน.
2. รัน catalog ตามลำดับเดิม.
3. เก็บ evidence และ SHA-256.
4. Cleanup/restore.
5. ยืนยัน DB rows=0 และ CI3 source unchanged.
6. ทำซ้ำครบ 3 รอบ.
7. Diff machine-readable results.
8. ต่างเฉพาะ timestamp/session/random value ที่มี approved normalization.

Expected output, DB totals, report rows และ business state ห้าม normalize ทิ้ง.

## Cleanup

หลังทดสอบ:

```bash
./db/dbctl.sh --runtime-root "$RUNTIME_ROOT" wp00c-fixture-clean
unset WP00C_TEST_PASSWORD
```

Cleanup ทำงานเมื่อมี fixture state file เท่านั้น. ลบเฉพาะ synthetic namespace, ตรวจ database rows=0, remove web container และยืนยัน CI3 source SHA/dirty count.

## Decision และ approval ที่ต้องปิด

| เรื่อง | ผู้ตัดสิน | ค่าแนะนำ |
|---|---|---|
| Price Confirm replay | Business/QA | idempotent success, no extra write |
| Status/New Order replay | Business/QA/Security | Correct and re-baseline |
| Missing CSRF | Security/QA | Correct and re-baseline |
| Cross-user/cross-branch access | Business/Security/QA | Deny and re-baseline |
| Invalid order transition | Business/QA/Security | Server-side transition policy |
| Old reset token | Security/Business | TTL + single use |
| Rating page redirect | Business/QA | Correct page contract or approved retirement |
| Referenced master delete | Business/DBA/QA | Approved hard/soft-delete policy |
| Report missing-master join | Business/DBA/QA | Preserve omission or correct totals |
| Background CMS behavior | Business/QA | DB-driven correction or retained file contract |

## Definition of done

WP-00C ปิดเมื่อครบทุกข้อ:

1. Test seams ลงนาม.
2. Explicit/implicit route inventory classified 100%.
3. Cases 53 กลุ่มแตกเป็น runnable Test IDs ครบทุก parameter variant.
4. Synthetic fixtures และ expected literals ลงนาม.
5. Full CI3 suite รัน deterministic 3 รอบ.
6. Expected HTTP/UI/data/file/integration/performance results ครบ.
7. Known defects มี preserve/correct/retire decision ครบ.
8. Business/QA และ affected Security/DBA approvals ครบ.
9. FAIL/BLOCKED/NOT_RUN/unapproved diff เท่ากับ 0.
10. Evidence hash, cleanup และ source-integrity proof ผ่าน.

จนกว่าจะครบ ห้ามประกาศ CI3 parity 100% และห้ามใช้ WP-00C ปิด WP-00M/Gate 1D.
