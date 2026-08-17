# Samsonite Tracking — Workflow Design: Excel Import

> Source: `samsoniteci3/application/controllers/Upload_excel.php` (806 บรรทัด) + `Request_order_model.php` + views `tracking/show_*` (อ่านจาก working tree 2026-08-16)
> Scope: Excel import 3 ชุดแบบสองขั้น preview/confirm (UC-09) พร้อม mapping ไป CI4
> Generated: 2026-08-17

ระบบมี import 3 ชุด ใช้กลไกสองขั้นเหมือนกันทั้งหมด: ขั้น preview เทลงตาราง temp ระดับ global แล้วขั้น confirm อ่าน temp กลับมาเขียนจริง เอกสารนี้บันทึกพฤติกรรมจริงรวม bug เพื่อเป็น baseline — จุดที่ต้องมี decision ก่อน implement CI4 รวมไว้ท้ายไฟล์

| § | Diagram | Source UC |
|---|---|---|
| 3.1 | กลไกสองขั้นร่วม 3 ชุด — activity | UC-09 |
| 3.2 | Confirm ชุด update status — activity | UC-09 |
| 3.3 | Confirm ชุด new order — activity | UC-09 |

---

## 3.1 กลไกสองขั้นร่วม 3 ชุด — activity

ทั้ง 3 ชุดก็อปโค้ดชุดเดียวกัน ต่างแค่ temp table, column mapping และสิ่งที่ confirm เขียน — ขั้น preview/confirm ส่งต่อกันผ่าน **temp table ใน DB ไม่ใช่ session** จึงแชร์ข้ามผู้ใช้ทั้งระบบ

```mermaid
flowchart TD
    START((●)) --> LIST["หน้า listing (GET)<br/>ฟอร์ม multipart input file เดียว"]
    LIST --> UP["POST *DataAdd<br/>รับ $_FILES file"]
    UP --> EXT{"นามสกุล xls/xlsx?<br/>(เช็คจากชื่อไฟล์ล้วน ไม่เช็ค MIME)"}
    EXT -->|no| RFAIL["flashdata error แล้วเรียก listing ตรง<br/>ไม่ redirect — ผู้ใช้เห็นหน้าเปล่า<br/>flash ไปโผล่ request ถัดไป"]
    RFAIL --> END_F((◉))
    EXT -->|yes| TRUNC["empty_table ล้าง temp ทั้งใบ<br/>(เกิดก่อน move ไฟล์ — ล้างของ user อื่นด้วย)"]
    TRUNC --> MOVE["move_uploaded_file ไป uploads/excel/<br/>ชื่อ = วันที่ + rand(0,999999)"]
    MOVE --> MOK{ย้ายสำเร็จ?}
    MOK -->|no| RBLANK["ไม่มี else — จบหน้าเปล่า<br/>ทั้งที่ temp ถูกล้างไปแล้ว"]
    RBLANK --> END_F2((◉))
    MOK -->|yes| READ["PHPExcel 1.8.x อ่าน sheet<br/>toArray ข้าม header + แถวคอลัมน์ A ว่าง"]
    READ --> INS["insert_batch ลง temp table<br/>ไม่มี validation ต่อแถวฝั่ง server"]
    INS --> QJOIN["query join temp กับ request_order<br/>render ตาราง preview"]
    QJOIN --> GATE["gate อยู่ฝั่ง client ทั้งหมด<br/>PHP ใน view + JS ซ่อน/แสดงปุ่ม Confirm"]
    GATE --> CONF["POST *Confirm<br/>ส่งแค่ count_ex ตัวเดียว"]
    CONF --> CNT{"count_ex > 0?"}
    CNT -->|no| RCNT[flashdata error + redirect listing]
    RCNT --> END_F3((◉))
    CNT -->|yes| REQ["query temp table ใหม่ทั้งหมด<br/>(ข้อมูล ณ เวลา confirm ไม่ใช่ที่ preview แสดง)"]
    REQ --> WRITE["ประมวลผลต่อแถว<br/>เขียน request_order ตามชุด (§3.2, §3.3)"]
    WRITE --> DONE["flash success + redirect listing<br/>ไม่ล้าง temp — confirm ซ้ำได้ไม่จำกัด"]
    DONE --> END_S((◉))

    classDef ok fill:#1f6f3a,stroke:#3fb950,color:#fff
    classDef fail fill:#6b1f1f,stroke:#f85149,color:#fff
    classDef gate fill:#1f3f6b,stroke:#58a6ff,color:#fff
    classDef warn fill:#6b5b1f,stroke:#d4a72c,color:#fff
    class DONE,END_S ok
    class RFAIL,END_F,RBLANK,END_F2,RCNT,END_F3 fail
    class EXT,MOK,CNT gate
    class TRUNC,GATE,REQ warn
```

