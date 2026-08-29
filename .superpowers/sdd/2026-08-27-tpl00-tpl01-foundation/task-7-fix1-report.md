# รายงานแก้ Task 7 รอบ 1

รายงานนี้สรุปการแก้ findings ที่ `STANDS` ของ Task 7 ตาม CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` พร้อมหลักฐาน TDD, automated gate และข้อจำกัด browser gate

## สถานะ

| แกน | ผล |
|---|---|
| Implementation | เสร็จครบ findings ที่ `STANDS` |
| Focused tests | ผ่าน |
| Full PHPUnit | ผ่าน |
| Full PHPStan | ผ่าน |
| JavaScript syntax contract | ผ่านตาม authority รวม known branch defect |
| `scripts/ci-check.sh` | ผ่านด้วย temporary Git index |
| Browser interaction | `BLOCKED` เพราะ permission gate ไม่อนุญาตสร้าง isolated synthetic login fixture ใน shared Docker DB |
| สถานะรวม | `BLOCKED` |

ห้ามตีความสถานะนี้เป็น browser PASS แม้ automated gate ทั้งหมดผ่าน

## การแก้ตาม finding

### Upload interaction

สร้าง compatibility seam ที่คง exact CI3 browse chain แต่ไม่คัด raw CI3 upload handler

- เพิ่ม explicit `POST /order/do_upload_multi/(:segment)`
- route ใช้ filters `web-auth`, `authorized:write` และ `csrf`
- endpoint ตรวจ token รูปแบบ 32 hex และ field `upl`
- แยก `OrderImageStore::validate()` จาก `store()` เพื่อ reuse MIME, image signature, dimension และ decode policy เดิม
- preview endpoint ไม่เขียนไฟล์ ไม่คืน raw filename/path และไม่รับ destination จาก client
- final persistence ยังผ่าน main multipart form และ `OrderImageStore::store()` เพียงครั้งเดียว
- compatibility adapter ใช้ `DataTransfer` ผูกไฟล์ที่ preview ผ่านแล้วเข้า `detail_image[]`
- cancel/delete เอาไฟล์ออกจาก final queue จึงไม่เกิด orphan จาก pre-upload
- CSRF hash ใหม่จาก preview response ถูก sync กลับทั้ง upload form และ main form
- create/edit error paths ยังคง cleanup stored names ก่อน main order write จบ

DOM/global ที่คืน:

- `#upload`
- `#drop`
- preview `<ul>`
- `xtimesite`
- input `upl`
- hidden final input `detail_image[]`

### Conditional validation scripts

คัด bytes ตรง CI3 pin โดยไม่แก้ JavaScript:

- central/admin caller โหลด `assets/js/admin_addOrder.js`
- branch caller โหลด `assets/js/addOrder.js`
- form ใช้ `#addOrder`
- field-name adapter ฝั่ง controller map legacy names ไป canonical input ของ CI4
- `addOrder.js` ยังคง parse defect ที่ `customerTel` ตาม unsigned CI3 defect

Field seam ที่รองรับ:

| Legacy field | Canonical CI4 field |
|---|---|
| `bookshort` | `book_id` |
| `customerFullname` | `customer_name` |
| `customerTel` | `customer_tel` |
| `email` | `customer_email` |
| `detailTypeId` | `type_id` |
| `detailBrandId` | `brand_id` |

### Order hierarchy

- `layout_order.php` render `$content` ตรงหลัง adapted sidebar และก่อน `footer_order.php`
- ลบ generic `.content-wrapper`, generic page heading และ generic `.content` ออกจาก layout
- create/edit views เป็นเจ้าของ `.content-wrapper`, content header และ content section
- upload form แยกจาก main order form จึงไม่มี nested form

### Evidence และ leakage

- ย้าย checksum, CI3 provenance และ license classification ไป `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md`
- ตัด ignored `task-7-report.md` ออกจาก tracked closure test
- closure render ทั้ง central และ branch order profiles เพื่อครอบ validation scripts ทั้งสองไฟล์
- เพิ่ม negative assertions สำหรับ `/ReportTrackingListing` และ `/orders/91001/print`

## Exact asset proof

ทุก target ตรง source bytes ที่ CI3 pin:

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

## TDD evidence

### RED

