# Brief แก้ Task 7 รอบ 3

แก้ Important finding เดียวจาก re-review รอบ 2: completion เกิดก่อน preview context ทำให้ clicked delete ไม่แตะ final queue

## เอกสารที่ต้องอ่าน

1. `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix2-re-review.md`
2. `app/Views/partials/order_upload.php`
3. exact `public/assets/js/browse/script.js` เฉพาะเพื่อเข้าใจ event timing ห้ามแก้ไฟล์นี้
4. regression harnessใน `tests/ci4/OrderHttpTest.php`

## Contract

- queue itemต้องสร้างและผูกกับ `data` หรือ File object identityทันทีเมื่อ `fileuploaddone` สำเร็จ แม้ `data.context` ยังไม่มี
- เมื่อ `FileReader.onload` สร้าง `data.context` ภายหลัง ต้อง bind queue itemกับ preview nodeเดียวกัน
- ห้าม pollด้วย arbitrary timeout
- ห้ามใช้ filenameเป็น identity
- pending cancelก่อน completionต้องไม่ลบ completed Fileตัวอื่น
- post-completion deleteต้องลบ Fileที่ผูกกับ clicked previewเท่านั้น
- ครอบ single, multiple, repeated selection และ duplicate names
- exact `script.js` bytesห้ามเปลี่ยน

## TDD

1. เพิ่ม executable RED regressionที่เรียก production inline adapterโดยลำดับ `fileuploaddone` ก่อน context
2. สร้าง contextภายหลังแล้ว click delete
3. RED ต้องแสดง final queueยังเหลือ File
4. แก้ production adapterขั้นต่ำ
5. GREEN ต้องพิสูจน์ queueว่างและ existing normal/duplicate/pending casesยังผ่าน

## Verification

- focused adapter tests
- `OrderHttpTest.php`
- focused upload server testsเพื่อกัน regression
- full PHPUnit
- PHPStan
- `scripts/ci-check.sh` บน exact Task 7 temporary candidate
- exact CI3 asset hashes
- real Git indexไม่เปลี่ยน

Browser authenticated matrixยังแยกเป็น `BLOCKED`; ห้ามอ้าง browser PASS

## Report

เขียนภาษาไทยที่:

`.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix3-report.md`

ตอบสั้น: สถานะ, files, RED/GREEN, full gates, concerns
