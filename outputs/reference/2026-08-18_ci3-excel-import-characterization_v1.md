# CI3 Excel import characterization v1

เอกสารนี้บันทึก vertical slice แรกของ WP-00C สำหรับ Excel status import เฉพาะขั้น preview บน CI3 ที่ pin แล้วและ MariaDB target. ผลนี้ยังไม่ปิด WP-00C ทั้งชุด.

## สถานะ

| รายการ | ค่า |
|---|---|
| Case ID | `XLS-PREVIEW-001` |
| สถานะ | PASS 5 รอบที่บันทึก; รอบล่าสุดหลัง fail-safe review |
| CI3 source | `8dad4e331a90f5c6765954454910b451eb0ff8e5` |
| Public seam | Login, `GET /UploadexcelListing`, `POST /ExcelDataAdd` |
| Fixture SHA-256 | `eb105f22550a5a3d80a94e260a26cc6047b90d54edfa3fb5427ffddbf4bb4522` |
| Browser visual | CHARACTERIZED ที่ viewport `1280x720`, DPR `1` |
| Screenshot SHA-256 | `342e25c3e7b87a3403e03b8ddd68cf933f0fea5681afd4f25054520974d7d7e6` |
| Target database | MariaDB 11.4.12, utf8mb4_general_ci, InnoDB |

## ขอบเขตความปลอดภัย

รอบนี้เป็น preview-only. ห้ามเรียก confirm action หรือ external messaging ทุกชนิด.

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
5. เปิดหน้า upload ผ่าน `GET /UploadexcelListing`.
6. ส่ง XLSX synthetic ผ่าน `POST /ExcelDataAdd`.
7. ตรวจ HTTP, rendered HTML, Thai text, temp-table effect และ absence of confirm side effects.
8. Restore database, ลบ uploaded fixture และยืนยัน CI3 source ไม่เปลี่ยน.

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
```

## วิธีรัน

กรณี runtime/evidence อยู่คนละ worktree ให้ส่ง path ชัดเจน. ห้ามใส่ secret value ใน command หรือ log.

```bash
DBCTL_ENV_FILE=/path/to/local/.env \
DBCTL_EVIDENCE_DIR=/path/to/evidence/db-foundation-001 \
./db/dbctl.sh safe-preview-smoke
```

ชื่อเดิม `excel-preview-smoke` ยังคงใช้ได้ แต่จะเรียก safety gate เดียวกันเพื่อบังคับฐานข้อมูลว่าง,
backup ว่าง, loopback-only และปิด outbound email/SMS ก่อนทดสอบ

## ช่องว่างที่เหลือ

- Business/QA ยังไม่ได้ approve expected result, fixture หรือ UX/UI baseline.
- ต้องตัดสินว่า PHPExcel error banners 67 รายการเป็น legacy behavior ที่ต้องคงไว้ชั่วคราว หรือ defect ที่ต้องปิดก่อน approve UX/UI baseline.
- Price preview และ new-order preview ยังไม่ถูก characterize.
- Confirm flow ตั้งใจไม่ทดสอบรอบนี้เพื่อรักษาข้อห้าม email/SMS และ external side effect.
- WP-00C และ Gate 1D ยังไม่ปิด.
