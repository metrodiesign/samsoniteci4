# รายงานแก้ Task 7 รอบ 2

รายงานนี้สรุปการแก้ duplicate queue identity, existing-image presentation, browser-proof claims, durable evidence และ route scope contamination โดยรักษา CI3 authority commit `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`

## สถานะ

| แกน | ผล |
|---|---|
| Implementation | เสร็จตาม findings รอบ 2 |
| Automated server proof | ผ่าน |
| Executable adapter proof | ผ่านด้วย production inline script ใน Node harness |
| Exact CI3 asset bytes | ตรงครบ 9 files |
| Full PHPUnit | `OK (426 tests, 8982 assertions)` |
| PHPStan | `[OK] No errors` |
| Full `scripts/ci-check.sh` | ผ่านด้วย exact temporary candidate 21 files |
| Authenticated browser proof | `BLOCKED` |
| สถานะรวม | `BLOCKED` |

สถานะรวมยังเป็น `BLOCKED` เพราะ runtime ตอบ HTTP `401` สำหรับ anonymous `/orders/new` และข้อกำหนดห้ามอ่านหรือเดา credential รวมถึงห้ามแตะ shared DB เพื่อสร้าง browser fixture

## Root cause และการแก้

### Duplicate filename queue

Root cause คือ adapter ใช้ `file.name` หา index ใน final queue ทำให้ preview หลายตัวที่ชื่อเหมือนกันชี้กลับไป File ตัวแรก และ in-flight cancel ของชื่อซ้ำสามารถลบ completed File ที่มีอยู่แล้ว

การแก้ขั้นต่ำอยู่ที่ shared partial `app/Views/partials/order_upload.php`:

- final queue เก็บ queue item ต่อ completion occurrence
- queue item ผูกกับ preview context ผ่าน jQuery `.data('orderQueueItem', item)`
- `DataTransfer` รับ `item.file` ตามลำดับ queue
- delegated delete หา queue item จาก preview node แล้วลบด้วย object identity
- in-flight preview ที่ยังไม่มี queue itemไม่เปลี่ยน completed queue
- exact `public/assets/js/browse/script.js` ไม่ถูกแก้

Executable regression render production inline scriptจริงแล้วรันผ่าน Node harness โดยใช้ File สอง object ชื่อ `camera.jpg` เหมือนกันแต่ `bytes` ต่างกัน จากนั้นตรวจ:

1. completion order เข้า final queueตรง File identity
2. in-flight cancel ชื่อซ้ำไม่ลบ completed File
3. ลบ preview ตัวที่สองแล้ว final queue เหลือ File ตัวแรก
4. ลบ preview ตัวแรกแล้ว queue ว่าง

หลักฐาน RED ก่อนแก้:

```text
Cancelling an in-flight duplicate removed a completed file.
```

ข้อความนี้หมายถึง adapter เดิมลบ completed File ผิดตัวเมื่อยกเลิก pending preview ชื่อซ้ำ

### Existing image ในหน้า edit

หน้า edit คืน observable behavior ตาม CI3 โดย parse `detailImage` แบบ pipe-separated และ render เฉพาะชื่อที่ตรง:

```text
\A[a-f0-9]{32}\.png\z
```

แต่ละ valid name render ผ่าน secure route:

```text
/order-image/{name}
```

การรักษา contract:

- valid names แสดง `<img>` ขนาด `150px` x `150px`
- malformed, traversal, legacy extension และ markup payload ไม่ถูก render
- URL attribute ผ่าน escaping
- no-upload คง prior DB association และ prior file
- replacement เปลี่ยน DB associationเป็น names ใหม่ แต่คง prior fileบน diskตาม CI3 ruling
- upload validation failure คง prior association/file และลบไฟล์ใหม่ที่เก็บก่อน failure
- DB update failure คง prior association/file และลบเฉพาะไฟล์ใหม่
- controller comment ระบุ storage disposition นี้ตรง ruling และไม่เรียก prior fileว่า orphan

หลักฐาน RED ก่อนแก้มี 3 failures จาก focused 4 tests:

- duplicate identity regression
- valid/malformed existing-image render
- no-upload existing image render

DB update-failure regression ผ่านตั้งแต่ RED และตรึงพฤติกรรม rollback เดิมไว้

### Browser-proof claims

แก้ `task-7-fix1-report.md` ให้ถอน claim ที่ว่า PHPUnit เดิมขับ browser `DataTransfer`, final association และ cancel/delete จริง

หลักฐานถูกแยกเป็น 3 ชั้น:

| ชั้น | พิสูจน์ได้ | พิสูจน์ไม่ได้ |
|---|---|---|
| PHPUnit server tests | auth, CSRF, validation, secure image route, render, persistence และ rollback | native browser queue/event chain |
| Node adapter regression | production inline adapter, duplicate identity, pending cancel และ post-completion delete | real DOM, native `DataTransfer`, FileReader, progress knob |
| Authenticated browser | ไม่มี เพราะ auth block | file select, drag/drop, preview, progress, CSRF refresh และ final submit |

