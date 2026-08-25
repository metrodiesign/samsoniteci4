# diff-batch4 — เทียบภาพ CI3 vs CI4 กลุ่ม admin (12 หน้า)

ดูภาพครบ 48/48 ไฟล์ (12 page-id x 2 ระบบ x 2 viewport) ตัดสินจากภาพจริงทุกใบ
ไม่มีไฟล์ไหนเปิดไม่ได้ ไม่มีหน้าไหนถูกข้าม

## สรุปตัวเลข

| verdict | จำนวนหน้า | รายชื่อ |
|---|---|---|
| MATCH | 0 | - |
| MINOR | 0 | - |
| MAJOR | 9 | login-history-own, users-history-of-user, contact-listing, menu-listing, menu-edit, background-listing, background-edit, users-listing, users-edit |
| BEHAVIOR | 3 | imports-status, imports-price, imports-new-order |

ลำดับความรุนแรงที่ใช้ตัดสินช่อง verdict: MATCH < MINOR < MAJOR < BEHAVIOR
(BEHAVIOR ถือว่าหนักสุดเพราะกระทบพฤติกรรมระบบ ไม่ใช่แค่ภาพ)

**ข้อควรอ่านก่อนตีความตัวเลข**: ที่ไม่มีหน้าไหนได้ MATCH เลย เพราะมีปัญหาระดับ
ระบบ 1 ข้อโดนทุกหน้าเท่ากัน (mobile overflow ของ CI4 — ดูหัวข้อถัดไป) ถ้าตัดข้อนั้น
ออกแล้วดูเฉพาะ desktop จะได้ MINOR 2 หน้า (contact-listing, menu-edit),
MAJOR 7 หน้า, BEHAVIOR 3 หน้า

## ความต่าง 2 ข้อที่โดนทุกหน้าเท่ากัน (root cause เดียว อย่านับซ้ำเป็น 12 bug)

### G1 — mobile ของ CI4 ไม่ยุบ layout (ทำให้ทุกหน้าได้ mobile = MAJOR)

ภาพ CI4 ทุกใบในชุดนี้กว้าง 3313px ทั้งที่ตั้ง viewport 390 เพราะแถบ nav แนวนอน
ล้นออกด้านข้าง ผลคือที่จอ 390px ผู้ใช้เห็นแค่แถบซ้ายสุดแล้วต้องเลื่อนจอแนวนอน
ส่วน CI3 ยุบเป็น layout mobile จริง (ภาพกว้าง 375px มีปุ่ม hamburger การ์ดเรียงเป็นแถวเดียว)
manifest ยืนยันแล้วว่าไม่ใช่ artifact ของการถ่าย เป็น responsive issue ของ CI4 จริง
ข้อเสนอ: FIX_CI4 (แก้ที่ nav ตัวเดียว หายทั้ง 12 หน้า)

### G2 — shell ของหน้าเปลี่ยนทรงทั้งระบบ (ไม่นับเป็น MAJOR รายหน้า)

CI3 ใช้ sidebar เมนูซ้าย + แถบหัวมีปุ่ม Back / ชื่อผู้ใช้ SYNTHETIC ADMIN / Sign out
+ footer เบอร์ 02-761-9999 + หัวหน้าเป็นแบนเนอร์น้ำเงินพร้อม breadcrumb
ส่วน CI4 ใช้แถบ nav แนวนอนอย่างเดียว ไม่มี Back ไม่มีชื่อผู้ใช้ ไม่มี Sign out
ไม่มี footer และไม่มีการ์ดขาวครอบเนื้อหา
ประเมินว่าเป็นการเปลี่ยน template ที่ตั้งใจ ไม่ใช่ของหายรายหน้า จึงไม่เอาไปตัดสิน verdict
แต่ควรให้ user ยืนยันว่ารับได้ โดยเฉพาะปุ่ม Sign out ที่หายไปจากทุกหน้า
ข้อเสนอ: NEED_USER

## ตารางผล

