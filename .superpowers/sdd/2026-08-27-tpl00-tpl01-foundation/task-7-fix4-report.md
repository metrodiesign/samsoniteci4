# รายงานแก้ Task 7 รอบ 4

เอกสารนี้สรุปการแก้ clone boundary ที่ original add data และหลักฐาน TDD, automated gate, exact asset และ real Git index สำหรับ Task 7 fix round 4

## สถานะ

| แกน | ผล |
|---|---|
| Architecture correction | ผ่าน |
| Focused adapter regression | ผ่าน |
| Focused upload server tests | ผ่าน |
| `OrderHttpTest.php` | ผ่าน |
| Full PHPUnit บน exact temporary candidate | ผ่าน |
| PHPStan | ผ่าน |
| Full `scripts/ci-check.sh` | ผ่านบน exact temporary candidate |
| Exact CI3 assets | ตรงครบ 9 files |
| Authenticated browser matrix | `BLOCKED` |
| สถานะรวม | `DONE_WITH_CONCERNS` |

## Root cause และ hook ที่ใช้

Exact source ยืนยันลำดับดังนี้:

1. `jquery.fileupload.js` สร้าง original add data A และเรียก `_trigger('add', e, newData)`
2. `jquery.ui.widget.js` เรียก `this.element.trigger(event, data)` ก่อน option callback ผ่าน `callback.apply(...)`
3. Event handler `fileuploadadd` จึงเห็น A ก่อน Exact `script.js` option callback เรียก `data.submit()`
4. `_getAJAXSettings()` สร้าง shallow clone B ด้วย `$.extend({}, this.options, data)`
5. `fileuploaddone` รับ B ขณะที่ Exact `FileReader.onload` กำหนด `A.context`

แก้ที่ `app/Views/partials/order_upload.php` โดยใช้ `fileuploadadd` เป็น original-data boundary:

- สร้าง state ต่อ File object identity ด้วย `WeakMap`
- ติด context observation บน A ก่อน option callback เริ่ม `FileReader` และเรียก `submit()`
- กำหนด property เป็น enumerable เพื่อให้ early context ถูก shallow-copy ไป B ตาม plugin semantics
- ให้ `fileuploaddone` ใช้ File object ที่ A และ B share กันเพื่อหา state เดิม
- สร้าง queue item หนึ่งครั้ง แล้ว bind กับ early หรือ late original context เดียวกัน
- ไม่ใช้ filename identity, timeout, polling หรือ global filename map
- ไม่แก้ Exact CI3 assets และไม่เพิ่ม dependency

## TDD

### RED

แก้ regression harness ก่อน production code ให้สร้าง A และ shallow clone B เป็นคนละ object แต่ share File identity แล้วกำหนด late context บน A

```text
Error: REAL_CLONE_LATE_QUEUE=1
Tests: 1, Assertions: 4, Failures: 1.
```

ผลนี้หมายถึง pre-fix adapter ติด setter บน B จึงไม่เห็น late assignment บน A และ File ยังเหลือใน final queue 1 รายการ

### GREEN

หลังย้าย observation ไป original `fileuploadadd` boundary:

```text
OK (1 test, 4 assertions)
```

Harness รัน production inline adapter จริงและครอบกรณีต่อไปนี้:

| กรณี | หลักฐานที่ตรึง |
|---|---|
| Early context | A มี context ก่อนสร้าง B และ clicked delete ทำให้ queue ว่าง |
| Completion before context | B completion มาก่อน แล้ว A รับ context ภายหลังและ delete ได้ |
| Multiple และ repeated selection | File คนละ object เข้า queue ตาม completion order |
| Duplicate names แบบ interleaved | bind และ delete ตาม File identity ไม่ใช่ชื่อไฟล์ |
| Failure และ abort | ไม่เพิ่ม queue item |
| Pending cancel | ไม่ลบ completed File อื่น |
| Missing context | ไม่ throw และไม่ cross-bind กับ preview ชื่อซ้ำ |
| Repeated completion | สร้าง queue item ครั้งเดียว |
| Clicked preview delete | ลบ File ที่ผูกกับ preview นั้นโดยตรง |

