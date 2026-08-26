# WP-03F re-baseline — สรุปผลและงานที่ยังเหลือ

วันที่ 2026-08-26 · ตารางราย 65 หน้าอยู่ที่ `2026-08-26_wp03f-rebaseline-verdict-table.md`

## ผลรวม

งาน wp03f ปิด failure class ไปได้จริง 3 คลาสครึ่ง แต่ **ยังไม่ถึง parity 100 เปอร์เซ็นต์ตาม charter**

| class | สถานะหลัง wp03f | หลักฐาน |
|---|---|---|
| C1 ไม่มี theme/layout admin | **ปิด** | ทุกหน้าหลัง login มี sidebar จัดกลุ่ม, header น้ำเงิน, footer ตรง CI3 |
| C2 เมนูล้นจอ / mobile พัง | **ปิด** | 61/65 หน้าวัด `scrollWidth` ที่ 390 ได้ `390` ตรง CI3 (ก่อนแก้ ~3313); เหลือ 4 หน้า public ที่ 412-413 |
| C3 label เป็นชื่อคอลัมน์ DB ดิบ | **ปิด** | label สองภาษาโผล่ครบในฟอร์ม master, order, users, background |
| C4 หน้า edit คือ listing ที่เติมค่า | **ปิด** | master 10 type + menu + background แยก listing/add/edit จริง มีปุ่ม Reset ครบ |
| C5 ฟิลด์/ปุ่ม/คอลัมน์หายรายหน้า | **ปิดบางส่วน** | ปิดแล้ว: branch selector 6 หน้า report, `sdate` ใน `/orders`, users แยก 3 หน้า + ปุ่มลบ, order ID คำนวณจาก book+number, dashboard เป็นกริดปุ่ม, คอลัมน์ listing ของ branch/book/menu/background ยังไม่ปิด: หน้า public/auth, ฟอร์มและ listing ของ order, ตัวควบคุมตารางของ report ทุกหน้า |

ตัวเลข verdict: MAJOR ลดจาก 54 เหลือ **30** หน้า, MINOR เพิ่มจาก 1 เป็น 33 หน้า, MATCH ยังเป็น **0**

## 30 หน้าที่ยัง MAJOR = 4 กลุ่มงาน ไม่ใช่ 30 บั๊ก

### G1 — หน้า public และ auth ยังไม่ถูกแตะเลย (6 หน้า)

`tracking-home-en`, `tracking-home-th`, `contact-form-en`, `contact-form-th`, `login`, `forgot-password`

งาน t1-t10 ทั้งหมดลงที่หน้าหลัง login กลุ่มนี้จึงยังเป็นสภาพเดิมตั้งแต่ WP-03E

- `contact-form-en` และ `contact-form-th`: CI4 เป็นฟอร์ม HTML ดิบไม่มี CSS เลย
  บล็อก `REPAIR CENTER` / `CUSTOMER RELATION` พร้อมที่อยู่และเบอร์โทร กับปุ่ม `Google Map` หายทั้งหมด
- `login`: ไม่มีแบนเนอร์ภาพหัวหน้า, ไม่มีหัวข้อ `Tracking`, **ไม่มีลิงก์ `Forgot Password`**
  (เข้าหน้าลืมรหัสจากหน้า login ไม่ได้เลย)
- `forgot-password`: ไม่มีแบนเนอร์และหัวข้อ `Tracking` เหมือนกัน
- `tracking-home-th`: **ปุ่มยังเป็นภาษาอังกฤษ** `HOW TO CHECK` / `CHECK NOW` ขณะที่ CI3 เป็น
  `วิธีตรวจสอบสถานะ` / `ติดตาม`
- `tracking-home-en` และ `tracking-home-th`: ปุ่มสองปุ่มเรียงลงมาแทนที่จะอยู่แถวเดียว
  และบล็อกเนื้อหาไม่กึ่งกลางจอ
- ทั้งสี่หน้าของกลุ่ม tracking ยังล้นแนวนอนที่ 390 (412-413)

### G2 — ฟอร์มและ listing ของ order (10 หน้า)

