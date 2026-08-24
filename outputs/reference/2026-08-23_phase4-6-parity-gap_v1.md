# Phase 4-6 Parity Gap Analysis (v1)

ผลเทียบ behavior CI3 (pin `ee1c95e`) กับ CI4 ปัจจุบัน ต่อ WP-04A/04B/04C/05A/05B/05C/06A/06B — หลักฐาน file:line จากผลสำรวจ researcher 2 ชุด (2026-08-23) claim น้ำหนักสูงถูกสุ่มตรวจซ้ำกับ source จริงแล้ว (transition matrix, print view, trackID format, ไม่มี SMS transport จริง)

## สรุปสถานะต่อ WP

| WP | สถานะ parity | gap ที่เหลือ | ขนาดงาน |
|---|---|---|---|
| WP-04A Tracking ID | atomic generator แก้ TOCTOU ของ CI3 แล้ว (`OrderSequence` FOR UPDATE + probe คู่ขนานใน gate) | format เปลี่ยนเป็น `G{yy}{mm}{seq4}` (CI3 = branch-prefixed) ต้อง approve; ไม่มี UNIQUE index บน `trackID` ระดับ DB | decision + small |
| WP-04B Order lifecycle | create/edit/soft-delete/replay-guard ครบมี test; transaction atomic | transition matrix ไปได้แค่ 1→2→{3,4}→5 — **status 6/7 (complete) หายทั้ง flow**; print view เหลือ 5 field ไม่ผ่าน AC-ORD-011 | large ทั้งคู่ |
| WP-04C Notifications | queue/retry/encrypt/no-secret-log ดีกว่า CI3; email ไม่มีทั้งคู่ (parity ตรง) | ไม่มี production SMS transport (loopback เท่านั้น); trigger เหลือแค่ตอน create (CI3 มี 3 จุด); ไม่มี manual resend + max-retry cap | large (รอ vendor decision) |
| WP-05A Spreadsheet | custom `XlsxReader` ปลอด dependency, MIME+extension+malformed test ครบ เข้มกว่า CI3 | ไม่รับ `.xls` legacy; cap ใหม่ 501 แถว / 5 MB ที่ CI3 ไม่เคยมี — ต้องยืนยันกับ usage จริง | decision |
| WP-05B Batch isolation | defect shared temp table แก้แล้ว: batch_id + owner + state machine + probe 2-owner ใน gate | ไม่มี | - |
| WP-05C File storage | CI4 ไม่เก็บไฟล์ต้นฉบับเลย เก็บแค่ SHA-256 (CI3 เก็บถาวรใต้ document root แบบไม่มี control) | ต้อง decision: audit trail ไฟล์จริง vs hash-only; `writable/uploads/` เป็น dead scaffolding | medium (ถ้าต้องเก็บ) |
| WP-06A Report queries | ratings matrix + summary parity ตรง รวม inner join brand/type (และปิด SQL injection ที่ CI3 มี) | **report 4 ตัวชื่อตรง CI3 แต่คำนวณคนละเรื่อง**: jobs-by-day, pending, pending-total, in-progress-average; summary search เหลือ 3 จาก 9 field (ดูหมายเหตุ) | large |
| WP-06B Export | format HTML-as-xls + header ตรง CI3 มี contract test | ไม่มี memory ceiling ทั้งคู่ (CI3 อัด `memory_limit=8048M`, CI4 ไม่ทำอะไรเลย = เสี่ยง fatal บน dataset ใหญ่) | medium |

## Gap เรียงตามความเสี่ยง

