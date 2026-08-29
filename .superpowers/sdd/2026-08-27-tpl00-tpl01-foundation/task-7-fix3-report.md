# รายงานแก้ Task 7 รอบ 3

รายงานนี้สรุปการแก้ race ระหว่าง upload completion กับ preview context ด้วย TDD โดยรักษาการแก้รอบ 1-2, Exact CI3 assets และ real Git index เดิมไว้

## สถานะ

| แกน | ผล |
|---|---|
| Finding รอบ 3 | แก้แล้ว |
| Focused adapter regression | ผ่าน |
| Focused upload server tests | ผ่าน |
| `OrderHttpTest.php` | ผ่าน |
| Full PHPUnit | ผ่านบน exact temporary candidate 21 files |
| PHPStan | ผ่าน |
| Full `scripts/ci-check.sh` | ผ่านบน exact temporary candidate และ route patch เดิม |
| Exact CI3 assets | ตรงครบ 9 files |
| Authenticated browser matrix | `BLOCKED` ตามข้อจำกัดเดิม |
| สถานะรวม | `DONE_WITH_CONCERNS` |

## Root cause และการแก้

Root cause คือ `fileuploaddone` สามารถสำเร็จก่อน asynchronous `FileReader.onload` ใน Exact CI3 `script.js` กำหนด `data.context` ได้ Adapter เดิมจึงเพิ่ม File เข้า final queue แต่ไม่มีจุด bind queue item เมื่อ context มาถึงภายหลัง

แก้เฉพาะ shared production adapter ใน `app/Views/partials/order_upload.php`:

- สร้าง queue item และผูกกับ `data.orderQueueItem` ทันทีเมื่อ completion สำเร็จ
- ถ้ามี `data.context` แล้ว ให้ bind queue item ตาม path เดิม
- ถ้ายังไม่มี context ให้ใช้ synchronous property setter ดัก assignment แรกของ `data.context`
- เมื่อ context มาถึง ให้แทน setter ด้วย writable value แล้ว bind preview node กับ queue item เดิม
- ไม่ใช้ arbitrary timeout, polling หรือ filename identity
- ไม่เปลี่ยน `public/assets/js/browse/script.js`

การแก้อยู่ที่ shared partial เดียว จึงครอบ create/edit, central/branch และทุก caller ที่ใช้ adapter นี้

## TDD

### RED

เพิ่ม regression ใน `tests/ci4/OrderHttpTest.php` ซึ่ง render และรัน production inline adapter จริงตามลำดับนี้:

1. ส่ง `fileuploaddone` โดยยังไม่มี `data.context`
2. ยืนยัน File เข้า final queue แล้ว
3. สร้าง context ภายหลัง
4. คลิก delete บน preview เดียวกัน
5. ยืนยัน final queue ต้องว่าง

คำสั่ง:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist --filter testUploadAdapterDeletesTheFileBoundToTheClickedDuplicateNamePreview tests/ci4/OrderHttpTest.php
```

ผลก่อนแก้:

```text
Deleting a late preview left its File in the final queue.
Tests: 1, Assertions: 4, Failures: 1.
```

ข้อความนี้หมายถึง preview ที่เกิดหลัง completion ถูกลบจาก UI แต่ File เดิมยังค้างใน final queue จำนวน 1 รายการ

### GREEN

หลังแก้ production adapter คำสั่งเดิมได้:

```text
OK (1 test, 4 assertions)
```

Regression เดียวกันยังขับและรักษากรณีต่อไปนี้:

- normal completion ที่ context มีอยู่แล้ว
- multiple และ repeated completion
- duplicate names ที่เป็น File คนละ object
- pending cancel ก่อน completion ซึ่งต้องไม่ลบ completed File
- post-completion delete ซึ่งต้องลบ File ตาม clicked preview identity
- completion-before-context แล้ว delete ภายหลัง

Mutation proof คือ production code ก่อนเพิ่ม late-context binding ให้ RED ตามข้อความข้างต้น และ code หลังเพิ่ม binding ให้ GREEN

## Verification

| Gate | ผล |
|---|---|
| Focused adapter regression | `OK (1 test, 4 assertions)` |
| Focused upload server tests | `OK (6 tests, 90 assertions)` |
| `OrderHttpTest.php` | `OK (74 tests, 1235 assertions)` |
| Full PHPUnit บน exact temporary candidate | `OK (426 tests, 8982 assertions)` |
| PHPStan บน exact temporary candidate | `[OK] No errors` |
| Full `scripts/ci-check.sh` | PASS ทุก gate |
| Temporary candidate count | `21` files |
| Route scope | ใช้ `task-7-route.patch` เดิมเพียง 1 hunk |
| Exact CI3 asset SHA-256 | `MATCH` 9/9 |
| Real Git index | tree เดิมและ cached diff ว่าง |

Full PHPUnit ที่รันบน real index โดยตรงล้มเฉพาะ tracked-asset gate เพราะ Exact CI3 assets ยังเป็น untracked ตามข้อกำหนดห้าม stage จากนั้นรันซ้ำด้วย temporary index ที่เริ่มจาก `6799684db6de09936122d2ae25a5461a878b0eb3`, stage เฉพาะ whole-file paths เดิม 20 files และ apply route patch เดิม 1 hunk ได้ candidate 21 filesและผ่านครบ

Full CI รอบแรกถูก sandbox ปิดการอ่าน `.env.example` ด้วยข้อความ:

```text
grep: .env.example: Operation not permitted
```

ข้อความนี้หมายถึง sandbox ปิด filesystem access ไม่ใช่ source gate failure การ retry คำสั่งเดิมนอก sandboxผ่านทุก gate รวม PHPUnit, PHPStan, route, asset tracking, secret policy, PII guard และ repository safety gate

## Exact CI3 assets

| Target | SHA-256 |
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
- `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix3-report.md`

ไม่มี dependency ใหม่ ไม่มี stage, commit หรือ push และไม่แตะ browser credential หรือ shared DB

## Concern ที่เหลือ

Authenticated browser matrix ยัง `BLOCKED` ตามข้อจำกัดเดิม จึงไม่อ้าง browser PASS สำหรับ native file select, drag/drop, FileReader timing, progress knob, in-flight abort, post-completion delete หรือ final browser submit แม้ production adapter regression และ automated gates ผ่านทั้งหมด
