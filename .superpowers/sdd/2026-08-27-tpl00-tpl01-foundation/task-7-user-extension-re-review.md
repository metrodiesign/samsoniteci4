# Final code re-review Task 7 รอบ 6/6

เอกสารนี้ตรวจ exact Task 7 candidate แบบ read-only หลัง user-approved rework รอบ 6/6 โดยใช้ source trace, focused/full gates และ isolated Chrome simulation ที่โหลด exact jQuery 1.10.2, plugin, widget, Knob, adapter และ Exact script จริง

## Verdict

| แกน | ผล |
|---|---|
| Native completed-delete finding | `ADDRESSED` |
| Critical ใหม่ | 0 |
| Important ใหม่ | 1 รายการฝั่ง test evidence ไม่ใช่ load-bearing production code |
| Production code candidate | `APPROVED WITH CONCERN` |
| Exact approved candidate | 21 paths |
| Browser matrix | `PENDING VERIFIER` |
| Final code-review decision | `PASS FOR BROWSER VERIFICATION` |

Adapter direct handler bind ก่อน Exact direct handlerจริง และ native clickลบ operation groupออกจาก final queueก่อน Exact handlerลบ preview DOM ไม่พบ load-bearing code finding ใหม่ จึงอนุมัติ exact candidateให้ verifierทำ Browser matrixต่อได้

## Finding matrix

| Scenario | ผล | หลักฐาน |
|---|---|---|
| Adapter direct bind order ก่อน Exact | `ADDRESSED` | actual jQuery event registryมี direct handlers 2 ตัว โดย `removeQueueItem` เป็นตัวแรกและ `fadeOut` เป็นตัวที่สอง |
| Native completed click | `ADDRESSED` | ก่อน click queue 1; handlerแรกทำ queue 0 ขณะ previewยัง connected; หลัง Exact handler previewถูกลบ |
| Native event path และ queue-before-removal | `ADDRESSED` | native `dispatchEvent(new MouseEvent('click', {bubbles:true}))` ได้ queue 0 และ DOM removed โดยไม่เรียก handlerตรงจาก reviewer simulation |
| Pending abort | `ADDRESSED` | actual plugin simulationได้ abort 1 ครั้งและ completed survivorยังอยู่ใน queue |
| Failed/rejected no-op | `ADDRESSED` | actual plugin rejectและ failให้ queue 0, preview error และ clickซ้ำไม่เพิ่มหรือลบ operationอื่น |
| Duplicate filename | `ADDRESSED` | completionสลับลำดับได้ queue `dup-2, dup-1`; click previewแรกเหลือเฉพาะ `dup-2` |
| Same File occurrence | `ADDRESSED` | File objectเดียวสอง occurrenceได้ cardinality 2; clickทีละ previewลด 2 เป็น 1 เป็น 0 |
| Interleaved completion | `ADDRESSED` | queueคง completion orderและลบด้วย operation identity ไม่อิง filename |
| Multi-file group | `ADDRESSED` | actual pluginเมื่อ `singleFileUploads=false` ได้ group 2 files และ native clickครั้งเดียวลบทั้ง group |
| Delegated fallback | `ADDRESSED` ฝั่ง code | `removeQueueItem()` ซ้ำเป็น no-opเมื่อ groupถูกลบแล้ว และ group objectกัน cross-operation deletion; native completed pathปัจจุบันลบ DOMก่อน fallback match |
| Late context | `ADDRESSED` | actual response successก่อน FileReader previewได้ queue 1 ก่อนมี `LI`; เมื่อ contextมาทีหลัง previewออกจาก working, Knobสร้าง canvasและ clickลบ queueได้ |
| Reassigned context residual | `ADDRESSED` ฝั่ง cardinality | actual reassignmentผูก old/new previewกับ groupเดียว; old clickลบ groupครั้งเดียวและ new clickเป็น no-op ไม่ข้าม operation |
| Terminal first-outcome-wins | `ADDRESSED` | repeated done, rejected-then-success และ failed-then-doneผ่าน harness; successไม่ย้อนเป็น errorหรือเพิ่ม queueซ้ำ |
| Progress และ Knob | `ADDRESSED` | late actual chainพบ hidden inputเดิมอยู่ใต้ preview, canvasมีจริง, terminal successเอา `working` ออก |
| Operation cardinality | `ADDRESSED` | single, duplicate, same File, interleaved และ groupผ่านทั้ง focused harnessกับ actual plugin simulation |
| Auth, CSRF และ route scope | `ADDRESSED` | routeเป็น POST-only พร้อม `web-auth`, `authorized:write`, `csrf`; anonymousได้ 401และ missing CSRFถูก rejectก่อน validation |
| Server association | `ADDRESSED` ตาม design | preview endpoint validateอย่างเดียวและไม่ persistชื่อไฟล์; final create/editรับ `detail_image[]`, validateซ้ำ, storeและผูกชื่อที่ serverสร้าง |
| Exact assets | `ADDRESSED` | SHA-256 ตรง CI3 pinครบ 9/9 และ asset orderตรง widget/plugin dependency chain |
| Package safety | `ADDRESSED` | candidateสร้างจาก `6799684`, whole-file 20 pathsและ route 1 hunk; tree/hashตรง implementer reportและ real indexไม่เปลี่ยน |

