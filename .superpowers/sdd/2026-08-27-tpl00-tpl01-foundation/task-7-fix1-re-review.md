# Re-review Task 7 fix round 1

เอกสารนี้ตรวจเฉพาะ Task 7 fix round 1 แบบ read-only เทียบ brief, review เดิม, implementer report, diff package, strict preservation spec และ CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`

## Spec compliance verdict

| แกน | Verdict | เหตุผล |
|---|---|---|
| Spec compliance | **FAIL** | Upload seam มี endpoint และ server guard แล้ว แต่ final queue/cancel contract ยังผิดเมื่อชื่อไฟล์ซ้ำ, edit replacement สร้าง orphan และ browser interaction ยังไม่มีหลักฐาน |
| Code quality | **CHANGES REQUIRED** | คงค้าง 1 Critical และ 1 Important จาก review เดิม พร้อม 4 Important ใหม่ |
| Automated verification | **PASS เฉพาะแกนที่รัน** | Focused, full PHPUnit, PHPStan, route, checksum และ syntax contract ผ่าน แต่ test ปัจจุบันไม่ขับ DataTransfer/cancel/delete ใน browser |
| Task 7 completion | **BLOCKED** | Browser gate ถูก permission block และ code findings ยังเปิด |

ไม่พบ Critical ใหม่ แต่ Critical เดิมเรื่อง browse interaction ยังปิดไม่ได้

## Verdict ต่อ finding เดิม

| Finding เดิม | Verdict | หลักฐาน |
|---|---|---|
| Critical: Browse chain ไม่มี contract ที่ทำงานได้ | **NOT ADDRESSED** | มี secure preview endpoint และ DOM adapter แล้ว แต่ `order_upload.php:47-53` ลบ final file ผิดตัวเมื่อชื่อซ้ำ, edit replacement ทิ้ง orphan และยังไม่มี browser proof สำหรับ preview/progress/abort/delete/final submit |
| Important: Conditional validation scripts มี active caller | **ADDRESSED** | `order_legacy_scripts.php:5-9` เลือก exact `addOrder.js` หรือ `admin_addOrder.js` ตาม `BranchID`; form id และ legacy field names ตรง caller; exact hashes ตรง CI3 pin |
| Important: `layout_order.php` เพิ่ม hierarchy ที่ไม่มีใน CI3 | **ADDRESSED** | `layout_order.php:158-168` render page-owned content ตรงระหว่าง sidebar กับ footer; create/edit เป็นเจ้าของ `.content-wrapper` คนละหนึ่งชั้น |
| Important: Candidate และ tracked-asset test พึ่ง ignored scratch report | **NOT ADDRESSED** | Test ไม่อ้าง scratch reportแล้วและ provenance ถูกย้ายจริง แต่ durable evidence ยังประกาศ Git state/candidate ก่อน Task 6 commit เป็นค่าปัจจุบันที่ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:9-19,48-71,110-131` |
| Minor: Negative leakage test ไม่ขับ report/print | **ADDRESSED** | `OrderHttpTest.php:670-682` render `/ReportTrackingListing` และ `/orders/91001/print` จริง พร้อม negative assertions |

## Findings ใหม่

### Important: Duplicate filename ทำให้ delete preview กับ final queue คนละไฟล์

- **ตำแหน่ง**: `app/Views/partials/order_upload.php:47-53`
- **Concrete failure scenario**: ผู้ใช้เลือกไฟล์สองไฟล์ที่ชื่อ `camera.jpg` เหมือนกันแต่ bytes ต่างกัน แล้วกดลบ preview ตัวที่สอง; exact `script.js` ลบ DOM ตัวที่กด แต่ adapter ใช้ `findIndex(file.name === name)` จึงลบ File ตัวแรกจาก `DataTransfer` และส่งตัวที่สองต่อ ทั้งที่ UI แสดงว่าตัวที่สองถูกลบ
- **Minimal fix**: ผูก File กับ `data.context` หรือ preview node ด้วย identity เฉพาะ แล้วลบจาก queue ตาม node ที่กด ห้ามใช้ client filename เป็น key และห้ามแก้ exact `script.js`
- **Class sweep**: ตรวจ create/edit, central/branch, repeated selection, drag/drop, in-flight abort และ post-completion delete; bug อยู่ที่ post-completion delete เมื่อมี duplicate names ทุก caller ที่ใช้ partial ร่วมกัน

