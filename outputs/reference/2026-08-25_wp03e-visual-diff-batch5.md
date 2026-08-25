# diff batch5 — reports + exports (11 page-id, 44 ภาพ)

manifest: `evidence/wp03e-visual/manifest-batch5.json`
ภาพที่เปิดดูจริง: 44/44 ไฟล์ (ครบทุก page-id x 2 ระบบ x 2 viewport) ไม่มีไฟล์ไหนเปิดไม่ได้

## กติกาที่ใช้ตัดสินในไฟล์นี้

- **MAJOR vs BEHAVIOR เมื่อเข้าเกณฑ์ทั้งคู่**: ถ้ารากของความต่างเป็นพฤติกรรมระบบ
  (ค่า default ที่ระบบเติม, endpoint/href ที่ปุ่มชี้ไป) ให้เป็น BEHAVIOR;
  ถ้าเป็น element หาย/เกินหรือ layout ให้เป็น MAJOR — คู่มือไม่ได้จัดลำดับสองค่านี้ไว้
  จึงประกาศกติกาไว้ตรงนี้แล้วใช้เหมือนกันทุกแถว
- **ช่องทางหลักฐานแยกกัน**: สิ่งที่ตัดสินจากภาพ = layout, element ที่มี/ไม่มี, จำนวนแถว,
  ข้อความบนปุ่ม; สิ่งที่ตัดสินจาก note ของ manifest = href/selector ของปุ่ม และชื่อ field
  ของฟิลเตอร์ (มองไม่เห็นในภาพนิ่ง) — ในไฟล์นี้ระบุที่มาไว้ทุกครั้งที่อ้าง

## ความต่างระดับ shell ที่พบเหมือนกันทุกหน้า (ไม่นับซ้ำในแต่ละแถว)

1. **โครง navigation คนละแบบ** — CI3 = sidebar ซ้ายเต็มความสูง (MENU / DASHBOARD /
   MASTER ADMIN / ORDER / REPORT TRACKING / ...) + แถบหัวสีน้ำเงิน + footer
   "NEED HELP ? CALL OUR CUSTOMER CENTRE AT 02-761-9999"; CI4 = แถบเมนูแนวนอนบนสุด
   ไม่มี sidebar ไม่มี footer
2. **nav ของ CI4 ล้นแนวนอน** — ภาพ CI4 ทุกใบกว้าง 3313px ทั้งที่ viewport 1440 และ 390
   เมนูแนวนอนไหลยาวเลยขอบจอ ตัวอักษรครึ่งหลังจางจนอ่านไม่ออก (ยืนยันจากภาพจริงทุกใบ
   ไม่ใช่ artifact ของการถ่าย ตาม batch_note)
3. **mobile 390 ของ CI4 ไม่ใช่ mobile layout จริง** — เนื้อหาถูกบีบเหลือคอลัมน์กว้าง ~225px
   ที่มุมซ้าย ส่วนที่เหลือเป็นพื้นที่ว่างของ nav ที่ล้น ตารางทุกหน้าจึงถูกตัดกลางคอลัมน์
   (เช่น `report-ratings__ci4__390x844.png` คอลัมน์ Detail ขาดกลางคำว่า "1: 0.00% 2: 0.00% 3: 0.00%")
   ข้อนี้กระทบทุก page-id ในชุดนี้เท่ากัน จึงยกมาไว้ที่นี่แทนการเขียนซ้ำ 11 แถว
4. **ตำแหน่งปุ่ม export ต่างกันเชิงโครง** — CI3 วางปุ่มที่มุมขวาบนของแถบหัวเรื่อง
   CI4 วางไว้ในการ์ดฟิลเตอร์ถัดจากปุ่ม Filter
5. **CI4 มีไอคอน debug toolbar ของ CodeIgniter** (ไอคอนสีส้มมุมล่างของ container)
   เพราะรัน ENVIRONMENT=development — ไม่ใช่ความต่างของ feature

## ตารางผล

