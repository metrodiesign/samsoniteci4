# ผลเทียบภาพ visual parity — batch2 (orders + listings)

ตัดสินจากภาพจริงใน `evidence/wp03e-visual/` ทั้ง desktop `1440x900` และ mobile `390x844`
บริบทอ่านจาก `manifest-batch2.json` (ถ่ายเมื่อ 2026-08-25T03:47:40Z, CI3 pin `ee1c95e`, CI4 `2589881`)

ดูภาพครบ 46/46 ไฟล์ตามที่ manifest ระบุ (rating-form มีเฉพาะฝั่ง CI4 ตาม decision ของ user)

## สรุปจำนวน

| verdict | จำนวนหน้า |
|---|---|
| MATCH | 0 |
| MINOR | 1 |
| MAJOR | 9 |
| BEHAVIOR | 2 |

## ตารางผลต่อหน้า

| page-id | desktop | mobile | verdict | สิ่งที่ต่าง | ข้อเสนอ |
|---|---|---|---|---|---|
| rating-form | BEHAVIOR | BEHAVIOR | BEHAVIOR | ไม่มีภาพ CI3 เทียบ (CI3 redirect ทิ้งทันที) — CI4 render ฟอร์มจริงแต่เป็น HTML ดิบไม่มี CSS ของฟอร์ม label เป็น `Question 1..8` และ desktop มี horizontal overflow เกิน 1440px | NEED_USER |
| reset-password | BEHAVIOR | BEHAVIOR | BEHAVIOR | CI3 pre-fill email (readonly) + pre-fill รหัสผ่านสุ่มทั้งสองช่อง; CI4 ทั้ง 3 ช่องว่าง แก้ไขได้ + คู่นี้ไม่ paired-real (CI4 ออก token ไม่ได้) | NEED_USER |
| orders-new | MAJOR | MAJOR | MAJOR | CI4 ขาดฟิลด์ REQUEST DATE, BRANCH TYPE, BRANCH SHORT, BOOK SHORT (ชื่อจริง), label หลายตัวเป็นชื่อคอลัมน์ดิบ (`number_id`, `order_id`, `book_id`, `customer_name`, ...), ไม่มี sidebar, ไม่มีปุ่ม Reset, mobile layout พัง (หน้าล้นออกนอก viewport) | FIX_CI4 |
| orders-edit | MAJOR | MAJOR | MAJOR | CI4 ขาด REQUEST ID, TRACK ID, BOOK SHORT, NUMBER ID, REQUEST DATE, BRANCH TYPE และไม่แสดงรูปที่แนบไว้เดิม; label ดิบเหมือน orders-new; mobile layout พัง | FIX_CI4 |
| orders-print | MINOR | MINOR | MINOR | หัวเรื่อง "ใบรับซ่อม" CI3 จัดกลาง ตัวใหญ่ / CI4 ชิดซ้าย ตัวเล็กกว่า, ตำแหน่งโลโก้ต่างกัน, ฟอนต์ serif vs sans, PURCHASED DATE CI3 แสดง `00/00/0000` CI4 เว้นว่าง; ข้อมูลทุกฟิลด์ตรงกัน | ACCEPT |
| order-listing-status1 | MAJOR | MAJOR | MAJOR | CI4 ไม่มีปุ่ม Add New, ไม่มีฟิลเตอร์ From Date/To Date, ไม่มีปุ่มลบ; เพิ่มคอลัมน์ checkbox + Status Update และบล็อก Provider/Send; คอลัมน์ Id แสดง `1` (CI3 `91001`), OrderID `WP00C-ORDER-001` (CI3 `WPC/001`); mobile ล้นแนวนอนหนัก | FIX_CI4 |
| order-listing-status2 | MAJOR | MAJOR | MAJOR | CI4 ไม่มีฟิลเตอร์ Date + Detail (เหลือ Search เดียว); dropdown Status ค่าตั้งต้นเป็น `3` (ตัวเลขดิบ) ขณะที่ CI3 เป็น `Select status`; CI4 เพิ่มลิงก์ Print/Edit ต่อแถว; จำนวนแถวตรงกัน 2 แถว ข้อมูลตรงกัน; mobile ล้นแนวนอน | FIX_CI4 |
| order-listing-status3 | MAJOR | MAJOR | MAJOR | รูปแบบเดียวกับ status2 — ไม่มีฟิลเตอร์ Date/Detail, dropdown Status ตั้งต้น `4` (ตัวเลขดิบ) แทน `Select Status`; 1 แถวตรงกัน; mobile ล้นแนวนอน | FIX_CI4 |
| order-listing-status4 | MAJOR | MAJOR | MAJOR | รูปแบบเดียวกับ status2 — dropdown Status ตั้งต้น `5` (ตัวเลขดิบ), ไม่มีฟิลเตอร์ Date/Detail; 1 แถวตรงกัน; mobile ล้นแนวนอน | FIX_CI4 |
| order-listing-status5 | MAJOR | MAJOR | MAJOR | CI4 เพิ่มบล็อก Status (ตั้งต้น `7`) + Send ที่ CI3 ไม่มีในหน้านี้, เพิ่มลิงก์ Print/Edit, ไม่มีฟิลเตอร์ Detail/Date; ปุ่ม ประเมิน มีทั้งคู่; mobile ล้นแนวนอน | FIX_CI4 |
| order-listing-status7 | MAJOR | MAJOR | MAJOR | CI4 ไม่มีฟิลเตอร์ Detail + Date, เพิ่มคอลัมน์ Status Update + Actions (Print/Edit) ที่ CI3 ไม่มี; ข้อมูล 1 แถวตรงกัน; mobile ล้นแนวนอน | FIX_CI4 |
| report-tracking-listing | MAJOR | MAJOR | MAJOR | จำนวนแถวต่างกันจริง: CI3 `Showing 0 to 0 of 0 entries` / CI4 9 แถว; ค่าตั้งต้นของฟิลเตอร์ต่างกัน (CI3 From/To = 25/08/2026, Status = None selected; CI4 Status IDs = `2,3`, วันที่ว่าง); CI4 ไม่มี DataTables (Show entries, sort, pagination) | NEED_USER |