เทียบ 3 ชุด

| หัวข้อ | ชุด 1 update status | ชุด 2 new order | ชุด 3 price |
|---|---|---|---|
| Routes | `UploadexcelListing`, `ExcelDataAdd`, `ExcelConfirm` | `UploadneworderexcelListing`, `ExcelNewOrderDataAdd`, `ExcelNewOrderConfirm` | `UploadexcelpriceListing`, `ExcelPriceDataAdd`, `ExcelPriceConfirm` |
| Temp table | `temp_updatestatus_order` | `temp_updatestatus_neworder` | `temp_updatestatus_price_order` |
| Join key กับ `request_order` | `orderIDShow` + `customerTel` | `orderIDShow` + tel (เจอ = ซ้ำ = ผิด) | `number_cmg` |
| Column map | A-I (G ราคาเมื่อ >0, H waranty, I number_cmg) | A-H (ไม่มี I) | A-G + I (ข้าม H) |
| ผลต่อ `request_order` | `update_batch` สถานะเป็น 3 หรือ 4 + วันที่ + ราคา + waranty | `insert` ทีละแถว order ใหม่ status 2 หรือ 4 | `update_batch` เฉพาะ `RepairPrice` ไม่แตะสถานะ |
| Log | `status_log` (`update_id`) + `uploadstaus` | `status_log` (`action_id`) | ไม่มี |
| Guard ฝั่ง server ตอน confirm | `action_status` เดิมต้องเป็น 2 หรือ 3 | ไม่มี (dup check อยู่ client และ JS เทียบผิดจนปุ่มโชว์เสมอ) | ไม่มี (บล็อก guard ถูก comment) |
| Transaction | ก้อน insert log แยกกัน, `update_batch` ไม่มี transaction, ไม่มีครอบทั้งชุด | ทีละแถว ทีละ transaction — ล้มกลาง loop แถวก่อนหน้า commit ไปแล้ว | ไม่มี |

## 3.2 Confirm ชุด update status — activity

การตัดสินสถานะต่อแถว: จับคู่ข้อความสถานะจากคอลัมน์ E กับตาราง `tracking_status` ด้วย `description_en LIKE` (ต่อสตริงดิบ — injection ผ่านเนื้อไฟล์ excel ได้)