## New finding

### Important: Harnessหลักยังเรียก handlerตรงแทน native event dispatch

- **Severity**: Important ฝั่ง regression evidence; ไม่ใช่ load-bearing production code finding
- **ตำแหน่ง**: `tests/ci4/OrderHttpTest.php:886-905`, `tests/ci4/OrderHttpTest.php:1125-1128`
- **Scenario**: `clickPreview()` วน `span.clickHandlers` แล้วเรียกแต่ละ functionตรง จากนั้นเรียก delegated handlerตรง; `dispatchClick()` ใน harnessที่สองทำแบบเดียวกัน
- **ผล**: Testสามารถกำหนด direct/bubble orderและ DOM-detach semanticsเองแล้ว false-greenได้ แม้ jQuery/native dispatchจริงเปลี่ยน จึงห้ามใช้สอง harnessนี้เพียงอย่างเดียวเพื่อ claim native event semantics
- **เหตุที่ไม่ block candidateรอบนี้**: reviewerรัน isolated Chromeด้วย exact jQuery 1.10.2, widget, fileupload, Knob, adapterและ Exact script แล้ว native `dispatchEvent`ยืนยัน production behaviorตรง contract
- **Minimal fix**: เปลี่ยน claimหลักเป็น browser/native harnessที่ใช้ DOM `dispatchEvent` จริง หรือย้าย native-order assertionไป Browser verifierและลดชื่อ/ข้อความของ Node harnessให้ระบุว่าเป็น deterministic handler model
- **Class sweep**: ตรวจทั้ง harness actual-shapeและ duplicate-name; ช่องว่างกระทบ direct order, bubble reachability, DOM removal timingและ pending abort ส่วน terminal state, queue identityและcardinality assertionsยังมีค่าอิสระจากช่องว่างนี้

ไม่พบ Critical ใหม่ และไม่พบ Important ใหม่ฝั่ง production code

## Actual simulation

### Native completed click

```text
before:    queue=1 connected=true directCount=2
handler 1: queue 1->0 connected=true source=removeQueueItem
handler 2: queue 0->0 connected true->false source=Exact fadeOut/remove
immediate: queue=0 connected=false delegated=0
later:     queue=0 connected=false delegated=0
csrf:      rotated
```

### Duplicate, same File, group และ terminal paths

```text
duplicate: [dup-2, dup-1] -> [dup-2] -> []
same File: [same-object, same-object] -> [same-object] -> []
group:     [group-a, group-b] -> []
pending:   aborts=1 queue=[survivor]
rejected:  queue=[] error=true
failed:    queue=[] error=true
```

