# ผลตรวจซ้ำขั้นสุดท้าย Task 7 fix round 5

เอกสารนี้ตรวจ Task 7 fix round 5 แบบ read-only เทียบ brief, re-review รอบ 4, exact package, production adapter, Exact Widget/File Upload/glue source และ harness เต็ม โดยแยกคำตัดสิน code candidate ออกจาก authenticated browser blocker อย่างชัดเจน

## คำตัดสิน

| แกน | Verdict | เหตุผล |
|---|---|---|
| Spec compliance | **FAIL** | Important 1 และ 2 เดิมปิด root cause หลักแล้ว แต่ Important 3 ยัง false-green ต่อ Exact DOM และ terminal ordering |
| Code candidate | **CHANGES REQUIRED** | ไม่พบ Critical ใหม่ แต่พบ Important ใหม่ 2 รายการที่เป็น load-bearing และ reproduce ได้จริง |
| Server, auth, CSRF และ association | **PASS ในขอบเขต automated tests** | Focused server matrix และ `OrderHttpTest.php` เต็มผ่าน |
| Exact assets และ package scope | **PASS** | Exact CI3 hashes ตรง 9/9, package มี 21 unique paths และ current blobs ตรง b-side 20 whole-file paths พร้อม route hunk เดิม |
| Full PHPUnit จาก real index | **BLOCKED ด้วย tracked-asset gate** | รันครบ 427 tests แต่ล้ม 1 test เพราะ exact assets 9 files ยัง untracked ตามข้อห้าม stage |
| Exact approved candidate | **0 paths** | Production behavior และ harness findings ยังเปิด |
| Authenticated browser matrix | **BLOCKED** | ไม่มี credential และไม่อ้าง isolated browser simulation เป็น application browser PASS |

Code candidate ถูกปฏิเสธจาก code findings ไม่ใช่จาก browser blocker

## Finding matrix ของ Important 1–3

| Finding เดิม | ผล | หลักฐาน |
|---|---|---|
| Important 1: stable context bridge ข้าม A/B clone | **ADDRESSED** | Actual jQuery 1.10.2 simulation ยืนยัน marker enumerable, A/B share state และ share jQuery bridge reference; empty bridge รองรับ `find`, `text`, `addClass`, `knob` โดยไม่ throw และ retarget ซ้ำโดย object identity ไม่เปลี่ยน |
| Important 2: operation occurrence และ multi-file cardinality | **ADDRESSED** | State อยู่บน original add operation ไม่ใช้ File-global slot; focused harness ผ่าน same File identity สอง operation, multi-file group, duplicate names, interleaved completion, group delete และ order preservation |
| Important 3: harness ต้องไม่ false-green ต่อ Exact chain | **NOT ADDRESSED** | Harness โหลด Exact `script.js` แต่จำลอง DOM, jQuery, Widget และ File Upload เอง; fake preview ใส่ `input` เป็น child ของ `LI` ต่างจาก Exact fragment ที่ browser และ jQuery 1.10.2 parse เป็น top-level `INPUT` กับ `LI` แยกกัน |

## Finding ใหม่

### Important 1: Exact progress และ knob path ไม่แตะ preview จริง แต่ harness ทำให้เขียว

- **Scenario**: Exact glue สร้าง fragment ที่ขึ้นต้นด้วย hidden `INPUT` แล้วตามด้วย `LI`; jQuery 1.10.2 ได้ collection สอง root และ `tpl.find('input')` คืน 0 เพราะ `find()` ไม่รวม root element
- **ผล production**: `tpl.find('input').knob()` ไม่สร้าง knob และ Exact progress callback `data.context.find('input').val(...).change()` ไม่อัปเดต element ใด แม้ stable bridge retarget สำเร็จ
- **ตำแหน่ง**: `public/assets/js/browse/script.js:20-45,76-93`, `app/Views/partials/order_upload.php:34-51`, `tests/ci4/OrderHttpTest.php:695-753,858-895`
- **Executable evidence**: Isolated actual chain ใช้ jQuery 1.10.2 พร้อม Exact Widget, File Upload, knob และ `script.js` ได้ `contextTags=["INPUT","LI"]`, `findInput=0`, `liInput=0`, `canvas=0`; หลัง success final progress ยังได้ `canvas=0`
- **False-green cause**: `previewCollection()` ใน harness สร้าง `root.children.input` ภายใน `LI` จึงทำให้ assertions `PROGRESS_DID_NOT_REACH_PREVIEW` และ `PROGRESS_DID_NOT_CHANGE_PREVIEW` ผ่านบน DOM ที่ production ไม่มี
- **Minimal fix**: ให้ adapter normalize top-level progress input เข้า preview `LI` ใน context setter ก่อน Exact บรรทัดถัดไปเรียก `tpl.find('input').knob()` และให้ harness parse Exact fragmentด้วย real jQuery DOM หรือจำลองสอง root ตามจริง
- **Class sweep**: ตรวจ early/late/missing/reassigned context, direct `find`, `filter`, `text`, `addClass`, `knob`, final progress-before-done, success, fail และ DOM removal แล้ว fail อยู่ที่ progress/knob path; error class และ queue binding บน `LI` ยังทำงาน

