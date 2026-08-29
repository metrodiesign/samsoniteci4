# Brief แก้ Task 7 รอบ 1

แก้ findings ที่รอด refute ของ Task 7 โดยรักษา CI3 presentation authority และ security adaptation ของ CI4 ห้าม commit, stage, push หรือแก้ไฟล์นอกขอบเขต finding

## เอกสารที่ต้องอ่าน

1. `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-review.md`
2. `docs/superpowers/plans/2026-08-27-tpl00-tpl01-foundation.md` เฉพาะ Task 7
3. `docs/superpowers/specs/2026-08-27-strict-ci3-template-preservation-design.md`
4. CI3 source ที่ commit `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`
5. current CI4 upload flow, `OrderImageStore`, routes, filters, create/edit views และ tests

## คำตัดสินที่ผูกงาน

### Upload interaction

สร้าง compatibility seam ขั้นต่ำที่ทำให้ CI3 file select, preview, progress และ cancel/delete contract ใช้งานได้จริง

- route ต้อง explicit, POST, authenticated และผ่าน CSRF
- reuse validation, random naming และ storage policy ของ `OrderImageStore`; ห้ามคัด raw CI3 handler
- ห้าม path traversal, client-controlled destination หรือ public raw filename
- คืน CI3 selectors/global ที่ browse chainต้องใช้ ได้แก่ `#upload`, `#drop`, preview `<ul>` และ `xtimesite`
- ต้องเชื่อม pre-upload กับ final create/edit persistenceอย่างถูกต้อง; ห้ามสร้าง orphan หรือบันทึกภาพซ้ำ
- error/abort ต้องไม่ทำให้ main order write partial
- ใช้ exact pinned browse assets; ห้าม rewrite third-party หรือ `script.js`

### Conditional validation scripts

CI3 มี active callerแล้ว

- central/admin callerต้องได้ behavior ของ exact pinned `admin_addOrder.js` ผ่าน compatibility adapterเฉพาะ selector/field-name seamที่จำเป็น
- branch callerต้องคง exact pinned `addOrder.js` และ known parse defect เพราะยังไม่มี signed correction
- load scriptตาม caller conditionเดียวกับ CI3
- ห้ามแก้ bytesของสอง CI3 scriptsเพื่อทำให้ทันสมัย
- เพิ่ม testพิสูจน์ conditional selection และ adapter contract

### Order hierarchy

ให้ `layout_order.php` output page contentตรง boundary ระหว่าง adapted `header_order.php` และ `footer_order.php`

- ห้ามสร้าง generic `.content-wrapper`, generic page heading หรือ `.content` รอบ `$content`
- page-specific viewเป็นเจ้าของ content wrapper/headerตาม CI3
- ปรับ current simplified create/edit viewsเท่าที่จำเป็นเพื่อให้ DOM validและเตรียมรับ exact TPL-06 content

### Evidence และ leakage tests

- ย้าย checksum, provenance และ license classification ของ Task 7 ไป tracked evidenceใต้ `outputs/reference/**`
- ตัด ignored SDD reportออกจาก tracked closure/candidate
- เพิ่ม negative order-asset assertionsสำหรับ `/ReportTrackingListing` และ `/orders/{id}/print`

## TDD และ verification

1. เขียน failing testsขับทุก branch ก่อนแก้ production code
2. เก็บ RED outputใน report
3. แก้ขั้นต่ำตาม root cause
4. รัน focused tests, full PHPUnit, PHPStan, JavaScript syntax checks และ `scripts/ci-check.sh`
5. ใช้ temporary Git indexเมื่อ asset tracked gateต้องจำลอง checkpoint; ห้ามเปลี่ยน real index
6. ตรวจ `git diff --check`; exact pinned filesที่มี whitespaceเดิมให้ใช้ path-scoped Git whitespace ruleโดยไม่ rewrite bytes
7. browser interactionต้องรันบน current source identityถ้าสร้าง synthetic local stateได้โดยไม่อ่าน/เปิดเผย credential; ถ้าถูก permission block ให้รายงาน `BLOCKED` ตามจริง ห้ามเดา

## Coverage self-check

รายงานต้องไล่ findingsทั้งหมด, testsที่ขับ branchจริง, mutation-checkของ upload auth/CSRF/validation/association และช่องว่างที่เหลือ ห้ามประกาศ PASS ถ้า browserหรือ full gateยังไม่ผ่าน

## Report

เขียนรายงานภาษาไทยที่:

`.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix1-report.md`

ตอบกลับสั้น ๆ เฉพาะสถานะ, รายชื่อไฟล์ที่แก้, test summary และ concerns ห้ามแปะ diffยาว
