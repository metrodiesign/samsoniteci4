# Phase 2-3 Parity Gap Analysis (v1)

ผลเทียบ behavior CI3 (pin `ee1c95e`) กับ CI4 ปัจจุบัน รายงานเฉพาะ gap ที่เหลือ ต่อ WP-02A/02B/03A/03B/03C/03D/03E — หลักฐาน file:line เต็มอยู่ในผลสำรวจของ researcher 2 ชุด (2026-08-23)

## สรุปสถานะต่อ WP

| WP | สถานะ parity | gap ที่เหลือ | ขนาดงาน |
|---|---|---|---|
| WP-02A Public tracking | backend แก้ defect shared temp table แล้ว (stateless `TrackingLookup`) | แปลงปี พ.ศ./ค.ศ. ตาม AC-PUB-003 ยังไม่ทำ + ขาด concurrency test | small |
| WP-02B Contact/email | CSRF + idempotency แก้ครบมี test | validation-error UX หาย (AC-PUB-005): ผู้ใช้กรอกผิดเห็น raw JSON ไม่คงค่าฟอร์ม | medium |
| WP-03A Session | behavior ครบ cookie flags ดีกว่า CI3 | test gap 3 จุด: session regeneration, Set-Cookie assertion, expiry จำลองเวลา | small |
| WP-03B Authorization | IDOR cross-branch 3 จุดแก้ครบมี regression test | decision item: role 3 เคยเขียนได้เพราะ dead-code bypass ใน CI3 — CI4 ปิดถูกต้องแต่ต้อง Business sign-off | small |
| WP-03C Master data | CRUD 10 entity ครบ + delete guard 409 ดีกว่า CI3 | decision เดียวกับ WP-03B + test role 2/3 บน endpoint | small |
| WP-03D Password reset | backend เกิน AC-IAM-006 ทุกข้อ (hashed token, TTL, throttle, Argon2id) | ไม่มีหน้าเว็บ forgot/reset เลย (route JSON ล้วน) + ไม่มี audit log ตาม deliverable | large |
| WP-03E View boundary | view หลักบางกลุ่มมีแล้ว (dashboard, rating, master generic form) | public tracking views 4 ไฟล์ยังไม่มี, status-action views ยุบหาย, ต้อง verify direct-model-call ย้ายออกจริง | large |

## Gap เรียงตามความเสี่ยง

1. **WP-03D ไม่มีหน้าเว็บ forgot/reset password** — backend พร้อม 100% แต่ผู้ใช้กดใช้จริงไม่ได้; ต้องสร้าง view + JS เรียก `password-reset/csrf` แล้ว POST JSON (contract มี test รออยู่แล้ว)
2. **WP-03E public tracking views หายทั้งกลุ่ม** (4 ไฟล์ customer-facing no-auth) และ tracking status-action views ยุบเหลือ controller เดียว — ต้อง verify UX parity
3. **WP-02B validation-error UX regression** — CI3 ใช้ `set_value()` + `validation_errors()` ถูกต้อง CI4 คืน 422 JSON เปล่า
4. **WP-03D audit log** — deliverable ใน charter ระบุ "audit" แต่ repo ไม่มีกลไก log security event เลย; คำว่า "no-token logs ผ่าน" กำกวม ต้องถามผู้เขียน charter
5. **Decision: role 3 write restriction** (WP-03B/03C) — CI4 ปิด hole ที่ CI3 เปิดโดย dead code; เป็น behavior change ต้อง Business approve พร้อม `CORRECT_AND_REBASELINE` record
6. **WP-02A ปฏิทิน พ.ศ./ค.ศ.** — CI3 ทำผิดทั้ง EN/TH (บวก 543 ทั้งคู่) ไม่มี baseline ถูกให้ลอก ต้อง decision format
7. **Test gap เล็ก** — WP-03A 3 จุด, WP-02A concurrency, WP-03C role matrix

## ข้อยืนยันฝั่งดี (มีผลรันจริง)

- Defect ที่ charter ห้ามย้าย: shared temp batch (WP-02A), missing CSRF (WP-02B/03D), replay duplicate writes (ระดับ Contact) — แก้ครบและมี test; ของ Order/New Order อยู่ Phase 4 ยังไม่ตรวจ
- Cross-branch IDOR ทั้ง 3 จุดจาก baseline (`outputs/reference/2026-08-22_route-auth-authorization-baseline_v1.md`) แก้ครบ: user edit, order edit, login history — คืน 404 + DB ไม่เปลี่ยน
- Password reset: enumeration-resistant (response + timing floor), revoke-on-reissue, session revoke ผ่าน `sessionVersion`

## คำถามที่ต้องการคำตอบก่อน implement

| ประเด็น | ผู้ตัดสิน | ตัวเลือก |
|---|---|---|
| Role 3 write restriction | Business/Security | คงแบบ CI4 (ปิด) + rebaseline record / เปิดตาม CI3 |
| ปฏิทินหน้า tracking | Business | ค.ศ. ทั้งคู่ / พ.ศ. เฉพาะ TH |
| ความหมาย "audit" ใน WP-03D deliverable | ผู้เขียน charter | log ทุก attempt ลงตาราง / แค่ no-leak ก็พอ |
| Forgot/reset UI + public tracking views | Business (UX parity 100%) | ทำตาม UX CI3 เดิม |

## หมายเหตุความคลาดเคลื่อนของ charter

- Direct-model-call views นับได้ 14 ไฟล์/28 จุด แต่ charter ระบุ 19 — ต้อง reconcile ตอน implement WP-03E
- สถานะ "Phase 2-7 ยังไม่เริ่ม" ในเอกสารเก่ากว่าโค้ดจริง — vertical slices ของ Phase 2-3 ถูกสร้างไว้มากแล้วใน scaffold
