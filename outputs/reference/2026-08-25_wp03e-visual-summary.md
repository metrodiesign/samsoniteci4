# WP-03E visual parity — สรุปผล (CI3 vs CI4)

วันที่ 2026-08-25 · CI3 pin `ee1c95e` · CI4 sha `2589881` · Chromium 151
หลักฐาน: **258 ภาพ / 65 หน้า / 2 viewport** (`1440x900`, `390x844`)
ตารางเต็มต่อหน้าอยู่ที่ `verdict-table.md` รายละเอียดต่อ batch อยู่ที่ `diff-batch1..5.md`

## หัวข้อเดียวที่ต้องจำ

**MATCH 0 จาก 65 หน้า** — CI4 ยังไม่ได้ทำ UI parity กับ CI3 เลย ไม่ใช่ต่างกันเล็กน้อยรายหน้า

| verdict | หน้า |
|---|---|
| MATCH | 0 |
| MINOR | 1 |
| MAJOR | 54 |
| BEHAVIOR | 10 |

| ข้อเสนอ | หน้า |
|---|---|
| FIX_CI4 | 39 |
| NEED_USER | 24 |
| CORRECT_AND_REBASELINE | 1 |
| ACCEPT | 1 |

หมายเหตุการนับ: `master-statustype-edit` ตารางลง MAJOR แต่คำอธิบายบันทึก BEHAVIOR แยกไว้ด้วย
(id ที่ไม่มีจริง CI3 ตอบ 200 CI4 ตอบ 404) รายงานต่อ batch จึงนับ BEHAVIOR ได้ 11 ส่วนตารางนับ 10

## 54 MAJOR = 5 failure class ไม่ใช่ 54 บั๊ก

### C1 — ไม่มี theme/layout ของระบบ admin (กระทบเกือบทุกหน้าหลัง login)
CI3 มี sidebar เมนู ~35 รายการ, header น้ำเงินพร้อมชื่อหน้า, card panel, footer, ปุ่ม Back / Add New
CI4 ไม่มีทั้งหมด — `app/Views/layout.php` มี `<style>` inline ~30 บรรทัดเป็น scaffold ล้วน

### C2 — เมนูล้นจอ ทำให้ทุกหน้าหลัง login กว้าง ~3313px
สาเหตุยืนยันจากโค้ด: `app/Views/layout.php:22` `nav { display: flex; align-items: center; gap: 1rem; }`
**ไม่มี `flex-wrap`** เมื่อ `$menuItems` มี ~35 รายการ nav จึงยืดเป็นแถวเดียวดันความกว้างทั้งเอกสาร
ผลข้างเคียง: พื้นหลัง header (`#111b35`) กว้างแค่ viewport รายการที่เลย ESTIMATEPRICE จึงเป็น
ตัวหนังสือขาวบนพื้นขาว อ่านไม่ออก และ mobile 390px ไม่ย่อจริง (viewport meta มีถูกต้องแล้วที่บรรทัด 13)
คลาสนี้แก้ที่ CSS จุดเดียวได้ผลทั้งกอง

### C3 — label และหัวตารางใช้ชื่อคอลัมน์ DB ดิบ
CI4 แสดง `customer_name`, `book_id`, `note`, `provider_datail`, `type_details`, `success`,
`group_id`, `role_id`, `branch_id` เป็น label ตรง ๆ
CI3 ใช้ label สองภาษาทุกช่อง (`CUSTOMER FULLNAME/ชื่อลูกค้า`) และมี `*` กำกับฟิลด์บังคับ ซึ่ง CI4 ไม่มีเลย

### C4 — หน้า edit ของ master data คือหน้า listing ที่เติมค่า
CI4 ทั้ง 10 หน้า edit = หน้า listing เดิม + เติมค่าในฟอร์ม + เปลี่ยนปุ่มเป็น Update
และยังมีตาราง listing ต่อท้าย; CI3 เป็นหน้าฟอร์มแยกมี Submit + Reset (ปุ่ม Reset หายทั้งระบบใน CI4)