## รายละเอียดหน้าที่ verdict เป็น MAJOR หรือ BEHAVIOR

### rating-form (BEHAVIOR)

ภาพ: `rating-form__ci4__1440x900.png`, `rating-form__ci4__390x844.png`

ไม่มีภาพ CI3 เทียบเพราะ CI3 `Rating::index()` redirect ทิ้งตั้งแต่บรรทัดแรก จึงตัดสินเป็น BEHAVIOR
ตามใบงาน สิ่งที่เห็นจากภาพ CI4 ฝั่งเดียว:

- header/footer ของ storefront (โลโก้ Samsonite, TRACK & TRACE / CONTACT US / SHOPPING, แถบเบอร์โทร) render ปกติ
- ตัวฟอร์มไม่มี CSS เลย — เป็น HTML ดิบ label เรียงว่า `Question 1` ถึง `Question 8` ตามด้วย `Comment`
  และปุ่ม `Submit rating` แบบปุ่ม default ของ browser ไม่มีวิดเจ็ตให้ดาว/ตัวเลือกคะแนน
- desktop: เนื้อหาล้นเกิน 1440px (ภาพกว้าง 1532px) แถบ header/footer จึงถูกตัดขวา
- Tracking ID `WP00C-TRACK-005` แสดงถูกต้อง

ต้องให้ user ตัดสินว่า CI4 หน้านี้ควรมีหน้าตาแบบใด เพราะไม่มี baseline ให้เทียบ

### reset-password (BEHAVIOR)

ภาพ: `reset-password__ci3__1440x900.png`, `reset-password__ci4__1440x900.png`,
`reset-password__ci3__390x844.png`, `reset-password__ci4__390x844.png`

ต่างที่พฤติกรรม ไม่ใช่แค่ภาพ:

