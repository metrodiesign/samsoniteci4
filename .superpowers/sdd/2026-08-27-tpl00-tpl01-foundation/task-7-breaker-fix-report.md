# รายงาน breaker correction Task 7

เอกสารนี้สรุปการแก้ load-bearing residual สองรายการใน implement รอบ 6 พร้อมหลักฐาน TDD และ gate รอบสุดท้าย โดยไม่เปลี่ยน real Git index

## สถานะ

| แกน | ผล |
|---|---|
| Exact DOM normalization | ปิดแล้ว |
| Terminal state และ late-context replay | ปิดแล้ว |
| Focused และ full automated gates | ผ่าน |
| Exact CI3 assets | ตรงครบ 9/9 |
| Authenticated browser matrix | `BLOCKED` ตามข้อจำกัดเดิม |
| สถานะรวม | `DONE_WITH_CONCERNS` |

## ไฟล์ที่แก้

- `app/Views/partials/order_upload.php`
- `tests/ci4/OrderHttpTest.php`
- `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-breaker-fix-report.md`

ไม่มี dependency ใหม่ และ Exact CI3 assets ไม่ถูกแก้

## Design

### Exact DOM normalization

- Context setter ใช้ jQuery collection เดิมที่ Exact callback ส่งมา
- ย้าย top-level hidden `INPUT` เข้า preview `LI` ก่อน Exact บรรทัดถัดไปเรียก `tpl.find('input').knob()`
- Stable bridge คง object identity เดิม และชี้ element ชุดเดิมโดยไม่สร้าง replacement markup
- หลัง normalization ทั้ง local `tpl.find('input')` และ completion bridge `.find('input')` พบ input ตัวเดียวกัน
- Queue marker และ delete binding อยู่บน preview `LI`
- Context ว่างหรือ malformed ไม่ทำให้ callback throw

### Terminal state

State ต่อ operation มีสถานะ `pending`, `success`, `rejected` และ `failed`

- `settle()` รับ terminal outcome แรกครั้งเดียว
- Error result และ over-limit จบเป็น `rejected`
- Transport fail และ abort จบเป็น `failed`
- Success เพิ่ม File occurrence เข้า queue ครั้งเดียวและจบเป็น `success`
- `bindContext()` replay terminal UI เมื่อ context มาภายหลัง
- `failed` และ `rejected` ลบ `working` พร้อมเพิ่ม `error`
- `success` ลบ `working` และป้องกัน late fail callback เปลี่ยน UI เป็น error

## RED และ GREEN

### RED ก่อนแก้ production

Focused Exact-shape harness ใช้ root shape `INPUT,LI` และ Exact callback ordering

```text
NORMALIZED_CONTEXT_INPUT_COUNT=0
KNOB_DID_NOT_REACH_REAL_INPUT
PROGRESS_DID_NOT_REACH_PREVIEW
FAIL_REPLAY_LEFT_PREVIEW_WORKING
REJECTED_THEN_SUCCESS_QUEUE_COUNT=1
FAILED_THEN_DONE_QUEUE_COUNT=1
OVER_LIMIT_GAINED_QUEUE_MARKER
```

หลังปิดสอง residual หลัก เพิ่ม mutation check สำหรับ success แล้ว fail ซ้ำ ได้ RED ที่ตรง terminal UI

```text
SUCCESS_CHANGED_TO_ERROR
```

### GREEN รอบสุดท้าย

| Case | ผล |
|---|---|
| Top-level `INPUT` และ `LI` ก่อน normalization | ผ่าน |
| `find('input')` จาก 0 เป็น 1 และคง input identity | ผ่าน |
| Knob และ progress แตะ input จริง | ผ่าน |
| Fail ก่อน context แล้ว replay `working=false`, `error=true` | ผ่าน |
| Rejected แล้ว done success ซ้ำ | queue คงเดิมและไม่มี item |
| Fail แล้ว done | queue คง 0 |
| Success แล้ว repeated done/fail | occurrence และ success UI คงเดิม |
| Operation marker และ same File occurrence | ผ่าน |
| Multi-file group, duplicate และ interleave | ผ่าน |
| Pending cancel และ missing context | ผ่าน |

Focused adapter harness รอบสุดท้าย:

```text
OK (2 tests, 9 assertions)
```

## Gate รอบสุดท้าย

| Gate | ผล |
|---|---|
| Focused upload server และ DOM matrix | `OK (22 tests, 458 assertions)` |
| `OrderHttpTest.php` เต็ม | `OK (75 tests, 1240 assertions)` |
| Full PHPUnit บน exact candidate | `OK (427 tests, 8987 assertions)` |
| PHPStan บน exact candidate | `[OK] No errors` |
| PHP syntax และ scoped whitespace | ผ่าน |
| Full `scripts/ci-check.sh` | ผ่านถึง `PASS repository safety gate` |
| Exact CI3 SHA-256 | `MATCH` 9/9 |

Full CI รอบ sandbox หยุดที่ข้อความต้นฉบับ:

```text
ERROR: permission denied while trying to connect to the docker API at unix:///var/run/docker.sock
```

ข้อความนี้หมายถึง sandbox บล็อก Docker socket ไม่ใช่ source failure จึงรันคำสั่งเดิมนอก sandbox แล้วผ่านทุก gate

## Exact temporary candidate

Temporary index เริ่มจาก `6799684db6de09936122d2ae25a5461a878b0eb3`

- Whole-file paths: 20
- Route patch: `task-7-route.patch` เดิม 1 hunk
- Candidate paths รวม: 21
- Candidate tree: `aa1ab4bb1f8f73a27ad75bd126fa0963e9216283`
- Binary diff SHA-256: `99d5d6678dc5b22604445722f5dc379c9227bbd31b3778f9aa2150f5acfb1b16`

Real Git index ก่อนและหลังตรงกัน

- Tree: `c6ce38a8953cb1dedf08e35446b3195347139425`
- Cached diff SHA-256: `01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b`

ไม่มี stage, commit, push, browser credential หรือ shared DB mutation

## Residual concerns

- Reassigned context ที่ทิ้ง marker บน old context ยังคงเป็น parked residual ตาม brief และไม่ได้ขยาย scope
- Authenticated browser matrix ยัง `BLOCKED` เพราะไม่มี credential และห้ามแตะ shared DB
- ไม่อ้าง browser PASS สำหรับ native file select, drag/drop, FileReader timing, visual knob, in-flight abort หรือ final submit