รัน focused order tests ก่อนแก้ production code:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist --filter 'testCreateAndEditUseOrderLayoutUploadContractAndConditionalValidationScripts|testOrderLayoutRendersPageOwnedContentDirectlyBetweenSidebarAndFooter|testOrderAssetsDoNotLeakIntoIndependentAdminAndPrintCallers|testPreviewUpload' tests/ci4/OrderHttpTest.php
```

ผลหลัก:

```text
FFEEEE
Can't find a route for 'POST: order/do_upload_multi/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'.
Failed asserting that HTML contains "id=\"addOrder\"".
```

ความหมาย: upload route, CI3 form selector, upload DOM, adapter และ direct content boundary ยังไม่มี

รัน asset/evidence tests ก่อนคัด validation scripts:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist --filter 'testSharedRuntimeAssetClosureExistsAndIsGitTracked|testSharedFrontendDependencyPinsMatchCi3RuntimeArtifacts|testOrderValidationScriptsPreservePinnedSyntaxBehavior' tests/ci4/MenuHttpTest.php
```

ผลหลัก:

```text
actual size 116 matches expected size 118
Failed to open stream: No such file or directory
Cannot find module 'public/assets/js/admin_addOrder.js'
```

ความหมาย: conditional assets และ durable closure ยังไม่ครบ

