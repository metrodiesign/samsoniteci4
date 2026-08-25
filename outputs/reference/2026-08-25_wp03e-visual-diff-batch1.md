# ผลเทียบภาพ batch1 (public + auth) — 10 page-id

เทียบจากภาพจริงครบ 40 ไฟล์ (10 page-id x CI3/CI4 x desktop 1440x900 / mobile 390x844)
ที่ `evidence/wp03e-visual/` บริบทอ่านจาก `manifest-batch1.json`

## สรุปจำนวน

| verdict | จำนวนหน้า |
|---|---|
| MATCH | 0 |
| MINOR | 0 |
| MAJOR | 9 |
| BEHAVIOR | 1 |

## ข้อสังเกตข้ามทุกหน้า (อ่านก่อนดูตาราง)

- ภาพ CI4 ทุกใบมีไอคอนเปลวไฟมุมขวาล่าง = debug toolbar ของ CodeIgniter 4
  แปลว่า CI4 ตอนถ่ายรันในโหมด development เป็นข้อจำกัดของสภาพแวดล้อมตอนถ่าย
  ไม่นับเป็น "element เกิน" ของหน้าไหน
- MAJOR 9 หน้าไม่ใช่บั๊ก 9 ตัวอิสระ แต่ส่วนใหญ่มาจากรากเดียวกัน: หน้า CI4 ยังไม่ได้ใส่ธีมของ CI3
  ครบ (container/grid ที่ทำให้เนื้อหาอยู่กลางหน้าหายไป, ฟอนต์บางส่วนตกไปเป็น serif,
  หน้า contact/login/forgot-password/dashboard/change-password ยังเป็น scaffold เปล่า)
  แก้ที่ชั้นธีม/เลย์เอาต์รอบเดียวน่าจะปิดได้หลายหน้าพร้อมกัน

## ตารางผลต่อหน้า

| page-id | desktop | mobile | verdict | สิ่งที่ต่าง | ข้อเสนอ |
|---|---|---|---|---|---|
| tracking-home-en | MINOR | MAJOR | MAJOR | desktop: บล็อกเนื้อหา CI4 ชิดซ้ายแทนที่จะอยู่กลาง ปุ่ม HOW TO CHECK / CHECK NOW เรียงลงล่างแทนเรียงข้างกัน ข้อความ footer เป็นฟอนต์ serif; mobile: ปุ่ม CONTACT US / SHOPPING ของ CI4 ไม่มีไอคอน กลายเป็นลิงก์ขีดเส้นใต้ และปุ่ม HOW TO CHECK ตัดเป็น 2 บรรทัด ขนาดปุ่มไม่เท่ากัน | FIX_CI4 |
| tracking-home-th | MAJOR | MAJOR | MAJOR | ปุ่มบนหน้าไทยของ CI4 เป็นภาษาอังกฤษ (HOW TO CHECK / CHECK NOW) ขณะที่ CI3 เป็นไทย (วิธีตรวจสอบสถานะ / ติดตาม) บวกปัญหาเลย์เอาต์และไอคอนชุดเดียวกับ tracking-home-en | FIX_CI4 |
| tracking-result-en | MAJOR | MAJOR | MAJOR | ข้อความสถานะที่แสดงต่างกัน: CI3 = "สถานะทดสอบ 1" (ไทย) CI4 = "SYNTHETIC NEW" (อังกฤษ) บน trackID เดียวกัน; CI4 ยังโชว์ข้อความ WP00C-TRACK-001 มุมซ้ายบน (alt ของรูปที่โหลดไม่ขึ้น) และเนื้อหาชิดซ้าย | NEED_USER |
| tracking-result-th | MINOR | MINOR | BEHAVIOR | ภาพ: ข้อมูลสถานะตรงกันทั้งคู่ ("สถานะทดสอบ 1" + 01/08/2569) ต่างแค่ CI4 ชิดซ้าย ฟอนต์ footer serif และโชว์ alt WP00C-TRACK-001; พฤติกรรม: CI3 เข้า GET ด้วย segment ไม่ได้ ต้องกรอกฟอร์มแล้ว POST ส่วน CI4 เข้า GET ตรงได้ | CORRECT_AND_REBASELINE |
| contact-form-en | MAJOR | MAJOR | MAJOR | CI4 เป็นหน้าเปล่าไม่มีสไตล์: บล็อก REPAIR CENTER, CUSTOMER RELATION, ปุ่ม Google Map, หัวข้อ MORE INFOMATION และฟอร์มที่จัดสไตล์แล้ว หายทั้งหมด เหลือ input เปลือย 4 ช่องเรียงบรรทัดเดียว | FIX_CI4 |
| contact-form-th | MAJOR | MAJOR | MAJOR | เหมือน contact-form-en ทุกประการ: CI4 เหลือหัวข้อ "ติดต่อเรา" กับ input เปลือย ข้อมูลศูนย์บริการซ่อม/ลูกค้าสัมพันธ์/ปุ่มแผนที่หายหมด | FIX_CI4 |
| login | MAJOR | MAJOR | MAJOR | CI4 เป็นการ์ด Sign in ธีมใหม่: ลิงก์ Forgot Password หายไป (ผู้ใช้เข้าหน้ารีเซ็ตรหัสจากหน้า login ไม่ได้) และ hero banner, หัวข้อ Tracking, แถบ footer เบอร์โทร หายทั้งหมด | FIX_CI4 |
| forgot-password | MAJOR | MAJOR | MAJOR | ฟิลด์ยังครบ (Email + Submit + ลิงก์ Login) แต่ hero banner, หัวข้อ Tracking และแถบ footer เบอร์โทร หายไปทั้งหมด CI4 เป็นการ์ดธีมใหม่ | FIX_CI4 |
| dashboard | MAJOR | MAJOR | MAJOR | เนื้อหาต่างกันคนละชุด: CI3 = ไทล์ REPORTS ใบเดียว, CI4 = การ์ดนับจำนวน Status 1-8 + ปุ่ม Open Report Tracking; เมนูจากแถบข้างแนวตั้งของ CI3 กลายเป็นแถบบนแนวนอนที่ล้นออกนอกจอ (หน้ากว้าง ~3313px) อ่านเมนูส่วนใหญ่ไม่ได้ทั้ง desktop และ mobile | NEED_USER |
| change-password | MAJOR | MAJOR | MAJOR | ปุ่ม Reset หายไปใน CI4 (CI3 มี Submit + Reset), การ์ด ENTER DETAILS และแถบหัวสีน้ำเงินหาย, เมนูแถบบนล้นออกนอกจอเหมือน dashboard | FIX_CI4 |

