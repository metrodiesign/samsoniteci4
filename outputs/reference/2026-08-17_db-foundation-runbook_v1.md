# DB Foundation Runbook — MariaDB 11.4 สำหรับ Samsonite Tracking

> สร้าง: 2026-08-17
> ขอบเขต: service `db` อย่างเดียว (ยังไม่มี web/app เพราะ PHP image digest ติด BLK-014)

เอกสารนี้คือคู่มือใช้งาน Docker MariaDB ที่โหลดข้อมูล production ของระบบเดิมเข้าไปแล้ว
ใช้เป็นฐานตั้งต้นของงานอัพเกรด CodeIgniter 3 ไป 4

## 1. กติกาสูงสุด: ห้ามกระทบโปรเจกต์อื่นบนเครื่อง

เครื่องนี้รัน Compose project ของงานอื่นอยู่ 7 ตัว (corporate-standard, nong-kaewta-api,
nongkaewta, offset-design-platform, pol-core, primeaccountclaudecowork, viriyah)
บวก orphan volume ของ project ชื่อ `ci4` อีก 9 ตัว รวม 33 container / 14 network /
110 volume ทั้งหมดนี้แตะไม่ได้

| กฎ | รายละเอียด |
|---|---|
| อ่านอย่างเดียวกับของคนอื่น | คำสั่งที่ยิงใส่ resource ของ project อื่นได้มีแค่ `docker ps`, `docker inspect`, `docker network ls`, `docker volume ls`, `docker compose ls` |
| ระบุ scope เสมอ | ทุกคำสั่งที่เปลี่ยน state ต้องมี `-p samsonitetracking-ci4-migration -f <compose path>` เต็ม ห้ามพึ่ง current directory |
| คำสั่งต้องห้าม | `docker system/container/network/volume prune`, `docker compose down --volumes`, `docker volume rm`, `docker stop $(...)`, `docker rm -f $(...)`, filter แบบ wildcard — `dbctl.sh preflight` grep ตัวเองยืนยันว่าไม่มี |
| port ชน | เปลี่ยนไป port ว่างตัวถัดไปใน pool อัตโนมัติ ห้าม stop / restart / แก้ config ของเจ้าของ port |
| volume `ci4_*` | ห้ามแตะ เป็นของ project `ci4` คนละตัว (label `com.docker.compose.project=ci4` สร้าง 2025-09-19) เหตุผลที่ชื่อ project ต้องยาว |
| พิสูจน์ด้วยหลักฐาน | `dbctl.sh snapshot before` / `after` แล้ว `diff` ต้องได้ 0 ทุกครั้งหลังงานหนัก |
| ไม่นับ port ชั่วคราว | snapshot ตัด listener ในช่วง ephemeral 49152–65535 ออก เพราะเป็นของ process บนเครื่องที่เกิดดับเองตลอดเวลา (เจอจริง: `codex` เปิด 54222/54223 ระหว่างทำงาน) และ published port ของ Docker อยู่ในช่วงนั้นไม่ได้ตาม protocol เลือก port |
| ไม่ auto-start | `restart: "no"` container จะไม่โผล่มาเองตอนเปิดเครื่อง |

## 2. Identity ของโปรเจกต์

| รายการ | ค่า |
|---|---|
| Compose project | `samsonitetracking-ci4-migration` |
| Compose file | `/Users/king_developer/Desktop/Project/samsonitetracking-ci4-migration/compose.yaml` |
| Workspace | git worktree ของรีโป `samsoniteci4` branch `feat/db-foundation` |
| Service | `db` (ไม่กำหนด `container_name`) |
| Network | `backend` (project-scoped) |
| Volume | `mariadb_data` → `/var/lib/mysql` |
| Image | `mariadb:11.4.12@sha256:67873d30a17f6a9c331f06363b2fa15f38abca415529966d67c84f87f82439fe` |
| Database | `samsonitetracking` utf8mb4 / utf8mb4_general_ci |

## 3. Port record `PORT-CI4-DB-001`

