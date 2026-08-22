# CI3 Excel import characterization v1

เอกสารนี้บันทึก vertical slice แรกของ WP-00C สำหรับ Excel Status, Price และ New Order บน CI3 ที่ pin แล้วและ MariaDB target. ครอบคลุม preview, Confirm success path และ Negative/Isolation baseline ที่แยก approval กัน แต่ยังไม่ปิด WP-00C ทั้งชุด.

## สถานะ

| รายการ | ค่า |
|---|---|
| Preview Case IDs | `XLS-PREVIEW-001`, `XLS-PRICE-PREVIEW-001`, `XLS-NEW-ORDER-PREVIEW-001` |
| Confirm Case IDs | `XLS-STATUS-CONFIRM-001`, `XLS-PRICE-CONFIRM-001`, `XLS-NEW-ORDER-CONFIRM-001` |
| สถานะ | APPROVED โดย Business และ QA เมื่อ 2026-08-19; preview และ Confirm success-path characterization ผ่าน |
| Negative/Isolation | CHARACTERIZED เมื่อ 2026-08-20; รอ Business/QA/Security disposition |
| CI3 source | `8dad4e331a90f5c6765954454910b451eb0ff8e5` |
| Public seams | Login, preview routes และ Confirm routes ของ Status, Price และ New Order |
| Status fixture SHA-256 | `eb105f22550a5a3d80a94e260a26cc6047b90d54edfa3fb5427ffddbf4bb4522` |
| Browser visual | CHARACTERIZED ทั้ง 3 flows ที่ viewport `1280x720` |
| Status screenshot SHA-256 | `342e25c3e7b87a3403e03b8ddd68cf933f0fea5681afd4f25054520974d7d7e6` |
| Target database | MariaDB 11.4.12, utf8mb4_general_ci, InnoDB |

## Preview approval

| รายการ | การตัดสินใจ |
|---|---|
| Business | APPROVED expected output และ workflow ของ Status, Price และ New Order preview |
| QA | APPROVED fixture, assertions, visual baselines และผล `safe-preview-smoke` |
| ผู้ลงนาม | Software Engineer ในบทบาท Business และ QA |
| วันที่ | 2026-08-19, Asia/Bangkok |
| PHPExcel warnings | Known CI3 defect; ไม่ใช่ behavior ที่ต้องรักษาใน CI4 |
| CI4 expectation | ไม่มี warning banners แต่ผล preview และ workflow ต้องเทียบเท่า baseline |
| ขอบเขต | อนุมัติ Status, Price และ New Order preview; ไม่รวม confirm flow |
| Execution evidence | P0–P8 ผ่าน, confirm/email/SMS เท่ากับ 0 และ cleanup database กลับเป็น 0 rows |

## Confirm approval

| รายการ | การตัดสินใจ |
|---|---|
| Business | APPROVED expected database outcome และ workflow ของ Status, Price และ New Order Confirm |
| QA | APPROVED synthetic fixture, assertions, runtime evidence และ cleanup |
| ผู้ลงนาม | Software Engineer ในบทบาท Business และ QA |
| วันที่ | 2026-08-19, Asia/Bangkok |
| Status และ Price | APPROVED เป็น CI3 baseline สำหรับ CI4 parity |
| New Order redirect warning | Known CI3 defect; ห้ามรักษาใน CI4 |
| CI4 expectation | Commit New Order สำเร็จ แล้ว redirect/render success โดยไม่มี warning หรือ header failure |
| ขอบเขต | อนุมัติ Confirm success path ด้วยข้อมูล synthetic; ไม่รวม duplicate/replay, CSRF หรือ concurrency |
| Safety evidence | Email/SMS attempts 0, backup files 0 และ cleanup database กลับเป็น 0 rows |

## ขอบเขตความปลอดภัยของ Preview

รอบ Preview ที่อนุมัติเดิมเป็น preview-only. ห้ามเรียก confirm action หรือ external messaging ทุกชนิด.