| page-id | desktop | mobile | verdict | สิ่งที่ต่าง (สั้น ตรงประเด็น) | ข้อเสนอ |
|---|---|---|---|---|---|
| report-ratings | MAJOR | MAJOR | MAJOR | CI3 แสดงคำถามภาษาไทย 6 ข้อ + progress bar ต่อดาว + ตาราง "No / Note"; CI4 เป็นตารางเดียว "Item / Total / Detail" 8 แถวชื่อ "Question 1"–"Question 8" ไม่มีข้อความคำถาม ไม่มี progress bar ไม่มีตาราง Note; ฟิลเตอร์ Branch หายฝั่ง CI4 | FIX_CI4 |
| report-jobs-by-day | MAJOR | MAJOR | MAJOR | CI4 ไม่มีปุ่ม Export, ไม่มีฟิลเตอร์ Branch, ไม่มีหัวข้อ "KPI REPORT" และ "Completed Job"; ข้อมูล 9 แถวตรงกัน | NEED_USER |
| report-pending | MAJOR | MAJOR | MAJOR | CI4 ไม่มีปุ่ม Export, ไม่มีฟิลเตอร์ Branch, ไม่มีหัวข้อ "KPI REPORT" และ "Pending Job"; ข้อมูล 2 แถวตรงกัน | NEED_USER |
| report-pending-total | MAJOR | MAJOR | MAJOR | CI4 ไม่มี **กราฟวงกลม** ที่ CI3 มี (พร้อม legend และหัวเรื่อง "Pending Job 25/07/2026 - 25/08/2026"), ไม่มีปุ่ม Export, ไม่มีฟิลเตอร์ Branch; ตาราง 4 แถวตรงกัน | NEED_USER |
| report-in-progress-average | MAJOR | MAJOR | MAJOR | CI4 ไม่มี **กราฟวงกลม** ที่ CI3 มี (5 ส่วน พร้อม legend ภาษาไทย), ไม่มีปุ่ม Export, ไม่มีฟิลเตอร์ Branch; ตาราง 6 แถวตรงกัน | NEED_USER |
| report-in-progress | MAJOR | MAJOR | MAJOR | CI4 ไม่มี DataTables (Show entries / Search / หัวคอลัมน์เรียงลำดับ / "Showing 1 to 8 of 8 entries" / Previous-1-Next) ที่ CI3 มีครบ, ไม่มีฟิลเตอร์ Branch, ฟิลเตอร์สถานะเปลี่ยนจาก dropdown เดี่ยวเป็น list box หลายค่า; ข้อมูล 8 แถวตรงกัน | NEED_USER |
| report-summary | MAJOR | MAJOR | MAJOR | ชุดคอลัมน์คนละชุด — CI3 = Action Status / Branch User / Branch Name / trackID / orderID / Urgent / Fullname / Tel; CI4 = Status / Tracking / Order / Branch / Brand / Type / Price (หาย Branch User, Urgent, Fullname, Tel; เพิ่ม Brand, Type, Price); CI4 ไม่มี DataTables แต่เพิ่มบรรทัด "8 matching order(s)." + ฟิลเตอร์ Search และ Branch; 8 แถวเท่ากัน | NEED_USER |
| export-ratings | BEHAVIOR | BEHAVIOR | BEHAVIOR | ปุ่มมีทั้งสองระบบแต่คนละตัว — ข้อความ "Export" vs "Export XLS" (จากภาพ); href ชี้คนละ endpoint `/user/excel_ratings//25-07-2026/25-08-2026` vs `/reports/ratings/export?start_date=...&end_date=...` (จาก note ของ manifest) | NEED_USER |
| export-in-progress | BEHAVIOR | BEHAVIOR | BEHAVIOR | ข้อความปุ่ม "Export" vs "Export XLS" (จากภาพ); href ของ CI3 ส่ง branchId/startDate/endDate/status ครบ ส่วน CI4 ส่งแค่ start_date/end_date ทั้งที่หน้ามีตัวเลือกสถานะหลายค่า (จาก note ของ manifest) | NEED_USER |
| export-tracking | BEHAVIOR | BEHAVIOR | BEHAVIOR | href ของ CI4 (`/reports/tracking/export`) ไม่มีพารามิเตอร์ฟิลเตอร์ติดไปเลย (จาก note ของ manifest); และ default date range ต่างกันจนตารางแสดงคนละจำนวน — **จากภาพ CI3 = 0 แถว** ("Showing 0 to 0 of 0 entries") ส่วน CI4 = 9 แถว (note ของ manifest บันทึกว่า CI3 ได้ 1 แถว ซึ่งไม่ตรงกับภาพ) | NEED_USER |
| export-summary | BEHAVIOR | BEHAVIOR | BEHAVIOR | ข้อความปุ่ม "Export" vs "Export XLS" (จากภาพ); href ของ CI3 = `/order/excel_report/0/0/0/0/` ส่วน CI4 = `/reports/summary/export` ไม่มีพารามิเตอร์ (จาก note ของ manifest) และใบงานระบุ endpoint ไว้เป็น `/Order/excel_report_sum` ซึ่งไม่ตรงกับปุ่มจริงบนหน้า | NEED_USER |