### Important: Edit replacement ทิ้ง orphan และไม่แสดง existing image ตาม CI3

- **ตำแหน่ง**: `app/Controllers/Order.php:361-407`, `app/Orders/OrderStore.php:109-179`, `app/Views/order_edit.php:46-162`, `tests/ci4/OrderHttpTest.php:1895-1956`
- **Concrete failure scenario**: order มี random PNG เดิมหนึ่งไฟล์ ผู้ใช้อัปโหลด replacement แล้ว edit สำเร็จ; DB เปลี่ยน `detailImage` เป็นชื่อใหม่ แต่ไฟล์เดิมยังอยู่บน disk โดยไม่มี row อ้างถึง ขณะหน้า edit ไม่ render existing image ที่ CI3 แสดงไว้ จึงทั้งสร้าง orphan และเปลี่ยน observable edit behavior
- **Minimal fix**: render existing valid image names ผ่าน `/order-image/{name}`; หลัง DB update สำเร็จเท่านั้นให้ลบ prior valid random names และแก้ test ที่ปัจจุบันยืนยันว่า old file ต้องค้างอยู่ ห้ามลบ prior files เมื่อ no-upload หรือ update fail
- **Class sweep**: create ไม่มี prior image; edit no-upload ต้องเก็บเดิม, edit success with replacement ต้องลบเดิม, validation/DB failure ต้องเก็บเดิมและลบเฉพาะไฟล์ใหม่; ตรวจ central/branch แล้ว; soft-delete ยังมี row association จึงไม่ใช่ orphan class เดียวกัน; report/print เป็น read paths

### Important: Test และ mutation claims ไม่ขับ browser queue contract จริง

- **ตำแหน่ง**: `tests/ci4/OrderHttpTest.php:604-653,900-966`, `app/Views/partials/order_upload.php:21-54`, `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix1-report.md:230-244`
- **Concrete failure scenario**: full PHPUnit ทั้ง 423 tests ผ่าน แม้ duplicate-name delete จะลบ File ผิดตัว เพราะ render test ตรวจเพียง string เช่น `new DataTransfer()` และ handler selector ส่วน final persistence tests inject uploaded files ตรงเข้า request โดย bypass preview adapter ทั้งหมด
- **Minimal fix**: ถอน mutation claims เรื่อง DataTransfer/final association/cancel-delete จนกว่าจะมี executable browser proof; เมื่อ permission พร้อมให้ขับ single/repeated files, duplicate names, CSRF refresh, preview/progress, in-flight abort, post-completion delete และ final create/edit persistence บน current source identity
- **Class sweep**: server tests ขับ auth, CSRF, token/image validation, response และ no-preview-persistence จริง; ช่องว่างอยู่ทั้ง create/edit, central/branch, single/multiple/repeated files, duplicate names, success/error/abort และ CSRF refresh

### Important: Diff package ปน hunks นอก Task 7

- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix1-review.diff:43-46,90-93`
- **Concrete failure scenario**: หาก gitops stage ทั้ง 21 candidate pathsตาม package จะรวมการเปลี่ยน tracking route จาก `(:segment)` เป็น `(:any)` และเพิ่ม password-reset aliases ซึ่งไม่อยู่ใน Task 7 fix brief ทำให้ checkpoint Task 7 พา auth/public route changes ที่ไม่ได้ review ใน scope นี้เข้า commit
- **Minimal fix**: regenerate review package และ temporary candidate จาก clean Task 6 checkpoint หรือ stage เฉพาะ Task 7 hunksหลัง concurrent tasks มี checkpoint แยกแล้ว; ห้ามใช้ whole-file candidate ปัจจุบันกับ `app/Config/Routes.php`
- **Class sweep**: กวาดครบ 21 package files; hunks นอก brief ที่ชัดเจนพบใน `app/Config/Routes.php` สองกลุ่มดังกล่าว ส่วน Order, image store, order views/assets/tests และ closure evidenceโยงกลับ finding เดิมได้

## Durable evidence และ candidate closure

Durable evidence มี checksum, CI3 provenance และ license classification ของ order assetsครบ 9 ไฟล์ และ exact bytes ตรง CI3 pinทุกไฟล์ แต่สถานะ Git ในเอกสารไม่เป็น current state:

- `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:9-19` ยังระบุ tracked 16, untracked 102 และ candidate 112
- HEAD ปัจจุบันคือ `6799684 wip(strict-template): t6 shared assets passed`; shared closure เดิมถูก commit แล้ว
- การจำลอง temporary index จาก 21 pathsใน diff packageให้ candidate count 21 และ tracked closure test ผ่าน
- เอกสารควรแยก historical Task 6 candidate ออกจาก current Task 7 delta เพื่อไม่ให้คำว่า exact candidate ชี้ชุดผิด

ไม่พบ secret pattern ใน diff package; email ที่พบมีเพียง `customer@example.invalid` ซึ่งเป็น synthetic test data ไม่ใช่ PII จริง

## Verification ที่รันจริง

| Command/การตรวจ | ผล | ความหมาย |
|---|---|---|
| ยืนยัน CI3 HEAD | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` | authority repo ตรง pin |
| Focused Order tests 10 รายการ | `OK (10 tests, 212 assertions)` | server auth/CSRF/validation/no persistence, layout, leakage และ edit image branches ผ่านตาม assertions ปัจจุบัน |
| `tests/ci4/RouteHttpTest.php` | `OK (5 tests, 600 assertions)` | POST-only allowlist และ bare/unknown 404 ผ่าน |
| `php spark routes` | endpoint เป็น POST พร้อม `web-auth authorized:write csrf` | runtime route table ตรง filter contract |
| Temporary-index Menu tests 3 รายการ | `OK (3 tests, 2172 assertions)` | exact 21-path candidate ทำให้ closure, checksum และ syntax gates ผ่าน โดย real index ไม่เปลี่ยน |
| Full PHPUnit ผ่าน temporary index | `OK (423 tests, 8955 assertions)` | automated suite เขียว แต่ไม่หักล้าง browser/queue findings |
| Full PHPStan | `[OK] No errors` | static analysis ผ่านด้วย `phpstan.neon.dist` |
| Browse JS 5 files และ `admin_addOrder.js` | `node --check` ผ่าน | syntax เท่านั้น |
| Exact `addOrder.js` | `SyntaxError: Unexpected identifier 'customerTel'` | known CI3 parse defect ถูกคง bytes ตรง pin; error อยู่ใน script tag แยกและยังไม่มี browser proof ของ scripts ถัดไป |
| Exact asset byte comparison 9 files | `MATCH` ทุกไฟล์ | CSS, icon, browse chain และ validation scripts ตรง CI3 pin |
| Duplicate-name queue simulation | หลังสั่งลบ preview ตัวที่สอง queue ยังเหลือ object ตัวที่สอง | ยืนยัน adapter ลบ object ตัวแรกตามชื่อ ไม่ใช่ตัวที่ผู้ใช้กด |
| Secret/PII scan | secret 0; synthetic `.invalid` email 1 | ไม่พบ secret หรือ PII จริงใน package |

การเรียก PHPStan ครั้งแรกด้วย `phpstan.neon` ล้มเพราะไฟล์ config ไม่มีอยู่ จากนั้นรันใหม่ด้วย `phpstan.neon.dist` และผ่าน

ไม่ได้รัน `scripts/ci-check.sh` ซ้ำในการ re-review นี้ เพราะ full PHPUnit, PHPStan และ focused gatesเพียงพอพิสูจน์ findings ซึ่งเป็น browser/runtime queue และ package-scope issues ไม่ใช่ automated gate failure

## Exact approved candidate

**0 files**

ยังไม่อนุมัติ candidate ใดจนกว่า Critical เดิม, 5 Important ที่เปิดอยู่ทั้งหมด และ package scope จะถูกแก้แล้ว re-review ใหม่

## Browser gate status

**BLOCKED**

Browser gate ถูก permission block ตาม brief จึงไม่มี current-source proof สำหรับ file select, preview, progress, DataTransfer, CSRF refresh, repeated/multiple files, duplicate names, in-flight abort, post-completion delete, edit existing image และ final create/edit association

Code review verdict เป็น **FAIL / CHANGES REQUIRED** แยกจาก browser completion verdict และห้ามประกาศ Task 7 PASS
