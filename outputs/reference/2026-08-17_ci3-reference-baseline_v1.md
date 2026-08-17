# CI3 Reference Baseline v1 — จุดยึดของ source ที่ใช้อ้างอิงตอนอัพเกรดเป็น CI4

เอกสารนี้เป็นสัญญาข้อเดียวว่า "source CI3 ที่งาน CI4 อ้างอิง" คืออะไร อยู่ที่ไหน commit ไหน และตรวจซ้ำได้ด้วยคำสั่งอะไร ทุกเอกสารและทุก script ในโปรเจกต์นี้ที่อ้าง CI3 ต้องชี้กลับมาที่ pin ในไฟล์นี้ ห้ามอ้าง "working tree ล่าสุด" อีก

เอกสารนี้นิยาม **ตัวส่วน** ของความครบถ้วนรายฟังก์ชันเท่านั้น ไม่ใช่หลักฐานว่า parity สำเร็จ — closure ของทุกจุดยังเป็น `0/1164`

## Pin

| Field | Value |
|---|---|
| Local path | `/Users/king_developer/Desktop/Project/samsoniteci3` |
| Remote | `https://github.com/metrodiesign/samsoniteci3.git` |
| Branch | `develop` |
| Commit | `8dad4e331a90f5c6765954454910b451eb0ff8e5` |
| Worktree ตอน pin | `CLEAN` (`git status --porcelain` ว่าง) |
| PHP ที่ใช้ enumerate | 8.5.7 (cli) |
| Pin ตั้งเมื่อ | 2026-08-17 |

Machine-readable — script อ่านบรรทัดนี้:

```
CI3_PIN=8dad4e331a90f5c6765954454910b451eb0ff8e5
```

Environment variable ที่ script ทุกตัวใช้หา source:

```bash
export CI3_SOURCE_ROOT=/Users/king_developer/Desktop/Project/samsoniteci3
```

ไม่สร้าง git tag บน `samsoniteci3` — repo นั้นอยู่นอกขอบเขตงานนี้ จุดยึดใช้ commit SHA เท่านั้น

## กฎการใช้ reference

| กฎ | รายละเอียด |
|---|---|
| Read-only เด็ดขาด | งาน CI4 ห้ามแก้ ห้าม commit ห้าม tag ในโครงสร้าง `samsoniteci3` ทุกกรณี |
| ต้องอยู่ที่ pin ก่อนอ่าน | ก่อนอ่านโค้ดเพื่อเขียน spec/test ต้องยืนยัน `git -C $CI3_SOURCE_ROOT rev-parse HEAD` ตรง pin และ worktree clean |
| ห้ามคัดลอก secret เข้า CI4 | `SECRETS-LOCAL.md` และค่าจริงใน `application/config/database.php` อยู่นอก git (ไม่ถูก track) — ค่าจริงต้องไปอยู่ `.env` ของ CI4 เท่านั้น ห้ามผ่าน repo |
| เปลี่ยน pin ต้อง regenerate | source เปลี่ยนบรรทัดไหน Function ID ของจุดนั้นเปลี่ยนตามสูตร (ผูก line number) ต้อง re-pin แล้ว regenerate evidence ใหม่ ห้ามแก้ ID มือ |
| เอกสารเก่าไม่ถือเป็น pin | เอกสารที่ระบุ "working tree 2026-08-16" หรือ commit `c0ef2dc394bb…` เป็น baseline ที่ตายแล้ว (ดู Drift ledger) |

## Function ID contract

Function ID และ AC ID ผลิตจากสูตร deterministic ต่อไปนี้ ยืนยันแล้วว่า re-mint ทับทุกแถวของ `2026-08-11_function-disposition-evidence_v1.md` ตรง **1411/1411** รวม anonymous callback:

| Layer | Input string | ID |
|---|---|---|
| PHP | `<relative_path>:<line>:<symbol>` เช่น `application/helpers/cias_helper.php:13:sms` | `F-PHP-` + `strtoupper(substr(sha1(input),0,12))` |
| JavaScript | `<relative_path>:<line>:<symbol>#<ordinal>` เช่น `application/views/addNew.php:207:h_recommend_createXMLRequest#1` | `F-JS-` + `strtoupper(substr(sha1(input),0,12))` |

`symbol` ใช้รูปที่เขียนในเอกสาร: PHP method เป็น `Class::method`, PHP function เป็นชื่อเปล่า, JS anonymous callback เป็น `callback.<event>@L<line>` และ `AC-FUNC-<12hex>` ใช้ hex ชุดเดียวกับ Function ID ของแถวนั้น

## Denominator: 1411 → 1164 live

baseline เดิม (evidence v1) generate จาก working tree ที่ยังไม่ commit — commit ที่มันอ้าง (`c0ef2dc394bbd6f35d39806dbcad9feca00e1442`) ไม่มีอยู่ในประวัติของ repo CI3 (`git cat-file -t` ตอบ `fatal: could not get object info` — แปลว่า object ไม่มีในคลัง) หลังจากนั้น commit `5409901` import source ใหม่โดยลบ dead file 51 ไฟล์และแทนค่า credential ด้วย placeholder จึงเกิด drift ตามตารางนี้

| ชั้น | evidence v1 | pin ปัจจุบัน | ผล |
|---|---:|---:|---|
| ไฟล์ใน manifest | 144 | ตรง 118 / เปลี่ยน 7 / หาย 19 | — |
| PHP acceptance points | 631 | 514 | หาย 117, re-hash 51 |
| JavaScript acceptance points | 780 | 650 | หาย 130 |
| **รวม** | **1411** | **1164 live + 247 retired** | invalidated 298 (21%) |

`1411 − 247 = 1164` — เลขไม่หดเงียบ ๆ ทุกจุดที่ออกจาก denominator มีแถวหลักฐานใน `2026-08-17_function-disposition-evidence_v2.md` หัวข้อ Retired points

Enumeration ที่ pin (นับด้วย `token_get_all()` และ regex ชุดเดียวกับ checker): PHP named function 514, JavaScript candidate token 650 (view inline 571 + custom external 79)

### 19 ไฟล์ที่หายจาก pin — 247 points

| Source | Layer | Points | Disposition เดิมใน v1 | เหตุ |
|---|---|---:|---|---|
| `application/controllers/--User.php` | PHP | 15 | RETIRE_PROPOSED 15 | ชื่อไฟล์พัง CI3 route ไม่ถึง |
| `application/controllers/Master.php` | PHP | 9 | MIGRATE 9 | class ไม่ตรงชื่อไฟล์ ไม่มี route |
| `application/models/Master_model.php` | PHP | 11 | MIGRATE 11 | ไม่มี caller |
| `application/libraries/Youtube.php` | PHP | 41 | REPLACE 11, RETIRE_PROPOSED 30 | third-party ไม่มี caller |
| `application/libraries/Sftp.php` | PHP | 21 | REPLACE 13, RETIRE_PROPOSED 8 | third-party ไม่มี caller |
| `application/libraries/php-excel.class.php` | PHP | 6 | REPLACE 4, RETIRE_PROPOSED 2 | third-party ไม่มี caller |
| `application/libraries/MY_Upload.php` | PHP | 5 | REPLACE 4, RETIRE_PROPOSED 1 | ไม่มี caller |
| `application/libraries/Oftp.php` | PHP | 5 | RETIRE_PROPOSED 5 | ไม่มี caller |
| `application/libraries/Google_oauth.php` | PHP | 4 | REPLACE 2, RETIRE_PROPOSED 2 | third-party ไม่มี caller |
| `application/views/tracking/trackingclose_KING_BACKUP.php` | JS | 37 | RETIRE_PROPOSED 37 | backup view |
| `application/views/tracking/report_tracking.php` | JS | 26 | MIGRATE 26 | controller โหลด `report_tracking_test` ไม่ใช่ไฟล์นี้ |
| `application/views/report_KING_BACKUP.php` | JS | 19 | RETIRE_PROPOSED 19 | backup view |
| `application/views/en/rating_KING_TH.php` | JS | 10 | MIGRATE 10 | ไม่มี loader |
| `application/views/en/rating_KING_EN.php` | JS | 10 | MIGRATE 10 | ไม่มี loader |
| `application/views/en/rating_KING_BACKUP.php` | JS | 10 | RETIRE_PROPOSED 10 | backup view |
| `application/views/th/rating.php` | JS | 10 | MIGRATE 10 | ไม่มี Rating controller ฝั่ง TH |
| `application/views/rating.php` | JS | 5 | MIGRATE 5 | ไม่มี loader |
| `application/views/includes/header_report.php` | JS | 2 | MIGRATE 2 | ไม่มี include |
| `application/views/tracking/-print_order.php` | JS | 1 | RETIRE_PROPOSED 1 | ชื่อไฟล์พัง ไม่มี loader |