**สรุปจำนวน**: MATCH 0 หน้า / MINOR 0 หน้า / MAJOR 7 หน้า / BEHAVIOR 4 หน้า

## รายละเอียดหน้าที่ verdict เป็น MAJOR หรือ BEHAVIOR

### report-ratings — MAJOR

ไฟล์: `report-ratings__ci3__1440x900.png`, `report-ratings__ci4__1440x900.png`,
`report-ratings__ci3__390x844.png`, `report-ratings__ci4__390x844.png`

CI3 เรนเดอร์เป็นรายงานเชิงคำถาม: หัวเรื่อง "RATING REPORT" แล้วไล่คำถามภาษาไทย
"1. ความพึงพอใจในการให้บริการของเจ้าหน้าที่ ณ จุดรับซ่อม" ถึง "6. ข้อเสนอแนะเพิ่มเติม"
(ข้อ 5 แตกย่อยเป็น 5.1–5.4) แต่ละข้อมีบรรทัด "Total 1" และแถบเปอร์เซ็นต์ 5 ระดับดาว
แถบที่มีค่าจะเป็นสีเขียวเต็มความกว้าง จบด้วยตาราง "No | Note" ที่มี 1 แถว
"SYNTHETIC RATING COMMENT"

CI4 เรนเดอร์เป็นตารางเดียว หัวคอลัมน์ "Item | Total | Detail" 8 แถว ชื่อรายการเป็น
"Question 1" ถึง "Question 8" คอลัมน์ Detail เป็นสตริงเปอร์เซ็นต์รวม
"1: 0.00% 2: 0.00% 3: 0.00% 4: 0.00% 5: 100.00%" ไม่มีข้อความคำถามภาษาไทยเลย
ไม่มี progress bar และไม่มีตาราง Note

ค่าช่วงวันที่เติมมาตรงกันทั้งสองระบบ (25/07/2026–25/08/2026) จึงไม่ใช่เรื่องข้อมูลคนละชุด
ฝั่ง CI3 มี dropdown "Branch: ALL" เพิ่มมาซึ่ง CI4 ไม่มี

**ข้อมูลตรงกัน mapping ตรงกันทีละข้อ** — ตรวจแล้วว่าไม่ใช่ปัญหาเรื่องข้อมูล CI3 มีบล็อก
คะแนน 8 บล็อก (ข้อ 1–4 และ 5.1–5.4) ส่วนข้อ 6 เป็นข้อความปลายเปิดที่ไปอยู่ในตาราง Note
ไม่ใช่บล็อกคะแนน ซึ่งแม็ปกับ 8 แถวของ CI4 ได้พอดี และค่าก็ตรงกันทุกข้อ:

| CI4 | ดาวที่ได้ 100% ฝั่ง CI4 | CI3 | แถบเขียวฝั่ง CI3 |
|---|---|---|---|
| Question 1 | 5 | ข้อ 1 | 5 ดาว |
| Question 2 | 4 | ข้อ 2 | 4 ดาว |
| Question 3 | 3 | ข้อ 3 | 3 ดาว |
| Question 4 | 2 | ข้อ 4 | 2 ดาว |
| Question 5 | 1 | ข้อ 5.1 | 1 ดาว |
| Question 6 | 5 | ข้อ 5.2 | ไม่มีแถบไหนเป็น 100% |
| Question 7 | 4 | ข้อ 5.3 | 4 ดาว |
| Question 8 | 3 | ข้อ 5.4 | 3 ดาว |

ความต่างจึงเป็นเรื่องการนำเสนอล้วน ๆ — สิ่งที่หายฝั่ง CI4 คือข้อความคำถามภาษาไทย
progress bar และตาราง Note ไม่ใช่ข้อมูลผิด

