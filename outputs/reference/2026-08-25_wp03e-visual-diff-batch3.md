# diff-batch3 — เทียบภาพ CI3 vs CI4 กลุ่ม master data (20 หน้า)

ที่มา: `evidence/wp03e-visual/manifest-batch3.json` + ภาพจริง 80 ไฟล์ (20 หน้า x 2 ระบบ x 2 viewport)
เปิดดูครบทั้ง 80 ไฟล์ด้วย Read tool ไม่มีไฟล์ไหนดูไม่ได้

## สรุปผล

| verdict | จำนวนหน้า |
|---|---|
| MATCH | 0 |
| MINOR | 0 |
| MAJOR | 20 |
| BEHAVIOR | 1 (master-statustype-edit — ซ้อนกับ MAJOR ของหน้าเดียวกัน) |

ทั้ง 20 หน้าเป็น MAJOR ด้วยเหตุผลร่วมชุดเดียวกัน ไม่ใช่ปัญหาเฉพาะหน้า —
CI4 ยังไม่มี layout/theme ของระบบ admin เลย เป็นหน้า HTML เปล่าที่ผูกกับตาราง DB ตรง ๆ

## ก่อนอ่านตาราง: ต้องรู้ 3 ข้อ

1. **ความกว้างภาพสองฝั่งไม่เท่ากันโดยธรรมชาติ** — ภาพ CI4 ทุกใบกว้าง 3313px เพราะ nav ล้นแนวนอน
   (ตรงตาม batch_note ข้อ 4) ผมครอปสำเนาไว้ที่ scratchpad เหลือซ้าย 1500px เพื่ออ่านตัวอักษรได้
   ยืนยันแล้วจากการเปิดภาพเต็มก่อนครอปว่าเนื้อหาหน้าทั้งหมดอยู่ในช่วง 0-1450px
   ส่วน 1450-3313px เป็นแถบ nav ที่ล้นออกอย่างเดียว ไม่มี element อื่น ไม่มีอะไรถูกครอปทิ้ง
2. **debug toolbar ของ CI4** (มุมขวาล่าง ไอคอนสีส้ม) เป็นความต่างของ harness ไม่นับเป็น diff
3. **ฟอร์ม create inline ในหน้า listing ของ CI4** เป็นการออกแบบตามใบงาน ไม่นับเป็น layout เพี้ยน
   แต่ **หน้า edit ของ CI4 ที่กลายเป็นหน้า listing เดิม** เป็นคนละเรื่องกัน — นับเป็น diff (ดู S6)

## ความต่างร่วม (พบทั้ง 20 หน้า)

| รหัส | ความต่าง |
|---|---|
| S1 | CI4 ไม่มี theme admin — ไม่มี sidebar เมนู (~35 รายการ: MASTER ADMIN / ORDER / REPORT / UPLOAD / USER ADMIN / WEBSITE ADMIN), ไม่มีแถบ header น้ำเงิน + ชื่อหน้าแบบ "Branch Management / Add, Edit, Delete", ไม่มี card panel ครอบตาราง, ไม่มี footer call center 02-761-9999 |
| S2 | ปุ่มหาย: **Back** (มุมซ้ายบน) และ **Add New** (มุมขวาบน) ไม่มีใน CI4; แถบ user (โลโก้ Samsonite, ไอคอน history, ไอคอนธนาคาร, ชื่อ SYNTHETIC ADMIN, Sign out) หายทั้งแถบ |
| S3 | label ฟอร์มและหัวตารางของ CI4 เป็นชื่อคอลัมน์ DB ดิบ (`branch_user_name`, `type_details`, `provider_datail`) แทนชื่ออ่านได้ของ CI3 (Branch User, Products Type Details, Provider Details) |
| S4 | ปุ่ม action ในตาราง: CI3 = ไอคอนดินสอ (ฟ้า) + ถังขยะ (แดง); CI4 = ลิงก์ข้อความ "Edit" + ปุ่มสีน้ำเงิน "Delete" |
| S5 | mobile 390x844: CI4 ไม่มี responsive layout เลย เนื้อหากว้าง ~1130px ล้นออกนอกจอ ต้อง scroll แนวนอน; CI3 ย่อเป็นคอลัมน์เดียวจริง (แม้ CI3 เองจะมีปัญหาตารางล้นใน card อยู่บ้าง) |
| S6 | หน้า edit ของ CI4 = หน้า listing เดิมที่เติมค่าลงฟอร์ม create แล้วเปลี่ยนปุ่มเป็น "Update" และยังแสดงตาราง listing ต่อท้าย; CI3 เป็นหน้าฟอร์มแยกจริงมี "ENTER ... DETAILS" + ปุ่ม Submit และ Reset ไม่มีตาราง (ปุ่ม Reset ไม่มีใน CI4 ทุกหน้า) |