### Important 2: Operation state ไม่บันทึก terminal outcome

- **Scenario A**: Upload fail ก่อน FileReader สร้าง context Exact fail callbackทำงานบน empty bridgeโดยไม่ throw แต่ไม่มี stateจำ failure; เมื่อ context มาทีหลัง preview จึงค้าง `working` และไม่มี `error`
- **Scenario B**: First done ถูกปฏิเสธเพราะเกิน 5 files หรือ `result.status !== 'success'` แล้ว duplicate done ภายหลังสามารถเปลี่ยน operation เดิมเป็น success เพราะ guard ตรวจเฉพาะ `state.item`
- **ตำแหน่ง**: `app/Views/partials/order_upload.php:28-32,63-84`, `public/assets/js/browse/jquery.fileupload.js:694-715,736-758`, `public/assets/js/browse/script.js:20-23,42,66-67,90-93`, `tests/ci4/OrderHttpTest.php:884-897,934-964`
- **Executable evidence A**: Actual Exact plugin fail ก่อน FileReader complete ได้ `threw=[]`, `contextLength=2`, `liWorking=true`, `liError=false`, `targetFiles=0`
- **Executable evidence B**: Source-derived runtime simulation ได้ first over-limit done `{queue:5,item:null,error:true}` แล้วหลังลบหนึ่ง occurrence duplicate done ได้ `{queue:5,item:true,last:"late"}`; error-result แล้ว duplicate success เปลี่ยน queueจาก 0 เป็น 1
- **Harness gap**: Early fail caseเรียก fail ซ้ำหลัง contextแล้วค่อย assert error; repeated completion caseครอบเฉพาะ success ที่สร้าง `state.item` แล้ว จึงไม่ขับ rejected terminal branches
- **Minimal fix**: เพิ่ม terminal state ต่อ operationที่ settle ครั้งเดียวใน done/fail ทุก outcome และให้ `bindContext()` replay terminal UI stateเมื่อ contextมาทีหลัง; harnessต้อง assert fail-before-context หลัง retargetโดยไม่ยิง failซ้ำ และ repeat doneหลัง error/limit rejectionต้องไม่มี state transition
- **Class sweep**: ตรวจ success, error result, transport fail, abort, over-limit, repeated done, fail-then-done, completion-before-context และ missing contextแล้ว successซ้ำหลัง successผ่าน แต่ rejected terminal pathsและ early-fail replayยังล้ม

ไม่พบ Critical ใหม่

## Adversarial runtime verification

### Exact callback และ state harness

```text
Focused adapter harness: OK (2 tests, 9 assertions)
Focused upload/server/DOM contract: OK (7 tests, 168 assertions)
```

ผลผ่านนี้ยืนยัน operation marker, bridge identity, same File occurrence, multi-file group และ basic callback ordering แต่ไม่หักล้าง findings เพราะ harness ใช้ fake DOM shape

### Actual jQuery 1.10.2 bridge semantics

```text
jquery=1.10.2
sameMarker=true
sameContext=true
enumerable=true
contextTags=["INPUT","LI"]
findInput=0
filterInput=1
liWorking=false
liMarker=true
```

Empty bridge เรียก `find`, `text`, `addClass`, `knob` ได้โดยไม่ throw และ retarget ซ้ำยังคง reference เดิม

### Actual Exact Widget/File Upload/glue chain

```text
beforeResolve requests=1 li=1 contextTags=["INPUT","LI"] findInput=0 liInput=0 canvas=0
afterResolve targetFiles=1 liWorking=false liInput=0 canvas=0 queueMarker=true
```

Simulation นี้โหลด Exact `jquery.ui.widget.js`, `jquery.fileupload.js`, `jquery.knob.js` และ `script.js`; ใช้ actual jQuery 1.10.2 DOM แต่ stub เฉพาะ network transport จึงเป็น code runtime evidence ไม่ใช่ authenticated application browser PASS

### Exact `_trigger` ordering

