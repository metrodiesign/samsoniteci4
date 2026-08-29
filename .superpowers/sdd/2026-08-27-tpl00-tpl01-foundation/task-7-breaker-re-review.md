# Final breaker re-review Task 7

ตรวจ breaker correction หลัง implement รอบ 6 และ rework cap 5/5 โดยแยก code candidate ออกจาก browser blocker

## Verdict

| แกน | ผล |
|---|---|
| Exact DOM normalization | `ADDRESSED` |
| Terminal first-outcome-wins | `ADDRESSED` |
| Critical ใหม่ | ไม่พบ |
| Important ใหม่ | 1 รายการแบบ load-bearing |
| Code candidate | `CHANGES REQUIRED` |
| Approved candidate | `0 paths` |
| Browser matrix | `BLOCKED` |
| Final decision | `STOP — BLOCKED` |

Residual สองรายการเดิมปิดแล้ว แต่ actual native click พบ operation cardinality regression ที่ harnessปัจจุบัน false-green จึงอนุมัติ candidateไม่ได้ และ rework capครบแล้ว

## Residual matrix

| Residual | ผล | หลักฐาน |
|---|---|---|
| Top-level `INPUT`/`LI` normalization | `ADDRESSED` | ก่อน setterได้ roots `INPUT,LI` และ `find('input')=0`; หลัง setterเหลือ top-level `LI`, `find('input')=1` โดย input identityเดิม |
| Knob และ progress | `ADDRESSED` | Exact Knobสร้าง canvasบน previewจริงและ progressอัปเดต inputเดิม |
| Missing/malformed context | `ADDRESSED` | Empty, input-only, li-only และ multiple rootsไม่ throw |
| Terminal state และ late replay | `ADDRESSED` | Fail/abortก่อน context replayเป็น `failed`, `working=false`, `error=true` |
| Rejected/error idempotency | `ADDRESSED` | Rejectedหรือfailedแล้ว done successซ้ำไม่เพิ่ม queue |
| Operation state model | `ADDRESSED` | Same File, duplicate, interleave และ group stateผ่าน harness |

## Load-bearing finding

### Completed preview ถูกลบจาก DOM แต่ File ยังอยู่ใน final queue

- **ตำแหน่ง**: `public/assets/js/browse/script.js:42-57`, `app/Views/partials/order_upload.php:103-113`, `tests/ci4/OrderHttpTest.php:874-878`
- **Scenario**: หลัง upload success ผู้ใช้กด deleteด้วย native click Exact direct handlerเรียก `tpl.fadeOut()` แล้ว `tpl.remove()` ก่อน delegated adapter handlerตรวจ target
- **ผล**: Previewหาย แต่ queueและ `target.files` ยังมี Fileเดิม จึงส่งรูปที่ผู้ใช้ตั้งใจลบไปกับ final form
- **หลักฐาน runtime**:

```text
before:    queue=1 delegated=0 liInDom=true marker=true
immediate: queue=1 delegated=0 liInDom=false
later:     queue=1 delegated=0 liInDom=false
```

- **สาเหตุ test false-green**: Harnessเรียก adapter click handlerตรง จึงข้าม Exact direct handler, DOM removal และ native bubbling
- **Minimal fix ที่ยังไม่ได้ทำ**: bind queue-removal handlerตรงกับ preview spanระหว่าง context setterก่อน Exact handler bind และเพิ่ม native-click regressionตาม event orderingจริง
- **Class sweep**: Completed single, duplicate, same File, interleaved และ multi-file groupใช้ pathเดียวกันจึงได้รับผล; pending cancelผ่านและไม่มี queue; failed/rejectedไม่มี queue item

ไม่พบ Critical ใหม่

## Verification

| Gate | ผล |
|---|---|
| Actual jQuery 1.10.2 append/move | normalization และ identityผ่าน |
| Actual Widget/File Upload/Knob/glue chain | terminal/progressผ่าน; native completed-delete reproduce finding |
| Focused adapter harness | `OK (2 tests, 9 assertions)` |
| Focused upload/auth/CSRF/association | `OK (23 tests, 461 assertions)` |
| `OrderHttpTest.php` | `OK (75 tests, 1240 assertions)` |
| PHPStan | `[OK] No errors` |
| Exact asset pin | `OK (1 test, 51 assertions)` |
| Exact CI3 SHA-256 | `MATCH` 9/9 |
| Full PHPUnit จาก real index | `427 tests, 8985 assertions, 1 failure` |

Full PHPUnitล้มเฉพาะ tracked-asset gate เพราะ Exact assets 9 filesยัง untrackedตามข้อห้าม stage:

```text
error: pathspec 'public/assets/css/style.css' did not match any file(s) known to git
Did you forget to 'git add'?
```

Previous exact packageมี 21 paths; current breaker deltaแตะเฉพาะ `app/Views/partials/order_upload.php` และ `tests/ci4/OrderHttpTest.php` Exact assetsไม่เปลี่ยน Real Git indexยังว่าง ไม่มี stage, commit หรือ push

## Browser status

`BLOCKED`

Authenticated application browser matrixยังขาด credentialและห้ามแตะ shared DB Isolated Chrome runนับเฉพาะ runtime proofของ jQuery/plugin/event semantics ไม่ใช่ browser matrix PASS

## Final decision

`STOP — BLOCKED`

Findingใหม่กระทบ final submitted file cardinalityโดยตรงและ rework capครบ 5/5 จึงห้ามเดินหน้า Task 8, checkpoint, ship หรือวน fixเพิ่มในแผนนี้
