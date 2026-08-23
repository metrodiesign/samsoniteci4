# Timestamp Contract: Asia/Bangkok Parity Evidence (v1)

หลักฐานการเปลี่ยน timestamp contract ของ CI4 จาก UTC เป็น `Asia/Bangkok` ให้ตรง CI3 (user ตัดสิน 2026-08-23)

## สรุปผล

| รายการ | ผล |
|---|---|
| `composer test` | `OK (127 tests, 1789 assertions)` |
| `bash scripts/ci-check.sh` (รวม concurrency gate บน Docker) | exit 0 |
| `vendor/bin/phpstan analyse` | `[OK] No errors` |
| Mutation check (revert `$appTimezone` เป็น UTC) | RED ที่ `TimezoneContractTest:18` — restore แล้ว GREEN |
| Audit | PASS (0 BLOCKING/HIGH/MEDIUM, 2 LOW follow-up) |

## Before

- CI3 ตั้ง `date_default_timezone_set('Asia/Bangkok')` (`application/config/config.php:22`) และเขียน `date('Y-m-d H:i:s')` ลงทุก date column
- CI4 scaffold ใช้ `gmdate` (17 จุด) + `DateTimeZone('UTC')` (19 จุด) — ข้อมูลในตารางเดียวกันจะเหลื่อม 7 ชั่วโมงตอน shadow/cutover

## Change

- `app/Config/App.php` — `$appTimezone = 'Asia/Bangkok'` (จุดตั้งค่าเดียว)
- `gmdate(...)` เป็น `date(...)` ทุกจุด; `DateTimeZone('UTC')` เป็น `date_default_timezone_get()` ทุกจุด (25 app files)
- test ใหม่ `tests/ci4/TimezoneContractTest.php` ตรึง contract; `ResetTokenFactoryTest` ปรับ expectation เป็น +07:00
- รายละเอียด disposition ต่อ call site: `.pipeline/timezone-bkk/changes.md`, audit: `.pipeline/timezone-bkk/audit.md`

## After — สิ่งที่ยืนยันว่าไม่เปลี่ยน

- Writer/reader ของ token expiry, rate limiter, delivery intent สมมาตรครบ (audit ข้อ 1)
- ไม่มี `gmdate`/`DateTimeZone('UTC')` ตกค้างใน `app/`, `tests/`, `public/`, `spark`
- trackID period producer 3 จุดเลื่อนพร้อมกันครบ
- `.env` ไม่มี `app.appTimezone` override

## Follow-up ที่บันทึกไว้ (LOW — ไม่ block)

1. `db/local-schema-only.sql` มี `cdate timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp()` หลาย table — DB-side default เขียนตาม MySQL session timezone ไม่ผูกกับ `appTimezone`; ต้องตั้ง `default-time-zone='+07:00'` ที่ MariaDB target เป็นส่วนหนึ่งของ DB foundation (Gate 1D) ก่อน shadow comparison
2. แถวที่เขียนใน dev DB ก่อนการแก้นี้เป็น UTC — เป็น synthetic data ล้วน ไม่ต้อง migrate แต่ผลเทียบเวลาย้อนหลังใน dev อาจเหลื่อม