| รายการ | ค่า |
|---|---|
| Host binding | `127.0.0.1:13306` → container `3306` |
| Pool สำรอง | `13306 → 13307 → 13308 → 18306` เลือกตัวแรกที่ว่างอัตโนมัติ |
| ห้ามใช้ | `18404` และ `18405–18419` จองไว้เป็น web port ตาม `PORT-CI4-LOCAL-001` |
| สถานะ | `RESERVED_RUNNING` ณ 2026-08-17 (`docker compose port db 3306` = `127.0.0.1:13306`) |
| เมื่อชน | preflight เลื่อน port เอง เขียนกลับ `.env` และบันทึกใน log; ถ้าเต็ม pool ให้หยุดและเปิด record ใหม่ |

## 4. Deviation record `DEV-DB-PORT-001`

| หัวข้อ | เนื้อหา |
|---|---|
| ขอบเขต | `compose.yaml` service `db` |
| เบี่ยงจาก | DKR-05, DKR-07 (ห้าม publish port ของ database) และ §22.3 ที่กำหนด `backend` เป็น `internal: true` |
| เหตุผล | รอบนี้มี service เดียว จำเป็นต้องต่อจาก host เพื่อสำรวจ schema และซ้อม CI3 บน MariaDB target; และ `internal: true` ใช้ร่วมกับ published port ไม่ได้เพราะ network ที่ internal ตัด external connectivity ทั้งหมด (ใส่ network สองตัวก็ไม่ช่วย เพราะ Compose เลือก network ที่ผูก port ด้วยการเรียงชื่อตามตัวอักษร และ `priority` ไม่มีผล) |
| มาตรการชดเชย | bind เฉพาะ `127.0.0.1` ไม่ใช่ `0.0.0.0`; รหัสผ่านมาจาก `.env` ที่ไม่ commit; `no-new-privileges`; `restart: "no"` |
| หมดอายุเมื่อ | เพิ่ม service `app`/`web` (ปิด BLK-014) หรือถึง Gate 1D |
| วิธีคืนสภาพ | เพิ่ม `internal: true` ให้ `backend`, ลบบล็อก `ports` ของ `db`, แล้วเข้าถึงผ่าน `docker compose exec` แทน |
| ผู้อนุมัติ / วันที่ | user อนุมัติ 2026-08-17 |

## 5. คำสั่งใช้งาน

รันจาก root ของ worktree

```bash
./db/dbctl.sh preflight        # เลือก port ว่าง + ตรวจ policy + เก็บ hash ของ input
./db/dbctl.sh snapshot before  # เก็บสภาพ host ก่อนเริ่ม
./db/dbctl.sh expect           # คำนวณค่าคาดหวังจากตัว dump โดยตรง
./db/dbctl.sh up               # preflight แล้ว up -d --wait
./db/dbctl.sh import           # import ครบ 6 ไฟล์ (ระบุชื่อไฟล์เพื่อ import เฉพาะตัวได้)
./db/dbctl.sh verify           # ตรวจ 17 ข้อ
./db/dbctl.sh collation        # พิสูจน์ collation parity กับของเดิม (BLK-001)
./db/dbctl.sh snapshot after && ./db/dbctl.sh diff   # พิสูจน์ว่าไม่กระทบใคร
./db/dbctl.sh status           # ดูสถานะ + port mapping
./db/dbctl.sh reset            # DROP + CREATE DATABASE เท่านั้น ไม่แตะ volume/container
./db/dbctl.sh down             # หยุด container (ไม่มี --volumes เด็ดขาด)
```

ทุกคำสั่งรันซ้ำได้ปลอดภัย: `preflight` รู้จัก port ที่ project ตัวเองเผยแพร่อยู่แล้วจึงไม่
เลื่อน port หนีตัวเอง, การตรวจ volume/network ดูที่ label เจ้าของไม่ใช่ดูว่ามีอยู่หรือไม่,
และ `import` สั่ง `DROP TABLE IF EXISTS` เฉพาะ table ที่อยู่ในไฟล์นั้นก่อนเสมอ

ต่อจาก host ด้วย GUI: host `127.0.0.1` port `13306` user/password จาก `.env`

## 6. วิธี import ที่ใช้