## รายละเอียดหน้าที่ verdict เป็น MAJOR หรือ BEHAVIOR

### tracking-home-en (MAJOR)

ไฟล์: `tracking-home-en__ci3__1440x900.png`, `tracking-home-en__ci4__1440x900.png`,
`tracking-home-en__ci3__390x844.png`, `tracking-home-en__ci4__390x844.png`

Desktop ไม่มี element หาย ครบทั้ง 5 ชิ้น (หัวข้อ TRACK & TRACE, บรรทัดรอง, ช่องกรอก,
2 ปุ่ม) แต่ CI3 จัดทุกอย่างกลางหน้าและวางปุ่มสองปุ่มข้างกัน ส่วน CI4 ดันบล็อกทั้งก้อนไปชิดซ้าย
ราว 1/3 ของจอ และวางปุ่มซ้อนลงล่าง นอกจากนี้ข้อความ footer "NEED HELP ? CALL OUR CUSTOMER
CENTRE AT" ของ CI4 เรนเดอร์เป็นฟอนต์ serif ขณะที่ CI3 เป็นฟอนต์ sans ของแบรนด์
เส้นขีดใต้ header ของ CI4 ยังลากไม่สุดความกว้างจอ (หยุดราว x=795 จาก 1520)

Mobile คือจุดที่ยก verdict เป็น MAJOR: ปุ่ม CONTACT US และ SHOPPING ของ CI3 มีไอคอน
(ซองจดหมาย / ถุงช็อปปิ้ง) อยู่เหนือข้อความ แต่ของ CI4 ไอคอนหายไปทั้งคู่ เหลือเป็นข้อความ
ขีดเส้นใต้ และแถวปุ่ม HOW TO CHECK / CHECK NOW ของ CI4 สูงไม่เท่ากัน โดย HOW TO CHECK
ตัดคำเป็นสองบรรทัด

### tracking-home-th (MAJOR)

ไฟล์: `tracking-home-th__ci3__1440x900.png`, `tracking-home-th__ci4__1440x900.png`,
`tracking-home-th__ci3__390x844.png`, `tracking-home-th__ci4__390x844.png`

