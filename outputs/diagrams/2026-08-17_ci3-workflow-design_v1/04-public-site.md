# Samsonite Tracking — Workflow Design: Public Website

> Source: `samsoniteci3/application/controllers/Track.php`, `Track_th.php`, `Contact.php`, `Contact_th.php`, `Rating.php`, `Error.php`, `welcome.php` + models ที่เกี่ยว (อ่านจาก working tree 2026-08-16)
> Scope: workflow ฝั่งเว็บสาธารณะ (UC-06 tracking, UC-07 contact, UC-08 rating) + error/welcome พร้อม mapping ไป CI4
> Generated: 2026-08-17

workflow ทุกตัวในไฟล์นี้บันทึกพฤติกรรมจริงของ CI3 รวม quirk เพื่อใช้เป็น design baseline ตอน implement CI4 — จุดที่ CI4 ต้องเปลี่ยนโครง (ไม่ใช่เปลี่ยน behavior) ระบุในตาราง mapping ท้ายแต่ละ section

| § | Diagram | Source UC |
|---|---|---|
| 4.1 | Public tracking — sequence | UC-06 |
| 4.2 | Public tracking — activity | UC-06 |
| 4.3 | Contact form — activity | UC-07 |
| 4.4 | Rating — sequence | UC-08 |

---

## 4.1 Public tracking — sequence

ลูกค้าเช็คสถานะงานซ่อมผ่านลิงก์ SMS (EN เท่านั้น) หรือพิมพ์ trackID ในฟอร์มหน้าแรก (`Track::index` เป็น default controller)

```mermaid
sequenceDiagram
    autonumber
    actor CUS as ลูกค้า
    participant SMS as SMS Gateway<br/>(cias_helper)
    participant TRK as Track / Track_th<br/>controller
    participant ROM as Request_order_model
    participant DB as MySQL

    Note over SMS,CUS: Phase A — ระบบหลังบ้านส่งลิงก์ (Order.php:736)
    SMS-->>CUS: ลิงก์ track/trackstatus/<trackID>

    Note over CUS,DB: Phase B — ค้นหาสถานะ
    CUS->>TRK: GET track/trackstatus/<trackID> (EN)<br/>หรือ POST searchText (EN/TH)
    alt input ว่าง
        TRK-->>CUS: flashdata error + render หน้าแรกใหม่
    else มีค่า
        TRK->>ROM: trackingSearch(searchText)
        ROM->>DB: TRUNCATE temp_status_log
        ROM->>DB: SELECT status_log WHERE order_id LIKE '%input%'
        ROM->>ROM: remap action_id (null + update_id 9/11/24/33 เป็น 6, นอกนั้น 3)
        ROM->>DB: INSERT batch ลง temp_status_log
        ROM->>DB: SELECT JOIN statusaction GROUP BY status_id
        ROM-->>TRK: timeline rows
        alt ไม่พบข้อมูล
            TRK-->>CUS: render "ไม่มีสินค้า" (วงกลม bg-unpass)
        else พบ
            TRK-->>CUS: render timeline (status_name_th + วันที่ พ.ศ.)
        end
    end
```

## 4.2 Public tracking — activity

จุดสำคัญ: การอ่านทำให้เกิด write — ทุกการค้นหา TRUNCATE ตาราง `temp_status_log` ที่แชร์ทั้งระบบ

