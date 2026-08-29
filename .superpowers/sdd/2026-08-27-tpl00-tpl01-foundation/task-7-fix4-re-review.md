# Re-review Task 7 fix round 4

เอกสารนี้ตรวจ fix round 4 แบบ read-only เทียบ brief, re-review รอบ 3, exact package, production adapter, Exact jQuery Widget/File Upload/glue source และ regression harness โดยไม่แก้ source หรือเปลี่ยน Git index

## Verdict

| แกน | Verdict | เหตุผล |
|---|---|---|
| Clone-boundary finding เดิม | **ADDRESSED** | `fileuploadadd` เห็น original A ก่อน option callback และ completion clone B ใช้ File identity เดียวกัน; late-context simulation ลบ queue ได้เหลือ 0 |
| False-green harness finding เดิม | **NOT ADDRESSED โดยรวม** | same-object defect เดิมถูกแก้แล้ว แต่ harness ยังไม่ขับ exact option callbacks, multi-file `data.files` หรือ File object เดิมที่ submit ซ้ำ จึงยังเขียวทั้งที่ production edge cases ล้ม |
| Spec compliance | **FAIL** | พบ Important ใหม่ 2 รายการใน production adapter/glue contract และ Important ใหม่ 1 รายการใน harness |
| Code quality | **CHANGES REQUIRED** | ไม่พบ Critical ใหม่; พบ Important ใหม่ 3 รายการ |
| Server/auth/CSRF/static regression | **PASS เฉพาะส่วนที่รันได้จาก real index** | Focused server, `OrderHttpTest.php`, PHPStan, package scope และ Exact CI3 hashes ผ่าน |
| Full PHPUnit จาก real index | **BLOCKED ด้วย tracked-asset gate เดิม** | 426 tests รันครบ แต่ล้ม 1 test เพราะ exact assets 9 files ยังไม่อยู่ใน real Git indexตามข้อห้าม stage |
| Exact approved candidate | **0 paths** | Production code findings และ harness finding ยังเปิด |
| Browser gate | **BLOCKED** | ไม่มี authenticated browser proof และ review นี้ไม่อ้าง Node simulation เป็น browser PASS |

## Finding matrix