ปัญหาเลย์เอาต์และไอคอนเหมือน tracking-home-en ทุกข้อ แต่มีเพิ่มที่ร้ายกว่า: หน้าไทยของ CI4
แสดงป้ายปุ่มเป็นภาษาอังกฤษ ทั้งบน desktop และ mobile — CI3 แสดง "วิธีตรวจสอบสถานะ" และ
"ติดตาม" ส่วน CI4 แสดง "HOW TO CHECK" และ "CHECK NOW" (placeholder ในช่องกรอกของ CI4
เป็นไทยถูกต้องแล้วคือ "ระบุรหัสติดตามของคุณ" ดังนั้นปัญหาอยู่ที่ป้ายปุ่มโดยเฉพาะ ไม่ใช่ทั้งหน้า)

### tracking-result-en (MAJOR)

ไฟล์: `tracking-result-en__ci3__1440x900.png`, `tracking-result-en__ci4__1440x900.png`,
`tracking-result-en__ci3__390x844.png`, `tracking-result-en__ci4__390x844.png`

trackID เดียวกัน (WP00C-TRACK-001) แต่ข้อความสถานะที่แสดงต่างกัน: CI3 แสดง "สถานะทดสอบ 1"
CI4 แสดง "SYNTHETIC NEW" วันที่ตรงกันทั้งคู่ (01/08/2569) และเทียบกับหน้าไทย
(`tracking-result-th__ci4__1440x900.png`) ที่ CI4 แสดง "สถานะทดสอบ 1" ตรงกับ CI3

อ่านได้สองทางจากภาพอย่างเดียว ตัดสินเองไม่ได้:
1. fixture มีชื่อสถานะสองภาษา CI3 ดึงชื่อไทยมาแสดงบนหน้าอังกฤษด้วย (CI3 ไม่ localize)
   ส่วน CI4 เลือกชื่อตามภาษาของหน้า — กรณีนี้ CI4 ถูกกว่า
2. CI4 ไปหยิบคนละฟิลด์/คนละแถวกับที่ CI3 ตั้งใจแสดง — กรณีนี้ CI4 ผิด

ต้องให้ user ชี้ขาดว่าหน้าอังกฤษควรแสดงชื่อสถานะภาษาใด จึงให้ NEED_USER

จุดต่างรองที่ต้องแก้ด้วย (FIX_CI4 ในตัวมันเอง): CI4 โชว์ข้อความ "WP00C-TRACK-001"
มุมซ้ายบนด้วยฟอนต์ serif ตรงตำแหน่งที่ CI3 เป็นไอคอนรูปเสีย — น่าจะเป็น alt text ของรูปเดียวกัน
ที่โหลดไม่ขึ้นทั้งสองระบบ (CI3 ไม่มี alt จึงขึ้นไอคอนรูปเสียแทน) และเนื้อหาของ CI4 ชิดซ้าย
ไม่อยู่กลางหน้าเหมือน CI3

### tracking-result-th (BEHAVIOR)

ไฟล์: `tracking-result-th__ci3__1440x900.png`, `tracking-result-th__ci4__1440x900.png`,
`tracking-result-th__ci3__390x844.png`, `tracking-result-th__ci4__390x844.png`

ด้านภาพล้วนหน้านี้เป็น MINOR ทั้งสอง viewport: ข้อมูลตรงกัน ("สถานะทดสอบ 1" + 01/08/2569)
ต่างแค่ CI4 ชิดซ้าย ฟอนต์ footer เป็น serif และโชว์ alt "WP00C-TRACK-001" แบบเดียวกับหน้า EN

เหตุที่ช่อง verdict ไม่ตรงกับกฎ "เอาค่าที่รุนแรงที่สุดของสองช่องซ้าย": การยกระดับมาจาก
พฤติกรรมที่ manifest บันทึกไว้ ไม่ได้มาจากภาพ — CI3 เข้า `/track_th/trackstatus/{trackID}`
ด้วย GET ไม่ได้จริง (ขึ้น error toast "Book creation failed" ฟอร์มว่าง เพราะ
`Track_th::trackstatus()` ไม่รับ parameter) ภาพ CI3 ที่ใช้เทียบจึงมาจากการกรอกฟอร์มที่
`/track_th` แล้ว POST ส่วน CI4 (`/tracking-th/{trackId}`) เข้าตรงด้วย GET ได้ผลลัพธ์ปกติ

