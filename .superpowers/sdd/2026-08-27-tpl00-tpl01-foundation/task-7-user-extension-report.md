# รายงาน Task 7 user-approved rework 6/6

เอกสารนี้สรุปการแก้ native click event ordering เพียง finding เดียว พร้อมหลักฐาน TDD และ full gates บน exact Task 7 candidate โดยไม่เปลี่ยน real Git index

## สถานะ

| แกน | ผล |
|---|---|
| Completed preview delete | ปิด finding แล้ว |
| Direct handler ordering | adapter bind ก่อน Exact |
| Delegated fallback | idempotent และไม่ลบ operation อื่น |
| Exact CI3 assets | ตรงครบ 9/9 และไม่ถูกแก้ |
| Full automated gates | ผ่าน |
| Browser fixture | รอ verifier หลัง code re-review ตามขอบเขตที่กำหนด |
| สถานะรวม | `DONE_WITH_CONCERNS` |

## ไฟล์ที่แก้

- `app/Views/partials/order_upload.php`
- `tests/ci4/OrderHttpTest.php`
- `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-user-extension-report.md`

ไม่มี dependency ใหม่ ไม่แก้ Exact assets และไม่แตะ timeout, polling หรือ Exact click handler

## Event ordering

1. Exact callback กำหนด `data.context` และเข้า context setter
2. Setter normalize top-level progress input เข้า preview `LI`
3. Setter เรียก `bindQueueRemoval(preview)` ให้ adapter direct handler bind กับ preview `span`
4. Setter คืน control แล้ว Exact บรรทัดถัดไปจึง bind direct click handler ของตัวเอง
5. Native click เรียก adapter direct handlerก่อน จึงลบ operation group จาก final queue และ sync `target.files` ขณะที่ preview ยังอยู่ใน DOM
6. Exact direct handlerจึง abort pending requestเมื่อจำเป็น และลบ preview DOM
7. ถ้า delegated fallbackได้รับ event ก่อน DOM detach การเรียก `removeQueueItem()` ซ้ำเป็น no-op; ถ้า target detachก่อน bubbling fallbackไม่ถูกเรียกและ queueก็ถูกลบแล้ว

Pending previewไม่มี `orderQueueItem` จึงไม่แตะ completed operation อื่น แต่ Exact handlerยัง abortหนึ่งครั้ง ส่วน failed/rejected previewไม่มี queue itemและเป็น no-op

## RED และ GREEN

### RED

Test เปลี่ยนจากการเรียก adapter handlerตรงเป็น native dispatch ที่เรียก direct handlersตามลำดับแล้วจึงจำลอง bubbling ผลก่อนแก้ production:

```text
NATIVE_CLICK_ORDER queue=1 delegated=0 liInDom=false
ADAPTER_DIRECT_DID_NOT_REMOVE_QUEUE_FIRST
EARLY_DELETE_COUNT=1
```

ข้อความนี้หมายถึง Exact handlerลบ previewก่อน delegated adapterจะทำงาน ทำให้ Fileยังค้างใน final queue ตรง finding

### GREEN

| Case | ผล |
|---|---|
| Completed single native click | queue และ `target.files` เหลือ 0 ก่อน DOM removal |
| Direct handler order | adapter เป็นลำดับแรก Exact เป็นลำดับถัดไป |
| Pending click | abort 1 ครั้ง และ completed queue อื่นไม่เปลี่ยน |
| Duplicate names | ลบเฉพาะ operation ที่ click |
| Same File occurrence | occurrence แยกกันถูกต้อง |
| Interleaved completion | queue identity และลำดับถูกต้อง |
| Multi-file group | ลบทั้ง group ของ preview เดียว |
| Failed/rejected | ไม่มี queue itemและ no-op |
| Delegated fallback | เรียกซ้ำแล้ว idempotent |

Focused adapter tests:

```text
OK (2 tests, 9 assertions)
```

## Full gates

| Gate | ผล |
|---|---|
| Focused upload/auth/CSRF/association | `OK (21 tests, 442 assertions)` |
| `OrderHttpTest.php` เต็ม | `OK (75 tests, 1240 assertions)` |
| Full PHPUnit บน exact candidate | `OK (427 tests, 8987 assertions)` |
| PHPStan บน exact candidate | `[OK] No errors` |
| PHP syntax และ scoped whitespace | ผ่าน |
| Full `scripts/ci-check.sh` | ผ่านถึง `PASS repository safety gate` |
| Exact CI3 SHA-256 | `MATCH` 9/9 |

Full CI รอบ sandboxหยุดที่ข้อความต้นฉบับ:

```text
ERROR: permission denied while trying to connect to the docker API at unix:///var/run/docker.sock
```

ข้อความนี้หมายถึง sandbox บล็อก Docker socket ไม่ใช่ source failure จึงรันคำสั่งเดิมนอก sandboxและผ่านทุก gate

## Exact temporary candidate

Temporary index เริ่มจาก `6799684db6de09936122d2ae25a5461a878b0eb3`

- Whole-file paths: 20
- Route patch: `task-7-route.patch` เดิม 1 hunk
- Candidate paths รวม: 21
- Candidate tree: `d572679d4c4bfdbd1d603961754f2a57fd6bcfef`
- Binary diff SHA-256: `f4e9d44eaf2e79260069bacabad3ec3dd512955f5d61603f9bcb2e06f64072f1`

Real Git index ก่อนและหลังตรงกัน

- Tree: `c6ce38a8953cb1dedf08e35446b3195347139425`
- Cached diff SHA-256: `01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b`

ไม่มี stage, commit หรือ push

## Residuals

- Finding ฝั่ง code ไม่มี residual ที่ทราบจาก automated harness
- Browser fixture และ authenticated interaction matrixไม่ได้ทำในรอบ implementerนี้ตามคำสั่ง ต้องให้ verifierใช้ disposable isolated DB หลัง code re-review
- จึงไม่อ้าง browser PASS สำหรับ native file select, drag/drop, FileReader timing, progress knob, in-flight abort หรือ final submit