## Verification

| Gate | ผล |
|---|---|
| Focused adapter regression | `OK (1 test, 4 assertions)` |
| Focused upload server regressions | `OK (6 tests, 90 assertions)` |
| `OrderHttpTest.php` | `OK (74 tests, 1235 assertions)` |
| Full PHPUnit บน candidate | `OK (426 tests, 8982 assertions)` |
| PHPStan บน candidate | `[OK] No errors` |
| Full `scripts/ci-check.sh` บน candidate | PASS ทุก gate |
| Candidate manifest | `PACKAGE_PATHS_EXACT=true`, `PACKAGE_COUNT=21` |
| Route scope | ใช้ `task-7-route.patch` เดิม 1 hunk |
| Exact CI3 asset SHA-256 | `MATCH` 9/9 |
| PHP syntax | `order_upload.php` ผ่าน |
| Real Git index | tree เดิมและ cached diff ว่าง |

Full CI รอบแรกใน sandbox ล้มที่ข้อความต้นฉบับ:

```text
grep: .env.example: Operation not permitted
FAIL: CI4 loopback port placeholder is missing
```

ข้อความนี้หมายถึง sandbox ปิดการอ่าน `.env.example` ไม่ใช่ source failure เมื่อรันคำสั่งเดิมนอก sandbox บน exact temporary candidate จึงผ่านทุก gate จนถึง `PASS repository safety gate`

## Exact CI3 assets

| Asset | SHA-256 |
|---|---|
| `public/assets/css/style.css` | `a0ca03a6569a9520ea1aaac734cfcb114d9418475eec43eae41201d1c65050b6` |
| `public/assets/img/icons.png` | `8e729e7a5839f3cb37c416b51461501f1bffcfc290ca973dd2b3cbbf5bcd24dd` |
| `public/assets/js/addOrder.js` | `86fdf03e7cdbf2bfb66fde74cee6374cbc24cdea2395f9b9d2e63caad1bb89e0` |
| `public/assets/js/admin_addOrder.js` | `4b07a289e72973be7f60963ff9156d70eedbe7adcd1779d38ccd0bfae5f33b42` |
| `public/assets/js/browse/jquery.knob.js` | `9a9bcdeb2150048832cd9c5b6f56db8e20e2ade75a60ca1eb014ad49b9b65c16` |
| `public/assets/js/browse/jquery.ui.widget.js` | `95694c8567c94e0bcdff9fa4711be1d0060509931b8d19b450109b8552a8ef71` |
| `public/assets/js/browse/jquery.iframe-transport.js` | `0ddd3dc005842bd02b0bba0fa65951f4b64714504c887af0dfcbd97f390325c4` |
| `public/assets/js/browse/jquery.fileupload.js` | `912fd62966a08f15145b4aefcac50e45893dfb5732869ec658b48ac1362ebb07` |
| `public/assets/js/browse/script.js` | `9a455e73fb66fe42f287f22cd96065e6f65039992a10ca687ce05df4dc8101ec` |

## ไฟล์ที่แก้

- `app/Views/partials/order_upload.php`
- `tests/ci4/OrderHttpTest.php`
- `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix4-report.md`

ไม่มี real stage, commit, push, browser credential หรือ shared DB mutation และ real Git index ยังเป็น tree `c6ce38a8953cb1dedf08e35446b3195347139425`

## Concern ที่เหลือ

Authenticated browser matrix ยัง `BLOCKED` ตามข้อจำกัด credential และ shared DB จึงไม่อ้าง browser PASS สำหรับ native file select, drag/drop, FileReader timing, progress knob, in-flight abort, post-completion delete หรือ final create/edit submit