| page-id | desktop | mobile | verdict | สิ่งที่ต่าง | ข้อเสนอ |
|---|---|---|---|---|---|
| imports-status | BEHAVIOR | MAJOR | BEHAVIOR | CI3 มีปุ่ม Upload + Reset (กดแล้ว commit ทันที) CI4 มีปุ่ม Preview ปุ่มเดียว (มีขั้น preview คั่น) label ต่างกัน EXCEL FILE: vs XLSX or XLS workbook | NEED_USER |
| imports-price | BEHAVIOR | MAJOR | BEHAVIOR | เหมือน imports-status ทุกข้อ | NEED_USER |
| imports-new-order | BEHAVIOR | MAJOR | BEHAVIOR | เหมือน imports-status ทุกข้อ | NEED_USER |
| login-history-own | MAJOR | MAJOR | MAJOR | CI4 หายไป 3 อย่าง: คอลัมน์ Session Data, ช่อง Search, หัวข้อระบุตัวผู้ใช้; ค่าใน 2 คอลัมน์ผิด: Browser ขึ้นคำว่า "Browser" (CI3 ขึ้น "Chrome 151.0.0.0") และ Platform ขึ้น "Unknown" (CI3 ขึ้น "Mac OS X"); pagination CI3 มีเลขหน้า 1 2 Next ส่วน CI4 มีแค่ลิงก์ Next | FIX_CI4 |
| users-history-of-user | MAJOR | MAJOR | MAJOR | ต่างชุดเดียวกับ login-history-own ทุกข้อ (ทั้งสองระบบ render หน้านี้เหมือนหน้า own history ของตัวเอง) | FIX_CI4 |
| contact-listing | MINOR | MAJOR | MAJOR | desktop: ข้อมูล 1 แถวตรงกันครบทุกค่า ต่างแค่ CI4 ไม่มีคอลัมน์ Id (93001) และชื่อหัวคอลัมน์ต่าง (Samsoniteid/Detail/Date vs Tracking ID/Message/Created); mobile: ตกตาม G1 | FIX_CI4 |
| menu-listing | MAJOR | MAJOR | MAJOR | CI3 เป็นตาราง Id / Menu Group Name / Actions(ปุ่มดินสอ) + ปุ่ม Add New แยกหน้า; CI4 ไม่มีตาราง เป็น bullet list ลิงก์ 4 รายการ ไม่มีคอลัมน์ Id ไม่มีปุ่ม action และเอาฟอร์มสร้างใหม่มาแปะไว้บนหน้า listing เลย; ชื่อกลุ่ม 4 รายการตรงกัน | FIX_CI4 |
| menu-edit | MINOR | MAJOR | MAJOR | ข้อมูลตรงกันเป๊ะ (ชื่อ SYNTHETIC CENTRAL, checkbox ติ๊กครบ 10 กลุ่มเหมือนกัน) ต่างที่ CI4 ไม่มีปุ่ม Reset (CI3 มี Submit + Reset) และ CI4 แสดง listing ต่อท้ายฟอร์มในหน้าเดียวกัน | FIX_CI4 |
| background-listing | MAJOR | MAJOR | MAJOR | CI3 เป็นตาราง Id / Track / Tracks Tatus / Contact / Status / Actions มี 1 แถว (97001, Status = Publishing) พร้อมช่องรูป 3 ช่อง; CI4 ไม่มีตารางเลย เป็นฟอร์มสร้างใหม่ (Status + ช่องอัปโหลด 12 ช่อง) + bullet list ลิงก์เดียว "Background 97001" ไม่เห็นค่า Status ไม่เห็นรูป ไม่มีปุ่ม action | FIX_CI4 |
| background-edit | MAJOR | MAJOR | MAJOR | CI3 จัดเป็น 3 หัวข้อ (Track & Trace / Track Status / Contact Us) มีช่องอัปโหลด 6 ช่องเฉพาะ EN พร้อม preview รูปเดิม + PUBLISHING STATUS แบบ Yes/No + Submit/Reset; CI4 เป็นรายการแบน 12 ช่อง (EN 6 + _th 6) ป้ายชื่อเป็นชื่อคอลัมน์ดิบ image_track_laptop ฯลฯ ไม่มี preview รูปเดิม Status เป็น dropdown ไม่มีปุ่ม Reset | NEED_USER |
| users-listing | MAJOR | MAJOR | MAJOR | CI3 เป็นตาราง No / Name / Email / Mobile / Role / Actions(แก้ไข + ลบ) 3 แถว + ปุ่ม Add New; CI4 ไม่มีตาราง เป็น bullet list ชื่อ+อีเมล 3 รายการ หายคอลัมน์ Mobile และ Role หายปุ่มลบ และเอาฟอร์มสร้าง user มาแปะบนหน้า listing (ป้ายชื่อเป็น field ดิบ group_id / role_id / branch_id); จำนวนผู้ใช้ 3 คนตรงกันสองระบบ | FIX_CI4 |
| users-edit | MAJOR | MAJOR | MAJOR | CI3 ใช้ dropdown ที่แสดงชื่อจริง (USER GROUP = SYNTHETIC BRANCH, BRANCH TYPE = SYNTHETIC RETAIL, BRANCH = SYNTHETIC BRANCH A, ROLE = SYNTHETIC OPERATOR); CI4 ใช้ช่องกรอกข้อความที่แสดงเลข id ดิบแทน (group_id 4 / role_id 2 / branch_id 1) และ **ไม่มีฟิลด์ BRANCH TYPE เลย**; CI4 มีฟิลด์ username (CI3 ไม่มี) และไม่มีปุ่ม Reset | FIX_CI4 |

