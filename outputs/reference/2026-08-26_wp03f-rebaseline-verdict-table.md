# WP-03F re-baseline — ตารางผลเทียบภาพ CI3 vs CI4 ราย 65 หน้า

วันที่ 2026-08-26 · CI3 pin `ee1c95e` (ตัวเดียวกับ WP-03E ไม่เปลี่ยน) · CI4 sha `2831034`
Chromium `151.0.7922.34` · viewport `1440x900` และ `390x844`

ชุดนี้ถ่ายใหม่เฉพาะฝั่ง CI4 (130 ภาพ ที่ `evidence/wp03f-visual/`) แล้วเทียบกับภาพ CI3 ชุดเดิม
ที่ `evidence/wp03e-visual/` ซึ่งใช้ซ้ำได้เพราะ pin ไม่เปลี่ยน

## หัวข้อเดียวที่ต้องจำ

**parity ยังไม่ 100 เปอร์เซ็นต์** — MAJOR ลดจาก 54 เหลือ 30 หน้า และ MATCH ยังเป็น 0
งานที่ปิดไปคือ layout/theme (C1), เมนูล้นจอ (C2), label ดิบ (C3), แยกหน้า add/edit ของ master data (C4)
และ C5 บางส่วน ส่วนที่ยังเหลือคือ **หน้า public/auth ทั้งกลุ่ม, ฟอร์มและ listing ของ order, และ report ทุกหน้า**

| verdict | wp03e (ก่อนแก้) | wp03f (ชุดนี้) |
|---|---|---|
| MATCH | 0 | 0 |
| MINOR | 1 | 33 |
| MAJOR | 54 | 30 |
| BEHAVIOR | 10 | 1 |
| CI4-only (อนุมัติ CORRECT_AND_REBASELINE แล้ว) | - | 1 |

## วิธีตรวจของแต่ละหน้า (basis)

ตารางท้ายเอกสารระบุ basis ต่อหน้าไว้ตรง ๆ ห้ามอ่านว่าทุกหน้าถูกเปิดดูภาพครบ

| รหัส | ความหมาย |
|---|---|
| `V` | เปิดดูภาพจริงทั้งสองระบบ |
| `D` | DOM inventory probe เทียบ heading / หัวตาราง / ปุ่ม / input / ลิงก์ ของทั้งสองระบบ |
| `M` | วัด `document.documentElement.scrollWidth` ที่ 390x844 ทั้งสองระบบ (ทำครบทั้ง 65 หน้า) |

คำสั่งที่ใช้เก็บหลักฐานอยู่ในไฟล์สรุป `2026-08-26_wp03f-rebaseline-summary.md`

## ผลวัด mobile overflow (ทำครบ 65 หน้า)

C2 ปิดจริงเกือบทั้งระบบ: 61 จาก 65 หน้าของ CI4 วัดได้ `390` ตรงกับ CI3 พอดี
เหลือ 4 หน้าที่ยังล้นเล็กน้อย ทุกหน้าอยู่ในกลุ่ม public

| หน้า | CI3 | CI4 |
|---|---|---|
| tracking-home-en | 390 | 413 |
| tracking-home-th | 390 | 413 |
| tracking-result-en | 390 | 412 |
| tracking-result-th | 390 | 412 |

เทียบกับก่อนแก้ที่หน้าหลัง login กว้าง ~3313 px ถือว่า class C2 ปิดแล้ว เหลือเป็นเศษของหน้า public

## ตารางเต็ม 65 หน้า

