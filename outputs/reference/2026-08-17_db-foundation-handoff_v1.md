# Handoff: DB Foundation ของ Samsonite CI4 migration

สรุปสถานะงาน Phase 0D ณ 2026-08-17 สำหรับคนที่มารับงานต่อ ครอบคลุมว่าอะไรรันอยู่
อะไรปิดไปแล้ว อะไรยังขวาง และอะไรที่ตัดสินใจไปแล้วห้ามรื้อ

## สถานะตอนนี้

มี 2 service รันอยู่บนเครื่อง ทั้งคู่ healthy

| Service | เข้าถึงที่ | Image |
|---|---|---|
| `db` | `127.0.0.1:13306` | `mariadb:11.4.12` pin digest |
| `web` | `http://127.0.0.1:18404` | `samsonitetracking-ci3:8dad4e3` build เอง |

ฐานข้อมูล `samsonitetracking` มี 31 table, 1,512,662 แถว เป็น utf8mb4_general_ci +
InnoDB + ROW_FORMAT DYNAMIC ทั้งหมด และ CI3 3.1.6 รันบน PHP 7.4 ต่อกับมันได้จริง

Compose project ชื่อ `samsonitetracking-ci4-migration` อยู่ที่ git worktree
`/Users/king_developer/Desktop/Project/samsonitetracking-ci4-migration` branch `feat/db-foundation`

## คำสั่งใช้งาน

รันจาก root ของ worktree ทุกคำสั่งรันซ้ำได้ปลอดภัย

```bash
./db/dbctl.sh status            # ดูสถานะ + port mapping
./db/dbctl.sh up                # เปิด db
./db/dbctl.sh web-build         # build image CI3 จากรีโปที่ pin
./db/dbctl.sh web-up            # เปิดทั้ง db และ web
./db/dbctl.sh down              # หยุด (ข้อมูลอยู่ใน volume ไม่หาย)
```

ชุดตรวจสอบ

```bash
./db/dbctl.sh verify            # 17 ข้อ: schema, row count, index, zero date, timezone
./db/dbctl.sh collation         # พิสูจน์ collation parity กับของเดิม
./db/dbctl.sh smoke             # CI3 smoke suite (backup, ทดสอบ, restore, verify)
./db/dbctl.sh rehearsal         # backup/restore/rollback 2 รอบ
./db/dbctl.sh diff              # พิสูจน์ว่าไม่กระทบ project อื่น
```

กู้ข้อมูล

```bash
./db/dbctl.sh backups           # ดูรายการ backup
./db/dbctl.sh restore <id>      # กู้คืน ใช้เวลา ~8 วินาที
./db/dbctl.sh reset             # DROP + CREATE DATABASE (ไม่แตะ volume/container)
./db/dbctl.sh import            # โหลด dump ใหม่ทั้งชุด ~20 วินาที
```

## งานที่ปิดไปแล้ว

| Blocker / WP | สถานะ | หลักฐาน |
|---|---|---|
| BLK-017 Docker isolation | ปิด | project แยก, port ปลอดภัย, diff = 0 ทุกรอบ |
| BLK-001 collation | ปิด | 8,633,439 ค่าใน 160 คอลัมน์ weight ตรงของเดิม |
| BLK-010 backup/restore | ปิด | 2 รอบ, RTO 8 วินาที, rollback ทำ DB พังจริงแล้วกู้ |
| WP-00B Data baseline | ครบ | restore เข้า isolated DB, row count ตรง |
| WP-00F Web exposure | ปิดบางส่วน | artifact ไม่มี phpMyAdmin, deny test ผ่าน |
| WP-00K MariaDB rehearsal | ครบ | CI3 smoke suite เป็นชิ้นสุดท้ายที่ขาด ตอนนี้ผ่านแล้ว |
| WP-00L utf8mb4/InnoDB | ครบ | มี signed collation decision |
| WP-00M DB foundation release | **ยังไม่ครบ** | ขาด parity 100% |

## สิ่งที่ยังขวาง Gate 1D

