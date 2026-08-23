# Business decisions Phase 4-6 (2026-08-23)

คำตอบ 8 ข้อจาก user (ผ่าน AskUserQuestion ใน session migration) ปลดบล็อกการ implement Phase 4-6 — อ้างคำถามจาก `2026-08-23_phase4-6-parity-gap_v1.md` หัวข้อ "คำถามที่ต้องการคำตอบก่อน implement"

## คำตัดสิน

| # | ประเด็น | คำตัดสิน | ผลต่อ implement |
|---|---|---|---|
| 1 | WP-06A report semantic | เขียน 4 report ใหม่ตาม CI3 (jobs-by-day = SLA matrix, pending / pending-total แยกชนิด, in-progress-average = status bucket + multi-status filter) | งาน large — `ReportMatrix` เขียนใหม่ตาม `User.php:640-661` ของ CI3 |
| 2 | Status 6/7 + SMS trigger | คืน flow เต็มตาม CI3 — transition matrix ครบ + SMS 3 จุด (create, return, complete+rating link) ส่งผ่าน queue loopback จนกว่า transport จริงพร้อม | WP-04B + WP-04C |
| 3 | Print order | Parity เต็มตาม CI3 (`print_order.php` ~1,260 บรรทัด) ตาม AC-ORD-011 | WP-04B |
| 4 | Tracking ID format | กลับไป branch-prefixed ตาม CI3 (เลิกใช้ `G{yy}{mm}{seq4}` กลาง) | WP-04A — แก้ generator + test; atomic FOR UPDATE คงเดิม |
| 5 | SMS vendor | ThaiBulkSMS เดิมตาม CI3 — implement production transport ตาม API เดิม, credential อ่านจาก env ตอน deploy, test ด้วย stub | WP-04C |
| 6 | File retention import | เก็บไฟล์จริงใน private volume (นอก document root + access control) เพิ่มจาก SHA-256 hash ที่มีอยู่ | WP-05C |
| 7 | Import contract | ต้องรับ `.xls` legacy ด้วย (เพิ่ม reader); cap 501 แถว / 5 MB คงไว้ตามที่ CI4 ตั้ง (user ไม่ได้แย้ง cap) | WP-05A |
| 8 | UNIQUE index `trackID` + `(branchID,numberID)` | ทำ — ผูกเข้า Gate 1D re-provision (ตรวจ duplicate เดิมก่อนเพิ่ม index) | Schema migration ใน Gate 1D (ยังรอ dump ใหม่จาก user) |

## หมายเหตุ

- ข้อ 2 และ 5 ประกอบกัน: trigger ครบ 3 จุดทำได้ทันทีบน loopback ส่วน transport ThaiBulkSMS ต่อเมื่อมี credential (env) — ห้าม hardcode/commit credential ทุกกรณี
- ข้อ 8 ติด dependency: dump ใหม่สำหรับ Gate 1D ยังไม่ได้จาก user (`DUMP_DIR` ว่าง)
- ลำดับ implement เสนอ: WP-04A (เล็ก) → WP-06B memory ceiling (เล็ก ไม่ติด decision) → WP-05A `.xls` → WP-05C file storage → WP-04B status 6/7 + print (large) → WP-04C SMS transport → WP-06A reports (large)
