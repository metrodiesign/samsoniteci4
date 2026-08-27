# WP-03G re-baseline — ผลรอบสุดท้ายหลังปิด G1-G4

วันที่ 2026-08-26 · CI3 pin `ee1c95e` (ตัวเดียวกับ WP-03E ไม่เปลี่ยน) · Chromium `151.0.7922.34`
ภาพ CI4 ชุดใหม่ 130 ไฟล์ที่ `evidence/wp03g-visual/` · ภาพ CI3 อ้างอิงชุดเดิมที่ `evidence/wp03e-visual/`

เอกสารก่อนหน้า: `2026-08-26_wp03f-rebaseline-summary.md` และ `..._verdict-table.md`
(สถานะหลังปิด C1-C5 ซึ่งยังเหลือ MAJOR 30 หน้า)

## หัวข้อเดียวที่ต้องจำ

**หน้าที่มีคู่ใน CI3 ทั้ง 64 หน้า มีหัวข้อหน้าและหัวตารางตรง CI3 ครบแล้ว และทั้ง 65 หน้าไม่ล้นแนวนอนที่ 390px**

| ตัวชี้วัด | ก่อน wp03f | หลัง wp03f | หลัง wp03g (ชุดนี้) |
|---|---|---|---|
| หัวข้อหน้า + หัวตาราง ตรง CI3 | - | 6/64 | **64/64** |
| `scrollWidth` ที่ viewport 390 เท่ากับ CI3 | 0/65 | 61/65 | **65/65** |
| verdict MAJOR (เกณฑ์ WP-03E) | 54 | 30 | ดูหัวข้อ "ข้อจำกัดของการวัดชุดนี้" |

หน้าที่ 65 คือ `rating-form` ซึ่งไม่มีคู่ใน CI3 (CI3 `Rating::index()` redirect ทิ้ง) — เป็น
`CORRECT_AND_REBASELINE` ที่ user อนุมัติไว้แล้วตั้งแต่ WP-03E จึงไม่นับเข้าตัวหาร

## สิ่งที่ปิดใน wp03g

### G1 หน้า public และ auth (6 หน้า)

root cause ของทั้งกลุ่มคือ `public.css` ใช้ flex `.row` และไม่มี `box-sizing: border-box`
ขณะที่ `main.css` ถูกเขียนมาบน bootstrap ของ CI3 — แก้ shim จุดเดียวได้ทั้งบล็อกกึ่งกลาง
ปุ่มอยู่แถวเดียว และ overflow 4 หน้าสุดท้าย

- `contact-form-en/th` เขียนใหม่ตามโครง CI3: บล็อก REPAIR CENTER / CUSTOMER RELATION
  พร้อมที่อยู่และเบอร์โทร ปุ่ม Google Map และฟอร์ม MORE INFOMATION (ข้อความคัด CI3 คำต่อคำ รวม typo)
- `tracking-home-th` ปุ่มเป็น `วิธีตรวจสอบสถานะ` / `ติดตาม`
- `login` ได้แบนเนอร์ `bg-login.jpg`, wordmark `Tracking` และ **ลิงก์ Forgot Password ที่หายไปทั้งระบบ**
- `forgot-password` และ `reset-password` ได้แบนเนอร์ชุดเดียวกัน

### G2 order (13 หน้า)

- listing 6 คิว: หัวข้อ ชื่อรายการในการ์ด และชื่อคอลัมน์ย้ายเข้า `Order::PROFILES` คัดจาก CI3 verbatim
  รวมความไม่สม่ำเสมอของ CI3 เอง (`TrackID` คิว 1/5, `trackID` คิว 2-4, `Track Id` คิว 7)
- ปุ่ม `Add New` และช่วงวันที่ `from Date` / `To Date` บนคิว 1, ปุ่ม action เป็นไอคอน Edit/Delete/Print
- `OrderStore::listing()` รับ `edate` แล้วกรองเป็นช่วง (`sdate` เดี่ยวยังเป็นหน้าต่างวันเดียวเหมือนเดิม)
- `Order::delete()` คืน header CSRF ใหม่ทุก status เพื่อให้ลบต่อเนื่องได้ (pattern เดียวกับที่ t8 พิสูจน์กับ Users)
- ฟอร์ม add/edit และใบพิมพ์ได้หัวข้อ CI3 (`NEW REQUEST REPAIR`, `Enter Request order Details`,
  `Urgent/ซ่อมด่วน`) และใบพิมพ์แยกเซลล์หัวตารางของ `ใบรับซ่อม` พร้อมปุ่มพิมพ์ที่มีชื่อ

### G3 report (11 หน้า)

- `Reports::HEADINGS` เป็น tuple `[title, caption, sectionTitle]` ตาม CI3
- `report-summary` / `export-summary` ขยายจาก 8 เป็น **26 คอลัมน์** ตาม CI3 ครบ
- `report-ratings` / `export-ratings` รื้อเป็นโครง CI3: 8 บล็อกคำถามพร้อม Total และเปอร์เซ็นต์ต่อคะแนน
  (add_id 5-8 คือข้อย่อย 5.1-5.4) และหัวข้อ `6. ข้อเสนอแนะเพิ่มเติม` พร้อมตาราง `No` / `Note`
  ที่อ่านจาก `rating_comment`