## รายละเอียดหน้าที่ verdict เป็น MAJOR หรือ BEHAVIOR

### imports-status / imports-price / imports-new-order — BEHAVIOR

ไฟล์อ้างอิง: `imports-status__ci3__1440x900.png` เทียบ `imports-status__ci4__1440x900.png`
(อีกสองหน้าโครงเหมือนกันทุกประการ ต่างแค่หัวข้อ)

CI3 หน้าเดียวจบ: หัวข้อ "Upload Management Add / Upload" > การ์ด "ENTER UPLOAD DETAILS"
> label "EXCEL FILE:" > ช่องเลือกไฟล์ > ปุ่ม **Upload** และ **Reset**
กด Upload คือ commit เข้าระบบเลย

CI4: หัวข้อ "Import status" > label "XLSX or XLS workbook" > ช่องเลือกไฟล์ >
ปุ่ม **Preview** ปุ่มเดียว ไม่มี Reset — แปลว่ามีขั้น preview คั่นก่อน commit

นี่ไม่ใช่ layout เพี้ยน แต่เป็น flow ที่ต่างกันจริง จำนวนขั้นที่ผู้ใช้ต้องกดเปลี่ยนไป
ตัดสินไม่ได้จากภาพว่า CI4 ตั้งใจเพิ่มขั้น preview (ซึ่งอาจดีกว่า) หรือหลุด scope
จึงเสนอ NEED_USER: ถ้า user ยืนยันว่า preview เป็นของใหม่ที่ตั้งใจ ให้เปลี่ยนเป็น
CORRECT_AND_REBASELINE; ถ้าต้องการ parity ตรง ๆ ให้เป็น FIX_CI4

### login-history-own / users-history-of-user — MAJOR

ไฟล์อ้างอิง: `login-history-own__ci3__1440x900.png` เทียบ `login-history-own__ci4__1440x900.png`
และคู่ `users-history-of-user__*` ซึ่งให้ผลชุดเดียวกัน

หัวตารางสองฝั่ง:
- CI3 6 คอลัมน์: Session Data | IP Address | User Agent | Agent Full String | Platform | Date-Time
- CI4 5 คอลัมน์: Date | IP | Browser | Agent | Platform

สิ่งที่หายฝั่ง CI4:
1. คอลัมน์ **Session Data** (CI3 แสดง JSON ของ session เช่น `{"role":"1","GroupID":"1",...}`) หายทั้งคอลัมน์
2. ช่อง **Search** มุมขวาบนของตาราง หายไป
3. หัวข้อระบุตัวผู้ใช้ **"SYNTHETIC ADMIN : <อีเมลลูกค้าสัมพันธ์ของแบรนด์>"** หายไป —
   สำคัญกับหน้า users-history-of-user เป็นพิเศษ เพราะไม่มีอะไรบอกว่ากำลังดู history ของใคร
