# Brief breaker correction Task 7

Implement รอบ 6 หลัง rework cap 5/5 แก้ load-bearing residualสองรายการครั้งเดียว หาก final reviewยังพบ load-bearing residualให้หยุดเป็น `BLOCKED` ห้ามวนแก้ต่อ

## เอกสารที่ต้องอ่าน

1. `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix5-re-review.md`
2. current adapterและ adapter harness
3. exact `script.js`, jQuery 1.10.2, Widget, File Upload และ Knob source

## Exact DOM normalization

Exact fragmentเป็น top-level hidden `INPUT` และ `LI` แยกกัน ทำให้ `tpl.find('input')` ไม่พบ root input

- ใน original A context setter ก่อน Exact statementถัดไป ให้ย้าย top-level progress inputเข้า preview `LI`
- ใช้ exact real context collectionที่ setterรับมา ไม่สร้าง replacement markup
- หลัง normalization local `tpl.find('input')` และ completion B bridge `.find('input')` ต้องพบ elementเดียวกัน
- Knob initializationและ progress updateต้องแตะ previewจริง
- queue marker/deleteยัง bindกับ preview `LI`
- missing malformed contextต้องไม่ throw
- ห้ามแก้ Exact assets

## Terminal state

Stateต่อ operationต้อง settleครั้งเดียว

- สถานะขั้นต่ำ: pending, success, rejected, failed
- `fileuploaddone` outcomeแรกเท่านั้นที่ตัดสิน success/rejected
- over-limit และ `result.status !== 'success'` เป็น terminal rejected; doneซ้ำห้ามเปลี่ยนเป็น success
- `fileuploadfail`/abortเป็น terminal failed; doneภายหลังห้ามเปลี่ยน queue
- failก่อน real contextมา ต้องบันทึก UI outcome
- เมื่อ contextมาภายหลัง `bindContext()` replay terminal UI: remove `working`; failed/rejectedเพิ่ม `error`; successคง success state
- repeated fail/doneต้อง idempotentและไม่เพิ่ม queueซ้ำ
- pending cancelก่อน terminalไม่ลบ completed operationอื่น

## Parked residual

Reassigned contextทิ้ง markerบน old contextไม่ใช่ reachable Exact caller ห้ามขยาย scopeแก้ เว้นแต่การแก้ load-bearingทำให้แก้ได้ฟรีโดยไม่เพิ่ม branch/test complexity

## Harness

ต้องใช้ Exact DOM shapeสอง rootsหรือ actual jQuery DOM และ Exact callback ordering

RED/GREEN บังคับ:

1. top-level INPUT + LI ก่อน normalization
2. `tpl.find('input')` จาก 0 เป็น 1หลัง setter
3. knob/progressหลัง contextแตะ inputจริง
4. fail-before-context แล้ว contextมา: `working=false`, `error=true`
5. over-limit doneแล้ว done successซ้ำ: queueไม่เปลี่ยนและ itemยังไม่มี
6. error-result doneแล้ว done successซ้ำ: queueยัง 0
7. failแล้ว done: queueยัง 0
8. successแล้ว repeated done/fail: queue occurrenceไม่เพิ่มหรือลดผิด
9. operation marker, same File occurrence, multi-file group, duplicate/interleave regressionsเดิมยังผ่าน

## Verification

- focused actual-shape adapter harness
- focused upload server/DOM tests
- full `OrderHttpTest.php`
- full PHPUnit, PHPStan, full CIบน exact temporary candidate 21 pathsและ route patchเดิม
- exact CI3 hashes 9/9
- real Git indexไม่เปลี่ยน

Authenticated browser matrixยัง `BLOCKED`; ห้ามอ้าง browser PASS

## Report

เขียนภาษาไทยที่:

`.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-breaker-fix-report.md`

ตอบสั้น: status, files, DOM/terminal design, RED/GREEN matrix, full gates, residual concerns