Anonymous runtime probe:

```text
ANONYMOUS_ORDER_FORM_HTTP=401
```

ผลนี้ยืนยัน auth block เท่านั้น ห้ามตีความเป็น browser interaction PASS

### Durable evidence

ปรับ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md` ให้แยกสถานะตามเวลา:

| ช่วง | Runtime closure | Candidate/checkpoint |
|---|---:|---:|
| Historical Task 6 ก่อน commit | 109 files | 103 files |
| Task 6 checkpoint `6799684` | 109 files | 103 files committed |
| Current Task 7 delta | 118 files | 21 files |

Current Task 7 เพิ่ม exact order assets 9 files และ code/test/evidence 12 files โดยไม่แก้ historical Task 6 stateย้อนหลัง

## TDD และ verification

### RED

รัน:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist --filter 'testUploadAdapterDeletesTheFileBoundToTheClickedDuplicateNamePreview|testEditRendersOnlyValidExistingImageNamesThroughTheSecureRoute|testEditWithoutUploadKeepsExistingImage|testEditDatabaseFailureKeepsPriorAssociationAndFileAndRemovesOnlyNewFiles' tests/ci4/OrderHttpTest.php
```

ผล:

```text
FFF.
Tests: 4, Failures: 3
```

ความหมายคือ duplicate queue identity และ existing-image render ยังผิด ส่วน DB update rollback behavior เดิมถูกต้อง

### GREEN

| Gate | ผล |
|---|---|
| Duplicate/existing-image focused | `OK (6 tests, 75 assertions)` |
| `OrderHttpTest.php` | `OK (74 tests, 1235 assertions)` |
| `RouteHttpTest.php` | `OK (5 tests, 600 assertions)` |
| Checksum และ syntax contract | `OK (2 tests, 56 assertions)` |
| Focused Order/Menu/Route | `OK (112 tests, 4268 assertions)` |
| Tracked closure บน temporary index | `OK (1 test, 2116 assertions)` |
| Full PHPUnit | `OK (426 tests, 8982 assertions)` |
| PHPStan | `[OK] No errors` |
| `scripts/ci-check.sh` | ผ่านทุก gate |
| Task 7 patch whitespace | ผ่าน |
| Runtime routes | upload เป็น POST พร้อม `web-auth authorized:write csrf`; image เป็น GET พร้อม `web-auth authorized:read` |

Full CI ใช้ wrapper ชั่วคราว unset `GIT_INDEX_FILE` เฉพาะ Git call ที่ชี้ไป CI3 checkout เพื่อไม่ให้ CI4 temporary indexรั่วข้าม repository; production scriptไม่ถูกแก้

### Exact CI3 asset proof

| Target | SHA-256 | ผล |
|---|---|---|
| `public/assets/css/style.css` | `a0ca03a6569a9520ea1aaac734cfcb114d9418475eec43eae41201d1c65050b6` | `MATCH` |
| `public/assets/img/icons.png` | `8e729e7a5839f3cb37c416b51461501f1bffcfc290ca973dd2b3cbbf5bcd24dd` | `MATCH` |
| `public/assets/js/browse/jquery.knob.js` | `9a9bcdeb2150048832cd9c5b6f56db8e20e2ade75a60ca1eb014ad49b9b65c16` | `MATCH` |
| `public/assets/js/browse/jquery.ui.widget.js` | `95694c8567c94e0bcdff9fa4711be1d0060509931b8d19b450109b8552a8ef71` | `MATCH` |
| `public/assets/js/browse/jquery.iframe-transport.js` | `0ddd3dc005842bd02b0bba0fa65951f4b64714504c887af0dfcbd97f390325c4` | `MATCH` |
| `public/assets/js/browse/jquery.fileupload.js` | `912fd62966a08f15145b4aefcac50e45893dfb5732869ec658b48ac1362ebb07` | `MATCH` |
| `public/assets/js/browse/script.js` | `9a455e73fb66fe42f287f22cd96065e6f65039992a10ca687ce05df4dc8101ec` | `MATCH` |
| `public/assets/js/addOrder.js` | `86fdf03e7cdbf2bfb66fde74cee6374cbc24cdea2395f9b9d2e63caad1bb89e0` | `MATCH` |
| `public/assets/js/admin_addOrder.js` | `4b07a289e72973be7f60963ff9156d70eedbe7adcd1779d38ccd0bfae5f33b42` | `MATCH` |

JavaScript syntax contract:

- browse chain 5 files ผ่าน `node --check`
- `admin_addOrder.js` ผ่าน `node --check`
- `addOrder.js` คง exact pinned syntax failure ที่ `customerTel`