4. pagination: CI3 มีปุ่มเลขหน้า `1` `2` `Next` ส่วน CI4 มีแค่ลิงก์ `Next` ตัวเดียว
   (กระโดดข้ามหน้าไม่ได้ และไม่รู้ว่ามีทั้งหมดกี่หน้า)

สิ่งที่ค่าเพี้ยนฝั่ง CI4 (สองข้อนี้น่าจะเป็น bug จริงไม่ใช่ดีไซน์):
5. คอลัมน์ **Browser** แสดงคำว่า `Browser` ทุกแถว ส่วน CI3 แสดง `Chrome 151.0.0.0`
   — ดูเหมือน CI4 ยังไม่ได้ parse user agent จริง
6. คอลัมน์ **Platform** แสดง `Unknown` ทุกแถว ส่วน CI3 แสดง `Mac OS X`
   — ทั้งที่ agent string ในคอลัมน์ Agent ของ CI4 เองมี `Macintosh; Intel Mac OS X 10_15_7` อยู่ครบ

เรื่องจำนวนแถว: **ไม่ได้เอามาตัดสิน** ตามที่ manifest เตือน (tbl_last_login โตทุกครั้งที่ login
สองฐานไม่มีทางเท่ากัน) — สังเกตว่าจำนวนแถวต่อหน้าเท่ากันที่ 5 แถวทั้งสองระบบ ตรงนี้ถือว่าตรง

หมายเหตุ: ไฟล์ CI4 ของสองหน้านี้เหมือนกันทุก byte (ตรวจด้วย md5) ซึ่งถูกต้อง
เพราะ admin ที่ล็อกอินคือ userId 9001 เอง หน้า own history กับ history ของ 9001
จึงเป็นข้อมูลชุดเดียวกัน — ฝั่ง CI3 ก็แสดงผลเหมือนกันสองหน้าเช่นกัน

### contact-listing — MAJOR (มาจาก mobile เท่านั้น)

ไฟล์อ้างอิง: `contact-listing__ci3__1440x900.png` เทียบ `contact-listing__ci4__1440x900.png`

desktop แทบตรงกัน: ข้อมูล 1 แถวเท่ากันตามที่ manifest ยืนยันจาก DB และค่าทุกช่องตรงกัน
(SYNTHETIC CONTACT / wp00c-contact@example.invalid / WP00C-TRACK-001 / 0000000000 /
SYNTHETIC CONTACT MESSAGE / 2026-08-10 07:00:00) ต่างแค่ CI4 ไม่มีคอลัมน์ `Id` (ค่า 93001)
และชื่อหัวคอลัมน์เปลี่ยนคำ (Samsoniteid → Tracking ID, Detail → Message, Date → Created)
สองข้อนี้ไม่กระทบการใช้งาน จึงให้ desktop = MINOR

หน้านี้ขึ้นเป็น MAJOR เพราะ mobile ตกตาม G1 อย่างเดียว — แก้ G1 แล้วหน้านี้จบ

### menu-listing — MAJOR

ไฟล์อ้างอิง: `menu-listing__ci3__1440x900.png` เทียบ `menu-listing__ci4__1440x900.png`

CI3: แบนเนอร์ "Menu Management Add, Edit, Delete" + ปุ่ม **Add New** มุมขวาบน +
การ์ด "MENU LIST" + ช่อง Search + ตาราง 3 คอลัมน์ (Id | Menu Group Name | Actions)
4 แถว แต่ละแถวมีปุ่มดินสอสำหรับแก้ไข

CI4: หัวข้อ "Menu groups" + ช่อง Search + ปุ่ม Search แล้วต่อด้วย **ฟอร์มสร้างเมนูกลุ่มใหม่**
(ช่อง Name + กล่อง "Visible menu groups" ที่มี checkbox 10 รายการ + ปุ่ม Create)
แล้วปิดท้ายด้วย **bullet list ลิงก์ 4 รายการ** (SYNTHETIC CENTRAL / OPERATIONS / REPORTING / BRANCH)

