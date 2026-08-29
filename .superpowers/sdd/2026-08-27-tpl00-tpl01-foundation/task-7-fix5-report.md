# รายงานแก้ Task 7 รอบ 5

เอกสารนี้สรุป root-cause implementation, หลักฐาน RED/GREEN และ full gates ของ Task 7 fix round 5 บน primary working tree โดยไม่เปลี่ยน real Git index

## สถานะ

| แกน | ผล |
|---|---|
| Important 1: stable context bridge | ปิดแล้ว |
| Important 2: operation occurrence และ multi-file cardinality | ปิดแล้ว |
| Important 3: Exact callback harness | ปิดแล้ว |
| Focused และ full automated gates | ผ่าน |
| Exact CI3 assets | ตรงครบ 9/9 |
| Authenticated browser matrix | `BLOCKED` |
| สถานะรวม | `DONE_WITH_CONCERNS` |

## ไฟล์ที่แก้

- `app/Views/partials/order_upload.php`
- `tests/ci4/OrderHttpTest.php`
- `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix5-report.md`

Exact CI3 assets ทั้ง 9 ไฟล์ไม่ถูกแก้ และไม่มี dependency ใหม่

## Design ที่ใช้

### Operation state

- สร้าง state หนึ่งตัวต่อ original `fileuploadadd` data A
- เก็บ state บน enumerable property `__ci4OrderUploadOperation`
- เมื่อ Exact `submit()` สร้าง shallow clone B, A และ B จึงถือ state reference เดียวกัน
- state เก็บ `data.files.slice()` ทั้งชุดตาม occurrence และลำดับ
- ไม่ใช้ WeakMap หรือ File object เป็น global slot
- completion สำเร็จเพิ่มทุก File occurrence ของ operation เข้า final queue
- `state.item` ทำให้ completion ซ้ำของ operation เดียว idempotent
- preview เก็บ group marker หนึ่งตัว และ clicked delete ลบเฉพาะ occurrence ทั้งกลุ่มของ operation นั้น

### Stable jQuery context bridge

- สร้าง jQuery collection ว่างหนึ่งตัวก่อน Exact option callback
- getter ของ `A.context` คืน collection reference เดิม ทำให้ B shallow-copy reference เดียวกัน
- setter ของ A แทนสมาชิกภายใน collection ด้วย element จาก real context โดยไม่เปลี่ยน object identity
- ก่อน real context มา `.find(...)`, `.text(...)`, `.knob(...)` และ `.addClass(...)` ทำงานบน empty collection โดยไม่ throw
- หลัง real context มา Exact progress/fail และ adapter binding ทำงานกับ preview element จริง
- ไม่ใช้ timeout, polling หรือแก้ Exact assets

## RED/GREEN matrix

### RED ก่อนแก้ production

Exact harness โหลด production inline adapter และ Exact `public/assets/js/browse/script.js` พร้อมจำลอง `_trigger` ตาม source โดยเรียก event handler ก่อน option callback

| Failure class | หลักฐาน RED |
|---|---|
| Operation marker | `OPERATION_MARKER_NOT_SHARED` |
| Progress ก่อน context | `TypeError: Cannot read properties of undefined (reading 'find')` |
| Fail ก่อน context | `TypeError: Cannot read properties of undefined (reading 'addClass')` |
| Progress/fail หลัง late context | ยังอ่าน B context เดิมและไม่แตะ preview |
| Same File object submit สองครั้ง | `SAME_FILE_TWICE_QUEUE_COUNT=1` |
| A เดียวมีสอง File | `MULTI_FILE_QUEUE_COUNT=1` |

คำสั่ง RED:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist --filter testUploadAdapterFollowsExactCallbacksAcrossOperationAndContextBoundaries tests/ci4/OrderHttpTest.php
```

ผลคือ `Tests: 1, Assertions: 5, Failures: 1` โดย error รวม failure class ข้างต้นในรอบเดียว

### GREEN หลังแก้ production

| Case บังคับ | ผล |
|---|---|
| A/B เป็นคนละ object และ share operation marker | ผ่าน |
| Early context | ผ่าน |
| Completion before context | ผ่าน |
| Progress ก่อน contextไม่ throw | ผ่าน |
| Fail ก่อน contextไม่ throw | ผ่าน |
| Progress/fail หลัง contextแตะ previewจริง | ผ่าน |
| Same File object สอง operation ได้ 2 occurrences | ผ่าน |
| Delete same File แยกตาม operation | ผ่าน |
| Multi-file A เก็บครบและ delete เป็น group | ผ่าน |
| Duplicate names และ interleaved completion | ผ่าน |
| Missing context และ pending cancel | ผ่าน |
| Fail, abort และ error result ไม่เพิ่ม queue | ผ่าน |
| Repeated completion | ผ่าน |

Focused adapter matrix หลังแก้:

```text
OK (2 tests, 9 assertions)
```

## Verification

| Gate | ผล |
|---|---|
| Exact adapter harness | `OK (1 test, 5 assertions)` |
| Adapter regressions ทั้งหมด | `OK (2 tests, 9 assertions)` |
| Focused upload server matrix | `OK (15 tests, 373 assertions)` |
| `OrderHttpTest.php` | `OK (75 tests, 1240 assertions)` |
| Full PHPUnit บน exact candidate | `OK (427 tests, 8987 assertions)` |
| PHPStan บน exact candidate | `[OK] No errors` |
| PHP syntax | ผ่านทั้ง 2 ไฟล์ที่แก้ |
| Whitespace check | ผ่านทั้ง 2 ไฟล์ที่แก้ |
| Exact CI3 SHA-256 | `MATCH` 9/9 |
| Full `scripts/ci-check.sh` | ผ่านถึง `PASS repository safety gate` |

Full CI รอบแรกใน sandbox หยุดที่ข้อความต้นฉบับ:

```text
grep: .env.example: Operation not permitted
FAIL: CI4 loopback port placeholder is missing
```

ข้อความนี้หมายถึง sandbox ปิดการอ่าน `.env.example` ไม่ใช่ source failure จึงรันคำสั่งเดิมนอก sandbox

รอบนอก sandbox ครั้งแรกพบ temporary wrapper เปรียบเทียบ CI3 path แบบ canonical แต่ script ส่ง path ที่มี `../` ทำให้ `GIT_INDEX_FILE` รั่วเข้า CI3 และรายงาน worktree dirty หลังแก้เฉพาะ temporary wrapper ให้ unset ตาม CI3 path suffix รอบสุดท้ายผ่านทุก gate โดยไม่แก้ production script

## Exact temporary candidate

Temporary index เริ่มจาก checkpoint `6799684db6de09936122d2ae25a5461a878b0eb3`

- Whole-file paths: 20
- Route patch: `task-7-route.patch` เดิม 1 hunk
- Candidate paths รวม: 21
- Candidate tree: `008afb854c9d55c83982eff32b990b034bde9155`
- Binary diff SHA-256: `7fb8a4c45c393edc22f6fa40ae824926a47546587d3df61ea36cc55f66a55fb3`

Real Git index ก่อนและหลัง verification ตรงกัน:

- Tree: `c6ce38a8953cb1dedf08e35446b3195347139425`
- Cached diff SHA-256: `01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b`

ไม่มี stage, commit, push, browser credential หรือ shared DB mutation

## Concern ที่เหลือ

Authenticated browser matrix ยัง `BLOCKED` ตามข้อจำกัด credential และ shared DB จึงไม่อ้าง browser PASS สำหรับ native file select, drag/drop, FileReader timing, progress knob, in-flight abort, post-completion delete และ final create/edit submit