```mermaid
flowchart TD
    START((●)) --> ENTRY{เข้าทางไหน?}
    ENTRY -->|"GET /track/trackstatus/id (EN, ลิงก์ SMS)"| GETID[รับ trackID จาก URI segment]
    ENTRY -->|"POST searchText (EN/TH ฟอร์มหน้าแรก)"| POSTID[รับจาก post searchText]
    GETID --> CHKEMPTY{ค่าว่าง?}
    POSTID --> CHKEMPTY
    CHKEMPTY -->|yes| RFAIL["flashdata error<br/>render index ใหม่ (ไม่ redirect)"]
    RFAIL --> END_F((◉))
    CHKEMPTY -->|no| TRUNC["TRUNCATE temp_status_log<br/>(ตารางแชร์ทั้งระบบ)"]
    TRUNC --> SEL["SELECT status_log<br/>WHERE order_id LIKE '%input%'<br/>(ต่อสตริงดิบ ไม่ bind)"]
    SEL --> REMAP["remap action_id:<br/>null + update_id 9/11/24/33 เป็น 6<br/>นอกนั้นเป็น 3"]
    REMAP --> INS[INSERT batch ลง temp_status_log]
    INS --> JOIN["SELECT JOIN statusaction<br/>GROUP BY status_id DESC"]
    JOIN --> FOUND{พบแถว?}
    FOUND -->|no| EMPTY["แสดง 'ไม่มีสินค้า'<br/>(ข้อความไทยแม้หน้า EN)"]
    EMPTY --> END_F2((◉))
    FOUND -->|yes| SHOW["แสดง timeline<br/>status_name_th + วันที่ พ.ศ.<br/>complete = วงกลมเขียว"]
    SHOW --> END_S((◉))

    classDef ok fill:#1f6f3a,stroke:#3fb950,color:#fff
    classDef fail fill:#6b1f1f,stroke:#f85149,color:#fff
    classDef gate fill:#1f3f6b,stroke:#58a6ff,color:#fff
    classDef warn fill:#6b5b1f,stroke:#d4a72c,color:#fff
    class SHOW,END_S ok
    class RFAIL,END_F,EMPTY,END_F2 fail
    class ENTRY,CHKEMPTY,FOUND gate
    class TRUNC,SEL warn
```

ความต่าง EN กับ TH (ต้องคง contract ตอน parity test)

| ประเด็น | Track (EN) | Track_th (TH) |
|---|---|---|
| รับ trackID จาก URI | ได้ (`trackstatus/<id>`) — เส้นทางลิงก์ SMS | ไม่ได้ — POST เท่านั้น |
| form_validation | ไม่มี (เช็คแค่ค่าว่าง) | `trim required` บน `searchText` |
| เช็ค complete จากคอลัมน์ | `status_name` | `status_name_th` (เทียบ literal อังกฤษเดิม) |
| ข้อความ error ตอน validation fail | `trackID failed` | `Book creation failed` (copy ผิดมาจาก Book) |

- **Route ตาย**: `rackstatus`, `rackstatus_th` ใน `routes.php:218-222` ชี้ `track/rackstatus` ซึ่งไม่มี method — ตกไป 404 override เส้นทางจริงคือ default routing `track/trackstatus` (ตรง legacy report §9.2)
- ลูกค้าค้นด้วย tracking no อย่างเดียว (partial match ได้เพราะ LIKE) — เห็นเฉพาะ timeline สถานะ ไม่มีชื่อลูกค้า/สินค้า/ราคา

### Mapping → CI4 (Phase ตาม plan v3 route-level strangler)

| CI3 element | CI4 target | หมายเหตุ |
|---|---|---|
| `Track::index` / `Track_th::index` | `App\Controllers\Track::index` แยก locale ด้วย route param หรือ controller คู่ตามเดิม | parity ก่อน — ห้ามรวม EN/TH จนกว่าจะผ่าน parity |
| `Track::trackstatus` (GET+POST) | `App\Controllers\Track::status($trackId = null)` + explicit routes `GET track/trackstatus/(:segment)`, `POST track/trackstatus` | URL contract ต้องคงเดิม (ลิงก์ SMS เก่ายังใช้ได้) |
| `trackingSearch` + `temp_status_log` | query ต่อ request ใน `StatusLogModel` (คืน result set เดียวกัน) — เลิก TRUNCATE ตารางแชร์ | การแก้นี้อนุมัติแล้วใน plan v3 §1 (shared temp-table 136 จุดต้องแยกตาม request) |
| `LIKE '%input%'` ต่อสตริงดิบ | Query Builder `like()` พร้อม escape | behavior partial match คงเดิม |
| remap `action_id` (9/11/24/33) | คง logic เดิมใน model + unit test ตรึงค่า | business rule ที่อนุมานจาก code — ห้ามเปลี่ยนช่วง parity |
| route ตาย `rackstatus` | ไม่สร้างใน CI4 | disposition: RETIRE (ยืนยันกับ function-disposition doc ก่อนปิด) |

---

## 4.3 Contact form — activity

ฟอร์มติดต่อ EN (`contact/addContact`) และ TH (`contact_th/addContact`) — logic เหมือนกันทั้งคู่ ต่างแค่ view และปลายทาง redirect

