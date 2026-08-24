# WP-03E View boundary — rebaseline ตัวเลขและสถานะ (2026-08-24)

> Scope: นับสด view coupling ของ CI3 (pin `ee1c95e`) และ CI4 (`develop` HEAD) เพื่อแทนตัวเลข 70/19
> ที่เอกสารเก่าอ้างจาก snapshot ก่อน CI3 repin แล้วสรุปว่างาน WP-03E เหลืออะไร
> เอกสารนี้เป็น "บันทึกเพิ่ม" ไม่ลด/ไม่แก้เกณฑ์ DoD เดิม การลดเกณฑ์เป็นการตัดสินใจของ user
> ทุกตัวเลขมาพร้อมคำสั่งที่รันจริงและผลลัพธ์ด้านล่าง (ไม่ลอกจาก spec)

## 1. ยืนยัน pin ของ reference

```
$ git -C /Users/king_developer/Desktop/Project/samsoniteci3 rev-parse HEAD
ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6
```

ตรงกับ pin ที่คาด (`ee1c95e`) จึงนับต่อได้. CI3 เป็น read-only reference ไม่มีการแก้ไฟล์ใด ๆ

## 2. นับสดฝั่ง CI3 (`application/views/`, ไม่นับ `demo/`)

คำสั่งทั้งหมดรันที่ `/Users/king_developer/Desktop/Project/samsoniteci3` ตรึงด้วย `--include='*.php'`
ให้ผลเสถียร (กัน `.DS_Store` ทำให้ตัวเลขแกว่ง)

### 2.1 จำนวน view ทั้งหมด

```
$ find application/views -type f -name '*.php' -not -path '*/demo/*' | wc -l
103
```

### 2.2 direct-model-call views

หมายเหตุสำคัญ: คำสั่งใน spec ใช้ regex ตัว M ใหญ่ (`\->[A-Za-z_]*Model(->|\()`) ซึ่ง**นับได้ 0**
เพราะ CI3 ตั้งชื่อ model เป็น lowercase (`welcome_model`, `login_model` ...) ไม่ใช่ `SomeModel`
จึงนับด้วย pattern lowercase ที่ตรงกับ convention จริงของ CI3 แทน

```
$ grep -rlE --include='*.php' '\->[A-Za-z_]*Model(->|\()' application/views | wc -l
0
$ grep -rilE --include='*.php' '\->[a-z_]+_model(->|\()' application/views | wc -l
14
$ grep -riE  --include='*.php' '\->[a-z_]+_model(->|\()' application/views | wc -l
30
```

ผล: direct-model-call = 14 ไฟล์ / 30 จุด

รายชื่อ 14 ไฟล์ (ตัด prefix `application/views/`):

```
includes/header.php
includes/header_order.php
tracking/excel_report_tracking.php
tracking/excel_reportsummary.php
tracking/print_order.php
tracking/report_tracking_test.php
tracking/reportsummary.php
tracking/show_price_upload_excel.php
tracking/show_upload_excel.php
tracking/tracking_completed.php
tracking/tracking.php
tracking/trackingclose.php
tracking/trackingrepair.php
tracking/trackingreturn.php
```

### 2.3 coupled views (session/load/db/input/config ผ่าน superobject)

```
$ grep -rlE --include='*.php' '\$this->(session|load|db|input|config)' application/views | wc -l
53
$ grep -rE  --include='*.php' '\$this->(session|load|db|input|config)' application/views | wc -l
268
```

ผล: coupled (ตาม pattern ของ spec) = 53 ไฟล์ / 268 จุด

## 3. นับสดฝั่ง CI4 (`app/Views/`)

CI4 อ้างที่ `develop` HEAD เป็น baseline ที่นิ่ง เพราะ working tree กำลังถูก task T1 แก้ค้างอยู่
(layout.php + หลาย controller ยัง uncommitted — ดูข้อ 5)

```
$ find app/Views -type f | wc -l
37
```

grep coupling ตาม pattern ของ spec (รันบน blob ของ HEAD):