- `jquery.ui.widget.js:464-490` trigger event handlerก่อน option callback
- `jquery.fileupload.js:483-488` shallow-copy dataเข้า request options
- `jquery.fileupload.js:694-708` final progressเกิดก่อน done
- `jquery.fileupload.js:711-715` fail event/callbackเกิดหลังเติม jqXHR status fields
- Adapter inline scriptอยู่ใน contentก่อน Exact plugin/glue assetsที่ `app/Views/layout_order.php:158,168`

## Automated verification ที่รันจริง

| Command หรือ gate | ผล | ความหมาย |
|---|---|---|
| Focused adapter harness | `OK (2 tests, 9 assertions)` | Current harnessเขียว แต่มี false-greenตาม Important ใหม่ 1–2 |
| Focused upload/server/DOM contract | `OK (7 tests, 168 assertions)` | Auth, CSRF, validation, secure image render, layout และ association pathsที่เลือกผ่าน |
| Exact asset pin tests | `OK (2 tests, 56 assertions)` | Hash, version, license header และ pinned JavaScript syntax behaviorผ่าน |
| Route method gate | `OK (1 test, 32 assertions)` | Preview endpointไม่เปิด GET |
| `OrderHttpTest.php` เต็ม | `OK (75 tests, 1240 assertions)` | Order/server regressionเต็มผ่าน |
| PHPStan ตาม CI script | `[OK] No errors` | Static analysisผ่าน |
| PHP/Pinned JS syntax | exit `0` | Adapter, harness และ Exact glue parseผ่าน |
| Scoped whitespace check | exit `0` | ไม่พบ whitespace errorใน scoped source/test |
| Exact CI3 SHA-256 | `MATCH` 9/9 | Exact assetsไม่เปลี่ยน bytes |
| Full PHPUnit จาก real index | `427 tests, 8985 assertions, 1 failure` | ล้มเฉพาะ tracked-asset gateจาก exact assets 9 filesที่ยัง untracked |
| Full `scripts/ci-check.sh` จาก real index | exit `1` ที่ PHPUnit stage | ก่อนถึงจุดนั้น shell, Composer audit, platform, PHP lint และ PHPStanผ่าน; root causeเดียวกับ full PHPUnit |

Full PHPUnit failureข้อความต้นฉบับ:

```text
error: pathspec 'public/assets/css/style.css' did not match any file(s) known to git
...
Did you forget to 'git add'?
```

ข้อความนี้หมายถึง real Git indexยังไม่มี exact assetsตามข้อห้าม stage ไม่ใช่ application behavior failure

## Exact package และ bytes

- Exact review package SHA-256: `ba68172fe1246f3d4d47e35b3544b57182340a3f15fb8fe517de754d3ee2ab28`
- Package paths: 21 unique paths
- Current whole-file blobsตรง b-sideของ exact diff: 20/20 paths
- `app/Config/Routes.php` เป็น shared WIP path; `task-7-route.patch` reverse-apply checkผ่าน ยืนยันว่า exact route hunkอยู่ใน current worktree
- Exact CI3 assets: 9/9 hashesตรง pin
- ไม่พบ pathนอก Task 7 package groupsใน exact diff

## Exact approved candidate

**0 paths**

Important ใหม่ทั้ง 2 รายการกระทบ runtime behaviorและ regression trust จึงไม่อนุมัติ candidate pathใดในรอบนี้

## Browser status

**BLOCKED**

ยังไม่มี authenticated browser proofสำหรับ native file select, drag/drop, real network CSRF rotation, FileReader race, DataTransfer assignment, visual progress knob, success/error, in-flight abort, pending cancel, post-completion delete และ final create/edit submit

Isolated Chrome runsใน reviewนี้ใช้พิสูจน์ DOM/jQuery/plugin semanticsเท่านั้น ไม่ถูกนับเป็น browser matrix PASS

## Residual classification หลัง rework cap

| Residual | Classification | เหตุผล |
|---|---|---|
| Exact DOM progress/knob false-green | **LOAD-BEARING** | Production progress controlไม่มีจริงและ harnessยืนยันผิด |
| Terminal outcomeไม่ถูก persist/replayและ rejected completionไม่ idempotent | **LOAD-BEARING** | Error raceทำให้ previewค้างและ duplicate terminal callbackเปลี่ยน queueได้ |
| Reassigned contextทิ้ง `orderQueueItem`บน old context | **PARKED** | Runtime simulationยืนยัน old markerค้าง แต่ Exact glueกำหนด A.contextครั้งเดียว จึงไม่มี reachable production callerใน packageปัจจุบัน |
| Authenticated browser matrix | **BLOCKED แยกแกน** | ขาด credential และห้ามแตะ shared DB; blockerนี้ไม่เปลี่ยน code verdict |
| Full real-index tracked-asset gate | **BLOCKED แยกแกน** | ต้องผ่าน gitopsหลัง review; reviewนี้ห้าม stage |