แก้บรรทัดปิด DDL ระหว่างที่ stream เข้า container ไม่ import ตามต้นฉบับแล้วค่อย
`ALTER TABLE ... CONVERT TO CHARACTER SET`

```bash
sed 's/^) ENGINE=.*/) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;/' dump.sql \
  | docker compose -p ... -f ... exec -T db mariadb ... samsonitetracking
```

ปลอดภัยเพราะตรวจแล้วว่าคำว่า `utf8mb3` / `MyISAM` / `latin1` ปรากฏเฉพาะใน 31 บรรทัด
ที่ขึ้นต้นด้วย `) ENGINE=` ไม่มีในบรรทัดข้อมูลเลย และ dump ทั้ง 222 MB ไม่มีอักขระ 4 ไบต์
จึงแปลง utf8mb3 เป็น utf8mb4 ได้โดย byte ของข้อมูลไม่เปลี่ยน

ข้อดีเทียบกับการ `CONVERT TO` ทีหลัง:

| ประเด็น | แก้ตอน stream | CONVERT ทีหลัง |
|---|---|---|
| `TEXT` ถูกขยายเป็น `MEDIUMTEXT` เงียบ ๆ | ไม่เกิด | เกิดแน่นอน 11 คอลัมน์ |
| rebuild table ใหญ่ | 2 รอบ | 3 รอบ |
| ช่วงที่ charset ผสมกันจน JOIN ใช้ index ไม่ได้ | ไม่มี | มี |
| transcode ข้อมูล latin1 | 0 ครั้ง | 2 ครั้ง |
| สำเนา dump PII เพิ่มบนดิสก์ | ไม่มี (แปลงในสายท่อ) | ไม่มี |

การส่ง dump ใช้ stdin ไม่ใช่ bind mount เพราะ dump อยู่นอก approved workspace
(`~/Downloads`) และห้ามคัดลอก PII เข้ารีโป

## 7. ค่า server ที่ตั้งไว้ และเหตุผล

| flag | ค่า | เหตุผล |
|---|---|---|
| `--character-set-server` | `utf8mb4` | target stack |
| `--collation-server` | `utf8mb4_general_ci` | algorithm เดียวกับ `utf8mb3_general_ci` เดิม ทำให้ sort/compare ตรงกับ production |
| `--character-set-collations` | `utf8mb4=utf8mb4_general_ci` | **สำคัญ** — 11.4.12 มาพร้อม `utf8mb4=utf8mb4_uca1400_ai_ci` ทำให้ literal หรือ DDL ที่เขียน `utf8mb4` เปล่า ๆ ได้ collation คนละตัวแล้วชนกับตารางเรา (`ERROR 1267`) เจอจริงตอน verify รอบแรก |
| `--sql-mode` | `NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION` | ต้องไม่มี `NO_ZERO_DATE`/`NO_ZERO_IN_DATE` เพราะข้อมูลมี zero date จริง 44,940 ค่า |
| `--innodb-default-row-format` | `dynamic` | คงเพดาน index 3,072 bytes; ถ้าเป็น COMPACT เพดานเหลือ 767 แล้ว `uploadstaus.tracking_id` (1,000 bytes) จะพังทันที |
| `--innodb-buffer-pool-size` | `1G` | default ของ image คือ 128 MB แต่ข้อมูล 222 MB |
| `--innodb-log-file-size` | `512M` | แต่ละไฟล์ครอบด้วย transaction เดียว ใหญ่สุด 85.8 MB |
| `--innodb-flush-log-at-trx-commit` | `2` | เครื่องซ้อมเท่านั้น **ห้ามใช้ต่อบน production** |
| `--max-allowed-packet` | `64M` | headroom เผื่อ backup |
| `--skip-name-resolve` | – | ตัด reverse DNS ตอนต่อจาก host |

ไม่ตั้ง `--default-time-zone` เพราะพิสูจน์แล้วว่าเซิร์ฟเวอร์เดิมเป็น UTC (ดูข้อ 8)

## 8. หลักฐานสำคัญที่ตรวจจากตัว dump

### timezone — เซิร์ฟเวอร์เดิมเป็น UTC

