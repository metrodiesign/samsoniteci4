# Review Task 7: Order layout profile

Review นี้ตรวจ package ของ Task 7 เทียบ brief, strict CI3 preservation spec, CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`, route/function disposition และ production render/upload flow ปัจจุบัน โดยไม่แก้ source, stage หรือ commit

## Verdict

| แกน | Verdict | เหตุผลย่อ |
|---|---|---|
| Spec compliance | **FAIL** | Browse interaction contract ใช้งานไม่ได้, upload route ถูกทำให้ 404 โดยไม่มี signed disposition และ order shell ยังไม่คง CI3 hierarchy ตรง source |
| Code quality | **CHANGES REQUIRED** | มี 1 Critical, 3 Important และ 1 Minor |
| Browser gate | **NOT-VERIFIED** | ไม่มี current-source browser run สำหรับ preview, progress, cancel/delete และ network behavior |
| Checkpoint eligibility | **NOT ELIGIBLE** | Gate 5 ยังไม่ผ่าน, `ci-check` ถูก Packagist 502 block และ candidate/test ยังอ้าง scratch report ที่ git ignore |

## Findings

### Critical: Browse chain ถูกโหลดแต่ไม่มี contract ที่ทำงานได้

**ตำแหน่ง:** `app/Views/partials/order_legacy_scripts.php:5-9`, `public/assets/js/browse/script.js:3-16`, `public/assets/js/browse/script.js:20-67`, `app/Views/order_new.php:36`, `app/Views/order_new.php:145-149`, `app/Views/order_edit.php:48`, `app/Views/order_edit.php:145-149`, `app/Config/Routes.php:62-74`, `tests/ci4/RouteHttpTest.php:131-136`

CI3 create และ edit มี `<form id="upload">`, `#drop`, `<ul>`, global `xtimesite` และ action `/order/do_upload_multi/{times}` จริง แต่ CI4 form มีเพียง `detail_image[]` ใน main multipart form ไม่มี selector หรือ global เหล่านี้ ดังนั้น pinned `script.js` ไม่ bind uploader และไม่มี preview, progress หรือ cancel/delete ตาม brief Step 5

หากภายหลังคืน CI3 upload DOM โดยไม่แก้ contract เดิม การเลือกไฟล์จะมี failure สองชั้น:

1. `script.js` เรียก `data.submit()` ไป `/order/do_upload_multi/{times}` ซึ่ง CI4 ตั้งใจให้ 404 ใน `RouteHttpTest`
2. callback ของ `FileReader` อ่าน `xtimesite` ที่ CI4 ไม่ประกาศ ทำให้เกิด `ReferenceError` ก่อนสร้าง preview context

การอ้างว่า route นี้ “ตั้งใจไม่ expose” ใน `task-7-report.md:155` ไม่ใช่ signed disposition: explicit 178-route ledger ไม่ครอบ implicit route นี้ และ authority ปัจจุบันที่ `outputs/diagrams/2026-08-22_function-disposition-evidence_v3.md:381` ระบุ `do_upload_multi` เป็น `MIGRATE`, `PLANNED_NOT_IMPLEMENTED`, ไม่มี retirement proof ขณะที่ workflow mapping ที่ `outputs/diagrams/2026-08-17_ci3-workflow-design_v1/02-order-tracking.md:152-164` กำหนดให้ย้ายเป็น CI4 UploadedFile, random name และ storage นอก public

**Concrete failure scenario:** ผู้ใช้เปิด `/orders/new` หรือ `/orders/91001` แล้วเลือกไฟล์จาก input ปัจจุบัน จะไม่มี CI3 preview/progress/cancel UI; หากคืน `#upload` ตาม CI3 จะยิง request ไป 404 และ preview callback พังจาก `xtimesite` ที่ไม่มี

**Minimal fix:** resolve contract ก่อน checkpoint โดยเลือกทางใดทางหนึ่งผ่าน authority ที่ถูกต้อง:

- implement explicit, authenticated, CSRF-protected compatibility upload seam ที่ใช้ validation เดียวกับ `OrderImageStore`, พร้อม DOM/global adapter และ browser test จริง หรือ
- ขอ signed human disposition อนุมัติ multipart-only correction แล้วหยุดโหลด browse chain ที่ inert พร้อม rebaseline interaction contract