แถวที่ 6 ของตารางเป็นข้อสังเกตเพิ่มที่ควรบันทึกไว้: บล็อก 5.x ของ CI3 เรนเดอร์แถบแค่
4 ระดับดาว (1–4 ดาว) ไม่มีแถวดาวที่ 5 ทั้งที่บล็อกข้อ 1–4 มีครบ 5 ระดับ ข้อ 5.2 ที่
CI4 บอกว่าได้ 5 ดาว 100% จึงไม่มีที่แสดงบนหน้า CI3 เลย (ทุกแถบขึ้น 0%) — เข้าข่ายว่า
ฝั่ง CI3 ต่างหากที่แสดงไม่ครบ ไม่ใช่ CI4 คำนวณผิด

### report-jobs-by-day — MAJOR

ไฟล์: `report-jobs-by-day__ci3__1440x900.png`, `report-jobs-by-day__ci4__1440x900.png`,
`report-jobs-by-day__ci3__390x844.png`, `report-jobs-by-day__ci4__390x844.png`

ข้อมูลในตารางตรงกันเป๊ะ 9 แถว (SYNTHETIC BRAND A/B x SYNTHETIC TYPE A/B, TOTAL,
และ 4 แถว "Over all repair time ... 0 %") หัวคอลัมน์ "Brand | Product Type | 0 | 1-7 |
8-30 | 31-45 | > 45" ตรงกัน

สิ่งที่หายฝั่ง CI4 เห็นชัดจากภาพ desktop:
- ปุ่ม "Export" มุมขวาบน
- dropdown "Branch : ALL"
- แถบหัวเรื่อง "KPI" และบรรทัด "KPI REPORT"
- หัวข้อย่อย "Completed Job" เหนือตาราง

เรื่องปุ่ม Export ต้องให้คนตัดสิน เพราะ note ของ manifest บันทึกว่าปุ่มของ CI3 บนหน้านี้
`href="#"` ไม่ชี้ endpoint ใด และตอนถ่ายไม่ได้ตรวจว่ามี JS handler ผูกอยู่หรือไม่
(ห้ามกดปุ่ม) จึงยังสรุปไม่ได้ว่าปุ่มฝั่ง CI3 ใช้งานได้จริง — ถ้าปุ่มเป็นของตายอยู่แล้ว
การที่ CI4 ไม่มีปุ่มก็อาจถูกต้องกว่า ส่วนการหายของฟิลเตอร์ Branch เป็นความสามารถที่หายจริง

### report-pending — MAJOR

ไฟล์: `report-pending__ci3__1440x900.png`, `report-pending__ci4__1440x900.png`,
`report-pending__ci3__390x844.png`, `report-pending__ci4__390x844.png`

ข้อมูลตรงกัน 2 แถว (WP00C-TRACK-003 / สถานะทดสอบ 3 / WPC/003 / 0000000000 /
04/08/2026 / 21 และแถว TOTAL 21) หัวคอลัมน์
"No | trackID | Status | เล่มที่/เลขที่ | เบอร์มือถือลูกค้า | วันที่ส่งซ่อม | Day" ตรงกัน

หายฝั่ง CI4: ปุ่ม "Export", dropdown "Branch : ALL", แถบหัวเรื่อง "KPI" / "KPI REPORT"
และหัวข้อย่อย "Pending Job" เงื่อนไขการตัดสินปุ่มเหมือนหน้า report-jobs-by-day
(CI3 `href="#"` ไม่ได้ตรวจ handler)

### report-pending-total — MAJOR

ไฟล์: `report-pending-total__ci3__1440x900.png`, `report-pending-total__ci4__1440x900.png`,
`report-pending-total__ci3__390x844.png`, `report-pending-total__ci4__390x844.png`

จุดที่หนักที่สุดของหน้านี้และ manifest ไม่ได้บันทึกไว้: **CI3 มีกราฟวงกลม แต่ CI4 ไม่มี**
CI3 แสดงหัวเรื่องกราฟ "Pending Job" + "25/07/2026 - 25/08/2026" กราฟวงกลม 3 ส่วน
พร้อม label ชี้ออกมาแต่ละส่วน ("Working in process - CMG (80)%",
"Waiting for CMG to pick up (20)%", "Pending for customer to pick up (0)%")
และ legend 3 รายการใต้กราฟ ฝั่ง CI4 ไม่มีกราฟและ legend เลย มีเฉพาะตาราง

ตารางด้านล่างตรงกัน 4 แถว (Waiting for CMG to pick up 1 / 20%, Working in process - CMG
4 / 80%, Pending for customer to pick up 0 / 0%, TOTAL 5 / 100%) หัวคอลัมน์
"No | Detail | Job | Average (Percent)" ตรงกัน