รวม disposition เดิม: RETIRE_PROPOSED 130, MIGRATE 83, REPLACE 34

หลักฐาน no-caller: grep loader ทุกตัวที่ CI3 ใช้ (`loadViews`, `load_web_Views`, `load_web_th_Views`, `load_order_Views`, `load_print_Views`, `loadViewspeint`), `application/config/routes.php` และ `<script src>` ที่ pin ได้ผล 0 hit ทุกไฟล์ ตัวที่ live แทนคือ `application/views/tracking/report_tracking_test.php` (โหลดที่ `application/controllers/Order.php:510` และ `:580`) และ `application/views/en/rating.php` (โหลดที่ `application/controllers/Rating.php:40`)

**ไม่มีทางกู้จาก git**: 19 ไฟล์นี้ไม่ปรากฏใน commit ใดของ repo CI3 (`bf5355c` มีแค่ `README.md`; `5409901` ลบก่อน commit) เนื้อหาที่เหลืออยู่มีสำเนาเดียวคือ `demo/application/views/tracking/report_tracking.php` ถ้าภายหลังพบว่าลบผิด ต้องกู้จาก production host ไม่ใช่จาก repo

### 7 ไฟล์ที่เนื้อหาเปลี่ยน — 51 points ต้อง re-capture

credential ถูกแทนด้วย placeholder ตาม commit `5409901` ฟังก์ชันยังอยู่ครบและ **บรรทัดไม่เลื่อน** (ตรวจแล้ว: symbol ตรงบรรทัดเดิม 100%, ฟังก์ชันที่ไม่มี row = 0) ดังนั้น Function ID เดิมใช้ต่อได้ทั้งหมด เปลี่ยนแค่ file hash และสถานะ before-evidence

| Source | Points | SHA-256 ที่ pin |
|---|---:|---|
| `application/helpers/basic_helper.php` | 18 | `87c0e5e3ee8feecbb5572c40efb7b7210ea56d0678f4593fedc8f9a3a7903af9` |
| `application/helpers/cias_helper.php` | 10 | `d49894782be8daf5e3af8f6a065270589cf7977066c595234f9317bc53ef425f` |
| `application/controllers/Login.php` | 9 | `3cba1cfbbd0566cde8db0bfa03125dd02c0df7a25e599e33905a2118917299d0` |
| `application/config/Contact.php` | 4 | `902830e44efac93e62521b8343790c1ef3aaff19dbbb4fd248c2da46c4868a4e` |
| `application/controllers/Contact.php` | 4 | `0d1c1699df3e4d69b45ccb56e4899f622fa9476558fa8b1adcf50e2d27b7e807` |
| `application/controllers/Contact_th.php` | 4 | `5d3ed92395c40efbe6d809bfa118fb5d4ef4ae6d7392d3822578a6d21e003dbc` |
| `application/libraries/Email.php` | 2 | `fe510baf3dc0609f7186f060a40c125feae728b03e17d4ecb4ed68d265d6f164` |

