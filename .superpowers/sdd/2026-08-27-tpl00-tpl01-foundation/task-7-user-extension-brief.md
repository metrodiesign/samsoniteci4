# Brief targeted rework Task 7 รอบ 6/6

ผู้ใช้อณุมัติขยาย capหนึ่งรอบเพื่อแก้ load-bearing findingเดียว: completed preview native clickลบ DOMก่อน delegated adapter handler ทำให้ Fileค้างใน final queue

## เอกสารที่ต้องอ่าน

1. `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-breaker-re-review.md`
2. current `app/Views/partials/order_upload.php`
3. exact `public/assets/js/browse/script.js:42-57`
4. current actual-shape/native-click harnessใน `tests/ci4/OrderHttpTest.php`

## Contract

- ใน context setter หลัง normalizeและก่อน Exact callback bind `.click(...)` ให้ bind queue-removal handlerตรงกับ preview span
- native handler orderต้องเป็น adapter direct handlerก่อน Exact direct handler
- completed preview clickต้องลบ operation/groupจาก final queueและ `target.files` ก่อน Exact handler remove DOM
- pending preview clickต้องไม่ลบ completed operationอื่น; Exact handlerยัง abort requestได้
- failed/rejected previewไม่มี queue itemและต้อง no-op
- delegated fallbackถ้าคงไว้ต้อง idempotentและห้ามลบผิด operation
- ครอบ single, duplicate names, same File occurrence, interleaved completion และ multi-file group
- ห้ามแก้ Exact assets, timeout, polling หรือ monkey-patch Exact click handler

## TDD

ใช้ actual/native bubbling semantics ไม่เรียก adapter handlerตรง

1. completion successสร้าง previewและ queue 1
2. native click span
3. Exact direct handler remove preview
4. assert queue 0, target files 0 และ adapter direct handlerถูกเรียกก่อน DOM removal
5. pending click assert abort 1และ completed queueอื่นไม่เปลี่ยน
6. group/duplicate/same identity assertionsเดิมยังผ่าน

RED ต้อง reproduce:

```text
queue=1 delegated=0 liInDom=false
```

GREEN ต้อง queue 0หลัง native clickแม้ previewถูก Exact handlerลบ

## Verification

- focused native-click adapter tests
- focused upload/auth/CSRF/association tests
- full `OrderHttpTest.php`
- full PHPUnit, PHPStan, full CIบน exact Task 7 candidate 21 pathsและ route patchเดิม
- exact CI3 hashes 9/9
- real Git indexไม่เปลี่ยน

Browser matrixรอ verifierใช้ disposable isolated DBหลัง code re-review ห้ามสร้าง fixtureใน implementerรอบนี้

## Report

เขียนภาษาไทยที่:

`.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-user-extension-report.md`

ตอบสั้น: status, files, event ordering, RED/GREEN, full gates, residuals