หายเพิ่มฝั่ง CI4: ปุ่ม "Export" (CI3 `href="#"` ตาม note), dropdown Branch,
แถบหัวเรื่อง "KPI" / "KPI REPORT"

ข้อควรพิจารณาก่อนสั่งให้ CI4 ทำกราฟตาม: กราฟของ CI3 มีลายน้ำ "Trial Version" และ
"CanvasJS.com" อยู่บนภาพ แปลว่าฝั่ง CI3 ใช้ไลบรารีที่ยังไม่ได้ซื้อสิทธิ์ — ต้องให้คนตัดสิน
ว่าจะพอร์ตกราฟมาด้วยไลบรารีตัวไหน หรือถือว่ากราฟเป็นของที่ตั้งใจตัดทิ้ง

### report-in-progress-average — MAJOR

ไฟล์: `report-in-progress-average__ci3__1440x900.png`,
`report-in-progress-average__ci4__1440x900.png`,
`report-in-progress-average__ci3__390x844.png`,
`report-in-progress-average__ci4__390x844.png`

รูปแบบเดียวกับ report-pending-total: **CI3 มีกราฟวงกลม 5 ส่วน CI4 ไม่มี** หัวเรื่องกราฟ
"In Progress Job" + "25/07/2026 - 25/08/2026" label ชี้ออกครบทั้ง 5 สถานะภาษาไทย
(สินค้าจัดส่งเข้าศูนย์บริการ 33.33%, อีก 4 สถานะ 16.67% เท่ากัน) พร้อม legend 5 รายการ
มีลายน้ำ "Trial Version" / "CanvasJS.com" เช่นเดียวกัน

ตารางตรงกัน 6 แถว (5 สถานะ + TOTAL 6 / 100.00%) หัวคอลัมน์
"No | Detail | Job | Average (Percent)" ตรงกัน ข้อความสถานะภาษาไทยตรงกันทุกแถว

หายเพิ่มฝั่ง CI4: ปุ่ม "Export" (หน้านี้ CI3 ใช้ `href="javascript:void(0);"` ตาม note
ซึ่งยิ่งบ่งว่าปุ่มพึ่ง JS handler ที่ยังไม่ได้ตรวจ), dropdown Branch,
แถบหัวเรื่อง "IN PROGRESS REPORT"

### report-in-progress — MAJOR

ไฟล์: `report-in-progress__ci3__1440x900.png`, `report-in-progress__ci4__1440x900.png`,
`report-in-progress__ci3__390x844.png`, `report-in-progress__ci4__390x844.png`

ข้อมูลตรงกันเป๊ะ 8 แถว (WP00C-TRACK-001 ถึง -006, -008, -009 พร้อม Branch Name,
Full Name, Tel, Request Date, Day) หัวคอลัมน์ตรงกัน

ความสามารถที่หายฝั่ง CI4 เห็นชัดจากภาพ desktop — CI3 ใช้ DataTables เต็มรูป:
- dropdown "Show 25 entries"
- ช่อง "Search:" มุมขวาบนของตาราง
- ไอคอนเรียงลำดับบนหัวคอลัมน์ทุกคอลัมน์ (คอลัมน์ No ถูกเรียงอยู่)
- บรรทัด "Showing 1 to 8 of 8 entries"
- ตัวแบ่งหน้า "Previous | 1 | Next"

CI4 เป็นตาราง HTML ธรรมดา ไม่มีทั้ง 5 อย่าง

ฟิลเตอร์ก็ต่างกัน: CI3 มี "Status: None selected" (ปุ่ม multiselect แบบ dropdown) +
"Branch: ALL" + ช่วงวันที่; CI4 มีช่วงวันที่ + list box "Status" ที่แสดงตัวเลือก
"สถานะทดสอบ 1"–"4" ค้างเป็นกล่องเปิดอยู่ (เลือกได้หลายค่า) และไม่มี Branch เลย
กล่อง Status ของ CI4 สูงไม่พอ ตัวเลือกที่ 5 ถูกตัดครึ่งบรรทัด

### report-summary — MAJOR

ไฟล์: `report-summary__ci3__1440x900.png`, `report-summary__ci4__1440x900.png`,
`report-summary__ci3__390x844.png`, `report-summary__ci4__390x844.png`