```
$ git ls-tree -r --name-only HEAD -- app/Views/ | grep '\.php$' \
    | while read f; do git show HEAD:"$f" \
        | grep -nE "session|MenuStore|db_connect|->model\(" | sed "s|^|$f:|"; done
app/Views/layout.php:2:$menuItems = service('session')->get('isLoggedIn') === true
app/Views/layout.php:3:    ? (new \App\Master\MenuStore(db_connect()))->visible((int) service('session')->get('GroupID'))
app/Views/layout.php:47:    <?php if (service('session')->get('isLoggedIn') === true): ?>
app/Views/reports/tracking.php:2:$showCmg = service('session')->get('BranchID') === null;
```

ผลบน HEAD: coupling เหลือ 4 จุด ใน 2 ไฟล์
- `app/Views/layout.php` (3 จุด) — สร้าง `MenuStore` + เรียก `service('session')`/`db_connect()` ในไฟล์ view;
  ปิดอยู่ใน task T1 (ดูข้อ 6.ก)
- `app/Views/reports/tracking.php:2` — เรียก `service('session')->get('BranchID')` ตรงในไฟล์ view;
  จุดนี้อยู่นอก scope ของ T1 ตาม state.md (T1 = layout.php เท่านั้น) จึงยังเปิดอยู่ (ดูข้อ 6.ก)

## 4. ตารางเทียบ ตัวเลขเก่า (charter/legacy report) กับตัวเลขนับสดวันนี้

ตัวเลขเก่ามาจาก `outputs/diagrams/2026-08-09_legacy-system-report_v3.md:341-342` (section 3.9) ซึ่งเขียนบน
working tree ก่อน CI3 repin — เอกสารนั้นระบุเองว่า source ทางการตอนเขียนคือ commit `8dad4e33`
(หมายเหตุ baseline 2026-08-17 ในไฟล์เดียวกัน)

| metric | ตัวเลขเก่า (บน `8dad4e33`) | นับสดวันนี้ (บน `ee1c95e`) | หมายเหตุ |
|---|---|---|---|
| direct-model-call views | 19 ไฟล์ / 46 จุด | 14 ไฟล์ / 30 จุด | เทียบได้ตรง metric; ลดลงเพราะ repin ลบไฟล์ dead code บางส่วน (เช่น `header_report.php` ตามหมายเหตุ baseline) |
| coupled views | 70 ไฟล์ / 244 จุด | 53 ไฟล์ / 268 จุด | นิยาม metric ต่างกัน (ดูด้านล่าง) — ไม่ใช่การเทียบตรง |

หมายเหตุนิยาม coupled: เลขเก่า "70 ไฟล์ / 244 จุด" นับ "session/model ผ่าน CI superobject" ส่วน pattern
ของ spec (`\$this->(session|load|db|input|config)`) นับคนละเซ็ต (รวม `load`/`db`/`input`/`config`
แต่ไม่รวม model) จึงเทียบตรงกันไม่ได้ ตัวเลขที่ต่างเป็นเพราะนิยาม ไม่ใช่ regression; ถ้านับด้วยนิยามใกล้เคียง
ของเลขเก่า (`\$this->(session|[a-z_]+_model)`) วันนี้ได้ 62 ไฟล์ / 218 จุด

## 5. สถานะ working tree ของ CI4 ระหว่างนับ (ทำไมใช้ HEAD เป็น baseline)

task T1 กำลังแก้ boundary ค้างอยู่ (uncommitted) ตอนนับ:

```
$ git status --porcelain -- app/
 M app/Controllers/BaseController.php
 M app/Controllers/Dashboard.php
 M app/Controllers/Login.php
 M app/Controllers/Order.php
 M app/Controllers/PasswordReset.php
 M app/Controllers/Reports.php
 M app/Views/layout.php
```

working tree ของ `layout.php` ถูกแก้ให้รับ `$menuItems`/`$isLoggedIn` เป็น view variable จาก controller
(boundary-correct) แต่ยังไม่ commit เอกสารนี้จึงอ้าง HEAD เป็นตัวเลขที่ reproduce ได้ ไม่ผูกกับ WIP ที่ขยับ

## 6. งานที่ยังค้างเพื่อปิด DoD WP-03E

DoD: "controller supplies data; view ไม่เรียก model/session policy โดยตรง โดย UX/UI เดิมไม่เปลี่ยน"
พร้อม paired visual/interaction comparison