```mermaid
flowchart TD
    START((●)) --> FORM["ลูกค้ากรอกฟอร์ม<br/>fullname / email / phone / detail"]
    FORM --> POST["POST addContact<br/>(ไม่มี CSRF token — ปิดทั้งระบบ)"]
    POST --> VAL{"validation ผ่าน?<br/>required ทุกช่อง + valid_email"}
    VAL -->|no| RVAL["render หน้า contact เดิม<br/>พร้อม validation_errors<br/>(URL ค้างที่ /addContact)"]
    RVAL --> END_F((◉))
    VAL -->|yes| INSDB["INSERT contact<br/>query builder + trans_start/complete"]
    INSDB --> INSOK{insert_id > 0?}
    INSOK -->|no| RINS[flashdata error + redirect contact]
    RINS --> END_F2((◉))
    INSOK -->|yes| MAIL["PHPMailer SMTP<br/>ส่งไป info.thailand@samsonite.com<br/>SetFrom = อีเมลที่ลูกค้ากรอก"]
    MAIL --> MOK{ส่งเมลสำเร็จ?}
    MOK -->|"exception"| RMAIL["flashdata error<br/>แต่ row contact ค้างใน DB<br/>(ไม่ rollback)"]
    RMAIL --> END_F3((◉))
    MOK -->|yes| ROK[flashdata success + redirect contact]
    ROK --> END_S((◉))

    classDef ok fill:#1f6f3a,stroke:#3fb950,color:#fff
    classDef fail fill:#6b1f1f,stroke:#f85149,color:#fff
    classDef gate fill:#1f3f6b,stroke:#58a6ff,color:#fff
    classDef warn fill:#6b5b1f,stroke:#d4a72c,color:#fff
    class ROK,END_S ok
    class RVAL,END_F,RINS,END_F2,RMAIL,END_F3 fail
    class VAL,INSOK,MOK gate
    class MAIL warn
```

Contract ที่ต้องคงตอน parity

| หัวข้อ | ค่าใน CI3 |
|---|---|
| Validation | `fullname` trim required max 128, `email` trim required valid_email max 128, `phone` trim required (ไม่บังคับตัวเลข), `detail` trim required ไม่จำกัดยาว |
| DB | `INSERT contact` ครอบ transaction คืน insert_id |
| Email | PHPMailer โหลดจาก `lib/PHPMailer/` นอก application tree, ค่า SMTP เป็น literal placeholder (`SMTP_HOST` ฯลฯ), from = อีเมลลูกค้า |
| ลำดับ side effect | insert ก่อน แล้วค่อยส่งเมล — เมลพัง row ไม่ถูกลบ |

### Mapping → CI4

| CI3 element | CI4 target | หมายเหตุ |
|---|---|---|
| `Contact::addContact` / `Contact_th::addContact` | `App\Controllers\Contact::create` + explicit POST route ต่อ locale | รวม logic ชั้น service เดียว ได้ต่อเมื่อ output/redirect ต่อ locale คงเดิม |
| PHPMailer + placeholder SMTP | CI4 `Email` service หรือ PHPMailer ผ่าน Composer, config จาก `.env` | secret ห้าม hardcode (plan v3 G-04); ต้องได้ค่า SMTP จริงจาก ops ก่อน cutover — ปัจจุบัน placeholder แปลว่าเมล prod อาจไม่เคยส่งสำเร็จ ต้อง verify พฤติกรรมจริงก่อนตรึง parity |
| CSRF ปิด | เปิด CSRF filter ของ CI4 บนฟอร์มสาธารณะ | plan v3 G-04 อนุมัติ — ต้อง regression ว่าฟอร์มยัง submit ได้ |
| validation rules | ย้าย rules เดิมเป๊ะไป CI4 Validation | รวม quirk `phone` ไม่บังคับตัวเลข |

---

## 4.4 Rating — sequence

หน้า rating ฝั่งลูกค้าถูกปิดแล้ว (`Rating::index` redirect ทันที — dead code) ผู้เรียกจริงคือ modal หลังบ้านหน้า `trackingclose` แต่ endpoint `addRating` ยังเปิดสาธารณะไม่มี guard