- CI3 เติมค่า email `wp00c-admin@example.invalid` ให้อัตโนมัติในช่องที่เป็น readonly (พื้นเทา) และ
  pre-fill ทั้งช่อง Password และ Confirm ด้วยรหัสสุ่ม (เห็นเป็นจุด 6-8 ตัว)
- CI4 ทั้ง 3 ช่องว่างเปล่าและแก้ไขได้ทั้งหมด รวมถึงช่อง Email
- โครงหน้าต่างกันด้วย: CI3 เป็นหน้าเปล่ามีหัว "Tracking / Admin System" ไม่มี navbar;
  CI4 มี navbar ดำ "Samsonite Tracking" และวางฟอร์มในการ์ดขาวมีหัวข้อ + คำอธิบาย
- ข้อควรระวังจาก manifest: คู่นี้ไม่ใช่ paired-real — ฝั่ง CI4 ออก token จริงไม่ได้ในเครื่องนี้
  (`reset_service_unavailable` จาก `EncryptionException` เพราะไม่มี encryption.key) ภาพ CI4 จึงเป็น
  render ของ `/reset-password` แบบไม่มี token

ต้องให้ user ตัดสินว่าการ pre-fill email/รหัสสุ่มแบบ CI3 เป็นพฤติกรรมที่ต้องคง parity หรือเป็นของเสียที่ตั้งใจตัดทิ้ง

### orders-new (MAJOR)

ภาพ: `orders-new__ci3__1440x900.png`, `orders-new__ci4__1440x900.png`,
`orders-new__ci3__390x844.png`, `orders-new__ci4__390x844.png`

ฟิลด์ที่ CI3 มีแต่หาไม่เจอใน CI4:

- `REQUEST DATE/วันที่ส่งซ่อม` (CI3 เติมค่า 25/08/2026 ให้อัตโนมัติ)
- `BRANCH TYPE/ประเภทของสาขา`
- `BRANCH SHORT/ตัวย่อสาขา`
- `BOOK SHORT/เล่มที่` (CI4 มีช่อง `book_id` แทน ซึ่งไม่ใช่ label เดียวกัน)

ฟิลด์ที่ CI4 มีเพิ่ม: `order_id`, `Number Waranty/หมายเลขประกัน`

ปัญหา label: CI4 ใช้ชื่อคอลัมน์ดิบเป็น label — `number_id`, `order_id`, `book_id`, `customer_name`,
`customer_tel`, `customer_email`, `note` — ขณะที่ CI3 ใช้ label สองภาษาทั้งหมด
(`CUSTOMER FULLNAME/ชื่อลูกค้า` ฯลฯ) และมีเครื่องหมาย `*` กำกับฟิลด์บังคับ ซึ่ง CI4 ไม่มีเลย

ปุ่ม: CI3 มี `ADD IMAGE`, `Submit`, `Reset`; CI4 มี input `Choose Files` (Repair image up to 5)
และปุ่ม `Create order` อย่างเดียว ไม่มี Reset

โครงหน้า: CI3 มี sidebar เมนูซ้ายเต็มระบบ + หัวแถบน้ำเงิน `NEW REQUEST REPAIR` + ฟอร์ม 2 คอลัมน์;
CI4 ไม่มี sidebar เลย ฟอร์มเป็นคอลัมน์เดียว และ navbar บนสุดล้นออกนอกจอ (เมนูไล่ไปจนเลย 3300px)

Mobile: ภาพ `orders-new__ci4__390x844.png` แสดงว่าหน้าเรนเดอร์ที่ความกว้างจริงราว 3313px
ทั้งที่ viewport 390px — เนื้อหาถูกบีบอยู่ซ้ายราว 230px และมีพื้นที่ว่างที่เหลือทั้งหมด
ถือว่า layout พังที่ mobile ส่วน CI3 responsive ปกติ (ฟอร์มเรียงคอลัมน์เดียวพอดีจอ)

### orders-edit (MAJOR)