### (ก) view boundary — ครึ่งที่ตรวจได้ด้วย code/test

- `app/Views/layout.php` — ปิดใน task T1 (คู่กับ task นี้); ที่ HEAD ยังมี 3 จุด working tree แก้แล้วรอ commit
- `app/Views/reports/tracking.php:2` — จุด coupling เพิ่มที่พบระหว่างนับ อยู่นอก scope ของ T1
  (state.md ระบุ T1 = layout.php เท่านั้น) ยังเปิดอยู่ ต้องให้ orchestrator/user ตัดสินว่าจะรวมเข้ารอบนี้หรือ
  แยก task

### (ข) paired visual/interaction comparison — ยังไม่เริ่ม

ต้องการ dependency ที่ charter ระบุคือ "approved UX/UI snapshots + pinned browser/viewport" ซึ่งยังไม่มีใน repo
ตรวจจริง `evidence/`:

```
$ ls -1 evidence/
db-foundation-001
route-auth-authorization-baseline.json
route-auth-authorization-browser.json
wp00c
```

มีแค่ `db-foundation-001/`, `wp00c/` และ route-auth JSON baseline 2 ไฟล์ — **ไม่มีชุด approved UX/UI
snapshot สำหรับ WP-03E visual comparison เลย** เป็นของที่ต้องรอ user ตัดสิน ห้าม agent สร้างแทน
(charter ข้อ 5 ห้ามสร้าง approval/ผลทดสอบแทนมนุษย์)

## 7. หน้าจอที่ CI3 มีแต่ CI4 ไม่มี — ตรวจ dead code

ตรวจว่าไฟล์ CI3 3 ตัวเป็น dead code จริง (ไม่มี route/`load->view` ชี้ถึง):

- `application/views/access.php` — **dead**: ไม่พบ `load->view('access')` ในcontroller ใด และไม่มี route
  ชี้ถึง (`grep -rn "view('access'" application/controllers` = ว่าง)
- `application/views/welcome_message.php` — **ไม่ dead สนิท**: ถูกโหลดที่
  `application/controllers/welcome.php:14` (`$this->load->view('welcome_message')`) และคลาส `Welcome`
  เข้าถึงได้ที่ URL `/welcome` ตาม default routing ของ CI3 อย่างไรก็ตามมันเป็นหน้า scaffold ตั้งต้นของ
  framework ไม่ได้ลิงก์จาก navigation ของแอป และ `default_controller` จริงคือ `track`
  (`application/config/routes.php:41` override บรรทัดตัวอย่างใน comment block ที่บรรทัด 28) — จัดเป็น
  leftover scaffold ที่ยัง URL-reachable ไม่ใช่ dead code แท้ (บันทึกตามจริง ต่างจากที่ spec ระบุว่า dead)
- `application/views/email/resetPassword.php` — **dead**: อ้างอิงจุดเดียวที่
  `application/controllers/Login.php:193` แต่บรรทัดนั้น comment ทิ้ง
  (`//$CI->email->message($CI->load->view('email/resetPassword', ...))`) จึงไม่ถูกเรียกจริง

ไม่มีไฟล์ใดในสามตัวนี้เป็น "หน้าจอที่ผู้ใช้เข้าถึงได้จริงแล้วขาดใน CI4" ที่ต้อง port

## 8. หลักฐานประกอบที่ spec อ้างแล้วยืนยันเอง

- ไฟล์ที่ legacy report อ้าง (`tracking/report_tracking.php:346-354`) ไม่มีในCI3 ปัจจุบัน — ถูก rename
  เป็น `tracking/report_tracking_test.php` (`ls application/views/tracking/report_tracking*.php`
  เจอเฉพาะ `report_tracking_test.php`)

## 9. ลิงก์ที่เกี่ยวข้อง

- ตัวเลขเก่า 70/19: `outputs/diagrams/2026-08-09_legacy-system-report_v3.md:341-342`
- แถว WP-03E ใน upgrade plan: `outputs/diagrams/2026-08-09_ci3-to-ci4-upgrade-plan_v3.md:372`
- gap docs: `outputs/reference/2026-08-23_phase2-3-parity-gap_v1.md`,
  `outputs/reference/2026-08-23_phase4-6-parity-gap_v1.md`