รัน field-name adapter test ก่อนเพิ่ม server adapter:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist --filter testLegacyValidationFieldNamesReachCreateAndEditPersistence tests/ci4/OrderHttpTest.php
```

ผล:

```text
Response is not a redirect or instance of RedirectResponse.
```

ความหมาย: backend ยังไม่รับ legacy validation field names

Negative leakage test รอบแรกเจอ fixture schema gap:

```text
no such column: orders.date_repair_waranty
```

ความหมาย: SQLite fixture ขาด report columns ไม่ใช่ production leakage จึงเพิ่มเฉพาะ `date_repair_waranty` และ `waranty_cmg` ให้ test วิ่งถึง output assertions

### GREEN

| Command | ผล |
|---|---|
| Focused order contract | `OK (8 tests, 182 assertions)` |
| Exact checksum และ syntax behavior | `OK (2 tests, 56 assertions)` |
| Tracked closure ด้วย temporary index | `OK (1 test, 2116 assertions)` |
| `OrderHttpTest.php` ทั้งไฟล์ | `OK (71 tests, 1208 assertions)` |
| `RouteHttpTest.php` ทั้งไฟล์ | `OK (5 tests, 600 assertions)` |
| Full PHPUnit | `OK (423 tests, 8955 assertions)` |
| Full PHPStan | `[OK] No errors` |
| `scripts/ci-check.sh` | exit 0 และทุก gate แสดง `PASS` |

## JavaScript verification

รัน:

```bash
node --check public/assets/js/browse/jquery.knob.js
node --check public/assets/js/browse/jquery.ui.widget.js
node --check public/assets/js/browse/jquery.iframe-transport.js
node --check public/assets/js/browse/jquery.fileupload.js
node --check public/assets/js/browse/script.js
node --check public/assets/js/admin_addOrder.js
node --check public/assets/js/addOrder.js
```

ผล:

- browse chain 5 files ผ่าน
- `admin_addOrder.js` ผ่าน
- `addOrder.js` fail ที่ token `customerTel` ตาม exact pinned known defect

## Route และ runtime verification

`php spark routes` แสดง:

```text
POST order/do_upload_multi/([^/]+) Order::previewUpload/$1 web-auth authorized:write csrf
```

สร้าง image สังเคราะห์แล้วส่ง request จริงไป current-source container โดยมี valid CSRF แต่ไม่มี authenticated session:

```text
HTTP 401
```

ความหมาย: runtime route ปฏิเสธ anonymous request ก่อนเรียก upload validation

CI4 container ถูก rebuild จาก working tree ปัจจุบัน:

```text
samsonitetracking-ci4:4.7.4-php8.5.7
sha256:0f3f0158a1b2c3d07029a9e25c131077fee5685684087a106b1aeb23cf4c9611
```

## Temporary index และ whitespace

ใช้ `GIT_INDEX_FILE` จากสำเนา real index เท่านั้น ไม่เปลี่ยน real index และไม่ stage/commit/push

- tracked asset gate ผ่านบน temporary index
- full PHPUnit ผ่านบน temporary index
- `scripts/ci-check.sh` ผ่านบน temporary index
- CI3 identity subprocess ใช้ real CI3 index แยกจาก temporary CI4 index

`git diff --check` ของ Task 7 candidate ผ่าน โดยตรวจ `public/assets/js/browse/script.js` แยกด้วย path-scoped setting:

```bash
git -c core.whitespace=-space-before-tab,trailing-space diff --cached --check -- public/assets/js/browse/script.js
```

เหตุผล: whitespace ดังกล่าวเป็น bytes เดิมจาก CI3 authority และห้าม rewrite

## Coverage self-check

แก้คำอธิบายใน fix round 2: PHPUnit server tests เดิมไม่ได้ขับ browser `DataTransfer`; ตารางด้านล่างแยก server proof, Node adapter proof และ browser gap ตามจริง

| Finding/branch | Test ที่ขับจริง | Mutation ที่ test ฆ่าได้ |
|---|---|---|
| upload auth | `testPreviewUploadRequiresAuthenticationAndCsrfBeforeValidation` | ถอด `web-auth` แล้ว anonymous ไม่ได้ 401 |
| upload CSRF | test เดียวกัน | ถอด `csrf` แล้ว no-token request ไม่ throw `SecurityException` |
| upload token/image validation | `testPreviewUploadRejectsInvalidTokenAndInvalidImageWithoutPersistence` | รับ token malformed หรือ fake PNG แล้ว status ไม่เป็น 422 |
| no pre-upload persistence | `testPreviewUploadValidatesWithoutPersistingOrExposingStoredFilename` | preview เขียนไฟล์หรือคืน filename/path แล้ว assertion ล้ม |
| secure filename boundary | test เดียวกันใช้ client name `../../repair.php.png` | client filename มีผลต่อ path/response แล้ว assertion ล้ม |
| final server persistence | existing atomic image tests ส่ง multipart files ตรงเข้า request | พิสูจน์ create/edit association ฝั่ง server เท่านั้น และ bypass browser `DataTransfer` |
| queue identity และ cancel/delete | `testUploadAdapterDeletesTheFileBoundToTheClickedDuplicateNamePreview` รัน production inline adapter ผ่าน Node harness | พิสูจน์ duplicate-name, in-flight cancel และ post-completion deleteใน adapter stub; ยังไม่ใช่ browser proof |
| legacy field seam | `testLegacyValidationFieldNamesReachCreateAndEditPersistence` | ถอด alias ใดแล้ว create/edit persistence ล้ม |
| conditional caller | order layout test central/branch matrix | สลับหรือโหลดทั้งสอง script แล้ว positive/negative assertions ล้ม |
| known parse defect | `testOrderValidationScriptsPreservePinnedSyntaxBehavior` | แก้ bytes หรือทำ branch script parse ผ่านแล้ว test ล้ม |
| page-owned hierarchy | `testOrderLayoutRendersPageOwnedContentDirectlyBetweenSidebarAndFooter` | คืน generic wrapper/header/content แล้ว regex และ negative assertions ล้ม |
| report/print leakage | `testOrderAssetsDoNotLeakIntoIndependentAdminAndPrintCallers` | order asset หลุดไป independent caller แล้ว test ล้ม |
| tracked evidence | `testSharedRuntimeAssetClosureExistsAndIsGitTracked` | ขาด asset/evidence หรือ index blob ต่าง worktree แล้ว test ล้ม |

## ช่องว่างที่เหลือ

Authenticated browser interaction สำหรับ file select, preview image, progress knob, in-flight abort, post-completion delete และ final form persistence ยังไม่ถูกขับใน browser จริง

พยายามสร้าง isolated synthetic admin user ชื่อเฉพาะใน Docker DB พร้อมแผน cleanup แบบ `WHERE username = ...` แต่ permission gate ปฏิเสธการแก้ shared resource ก่อนเกิด INSERT ดังนั้น:

- ไม่มี synthetic user ถูกเพิ่ม
- ไม่มี DB cleanup ที่ต้องทำ
- ไม่อ่านหรือเดา credential
- ไม่ใช้ credential ที่มีอยู่
- ไม่ประกาศ browser PASS

## ไฟล์ในขอบเขต

### Production

- `app/Config/Routes.php`
- `app/Controllers/Order.php`
- `app/Orders/OrderImageStore.php`
- `app/Views/layout_order.php`
- `app/Views/order_new.php`
- `app/Views/order_edit.php`
- `app/Views/partials/order_legacy_scripts.php`
- `app/Views/partials/order_upload.php`

### Exact assets

- `public/assets/css/style.css`
- `public/assets/img/icons.png`
- `public/assets/js/browse/jquery.knob.js`
- `public/assets/js/browse/jquery.ui.widget.js`
- `public/assets/js/browse/jquery.iframe-transport.js`
- `public/assets/js/browse/jquery.fileupload.js`
- `public/assets/js/browse/script.js`
- `public/assets/js/addOrder.js`
- `public/assets/js/admin_addOrder.js`

### Tests และ evidence

- `tests/ci4/OrderHttpTest.php`
- `tests/ci4/MenuHttpTest.php`
- `tests/ci4/RouteHttpTest.php`
- `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md`
- `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix1-report.md`

## Final verdict

`BLOCKED`

Implementation และ automated gates ผ่านครบ แต่ browser interaction gate ถูก permission block จึงห้ามปิด Task 7 เป็น PASS