| ประเด็นที่ตรวจ | ผล | หลักฐาน |
|---|---|---|
| jQuery Widget `_trigger` ordering | **ADDRESSED** | `jquery.ui.widget.js:464-490` เรียก `this.element.trigger(event, data)` ก่อน `callback.apply(...)` |
| `fileuploadadd` รับ original A ก่อน `submit()` | **ADDRESSED** | `jquery.fileupload.js:811-848` ส่ง `newData` เข้า `_trigger('add')`; Exact `script.js:20-23,66-67` เริ่ม FileReader แล้วเรียก `data.submit()` ใน option callback |
| Completion B แยก object แต่ share File identity | **ADDRESSED** | `_getAJAXSettings()` ที่ `jquery.fileupload.js:483-488` shallow-clone data; simulation ได้ `CLONE_DISTINCT=true` และ `FILE_IDENTITY_SHARED=true` |
| Completion-before-context queue/delete | **ADDRESSED** | Production adapter simulation ได้ `REAL_CLONE_LATE_QUEUE=0` |
| Early context | **ADDRESSED ใน adapter branch** | Enumerable getter ทำให้ context ที่มีอยู่ก่อน clone ถูก copy และ state bind queue item ได้ |
| Late context สำหรับ Exact progress/fail callback | **NOT ADDRESSED** | B จับค่า `context` ก่อน FileReader onload; Exact callbacks ยังอ่าน `B.context` ที่ไม่มีและ throw |
| Missing context | **NOT ADDRESSED ครบ chain** | Adapter event handlerไม่ throw แต่ Exact `script.js` fail callback throw เมื่อ `data.context` ยังไม่มี |
| Reassigned context | **PARTIAL** | Setter bind context ใหม่ได้ แต่ context เก่ายังคง `orderQueueItem`; Exact CI3 callerกำหนดครั้งเดียว จึงไม่ยกระดับเป็น finding แยก |
| Multi-file `data.files` ใน A เดียว | **NOT ADDRESSED** | Adapterสร้าง stateและ queue itemจาก `data.files[0]` เท่านั้น; simulationได้ `MULTI_DATA_HAS_SECOND=false` |
| Same File object submit ซ้ำ | **NOT ADDRESSED** | WeakMap slotถูก overwrite; simulation bindเฉพาะ submissionที่สองและ queueเพียง 1 item |
| Repeated completion | **ADDRESSED สำหรับ stateเดียว** | `state.item` guard ป้องกัน queue itemซ้ำ |
| Duplicate names, interleaved distinct File objects | **ADDRESSED** | Stateใช้ object identity ไม่ใช้ filename; current regression ครอบ completionสลับลำดับ |
| Fail/abort ไม่เพิ่ม final queue | **ADDRESSED เฉพาะ adapter queue** | `fileuploadfail` ไม่ push file และ error result returnก่อนสร้าง item แต่ Exact option callbackยัง throw |
| Pending cancel | **ADDRESSED** | Contextที่ไม่มี queue itemไม่ลบ completed Fileอื่น |
| Memory lifetime | **ไม่พบ finding** | WeakMapไม่ยึด keyไว้เอง; successful Fileถูกยึดตามอายุ queueจน delete/page unload และ failed stateหมดอายุเมื่อ plugin/data referencesหมด |
| WeakMap browser support/fallback | **ไม่พบ code finding; browserยัง BLOCKED** | Repoไม่มี explicit browser baseline; adapterต้องใช้ `DataTransfer` constructorและ writable `input.files` อยู่แล้ว ซึ่งเป็น baselineที่เข้มกว่า WeakMap จึงไม่มีหลักฐานว่าต้องเพิ่ม fallbackเฉพาะ WeakMap |
| Handler registration orderกับ Exact `script.js` | **ADDRESSED** | Inline adapterอยู่ใน page contentที่ `layout_order.php:158`; browse chainและ `script.js` โหลดภายหลังที่ `layout_order.php:168` และ `order_legacy_scripts.php:10-14` |
| Node harnessขับ production inline adapter | **ADDRESSED** | Test render partialและแทรก scriptจริงจาก view |
| Node harnessจำลอง full plugin/widget/glue semantics | **NOT ADDRESSED** | Harnessเรียก handlersเองและไม่โหลด Exact `script.js`, option callback หรือ `_trigger` ordering |
| Auth/authorization/CSRF | **ADDRESSED** | Routeมี `web-auth`, `authorized:write`, `csrf`; focused testsยืนยัน anonymous 401 และ CSRF rejectionก่อน validation |
| Server associationและ persistence | **ADDRESSED** | Preview endpoint validateอย่างเดียว ไม่ persistหรือเปิดเผย filename; final create/editรับ `detail_image[]` และใช้ `OrderImageStore` |
| Exact assets | **ADDRESSED** | SHA-256 ตรง pinครบ 9/9 และ current blobsของ plugin/widget/glueตรง exact package b-side |
| Package scope | **ADDRESSED** | Exact packageมี 21 paths, headersตรงกันทั้งหมด, route patch 1 hunk และไม่มี pathนอก Task 7 groups |

## Finding ใหม่

### Important 1: Late contextไม่ถูก bridgeไป completion B สำหรับ Exact progress/fail callbacks