dump ตั้ง `SET time_zone = "+00:00"` ทุกไฟล์ ถ้าต้นทางเป็น Asia/Bangkok แล้วเราตั้ง
container เป็น UTC เวลาจะเลื่อน 7 ชั่วโมงโดยที่ row count ตรวจไม่เจอ

เทียบคอลัมน์ที่เซิร์ฟเวอร์เติมค่าเองทั้งคู่:

| คอลัมน์ | ชนิด | ค่าท้ายสุดใน dump |
|---|---|---|
| `status_log.cdate` | TIMESTAMP (พิมพ์เป็น UTC) | `2026-07-31 05:09:08` |
| `tbl_last_login.createdDtm` | DATETIME (เวลาเครื่องตรง ๆ) | `2026-07-31 05:08:31` |

ห่างกัน 37 วินาที ไม่ใช่ 7 ชั่วโมง จึงสรุปว่าต้นทางเป็น UTC — container ที่เป็น UTC
โดย default ตรงกับ production อยู่แล้ว

### encoding — แปลงแล้ว byte ไม่เปลี่ยน

รันตัวถอดรหัส UTF-8 ทีละ chunk ครบ 222 MB: valid UTF-8 ทั้งหมด, mojibake 0,
replacement char 0, **อักขระ 4 ไบต์ 0 ตัว** และ `tbl_reset_password` (latin1)
เป็น ASCII ล้วน

### สภาพ schema เดิม

- 31 table รวม 1,512,662 แถว
- ไม่มี FOREIGN KEY / VIEW / TRIGGER / PROCEDURE / UNIQUE / FULLTEXT / SPATIAL เลย
- `tbl_users` (127 แถว) และ `tbl_roles` (3 แถว) เป็น MyISAM แปลงเป็น InnoDB แล้ว
- `AUTO_INCREMENT` ของทุก table เท่ากับ `MAX(PK)+1` พอดี จึงไม่มีความเสี่ยงตอน rebuild
- index นอก PK มี 5 ตัว ยาวสุด `rating_comment.track_id` = 1,004 bytes ห่างเพดาน 3,072 มาก

## 9. เกณฑ์ตรวจ 17 ข้อ (`dbctl.sh verify`)

| # | ตรวจอะไร | เกณฑ์ |
|---|---|---|
| V1 | server identity + `sql_mode` | 11.4.12 / utf8mb4 / general_ci / dynamic / 16384 และไม่มี `NO_ZERO_*` |
| V2 | `character_set_collations` และ default collation ของ schema | `utf8mb4=utf8mb4_general_ci` และ `utf8mb4_general_ci` |
| V3 | จำนวน base table | 31 |
| V4 | table ที่ engine / row_format / collation ผิด | 0 |
| V5 | column ที่ collation ผิด | 0 |
| V6 | column ที่เป็น `mediumtext`/`longtext` | 0 (พิสูจน์ว่าไม่มี `TEXT` ถูกขยาย) |
| V7 | row count ต่อ table เทียบค่าที่นับจาก dump | ตรงทั้ง 31 ตัว รวม 1,512,662 |
| V8 | index set เทียบ dump | 36 |
| V9 | `AUTO_INCREMENT` เทียบ dump | ตรงทั้ง 30 ค่า |
| V10 | zero date ในข้อมูลและใน column default | `request_order` 44,701 / `branch` 18 / default ยังอยู่ |
| V11 | mojibake และ replacement character | 0 |
| V12 | ข้อมูลไทยมีจริงและอัตราส่วน byte ต่ออักขระ | > 0 และไม่เกิน 3.0 |
| V13 | `status_log` แถวท้ายสุด | `2026-07-31 05:09:08` UTC ตรงกับ dump |
| V14 | `tbl_users`, `tbl_roles` เป็น InnoDB | 2 |
| V15 | `tbl_reset_password` non-ASCII หลังแปลงจาก latin1 | 0 |
| V16 | `EXPLAIN` ยังใช้ index | เห็น `key=` ไม่ใช่ `type=ALL` |
| V17 | ต่อจาก host ได้ | port ตอบรับ |