1. **WP-06A report semantic mismatch** — CI4 `ReportMatrix::matrix()` ใช้ชื่อ kind ตรงกับชื่อ method CI3 แต่เนื้อ query คนละ report: `jobs-by-day` CI3 คือ SLA same-day matrix ต่อ brand×type×warranty (`User.php:640-661`) แต่ CI4 คือ histogram นับงานต่อวัน (`ReportMatrix.php:57-62`); `pending` กับ `pending-total` ใน CI4 คืนผลเหมือนกันทุกตัวอักษร (`ReportMatrix.php:64`) ขณะที่ CI3 เป็น 2 report ต่างชนิด (job list กับ 3 ตัวเลขรวม); `in-progress-average` CI3 คือ status-bucket count แต่ CI4 คือ per-job days-elapsed list; `in-progress` CI4 ตัด multi-status filter ที่ CI3 ให้ user เลือกทิ้ง
2. **WP-04B status complete (6/7) หายทั้ง flow** — matrix ใน `OrderTransitionWorkflow.php:36-41` ไปได้ไกลสุดแค่ status 5 ทั้งที่ schema/route ยังอ้าง `date_complete` และ filter status=7 อยู่ — ผูกกับ SMS trigger ตอน status 5/7 ที่หายไปด้วย (CI3 ยิง 3 จุด: create, return, complete+rating link — `Order.php:746,859,864`; CI4 enqueue เฉพาะ create)
3. **WP-04B print order เหลือ placeholder** — `app/Views/order_print.php` 12 บรรทัด แสดง 5 field ดิบ เทียบ CI3 `print_order.php` ~1,260 บรรทัด (brand/type/condition/estimate/warranty/images/status label/วันที่) — ไม่ผ่าน AC-ORD-011
4. **WP-04C ไม่มี production SMS transport** — มีแต่ `LoopbackSmsTransport` (throw ถ้า production) queue/retry/audit พร้อมแล้วแต่ส่งจริงไม่ได้ สอดคล้อง `INTEGRATION-SMS-001` ใน WP-00C catalog ที่ `PREPARED_NOT_RUN`
5. **WP-06B ไม่มี memory ceiling ฝั่ง export** — CI4 ไม่ set `memory_limit` และเรียก `summary()` โดยไม่มี limit (`Reports.php:108-110`) — regression ด้าน resilience เทียบ CI3 ที่กันด้วยการอัด memory 8 GB
6. **WP-04A/04B schema hardening** — `trackID` มีแค่ `KEY` ไม่ใช่ `UNIQUE` (`db/local-schema-only.sql:236`) และ `(branchID,numberID)` เช็คแค่ app-level — เป็น schema change บนตาราง legacy ที่ coexist กับ CI3 ต้อง approve ร่วม
7. **WP-05A input contract แคบลง** — ปฏิเสธ `.xls`, cap 501 แถว / 5 MB (hardcode `XlsxReader.php:53`, `:15-27`) — ต้องยืนยัน usage จริงไม่เกิน

## ข้อยืนยันฝั่งดี (มีผลรันจริง)

- Defect ที่ charter ห้ามย้าย แก้ครบทั้ง 3 ตัวของ Phase 4-5: order replay (submission_id + unique `request_id` + transaction เดียว, test `OrderHttpTest.php:162-192`), transition replay (optimistic lock + `affectedRows()===1` + rollback, test `:227-246`), shared temp batch (batch_id + owner + state machine, probe 2-owner `ci4-concurrency-check.sh:192-209`)
- Tracking ID atomic: `OrderSequence` FOR UPDATE + reconcile + probe คู่ขนานผ่าน gate ทุกครั้ง
- Import security เข้มกว่า CI3: MIME+extension คู่, XXE guard ใน `XlsxReader`, malformed test เขียนศูนย์แถว
- SMS layer: payload เข้ารหัส, `__debugInfo()` redact, test ยืนยัน no-secret-log

## คำถามที่ต้องการคำตอบก่อน implement

| ประเด็น | ผู้ตัดสิน | ตัวเลือก |
|---|---|---|
| WP-06A: reproduce report semantic CI3 เป๊ะ (large) หรือรับ CI4 เป็น report ชุดใหม่ | Business | (ก) เขียน 4 report ใหม่ตาม CI3 / (ข) sign-off ชุดใหม่ |
| Status 6/7 + SMS trigger ที่หาย | Business/Engineering | คืน flow เต็ม / ตัด scope อย่างเป็นทางการ |
| Print order | Business | parity เต็มตาม CI3 / ลด scope |
| Tracking ID format `G` กลาง vs branch prefix เดิม | Business | ระบบภายนอกที่ parse prefix รับได้ไหม |
| SMS vendor + credential + retry cap + manual resend scope | Business/Ops | ThaiBulkSMS เดิม / เปลี่ยน; นโยบาย dead-letter |
| File retention ของไฟล์ import | Business/Security | เก็บไฟล์จริงใน private volume / hash-only |
| `.xls` + row/size cap | Business | บังคับ `.xlsx` / เพิ่ม `.xls` support; ยืนยัน cap 501 แถว |
| UNIQUE index `trackID` + `(branchID,numberID)` | Engineering/DBA | schema change ร่วม CI3 coexistence |

## หมายเหตุ

- WP-05B ปิดได้เลยเมื่อ business sign-off — evidence ครบทั้ง state machine และ concurrency probe
- คำถามค้างเรื่อง `join(..., 'inner')` ของ `summary` ปิดแล้ว (ตรวจซ้ำ 2026-08-23 รอบหลัง): CI3 `reportsummary` ก็ inner join กับ brand/type เหมือนกัน (`Request_order_model.php:793-794`) — parity ตรง ไม่มี gap
- เจอ gap เล็กเพิ่มจากการตรวจซ้ำ: search ของ `summary` CI3 ครอบ 9 field (`trackID`, `orderID`, `customerFullname`, `detailSKUName`, `orderIDShow`, `branch_name`, `customerTel`, `customerEmail`, `status_name` — `Request_order_model.php:799-807`) แต่ CI4 ครอบแค่ 3 field (`trackID`, `orderIDShow`, `customerFullname` — `ReportMatrix.php:124-126`) — user ที่เคยค้นด้วยเบอร์โทร/อีเมล/ชื่อสถานะจะหาไม่เจอ ขนาดงาน small รวมเข้า scope WP-06A
- Export test ปัจจุบัน (`ReportHttpTest.php:151`) ครอบแค่ contract ไม่ครอบ scale — งาน memory ceiling ควรมาพร้อม volume benchmark ของ WP-00C