`orders-new`, `orders-edit`, `orders-print`, `order-listing-status1/2/3/4/5/7`, `report-tracking-listing`

- ฟอร์ม order: CI3 เป็นการ์ดสองคอลัมน์ มีหัวข้อกลุ่ม `ENTER REQUEST ORDER DETAILS` /
  `URGENT/ซ่อมด่วน` label ตัวพิมพ์ใหญ่พร้อม `*` กำกับฟิลด์บังคับ และปุ่ม `ADD IMAGE`
  ส่วน CI4 เป็นคอลัมน์เดียวเรียงยาว label ตัวพิมพ์เล็ก ไม่มี `*` และใช้ `Choose Files` แทน
- listing ของ order: CI3 มีหัวข้อ `<คิว> List` ในการ์ด, ช่อง From Date / To Date / Detail,
  ปุ่ม action เป็นไอคอน และ **ปุ่ม `Add New` บนคิว 1** ส่วน CI4 หัวข้อเป็น `NEW ORDER`,
  ไม่มี `Add New`, action เป็นลิงก์ข้อความ `Edit` / `Print` และมีแถบ Provider + Send เพิ่มเข้ามา
- ชื่อคอลัมน์ต่างกันเกือบทุกคิว เช่น `TrackID` เป็น `Tracking ID`, `OrderID` เป็น `Order`
- `orders-print`: CI3 เป็นใบรับซ่อมที่มีตาราง `ใบรับซ่อม` พร้อม checkbox condition / estimateprice / fixed
  และปุ่ม `btnPrint` ส่วน CI4 ไม่มีองค์ประกอบเหล่านี้

### G3 — ตัวควบคุมตารางของ report หายทั้งหมด (11 หน้า)

`report-ratings`, `report-jobs-by-day`, `report-pending`, `report-pending-total`,
`report-in-progress-average`, `report-in-progress`, `report-summary`, และ `export-*` อีก 4 หน้า

CI3 ใช้ DataTables ทุกหน้า จึงมีครบ: `Show N entries`, ช่อง `Search:` ของตาราง,
หัวคอลัมน์กดเรียงลำดับได้, แถบ `Previous / 1 / Next` และ dropdown เลือกหลายสถานะ (`None selected`)
CI4 ไม่มีสักอย่าง มีแค่ฟิลเตอร์ด้านบนกับปุ่ม `Filter`

`report-summary` หนักสุด: CI3 แสดง 13 คอลัมน์ (รวม Branch User, Urgent, Fullname, Tel, Email,
Category, Condition, Estimate Price, Equipment) CI4 เหลือ 8 คอลัมน์

ปุ่ม export มีทั้งสองระบบแต่คนละชื่อ (`Export` กับ `Export XLS`) — เรื่องนี้เป็นแค่ข้อความ

### G4 — คอลัมน์หายในหน้า history และ contact (3 หน้า)

`login-history-own`, `users-history-of-user`, `contact-listing`

- history ทั้งสองหน้า: คอลัมน์ `Session Data` หาย (ตรงกับที่ WP-03E เคยบันทึกไว้)
  และไม่มีช่องค้นหากับแถบแบ่งหน้า `1 2 3` ที่ CI3 มี
- `contact-listing`: CI3 มีคอลัมน์ `Id`, `Samsoniteid`, `Detail`, `Date`
  CI4 เหลือ `Tracking ID`, `Message`, `Created` — ต้องให้ user ตัดสินว่าจะยึด CI3 หรือรับของ CI4

## 33 หน้าที่เป็น MINOR — ต่างที่สกิน ไม่กระทบการใช้งาน

master data 20 หน้า, imports 3 หน้า, menu/background 4 หน้า, users 2 หน้า,
dashboard, change-password, tracking-result สองภาษา

รูปแบบความต่างเหมือนกันหมดสามข้อ

1. **หัวข้อหน้า** — CI3 ใช้ `<ชื่อ> Management  Add, Edit, Delete` + หัวข้อในการ์ด `<ชื่อ> List`
   CI4 ใช้ `Master data: <type>` บรรทัดเดียว