เหลือเรื่องเดียวคือ `CI3 parity 100%` ซึ่งนิยามไม่ได้เพราะ **WP-00C ยังไม่มี**

WP-00C คือ characterization catalog + approved fixtures — เอกสารที่บอกว่าพฤติกรรมที่ถูกต้อง
ของแต่ละหน้าคืออะไร ยังไม่มีใครเขียน จึงยังเทียบ before/after ระดับพฤติกรรมไม่ได้

smoke suite ที่ทำไปพิสูจน์ได้แค่ว่า **ระบบไม่พัง** ไม่ได้พิสูจน์ว่าผลลัพธ์ตรงของเดิมทุกจุด
นี่คือช่องว่างที่เหลือ ไม่ใช่รายละเอียดเล็ก

## สิ่งที่ค้นพบและยังไม่ได้แก้

### หน้า 404 พังทั้งระบบ

ทุก URL ที่ไม่มีจริงตอบ HTTP 200 พร้อม PHP warning แทนที่จะเป็น 404

- **สาเหตุ**: `routes.php:42` ชี้ `404_override` ไปที่ controller ชื่อ `Error` แต่ PHP 7
  มี class ชื่อ `Error` มาให้ในตัว CI3 เห็นว่า class มีอยู่แล้วจึงไม่โหลดไฟล์ของแอป
- **หลักฐาน**: `class_exists("Error")` เป็น true แต่ `getFileName()` เป็น false
- **ไม่ใช่ผลจากการย้าย DB**: production รัน PHP 7.4.32 อยู่ อาการนี้จึงมีอยู่จริงตอนนี้
- **ผลข้างเคียง**: `application/controllers/Error.php` เป็น dead code มาตลอด
- **ต้องแก้ตอนพอร์ต CI4**: PHP 8.5 ก็มี class `Error` เหมือนกัน ต้องเปลี่ยนชื่อ controller

### บั๊ก 404 บอกอะไรมากกว่าตัวมันเอง

บั๊กนี้ไม่ได้อยู่ในมุมมืด มันโผล่ทุกครั้งที่มีคนเปิด URL ผิด และมันอยู่มาตั้งแต่ระบบย้ายมา
PHP 7 แต่ไม่มีใครเห็น แปลว่า **ยังไม่เคยมีใครตรวจว่าโค้ดชุดนี้ทำงานถูกจริงบน PHP 7**
มีแค่การยืนยันว่ามันไม่ล้ม

- **สิ่งที่ควรทำก่อนพอร์ต CI4**: ไล่หา class ทั้งหมดในโปรเจกต์ที่ชนชื่อกับ class
  ในตัวของ PHP อาการจะเงียบแบบเดียวกันคือไฟล์ไม่ถูกโหลดโดยไม่มี error
- **ทำไมเร่งด่วนขึ้นตอนขึ้น CI4**: PHP 8.5 เพิ่ม class ในตัวมากกว่า 7.4 ชื่อที่เคยปลอดภัย
  อาจชนเพิ่ม และ CI4 ใช้ namespace กับ autoloader คนละแบบ อาการจะเปลี่ยนไปอีก
- **วิธีตรวจ**: เทียบชื่อ class ในโปรเจกต์กับ `get_declared_classes()` ของ PHP เปล่า ๆ
  ไม่ใช่ไล่ด้วยตา

### credential ของ production ยังไม่ rotate

DB credential แบบ plaintext อยู่ที่ `application/config/database.php:51-61` ของชุด source
ใน `~/Downloads` และ `SECRETS-LOCAL.md` ในรีโป CI3 มี credential จริงของ DB และ SMTP

ถ้ายังใช้บน production อยู่ ถือว่ารั่วแล้วต้องเปลี่ยน — งานนี้อยู่ใน WP-00D

### คอลัมน์ token และ hash เทียบแบบไม่สนตัวพิมพ์

`tbl_users.password`, `tbl_reset_password.activation_id`, `ci_sessions.session_id`
ใช้ collation ที่เป็น case-insensitive ทำให้ entropy ของ token ลดลง