ชื่อกลุ่ม 4 รายการตรงกันทั้งสองระบบ ข้อมูลไม่หาย แต่รูปแบบการนำเสนอต่างกันมาก:
ไม่มีตาราง ไม่มีคอลัมน์ Id ไม่มีคอลัมน์ Actions และหน้าที่ควรเป็น listing กลายเป็นหน้า
listing+create รวมกัน (CI3 แยกหน้า Add New ออกไปต่างหาก)

### menu-edit — MAJOR (มาจาก mobile เท่านั้น)

ไฟล์อ้างอิง: `menu-edit__ci3__1440x900.png` เทียบ `menu-edit__ci4__1440x900.png`

เนื้อข้อมูลตรงกันเป๊ะ: ชื่อกลุ่ม `SYNTHETIC CENTRAL` เหมือนกัน และ checkbox
ทั้ง 10 รายการ (DASHBOARD / MASTER ADMIN / ORDER / REPORT TRACKING / REPORT SUMMARY /
UPLOAD STATUS / UPLOAD PRICE / UPLOAD NEW REQUEST / USER ADMIN / WEBSITE ADMIN)
ติ๊กครบเหมือนกันทั้งสองฝั่ง

ต่างที่ CI4 มีปุ่ม **Update** ปุ่มเดียว ไม่มี **Reset** (CI3 มี Submit + Reset)
และ CI4 render ฟอร์มแก้ไขอยู่ในหน้าเดียวกับ listing จึงมี bullet list 4 รายการต่อท้าย
ทั้งสองข้อไม่ทำให้ข้อมูลหาย จึงให้ desktop = MINOR

หน้านี้ขึ้นเป็น MAJOR เพราะ mobile ตกตาม G1 อย่างเดียว

### background-listing — MAJOR

ไฟล์อ้างอิง: `background-listing__ci3__1440x900.png` เทียบ `background-listing__ci4__1440x900.png`

CI3: การ์ด "BACKGROUND WEB LIST" + ตาราง 6 คอลัมน์ (Id | Track | Tracks Tatus |
Contact | Status | Actions) 1 แถว: Id `97001`, ช่องรูป 3 ช่อง, Status = `Publishing`,
ปุ่มดินสอแก้ไข

CI4: หัวข้อ "Website backgrounds" แล้วเป็น **ฟอร์มสร้างใหม่ทันที** (dropdown Status
ค่า Publishing + ช่องเลือกไฟล์ 12 ช่อง + ปุ่ม Create) ปิดท้ายด้วย bullet list
ลิงก์เดียว `Background 97001`

สิ่งที่หายฝั่ง CI4: ตารางทั้งตาราง คอลัมน์ Status ที่แสดงค่าปัจจุบันของแถว
ช่องรูป preview และปุ่ม action ผู้ใช้เห็นแค่ลิงก์ชื่อ Background 97001 ไม่รู้ว่าแถวนี้
สถานะอะไรจนกว่าจะกดเข้าไป

หมายเหตุฝั่ง CI3: ช่องรูปทั้ง 3 ช่องในภาพ CI3 ขึ้นเป็น alt text `Responsive image`
คือรูปโหลดไม่ขึ้น — เป็นของเสียที่มีอยู่เดิมใน CI3 ไม่ใช่ผลจากการถ่าย และไม่ใช่ประเด็นที่
CI4 ต้อง copy ตาม บันทึกไว้ให้ user รู้เฉย ๆ

### background-edit — MAJOR

ไฟล์อ้างอิง: `background-edit__ci3__1440x900.png` เทียบ `background-edit__ci4__1440x900.png`

CI3: จัดกลุ่มเป็น 3 หัวข้อชัดเจน
- ENTER BACKGROUND: TRACK & TRACE — LAPTOP SIZE 1920PX (EN) + MOBILE SIZE 480PX (EN)
- ENTER BACKGROUND: TRACK STATUS — คู่เดียวกัน
- ENTER BACKGROUND: CONTACT US — คู่เดียวกัน

