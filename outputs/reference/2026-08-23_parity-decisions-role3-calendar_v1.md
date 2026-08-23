# Parity Decisions: Role 3 Write + Buddhist Calendar (v1)

บันทึกการตัดสินใจของ Business (2026-08-23) 2 ข้อ พร้อมหลักฐาน implement — ทั้งคู่เลือก parity ตามพฤติกรรมจริงของ CI3

## Decision 1: Role 3 เขียน/ลบได้ตาม CI3 (PRESERVE)

- **Before**: CI4 scaffold ปิด role 3 เป็น read-only (`AuthorizationPolicy::ACTIONS_BY_ROLE`) ทั้งที่ CI3 จริงทุก role เขียนได้ เพราะ `isAdmin()` เป็น dead code (`role < 1` ไม่มีทางจริง)
- **Decision**: Business เลือก "เปิดตาม CI3" — role 3 ได้ read/write/delete; branch isolation (IDOR fix) คงเดิมทุกจุด ไม่ถูกย้อน
- **Change**: `app/Authorization/AuthorizationPolicy.php` เพิ่ม write/delete ให้ role 3; `app/Controllers/MasterData.php` ถอด gate admin-only ที่ CI3 ไม่มี; flip tests 4 ตัว (policy matrix, filter write route, own-branch mutate, user cross-branch create ยังต้อง 422)
- **After**: `composer test` → `OK (127 tests, 1792 assertions)`; cross-branch + escalation guard ยังยืนตาม test เดิม (`UserHttpTest` 404/422 ครบ)

## Decision 2: ปฏิทิน พ.ศ. ทั้ง EN และ TH ตาม CI3 (PRESERVE รวม defect)

- **Before**: CI4 โชว์ raw datetime; CI3 แปลง `dd/mm/(ปี ค.ศ.+543)` ทั้งสองภาษา (หน้า EN ก็เป็น พ.ศ.)
- **Decision**: Business เลือก "พ.ศ. ทั้งคู่ตาม CI3" — parity เป๊ะรวมพฤติกรรมที่หน้า EN แสดงปีพุทธศักราช
- **Change**: `app/Views/tracking.php` แปลง `occurred_at` เป็น `d/m/` + ปี+543 ทั้งสองภาษา (attribute `datetime` คง ISO เดิม); test ยืนยัน `05/08/2569` โผล่ทั้งหน้า EN และ TH
- **After**: `PublicTrackingHttpTest` เขียว; gate เต็ม exit 0

## Traceability

- คำถาม-คำตอบอยู่ในบทสนทนา session 2026-08-23 (AskUserQuestion 4 ข้อ — อีก 2 ข้อคือ audit table ของ WP-03D และการเริ่มสร้าง views ตาม UX CI3 จะมี record แยกเมื่อ implement)
- Gap analysis ที่นำมาสู่การตัดสินใจ: `outputs/reference/2026-08-23_phase2-3-parity-gap_v1.md`