เป็นพฤติกรรมเดิมของ production การแก้เป็น `ascii_bin` ต้องมาพร้อม regression test
ของ login และ reset password ไม่ใช่แก้เดี่ยว ๆ

## การตัดสินใจที่ล็อกแล้ว

อย่ารื้อโดยไม่มีเหตุผลใหม่ ทุกข้อมีหลักฐานรองรับในเอกสารที่อ้างไว้ท้ายไฟล์

| หัวข้อ | ค่า | เหตุผลสั้น |
|---|---|---|
| collation | `utf8mb4_general_ci` | ให้ผลเหมือน `utf8mb3_general_ci` เดิมทุกค่า ส่วน `unicode_ci` ยุบชื่อคนไทยที่ต่างกันแค่วรรณยุกต์ |
| PHP ของ CI3 | 7.4 | `lib/PHPExcel` มี curly-brace offset 153 จุด ซึ่ง fatal ตั้งแต่ PHP 8.0 |
| วิธีส่ง source เข้า image | build context ไม่ใช่ bind mount | ไม่ผิด AC-DKR-005 และบังคับ WP-00F ได้จริง |
| วิธี import | แก้บรรทัด DDL ตอน stream | เลี่ยง `TEXT` ถูกขยายเป็น `MEDIUMTEXT` 11 คอลัมน์ และไม่มีช่วง charset ผสม |
| timezone ของ container | UTC (ไม่ตั้งทับ) | พิสูจน์แล้วว่าเซิร์ฟเวอร์เดิมเป็น UTC |
| port ของ DB | publish `127.0.0.1:13306` | deviation `DEV-DB-PORT-001` ที่ user อนุมัติ |

## ข้อควรระวัง

### ห้ามกระทบ project อื่นบนเครื่อง

เครื่องนี้มี Compose project ของงานอื่น 7 ตัว รวม 33 container / 110 volume

- คำสั่งที่ยิงใส่ของคนอื่นได้มีแค่แบบอ่านอย่างเดียว
- ห้าม `prune` ทุกชนิด, ห้าม `down --volumes`, ห้าม wildcard filter
- port ชน ให้เลื่อนไป port ว่าง ห้ามไปหยุดหรือแก้ config ของเจ้าของ port
- ห้ามแตะ volume ตระกูล `ci4_*` เป็นของ project อื่นที่ชื่อคล้ายกันเท่านั้น

### รีโป CI3 เป็น read-only

`/Users/king_developer/Desktop/Project/samsoniteci3` pin ที่ commit
`8dad4e331a90f5c6765954454910b451eb0ff8e5` ห้ามแก้ ห้าม commit ห้าม checkout

`dbctl.sh web-build` ตรวจ HEAD และ `git status` ทั้งก่อนและหลัง build ถ้าเปลี่ยนจะหยุดทันที

### ข้อมูลเป็น production จริง

dump และฐานข้อมูลมี PII ครบ: bcrypt hash 127 บัญชี, reset token แบบ plaintext,
session และ IP 117,377 แถว, ชื่อและเบอร์ลูกค้าราว 570,000 แถว

- ห้ามคัดลอก dump เข้ารีโป — `.gitignore` มี `*.sql` เป็น guard ชั้นสอง
- backup ยังไม่เข้ารหัส ก่อนนำออกนอกเครื่องนี้ต้องเข้ารหัสและมี key management
- ไฟล์ evidence เก็บได้เฉพาะตัวเลข hash และชื่อ table ห้ามเขียน sample row

### PHP 7.4 หมดอายุแล้ว

image ของ web เป็นเครื่องซ้อมสำหรับ CI3 เท่านั้น ห้ามใช้เป็น runtime ของ CI4
ซึ่งต้องเป็น PHP 8.5 ตาม target stack และยังติด BLK-014 เรื่อง image digest

## ตัวเลือกงานถัดไป

งานที่เหลือแตกเป็นสองสายที่เดินขนานกันได้ ไม่ต้องรอกัน

