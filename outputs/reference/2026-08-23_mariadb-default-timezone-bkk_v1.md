# หลักฐาน: MariaDB default-time-zone=+07:00 (follow-up timestamp contract)

ปิด follow-up ข้อ 1 ของ `2026-08-23_timezone-parity-evidence_v1.md` — ตั้ง `--default-time-zone=+07:00` ที่ MariaDB ให้ DB-side timestamp ตรง contract Asia/Bangkok เดียวกับ `appTimezone`

## เหตุผลและขอบเขต

- Schema legacy มี `cdate timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp()` 13 ตาราง (`db/local-schema-only.sql`)
- CI4 เขียน `cdate` เองด้วย PHP ทุก insert path แต่ **update path ของ master data ไม่ตั้ง `cdate`** — `ON UPDATE` ฝั่ง DB จึง fire และก่อนแก้จะประทับเวลา UTC (server tz เดิม = SYSTEM ใน container = UTC) เพี้ยน -7 ชม. จาก contract
- ใช้ offset ตัวเลข `+07:00` ไม่ใช่ชื่อ zone เพราะ tz table ไม่ได้ load ใน image
- จังหวะที่ตั้งตอนนี้ปลอดภัยสุด: production dump ถูก purge แล้ว (PDPA, PR #7) ข้อมูลใน volume เป็น synthetic ล้วน (`status_log` 10 แถว, `uploadstaus` 0 แถว) — ไม่มีข้อมูลที่ import ใต้ UTC ให้ค่าแสดงผลเลื่อน และ dump ชุดใหม่ที่จะ import ภายหลังจะถูกตีความใต้ +07:00 ถูกต้องตั้งแต่แรก (string round-trip คงเดิม, V13 ใน `db/dbctl.sh` ไม่ต้องแก้)

## หลักฐานรันจริง

| ข้อพิสูจน์ | คำสั่ง/วิธี | ผล |
|---|---|---|
| Flag อยู่บน process จริง | `docker inspect ... .Config.Cmd` + `ps aux` ใน container | `--default-time-zone=+07:00` ทั้งสองทาง |
| Global/session tz + NOW() | `SELECT @@global.time_zone, @@session.time_zone, NOW()` ผ่าน mysqli | `+07:00 \| +07:00 \| 2026-08-23 16:56:47` (ตรงเวลา Bangkok จริง) |
| ON UPDATE ประทับ Bangkok | UPDATE ค่าจริงบนแถว synthetic `type.type_id=1` แล้ว revert | `cdate=2026-08-23 16:57:37` = `NOW()` เป๊ะ (ก่อนแก้จะได้ 09:57 UTC) |
| CI3 web ไม่กระทบ | `curl http://127.0.0.1:18404/` หลัง recreate container | HTTP 200 หน้า DB-backed ปกติ |

หมายเหตุการรัน probe: `UPDATE SET col = col` (ค่าเท่าเดิม) ไม่ fire `ON UPDATE` — MariaDB ข้าม row ที่ไม่เปลี่ยน ต้องเปลี่ยนค่าจริงแล้ว revert (แถวที่แตะเป็น synthetic, revert ค่าเดิมครบ เหลือเฉพาะ `cdate` ที่ขยับตามพฤติกรรมที่ทดสอบ)

## เงื่อนไขผูกพันตอน import dump รอบหน้า (Gate 1D)

- Import และ read ต้องรันใต้ +07:00 ทั้งคู่ — flag นี้ทำให้เป็นค่า default ของทุก session แล้ว
- ห้าม import dump ใต้ session ที่ set time zone อื่นเอง มิฉะนั้นค่าแสดงผล TIMESTAMP จะเลื่อนจาก dump

## ข้อค้นพบข้างเคียงระหว่างทำ (ต้องการการตัดสินใจจาก user)

- `.env` ของ repo หลักถูกเขียนทับด้วยค่า placeholder ชุดเดียวกับ `.env.example` ตั้งแต่ 2026-08-21 22:41 — DB credential จริงใน `.env` หายไป
- ผลกระทบ: `db/dbctl.sh` (import/verify/rehearsal flow ที่ใช้ root) ใช้งานไม่ได้จนกว่า credential จะถูกซ่อม — พังมาก่อนงานนี้ ไม่ได้เกิดจากการ recreate container
- gate ปกติ (`ci-check.sh`, concurrency check, phpunit) ไม่พึ่ง credential ใน `.env` — ยังเขียวตามปกติ
- ทางแก้ 2 ทางรอ user เลือก: กู้ `.env` ตัวจริงจาก backup/worktree เดิม หรืออนุมัติ reset root password ผ่าน `--skip-grant-tables` (ดูรายงานรอบประจำ)