- **Scenario**: `fileuploadadd` ติด getter/setterบน A แล้ว Exact option callbackเริ่ม FileReaderและเรียก `submit()` ทันที Bจึงถูกสร้างตอน getterยังคืน `undefined` ต่อมา onloadกำหนด contextบน A แต่ Bไม่เปลี่ยน เมื่อ `_trigger('progress')` หรือ `_trigger('fail')` เรียก Exact option callback จึงอ่าน `data.context.find(...)` หรือ `data.context.addClass(...)` แล้ว throw
- **ตำแหน่ง**: `app/Views/partials/order_upload.php:34-46,54-77`, `public/assets/js/browse/jquery.fileupload.js:483-488,694-715,736-755`, `public/assets/js/browse/jquery.ui.widget.js:464-490`, `public/assets/js/browse/script.js:20-23,42,66-67,76-93`
- **Executable evidence**: Simulationที่โหลด production adapterและ Exact `script.js` ได้ `ADD_ORDER=event:add>callback:add`, `CLONE_DISTINCT=true`, `FILE_IDENTITY_SHARED=true` และ `FAIL_BEFORE_CONTEXT_THROW=TypeError:Cannot read properties of undefined (reading 'addClass')`
- **Minimal fix**: ให้ Aมี stable jQuery context bridgeตั้งแต่ก่อน option callbackเพื่อให้ B shallow-copy referenceเดียวกัน แล้ว retarget bridgeไป real contextเมื่อ setterรับค่า ห้าม polling, timeout หรือแก้ Exact CI3 asset
- **Class sweep**: ตรวจ create/edit, central/branch, progress, fail, abort, success done, early/late/missing context และ event-before-callback orderingแล้ว done queue pathผ่าน แต่ progress/fail/abort late-context pathยังล้ม

### Important 2: WeakMapหนึ่ง slotต่อ Fileไม่รองรับ identityเดิมซ้ำหรือ multi-file A

- **Scenario**: Original A1 และ A2 submit File objectเดียวกันก่อน completion WeakMap entryของ A1ถูก A2 overwrite เมื่อ B1 completeจึงหยิบ stateของ A2 ทำให้ previewแรกไม่ bindและสอง successful submissionsเหลือ queue itemเดียว อีกกรณี Aมี `data.files=[F1,F2]` แต่ `observeContext()` และ completionใช้เพียง index 0 จึงทำ F2หายจาก final form queue
- **ตำแหน่ง**: `app/Views/partials/order_upload.php:34-36,54-73`
- **Executable evidence**: Production adapter simulationได้ `SAME_FILE_TWICE_QUEUE=1`, `SAME_FILE_FIRST_BOUND=false`, `SAME_FILE_SECOND_BOUND=true`, `SAME_FILE_FIRST_DELETE_QUEUE=1`, `MULTI_DATA_QUEUE_COUNT=1` และ `MULTI_DATA_HAS_SECOND=false`
- **Minimal fix**: ผูก stateกับ original add operationและให้ markerที่ไม่ชนกัน shallow-copyไป B พร้อมเก็บ File listทั้งชุด; หากยังใช้ WeakMapตาม File identity ต้องเก็บ occurrence collection ไม่ใช่ overwriteหนึ่ง state และ clickต้องลบ operation/groupที่ bindตรงตัว
- **Class sweep**: ตรวจ distinct Fileชื่อซ้ำ, interleaved completion, repeated selection, same File identityซ้ำ, multi-file A, repeated completion, pending cancel และ deleteแล้ว distinct File pathผ่าน แต่ same-identity/multi-file cardinalityยังล้ม

### Important 3: Regression harnessยัง false-greenต่อ Exact trigger/glueและ cardinality semantics

- **Scenario**: Harness extractเฉพาะ inline adapterแล้ว `original(file)` สร้าง singleton `files` และเรียก `handlers.fileuploadadd` เอง จากนั้นเรียก `fileuploaddone/fileuploadfail` โดยไม่เรียก Exact option callbacks จึงไม่เห็น progress/fail exception, same File overwrite หรือ multi-file loss แม้ testผ่าน
- **ตำแหน่ง**: `tests/ci4/OrderHttpTest.php:655-817`, โดยเฉพาะ `665-720,727-796`; เทียบ `public/assets/js/browse/jquery.ui.widget.js:464-490` และ `public/assets/js/browse/script.js:20-93`
- **Executable evidence**: Current focused testได้ `OK (1 test, 4 assertions)` แต่ augmented production simulationได้ failure outputsใน Important 1 และ 2 โดยไม่แก้ adapter
- **Minimal fix**: ให้ harnessโหลด Exact `script.js` หรือจำลอง `_trigger` ตาม sourceโดยเรียก event handlerก่อน option callback, ให้ `submit()` สร้าง shallow clone Bจริง และเพิ่ม assertionsสำหรับ progress/failก่อน context, same File identityซ้ำ และ multi-file `data.files`
- **Class sweep**: Current harnessครอบ early/late A-B clone, distinct duplicate names, interleaved completion, fail/abort queue, pending cancel, missing contextและ repeated completion แต่ไม่ครอบ option callback chain, progress callback, same-identity collisionหรือ multi-file A

