# รายงาน Task 7: Order layout profile

เอกสารนี้บันทึก TDD, การแมป CI3 pin, asset identity, controller profile, verification และข้อจำกัดของ order-specific layout สำหรับหน้า create/edit เท่านั้น

## สรุปผล

| แกน | ผล | สถานะ |
|---|---|---|
| Order layout | เพิ่ม `layout_order.php` จาก CI3 order shell โดย adapt เฉพาะ CI4 seam | ผ่านฝั่ง source และ HTTP test |
| Controller profile | `/orders/new` และ `/orders/{id}` ใช้ `order`; listing/report ใช้ `admin`; print ไม่มี layout | ผ่าน |
| Runtime assets | `style.css`, browse JavaScript 5 ไฟล์ และ recursive `icons.png` ตรง CI3 pin ทุก byte | ผ่าน |
| Asset leakage | listing และ dashboard ไม่มี order asset | ผ่าน |
| `addOrder.js` | ไม่เพิ่ม เพราะ syntax error และ brief ห้ามเพิ่ม | ผ่าน |
| Browser interaction | ยังไม่ได้รันบน current source identity | `NOT-VERIFIED` |
| Automated gate | focused Order/Menu/AccessDenied, PHP syntax, PHPStan และ full PHPUnit ผ่าน | `ci-check` ถูก Packagist 502 block |

## CI3 authority และ source mapping

CI3 presentation authority คือ commit `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`

| CI3 source | SHA-256 | CI4 target | การ adapt |
|---|---|---|---|
| `application/views/includes/header_order.php` | `36d9da71c34745639351c549fb1b6cae2ffd09d7e7d30f7d9241bfeff6b62475` | `app/Views/layout_order.php` | เปลี่ยน helper, presenter data, escaping, content seam และ POST+CSRF logout |
| `application/views/includes/footer_order.php` | `6f4edfdc81f0f164ddd0e79899799bbf58051e8c99daf45c7e4d138e80e77b20` | `app/Views/partials/order_legacy_scripts.php` | เปลี่ยนเฉพาะ `base_url()` syntax และคง script order |
| `application/libraries/BaseController.php::load_order_Views()` | อยู่ใน pin เดียวกัน | `BaseController::layout(..., profile: 'order')` | reuse presenter และ menu data ที่ Task 4/5 สร้างไว้ |

`layout_order.php` คง AdminLTE hierarchy, branch header, autocomplete, user menu, sidebar, footer และ active-menu script ตาม CI3 order header/footer โดยไม่ query DB จาก view

## Controller profile mapping

Caller sweep จาก CI3 `application/controllers/Order.php` พบ profile ดังนี้

| CI3 caller | CI3 render | CI4 caller | CI4 profile |
|---|---|---|---|
| `add()` | `load_order_Views("tracking/add_order")` | `Order::newOrder()` | `order` |
| `editOrdersOld()` | `load_order_Views("tracking/edit_order")` | `Order::editForm()` | `order` |
| queue/listing ทุกสถานะ | `loadViews(...)` | `Order::listing()` | `admin` |
| `ReportTrackingListing()` | `loadViews(...)` | `Order::reportTrackingListing()` | `admin` |
| `editOrders()` print | `load_print_Views(...)` | `Order::print()` | ไม่มี shared layout |

เปลี่ยน controller เพียงสอง render call ของ create/edit ไม่มี business query, workflow, upload persistence หรือ authorization logic ซ้ำ

## Exact runtime assets

ทุกไฟล์คัดด้วย `git show` จาก CI3 pin โดยไม่ rewrite และ checksum target ตรง source

