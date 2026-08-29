# Re-review Task 7 fix round 3

เอกสารนี้ตรวจ fix round 3 แบบ read-only เทียบ brief, re-review รอบ 2, exact package, production adapter, Exact CI3 upload chain และ executable adversarial simulation โดยไม่แก้ source หรือเปลี่ยน Git state

## Verdict

| แกน | Verdict | เหตุผล |
|---|---|---|
| Late-context queue identity | **NOT ADDRESSED** | Setter ถูกติดบน `options` clone ที่ event `fileuploaddone` ส่งมา แต่ Exact `script.js` กำหนด `context` บน `data` object เดิมจาก callback `add` |
| Spec compliance | **FAIL** | Completion-before-context ยังทำให้ clicked preview ลบ File จาก final queue ไม่ได้ใน production event semantics จริง |
| Code quality | **CHANGES REQUIRED** | Finding เดิมยังเปิด 1 Important และพบ Important ใหม่ 1 รายการใน regression harness; ไม่พบ Critical ใหม่ |
| Focused regression | **FALSE GREEN** | Test ผ่านเพราะใช้ object เดียวสำหรับ completion และ late assignment ซึ่งไม่ตรง jQuery File Upload 5.26 |
| Server และ static gates | **PASS เฉพาะส่วนที่รันได้โดยไม่ stage** | `OrderHttpTest.php` และ PHPStan ผ่าน; full PHPUnit จาก real indexติด tracked-asset gateตามสถานะ untracked เดิม |
| Exact package | **PASS ด้านขอบเขต** | มี exact 21 paths ตรง manifest, ไม่มี extra หรือ missing path และไม่มี diff header นอก Task 7 |
| Exact CI3 bytes | **PASS** | SHA-256 ตรง pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` ครบ 9/9 |
| Browser gate | **BLOCKED** | ไม่มี authenticated browser proof; ห้ามตีความ Node simulation เป็น browser PASS |
| Exact approved candidate | **0 files** | Code finding ยังเปิด จึงไม่อนุมัติ path ใด |

## Finding matrix

| ประเด็นที่ตรวจ | ผล | หลักฐาน |
|---|---|---|
| Finding เดิม: completion ก่อน context | **NOT ADDRESSED** | `jquery.fileupload.js:483-488,736-755` สร้าง `options` clone; `script.js:20-23,42,66-67` กำหนด `context` บน original `data` |
| Context มีอยู่ก่อน completion | **ADDRESSED เฉพาะ branch นี้** | Context ถูก shallow-copy เข้า `options`; clicked preview ลบ queue item ถูกต้อง |
| Context มาช้าหลัง completion | **NOT ADDRESSED** | Adversarial run ได้ `REAL_CLONE_LATE_QUEUE=1` |
| Context ไม่มา | **ไม่เพิ่ม finding** | Queue คง File ที่ upload สำเร็จและไม่มี preview ให้คลิก; ไม่มี cross-bind แต่ยังไม่มี browser proof |
| Assignment หลายครั้งบน object เดียว | **ไม่ robust แต่ไม่มี Exact CI3 caller** | Setter bind assignment แรกเท่านั้น; assignment ที่สองไม่ bind ได้ `REPEATED_ASSIGN_SECOND_QUEUE=1` |
| Multiple/repeated/duplicate completion interleave | **NOT ADDRESSED ใน late-context branch** | Completion สลับลำดับของ File ชื่อซ้ำแล้ว late previews ทั้งสองลบไม่ได้ ได้ queue เหลือ 2 |
| Pending cancel ก่อน completion | **ADDRESSED ใน harness ปัจจุบัน** | Context ที่ไม่มี queue itemไม่ลบ completed File อื่น |
| Error/abort | **ADDRESSED ด้าน final queue** | `fileuploadfail` และ result status errorไม่เพิ่ม File; adversarial run ได้ `FAIL_ERROR_QUEUE=0` |
| Existing property descriptor | **ไม่มี production collision ที่พิสูจน์ได้** | Exact plugin สร้าง fresh extensible object; context ที่ clone มาเป็น writable/configurable/enumerable |
| `Object.defineProperty` exception | **มี exception guard gap แต่ production path ปัจจุบันไปไม่ถึง descriptor นี้** | Non-configurable adversarial objectทำให้ `TypeError`; exact plugin cloneไม่รักษา restrictive descriptor |
| Legacy browser compatibility | **BLOCKED ไม่ใช่ finding ใหม่** | Plain-object `Object.defineProperty` ไม่ครอบ browser legacy บางรุ่น แต่ adapter มี `DataTransfer` constructor และ writable `input.files` เป็น baseline ที่ใหม่กว่าอยู่แล้ว |
| Regression ขับ production inline adapter | **PARTIAL** | Test render production scriptจริง แต่จำลอง jQuery File Upload data identity ผิด |
| Exact package และ scope | **ADDRESSED** | 21 diff headersตรง expected manifest, ไม่มี extra หรือ missing path |
| Full gates | **PARTIAL/BLOCKED จาก no-stage constraint** | Real-index full suiteติด 9 untracked exact assets; ไม่สร้าง temporary indexเพราะคำสั่งห้าม stage |
| CI3 byte preservation | **ADDRESSED** | 9 assetsเป็น `MATCH` ทุกไฟล์ และ `script.js` hashตรง `9a455e73...8101ec` |

## Finding ที่ยังเปิด

### Important: Setter ถูกติดบน completion clone คนละ object กับ late context assignment

- **Scenario**: Callback `add` รับ object A แล้วเรียก `data.submit()`. Plugin สร้าง object B ด้วย `$.extend({}, this.options, data)` และยิง `fileuploaddone` ด้วย B. Adapter ติด setter ที่ `B.context`; ต่อมา `FileReader.onload` กำหนด `A.context`. Preview จึงไม่มี `orderQueueItem` และ clicked deleteไม่ลบ File จาก final queue
- **ตำแหน่ง**: `public/assets/js/browse/jquery.fileupload.js:483-488,694-708,736-755`, `public/assets/js/browse/script.js:20-23,42,66-67`, `app/Views/partials/order_upload.php:34-57,62-68`
- **Executable evidence**: Production inline adapter กับ shallow-clone event semantics ให้ `CLONE_IS_DISTINCT=true` และ `REAL_CLONE_LATE_QUEUE=1`; same-object control ให้ `SAME_OBJECT_LATE_QUEUE=0`
- **Minimal fix**: ดัก `context` บน original `fileuploadadd` data ก่อน Exact callback เรียก `data.submit()`, เก็บ state ด้วย File object identity และ bind current/late contextกับ queue itemเดียวกันเมื่อ completion สำเร็จ; ห้าม filename, polling หรือ arbitrary timeout
- **Class sweep**: Shared partialกระทบ create/edit, central/branch, single, multiple, repeated selection และ duplicate names. Early-context branchผ่าน; late-context และ interleaved duplicate branchยังค้าง. Failure/abortไม่เพิ่ม queue และ pending cancelไม่ลบ completed File อื่น

## Finding ใหม่

### Important: Regression harness บังคับ same-object semantics จึง false-green

- **Scenario**: Test เรียก `handlers.fileuploaddone(null, lateData)` แล้วกำหนด `lateData.context` บน object เดียวกัน ทำให้ setterทำงาน แต่ production pluginส่ง cloneจาก `_getAJAXSettings()` เข้า done eventและ Exact `script.js` ยังคง original object
- **ตำแหน่ง**: `tests/ci4/OrderHttpTest.php:655-770`, โดยเฉพาะ `739-749`; เทียบ `public/assets/js/browse/jquery.fileupload.js:483-488,736-755`
- **Executable evidence**: Focused testได้ `OK (1 test, 4 assertions)` แต่ adversarial production-adapter runเปลี่ยนเฉพาะ identityให้ตรง pluginแล้วได้ `REAL_CLONE_LATE_QUEUE=1`
- **Minimal fix**: Harness ต้องสร้าง original add data และ completion cloneแยกกันตาม shallow-copy semanticsจริง, ส่ง cloneเข้า `fileuploaddone`, กำหนด contextบน original และคลิก previewนั้น; เพิ่ม interleaved duplicate, early context, no context, fail/abort และ repeated assignment casesโดยยังรัน production inline adapterจริง
- **Class sweep**: Test ปัจจุบันขับ normal duplicate, pending cancel และ same-object late assignment แต่ไม่ขับ clone boundary, interleaved late duplicate หรือ setter exception semantics. Node `Object.defineProperty` ตรง modern browser plain-object semantics แต่ไม่แทน authenticated legacy browser proof

ไม่พบ Critical ใหม่

## Executable adversarial verification

ใช้ JavaScript จาก `app/Views/partials/order_upload.php` โดยตรง แล้วสลับ event orderและ data identityตาม Exact plugin source

```text
SAME_OBJECT_LATE_QUEUE=0
CLONE_IS_DISTINCT=true
DONE_DATA_EXTENSIBLE=true
DONE_CONTEXT_DESCRIPTOR_BEFORE=undefined
REAL_CLONE_LATE_QUEUE=1
CONTEXT_BEFORE_CLONE_QUEUE=0
CLONED_CONTEXT_DESCRIPTOR=true,true,true
REPEATED_ASSIGN_SECOND_QUEUE=1
REPEATED_ASSIGN_FIRST_QUEUE=0
INTERLEAVED_DUPLICATE_LATE_QUEUE=2
INTERLEAVED_DUPLICATE_ORDER=b,a
FAIL_ERROR_QUEUE=0
NONCONFIGURABLE_CONTEXT_THROW=TypeError
```

ผลสำคัญคือ control แบบ test ปัจจุบันผ่าน แต่ production clone semanticsยังเหลือ File 1 รายการ และ duplicate completionที่สลับลำดับเหลือ File 2 รายการโดยไม่ bindผิดข้าม preview เพราะไม่มี previewใดถูก bindเลย

## Verification ที่รันจริง

| Command/การตรวจ | ผล | ความหมาย |
|---|---|---|
| Focused production adapter regression | `OK (1 test, 4 assertions)` | ยืนยัน false-green ของ same-object harness ไม่ได้ปิด production clone branch |
| `OrderHttpTest.php` | `OK (74 tests, 1235 assertions)` | Server/order regressionทั้งหมดผ่าน |
| PHPStan | `[OK] No errors` | Static analysisผ่าน |
| Full PHPUnit จาก real index | `426 tests, 8980 assertions, 1 failure` | ล้มเฉพาะ tracked-asset gateเพราะ 9 exact assetsยัง untracked |
| `scripts/ci-check.sh` จาก real index | exit `1` หลัง PHPStan | Embedded full PHPUnitหยุด gateตาม failure เดียวกัน; ไม่ใช่ queue proof |
| Exact package manifest | `PACKAGE_COUNT=21`, `PACKAGE_PATHS_EXACT=True` | ไม่มี extra/missing path |
| Exact CI3 byte comparison | `MATCH` 9/9 | Exact assetsไม่เปลี่ยน bytes |
| `node --check public/assets/js/browse/script.js` | exit `0` | Exact browse script parseผ่าน |
| `git diff --check` เฉพาะ adapter/test | exit `0` | ไม่มี whitespace error |
| Real index ก่อน/หลัง | tree `c6ce38a8953cb1dedf08e35446b3195347139425`, cached diff exit `0` | ไม่มี stage, commit หรือ push |

Full PHPUnit แสดงข้อความต้นฉบับ:

```text
error: pathspec 'public/assets/css/style.css' did not match any file(s) known to git
Did you forget to 'git add'?
```

ข้อความนี้หมายถึง exact assetsยังไม่อยู่ใน real Git index ตามข้อห้าม stage ไม่ใช่ failure ของ server behavior. จึงไม่สร้าง temporary candidate indexเพื่อ rerun full gate และไม่ใช้ผล PASS จาก reportแทนการพิสูจน์ queue branchที่ adversarial runทำให้ RED โดยตรง

## Exact package, CI3 bytes และ scope

- Exact package SHA-256: `4c8842ea1991e95d58bcf3e67c61fa6c1378b351ee989c704a064e74fc41ae50`
- `order_upload.php` SHA-256: `ce0f0de39675a5644fbc808bd881cd548893386b607500eabba434875be322d9`
- `OrderHttpTest.php` SHA-256: `a8f95574363065f1166639f644632cf93b7b3098c1c4868d69b17fda5bfd29a1`
- Exact packageมี 21 pathsตาม Task 7 manifestและ route hunkเดียวจาก `task-7-route.patch`
- Diff headersไม่มี pathของ tracking, password reset, contact หรือ sourceนอก Task 7
- CI3 checkoutอยู่ที่ pinตรงและ clean; Exact CI3 assetsทั้ง 9 fileตรง SHA-256 ของ CI4 target

## Exact approved candidate

**0 files**

ยังไม่อนุมัติ pathใดจนกว่า production clone boundaryและ regression harness semantic mismatchจะถูกแก้และ re-reviewผ่าน

## Browser status

**BLOCKED**

ยังไม่มี authenticated browser proof สำหรับ native file select, drag/drop, FileReader timing, `DataTransfer`, repeated/multiple files, duplicate names, success/error, in-flight abort, post-completion delete, descriptor behaviorใน browser target และ final create/edit submit. Code candidateอนุมัติไม่ได้จาก code findingที่ยังเปิด แม้ browser gateถูกแยกสถานะไว้แล้ว