ห้ามเปิด CI3 raw endpoint ตรง ๆ เพราะจะย้อน security fix เรื่อง path traversal, random filename, MIME/image validation และ storage นอก public

**Class sweep:** ตรวจครบ create `/orders/new`, edit `/orders/{id}`, pinned browse scripts 5 ไฟล์, selectors `#upload/#drop`, global `xtimesite`, implicit endpoint, current multipart POST, CSRF และ `OrderImageStore`; listing/report/print ไม่เรียก order profile จึงไม่เข้า failure class นี้

### Important: Conditional validation scripts มี active caller แต่ถูกปัดด้วยข้อสรุปที่ไม่ครบ

**ตำแหน่ง:** `app/Views/partials/order_legacy_scripts.php:1-9`, `app/Views/order_new.php:36-146`, `app/Views/order_edit.php:48-146`, `tests/ci4/OrderHttpTest.php:229-255`, `task-7-report.md:141-145`

CI3 `tracking/add_order.php:454-463` และ `tracking/edit_order.php:647-656` โหลด script ตาม branch จริง:

- branch caller โหลด `assets/js/addOrder.js`; `node --check` ยืนยัน syntax error ที่ token `customerTel`
- central/admin caller โหลด `assets/js/admin_addOrder.js`; `node --check` ผ่าน และ script บังคับ required-field validation ฝั่ง browser

ดังนั้นคำว่า “ยังไม่พิสูจน์ active caller” ใน brief ถูก evidence ใหม่หักล้างแล้ว และการไม่เพิ่มทั้งสองไฟล์เปลี่ยน behavior ของ admin caller โดยไม่มี disposition ปัจจุบัน CI4 กำหนด `required` เฉพาะบาง field และพึ่ง server-side 422 จึงไม่คง validation timing/message ของ CI3

**Concrete failure scenario:** admin ส่ง create form โดยไม่กรอก customer/type/brand จะไม่เห็น CI3 jQuery validation ก่อน submit แต่ request ไป backend และจบด้วย response 422; branch behavior ของ CI3 ที่ script parse พังก็ยังไม่มี browser evidence หรือ signed correction ว่าจะ preserve หรือแก้

**Minimal fix:** port valid `admin_addOrder.js` สำหรับ caller ที่ตรงกันและ adapt selector/field name ที่ CI4 บังคับเท่านั้น; สำหรับ broken `addOrder.js` ให้บันทึก known-defect runtime evidence แล้วขอ signed disposition ว่าจะ preserve parse failure หรืออนุมัติ correction ห้ามใช้ negative assertion ว่าไม่โหลดเป็นหลักฐานปิด contract

**Class sweep:** ตรวจทั้ง add/edit, branch/admin conditional callers, script syntax, form IDs, field names และ required rules แล้ว ไม่พบ caller อื่นของสองไฟล์นี้

### Important: `layout_order.php` เพิ่ม hierarchy ที่ไม่มีใน CI3 order shell

**ตำแหน่ง:** `app/Views/layout_order.php:158-182`

CI3 `header_order.php` จบหลัง `<aside class="main-sidebar">`; order content view เป็นเจ้าของ `<div class="content-wrapper">`, content header/content และจุดปิด wrapper ก่อน `footer_order.php` แต่ target เพิ่ม generic `content-wrapper`, generic page heading และ content section รอบ `$content` เอง แล้ววาง footer/scripts ก่อนปิด outer wrapper

นี่ไม่ใช่เพียง helper/data/content seam และจะชนกับ TPL-06 เมื่อคัด exact `add_order.php` หรือ `edit_order.php`: content ที่มี `content-wrapper` อยู่แล้วจะถูกซ้อนอีกชั้นและ footer hierarchy ต่างจาก CI3

**Concrete failure scenario:** เมื่อ Task TPL-06 แทน current simplified form ด้วย exact CI3 content, DOM จะมี `.content-wrapper > .content > .content-wrapper` และ wrapper closure/footer position ไม่ตรง authority ทำให้ DOM/visual gate ล้ม

**Minimal fix:** ให้ order layout output `$content` ตรง boundary ระหว่าง adapted `header_order.php` และ `footer_order.php`; page-specific content ต้องเป็นเจ้าของ content wrapper/header ตาม CI3 source ไม่เพิ่ม generic shell หรือ access-denied branch ใน order profile