| CI4 target | SHA-256 | Version หรือชนิด | License evidence |
|---|---|---|---|
| `public/assets/css/style.css` | `a0ca03a6569a9520ea1aaac734cfcb114d9418475eec43eae41201d1c65050b6` | custom CI3 stylesheet | CI3 project source provenance; ไม่มี embedded license header |
| `public/assets/img/icons.png` | `8e729e7a5839f3cb37c416b51461501f1bffcfc290ca973dd2b3cbbf5bcd24dd` | recursive CSS dependency | CI3 project source provenance; ไม่มี embedded license header |
| `public/assets/js/browse/jquery.knob.js` | `9a9bcdeb2150048832cd9c5b6f56db8e20e2ade75a60ca1eb014ad49b9b65c16` | jQuery Knob 1.2.0 | embedded MIT/GPL header; ใช้ MIT option และ `public/assets/licenses/MIT.txt` |
| `public/assets/js/browse/jquery.ui.widget.js` | `95694c8567c94e0bcdff9fa4711be1d0060509931b8d19b450109b8552a8ef71` | jQuery UI Widget 1.10.1+amd | embedded MIT header และ `public/assets/licenses/MIT.txt` |
| `public/assets/js/browse/jquery.iframe-transport.js` | `0ddd3dc005842bd02b0bba0fa65951f4b64714504c887af0dfcbd97f390325c4` | Iframe Transport 1.6.1 | embedded MIT header และ `public/assets/licenses/MIT.txt` |
| `public/assets/js/browse/jquery.fileupload.js` | `912fd62966a08f15145b4aefcac50e45893dfb5732869ec658b48ac1362ebb07` | jQuery File Upload 5.26 | embedded MIT header และ `public/assets/licenses/MIT.txt` |
| `public/assets/js/browse/script.js` | `9a455e73fb66fe42f287f22cd96065e6f65039992a10ca687ce05df4dc8101ec` | custom CI3 browse glue | CI3 project source provenance; ไม่มี embedded license header |

Script order ที่ render หลัง content:

1. `jquery.knob.js`
2. `jquery.ui.widget.js`
3. `jquery.iframe-transport.js`
4. `jquery.fileupload.js`
5. `script.js`

`style.css` อ้าง `../img/icons.png`; asset closure test พบ dependency นี้แบบ recursive จึงเพิ่ม exact pinned byte แม้ brief ไม่ลิสต์ไฟล์นี้ไว้ตรง ๆ

## TDD: RED และ GREEN

### RED