รวม 6 ช่องอัปโหลด แต่ละช่องมี preview รูปที่ใช้อยู่ใต้ช่อง (ในภาพขึ้นเป็นไอคอนรูปเสีย
เพราะรูปโหลดไม่ขึ้นตามข้อ background-listing) ปิดท้ายด้วย `PUBLISHING STATUS Yes No`
และปุ่ม Submit + Reset — สังเกตว่าหัวหน้าเขียน "background Web **EN**" แปลว่า CI3
น่าจะแยกหน้า TH ไว้อีกหน้า

CI4: ไม่มีการจัดกลุ่ม เป็นรายการแบน 12 ช่องเรียงลงมา ป้ายชื่อเป็นชื่อคอลัมน์ DB ดิบ
(`image_track_laptop`, `image_track_mobile`, `image_trackstatus_laptop`,
`image_trackstatus_mobile`, `image_contact_laptop`, `image_contact_mobile`
แล้วตามด้วยชุด `_th` อีก 6 ช่อง) ด้านบนมี dropdown Status ค่า `Publishing`
ปุ่ม Update ปุ่มเดียว ไม่มี Reset ไม่มี preview รูปเดิมสักช่อง

ประเด็นที่ต้องให้คนตัดสิน: CI4 รวม EN + TH ไว้หน้าเดียว (12 ช่อง) ส่วน CI3 แยกเป็น
หน้า EN (6 ช่อง) — ถ้าการรวมเป็นการออกแบบใหม่ที่ตั้งใจ ต้องยืนยันว่าหน้า TH เดิมของ CI3
ถูกกลืนมาที่นี่ครบและไม่มีหน้ากำพร้าเหลือ จึงเสนอ NEED_USER
ส่วนอีก 2 ข้อควรแก้ไม่ว่าตัดสินยังไง: ป้ายชื่อควรเป็นข้อความอ่านออก ไม่ใช่ชื่อคอลัมน์ดิบ
และควรมี preview รูปที่ใช้อยู่ ไม่งั้นผู้ดูแลไม่รู้ว่าตอนนี้รูปไหนถูกใช้อยู่

### users-listing — MAJOR

ไฟล์อ้างอิง: `users-listing__ci3__1440x900.png` เทียบ `users-listing__ci4__1440x900.png`

CI3: แบนเนอร์ "User Management Add, Edit, Delete" + ปุ่ม **Add New** + การ์ด "USERS LIST"
+ ช่อง Search + ตาราง 6 คอลัมน์ (No | Name | Email | Mobile | Role | Actions) 3 แถว
แต่ละแถวมีปุ่มแก้ไข (ฟ้า) และปุ่มลบ (แดง)

CI4: หัวข้อ "Users" + ช่อง Search + ปุ่ม Search แล้วต่อด้วย **ฟอร์มสร้าง user ใหม่**
(username / name / email / mobile / group_id / role_id / branch_id / Password /
Confirm password + ปุ่ม Create) ปิดท้ายด้วย bullet list 3 รายการ รูปแบบ
`ชื่อ — อีเมล`

สิ่งที่หายฝั่ง CI4: ตารางทั้งตาราง คอลัมน์ **Mobile** คอลัมน์ **Role**
(ทำให้ดูไม่ออกว่าใครเป็น admin ใครเป็น operator จากหน้า listing) และ **ปุ่มลบ**
ทั้งหมด — ฟังก์ชันลบผู้ใช้ไม่มีทางเข้าถึงจากหน้านี้เลย

จำนวนผู้ใช้ที่แสดง 3 คนเท่ากันทั้งสองระบบ (SYNTHETIC ADMIN / SYNTHETIC OPERATOR A /
SYNTHETIC OPERATOR B) และค่า email ตรงกันทุกแถว — ส่วนนี้ parity ผ่าน

### users-edit — MAJOR

ไฟล์อ้างอิง: `users-edit__ci3__1440x900.png` เทียบ `users-edit__ci4__1440x900.png`

CI3 (userId 9002): ฟอร์ม 2 คอลัมน์ในการ์ด "ENTER USER DETAILS"
- USER GROUP: dropdown ค่า `SYNTHETIC BRANCH`
- BRANCH TYPE: dropdown ค่า `SYNTHETIC RETAIL`
- BRANCH: dropdown ค่า `SYNTHETIC BRANCH A`
- FULL NAME `SYNTHETIC OPERATOR A` / EMAIL ADDRESS `wp00c-a@example.invalid`
- PASSWORD / CONFIRM PASSWORD (ว่าง มี placeholder)
- MOBILE NUMBER `0000000000` / ROLE: dropdown ค่า `SYNTHETIC OPERATOR`
- ปุ่ม Submit + Reset