CI4 ทำได้มากกว่า CI3 ในจุดนี้ และเป็นทางที่ผู้ใช้แชร์ลิงก์ผลติดตามได้จริง จึงเสนอ
CORRECT_AND_REBASELINE (ต้องขออนุมัติ user ก่อน เพราะเท่ากับยอมรับว่า baseline CI3
ของหน้านี้เป็นของเสีย) ส่วนเรื่องชิดซ้าย/ฟอนต์ ไปรวมกับงานแก้ธีมชุดเดียวกับหน้าอื่น

### contact-form-en (MAJOR)

ไฟล์: `contact-form-en__ci3__1440x900.png`, `contact-form-en__ci4__1440x900.png`,
`contact-form-en__ci3__390x844.png`, `contact-form-en__ci4__390x844.png`

CI3 มี 3 ส่วน: การ์ด REPAIR CENTER (ไอคอนช่าง + ที่อยู่ 3388/25-97 + เบอร์ <เบอร์ติดต่อบนหน้าเว็บ>-95 +
ปุ่ม Google Map), การ์ด CUSTOMER RELATION (อีเมล <อีเมลลูกค้าสัมพันธ์ของแบรนด์> + เวลาทำการ +
เบอร์) และส่วน MORE INFOMATION / FILL YOUR INFORMATION พร้อมฟอร์ม 4 ช่องจัดสไตล์
(NAME & SURNAME, E-mail, PHONE NUMBER, DETAIL) กับปุ่ม SEND NOW

CI4 เหลือหัวข้อ "Contact us" ฟอนต์ serif กับ input เปลือยไม่มี CSS 4 ช่อง (Full name, Email,
Phone, Message) เรียงต่อกันในบรรทัดเดียว + ปุ่ม "Send message" แบบปุ่มเบราว์เซอร์ดิบ
ข้อมูลศูนย์ซ่อม ข้อมูลลูกค้าสัมพันธ์ และปุ่ม Google Map หายทั้งหมด mobile เป็นแบบเดียวกัน
(ข้อมูลติดต่อหายหมด เหลือ input เปลือยตัดบรรทัดมั่ว) หน้านี้ยังไม่ได้ทำจริง ไม่ใช่แค่ธีมเพี้ยน

### contact-form-th (MAJOR)

ไฟล์: `contact-form-th__ci3__1440x900.png`, `contact-form-th__ci4__1440x900.png`,
`contact-form-th__ci3__390x844.png`, `contact-form-th__ci4__390x844.png`

เหมือน contact-form-en ทุกข้อ ฝั่ง CI3 มีครบ (ศูนย์บริการซ่อม + ที่อยู่ไทย + ปุ่มแผนที่,
ลูกค้าสัมพันธ์, ข้อมูลเพิ่มเติม + ฟอร์ม 4 ช่อง + ปุ่ม "ส่ง") ฝั่ง CI4 เหลือหัวข้อ "ติดต่อเรา"
กับ input เปลือย 4 ช่อง (ชื่อ, อีเมล, โทรศัพท์, รายละเอียด) + ปุ่ม "ส่งข้อความ"
ป้ายกำกับเป็นไทยถูกแล้ว แต่เนื้อหาส่วนข้อมูลติดต่อและสไตล์ทั้งหมดหายไป

### login (MAJOR)

ไฟล์: `login__ci3__1440x900.png`, `login__ci4__1440x900.png`,
`login__ci3__390x844.png`, `login__ci4__390x844.png`

จุดที่กระทบการใช้งานจริง ไม่ใช่แค่ธีม: **ลิงก์ "Forgot Password" หายไปจากหน้า login ของ CI4**
CI3 มีลิงก์นี้อยู่ซ้ายของปุ่ม Sign In ทั้ง desktop และ mobile ผู้ใช้ที่ลืมรหัสจึงเข้าหน้ารีเซ็ต
จากหน้า login ไม่ได้เลยใน CI4 (หน้า forgot-password มีอยู่ แต่ไม่มีทางเข้าจากหน้านี้)

element อื่นที่หายไป: hero banner รูปนางแบบ + โลโก้ Samsonite ด้านบน, หัวข้อ "Tracking",
ไอคอนซองจดหมาย/แม่กุญแจในช่องกรอก, placeholder "UserID" / "Password", แถบ footer
"NEED HELP ? CALL OUR CUSTOMER CENTRE AT 02-761-9999" ส่วนที่เพิ่มเข้ามาใน CI4 คือแถบหัว
สีกรมท่า "Samsonite Tracking" และข้อความ "Use your Samsonite Tracking account."