```mermaid
flowchart TD
    START((●)) --> ROW[อ่านแถวจาก temp join request_order]
    ROW --> GUARD{"action_status เดิม<br/>เป็น 2 หรือ 3?"}
    GUARD -->|"no (รวม join ไม่เจอ = NULL)"| SKIP[ข้ามแถวเงียบ ๆ]
    SKIP --> NEXT{มีแถวต่อ?}
    GUARD -->|yes| MAPST["จับคู่ข้อความคอลัมน์ E กับ tracking_status<br/>ด้วย description_en LIKE '%text%'<br/>ได้ status_id + flag success"]
    MAPST --> SUC{success == 1?}
    SUC -->|yes| ST4["action_status = 4<br/>+ date_repair, date_update_status<br/>+ date_repair_complete, ราคา, waranty"]
    SUC -->|no| WAR{"waranty_cmg = OUT/out?"}
    WAR -->|yes| ST3W["action_status = 3<br/>+ date_repair + date_repair_waranty"]
    WAR -->|no| ST3["action_status = 3 (ไม่มี date_repair)"]
    ST4 --> COLLECT[สะสมเข้า batch]
    ST3W --> COLLECT
    ST3 --> COLLECT
    COLLECT --> NEXT
    NEXT -->|yes| ROW
    NEXT -->|no| WRITE["insert_batch uploadstaus<br/>insert_batch status_log (update_id)<br/>update_batch request_order (ไม่มี transaction)"]
    WRITE --> DONE["flash success เสมอ<br/>แม้ไม่มีแถวไหนผ่าน gate"]
    DONE --> END_S((◉))

    classDef ok fill:#1f6f3a,stroke:#3fb950,color:#fff
    classDef fail fill:#6b1f1f,stroke:#f85149,color:#fff
    classDef gate fill:#1f3f6b,stroke:#58a6ff,color:#fff
    classDef warn fill:#6b5b1f,stroke:#d4a72c,color:#fff
    class DONE,END_S ok
    class SKIP fail
    class GUARD,SUC,WAR,NEXT gate
    class MAPST,WRITE warn
```

## 3.3 Confirm ชุด new order — activity

ชุดเดียวที่สร้าง order ใหม่ — จุดเปราะสุดของไฟล์: gen trackID แบบ MAX+1 (race ได้), array ไม่ reset ต่อแถว, ชื่อคอลัมน์ราคามี space ต่อท้าย

```mermaid
flowchart TD
    START((●)) --> ROW[อ่านแถวจาก temp]
    ROW --> DATE["แปลงวันที่ DD/MM/YYYY เป็น YYYY-MM-DD<br/>explode orderIDShow ด้วย / ได้ number_cmg + numberId"]
    DATE --> TRACK["gen trackID = G + ปีเดือน + running<br/>จาก SELECT MAX ... LIKE '%GyyMM%' แล้ว +1<br/>(สอง user พร้อมกันชนได้)"]
    TRACK --> SUC{"status text จับคู่ tracking_status<br/>success == 1?"}
    SUC -->|yes| ST4["action_status = 4 + date_repair_complete"]
    SUC -->|no| ST2["action_status = 2<br/>(date_repair_complete ของแถวก่อน<br/>อาจติดมาเพราะ array ไม่ reset)"]
    ST4 --> HARD["ค่า hardcode: branch_type_id 6, branchID 90<br/>คอลัมน์ 'RepairPrice ' มี space ต่อท้าย<br/>ราคาจึงไม่ถูกบันทึกในชุดนี้"]
    ST2 --> HARD
    HARD --> INSONE["insert request_order ทีละแถว ทีละ transaction"]
    INSONE --> IOK{insert_id > 0?}
    IOK -->|yes| LOG["insert status_log (action_id 4 หรือ 2)"]
    IOK -->|no| SKIP["ข้าม — แถวก่อนหน้า commit ไปแล้ว<br/>ไม่ rollback ทั้งชุด"]
    LOG --> NEXT{มีแถวต่อ?}
    SKIP --> NEXT
    NEXT -->|yes| ROW
    NEXT -->|no| DONE[flash success + redirect]
    DONE --> END_S((◉))

    classDef ok fill:#1f6f3a,stroke:#3fb950,color:#fff
    classDef fail fill:#6b1f1f,stroke:#f85149,color:#fff
    classDef gate fill:#1f3f6b,stroke:#58a6ff,color:#fff
    classDef warn fill:#6b5b1f,stroke:#d4a72c,color:#fff
    class DONE,END_S ok
    class SKIP fail
    class SUC,IOK,NEXT gate
    class TRACK,HARD warn
```

ชุด 3 (price) ไม่มี diagram แยก: confirm อ่าน temp แล้ว `update_batch` คอลัมน์ `RepairPrice` อย่างเดียว — guard ทั้งหมดถูก comment ทิ้ง แถวที่ join ไม่เจอ (`request_id` NULL) ก็เข้า batch, `temp_pic <= 0` เขียนค่าว่างทับราคาเดิมได้