## ตารางผลต่อหน้า

| page-id | desktop | mobile | verdict | สิ่งที่ต่าง | ข้อเสนอ |
|---|---|---|---|---|---|
| master-branch-listing | MAJOR | MAJOR | MAJOR | S1-S5; CI3 แสดง Branch Type เป็นชื่อ (SYNTHETIC RETAIL/SERVICE) แต่ CI4 แสดง `branch_type` เป็นเลข 1/2; CI4 มีคอลัมน์เกิน 2 ตัว (`branch_details`, `book_order`); แถวข้อมูล id 1-2 ตรงกัน | NEED_USER |
| master-branch-edit | MAJOR | MAJOR | MAJOR | S1-S6; CI3 ฟอร์ม 6 ฟิลด์ 2 คอลัมน์ (DETAIL เป็น textarea), CI4 7 ฟิลด์ 1 คอลัมน์ + มี `book_order` เกิน และ `branch_details` เป็น input บรรทัดเดียว | NEED_USER |
| master-branchtype-listing | MAJOR | MAJOR | MAJOR | S1-S5; รูป branch type แตก **ทั้งสองระบบ** ไม่ใช่ CI4 ฝ่ายเดียว; CI4 มีช่องอัปโหลด "PNG image" ในหน้า listing; CI3 mobile ตัดคอลัมน์ Image/Actions หายเพราะตารางล้นใน card | FIX_CI4 |
| master-branchtype-edit | MAJOR | MAJOR | MAJOR | S1-S6; CI3 = BRANCH TYPE NAME + IMAGE (มี preview รูปที่แตก); CI4 preview รูปแตกเหมือนกันแต่วางชนกับปุ่ม Update จนซ้อนทับ | FIX_CI4 |
| master-statustype-listing | MAJOR | MAJOR | MAJOR | S1-S5; แถว 9001/9002 และค่า success 1/0 ตรงกันสองฐาน; หัวคอลัมน์ CI3 = Id/Description Th/Description En/Config Status, CI4 = `status_id`/`description_th`/`description_en`/`success` | FIX_CI4 |
| master-statustype-edit | MAJOR | MAJOR | MAJOR | S1-S6; ฟิลด์ 3 ตัวตรงกัน (CI3 label "CONFIG STATUS(0/1)" อธิบายค่าที่รับได้ แต่ CI4 เขียนแค่ `success`); **BEHAVIOR แยกต่างหาก**: id ที่ไม่มีจริง CI3 ตอบ 200 render ฟอร์มเปล่า CI4 ตอบ 404 (ไม่ปรากฏในภาพชุดนี้ ยกมาจาก manifest) | NEED_USER |
| master-producttype-listing | MAJOR | MAJOR | MAJOR | S1-S5; ข้อมูล 2 แถวตรงกัน (SYNTHETIC TYPE A/B) ต่างเฉพาะความต่างร่วม | FIX_CI4 |
| master-producttype-edit | MAJOR | MAJOR | MAJOR | S1-S6; ฟิลด์เดียวตรงกัน CI3 "PRODUCTS TYPE DETAILS" vs CI4 `type_details` | FIX_CI4 |
| master-book-listing | MAJOR | MAJOR | MAJOR | S1-S5; CI3 แสดง Status เป็นคำว่า "Publishing" แต่ CI4 แสดง `status` เป็นเลข 1; CI4 มีคอลัมน์ `bunber_limit` (999) ที่ CI3 ไม่แสดง; CI3 แสดง Branch Name เป็นชื่อสาขา แต่ CI4 แสดง `branch_id` เป็นเลข | NEED_USER |
| master-book-edit | MAJOR | MAJOR | MAJOR | S1-S6; CI3 มี PUBLISHING STATUS เป็น radio YES/NO แต่ CI4 เป็นช่องข้อความ `status` ใส่ "1"; CI4 มีฟิลด์ `bunber_limit` เกินมา; dropdown branch แสดงชื่อสาขาตรงกันทั้งคู่ | NEED_USER |
| master-brand-listing | MAJOR | MAJOR | MAJOR | S1-S5; ข้อมูล 2 แถวตรงกัน; CI3 mobile ตัดคอลัมน์ Actions หายบางส่วนเพราะตารางล้นใน card | FIX_CI4 |
| master-brand-edit | MAJOR | MAJOR | MAJOR | S1-S6; ฟิลด์เดียวตรงกัน CI3 "BRAND NAME" vs CI4 `brand_details` | FIX_CI4 |
| master-condition-listing | MAJOR | MAJOR | MAJOR | S1-S5; ข้อมูล 2 แถวตรงกัน; CI3 mobile ตัดคอลัมน์ Actions หาย | FIX_CI4 |
| master-condition-edit | MAJOR | MAJOR | MAJOR | S1-S6; ฟิลด์เดียวตรงกัน CI3 "CONDITION NAME" vs CI4 `condition_details` | FIX_CI4 |
| master-estimateprice-listing | MAJOR | MAJOR | MAJOR | S1-S5; ข้อมูล 2 แถวตรงกัน; CI3 mobile ตัดคอลัมน์ Actions หาย | FIX_CI4 |
| master-estimateprice-edit | MAJOR | MAJOR | MAJOR | S1-S6; ฟิลด์เดียวตรงกัน CI3 "ESTIMATEPRICE NAME" vs CI4 `estimateprice_details` | FIX_CI4 |
| master-fixed-listing | MAJOR | MAJOR | MAJOR | S1-S5; ข้อมูล 2 แถวตรงกัน; หน้านี้ CI3 mobile แสดงครบทุกคอลัมน์รวม Actions | FIX_CI4 |
| master-fixed-edit | MAJOR | MAJOR | MAJOR | S1-S6; ฟิลด์เดียวตรงกัน CI3 "FIXED NAME" vs CI4 `fixed_details` | FIX_CI4 |
| master-provider-listing | MAJOR | MAJOR | MAJOR | S1-S5; ข้อมูล 2 แถวและค่าทุกช่องตรงกัน; CI3 mobile ตัดคอลัมน์ Provider Tel/Details/Actions หาย | FIX_CI4 |
| master-provider-edit | MAJOR | MAJOR | MAJOR | S1-S6; 3 ฟิลด์ตรงกัน แต่ CI3 DETAIL เป็น textarea ส่วน CI4 `provider_datail` เป็น input บรรทัดเดียว (สะกดตาม schema เดิม) | FIX_CI4 |