## สถานะ ณ 2026-08-24 (ตรวจซ้ำบน develop)

ตรวจแต่ละ gap ในรายการ "Gap เรียงตามความเสี่ยง" จากโค้ดจริงบน `develop`:

- **Gap 1 (WP-06A report semantic mismatch)** — **ตรวจซ้ำบางส่วน / ยังไม่ยืนยันปิด**: PR #29
  (`wp06a-report-parity`) และ PR #32 (`report-parity-fixes`) merge แล้ว รวม commit `019e9dd`
  "match CI3 ratings default range and DATEDIFF day count"; `ReportMatrix::matrix()` ยังแยก kind
  `pending-total`/`jobs-by-day`/`in-progress-average`/`in-progress` (`app/Reporting/ReportMatrix.php:56-68`)
  รอบนี้ยังไม่ได้เทียบ semantic ราย kind กับ CI3 อย่างครบ จึงยังไม่ประกาศปิด — treat as partially open
  จนกว่าจะมี report parity sign-off
- **Gap 2 (WP-04B status complete 6/7 + SMS trigger)** — **ปิดเป็นส่วนใหญ่**:
  `OrderTransitionWorkflow` รองรับ transition `5 -> 7` และตั้ง `date_complete` เมื่อ complete
  (`app/Orders/OrderTransitionWorkflow.php:41,51,63-64`) และ enqueue SMS ทั้ง return (deliver) และ
  complete (`:84-87`, `OrderSmsMessages::completed`) — ต่อยอดจาก PR #28 (`wp04b-complete-flow`) และ
  WP-04D
- **Gap 3 (WP-04B print order เหลือ placeholder)** — **ปรับปรุงแล้ว**: `app/Views/order_print.php`
  ขยายจาก ~12 เป็น 158 บรรทัดผ่าน PR #31 (`wp04d-form-parity`) มี field ครบขึ้นมาก; ความครบ 1:1 เทียบ CI3
  `print_order.php` (~1,260 บรรทัด) ยังไม่ได้ตรวจ field-by-field รอบนี้
- **Gap 4 (WP-04C ไม่มี production SMS transport)** — **ปิดแล้ว (transport)**: มี
  `app/Orders/ThaiBulkSmsTransport.php` (production transport มี test `ThaiBulkSmsTransportTest`) คู่กับ
  `LoopbackSmsTransport` ผ่าน PR #30 (`wp04c-sms-transport`); การส่งจริงยังต้อง credential/sandbox
  (environment) จึงไม่นับเป็น code gap
- **Gap 5 (WP-06B ไม่มี memory ceiling ฝั่ง export)** — **ปิดแล้ว**: `Reports::export()` เรียก
  `ini_set('memory_limit', '8048M')` ก่อนดึงแถว (`app/Controllers/Reports.php:99-102`) parity กับ CI3
  ผ่าน PR #25 (`wp06b-export-memory`)
- **Gap 6 (WP-04A/04B schema hardening: trackID UNIQUE)** — **ยังเปิด**: `db/local-schema-only.sql:236`
  ยังเป็น `KEY \`trackID\` (\`trackID\`)` ไม่ใช่ `UNIQUE` — เป็น schema change บนตาราง legacy ที่ต้อง approve ร่วม
- **Gap 7 (WP-05A input contract แคบลง: ปฏิเสธ .xls, cap 501/5MB)** — **ยังเปิด (decision)**:
  `app/*/XlsxReader.php` ยัง cap 501 แถว (`:53`) และปฏิเสธไฟล์ที่ไม่ใช่ xlsx — เป็น decision item ต้องยืนยัน
  usage จริงไม่เกิน limit ไม่ใช่ bug

หมายเหตุ search (แก้ต่อจากข้อ 52): search ของ `summary` **ปิดแล้วใน PR #33 (`search-parity`)** — CI4 ครอบ
ครบ 9 field เท่า CI3 แล้ว (`trackID`, `orderID`, `customerFullname`, `detailSKUName`, `orderIDShow`,
`branch_name`, `customerTel`, `customerEmail`, `status_name`); เลขบรรทัดปัจจุบันคือ
`app/Reporting/ReportMatrix.php:350-354` (เลื่อนจาก `:124-126` ที่ข้อ 52 อ้าง) ยืนยันด้วย
`git log --oneline --grep="search" develop` (merge `aaf8049` PR #33)