ไม่พบ Critical ใหม่

## Adversarial verification

Simulationใช้ JavaScriptจาก production partialและโหลด Exact `public/assets/js/browse/script.js` โดยจำลอง Widget `_trigger` เป็น event handlerก่อน option callback และให้ `submit()` สร้าง shallow completion clone

```text
ADD_ORDER=event:add>callback:add
CLONE_DISTINCT=true
FILE_IDENTITY_SHARED=true
REAL_CLONE_LATE_QUEUE=0
SAME_FILE_TWICE_QUEUE=1
SAME_FILE_FIRST_BOUND=false
SAME_FILE_SECOND_BOUND=true
SAME_FILE_FIRST_DELETE_QUEUE=1
SAME_FILE_SECOND_DELETE_QUEUE=0
MULTI_DATA_QUEUE_COUNT=1
MULTI_DATA_HAS_SECOND=false
FAIL_BEFORE_CONTEXT_THROW=TypeError:Cannot read properties of undefined (reading 'addClass')
NODE_EXIT=0
```

ผลนี้ยืนยันว่า clone-boundary root causeเดิมปิดแล้ว แต่ยังมี failure classที่ current harnessไม่ขับ

## Verification ที่รันจริง

| Command/การตรวจ | ผล | ความหมาย |
|---|---|---|
| Focused production adapter regression | `OK (1 test, 4 assertions)` | Same-object false-greenเดิมถูกแก้ แต่ยังไม่ครอบ findingsใหม่ |
| Focused upload server regressions | `OK (5 tests, 57 assertions)` | Auth, CSRF, preview validation และ upload server pathsที่เลือกผ่าน |
| `OrderHttpTest.php` | `OK (74 tests, 1235 assertions)` | Order/server regressionทั้งหมดผ่าน |
| PHPStan | `[OK] No errors` | Static analysisผ่าน |
| Full PHPUnit จาก real index | `426 tests, 8980 assertions, 1 failure` | ล้มเฉพาะ tracked-asset gateเพราะ exact assets 9 filesยัง untracked |
| PHP syntax, Node parse, diff check | exit `0` | Adapter PHP, Exact `script.js` และ whitespaceผ่าน |
| Exact CI3 SHA-256 | `MATCH` 9/9 | Exact assetsไม่เปลี่ยน bytes |
| Exact package | SHA-256 `e8f8528f56a6802d20cb6447471b99efba9d58f1a427c4953157af341e59ae69`, 21 paths | Package headers/scopeตรงและ core current blobsตรง b-side |
| Real Git index baseline | tree `c6ce38a8953cb1dedf08e35446b3195347139425`, cached diffว่าง | ไม่มี stage, commit หรือ push |

Full PHPUnit failureข้อความต้นฉบับ:

```text
error: pathspec 'public/assets/css/style.css' did not match any file(s) known to git
Did you forget to 'git add'?
```

ข้อความนี้หมายถึง exact assetsยังไม่อยู่ใน real Git indexตามข้อห้าม stage ไม่ใช่ server behavior failure และ reviewนี้ไม่สร้าง temporary indexเพื่อแทนหลักฐานของ report

## Exact approved candidate

**0 paths**

ยังไม่อนุมัติ pathใดจนกว่า Important 1-3 จะปิดและ regression harnessทำให้ failure scenariosข้างต้นเป็น GREENจริง

## Browser status

**BLOCKED**

ยังไม่มี authenticated browser proofสำหรับ native file select, drag/drop, FileReader timing, progress knob, WeakMap/DataTransfer behavior, repeated/multiple files, duplicate names, same File identityซ้ำ, success/error, in-flight abort, pending cancel, post-completion delete และ final create/edit submit Node simulationเป็น code evidenceเท่านั้น ไม่ใช่ browser PASS