### สายที่ต้องรอคนอื่นตัดสิน

- **WP-00C behavior baseline** — ตัวปลดล็อก Gate 1D ตัวจริง ติดที่ต้องมีฝั่งธุรกิจ
  ร่วมนิยามว่าผลลัพธ์ที่ถูกคืออะไร ไม่ใช่งานที่วิศวกรตัดสินเองได้
- **แก้ controller `Error`** — ต้องตัดสินก่อนว่าจะแก้ที่รีโป CI3 (ต้องเปิดให้เขียนได้
  ซึ่งขัดกฎ read-only ปัจจุบัน) หรือรอไปแก้ตอนพอร์ตเป็น CI4
- **WP-00D rotate credential** — เป็นงานฝั่ง security ที่ต้องมีคนถือสิทธิ์ระบบจริง

### สายที่ลุยต่อได้ทันที

- **ขยาย smoke ไป Excel import ก่อนเพื่อนในสายนี้** — ใช้ temp table 4 ใบที่ TRUNCATE
  ทั้งใบ บวก PHPExcel ที่เป็นตัวขวาง PHP 8 อยู่แล้ว เป็นจุดที่การย้าย DB น่าจะสะดุดที่สุด
- **ไล่หา class ที่ชนชื่อกับ built-in ของ PHP** — ต่อยอดจากบั๊ก 404 ทำได้เลยไม่ต้องรอใคร
- **smoke เส้นทางที่เหลือ** — reset password, ส่งอีเมล, report ที่ข้อมูลเยอะ

จุดที่ควรเริ่ม: Excel import ให้ผลตอบแทนต่อแรงสูงสุด เพราะถ้าพังจะพังแบบเห็นชัด
และมันแตะทั้ง temp table, charset และ library ที่เก่าที่สุดในระบบพร้อมกัน

## เอกสารและหลักฐาน

เอกสารทั้งหมดอยู่ใน `outputs/reference/` ของ worktree นี้

| ไฟล์ | เนื้อหา |
|---|---|
| `2026-08-17_db-foundation-runbook_v1.md` | คู่มือหลัก: identity, port record, deviation, ค่า server, เกณฑ์ตรวจ 17 ข้อ |
| `2026-08-17_collation-decision_v1.md` | คำตัดสิน collation พร้อมตัวเลขเทียบและทางเลือกที่ตัดทิ้ง |
| `2026-08-17_backup-restore-rehearsal_v1.md` | ผลซ้อม backup/restore/rollback 2 รอบ |
| `2026-08-17_ci3-smoke-rehearsal_v1.md` | ผล CI3 smoke suite และวิธีสร้าง runtime |

หลักฐานที่เครื่องสร้างเองอยู่ใน `evidence/db-foundation-001/` ซึ่ง gitignore ไว้

| โฟลเดอร์ | เนื้อหา |
|---|---|
| `00-manifest/` | sha256 ของ dump, compose, script, rendered config |
| `01-baseline/` | ค่าคาดหวังจาก dump, ผลจริง, backup manifest, restore log |
| `19-docker-isolation/` | snapshot before/after และ non-interference diff |
| `20-ci3-smoke/` | สภาพรีโป CI3 ก่อนเริ่ม |

## สถานะ PR

PR #4 `feat/db-foundation` เข้า `develop` ยังเปิดอยู่ ยังไม่ merge

| Commit | เนื้อหา |
|---|---|
| `b23de9e` | MariaDB container + dump import pipeline |
| `d9d5a7b` | ทำให้ preflight และ resource check รันซ้ำได้ |
| `df33ad3` | พิสูจน์ collation parity ปิด BLK-001 |
| `ddcd597` | backup/restore/rollback rehearsal ปิด BLK-010 |
| `25638f8` | รัน CI3 บนฐานข้อมูลที่ย้ายแล้ว |

commit `b23de9e` ไม่มี trailer `Co-Authored-By` เพราะ push ไปก่อนแล้วและการแก้ย้อนหลัง
ต้อง force push ซึ่งกฎห้ามไว้