### Important: Candidate และ tracked-asset test พึ่ง git-ignored scratch report

**ตำแหน่ง:** `tests/ci4/MenuHttpTest.php:305-332`, `.superpowers/sdd/.gitignore:1`, `task-7-report.md:171-189`

`task-7-report.md` อยู่ใต้ `.superpowers/sdd/**` ซึ่งถูก ignore ทั้ง tree และ `git ls-files --error-unmatch` ยืนยันว่าไม่ tracked แต่ test ใส่ไฟล์นี้ใน `$trackedFiles` และรายงานนับเป็น checkpoint candidate ลำดับที่ 13

Focused run บน real index ล้มด้วยข้อความ:

```text
error: pathspec '.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-report.md' did not match any file(s) known to git
Failed asserting that 1 is identical to 0.
```

ความหมาย: แม้ asset ใหม่จะถูก stage/commit ครบ test ก็ยังล้มที่ scratch report เว้นแต่ force-add ไฟล์ที่ policy ตั้งใจ ignore

นอกจากนี้ order asset provenance/license evidence มีเฉพาะ scratch report; durable evidence เดิม `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:129-154` ระบุ order browse chain ว่าอยู่นอก Task 6 และยังไม่บันทึก bundle นี้ครบ โดยเฉพาะ custom `style.css`, `icons.png`, `script.js` ที่รายงานเรียก CI3 provenance ว่า license evidence ทั้งที่ไม่มี embedded license header

**Concrete failure scenario:** checkpoint commit ที่ถูกต้องไม่ force-add scratch report แล้ว CI รัน `MenuHttpTest` จะ fail; หาก force-add เพื่อให้ test ผ่าน จะละเมิดขอบเขต SDD scratch และสร้าง precedent ให้ evidence ชั่วคราวกลายเป็น release artifact

**Minimal fix:** ย้าย checksum, source provenance และ first-party/license classification ไป tracked evidence ใต้ `outputs/reference/**`, ให้ test อ้าง evidence นั้น และตัด `task-7-report.md` ออกจาก `$trackedFiles` กับ candidate list

### Minor: Negative leakage test ยังไม่ขับ independent report/print callers

**ตำแหน่ง:** `tests/ci4/OrderHttpTest.php:257-262`, `app/Controllers/Order.php:225-229`, `app/Controllers/Order.php:407-460`

Test ตรวจ `/ordersListing` และ `/TrackingListing` ซึ่ง funnel เข้า `Order::listing()` เดียวกัน แต่ยังไม่ render `Order::reportTrackingListing()` และ `Order::print()` ซึ่งเป็น independent render paths ตาม source mapping

Code sweep ปัจจุบันยืนยันว่า report ใช้ default admin profile และ print render standalone จึงยังไม่พบ leakage จริง แต่ production-output regression ไม่มี test ตรึงสอง path นี้

**Minimal fix:** เพิ่ม negative order-asset assertions สำหรับ `/ReportTrackingListing` และ `/orders/91001/print`; ไม่ต้องไล่ทุก listing alias เพราะทุก alias ใช้ method เดียวกัน

## Source mapping และ caller sweep

| CI3 source caller | CI4 production caller | Profile ที่พบ | ผล review |
|---|---|---|---|
| `Order::add()` → `load_order_Views()` | `Order::newOrder()` | `order` | mapping ถูก แต่ interaction contract พัง |
| `Order::editOrdersOld()` → `load_order_Views()` | `Order::editForm()` | `order` | mapping ถูก แต่ interaction contract พัง |
| queue/listing ทุกสถานะ | `Order::listing()` ผ่าน aliases ทั้งหมด | default `admin` | ไม่มี order asset leakage จาก code path |
| `Order::ReportTrackingListing()` | `Order::reportTrackingListing()` | default `admin` | ไม่มี leakage จาก code path แต่ขาด negative render test |
| `Order::editOrders()` print | `Order::print()` | standalone | ไม่มี leakage จาก code path แต่ขาด negative render test |

`BaseController::layout(..., profile: 'order')` reuse `AdminLayoutPresenter`, `MenuStore`, escaping และ POST+CSRF logout ถูกทาง และไม่มี business query, upload persistence หรือ authorization logic ถูกคัดเข้า view

## Asset และ JavaScript verification