## รายละเอียดหน้าที่ verdict เป็น MAJOR หรือ BEHAVIOR

ทั้ง 20 หน้าเป็น MAJOR จากชุดเหตุผลเดียวกัน จึงเขียนรวมเป็นประเด็นแทนการซ้ำ 20 ครั้ง
แล้วแยกเฉพาะหน้าที่มีประเด็นเพิ่มไว้ท้ายหัวข้อ

### M1. CI4 ไม่มี layout ของระบบ admin ทั้งชุด (กระทบทั้ง 20 หน้า)

ภาพอ้างอิงที่ชัดที่สุด: `master-branch-listing__ci3__1440x900.png` เทียบ `master-branch-listing__ci4__1440x900.png`

CI3 มีโครง 4 ส่วน: แถบบนขาว (โลโก้ Samsonite, ปุ่ม Back, ไอคอน history/ธนาคาร, ชื่อผู้ใช้, Sign out),
sidebar ดำซ้ายมือความสูงเต็มหน้าที่มีเมนูราว 35 รายการแบ่ง 6 กลุ่ม (DASHBOARD, MASTER ADMIN, ORDER,
REPORT TRACKING, REPORT SUMMARY, UPLOAD *, USER ADMIN, WEBSITE ADMIN) และ **ไฮไลต์รายการของหน้าปัจจุบัน**,
แถบ header น้ำเงินที่มีชื่อหน้า + คำบรรยาย + ปุ่ม Add New, และ card ขาวครอบตารางที่มีหัวข้อ
(เช่น "BRANCH LIST") กับช่อง Search มุมขวา สุดท้ายมี footer สีฟ้าเบอร์ call center