คำสั่ง:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist --filter testCreateAndEditUseOrderLayoutWhileListingsStayOnAdminLayout tests/ci4/OrderHttpTest.php
```

ผลก่อน implementation:

```text
/orders/new missing /assets/css/style.css
FAILURES!
Tests: 1, Assertions: 3, Failures: 1.
```

ความหมาย: test ล้มเพราะ order profile และ asset chain ยังไม่มีจริง ไม่ใช่ fixture หรือ syntax error

Asset closure RED:

```text
Invalid file: "layout_order.php"
hash_file(./public/assets/css/style.css): Failed to open stream: No such file or directory
```

ความหมาย: layout และ pinned assets ยังไม่มีตาม contract

หลังเพิ่ม `style.css` ครั้งแรก closure พบ recursive gap:

```text
public/assets/img/icons.png
Failed asserting that file exists.
```

จึงคัด `icons.png` จาก pin และเพิ่ม checksum gate

### GREEN

```text
OK (1 test, 42 assertions)
OK (65 tests, 1086 assertions)
OK (31 tests, 425 assertions)
[OK] No errors
```

ผลตามลำดับคือ order layout test, focused `OrderHttpTest`, `AccessDeniedHttpTest` และ PHPStan

ผลเพิ่มเติมบน exact temporary index:

```text
MenuHttpTest: OK (32 tests, 2200 assertions)
Full PHPUnit: OK (416 tests, 8596 assertions)
```

`ci-check` รอบแรกใน sandbox หยุดเพราะ sandbox อ่าน `.env.example` ไม่ได้ หลัง retry นอก sandbox Packagist security advisory endpoint ตอบ `HTTP/2 502` ซ้ำสองครั้ง จึงหยุด retry ตาม policy ผลก่อนถึงจุดนั้นผ่าน shell syntax, dependency/route/health smoke, mysqli/mysqlnd, Composer platform, PHP lint, Docker aliases และ DB isolation

สถานะ `scripts/ci-check.sh`: `BLOCKED_EXTERNAL`, ไม่อ้าง PASS

## Automated coverage

| Contract | Test evidence |
|---|---|
| create/edit ใช้ order shell | `OrderHttpTest::testCreateAndEditUseOrderLayoutWhileListingsStayOnAdminLayout` |
| asset exact order | test เดียวกันใช้ offset ที่เพิ่มขึ้นตามลำดับ |
| listing ไม่มี order asset | test เดียวกันตรวจ `/ordersListing` และ `/TrackingListing` |
| dashboard ไม่มี order asset | `MenuHttpTest::testAdminShellUsesDatabaseBranchDataForHeaderAndAutocomplete` |
| runtime closure รวม order profile | `MenuHttpTest::testSharedRuntimeAssetClosureExistsAndIsGitTracked` |
| SHA-256, version, license header | `MenuHttpTest::testSharedFrontendDependencyPinsMatchCi3RuntimeArtifacts` |
| `addOrder.js` ไม่ถูกโหลด | order layout test ตรวจ negative assertion ทั้ง create/edit |
| PHP syntax | `php -l` ต่อ controller, layout และ partial |
| JavaScript syntax | `node --check` ต่อ browse JavaScript 5 ไฟล์ |

## `addOrder.js` disposition

CI3 add/edit view มี source caller ของ `assets/js/addOrder.js` และ `assets/js/admin_addOrder.js` แต่ Task 7 อนุญาตเฉพาะ style และ browse chain 5 ไฟล์

คำสั่ง `node --check` บน pinned `addOrder.js` คืน syntax error ที่ token `customerTel`; จึงไม่เพิ่มทั้ง `addOrder.js` และ dependency นอก exact task bundle ไม่มีการ upgrade หรือ replacement

## Browser และ upload behavior

สถานะ: `NOT-VERIFIED`

เหตุผล:

- CI4 container ที่ `127.0.0.1:18405` ใช้ immutable image `samsonitetracking-ci4:4.7.4-php8.5.7` และไม่มี source mount จึงไม่ใช่ current working tree
- environment ปัจจุบันไม่มี `WP00C_TEST_PASSWORD` จึง login synthetic runtime ไม่ได้โดยไม่เปิดเผยหรือสร้าง credential ใหม่
- CI4 ปัจจุบันตั้งใจไม่ expose legacy `/order/do_upload_multi`; route regression ระบุ endpoint นี้เป็น 404
- create/edit form ใช้ multipart upload บน submit ตาม current backend seam; legacy preview/progress/cancel hooks ที่พึ่ง pre-upload endpoint ยังไม่ได้ขับบน current runtime

จึงไม่อ้าง file select, preview, progress, cancel/delete หรือ network interaction ว่า PASS ในรายงานนี้ Automated HTTP test ยืนยันเฉพาะ rendered asset chain, selector-independent multipart input และ server-side upload behavior เดิม

## Self-review

- ไม่มี dependency ใหม่ ไม่มี version upgrade และไม่มี JavaScript rewrite
- reuse `AdminLayoutPresenter`, `MenuStore`, menu data, escaping และ POST+CSRF logout จาก shared layout
- ไม่แตะ listing, report, print, upload workflow หรือ business query
- `style.css` และ browse assetsโหลดเฉพาะ create/edit
- `addOrder.js` ไม่อยู่ใน candidate
- shared `BaseController.php` มี order profile seam จาก Task 5 อยู่แล้ว จึงไม่แก้ซ้ำ
- plain `git diff --check` พบ `space-before-tab` 18 จุดใน exact pinned `script.js`; ไม่ rewrite เพราะ checksum ต้องตรง authority
- `git -c core.whitespace=-space-before-tab,trailing-space diff --cached --check` ผ่าน โดยยังตรวจ trailing whitespace ตามเดิม

## Exact checkpoint candidate

Candidate มี 13 ไฟล์:

1. `app/Controllers/Order.php`
2. `app/Views/layout_order.php`
3. `app/Views/partials/order_legacy_scripts.php`
4. `public/assets/css/style.css`
5. `public/assets/img/icons.png`
6. `public/assets/js/browse/jquery.knob.js`
7. `public/assets/js/browse/jquery.ui.widget.js`
8. `public/assets/js/browse/jquery.iframe-transport.js`
9. `public/assets/js/browse/jquery.fileupload.js`
10. `public/assets/js/browse/script.js`
11. `tests/ci4/OrderHttpTest.php`
12. `tests/ci4/MenuHttpTest.php`
13. `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-report.md`

สร้าง temporary index จาก `HEAD` แล้ว add เฉพาะ 13 ไฟล์นี้เรียบร้อย tracked-asset gate ผ่าน และ SHA-256 ของ real index ก่อน/หลังตรงกัน (`real_index_unchanged=yes`)

## Concerns และไม้ต่อ

1. Browser interaction บน current source identity ยังเป็น `NOT-VERIFIED`; ต้อง rebuild isolated CI4 runtime และใช้ synthetic credential โดยไม่ log ค่า ก่อนประกาศ interaction PASS
2. Legacy browse `script.js` พึ่ง pre-upload endpoint และ globals ของ CI3 แต่ CI4 backend ใช้ multipart submit seam จึงยังไม่มีหลักฐานว่า preview/progress/cancel ทำงานร่วมกับ current backend
3. `scripts/ci-check.sh` ยังต้อง rerun เมื่อ Packagist security advisory endpoint หายจาก 502; full PHPUnit ผ่านแล้ว