### Late context

```text
before preview:            queue=0 li=0
after done before preview: queue=1 li=0
after context:             queue=1 working=false error=false canvas=true
handler order:             adapter first, Exact second
after native click:        queue=0 connected=false
```

Simulationนี้เป็น runtime proofของ exact JavaScript/event chainเท่านั้น ไม่ใช่ authenticated application Browser matrix

## Verification

| Gate | ผลจริง |
|---|---|
| Focused adapter harness | `OK (2 tests, 9 assertions)` |
| Focused auth/CSRF/association selection | `OK (6 tests, 157 assertions)` |
| Full `OrderHttpTest.php` | `OK (75 tests, 1240 assertions)` |
| Full PHPUnit บน exact candidate | `OK (427 tests, 8987 assertions)` |
| PHPStan | `[OK] No errors` |
| Full `scripts/ci-check.sh` บน exact candidate | ผ่านถึง `PASS repository safety gate` |
| Exact CI3 assets | `MATCH` 9/9 |
| JavaScript syntax | browse 5 filesและ `admin_addOrder.js` ผ่าน |
| Scoped whitespace | ผ่าน ไม่มี output |
| Real Git index | tree `c6ce38a8953cb1dedf08e35446b3195347139425`, cached diffว่าง |

Full CI รอบ sandboxแรกหยุดด้วยข้อความต้นฉบับ:

```text
ERROR: permission denied while trying to connect to the docker API at unix:///var/run/docker.sock
```

ข้อความนี้หมายถึง sandboxบล็อก Docker socket ไม่ใช่ source failure คำสั่งเดิมจึงถูกรันนอก sandboxและผ่านทุก gate

## Exact approved candidate

Candidateเริ่มจาก checkpoint `6799684` และอนุมัติ 21 paths:

1. `app/Config/Routes.php` เฉพาะ preview-upload route hunk
2. `app/Controllers/Order.php`
3. `app/Orders/OrderImageStore.php`
4. `app/Views/layout_order.php`
5. `app/Views/order_edit.php`
6. `app/Views/order_new.php`
7. `app/Views/partials/order_legacy_scripts.php`
8. `app/Views/partials/order_upload.php`
9. `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md`
10. `public/assets/css/style.css`
11. `public/assets/img/icons.png`
12. `public/assets/js/addOrder.js`
13. `public/assets/js/admin_addOrder.js`
14. `public/assets/js/browse/jquery.fileupload.js`
15. `public/assets/js/browse/jquery.iframe-transport.js`
16. `public/assets/js/browse/jquery.knob.js`
17. `public/assets/js/browse/jquery.ui.widget.js`
18. `public/assets/js/browse/script.js`
19. `tests/ci4/MenuHttpTest.php`
20. `tests/ci4/OrderHttpTest.php`
21. `tests/ci4/RouteHttpTest.php`

Package identity:

```text
candidate_count=21
candidate_tree=d572679d4c4bfdbd1d603961754f2a57fd6bcfef
candidate_binary_diff_sha256=f4e9d44eaf2e79260069bacabad3ec3dd512955f5d61603f9bcb2e06f64072f1
review_package_sha256=b005901c55f3be9c467c1cc40356f1623cae0bbf0c8b4e00212a0dc0062742d8
```

ไม่มี stage, commit หรือ push และ route hunksของ tracking/password resetไม่อยู่ใน exact candidate

## Browser status

`PENDING VERIFIER`

ขั้นถัดไปคือ verifierรัน authenticated Browser matrixบน disposable isolated DB สำหรับ native file select, drag/drop, FileReader timing, progress Knob, in-flight abort, duplicate/multiple files, CSRF rotation และ final create/edit submit ห้ามตีความ isolated event simulationข้างต้นเป็น Browser matrix PASS
