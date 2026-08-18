# Backup / Restore rehearsal (ปิด BLK-010 และเงื่อนไข backup ของ Gate 1D)

> วันที่: 2026-08-17
> ทำซ้ำได้ด้วย: `./db/dbctl.sh rehearsal`
> สถานะ: **ผ่าน 2 รอบ**

## สิ่งที่ข้อกำหนดขอ และสิ่งที่ทำได้

| ข้อกำหนด | แหล่ง | ผล |
|---|---|---|
| backup ต้องอยู่บน owned volume เท่านั้น | Gate 1D | volume `samsonitetracking-ci4-migration_backup_data` label `com.docker.compose.project=samsonitetracking-ci4-migration` ไม่ใช่ bind mount ไม่ใช่ host path |
| backup ID | BLK-010 | รูปแบบ `bk-<UTC timestamp>[-label]` |
| checksum | BLK-010 | sha256 ต่อไฟล์ เก็บคู่ไว้ที่ `<id>.sha256` บน volume เดียวกัน และตรวจก่อน restore ทุกครั้ง |
| timed restore log | BLK-010 | บันทึกวินาทีต่อครั้งลง `evidence/.../restore-log.tsv` |
| timed upgrade log | BLK-010 | `mariadb-check --check-upgrade --all-databases` ทุกรอบ |
| timed conversion log | BLK-010 | การแปลง utf8mb4/InnoDB เกิดตอน import (~20 วินาที) ดู runbook |
| timed rollback log | BLK-010 | ทำ DB พังจริงแล้วกู้ ดูหัวข้อถัดไป |
| 2 รอบ | BLK-010 | รอบ 1 และ 2 ผ่านทั้งคู่ |
| ไม่มี successful restore/rollback = No-Go | BLK-010 | ผ่านแล้ว ไม่ No-Go |

## ผลรันจริง

| รอบ | Backup ID | ขนาด (bytes) | backup (วินาที) | restore (วินาที) | rollback | upgrade check | verify 17 ข้อ |
|---|---|---|---|---|---|---|---|
| 1 | `bk-20260817T124449Z-r1` | 31,025,297 | 3 | 8 | ผ่าน | 60 tables OK | ผ่าน |
| 2 | `bk-20260817T124506Z-r2` | 31,025,297 | 4 | 8 | ผ่าน | 60 tables OK | ผ่าน |

- **RTO ที่วัดได้: 8 วินาที** สำหรับ database 1,512,662 แถว
- backup บีบอัดจาก 222 MB เหลือ 29.6 MB
- พื้นที่: `/backup` ใช้ไป 119 MB จากที่เหลือ 108 GB

## การพิสูจน์ rollback ไม่ใช่แค่ "restore แล้วไม่ error"

แต่ละรอบทำตามนี้:

1. backup แล้วจดจำนวนแถวของ `rating` (75,282)
2. **ทำลายจริง**: `DROP TABLE rating`
3. ยืนยันว่าตารางหายจริง — ถ้ายังอยู่ให้หยุดทันที เพราะการซ้อมที่ไม่ได้ทำลายอะไรไม่พิสูจน์อะไร
4. restore จาก backup ID นั้น พร้อมจับเวลา
5. นับแถวใหม่ ต้องได้ 75,282 เท่าเดิม
6. รัน `verify` ครบ 17 ข้อ ต้องผ่านหมด ไม่ใช่แค่ตารางที่ลบไปกลับมา

ถ้าขั้นไหนพลาด สคริปต์หยุดทันทีและถือว่าไม่ผ่าน

## รายละเอียดทางเทคนิค

```bash
# backup
mariadb-dump -u root --single-transaction --quick --default-character-set=utf8mb4 \
  --add-drop-database --databases samsonitetracking | gzip -6 > /backup/<id>.sql.gz

# restore (ตรวจ checksum และ gzip integrity ก่อนเสมอ)
sha256sum -c <id>.sha256
gzip -t /backup/<id>.sql.gz
gunzip -c /backup/<id>.sql.gz | mariadb -u root --default-character-set=utf8mb4
```

| ตัวเลือก | เหตุผล |
|---|---|
| `--single-transaction` | ได้ snapshot ที่สอดคล้องกันโดยไม่ต้อง lock ตาราง ใช้ได้เพราะทุกตารางเป็น InnoDB แล้ว (ตอนต้นทางมี MyISAM 2 ตัวจะใช้ไม่ได้) |
| `--add-drop-database` | ทำให้ไฟล์ restore ได้ด้วยตัวเอง รันซ้ำได้ ไม่ต้อง drop มือ |
| `--databases` | ได้ `CREATE DATABASE` พร้อม charset/collation ติดมาด้วย restore แล้วได้ utf8mb4_general_ci ถูกต้องทันที |
| `--quick` | ไม่ buffer ทั้งตารางใน memory |
| `gzip -6` | ค่ากลาง บีบได้ 7 เท่าโดยไม่กิน CPU นาน |

## คำสั่ง

```bash
./db/dbctl.sh backup [label]   # สร้าง backup คืน backup ID
./db/dbctl.sh backups          # ดูรายการพร้อมขนาดและ checksum
./db/dbctl.sh restore <id>     # กู้คืน (ตรวจ checksum ก่อน)
./db/dbctl.sh upgrade-check    # ตรวจว่า schema จาก 10.6 ไม่ต้อง upgrade บน 11.4
./db/dbctl.sh rehearsal        # ชุดเต็ม 2 รอบตามที่ BLK-010 บังคับ
```

## Retention และความจุ

- ตอนนี้ **ไม่ลบ backup อัตโนมัติ** — การลบข้อมูลเป็นการกระทำที่ย้อนไม่ได้ และ DKR-11
  บังคับให้การแตะ volume ต้องมีคนอนุมัติ จึงให้ลบด้วยมือเมื่อจำเป็น
- แต่ละ backup ~29.6 MB พื้นที่เหลือ 108 GB รองรับได้หลักพันไฟล์ ยังไม่ต้องมีนโยบายอัตโนมัติ
- เมื่อขึ้น staging/production ต้องกำหนด retention จริง (จำนวนวัน จำนวนชุด) และ capacity plan
  ตามที่ BLK-010 ระบุ

## สิ่งที่ยังไม่ครอบ (ต้องปิดก่อนขึ้น production)

- **ไม่มีการเข้ารหัส backup** — BLK-010 ระบุเรื่อง encryption ไว้ แต่รอบนี้เป็นเครื่องซ้อม
  local และไฟล์ backup อยู่ใน Docker volume บนเครื่องเดียวกับที่ database ก็ไม่ได้เข้ารหัสอยู่แล้ว
  การเพิ่มการเข้ารหัสตอนนี้จึงไม่ได้ลดความเสี่ยงจริงและต้องมีระบบจัดการกุญแจมารองรับ
  **backup มี PII ครบทั้งชุด** (bcrypt hash, reset token, session, ชื่อ-เบอร์ลูกค้า ~570,000 แถว)
  ก่อนนำ backup ออกนอกเครื่องนี้ต้องเข้ารหัสและมี key management เสมอ
- ยังไม่ได้ทดสอบ restore ข้ามเครื่องหรือข้าม environment
- ยังไม่มี backup access control / restore owner ที่เป็นทางการ (BLK-010 ขอไว้) —
  ตอนนี้ใครเข้าถึงเครื่องนี้ได้ก็ restore ได้
- ยังไม่ได้ซ้อมบน environment ขนาดเท่า production จริง (เครื่องนี้เป็น dev machine)