ทั้งสองระบบแสดง 8 แถว และแม็ปกันได้ตาม trackID (WP00C-TRACK-008 ถึง -001 เรียงลงมา
เหมือนกัน สถานะ SYNTHETIC DELETED / COMPLETED / HIDDEN / RETURN / REPAIR COMPLETE /
REPAIR / REQUEST / NEW ตรงกันทุกแถว) แต่ **ชุดคอลัมน์คนละชุด**:

- CI3: No | Action Status | Branch User | Branch Name | trackID | orderID | Urgent |
  Fullname | Tel (คอลัมน์ขวาสุดถูกตัดที่ขอบภาพ)
- CI4: No | Status | Tracking | Order | Branch | Brand | Type | Price

คอลัมน์ที่หายฝั่ง CI4: Branch User, Urgent, Fullname, Tel
คอลัมน์ที่เพิ่มฝั่ง CI4: Brand, Type, Price (มีค่า 800.00 ลงมาถึง 100.00)

CI3 มี DataTables ครบชุดเหมือนหน้า report-in-progress (Show entries / Search /
เรียงลำดับ / "Showing 1 to 8 of 8 entries" / Previous-1-Next) CI4 ไม่มี แต่เพิ่มบรรทัด
"8 matching order(s)." ใต้หัวเรื่องแทน

ฟิลเตอร์: CI3 = Brand / ยี่ห้อ, Category / ประเภท, Status, From Date, To Date (ทั้งหมด
ว่างเปล่า placeholder "Date"); CI4 = Search, From date, To date, Status, Brand, Type,
Branch — CI4 เพิ่ม Search และ Branch ที่ CI3 ไม่มี ทั้งสองระบบเว้นช่วงวันที่ว่างเหมือนกัน
จึงไม่มีประเด็น default date ในหน้านี้

### export-ratings — BEHAVIOR

ไฟล์: `export-ratings__ci3__1440x900.png`, `export-ratings__ci4__1440x900.png`,
`export-ratings__ci3__390x844.png`, `export-ratings__ci4__390x844.png`

page-id นี้ตัดสินที่ปุ่ม export ไม่ใช่ตัวหน้า (ตัวหน้าตัดสินไว้แล้วที่ report-ratings)

จากภาพ: ปุ่มมีทั้งสองระบบ CI3 เป็นปุ่มขอบขาวข้อความ "Export" ที่มุมขวาบนของแถบหัวเรื่อง
สีน้ำเงิน CI4 เป็นปุ่มทึบสีน้ำเงินข้อความ "Export XLS" อยู่ในการ์ดฟิลเตอร์ถัดจากปุ่ม Filter

จาก note ของ manifest (มองไม่เห็นในภาพ): selector และ href ต่างกันเชิงโครงสร้าง
- CI3 `a.btn.btn-primary` → `/user/excel_ratings//25-07-2026/25-08-2026`
  (ยัดค่าฟิลเตอร์เป็น path segment มี `//` เพราะ segment branch ว่าง)
- CI4 `a.button` → `/reports/ratings/export?start_date=25%2F07%2F2026&end_date=25%2F08%2F2026`
  (query string, วันที่เป็นสแลช encode แทนขีดกลาง)

endpoint คนละ path กันโดยสิ้นเชิง ต้องให้คนตัดสินว่า URL contract ใหม่ของ CI4 เป็น
สิ่งที่ตั้งใจ (route ใหม่ตาม CI4 convention) หรือต้องคง path เดิมไว้เพื่อ backward-compat
กับ bookmark/สคริปต์ที่มีอยู่ **ยังไม่ได้กดปุ่มทั้งสองระบบ จึงยังไม่มีหลักฐานว่าไฟล์
ที่ดาวน์โหลดออกมาเหมือนหรือต่างกัน**

### export-in-progress — BEHAVIOR

ไฟล์: `export-in-progress__ci3__1440x900.png`, `export-in-progress__ci4__1440x900.png`,
`export-in-progress__ci3__390x844.png`, `export-in-progress__ci4__390x844.png`

จากภาพ: ปุ่ม "Export" (CI3 มุมขวาบน) vs "Export XLS" (CI4 ในการ์ดฟิลเตอร์) — มีทั้งคู่

จาก note ของ manifest:
- CI3 → `/user/excel_in_progress_job?branchId=&startDate=25-07-2026&endDate=25-08-2026&status=`
- CI4 → `/reports/in-progress/export?start_date=25%2F07%2F2026&end_date=25%2F08%2F2026`