### Mapping → CI4

| CI3 element | CI4 target | หมายเหตุ |
|---|---|---|
| PHPExcel 1.8.x (2015, EOL, vendored ใน `lib/`) | PhpSpreadsheet ผ่าน Composer | plan v3 ระบุ PhpSpreadsheet ใน research แล้ว — column mapping ต้องเทียบเซลล์ต่อเซลล์ตอน parity test |
| `$_FILES` + `move_uploaded_file` + เช็คนามสกุลจากชื่อไฟล์ | CI4 `UploadedFile` + validation `ext_in` + `is_image`/MIME ตามชนิด | CI4 ≥ 4.7.4 มี fix filename/path traversal + content-extension validation ที่ plan บังคับ (G-04) |
| temp table แชร์ 3 ใบ (`empty_table` ทั้งใบ) | เพิ่ม batch identifier ต่อการ upload (คอลัมน์ batch_id หรือ per-request storage) | การแก้อนุมัติแล้ว (plan v3 §1 shared temp-table 136 จุด + non-goal "แยกข้อมูลตาม request/import batch") — result contract ของ preview ต้องเท่าเดิม |
| confirm อ่าน temp กลับ + `count_ex` จาก client | confirm ผูก batch_id + server นับแถวจริง | ปิดช่อง confirm ข้าม batch ของคนอื่น |
| confirm ซ้ำได้ไม่จำกัด | ล้าง batch หลัง commit หรือ token กันซ้ำ | ต้องมี decision record — พฤติกรรมเดิมสร้าง `status_log`/`uploadstaus` ซ้ำ |
| `description_en LIKE '%text%'` ต่อสตริงดิบ | Query Builder bind | ผล match ต้องเท่าเดิม (รวม partial match) |
| ไม่มี transaction ครอบ confirm | transaction ครอบต่อ batch | ตรง G-03 (ไม่ทำข้อมูลเสีย); ชุด 2 ต้องเลิก commit ทีละแถว |
| ไฟล์ค้าง `uploads/excel/` ใต้ web root ไม่ลบ | เก็บนอก public/ + ลบหลังอ่าน (หรือ retention ตามที่ business กำหนด) | CI4 โครง `public/` แยก web root อยู่แล้ว |

## Notes — จุดที่ต้องมี decision ก่อน implement

พฤติกรรมเหล่านี้เป็น bug ที่ทำงานอยู่จริงบน production — parity 100% ตามตัวอักษรจะ replicate bug ทั้งหมด ต้องให้ business/engineering ตัดสินและบันทึกเป็น decision record (ตาม change control ของ plan v3) ก่อนเขียน spec รายชุด:

- ชุด 2: คอลัมน์ `'RepairPrice '` (space ต่อท้าย) ทำให้ราคาไม่ถูกบันทึก — แก้ = พฤติกรรมเปลี่ยน (ราคาจะเริ่มถูกบันทึก)
- ชุด 2: `$OrderInfo` ไม่ reset ต่อแถว — `date_repair_complete` รั่วจากแถว success ไปแถวถัดไป
- ชุด 2: ปุ่ม Confirm แสดงเสมอแม้ทุกแถวซ้ำ (JS เทียบ `data_class_error == 1` กับ string ว่าง) และ server ไม่เช็คซ้ำ — เสี่ยง order ซ้ำ
- ชุด 3: guard ถูก comment — เขียนราคาว่างทับของเดิมได้
- ชุด 1: confirm ที่ไม่มีแถวผ่าน gate ยังขึ้น success
- วันที่ไม่ลบ 543 (เวอร์ชัน comment เคยลบ) — ต้อง confirm ว่าไฟล์ excel ที่ใช้จริงปัจจุบันเป็น ค.ศ.
- `status_log` ถูกเขียนคนละคอลัมน์ (`update_id` ชุด 1 / `action_id` ชุด 2) — ตรวจ schema จริงตอน DB baseline (repo ไม่มีไฟล์ `.sql`)

**Render**: GitHub / Obsidian / VS Code Mermaid
