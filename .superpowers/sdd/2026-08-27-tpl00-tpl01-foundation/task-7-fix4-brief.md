# Brief แก้ Task 7 รอบ 4

แก้ clone-boundary root cause หลัง fix 3 รอบไม่ปิด finding ห้ามต่อยอด setterบน completion objectเดิม

## เอกสารที่ต้องอ่าน

1. `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix3-re-review.md`
2. `app/Views/partials/order_upload.php`
3. exact plugin `public/assets/js/browse/jquery.fileupload.js` โดยเฉพาะ add, `_getAJAXSettings()` และ done event
4. exact glue `public/assets/js/browse/script.js`
5. production adapter regressionใน `tests/ci4/OrderHttpTest.php`

## Root cause ที่ยืนยันแล้ว

- Exact callback `add` รับ original data object A
- `data.submit()` สร้าง shallow clone B สำหรับ AJAX/done event
- Exact `FileReader.onload` กำหนด `A.context`
- `fileuploaddone` รับ B
- setterบน B จึงไม่มีทางเห็น late assignmentบน A

## Contract

- ดัก original dataที่ `fileuploadadd` ก่อน Exact option callbackเรียก `submit()`
- สร้าง stateต่อ File object identityบน original data ไม่ใช้ filename
- ติด context observationบน original A ก่อน callbackเริ่ม asynchronous FileReader
- `fileuploaddone` หา stateจาก File object identityที่ shallow clone Bยังอ้าง objectเดิม
- completionสร้าง queue itemครั้งเดียวและ bindกับ early/late original contextเดียวกัน
- failure/abortไม่เพิ่ม queue
- pending cancelไม่ลบ completed Fileอื่น
- ครอบ single, multiple, repeated, duplicate names และ interleaved completion
- contextไม่มาได้โดยไม่ throwหรือ cross-bind
- ห้าม arbitrary timeout, polling, global filename map หรือแก้ exact CI3 assets

ตรวจ event orderingจาก plugin/jQuery Widget sourceจริง ห้าม assumeว่า `fileuploadadd` eventเกิดหลัง option callback ถ้าหลักฐาน sourceขัดให้เลือก hookขั้นต่ำอื่นบน original dataและบันทึกเหตุผล

## TDD

Harnessต้องจำลอง production semantics:

1. original add data A
2. shallow completion clone B แยก objectแต่ share File identity
3. early context branch
4. completion-before-context branch
5. interleaved duplicate-name originalsและ clones
6. fail/abort
7. pending cancel
8. clicked deleteตรง preview identity

RED ต้องล้มกับ pre-fix adapterโดย `REAL_CLONE_LATE_QUEUE=1` หรือ equivalent; GREEN ต้องได้ queue 0หลัง deleteและไม่ regress branchอื่น

## Verification

- focused executable adapter regression
- focused upload server tests
- full `OrderHttpTest.php`
- full PHPUnit, PHPStan, full CIบน exact Task 7 temporary candidate 21 pathsและ route patchเดิม
- exact CI3 hashes 9/9
- real Git indexไม่เปลี่ยน

Browser authenticated matrixยัง `BLOCKED`; ห้ามอ้าง browser PASS

## Report

เขียนภาษาไทยที่:

`.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix4-report.md`

ตอบสั้น: status, files, root-cause hook, RED/GREEN, full gates, concerns