- ไม่เรียก `ExcelConfirm`, `ExcelPriceConfirm` หรือ `ExcelNewOrderConfirm`
- ไม่เรียก contact, password-reset, notification หรือ provider path
- ไม่ส่ง email หรือ SMS
- ไม่ใช้ production recipient, production credential หรือ production endpoint
- รันเฉพาะ isolated rehearsal runtime ที่ไม่มี operator อื่นใช้งาน
- Fail closed เมื่อ upload directory มีไฟล์อยู่ก่อนเริ่ม
- ตรวจ source ของ `ExcelDataAdd()` ก่อนรันและ fail closed เมื่อพบ messaging call
- ตรวจ web log หลังรันว่า confirm/email/SMS activity เท่ากับ 0

## Test seam

ทดสอบผ่าน interface ที่ผู้ใช้และ operator ใช้จริง ไม่เรียก controller/model method โดยตรง.

1. สร้าง restore point บน isolated database.
2. สร้าง test operator synthetic ชั่วคราวโดยไม่แก้ user จริง.
3. เพิ่ม order synthetic หนึ่งแถวสำหรับ join ใน preview.
4. Login ผ่าน `POST /loginMe`.
5. เปิดหน้า upload ของ Status, Price และ New Order.
6. ส่ง XLSX synthetic ผ่าน preview POST route ของแต่ละ flow.
7. ตรวจ HTTP, rendered HTML, Thai text, temp-table effect และ absence of confirm side effects.
8. Restore database, ลบ uploaded fixture และยืนยัน CI3 source ไม่เปลี่ยน.

## Confirm characterization

Confirm ใช้ isolated empty database, synthetic operator/order และ outbound Email/SMS ที่ปิดไว้. ไม่ใช้ recipient หรือ credential จริง.

| Case ID | Flow | ผลที่สังเกต | สถานะ |
|---|---|---|---|
| `XLS-STATUS-CONFIRM-001` | Status | HTTP 200; `uploadstaus=1`, `status_log=1`, order action status เป็น `3` | PASS |
| `XLS-PRICE-CONFIRM-001` | Price | ราคา synthetic เปลี่ยนจาก `321.00` เป็น `1234.00`; ไม่มี Status side effect เพิ่ม | PASS |
| `XLS-NEW-ORDER-CONFIRM-001` | New Order | สร้าง synthetic order และ `status_log` อย่างละ 1 แถว ก่อน redirect warning | PASS WITH KNOWN CI3 DEFECT |
| `XLS-CONFIRM-SAFETY-001` | Safety/cleanup | Email/SMS attempts 0, backup files 0 และ database rows หลัง cleanup เท่ากับ 0 | PASS |

New Order defect เกิดเมื่อ tracking number แรกของเดือนทำให้ `SELECT MAX` คืน aggregate row แต่ `trackID=NULL` ที่ `Request_order_model.php:1361`. การคำนวณเลขถัดไปจึงเกิด `A non-numeric value encountered`; output นี้ทำให้ redirect ตามด้วย `Cannot modify header information`. Database commit สำเร็จก่อน warning. CI4 ต้องแก้ root cause และห้ามรักษา warning/redirect failure นี้.

Runtime evidence อยู่ใน local ignored path:

| หลักฐาน | Path | SHA-256 |
|---|---|---|
| Run log | `evidence/db-foundation-001/01-baseline/safe-confirm-run.txt` | `beb39e8756ac65e77868b542bcfa9780a95dbcd2b9492ef0ad299317ff2a1753` |
| Observed state | `evidence/db-foundation-001/01-baseline/safe-confirm-observed.tsv` | `ab3961e8572efaec6f0e5d6aa3a3f6eccb9778568a772232f3214b560a4ddd7b` |
| New Order response | `evidence/db-foundation-001/01-baseline/safe-confirm-new-order-response.html` | `b26ba22ea7cfc18c6b2a7311b3dfca09883b18e691c190396cd9f1a2005578aa` |
| Web log | `evidence/db-foundation-001/01-baseline/safe-confirm-web.log` | `178e3f53365b58f9f1b04074c4c418586ec2c38e5c78a424f90b21ab11cf08de` |

## Confirm Negative/Isolation baseline

รันผ่าน Confirm HTTP routes ด้วย operator synthetic 2 sessions บน isolated empty database. Email/SMS disabled, backup files 0 และ cleanup database กลับเป็น 0 rows.