CI4 มีแค่ nav แถบเดียวด้านบน (ที่ล้นออกนอกจอ) + `<h1>Master data: branch</h1>` + ฟอร์ม + ตาราง
ไม่มีอีก 4 ส่วนที่เหลือ ผู้ใช้จึงเสียทั้งเมนูนำทาง ปุ่ม Back ปุ่ม Add New และตัวบอกตำแหน่งปัจจุบัน
นี่คือความต่างที่ผู้ใช้เห็นผลจริง ไม่ใช่เรื่องระยะห่าง/ฟอนต์ จึงเป็น MAJOR ไม่ใช่ MINOR

### M2. mobile 390 ของ CI4 ไม่ใช่ layout mobile (กระทบทั้ง 20 หน้า)

ภาพอ้างอิง: `master-branch-listing__ci4__390x844.png` เทียบ `master-branch-listing__ci3__390x844.png`

ภาพ CI4 ที่ viewport 390 หน้าตาเหมือนภาพ desktop เกือบทุกอย่าง ต่างแค่ nav ถูกบีบ —
เนื้อหาหลักยังกว้าง ~1130px ทั้งที่จอกว้าง 390px แปลว่ามี scroll แนวนอนค้างอยู่จริงบนมือถือ
ส่วน CI3 ย่อเป็นคอลัมน์เดียวจริง card หดตามจอ

ประเด็นนี้ manifest ระบุไว้แล้วว่าเป็น responsive issue ฝั่ง CI4 ที่ควรยกให้ user ตัดสินแยก
ผมให้คะแนน mobile ของทุกหน้าเป็น MAJOR เท่ากันหมดตามหลักเดียวกัน ไม่แยกรายหน้า

หมายเหตุความเป็นธรรม: CI3 เองก็มีปัญหา mobile คนละแบบ — ตารางใน card ล้นแล้วคอลัมน์ท้าย ๆ
(Image, Actions, Provider Tel) ถูกตัดหายไปเลย เห็นได้ที่ `master-branchtype-listing__ci3__390x844.png`,
`master-provider-listing__ci3__390x844.png`, `master-brand-listing__ci3__390x844.png`
มีเพียง `master-fixed-listing__ci3__390x844.png` ที่แสดงครบทุกคอลัมน์

### M3. หน้า edit ของ CI4 ไม่ใช่หน้า edit (กระทบ 10 หน้า edit)

ภาพอ้างอิง: `master-branch-edit__ci4__1440x900.png` เทียบ `master-branch-edit__ci3__1440x900.png`

CI3 `/editBranchOld/1` เป็นหน้าฟอร์มแยกจริง: หัวข้อ "ENTER BRANCH DETAILS", ฟอร์ม 2 คอลัมน์,
ปุ่ม Submit + Reset และไม่มีตาราง listing อยู่ในหน้า

CI4 `/master/branch/1` render หน้า listing เดิมทั้งหน้า แล้วเติมค่าของแถวที่เลือกลงในฟอร์ม create
เปลี่ยนปุ่มจาก "Create" เป็น "Update" ตารางรายการทั้งหมดยังอยู่ข้างล่างเหมือนเดิม
และไม่มีปุ่ม Reset

