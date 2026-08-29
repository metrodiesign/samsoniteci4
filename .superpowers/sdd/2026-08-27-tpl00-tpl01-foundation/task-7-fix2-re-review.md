# Re-review Task 7 fix round 2

เอกสารนี้ตรวจ Task 7 fix round 2 แบบ read-only เทียบ brief, review รอบก่อน, exact delta package, strict preservation spec และ CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`

## Verdict

| แกน | Verdict | เหตุผล |
|---|---|---|
| Spec compliance | **FAIL** | Adapter แก้การใช้ชื่อไฟล์เป็น key แล้ว แต่ยังมี race เมื่อ upload เสร็จก่อน `FileReader.onload` สร้าง `data.context`; preview ที่เกิดภายหลังไม่มี queue identity จึงลบ File จาก final queue ไม่ได้ |
| Code quality | **CHANGES REQUIRED** | พบ Important ใหม่ 1 รายการ; ไม่พบ Critical ใหม่ |
| Automated server proof | **PASS** | Focused tests, full PHPUnit, PHPStan, route contract, exact bytes และ full CI ผ่านบน exact temporary candidate |
| Executable adapter proof | **FAIL ต่อ timing branch** | Test ปัจจุบันผ่านเพราะ mock ส่ง `data.context` พร้อม completion เสมอ; production adapter simulation แบบ late context เหลือ File ใน queue หลังคลิกลบ |
| Browser gate | **BLOCKED** | Anonymous `/orders/new` ตอบ `401`; ไม่มี authenticated browser proof |
| Task 7 completion | **BLOCKED** | มี code finding เปิด 1 รายการและ browser gate ยังไม่ปิด |

## Finding matrix จากรอบก่อน

| Finding เดิม | Verdict | หลักฐาน |
|---|---|---|
| Critical: Browse chain ไม่มี contract ที่ทำงานได้ | **NOT ADDRESSED** | Route, server guard และ identity adapter มีแล้ว แต่ `order_upload.php:34-44` bind identity เฉพาะเมื่อ `data.context` มีอยู่ ณ completion; exact `script.js:20-67` สร้าง context ใน asynchronous `FileReader.onload` หลังเริ่ม `data.submit()` จึงยังมี final queue branch ที่ผิด |
| Important: Conditional validation scripts มี active caller | **ADDRESSED** | `order_legacy_scripts.php:5-9` เลือก exact `addOrder.js` หรือ `admin_addOrder.js` ตาม `BranchID`; create/edit central และ branch matrix ผ่าน |
| Important: `layout_order.php` เพิ่ม hierarchy ที่ไม่มีใน CI3 | **ADDRESSED** | Layout render page-owned contentตรงระหว่าง sidebar และ footer; create/edit เป็นเจ้าของ `.content-wrapper` คนละหนึ่งชั้น |
| Important: Candidate และ tracked-asset test พึ่ง ignored scratch report | **ADDRESSED** | Test อ้าง durable evidence ใต้ `outputs/reference/**`; exact temporary candidate มี 21 files และ tracked closure ผ่าน |
| Minor: Negative leakage test ไม่ขับ report/print | **ADDRESSED** | Test render `/ReportTrackingListing` และ `/orders/91001/print` จริง พร้อม negative assertions |
| Important: Duplicate filename ทำให้ delete preview กับ final queue คนละไฟล์ | **NOT ADDRESSED** | Object identity แก้ normal completion แล้ว แต่ late-context completion ไม่ bind preview node; คลิกลบ preview ยังปล่อย File เดิมใน final queue และ Node test ปัจจุบันไม่ขับ branch นี้ |
| Important: Edit replacement ทิ้ง orphan และไม่แสดง existing image ตาม CI3 | **ADDRESSED ตาม ruling** | หน้า edit render valid pipe-separated names ผ่าน `/order-image/{name}` พร้อม escaping; malformed namesไม่ render; no-upload, replacement, validation failure และ DB failure คง prior fileตาม CI3 ruling |
| Important: Test และ mutation claims ไม่ขับ browser queue contractจริง | **ADDRESSED เฉพาะการแยกหลักฐาน** | Reports แยก server, Node adapter และ browser ชัด และไม่อ้าง browser PASS; browser matrixยังระบุ `BLOCKED` |
| Important: Diff package ปน hunks นอก Task 7 | **ADDRESSED** | Exact package เท่ากับ temporary manifest 21 files; `Routes.php` มีหนึ่ง upload hunk และไม่มี tracking/password-reset hunks |

## Finding ใหม่

### Important: Completion ก่อน preview context ทำให้ clicked delete ไม่แตะ final queue

- **ตำแหน่ง**: `app/Views/partials/order_upload.php:34-44,48-54`, `public/assets/js/browse/script.js:20-67`, `tests/ci4/OrderHttpTest.php:655-757`
- **Scenario**: ผู้ใช้เลือก `camera.jpg`; upload response เสร็จก่อน `FileReader.onload` กำหนด `data.context`. Adapter ใส่ File ลง `target.files` แต่ไม่ bind `orderQueueItem`. เมื่อ preview ปรากฏแล้วผู้ใช้คลิกลบ delegated handler ได้ `undefined`, `files.indexOf(undefined)` เป็น `-1` และ final form ยังส่ง File ที่ UI ลบแล้ว. ชื่อซ้ำทำให้ acceptance เรื่อง exact clicked identity ยังผิดเช่นเดิมใน timing branch นี้
- **Executable evidence**: รัน production inline adapter โดยส่ง `fileuploaddone` ก่อนกำหนด context แล้วกำหนด contextและคลิกลบภายหลัง ได้ `QUEUE_AFTER_LATE_CONTEXT_DELETE=1`
- **เหตุที่ test false-green**: Harness ที่ `OrderHttpTest.php:717-718` ส่ง `firstContext` และ `secondContext` พร้อมทุก completion จึงไม่เคยขับลำดับจริงที่ context มาช้ากว่า response
- **Minimal fix**: เก็บ queue itemด้วย `data` หรือ File object identityทันที แล้ว defer การ bind ไปยัง `data.context` จน contextมีอยู่; ห้ามย้อนกลับไปใช้ filename. เพิ่ม executable regression ลำดับ completion-before-context แล้วคลิกลบ previewเดียวกัน
- **Class sweep**: กระทบ create/edit, central/branch และ shared partialเดียวกัน; กระทบ single, multiple, repeated selection, duplicate names, success completion, post-completion delete และ final association. Pending cancelก่อน completionยังไม่เพิ่ม File; failureไม่เพิ่ม queue. Report/printไม่ใช้ adapter

## Existing-image ruling

| Branch | ผลตรวจ |
|---|---|
| Valid single/multiple names | Render ผ่าน secure routeครบและรักษาลำดับ pipe-separated |
| URL/markup escaping | รับเฉพาะ `[a-f0-9]{32}.png`; attribute ผ่าน `esc(..., 'attr')` |
| Malformed/traversal/legacy names | ไม่ render |
| No upload | คง prior association และ prior file |
| Replacement success | เปลี่ยน DB associationเป็น names ใหม่และคง prior fileบน diskตาม CI3 ruling |
| Upload validation failure | คง prior association/file และลบเฉพาะไฟล์ใหม่ |
| DB update failure | คง prior association/file และลบเฉพาะไฟล์ใหม่ |

ไม่รื้อ storage contract และไม่เรียก prior fileว่า orphan

## Package, evidence และ safety

- Exact package มี 21 files: whole-file 20 pathsและ `Routes.php` 1 hunk
- Package ตรง temporary manifestแบบ byte/blob identity เมื่อสร้างด้วย `git diff --cached --unified=10`
- `Routes.php` ไม่มี tracking `(:any)` หรือ password-reset hunks
- Exact CI3 assets 9 filesเป็น `MATCH` ทุกไฟล์; `script.js` ไม่ถูกแก้
- Browse JavaScript 5 filesและ `admin_addOrder.js` ผ่าน `node --check`; `addOrder.js` คง pinned `SyntaxError` ที่ `customerTel`
- Durable evidence แยก historical Task 6 `109/103`, checkpoint `6799684` และ current Task 7 `118/21` ตรง Git stateที่ตรวจ
- Candidate-tree PII guard และ secret file policy ผ่าน; packageมีเฉพาะ synthetic `.invalid` email และไม่พบ secret/PII จริง
- Real Git index ก่อนและหลังเป็น `c6ce38a8953cb1dedf08e35446b3195347139425`; cached diff ว่าง

## Verification ที่รันจริง

| Command/การตรวจ | ผล | ความหมาย |
|---|---|---|
| CI3 `rev-parse HEAD` และ status | pin ตรง; dirty `0` | Authority ถูกต้องและ clean |
| Duplicate/existing-image focused | `OK (6 tests, 75 assertions)` | Assertions ปัจจุบันผ่าน แต่ไม่ขับ late-context race |
| Upload auth/CSRF/no-persistence/final server association focused | `OK (5 tests, 68 assertions)` | Round 1 server guards และ persistenceไม่ regress |
| `RouteHttpTest.php` | `OK (5 tests, 600 assertions)` | Route allowlistและ filtersผ่าน |
| Full PHPUnit บน exact temporary candidate | `OK (426 tests, 8982 assertions)` | Full automated suiteผ่าน |
| Full PHPStan | `[OK] No errors` | Static analysisผ่าน |
| Full `scripts/ci-check.sh` บน exact temporary candidate | PASS ทุก gate | Final runใช้ wrapperที่ unset `GIT_INDEX_FILE` สำหรับ CI3 checkout |
| Exact CI3 byte comparison | `MATCH` 9/9 | CSS, icon, browse chain และ validation scriptsตรง pin |
| JavaScript syntax contract | ตรง pinned behavior | Known `addOrder.js` defectยังคงเดิม |
| Package/manifest equality | `PACKAGE_EXACT=YES`, candidate `21` | Review packageมีเฉพาะ declared Task 7 delta |
| Production adapter late-context simulation | `QUEUE_AFTER_LATE_CONTEXT_DELETE=1` | ยืนยัน Fileค้างใน final queueหลัง previewที่มาช้าถูกคลิกลบ |
| Runtime route table | uploadเป็น POSTพร้อม `web-auth authorized:write csrf`; imageเป็น GETพร้อม `web-auth authorized:read` | Server route contractตรง report |
| Anonymous runtime probe | `ANONYMOUS_ORDER_FORM_HTTP=401` | ยืนยัน browser auth blockerเท่านั้น |
| Real index before/after | hashเดียวกัน; cached diffว่าง | Review ไม่ stage, commit หรือ push |

การรัน `scripts/ci-check.sh` ครั้งแรกถูก sandbox ปิดการอ่าน `.env.example`; การรันนอก sandboxรอบถัดไปพบ wrapperจับ CI3 pathแบบ exact stringไม่ครอบ pathที่มี `../`. หลังแก้ wrapperชั่วคราวให้จับ pathลงท้าย `samsoniteci3` คำสั่งเดิมผ่านทุก gate โดยไม่แก้ repository

## Exact approved candidate

**0 files**

ยังไม่อนุมัติ path ใดจนกว่า late-context queue identity finding จะถูกแก้และ re-review ผ่าน

## Browser status

**BLOCKED**

ยังไม่มี authenticated browser proof สำหรับ native file select, drag/drop, FileReader timing, progress knob, CSRF DOM sync, repeated/multiple files, duplicate names, success/error, in-flight abort, post-completion delete และ final create/edit submit