ประเด็นที่หนักกว่าเรื่องข้อความปุ่ม: **href ของ CI4 ไม่ส่งค่า status ไปเลย** ทั้งที่หน้านี้
มีตัวเลือกสถานะแบบเลือกหลายค่าอยู่จริง (เห็นเป็น list box ในภาพ CI4) แปลว่าถ้าผู้ใช้
เลือกสถานะแล้วกด Export XLS ไฟล์ที่ได้อาจไม่ถูกกรองตามที่เห็นบนหน้าจอ ฝั่ง CI3 ส่ง
`branchId` และ `status` ไปด้วย (ค่าว่างเพราะยังไม่ได้เลือก) จึงมีช่องรับค่าอยู่แล้ว
รวมถึงรูปแบบวันที่ก็คนละแบบ (ขีดกลาง vs สแลช encode)

ข้อจำกัดของหลักฐาน: ตัดสินจาก href ที่บันทึกตอน default เท่านั้น ยังไม่ได้ลองเลือกสถานะ
แล้วดูว่า href เปลี่ยนตามหรือไม่ และไม่ได้กดปุ่ม — ถ้า CI4 ผูก JS ที่เติมพารามิเตอร์
ตอนคลิก ข้อสังเกตนี้อาจตกไป ต้องให้คนตรวจ

### export-tracking — BEHAVIOR

ไฟล์: `export-tracking__ci3__1440x900.png`, `export-tracking__ci4__1440x900.png`,
`export-tracking__ci3__390x844.png`, `export-tracking__ci4__390x844.png`

หน้านี้มีสองประเด็นซ้อนกัน

**หนึ่ง — ปุ่ม export** จากภาพมีทั้งสองระบบ ("Export" vs "Export XLS")
จาก note ของ manifest CI3 → `/order/excel_report/0/25-08-2026/25-08-2026/0/`
(พาช่วงวันที่ default ของหน้าไปด้วย) ส่วน CI4 → `/reports/tracking/export`
**ไม่มีพารามิเตอร์ฟิลเตอร์ติดไปเลย** ทั้งที่หน้า CI4 มีช่อง Status IDs, ช่วงวันที่ และ Search
เป็นความเสี่ยงเดียวกับ export-in-progress แต่หนักกว่าเพราะไม่ส่งอะไรเลยแม้แต่วันที่

**สอง — ค่า default ของช่วงวันที่ และจำนวนแถวที่ต่างกัน** ตรงนี้ภาพขัดกับ note ของ manifest
จึงบันทึกตามภาพ:
- CI3 (`export-tracking__ci3__1440x900.png`): From Date และ To Date เติมมาเป็น
  25/08/2026 ทั้งคู่ (วันนี้ถึงวันนี้) ตาราง **ไม่มีแถวข้อมูลเลย** — ตัวตารางเป็นแถบว่าง
  และบรรทัดสรุปเขียนว่า "Showing 0 to 0 of 0 entries" ปุ่มแบ่งหน้า Previous/Next
  ไม่มีเลขหน้า
- CI4 (`export-tracking__ci4__1440x900.png`): ช่อง From date / To date ว่าง
  (แสดง placeholder "dd/mm/yyyy") ตารางมี 9 แถว และมีบรรทัด
  "9 matching order(s). Status accepts comma-separated IDs."

**note ของ manifest บันทึกว่า CI3 แสดง 1 แถว แต่ภาพแสดง 0 แถวชัดเจน** — จุดนี้ note
กับภาพไม่ตรงกัน ในไฟล์นี้ยึดตามภาพตามที่คู่มือกำหนด ("ตัดสินจากภาพจริงเท่านั้น")
ข้อสรุปเชิงพฤติกรรมไม่เปลี่ยน: ความต่างจำนวนแถวเกิดจาก default date range
ที่ CI3 เติมแต่ CI4 ไม่เติม ไม่ใช่ข้อมูลใน DB คนละชุด

ความต่างของฟิลเตอร์และคอลัมน์เพิ่มเติมที่เห็นจากภาพ:
- CI3 มีปุ่ม multiselect สถานะ "None selected"; CI4 มีช่อง "Status IDs" ที่แสดง
  placeholder สีจาง "2,3" (เป็นข้อความใบ้รูปแบบ ไม่ใช่ค่าที่ถูกตั้งไว้ — สีเดียวกับ
  placeholder "dd/mm/yyyy" และ "Tracking, order, customer" ในการ์ดเดียวกัน)
  พร้อมช่อง Search แยกอีกช่อง