ผลกับผู้ใช้: (ก) ไม่มีสัญญาณทางสายตาว่ากำลังแก้แถวไหนอยู่ นอกจากค่าที่ถูกเติมในฟอร์ม
(ข) ปุ่ม Reset ที่ CI3 มีหายไป (ค) หน้ายาวกว่าเดิมโดยไม่จำเป็น
ข้อนี้ต่างจากเรื่อง "create form inline" ที่ manifest บอกว่าตั้งใจ — ตรงนั้นคือหน้า listing
ส่วนนี่คือหน้า edit ที่กลายเป็น listing

### M4. label เป็นชื่อคอลัมน์ DB ดิบ (กระทบทั้ง 20 หน้า)

ตัวอย่างชัดที่ `master-provider-edit__ci4__1440x900.png`: label เขียนว่า `provider_datail`
(สะกดตาม schema เดิมซึ่งพิมพ์ผิดจาก detail) ส่วน CI3 เขียนว่า "DETAIL"
แบบเดียวกันทุกหน้า: `type_details` vs "PRODUCTS TYPE DETAILS", `brand_details` vs "BRAND NAME",
`fixed_details` vs "FIXED NAME", `success` vs "CONFIG STATUS(0/1)"

กรณี `success` เสียข้อมูลมากที่สุด — CI3 บอกในวงเล็บว่ารับค่า 0/1 เท่านั้น CI4 ไม่บอก

### M5. ค่า foreign key แสดงเป็นเลขดิบแทนชื่อ (2 หน้า)

- `master-branch-listing`: CI3 คอลัมน์ Branch Type แสดง "SYNTHETIC RETAIL"/"SYNTHETIC SERVICE"
  แต่ CI4 คอลัมน์ `branch_type` แสดง 1/2
- `master-book-listing`: CI3 คอลัมน์ Branch Name แสดง "SYNTHETIC BRANCH A"/"B" และ Status
  แสดงคำว่า "Publishing" แต่ CI4 แสดง `branch_id` = 1/2 และ `status` = 1

ผู้ใช้ที่ไม่รู้ mapping จะอ่านหน้าไม่ออก จึงเป็น MAJOR ไม่ใช่ MINOR
(ในหน้า edit ทั้งสองระบบใช้ dropdown ที่แสดงชื่อเหมือนกัน ปัญหาอยู่ที่หน้า listing เท่านั้น)

### M6. ฟิลด์/คอลัมน์ที่มีใน CI4 แต่ไม่มีใน CI3 (3 หน้า)

- `master-branch-listing` / `master-branch-edit`: CI4 มี `book_order` และ `branch_details`
  ที่ฟอร์มและตารางของ CI3 ไม่เปิดให้เห็น (CI3 edit มี DETAIL ซึ่งตรงกับ branch_details
  แต่ listing ของ CI3 ไม่มีคอลัมน์นี้ ส่วน book_order ไม่มีทั้งสองที่)
- `master-book-listing` / `master-book-edit`: CI4 มี `bunber_limit` (ค่า 999) ที่ CI3 ไม่มีทั้งใน
  ตารางและฟอร์ม

ต้องให้ user ตัดสินว่าเป็น CI4 เปิดเผยฟิลด์เกินความจำเป็น หรือ CI3 ซ่อนฟิลด์ที่ควรแก้ได้
จึงให้ข้อเสนอเป็น NEED_USER สำหรับ 4 หน้านี้

### M7. control ที่เปลี่ยนชนิด (1 หน้า)

`master-book-edit`: CI3 ใช้ radio button "PUBLISHING STATUS: YES / NO"
(`master-book-edit__ci3__1440x900.png`) แต่ CI4 ใช้ช่องข้อความอิสระชื่อ `status` ที่ใส่ค่า "1"
(`master-book-edit__ci4__1440x900.png`) — ผู้ใช้พิมพ์ค่าอะไรก็ได้ ต่างจาก CI3 ที่บังคับ 2 ทางเลือก