2. **ไม่มีการ์ดขาวครอบตาราง/ฟอร์ม** และช่องค้นหาอยู่เต็มความกว้างด้านบนแทนที่จะอยู่มุมขวาของการ์ด
3. **ปุ่ม action เป็นลิงก์ข้อความ** (`Edit`) กับปุ่มสี่เหลี่ยม (`Delete`) แทนปุ่มไอคอนดินสอ/ถังขยะของ CI3

ฟอร์ม edit ของ master ยังเป็นคอลัมน์เดียวขณะที่ CI3 เป็นสองคอลัมน์ในการ์ด

ฟิลด์ที่ CI4 มีเกิน CI3 (`book order` ใน branch, `bunber_limit` ใน book, ฟิลด์ `_th` 6 ตัวใน background)
เป็นการเบี่ยงที่อนุมัติไว้แล้วตอน t7 (ถอดออกจากฟอร์มแล้วแก้ค่าไม่ได้) ไม่ใช่ finding ใหม่

## หลักฐานและวิธีทำซ้ำ

ภาพเก็บนอก repo ตาม decision เดิม เอกสารสองใบนี้คือส่วนที่ commit

| ของ | ที่อยู่ |
|---|---|
| ภาพ CI4 ชุดใหม่ 130 ไฟล์ | `evidence/wp03f-visual/` |
| ภาพ CI3 ชุดเดิม 136 ไฟล์ (ใช้ซ้ำ pin ไม่เปลี่ยน) | `evidence/wp03e-visual/` |
| manifest ของการถ่าย + ผล probe | scratchpad ของ session (`ci4-capture-manifest.json`, `mobile-widths.json`, `dom-inventory.json`) |

ขั้นตอนที่รันจริง

1. ลบแถวขยะจาก verify t5 ที่จะติดภาพหน้า `/menu`
   `DELETE FROM group_menu WHERE id=5 AND name LIKE 'VERIFY MENU UPDATED%'` บนฐาน `samsonite_ci4` (ลบ 1 แถว)
2. rebuild service `ci4` อย่างเดียวเป็นโหมด production ด้วย override นอก repo
   `docker compose -p samsonitetracking-ci4-migration -f compose.yaml -f <override> up -d --build ci4`
   override ปิดสองอย่างที่ทำให้ harness HTTP ล้วนใช้ไม่ได้ และไม่กระทบ markup ที่เอาไปเทียบ
   - `app_forceGlobalSecureRequests=false` มิฉะนั้นทุก request ตอบ `307` ไป https ที่ไม่มีจริง
   - `cookie_secure=false` มิฉะนั้น `/login` ตอบ `500`
     `SecurityException: Attempted to send a secure cookie over a non-secure connection`
3. ยืนยันว่าไม่มี debug toolbar แล้ว `curl -s .../login | grep -c -i debugbar` ได้ `0`
4. ถ่าย 130 ภาพด้วย Chromium `151.0.7922.34` ผ่าน playwright (`fullPage`) ทุกใบตอบ `200` ไม่มี redirect
5. วัด mobile overflow และ DOM inventory ทั้งสองระบบ
6. คืน service `ci4` กลับ `CI_ENVIRONMENT=development` แล้ว `/health` ตอบ `{"status":"ok","service":"ci4"}`

Docker non-interference: เทียบ container / network / volume ก่อนและหลังทั้งรอบ ไม่มี diff
แตะเฉพาะ service `ci4` ของ project `samsonitetracking-ci4-migration` เท่านั้น

## สิ่งที่ยังไม่ได้ verify

- **หน้าที่ระบุว่า `ไม่ได้ตรวจภาพ` ในช่อง mobile ของตาราง** ตัดสินจาก DOM inventory กับผลวัด
  `scrollWidth` เท่านั้น ยังไม่ได้เปิดดูภาพ 390x844 ทีละใบ
- **ปุ่ม export ยังไม่ได้กดจริง** ทั้งสองระบบ (เหมือนรอบ WP-03E) จึงไม่มีหลักฐานว่าไฟล์ที่ได้ตรงกัน
- **`rating-form` ไม่มีคู่ CI3** ตาม decision `CORRECT_AND_REBASELINE` ที่อนุมัติไว้แล้ว