| Case ID | กรณี | ผล CI3 ที่สังเกต | Disposition candidate |
|---|---|---|---|
| `XLS-STATUS-REPLAY-001` | Status Confirm ซ้ำ 2 ครั้ง | รับทั้ง 2 request; `uploadstaus=2`, `status_log=2` | Correct and re-baseline; ห้าม duplicate side effect ใน CI4 |
| `XLS-PRICE-REPLAY-001` | Price Confirm ซ้ำ 2 ครั้ง | รับทั้ง 2 request; ราคาสุดท้าย `1234.00`, ไม่มี Status row เพิ่ม | Business/QA ต้องกำหนด idempotent response ของ CI4 |
| `XLS-NEW-ORDER-REPLAY-001` | New Order Confirm ซ้ำข้าม session | สร้าง order และ `status_log` อย่างละ 2 แถวจาก batch เดียว | Correct and re-baseline; ห้ามสร้างซ้ำใน CI4 |
| `XLS-CONFIRM-CSRF-001` | Confirm ไม่มี CSRF token | Preview form ไม่มี token และ Confirm ทั้ง 3 routes รับ tokenless POST | Correct and re-baseline; Security sign-off required |
| `XLS-CONFIRM-ISOLATION-001` | Operator A/B มี workflow ซ้อนกัน | B preview ทับ temp row ของ A; session A Confirm batch ของ B ได้ | Correct and re-baseline; batch ต้องมี owner ใน CI4 |

คำว่า PASS ของ harness หมายถึง reproduce CI3 ได้ตรง assertions ไม่ได้หมายถึง behavior ปลอดภัย. Status/New Order replay, missing CSRF และ cross-user batch contamination เป็น known CI3 defects และห้ามรักษาใน CI4.

CI4 ต้องใช้ batch ID + owner, ตรวจ owner ทุก preview/confirm, ทำ Confirm idempotent และ reject CSRF ก่อน business write. Expected response ที่แน่นอนของ Price replay ยังต้องให้ Business/QA อนุมัติ.

รอบนี้ใช้ controlled overlapping sessions เพื่อให้ผล deterministic. ยังไม่ใช่ simultaneous-request stress test และยังไม่พิสูจน์ tracking ID `MAX+1` race.

| หลักฐาน | Path | SHA-256 |
|---|---|---|
| Run log | `evidence/db-foundation-001/01-baseline/safe-confirm-negative-run.txt` | `1fa3f915c921eb0b6d5379988f3a2790aef6f4d1f614f0f943ac639934b98b1f` |
| Observed state | `evidence/db-foundation-001/01-baseline/safe-confirm-negative-observed.tsv` | `3b2df71e2ebf2252d4c5ba5267b77a6276c1d829863ece129d77bccfce121e98` |
| Web log | `evidence/db-foundation-001/01-baseline/safe-confirm-negative-web.log` | `795579b2b602f8b0b89ede123b95e2f1b59300a73ea0bb812fc01e5334df4619` |

## Synthetic fixture

Fixture สร้างใหม่ทุกครั้งด้วย Python standard library. ไม่มี PII หรือ credential.

| Field | ค่า |
|---|---|
| Order | `CI3/BASELINE/001` |
| Name | `ผู้ทดสอบ CI3` |
| Telephone | `[REDACTED SYNTHETIC PLACEHOLDER]` |
| Update date | `01/08/2026` |
| Status | `สถานะทดสอบ` |
| Received date | `31/07/2026` |
| Price | `1234` |
| Warranty | `IN` |
| CMG | `CI3-CMG-001` |

หมายเลข synthetic เดิมถูก redact จากเอกสารปัจจุบันเพื่อลดการเก็บข้อมูลที่มีรูปแบบเหมือนเบอร์โทรศัพท์;
fixture hash ด้านบนยังเป็นค่าจากรอบ characterization เดิม

## Assertions