ผลรัน 2026-08-17: **ผ่านครบ 17 ข้อ 2 รอบติด** ตัวเลขตรงกันทุกบรรทัดทั้งสองรอบ
import ใช้เวลา ~20 วินาที

## 10. Reset เมื่อพังกลางทาง

| ระดับ | สถานการณ์ | ทำอะไร |
|---|---|---|
| L0 | ไฟล์เดียวพัง | `./db/dbctl.sh import <ชื่อไฟล์>` — สคริปต์ `DROP TABLE IF EXISTS` เฉพาะ table ในไฟล์นั้นก่อน จึงรันซ้ำได้ทั้งที่ dump ไม่มี `DROP TABLE` |
| L1 | schema เพี้ยน | `./db/dbctl.sh reset && ./db/dbctl.sh import` — `DROP DATABASE` + `CREATE DATABASE` ผ่าน SQL ไม่แตะ volume/container ใช้กรณีนี้เป็นหลัก |
| L2 | เปลี่ยนค่า server | ตรวจ label `com.docker.compose.project` ของ container ก่อน แล้ว `up -d --force-recreate --wait db` แบบมี `-p`/`-f` ครบ ข้อมูลอยู่ใน volume ไม่หาย |
| L3 | datadir เสียหายจริง | ต้องมี manifest + backup/restore proof + คนอนุมัติก่อน ปกติไม่ต้องใช้เพราะ L1 ให้ผลเดียวกัน |

## 11. ความปลอดภัยของข้อมูล

dump เป็น production data จริง ไม่ใช่ sample:

- bcrypt password hash + email + เบอร์ 127 บัญชี ใน `tbl_users`
- reset token แบบ plaintext 16 แถว ใน `tbl_reset_password`
- session JSON + IP + User-Agent 117,377 แถว ใน `tbl_last_login`
- ชื่อ-เบอร์-email ลูกค้า ~570,000 แถว ใน `request_order` และ `uploadstaus`

ข้อบังคับ:

- ห้ามคัดลอก dump เข้ารีโป — `.gitignore` มี `*.sql` เป็น guard ชั้นสอง
- `.env` ไม่ commit และรหัสผ่านสร้างใหม่สำหรับ local เท่านั้น ห้ามใช้ค่าเดียวกับ
  credential production ที่อยู่ใน `application/config/database.php:51-61` ของ source เดิม
- credential ชุดนั้นอยู่ใน `~/Downloads` แบบ plaintext ถ้ายังใช้บน production ต้อง rotate
  (งานนี้อยู่ใน WP-00D)
- ไฟล์ evidence เก็บได้เฉพาะตัวเลข hash และชื่อ table ห้ามเขียน sample row / token

## 12. สิ่งที่ยังไม่ได้ทำ

- ไม่มี service web/app — PHP 8.5 image digest ยังติด BLK-014
- ไม่มี CI4 migration file — แผนกำหนดให้ baseline schema แยกจาก migration
- ยังไม่ทำ anonymization ของ PII ควรทำก่อนแจกจ่าย environment ให้คนอื่น
- **ยังไม่เปลี่ยน collation ของคอลัมน์ token/hash เป็น `ascii_bin`** —
  `tbl_users.password`, `tbl_reset_password.activation_id`, `ci_sessions.session_id`
  ถูกเทียบแบบ case-insensitive ซึ่งลด entropy ของ token แต่เป็นพฤติกรรมเดิมของ
  production การแก้ตอนนี้อาจทำให้ login พัง ควรทำพร้อม regression test ในงาน security
- **BLK-001 ปิดแล้ว** — ดู `2026-08-17_collation-decision_v1.md` พิสูจน์ว่า
  `utf8mb4_general_ci` ให้ผลเหมือน `utf8mb3_general_ci` เดิมทุกค่า (8,633,439 ค่า
  จาก 160 คอลัมน์, ลำดับการเรียงเปลี่ยน 0 แถว) ส่วน parity ระดับแอปยังต้องรอ CI3
  ขึ้นมารันบน DB ตัวนี้ (WP-00K/WP-00M)