### G4 history และ contact (3 หน้า)

- คืนคอลัมน์ `Session Data` ที่หายไป (ตรวจแล้วว่าเก็บ JSON ของ role/GroupID/BranchID
  ไม่มี session id หรือ token จึงแสดงได้), ช่องค้นหา และ pager ตัวเลข
- `contact-listing` กลับไปใช้คอลัมน์ CI3 ครบตาม decision ของ user

### งานพ่วง — heading sweep ทั้งระบบ

`layout.php` ย้าย subtitle เข้าไปใน `<h1>` แบบ CI3 และ `MasterCatalog::heading()` ถือข้อความ CI3
verbatim ของ master ทั้ง 10 type ทำให้ ~30 หน้าพลิกพร้อมกัน

## ความต่างที่เหลือและเป็น decision ไม่ใช่งานค้าง

| เรื่อง | เหตุผล |
|---|---|
| คิว 1 และ 5 มีฟอร์ม bulk (select-all + checkbox + Send) ที่ CI3 ไม่มี | ฟอร์มนี้ task ก่อนหน้าเพิ่มและอนุมัติแล้ว ถอดออกแล้วผู้ใช้ต้องติ๊กเอง 50 แถว จึงคงไว้แล้วย้าย select-all ลงไปข้างปุ่ม Send เพื่อให้หัวคอลัมน์ตรง CI3 |
| คิว 2-4 ไม่มีลิงก์ Edit/Print ในตาราง | ตาม CI3; route `/orders/{id}` และ `/print` ยังเข้าได้ทาง URL ตาม decision ของ wp03f |
| ฟิลด์เกินในฟอร์ม master (`book order`, `bunber_limit`, `_th` ของ background) | decision ของ t7: ถอดจากฟอร์มแล้วแก้ค่าไม่ได้ |
| `rating-form` ไม่มีคู่ CI3 | `CORRECT_AND_REBASELINE` อนุมัติแล้ว |

## ข้อจำกัดของการวัดชุดนี้ (อ่านก่อนใช้ตัวเลข)

- ตัวชี้วัด **64/64 วัดสองแกน**: ข้อความหัวข้อ (`h1`-`h3`) และข้อความหัวตาราง (`th`) ของทั้งสองระบบ
  เทียบกันตรงตัว **ไม่ได้แปลว่าทุกหน้าเป็น MATCH ตามเกณฑ์ WP-03E** ซึ่งตัดสินจากภาพเต็มหน้า
- ยังไม่ได้ไล่ดูภาพ 130 ใบทีละใบเทียบ CI3 ในรอบนี้ — ที่ตรวจด้วยตาคือหน้าตัวอย่างระหว่างทำงาน
  (contact, tracking-home, login, order listing คิว 1, master listing/edit, report summary, report ratings)
- ความต่างระดับสกินที่รู้ว่ายังมี: ตำแหน่งและขนาดของช่องค้นหาในการ์ด, สีและขนาดปุ่ม action,
  ระยะห่างของฟอร์มสองคอลัมน์ในหน้า master/order เทียบกับ CI3
- **ปุ่ม export ยังไม่ได้กดจริง** ทั้งสองระบบ จึงไม่มีหลักฐานว่าไฟล์ที่ได้ตรงกัน
- ฟิลด์ที่ CI3 มีในฟอร์ม order แต่ CI4 ยังไม่มี (`request Date`, `Branch Type`, `branch short`)
  **ยังไม่ได้เพิ่ม** — เป็นงานที่ต้องมี spec เพราะแตะ controller/store

## หลักฐานและวิธีทำซ้ำ

gate ที่รันจริงบน source ชุดสุดท้าย

```
composer test                       OK (311 tests, 4484 assertions)
php vendor/bin/phpstan analyse       [OK] No errors
bash scripts/ci-check.sh             EXIT=0
curl http://127.0.0.1:18405/health   {"status":"ok","service":"ci4"}
```

ขั้นตอนถ่ายภาพเหมือนรอบ wp03f: rebuild service `ci4` เป็น production ด้วย override นอก repo
ที่ปิด `app_forceGlobalSecureRequests` และ `cookie_secure` (ไม่งั้นได้ `307` และ `500`)
แล้วคืนเป็น development เมื่อเสร็จ; Docker diff container/network/volume = 0 ตลอดทั้งรอบ

**กับดักที่เจอรอบนี้**: `/loginMe` มี rate limit จริง — สคริปต์ probe ที่ล็อกอินซ้ำหลายรอบติดกัน
ทำให้ได้ `429` แล้วภาพชุดนั้นกลายเป็นหน้า 401 ทั้งหมด ถ้าเจอ ให้รอหน้าต่าง rate limit หมุน
(ประมาณ 15 นาที) แล้วถ่ายใหม่ อย่าตีความว่าเป็น regression ของหน้า