| รายการ | ผล |
|---|---|
| `style.css` | bytes ตรง CI3 pin |
| recursive `assets/img/icons.png` | bytes ตรง CI3 pin และ path resolution จาก `../img/icons.png` ถูก |
| browse JavaScript 5 ไฟล์ | bytes ตรง CI3 pin |
| `node --check` browse JavaScript 5 ไฟล์ | ผ่าน |
| script order | ตรง `footer_order.php` |
| shared/admin leakage จาก source | ไม่พบ; order assets อยู่ใน order partial/layout เท่านั้น |
| `addOrder.js` syntax | fail ที่ `customerTel`; เป็น known defect ที่ยังไม่มี signed disposition |
| `admin_addOrder.js` syntax | ผ่าน; มี active admin caller แต่ candidate ไม่โหลด |
| license provenance | third-party headers + `MIT.txt` มีหลักฐาน; custom assets ยังขาด durable first-party/license classification |

Pinned `script.js` มี whitespace เดิมจาก authority การคง bytes ตรง pin ถูกต้องกว่า rewrite; path-scoped `core.whitespace=-space-before-tab,trailing-space` ใช้กับ diff check ได้โดยไม่เปลี่ยนไฟล์ แต่ไม่ช่วยปิด functional findings ข้างต้น

## Verification ที่รัน

| Command | ผล | ความหมาย |
|---|---|---|
| `vendor/bin/phpunit --configuration phpunit.xml.dist --filter testCreateAndEditUseOrderLayoutWhileListingsStayOnAdminLayout tests/ci4/OrderHttpTest.php` | `OK (1 test, 42 assertions)` | ยืนยันเพียง rendered asset presence/order และสอง listing negatives |
| `vendor/bin/phpunit --configuration phpunit.xml.dist --filter 'testSharedRuntimeAssetClosureExistsAndIsGitTracked|testSharedFrontendDependencyPinsMatchCi3RuntimeArtifacts|testAdminShellUsesDatabaseBranchDataForHeaderAndAutocomplete' tests/ci4/MenuHttpTest.php` | `FAILURES! Tests: 3, Assertions: 1944, Failures: 1.` | asset bytes/version testsผ่าน แต่ tracked closure ล้มเพราะ candidate ยัง untracked และ scratch reportไม่ tracked |
| `vendor/bin/phpunit --configuration phpunit.xml.dist --filter testUnknownAndUnapprovedImplicitEntriesReturnReal404ForAnonymousAndAuthenticatedUsers tests/ci4/RouteHttpTest.php` | `OK (1 test, 20 assertions)` | ยืนยัน current 404 expectation เท่านั้น ไม่ใช่ signed retirement disposition |
| `node --check` browse JavaScript 5 ไฟล์ | ผ่าน | ยืนยัน syntax เท่านั้น ไม่ยืนยัน selector/global/API/network behavior |
| Python byte comparison ต่อ CI3 pin | ทั้ง 7 assets `True` | ยืนยัน exact bytes รวม recursive icon |

Packagist `HTTP/2 502` เป็น external blocker จริงและรายงานไม่อ้าง `ci-check` PASS ถูกต้อง แต่ evidence ปัจจุบันยังไม่พอให้ checkpoint ก่อน rerun เพราะมี source/spec findings อิสระจาก Packagist และ browser gate ยัง `NOT-VERIFIED`

## Checkpoint candidate ที่ถูกต้อง

Candidate source/test/asset มี **12 ไฟล์** ไม่ใช่ 13:

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

`task-7-report.md` เป็น ignored SDD scratch ห้ามนับเป็น commit candidate; durable evidence ใหม่ใต้ `outputs/reference/**` จะเป็น candidate เพิ่มต่างหากเมื่อสร้างและ review แล้ว

## Blockers ก่อน re-review

1. Resolve legacy upload interaction และ `/order/do_upload_multi` ด้วย signed disposition หรือ secure compatibility implementation
2. Resolve conditional `addOrder.js`/`admin_addOrder.js` behavior จาก active callers
3. คืน order header/content/footer boundary ให้ตรง CI3 hierarchy
4. ย้าย durable asset provenance/license evidence ออกจาก ignored report และแก้ tracked closure test
5. รัน focused/full gates, `ci-check` หลัง Packagist ฟื้น และ browser interaction บน current source identity
