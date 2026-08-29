# Brief แก้ Task 7 รอบ 5

รอบสุดท้ายของ rework cap แก้ Important 3 รายการจาก re-review รอบ 4 ด้วย operation-level stateและ stable context bridge ห้ามวนกลับไปใช้ WeakMap File slot

## เอกสารที่ต้องอ่าน

1. `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix4-re-review.md`
2. current `app/Views/partials/order_upload.php`
3. exact `jquery.ui.widget.js`, `jquery.fileupload.js`, `script.js`
4. adapter regressionใน `tests/ci4/OrderHttpTest.php`

## Architecture ที่ผูกงาน

### Operation state

- ที่ `fileuploadadd` original A สร้าง stateหนึ่งตัวต่อ add operation
- เก็บ stateบน propertyที่ enumerableและชื่อ scopeเฉพาะ เพื่อให้ jQuery shallow clone Bได้รับ object referenceเดียวกัน
- stateเก็บ File listทั้ง `data.files` ตาม occurrenceและลำดับ ห้าม keyด้วย filenameหรือ File object global slot
- same File object submitซ้ำต้องได้คนละ operation stateและคนละ queue occurrence
- completionซ้ำของ operationเดียวต้อง idempotent
- completion successเพิ่ม Fileทั้งหมดของ operationเข้า final queue
- clicked previewลบ operation/groupที่ bindกับ previewนั้นเท่านั้น

### Stable context bridge

- ก่อน Exact option callback ให้ `A.context` getterคืน stable jQuery object/referenceที่ B shallow-copyได้
- เมื่อ Exact callbackกำหนด real contextบน A ให้ retarget/mutate stable bridgeให้ jQuery methodsทำงานกับ real context โดย Bยังถือ referenceเดิม
- ก่อน real contextมา Exact progress/fail callbacksต้องไม่ throw; empty bridgeต้องรองรับ `.find(...)`, `.text(...)`, `.knob(...)`, `.addClass(...)`
- หลัง real contextมา progress/fail/doneต้องกระทบ previewจริง
- ห้าม timeout, polling หรือแก้ Exact assets

ใช้ jQuery object semanticsที่รองรับ dependency versionเดิม ห้ามสร้าง custom APIใหญ่ถ้าการ mutate jQuery collectionขั้นต่ำพอ

## Harness ที่บังคับ

โหลด production inline adapterและ Exact `script.js` หรือจำลอง `_trigger` ตรง sourceโดย event handlerก่อน option callback ต้องมี executable cases:

1. A/B shallow cloneคนละ objectและ share operation marker
2. early context
3. completion-before-context
4. progressก่อน contextไม่ throw
5. failก่อน contextไม่ throw
6. progress/failหลัง contextแตะ previewจริง
7. same File object submitซ้ำได้ queue 2 occurrencesและ deleteแยกกัน
8. one A with multiple filesเก็บครบและ deleteเป็น operation group
9. duplicate names/interleaved completion
10. missing context, pending cancel, fail/abort, repeated completion

RED ต้อง reproduce Important 1-3 กับ pre-fix code; GREEN ต้องปิดทุก case

## Verification

- focused adapter harness
- focused upload server tests
- full `OrderHttpTest.php`
- full PHPUnit, PHPStan, full CIบน exact Task 7 temporary candidate 21 pathsและ route patchเดิม
- exact CI3 hashes 9/9
- real Git indexไม่เปลี่ยน

Browser authenticated matrixยัง `BLOCKED`; ห้ามอ้าง browser PASS

## Report

เขียนภาษาไทยที่:

`.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix5-report.md`

ตอบสั้น: status, files, state/bridge design, RED/GREEN matrix, full gates, residual concerns