CI4 (userId 9002): ช่องกรอกเรียงลงมาคอลัมน์เดียว
- username `wp00c-a` (CI3 ไม่มีฟิลด์นี้)
- name `SYNTHETIC OPERATOR A` / email `wp00c-a@example.invalid` / mobile `0000000000`
- group_id `4` / role_id `2` / branch_id `1` — เป็นช่องกรอกข้อความที่ใส่เลข id ดิบ
- Password / Confirm password
- ปุ่ม Update (ไม่มี Reset) แล้วต่อด้วย bullet list ผู้ใช้ 3 คน

สามประเด็นเรียงตามความสำคัญ:
1. **ฟิลด์ BRANCH TYPE หายทั้งฟิลด์** — CI3 มี CI4 ไม่มี ถ้าค่านี้ถูกใช้จริงในระบบ
   แปลว่าแก้ไม่ได้จากหน้านี้
2. **dropdown กลายเป็นช่องกรอกเลข id ดิบ** — CI3 ให้เลือกจากรายการชื่อจริง
   CI4 ต้องพิมพ์เลขเอง (group_id 4 / role_id 2 / branch_id 1) เสี่ยงพิมพ์ผิดเป็น id
   ที่ไม่มีอยู่จริงหรือ id ของสาขาอื่น และผู้ดูแลต้องจำเองว่าเลขไหนคือกลุ่มไหน
3. ปุ่ม Reset หายไป (ข้อเดียวกับหน้า edit อื่น ๆ ในชุดนี้)

ค่าทุกช่องที่มีทั้งสองฝั่งตรงกัน (ชื่อ อีเมล เบอร์) และเลข id ที่ CI4 แสดง
สอดคล้องกับชื่อที่ CI3 เลือกไว้ จึงไม่มีปัญหาเรื่องความถูกต้องของข้อมูล
ปัญหาอยู่ที่รูปแบบการป้อนและฟิลด์ที่ขาด

## สิ่งที่ตรวจแล้วว่าไม่ใช่ปัญหา (บันทึกกันตรวจซ้ำ)

- **ความกว้าง/ความสูงของภาพสองฝั่งไม่เท่ากัน** — CI3 1425px หรือ 375px ตาม viewport
  สูง 1627-1680px คงที่ (sidebar ยาวดันความสูงขั้นต่ำ) ส่วน CI4 3313px สูงตามเนื้อหา
  829-1303px เป็นผลของ shell คนละแบบ + G1 ไม่ใช่เนื้อหาหาย
- **จำนวนแถว login history ไม่เท่ากัน** — ธรรมชาติของ tbl_last_login ตามที่ manifest เตือน
  ไม่นับเป็น parity bug (จำนวนแถวต่อหน้า 5 แถวเท่ากัน ซึ่งเป็นสิ่งที่เทียบได้จริง)
- **CI4 มี debug toolbar ของ CodeIgniter** (ไอคอนไฟส้มกลางขอบขวาของภาพ CI4 บางใบ)
  เป็นผลของ ENVIRONMENT=development ไม่ใช่ UI
- **หน้า CI4 ของ menu-edit / background-edit / users-edit มีขนาดไฟล์และ dimension
  เท่ากับหน้า listing คู่ของมัน** — ตรวจ md5 แล้วต่างกันจริงทุกคู่ ไม่ใช่ภาพซ้ำ
  (เป็นเพราะ CI4 render ฟอร์ม edit ในหน้า listing เดียวกัน ความสูงจึงเท่ากัน)
- **title ของหน้าไม่ตรงกันสองระบบ** — CI3 ใช้ title ค้างจาก template เดิม เช่นหน้าแก้เมนู
  ขึ้นว่า `CodeInsect : Edit User` เป็นของเสียเดิมฝั่ง CI3 ไม่ต้องให้ CI4 ทำตาม