แบบเดียวกันในระดับเบากว่า: `master-branch-edit` และ `master-provider-edit`
ที่ CI3 ใช้ textarea (หลายบรรทัด) แต่ CI4 ใช้ input บรรทัดเดียว

### B1. BEHAVIOR — master-statustype-edit: id ที่ไม่มีจริงตอบต่างกัน

ประเด็นนี้ **ไม่ปรากฏในภาพชุดปัจจุบัน** เพราะถ่ายใหม่ด้วย id 9001 ที่มีจริง
ยกมาจาก note ของ manifest เพื่อไม่ให้ตกหล่น:

- CI3 `/editStatustypeOld/1` (id ไม่มีในตาราง `tracking_status`) ตอบ HTTP 200 แล้ว render
  ฟอร์มเปล่า ไม่ guard กรณีหาแถวไม่เจอ
- CI4 `/master/statustype/1` ตอบ HTTP 404 เพราะ `MasterData::edit()` โยน PageNotFoundException
  เมื่อ `MasterDataStore::find()` คืน null

ผมไม่ตรวจสอบซ้ำในรอบนี้เพราะงานนี้ดูภาพนิ่งอย่างเดียวและภาพชุดนี้ไม่มีเคสนั้น
ต้องให้ user ตัดสินว่ายึดพฤติกรรมไหน — โดยส่วนตัวพฤติกรรม CI4 (404) ถูกกว่า
แต่ถ้ามีโค้ดเดิมที่พึ่งการได้ 200 อยู่ ต้องรู้ก่อนตัดสิน

### N1. เรื่องที่ manifest ระบุไว้แต่ผมพบว่าไม่ตรงกับภาพ

manifest `batch_note` และ note ของ `master-branchtype-listing` ระบุว่า
"ทำให้ตำแหน่งรูปในภาพ CI4 เป็น broken image ส่วน CI3 ไม่มี"

ภาพจริงไม่ตรงกับข้อนี้ — **CI3 ก็รูปแตกเหมือนกัน**:
- `master-branchtype-listing__ci3__1440x900.png` คอลัมน์ Image แสดงไอคอนรูปแตกพร้อม alt text
  "Responsive image" ทั้ง 2 แถว
- `master-branchtype-edit__ci3__1440x900.png` ใต้ปุ่ม Choose File มีไอคอนรูปแตกเช่นกัน
- ฝั่ง CI4 แตกเหมือนกันแต่ alt text เขียนว่า "Branch type image"

แปลว่ารูป branch type หายในฐาน fixture ทั้งสองระบบ ไม่ใช่ regression ที่ CI4 ทำพัง
ผมจึงไม่นับข้อนี้เป็น diff ที่ CI4 ต้องแก้ (ต่างที่เหลือคือ alt text คนละข้อความ ซึ่งเป็น MINOR
และถูกกลบด้วย MAJOR อื่นของหน้าเดียวกันอยู่แล้ว)

## ข้อสังเกตสำหรับคนตัดสินใจต่อ

ความต่าง S1-S4 และ M1/M3/M4 ไม่ใช่ 20 บั๊กแยกกัน แต่เป็นงานเดียว: CI4 ยังไม่ได้ port
layout/theme ของ CI3 มาและยังใช้ view scaffold ที่ generate จาก schema ตรง ๆ
ถ้าแก้ที่ layout ร่วมกับ view template ของ master data ทีเดียว จะปิดได้ทั้ง 20 หน้าพร้อมกัน

ส่วนที่ต้องตัดสินแยกจริง ๆ มีแค่ 3 กลุ่ม: responsive ของ CI4 (M2),
ฟิลด์ที่มีเกิน/แสดงเป็นเลขดิบ (M5, M6, M7 — 4 หน้า: branch x2, book x2),
และพฤติกรรม 404 vs 200 (B1 — 1 หน้า)