| ID | สิ่งที่ตรวจ | ผล |
|---|---|---|
| X1 | Synthetic operator/order ไม่มีอยู่ก่อน setup และมีอย่างละหนึ่งแถวหลัง setup | PASS |
| X2 | `ExcelDataAdd()` ไม่มี email/SMS call และ fixture SHA ตรง canonical value | PASS |
| X3 | Login สำเร็จและ upload form render โดยไม่เด้งกลับหน้า login | PASS |
| X4 | XLSX preview ตอบ 200, Thai text ตรง และไม่มี PHP fatal | PASS |
| X5 | Temp row ตรง fixture, `uploadstaus` และ `status_log` ไม่เปลี่ยน | PASS |
| X6 | Web log ไม่มี confirm/email/SMS หรือ fatal/database error | PASS |
| X7 | Restore verification ผ่าน, synthetic operator/order และ uploaded file ถูกลบ | PASS |
| X8 | CI3 source SHA และ dirty-file count ไม่เปลี่ยน | PASS |
| X9 | Browser render preview จริง, Thai valid-message แสดง, confirm button แสดงแต่ไม่ถูกกด | PASS WITH LEGACY WARNINGS |
| X10 | Price preview แสดง heading, valid-message และ confirm button โดยไม่เรียก confirm route | PASS WITH LEGACY WARNINGS |
| X11 | New Order preview แสดง synthetic row, ไม่มี duplicate message และไม่เรียก confirm route | PASS WITH LEGACY WARNINGS |

## Visual baseline

รันผ่าน in-app Browser กับ live localhost โดยตรง ไม่ใช้ `file://` หรือ HTML artifact.

| รายการ | ผล |
|---|---|
| เวลาเริ่ม UTC | `2026-08-18T14:24:51Z` |
| URL หลัง upload | `http://127.0.0.1:18404/ExcelDataAdd` |
| Browser viewport | `1280x720`, DPR `1` |
| Screenshot pixels | `1265x712` |
| Preview heading | `Data Upload file Management` แสดง |
| Valid message | `ข้อมูลถูกต้อง กรุณากด Comfirm เพื่ออัพโหลดสถานะ` แสดง |
| Confirm control | `Confirm Data` แสดง แต่ไม่ถูกกด |
| Synthetic row | อยู่ใน DOM ครบ แต่ legacy CSS ซ่อน valid row |
| PHP error banners | 67 รายการก่อน content: PHPExcel deprecation, notice และ warning |
| Confirm table/status log | 0 แถวสำหรับ synthetic tracking ID |
| Confirm/email/SMS markers | 0 |

Screenshot เก็บเฉพาะ local ignored evidence. ภาพเลื่อนไปยัง content area จึงไม่รวม error banners และไม่มี PII จริง.

```text
evidence/db-foundation-001/20-ci3-smoke/visual/excel-status-preview-1280x720.png
```

SHA-256:

```text
342e25c3e7b87a3403e03b8ddd68cf933f0fea5681afd4f25054520974d7d7e6
```

## Price และ New Order visual baseline

Capture ผ่าน in-app Browser บน live localhost เมื่อ 2026-08-19. ภาพเลื่อนไปยัง content area หลัง PHPExcel warning banners.

| รายการ | Price | New Order |
|---|---|---|
| Case ID | `XLS-PRICE-PREVIEW-001` | `XLS-NEW-ORDER-PREVIEW-001` |
| URL หลัง upload | `http://127.0.0.1:18404/ExcelPriceDataAdd` | `http://127.0.0.1:18404/ExcelNewOrderDataAdd` |
| Browser viewport | `1280x720` | `1280x720` |
| Screenshot pixels | `1265x712` | `1265x712` |
| Preview heading | `Data Upload file Price Management` | `Data Upload New REQUEST file Management` |
| Expected content | Valid Thai message และ `Confirm Data` | `SYN/NEW-001`, `Confirm Data` และไม่มี duplicate message |
| PHP error banners | 67 รายการก่อน content | 67 รายการก่อน content |
| Confirm/email/SMS markers | 0 | 0 |
| Screenshot SHA-256 | `5856ef9eb76ef73551c0d3c0c413d4cd3138705d9b043ace3d12042d9c89f65d` | `b77c8b4aed9bf469a93dcbe001e474fa39e55305e68154c350df451149c82350` |
| Approval | APPROVED โดย Business และ QA | APPROVED โดย Business และ QA |