## Coverage self-check

| Branch | Executable proof | สถานะ |
|---|---|---|
| create/edit central และ branch render | HTTP render matrix | PASS ฝั่ง server |
| single/multiple final persistence | multipart PHPUnit tests | PASS ฝั่ง server |
| repeated selection และ duplicate names | Node adapter regression | PASS ฝั่ง adapter; browser `BLOCKED` |
| preview success/error | HTTP preview tests | PASS ฝั่ง server |
| in-flight abort | pending contextใน Node regression | PASS ฝั่ง adapter; browser `BLOCKED` |
| post-completion delete | exact preview identityใน Node regression | PASS ฝั่ง adapter; browser `BLOCKED` |
| CSRF reject และ response refresh payload | HTTP tests | PASS ฝั่ง server |
| CSRF hash sync เข้า DOM | source contractเท่านั้น | browser `BLOCKED` |
| existing valid/malformed names | HTTP edit render regression | PASS ฝั่ง server |
| no-upload | DB association/file regression | PASS |
| replacement | DB associationเปลี่ยนและ prior fileคงอยู่ | PASS |
| validation failure | prior association/fileคงอยู่และไฟล์ใหม่ถูกลบ | PASS |
| DB update failure | trigger regression ตรึง prior association/fileและ cleanupใหม่ | PASS |
| native file select, drag/drop, FileReader และ progress knob | ไม่มี authenticated browser session | `BLOCKED` |
| final create/edit submitจาก browser queue | ไม่มี authenticated browser session | `BLOCKED` |

## Exact Task 7 staging manifest

Temporary indexเริ่มจาก `6799684` และมี candidate 21 filesเท่านั้น

### Whole-file paths 20 files

1. `app/Controllers/Order.php`
2. `app/Orders/OrderImageStore.php`
3. `app/Views/layout_order.php`
4. `app/Views/order_edit.php`
5. `app/Views/order_new.php`
6. `app/Views/partials/order_legacy_scripts.php`
7. `app/Views/partials/order_upload.php`
8. `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md`
9. `public/assets/css/style.css`
10. `public/assets/img/icons.png`
11. `public/assets/js/addOrder.js`
12. `public/assets/js/admin_addOrder.js`
13. `public/assets/js/browse/jquery.fileupload.js`
14. `public/assets/js/browse/jquery.iframe-transport.js`
15. `public/assets/js/browse/jquery.knob.js`
16. `public/assets/js/browse/jquery.ui.widget.js`
17. `public/assets/js/browse/script.js`
18. `tests/ci4/MenuHttpTest.php`
19. `tests/ci4/OrderHttpTest.php`
20. `tests/ci4/RouteHttpTest.php`

### Route path 1 hunk

`app/Config/Routes.php` ห้าม whole-file stage ให้ apply เฉพาะ hunk นี้:

```diff
diff --git a/app/Config/Routes.php b/app/Config/Routes.php
--- a/app/Config/Routes.php
+++ b/app/Config/Routes.php
@@ -64,6 +64,7 @@ $routes->get('orders/new', 'Order::newOrder', ['filter' => ['web-auth', 'authori
 $routes->post('orders/new', 'Order::create', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
 $routes->get('Orders', 'Order::newOrder', ['filter' => ['web-auth', 'authorized:write']]);
 $routes->post('addNewOrders', 'Order::create', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
+$routes->post('order/do_upload_multi/(:segment)', 'Order::previewUpload/$1', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
 $routes->post('sendorderUpdate', 'Order::sendToProvider', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
 $routes->post('sendorderUpdateStatus', 'Order::updateStatus', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
 $routes->post('sendorder_deliver', 'Order::deliver', ['filter' => ['web-auth', 'authorized:write', 'csrf']]);
```

ห้ามรวม route hunks ต่อไปนี้ใน Task 7 staging:

- tracking `(:segment)` เป็น `(:any)`
- password-reset aliases

Temporary manifest set equality ได้ `21` และ full CIผ่านจาก package นี้

## Real Git index

- real index `write-tree` ก่อนและหลังเป็น `c6ce38a8953cb1dedf08e35446b3195347139425`
- `git diff --cached --quiet` ผ่าน
- ไม่มี real staged path
- ไม่มี stage, commit หรือ push

## ไฟล์ที่แก้ใน fix round 2

- `app/Controllers/Order.php`
- `app/Views/order_edit.php`
- `app/Views/partials/order_upload.php`
- `tests/ci4/OrderHttpTest.php`
- `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md`
- `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix1-report.md`
- `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix2-report.md`

## Concern ที่เหลือ

Authenticated browser matrix ยัง `BLOCKED` ตามข้อจำกัด credential/shared DB จึงห้ามประกาศ Task 7 PASS แม้ automated server, adapter, checksum, static analysis และ full CI gates ผ่านทั้งหมด