ภาพ: `orders-edit__ci3__1440x900.png`, `orders-edit__ci4__1440x900.png`,
`orders-edit__ci3__390x844.png`, `orders-edit__ci4__390x844.png`

ฟิลด์ที่ CI3 แสดงพร้อมค่าจริงแต่ CI4 ไม่มี:

- `REQUEST ID/เลขที่ส่งซ่อม` = 91001
- `TRACK ID/เลขติดตาม` = WP00C-TRACK-001
- `BOOK SHORT/เล่มที่` = WPC
- `NUMBER ID/เลขที` = 001
- `REQUEST DATE/วันที่ส่งซ่อม` = 01/08/2026
- `BRANCH TYPE/ประเภทของสาขา` = SYNTHETIC RETAIL

รูปที่แนบไว้กับ order: CI3 แสดง thumbnail ของรูปเดิมพร้อมปุ่ม `ADD IMAGE`;
CI4 มีเพียง `Choose Files` (Repair image up to 5, replaces current) ไม่แสดงรูปที่มีอยู่

ค่าที่ตรงกันทั้งสองฝั่ง: customer name/tel/email, category, brand, branch, warranty = มี,
condition A ติ๊ก, estimate low ติ๊ก, fix A ติ๊ก, created by = SYNTHETIC OPERATOR A

ปัญหา label ดิบและ layout/mobile พังเหมือน orders-new ทุกประการ
จุดที่ CI4 ทำถูก: ล็อกช่อง `สาขา (แก้ไม่ได้)` เป็น readonly

### order-listing-status1 (MAJOR)

ภาพ: `order-listing-status1__ci3__1440x900.png`, `order-listing-status1__ci4__1440x900.png`,
`order-listing-status1__ci3__390x844.png`, `order-listing-status1__ci4__390x844.png`

จำนวนแถวตรงกัน 1 แถว (WP00C-TRACK-001) และข้อมูลลูกค้า/วันที่/สถานะตรงกัน แต่ควบคุมต่างกันมาก:

- CI3 มีปุ่ม `Add New` มุมขวาบน — CI4 ไม่มี
- CI3 มีฟิลเตอร์ `From Date` + `To Date` + `Detail` (ช่องค้นหา) — CI4 เหลือช่อง `Search` เดียว
- CI3 Actions เป็นปุ่มไอคอน 3 ตัว (แก้ไข / ลบ / พิมพ์) — CI4 เป็นลิงก์ตัวอักษร `Print` `Edit`
  ไม่มีปุ่มลบ
- CI4 เพิ่มคอลัมน์ checkbox (เลือกหลายแถว) + คอลัมน์ `Status Update` และเพิ่มบล็อก
  `Provider` (dropdown Select provider) + ปุ่ม `Send` ใต้ตาราง ซึ่ง CI3 ไม่มี
- ค่าในคอลัมน์ Id ต่างกัน: CI3 = `91001` (request_id จริง) / CI4 = `1` (ลำดับแถว)
- คอลัมน์ Order: CI3 = `WPC/001` / CI4 = `WP00C-ORDER-001`

Mobile: CI3 ตารางล้นแนวนอนบ้าง (เห็นถึงคอลัมน์ OrderID) แต่ shell ของหน้ายังพอดีจอ 390px;
CI4 ทั้งหน้าเรนเดอร์กว้าง ~3313px ทับ viewport ทั้งหมด

### order-listing-status2 / status3 / status4 (MAJOR — รูปแบบเดียวกัน)

ภาพ: `order-listing-status{2,3,4}__ci{3,4}__{1440x900,390x844}.png`

ข้อมูลในตารางตรงกันทุกหน้า (status2 = 2 แถว WP00C-TRACK-009/002, status3 = 1 แถว TRACK-003,
status4 = 1 แถว TRACK-004) ต่างกันที่ควบคุม:

- CI3 มีฟิลเตอร์ `Date` + `Detail` พร้อมปุ่มแว่นขยาย — CI4 เหลือ `Search` + ปุ่ม `Search`
- dropdown เปลี่ยนสถานะ: CI3 ตั้งต้นเป็นข้อความ `Select status` / `Select Status`
  ส่วน CI4 ตั้งต้นเป็นตัวเลขดิบ — `3` ที่ status2, `4` ที่ status3, `5` ที่ status4
  (เป็นทั้ง default value ที่ต่างและ label ที่ไม่ใช่ชื่อสถานะ)
- CI3 หัวคอลัมน์ Actions เขียนว่า `Actions Select ALL Tracking` พร้อม checkbox เลือกทั้งหมด
  วางในคอลัมน์ขวาสุด; CI4 ย้าย checkbox ไปคอลัมน์ซ้ายสุดและเพิ่มลิงก์ `Print` `Edit` ในคอลัมน์ Actions
  ซึ่ง CI3 ไม่มีในสามหน้านี้
- คอลัมน์ Id: CI3 นับ 1, 2 เหมือน CI4 ในสามหน้านี้ (ไม่ต่าง) แต่คอลัมน์ Order ต่างเหมือน status1
  (`WPC/009` เทียบ `WP00C-ORDER-009`)
- โครงหน้า/sidebar/mobile overflow เหมือน status1 ทุกประการ

### order-listing-status5 (MAJOR)

ภาพ: `order-listing-status5__ci3__1440x900.png`, `order-listing-status5__ci4__1440x900.png`,
`order-listing-status5__ci3__390x844.png`, `order-listing-status5__ci4__390x844.png`

- CI3 (COMPLETE FEEDBACK) ไม่มีบล็อกเปลี่ยนสถานะเลย — มีแค่ตาราง + ปุ่ม `ประเมิน` ในคอลัมน์ Actions
  ส่วน CI4 (COMPLETE) เพิ่มบล็อก `Status` ตั้งต้น `7` + ปุ่ม `Send` ใต้ตาราง และเพิ่มลิงก์
  `Print` `Edit` ข้างปุ่ม ประเมิน
- CI3 มีฟิลเตอร์ `Detail:` + `Date:` + ปุ่มแว่นขยาย — CI4 เหลือ `Search`
- คอลัมน์ Id: CI3 = `91005` / CI4 = `1`
- คอลัมน์ Action Status ของ CI3 ถูกตัดที่ขอบตาราง (เห็นแค่ `SYNTHETIC`) เทียบค่ากับ CI4
  (`SYNTHETIC RETURN`) ไม่ได้จากภาพนี้ — ระบุว่าตรวจไม่ได้
- Mobile: CI3 พอดีจอ (ตารางล้นแนวนอนบางส่วน), CI4 ทั้งหน้ากว้าง ~3313px

### order-listing-status7 (MAJOR)

ภาพ: `order-listing-status7__ci3__1440x900.png`, `order-listing-status7__ci4__1440x900.png`,
`order-listing-status7__ci3__390x844.png`, `order-listing-status7__ci4__390x844.png`

- ข้อมูล 1 แถวตรงกัน (WP00C-TRACK-007, request 07/08/2026, completed 08/08/2026)
- CI3 มีฟิลเตอร์ `Detail:` + `Date:` + ปุ่มแว่นขยาย — CI4 เหลือ `Search`
- CI3 ไม่มีคอลัมน์ Actions ที่ใช้งานได้ (คอลัมน์ขวาถูกตัดที่ขอบตาราง เห็นแค่ตัว `A`);
  CI4 เพิ่มคอลัมน์ `Status Update` + `Actions` ที่มีลิงก์ `Print` `Edit`
  ส่วนของ CI3 ที่ถูกตัดจึงเทียบไม่ได้เต็ม — ระบุว่าตรวจไม่ได้ในภาพนี้
- คอลัมน์ Id: CI3 = `91007` / CI4 = `1`
- Mobile: CI4 ทั้งหน้ากว้าง ~3313px ทับ viewport

### report-tracking-listing (MAJOR)