จุดที่กระทบ before-behavior: `cias_helper::sms` (ThaiBulkSMS), email/SMTP, DB connect, CI encryption key — before-evidence จาก source บอกได้แค่ shape ของ request/ผลลัพธ์ ค่าจริงต้อง capture จาก environment ที่มี credential ห้าม inline ค่าเข้าเอกสาร

## Drift ledger

บันทึกไว้เพื่อไม่ให้ใครอ่านเอกสารเก่าแล้วเข้าใจว่ายังตรง pin — ไม่ rewrite เนื้อหาเอกสารเก่า

| เอกสาร | สถานะเทียบ pin | การจัดการ |
|---|---|---|
| `outputs/diagrams/2026-08-11_function-disposition-evidence_v1.md` | SUPERSEDED — baseline commit ไม่มีในคลัง, worktree DIRTY, 298 points invalidated | ถูกแทนด้วย `2026-08-17_function-disposition-evidence_v2.md` เก็บไว้เป็นประวัติ |
| `outputs/diagrams/2026-08-09_legacy-system-report_v3.md` | อ้าง path ที่ถูกลบ 5 จุด | เติมหมายเหตุชี้ไฟล์นี้ ไม่แก้เนื้อหา |
| `outputs/diagrams/2026-08-09_ci3-to-ci4-upgrade-plan_v3.md` | §21.2 ตัวเลข 1411 + คำสั่ง gate ชี้ v1 + ข้อ "ห้าม exclude โดยเดา" อ้างไฟล์ที่ถูกลบ 2 จุด | อัปเดตให้ชี้ pin และ v2 |
| `outputs/diagrams/2026-08-17_ci3-workflow-design_v1/` | ตรวจแล้วไม่อ้าง path ที่ถูกลบเลย (0 hits ทุกไฟล์) แต่ header เขียน "working tree 2026-08-16" | เติม commit pin ที่ README |

## วิธีตรวจซ้ำ

```bash
export CI3_SOURCE_ROOT=/Users/king_developer/Desktop/Project/samsoniteci3

# 1. ยืนยัน pin
git -C "$CI3_SOURCE_ROOT" rev-parse HEAD          # ต้องได้ 8dad4e331a90f5c6765954454910b451eb0ff8e5
git -C "$CI3_SOURCE_ROOT" status --porcelain      # ต้องว่าง

# 2. ยืนยัน denominator + citation + hash + ID ครบ (exit 0 เท่านั้นถือว่าผ่าน)
php scripts/check-function-disposition.php outputs/diagrams/2026-08-17_function-disposition-evidence_v2.md
```

checker ตรวจ: commit pin, worktree clean, manifest hash รายไฟล์, exact source citation, one-source-to-one-row, Function ID/AC-FUNC uniqueness, สูตร ID และ schema ของ disposition/execution state

## ข้อจำกัดที่ประกาศไว้

| ข้อจำกัด | ผล |
|---|---|
| ไม่มีไฟล์ `.sql` ใน repo CI3 | DB schema ยืนยันจาก source ไม่ได้ ต้อง capture จาก instance จริงตอน discovery |
| static caller เท่านั้น | dynamic call, string route, reflection, cron/CLI, external consumer และ production traffic ยังพิสูจน์ไม่ได้ — จุดใหม่ที่เจอจาก runtime ต้องเพิ่ม denominator แล้ว reset gate |
| ไม่มี CI4 tree | `app/` และ `spark` ยังไม่มี ทุก destination จึงเป็น `PLANNED_NOT_IMPLEMENTED` และ after-evidence ยัง MISSING |
| credential เป็น placeholder | before-behavior ของ SMS/email/DB/encryption ต้องรันใน environment ที่มีค่าจริง |
| baseline ก่อน pin กู้ไม่ได้ | evidence v1 ไม่สามารถ reproduce ได้อีก เพราะ tree ที่ใช้ generate ไม่ถูก commit ไว้ |