### forgot-password (MAJOR)

ไฟล์: `forgot-password__ci3__1440x900.png`, `forgot-password__ci4__1440x900.png`,
`forgot-password__ci3__390x844.png`, `forgot-password__ci4__390x844.png`

ตัว flow ยังครบ (ช่อง Email + ปุ่ม Submit + ลิงก์ Login กลับ) verdict เป็น MAJOR
เพราะ element ที่หายไป ไม่ใช่เพราะสีหรือฟอนต์ต่าง: hero banner + โลโก้ Samsonite,
หัวข้อ "Tracking", ไอคอนซองจดหมายในช่อง Email, placeholder "Email" และแถบ footer
เบอร์ 02-761-9999 หายทั้งหมดทั้ง desktop และ mobile ส่วน CI4 เพิ่มแถบหัว
"Samsonite Tracking" และข้อความอธิบาย "Enter your account email and we will send reset
instructions." ที่ CI3 ไม่มี (CI3 มีแค่บรรทัด "Forgot Password")

### dashboard (MAJOR)

ไฟล์: `dashboard__ci3__1440x900.png`, `dashboard__ci4__1440x900.png`,
`dashboard__ci3__390x844.png`, `dashboard__ci4__390x844.png`

มีสองเรื่องที่ต้องแยกกันตัดสิน:

1. **เมนูล้นออกนอกจอ — เป็นบั๊กชัด ต้องแก้แน่นอน** CI3 วางเมนูเป็นแถบข้างแนวตั้ง
   แบ่งกลุ่ม (DASHBOARD, MASTER ADMIN, ORDER, REPORT TRACKING, REPORT SUMMARY,
   UPLOAD STATUS/PRICE/NEW REQUEST, USER ADMIN, WEBSITE ADMIN) เลื่อนอ่านได้ครบ
   ส่วน CI4 ยัดเมนูทั้งหมดเป็นแถบบนแนวนอนบรรทัดเดียว ทำให้หน้ากว้างราว 3313px
   รายการตั้งแต่ ESTIMATEPRICE เป็นต้นไปหลุดออกนอกพื้นหลังแถบ กลายเป็นตัวหนังสือขาวบนพื้นขาว
   อ่านไม่ออกและกดยาก อาการเดียวกันทั้ง desktop และ mobile (mobile ไม่ยุบเป็นเมนูแฮมเบอร์เกอร์)
2. **เนื้อหากลางหน้าเป็นคนละชุด — ต้องให้ user ตัดสิน** CI3 มีไทล์เดียวคือ REPORTS
   ใต้หัวข้อ "Dashboard Control panel" ส่วน CI4 มีบรรทัด "Welcome, SYNTHETIC ADMIN."
   การ์ดนับจำนวน Status 1-8 (ค่า 1,2,1,1,1,1,1,1) และปุ่ม Open Report Tracking
   ไม่ใช่ของหายหรือของพัง แต่เป็นการออกแบบใหม่คนละแบบ

จึงให้ ข้อเสนอ = NEED_USER สำหรับเรื่องเนื้อหา โดยเรื่องเมนูล้นจอถือเป็น must-fix
ไม่ต้องรอคำตอบ user

### change-password (MAJOR)

ไฟล์: `change-password__ci3__1440x900.png`, `change-password__ci4__1440x900.png`,
`change-password__ci3__390x844.png`, `change-password__ci4__390x844.png`

- **ปุ่ม Reset หายไปใน CI4** CI3 มีสองปุ่มคือ Submit และ Reset ส่วน CI4 มีปุ่มเดียว
  "Change password" ผู้ใช้ล้างฟอร์มด้วยปุ่มไม่ได้แล้ว
- ป้ายกำกับเปลี่ยนคำ: OLD PASSWORD / NEW PASSWORD / CONFIRM NEW PASSWORD (CI3)
  เป็น Current password / New password / Confirm password (CI4) และ placeholder
  ในช่องกรอกหายทั้งสามช่อง
- โครงหน้าหาย: แถบหัวสีน้ำเงินพร้อมหัวข้อ "Change Password Set new password for your account",
  การ์ดขาว "ENTER DETAILS", แถบ footer เบอร์โทร
- เมนูแถบบนล้นออกนอกจอแบบเดียวกับ dashboard ทั้ง desktop และ mobile