| batch | page-id | desktop | mobile | verdict | ข้อเสนอ | basis |
|---|---|---|---|---|---|---|
| 1 | tracking-home-en | MAJOR | MAJOR | MAJOR | FIX_CI4 | V D M |
| 1 | tracking-home-th | MAJOR | MAJOR | MAJOR | FIX_CI4 | V D M |
| 1 | tracking-result-en | MINOR | MINOR | MINOR | FIX_CI4 | V D M |
| 1 | tracking-result-th | MINOR | MINOR | MINOR | FIX_CI4 | V D M |
| 1 | contact-form-en | MAJOR | MAJOR | MAJOR | FIX_CI4 | V D M |
| 1 | contact-form-th | MAJOR | MAJOR | MAJOR | FIX_CI4 | V D M |
| 1 | login | MAJOR | MAJOR | MAJOR | FIX_CI4 | V D M |
| 1 | forgot-password | MAJOR | MAJOR | MAJOR | FIX_CI4 | V D M |
| 1 | dashboard | MINOR | MINOR | MINOR | FIX_CI4 | V D M |
| 1 | change-password | MINOR | MINOR | MINOR | FIX_CI4 | V D M |
| 2 | rating-form | CI4-only | CI4-only | CI4-only | CORRECT_AND_REBASELINE (อนุมัติแล้ว) | D M |
| 2 | reset-password | BEHAVIOR | BEHAVIOR | BEHAVIOR | CORRECT_AND_REBASELINE (อนุมัติแล้ว) | D M |
| 2 | orders-new | MAJOR | MINOR | MAJOR | FIX_CI4 | V D M |
| 2 | orders-edit | MAJOR | MINOR | MAJOR | FIX_CI4 | V D M |
| 2 | orders-print | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 2 | order-listing-status1 | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | V D M |
| 2 | order-listing-status2 | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 2 | order-listing-status3 | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 2 | order-listing-status4 | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 2 | order-listing-status5 | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 2 | order-listing-status7 | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 2 | report-tracking-listing | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 3 | master-branch-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | V D M |
| 3 | master-branch-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | V D M |
| 3 | master-branchtype-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-branchtype-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-statustype-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-statustype-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-producttype-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-producttype-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-book-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-book-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-brand-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | V D M |
| 3 | master-brand-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | V D M |
| 3 | master-condition-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-condition-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-estimateprice-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-estimateprice-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-fixed-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-fixed-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-provider-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 3 | master-provider-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 4 | imports-status | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 4 | imports-price | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 4 | imports-new-order | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 4 | login-history-own | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 4 | users-history-of-user | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 4 | contact-listing | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | NEED_USER | D M |
| 4 | menu-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | V D M |
| 4 | menu-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 4 | background-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 4 | background-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | NEED_USER | D M |
| 4 | users-listing | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | V D M |
| 4 | users-edit | MINOR | ไม่ได้ตรวจภาพ | MINOR | FIX_CI4 | D M |
| 5 | report-ratings | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 5 | report-jobs-by-day | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 5 | report-pending | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 5 | report-pending-total | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 5 | report-in-progress-average | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 5 | report-in-progress | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 5 | report-summary | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | V D M |
| 5 | export-ratings | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 5 | export-in-progress | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 5 | export-tracking | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |
| 5 | export-summary | MAJOR | ไม่ได้ตรวจภาพ | MAJOR | FIX_CI4 | D M |

## ข้อควรระวังของ DOM probe (อ่านก่อนใช้ตัวเลข)

สามอย่างนี้เป็น artifact ของวิธีวัด ไม่ใช่ความต่างจริง อย่านับเป็น finding

- **`rowCount` ของ CI3 มากกว่า CI4 หนึ่งเสมอในหน้า listing** — ตาราง CI3 นับแถวหัวตารางเข้า `tbody` ด้วย
- **ปุ่มของ CI3 โผล่มาเป็นชื่อ class เช่น `btn btn-sm btn-default searchList`** — เป็นปุ่มค้นหาแบบไอคอนที่ไม่มีข้อความ
  คู่ของมันใน CI4 คือปุ่มที่มีข้อความ `Search`
- **`submit` / `reset` ที่ "หายใน CI4"** — CI3 ใช้ `<input type="submit">` / `<input type="reset">`
  ส่วน CI4 ใช้ `<button>` ปุ่มมีอยู่จริงทั้งคู่ (ยืนยันด้วยภาพในหน้า master และ change-password)

ส่วน `Add New` และ `Edit` ที่รายงานว่า "หายจากปุ่ม แต่เกินในลิงก์" คือของจริงที่ยังอยู่ แต่ CI4 render
เป็นลิงก์ข้อความหลังงาน cleanup ที่ถอด `class="btn"` ออก — นับเป็นความต่างเชิงสายตา ไม่ใช่ฟังก์ชันหาย