ภาพ: `report-tracking-listing__ci3__1440x900.png`, `report-tracking-listing__ci4__1440x900.png`,
`report-tracking-listing__ci3__390x844.png`, `report-tracking-listing__ci4__390x844.png`

หน้านี้ต่างทั้งจำนวนข้อมูลและกลไกฟิลเตอร์:

- จำนวนแถว: CI3 แสดง `Showing 0 to 0 of 0 entries` (ตารางว่าง) เพราะค่าตั้งต้นของ
  `From Date` และ `To Date` ถูกเซ็ตเป็น `25/08/2026` (วันที่ถ่ายภาพ) ทั้งคู่ ทำให้ไม่มี order ใดเข้าเกณฑ์
  ส่วน CI4 แสดง 9 แถว พร้อมข้อความ `9 matching order(s). Status accepts comma-separated IDs.`
- ค่าตั้งต้นฟิลเตอร์: CI3 `Status: None selected` + วันที่วันนี้ทั้งคู่;
  CI4 `Status IDs: 2,3` + From/To date ว่าง — ค่าตั้งต้นคนละชุด และผลที่แสดง (9 แถว รวมสถานะ
  SYNTHETIC NEW / DELETED / HIDDEN ที่ไม่ใช่ 2,3) บอกว่า CI4 ยังไม่ได้ apply ค่า `2,3` ตอนโหลดครั้งแรก
- กลไกตาราง: CI3 เป็น DataTables — มี `Show 25 entries`, ช่อง `Search:` แยก, หัวคอลัมน์กดเรียงได้,
  ปุ่ม `Previous` / `Next`; CI4 เป็นตารางธรรมดา ไม่มี page size, ไม่มี sort, ไม่มี pagination
- ปุ่ม export: CI3 = `Export` (มุมขวาบน) / CI4 = `Export XLS` (ในกล่องฟิลเตอร์)
- คอลัมน์: ทั้งคู่มี No, Action Status, Status Update, TotalDay, CMG TotalDay, Branch User,
  Branch Name, trackID, orderID ตรงกัน; CI4 เพิ่ม `CMG No.` และ `Urgent` ซึ่งฝั่ง CI3 ถูกตัด
  ที่ขอบตาราง (เห็นแค่ `เลขที่ C...`) จึงเทียบคอลัมน์ท้าย ๆ ไม่ได้จากภาพ
- Mobile: CI3 responsive พอดีจอ 390px; CI4 กล่องฟิลเตอร์พอดีจอแต่ตัวหน้ากว้าง ~3313px และตาราง
  ถูกตัดที่ราว 225px เห็นแค่คอลัมน์ No + Action Status

เสนอ `NEED_USER` เพราะจำนวนแถวที่ต่างกันเกิดจากค่าตั้งต้นของฟิลเตอร์คนละแบบ ต้องให้คนตัดสินว่า
พฤติกรรม "โหลดมาแล้วว่างเพราะ default = วันนี้" ของ CI3 คือสิ่งที่ต้องคง parity หรือเป็นของเสีย

## หมายเหตุร่วมทุกหน้า

- CI4 รันด้วย `ENVIRONMENT=development` จึงมีปุ่มไอคอนเปลวไฟของ CodeIgniter debug toolbar
  ติดอยู่มุมขวาล่าง (บางหน้าลอยกลางจอเพราะ layout ล้น) — เป็นของ harness ไม่ใช่ของ UI
  ไม่นับเป็นความต่าง
- ปัญหา layout ล้นแนวนอนที่ mobile พบใน CI4 ทุกหน้าที่ล็อกอินแล้ว (orders-new, orders-edit,
  ทุก order-listing, report-tracking-listing) รูปแบบเดียวกันหมด — น่าจะเป็นสาเหตุเดียวกัน
  คือ navbar ที่ไม่มี responsive collapse ดันความกว้างเอกสาร
- CI4 ทุกหน้าที่ล็อกอินแล้วไม่มี sidebar เมนู ใช้ navbar แนวนอนแทน และรายการเมนูล้นออกนอกจอ
  ตั้งแต่ desktop 1440px