```mermaid
sequenceDiagram
    autonumber
    actor OP as เจ้าหน้าที่<br/>(modal trackingclose)
    participant RT as Rating::addRating<br/>(ไม่มี auth guard)
    participant RM as Rating_model
    participant ROM as Request_order_model
    participant DB as MySQL

    OP->>RT: POST addRating<br/>requestId, ratingTrackId, ratingBranchId,<br/>ratingOne..Eight, ratingComment
    RT->>RM: getRatingsByTrackId(trackId)
    alt เคยมีคะแนนของ trackId นี้แล้ว
        RT-->>OP: JSON 400 "Invalid data." (ไม่บันทึก)
    else ยังไม่มี
        RT->>RM: addRatings() — trans_begin
        RM->>DB: INSERT batch rating 8 แถว (add_id 1-8)
        opt มี comment
            RM->>DB: DELETE rating_comment WHERE track_id + branch_id
            RM->>DB: INSERT rating_comment ใหม่
        end
        RM->>RM: commit เมื่อ affected_rows > 0 ไม่งั้น rollback
        alt insert ล้มเหลว
            RT-->>OP: JSON 500
        else สำเร็จ
            RT->>ROM: getOrderByRequestId(requestId)
            alt action_status === 5
                ROM->>DB: UPDATE request_order SET action_status=7,<br/>date_complete=now, date_update_status=now
            else ไม่ใช่ 5 หรือไม่พบ order
                Note over RT,ROM: ข้ามเงียบ ๆ — คะแนนบันทึกแล้ว ยังตอบ success
            end
            RT-->>OP: JSON success {status true, code 0}
        end
    end
```

- กันโหวตซ้ำชั้นเดียวด้วย SELECT ก่อน INSERT — ไม่มี unique constraint ไม่มี lock ยิงพร้อมกันหลุดได้
- validation ช่วงคะแนน 1-5 อยู่ฝั่ง JS เท่านั้น server cast int อย่างเดียว

### Mapping → CI4

| CI3 element | CI4 target | หมายเหตุ |
|---|---|---|
| `Rating::index` (dead — redirect ทันที) | ไม่สร้าง | disposition: RETIRE (view `en/rating` + JS เก่าเลิกด้วยกัน) |
| `Rating::addRating` | `App\Controllers\Rating::create` (JSON) + auth filter session หลังบ้าน | ผู้เรียกจริงมีแค่ modal หลังบ้าน — ใส่ filter ได้โดยไม่กระทบผู้ใช้จริง แต่ต้อง confirm กับ business ก่อน (เปลี่ยน exposure = ต้องมี decision record ตาม plan v3 change control) |
| กันโหวตซ้ำด้วย SELECT | คง logic + เพิ่ม unique constraint ระดับ DB | ส่วน constraint เป็น release ฝั่ง DB — แยกตาม plan v3 (DB conversion แยกจาก app cutover) |
| อัปเดต `action_status` 5 เป็น 7 | คง logic เดิมใน service + test ตรึง | ผูกกับ status lifecycle ใน `02-order-tracking.md` |

---

## Error / welcome

| Controller::method | พฤติกรรม CI3 | CI4 target |
|---|---|---|
| `Error::index` (404 override) | เรียก `isLoggedIn()` ของตัวเอง — ยังไม่ล็อกอินแสดงฟอร์ม login admin, ล็อกอินแล้ว redirect `pageNotFound`; ตอบ HTTP 200 เสมอ | หน้า 404 จริง (HTTP 404) ธีมเว็บสาธารณะ — เลิกโชว์ฟอร์ม login (เปลี่ยนที่ต้องบันทึก decision เพราะแตะ behavior) |
| `Welcome::index` | หน้า welcome ตั้งต้น CI ไม่ถูกลิงก์จากที่ไหน | ไม่สร้าง — disposition: RETIRE |
| `pageNotFound` (ซ้ำในทุก controller) | view `404` wrapper admin | รวมเหลือจุดเดียวใน CI4 (custom 404 handler) |

## Notes

- ทุก endpoint สาธารณะไม่มี CSRF (`csrf_protection = FALSE` ทั้งระบบ) และ `global_xss_filtering = FALSE` — CI4 เปิด CSRF ตาม G-04 แล้วต้อง regression ฟอร์มสาธารณะทุกตัว
- `temp_status_log` เป็น global mutable state ระหว่าง request ลูกค้าทุกคน — จุดแก้ที่อนุมัติแล้ว (plan v3 §1)
- background CMS: view สาธารณะ hardcode `uploads/web/trackstatus_laptop.png` — ตาราง `background` ที่ admin เขียนไม่เคยถูกอ่านฝั่ง public (ดู `05-master-data.md` ส่วน Background_web)
- SMS ลิงก์ EN เท่านั้น — TH ไม่มีเส้นทางรับ trackID จาก URL; ถ้า business ต้องการลิงก์ TH เป็น scope ใหม่ ไม่อยู่ใน parity

**Render**: GitHub / Obsidian / VS Code Mermaid