Price valid row อยู่ใน DOM แต่ legacy CSS ซ่อนจากหน้าจอ เช่นเดียวกับ Status baseline. New Order valid row แสดงบนหน้าจอ.

```text
evidence/db-foundation-001/20-ci3-smoke/visual/excel-price-preview-1280x720.png
evidence/db-foundation-001/20-ci3-smoke/visual/excel-new-order-preview-1280x720.png
```

ก่อน cleanup มี Price temp row 1, New Order temp row 1, seeded request order 1 และ confirm tables/logs 0. หลัง cleanup database rows และ backup files กลับเป็น 0.

## ผลรัน deterministic

| เวลา UTC | Source SHA | Fixture SHA-256 | Result | Confirm calls | Messaging calls |
|---|---|---|---|---:|---:|
| 2026-08-18T13:46:04Z | `8dad4e3` | `eb105f2` | PASS | 0 | 0 |
| 2026-08-18T13:46:27Z | `8dad4e3` | `eb105f2` | PASS | 0 | 0 |
| 2026-08-18T13:46:51Z | `8dad4e3` | `eb105f2` | PASS | 0 | 0 |
| 2026-08-18T14:32:03Z | `8dad4e3` | `eb105f2` | PASS | 0 | 0 |
| 2026-08-18T14:34:41Z | `8dad4e3` | `eb105f2` | PASS | 0 | 0 |

Machine-generated evidence อยู่ใน local ignored path:

```text
evidence/db-foundation-001/20-ci3-smoke/excel-preview-runs.tsv
evidence/db-foundation-001/01-baseline/excel-preview-postrestore-verify.txt
evidence/db-foundation-001/20-ci3-smoke/visual/excel-status-preview-1280x720.png
evidence/db-foundation-001/20-ci3-smoke/visual/excel-price-preview-1280x720.png
evidence/db-foundation-001/20-ci3-smoke/visual/excel-new-order-preview-1280x720.png
```

## วิธีรัน

กรณี runtime/evidence อยู่คนละ worktree ให้ส่ง path ชัดเจน. ห้ามใส่ secret value ใน command หรือ log.

```bash
RUNTIME_ROOT=/absolute/path/to/samsonitetracking-ci4-migration
DBCTL_ENV_FILE="$RUNTIME_ROOT/.env" \
DBCTL_EVIDENCE_DIR="$RUNTIME_ROOT/evidence/db-foundation-001" \
./db/dbctl.sh --runtime-root "$RUNTIME_ROOT" safe-preview-smoke
```

เปลี่ยน `RUNTIME_ROOT` เป็น absolute path ของ runtime จริง. ห้ามใช้ path ตัวอย่างตรง ๆ.

ชื่อเดิม `excel-preview-smoke` ยังคงใช้ได้ แต่จะเรียก safety gate เดียวกันเพื่อบังคับฐานข้อมูลว่าง,
backup ว่าง, loopback-only และปิด outbound email/SMS ก่อนทดสอบ

Confirm characterization ใช้ safety gate เดียวกัน:

```bash
RUNTIME_ROOT=/absolute/path/to/samsonitetracking-ci4-migration
DBCTL_ENV_FILE="$RUNTIME_ROOT/.env" \
DBCTL_EVIDENCE_DIR="$RUNTIME_ROOT/evidence/db-foundation-001" \
./db/dbctl.sh --runtime-root "$RUNTIME_ROOT" safe-confirm-smoke
```

Negative/Isolation characterization:

```bash
RUNTIME_ROOT=/absolute/path/to/samsonitetracking-ci4-migration
DBCTL_ENV_FILE="$RUNTIME_ROOT/.env" \
DBCTL_EVIDENCE_DIR="$RUNTIME_ROOT/evidence/db-foundation-001" \
./db/dbctl.sh --runtime-root "$RUNTIME_ROOT" safe-confirm-negative-smoke
```

## ช่องว่างที่เหลือ

- Negative/Isolation disposition ยังรอ Business/QA และ Security approval.
- Simultaneous Confirm stress และ tracking ID `MAX+1` race ยังไม่ทดสอบ; ทำใน WP-04A/WP-05B.
- WP-00C และ Gate 1D ยังไม่ปิด.