- CI3 มีคอลัมน์ "Actions" ปิดท้ายตาราง ฝั่ง CI4 มองไม่เห็นคอลัมน์นี้ในภาพ แต่ตาราง CI4
  ถูกตัดที่ขอบ container จึงยังยืนยันไม่ได้ว่าไม่มีจริงหรือแค่มองไม่เห็น
- คอลัมน์ที่เห็นตรงกัน: No, Action Status, Status Update, TotalDay, CMG TotalDay,
  Branch User, Branch Name, trackID, orderID

### export-summary — BEHAVIOR

ไฟล์: `export-summary__ci3__1440x900.png`, `export-summary__ci4__1440x900.png`,
`export-summary__ci3__390x844.png`, `export-summary__ci4__390x844.png`

จากภาพ: ปุ่ม "Export" (CI3 มุมขวาบน) vs "Export XLS" (CI4 ในการ์ดฟิลเตอร์) มีทั้งคู่

จาก note ของ manifest:
- CI3 `a.btn.btn-primary` → `/order/excel_report/0/0/0/0/` (ค่า 0 ทั้งหมดเพราะยัง
  ไม่ได้ตั้งฟิลเตอร์)
- CI4 `a.button` → `/reports/summary/export` (ไม่มีพารามิเตอร์)

จุดที่ต้องให้คนตัดสินเพิ่มอีกหนึ่งข้อ: ใบงาน `batch5-report.md` ระบุ endpoint ของ
export-summary ไว้ว่า `/Order/excel_report_sum` แต่ปุ่มจริงบนหน้า /reportsummary
ของ CI3 ชี้ไป `/order/excel_report` ซึ่งเป็น endpoint เดียวกับปุ่มบนหน้า
/ReportTrackingListing ต่างกันแค่ค่า segment — ตอนถ่ายสแกนปุ่มทั้งหน้าแล้วพบว่า
มีปุ่ม export เดียว ไม่มีปุ่มที่ชี้ไป excel_report_sum ซ่อนอยู่ (ตาม note ของ manifest)
ต้องยืนยันว่าเป้าหมายการพอร์ตคือ endpoint ไหนกันแน่ ก่อนตัดสินว่า CI4 ทำถูกหรือผิด

**ยังไม่ได้กดปุ่มดาวน์โหลดทั้งสองระบบ** จึงยังไม่มีหลักฐานว่าเนื้อหาไฟล์ที่ export ออกมา
ตรงกันหรือไม่ ทุกข้อสรุปของ page-id กลุ่ม export ในไฟล์นี้จำกัดอยู่ที่ตัวปุ่มเท่านั้น

## สิ่งที่งานนี้ยังไม่ครอบ

- ไม่ได้กดปุ่ม export ใด ๆ จึงไม่รู้ว่าไฟล์ที่ดาวน์โหลดจากสองระบบมีเนื้อหาตรงกันหรือไม่
  และไม่รู้ว่าปุ่มของ CI3 ที่ `href="#"` / `href="javascript:void(0);"` บน 4 หน้า
  (jobs-by-day, pending, pending-total, in-progress-average) ใช้งานได้จริงหรือเป็นปุ่มตาย
- ไม่ได้กรอกวันที่หรือเลือกฟิลเตอร์เอง จึงเทียบได้เฉพาะสถานะ default
  ความต่างของผลลัพธ์หลังกดค้นหายังไม่ได้ตรวจ
- ตาราง CI4 ที่ถูกตัดขอบ container (report-summary, export-tracking, export-summary
  ทั้ง desktop และ mobile) ยืนยันไม่ได้ว่ามีคอลัมน์ขวาสุดอะไรบ้าง
- ไม่ได้ตรวจ mobile layout ของ CI4 ในสภาพจริง เพราะ nav ที่ล้นทำให้ fullPage capture
  ขยายเป็น 3313px ทุกใบ สิ่งที่ยืนยันได้คือมีปัญหา responsive จริง แต่ประเมินไม่ได้ว่า
  ถ้าแก้ nav แล้วเนื้อหาส่วนที่เหลือจะเรียงตัวถูกต้องหรือไม่