### C5 — ฟิลด์/ปุ่ม/คอลัมน์หายรายหน้า
- orders-new / orders-edit: `REQUEST DATE`, `BRANCH TYPE`, `BRANCH SHORT`, `BOOK SHORT`,
  `REQUEST ID`, `TRACK ID`, `NUMBER ID` หาย; thumbnail รูปที่แนบไว้ไม่แสดง
- login: ลิงก์ Forgot Password หาย · change-password: ปุ่ม Reset หาย
- contact ทั้งสองภาษา: ข้อมูลศูนย์ซ่อม ลูกค้าสัมพันธ์ ปุ่ม Google Map หายหมด
- login history: CI3 6 คอลัมน์ CI4 เหลือ 5 (`Session Data` หาย)
- users-edit: CI3 เป็น dropdown ชื่ออ่านออก CI4 ให้พิมพ์เลข id ดิบ (ใช้งานจริงไม่ได้)
- report ทุกหน้า: ปุ่ม Export, dropdown Branch, แถบ KPI, Show N entries, ช่อง Search,
  ไอคอนเรียงลำดับ, แถบแบ่งหน้า ของ CI3 ไม่มีใน CI4
- tracking-home-th: ปุ่มยังเป็นอังกฤษ (`HOW TO CHECK` / `CHECK NOW`) CI3 เป็นไทย

## 10 BEHAVIOR — ต่างที่พฤติกรรม ต้องให้คนตัดสิน ไม่ใช่งานแก้ CSS

| เรื่อง | CI3 | CI4 |
|---|---|---|
| rating | `Rating::index()` redirect ทิ้งทันที ผู้ใช้จริงไม่เคยเห็นหน้านี้ | มีหน้าจริง (ฟอร์มดิบ Question 1-8) |
| tracking-result-th | เข้า GET ด้วย segment ไม่ได้ ต้อง POST จากฟอร์ม | เข้าตรงได้ |
| reset-password | pre-fill email readonly + รหัสสุ่มในช่อง password | ทุกช่องว่างและแก้ได้ |
| master edit id ที่ไม่มีจริง | 200 ฟอร์มเปล่า | 404 `PageNotFoundException` |
| default date `/ReportTrackingListing` | เติมวันนี้ถึงวันนี้ | เว้นว่าง (ตารางจึงคนละจำนวนแถว) |
| ปุ่ม export 4 หน้า | ยัดฟิลเตอร์ไปกับ URL (`branchId`, `status`, ช่วงวันที่) | บางหน้าไม่ส่งฟิลเตอร์เลย |
| หน้า imports 3 หน้า | โครงคนละแบบ | โครงคนละแบบ |
| tracking-result-en | ข้อความสถานะ `สถานะทดสอบ 1` | `SYNTHETIC NEW` บน trackID เดียวกัน |
| dashboard | ไทล์ REPORTS ใบเดียว | การ์ดนับ Status 1-8 |
| CI3 `isAdmin()` dead gate | ทุก role เข้า menu/background ได้จริง | บังคับ role 1 |

## ข้อจำกัดของหลักฐานชุดนี้ (ไม่กระทบ verdict)

- ภาพ CI4 ทุกใบมี debug toolbar เพราะรันโหมด development — verdict ทั้งหมดเป็นเรื่องโครงสร้าง
  (ธีมหาย ฟิลด์หาย) ไม่ใช่เรื่องที่ toolbar บัง; ชุด re-baseline หลังแก้ให้ถ่ายในโหมด production
- CI4 ออก reset token จริงในเครื่องนี้ไม่ได้ (`EncryptionException` เพราะไม่มี `encryption.key`)
  ภาพ `reset-password__ci4__*` จึงเป็นหน้าแบบไม่มี token
- รูป branch type ไม่แสดงทั้งสองระบบ เพราะไฟล์รูปไม่มีในฐาน fixture ไม่ใช่ regression ของ CI4
- ไม่ได้กดปุ่ม export ทั้งสองระบบ จึงยังไม่มีหลักฐานว่าเนื้อหาไฟล์ `.xls` ตรงกันหรือไม่
- ปุ่ม export บนหน้า `/reportsummary` ของ CI3 ชี้ `/order/excel_report` ไม่ใช่ `/Order/excel_report_sum`
  ตามที่ route map เขียนไว้ (สแกนทั้งหน้าแล้วไม่มีปุ่ม `excel_report_sum`)
