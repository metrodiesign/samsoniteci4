# CI3 smoke rehearsal บน MariaDB 11.4 (ปิด WP-00K ส่วน CI3 suite)

> Historical evidence: ผลนี้มาจาก CI3 pin `8dad4e3`. Active pin เปลี่ยนเป็น `ee1c95e` หลัง PR #3; ต้อง rerun ตาม `2026-08-21_ci3-source-repin-pr3_v1.md` ก่อนใช้ปิด Gate 1D.

> วันที่: 2026-08-17
> ทำซ้ำได้ด้วย: `./db/dbctl.sh web-build && ./db/dbctl.sh web-up && ./db/dbctl.sh smoke`
> สถานะ: **ผ่านครบ**

## ทำไมต้องทำ

การแปลง charset/engine ที่ผ่านมาพิสูจน์แค่ว่า "ข้อมูลถูกต้องตอน query ตรง ๆ"
ยังไม่ได้พิสูจน์ว่า "แอปเดิมใช้งานได้" ซึ่งแผนบังคับไว้ที่ WP-00K (`CI3 smoke/full suite`),
WP-00M (`deploy DB target โดย CI3 application code เดิม`) และ Gate 1D (`CI3 target-DB parity`)

## ผลลัพธ์

CI3 3.1.6 บน PHP 7.4.33 + Apache ต่อกับ MariaDB 11.4.12 ที่แปลงเป็น utf8mb4_general_ci +
InnoDB แล้ว **ทำงานได้ครบทุกเส้นทางที่ทดสอบ**

| # | ตรวจอะไร | ผล |
|---|---|---|
| S1 | `GET /` (controller `track`) | 200 — ผ่านหมายถึง Apache + mod_rewrite + PHP + **DB connect ตอน bootstrap** + session path ใช้ได้ทั้งชุด |
| S2 | `GET /track_th/index` | 200, มีอักษรไทย, ไม่มี mojibake |
| S3 | `GET /track/trackstatus/S26070273` | 200 (เส้นทางนี้เขียน `temp_status_log` จริง) |
| S4 | `GET /login` | 200 |
| S5 | `POST /loginMe` แล้ว `GET /dashboard` | 200 และไม่ถูกเด้งกลับหน้า login |
| S6 | `GET /order` ในสถานะ login | 200 |
| S7 | deny test ของ WP-00F | `application/config/config.php` 403, `system/core/CodeIgniter.php` 403, `SECRETS-LOCAL.md` 403, ไม่มีเนื้อหา phpMyAdmin ถูกเสิร์ฟ |
| S8 | log ของ web | ไม่มี PHP Fatal / Uncaught / database error |
| S9 | log ของ DB | ไม่มี `Illegal mix of collations` |
| S10 | restore แล้ว verify | 17 ข้อผ่าน และรหัสผ่านชั่วคราวถูกลบกลับ |
| S11 | รีโป CI3 | HEAD ตรง pin และ clean เหมือนก่อนเริ่ม |

## สิ่งที่ค้นพบระหว่างทดสอบ

### ข้อบกพร่องจริงของระบบเดิม: หน้า 404 พังทั้งระบบ

ทุก URL ที่ไม่มีจริงตอบ **HTTP 200** พร้อม PHP warning แทนที่จะเป็น 404

```
Message: call_user_func_array() expects parameter 1 to be a valid callback,
         class 'Error' does not have a method 'index'
```

**สาเหตุ**: `routes.php:42` ตั้ง `404_override = 'error'` ให้ไปที่
`application/controllers/Error.php` แต่ **PHP 7 มี class ชื่อ `Error` มาให้ในตัว**
(ตระกูล `Throwable`) CI3 เช็คแล้วเห็นว่า class มีอยู่แล้วจึงไม่โหลดไฟล์ของแอป
แล้วเรียก `Error::index()` ซึ่ง class ในตัวของ PHP ไม่มี

พิสูจน์ในคอนเทนเนอร์:

```
class_exists("Error", false)      => true
method_exists("Error", "index")   => false
(new ReflectionClass("Error"))->getFileName() => false   // ไม่ได้มาจากไฟล์
```

**ไม่ใช่ผลจากการย้าย DB** — production รัน PHP 7.4.32 (ตาม header ของ dump) จึงมีอาการนี้
อยู่แล้วในตอนนี้ ผลกระทบ: soft 404 (search engine และ monitoring อ่านว่าเพจปกติ),
และเปิดเผย PHP warning เมื่อ `display_errors` เปิด

**สิ่งที่ต้องทำตอนย้าย CI4**: เปลี่ยนชื่อ controller เป็นอย่างอื่น เช่น `Errorpage`
แล้วตั้ง status 404 ให้ถูก — PHP 8.5 ก็ยังมี class `Error` ในตัวเหมือนกัน

### `application/controllers/Error.php` เป็น dead code

สืบเนื่องจากข้อบน ไฟล์นี้ไม่เคยถูกโหลดเลย ควรอยู่ใน function disposition ว่า
"ไม่เคยทำงานจริงตั้งแต่ย้ายมา PHP 7"

## วิธีสร้าง runtime

### ทำไมต้อง PHP 7.4 ไม่ใช่ 8.x

`lib/PHPExcel` มี curly-brace string offset (`$str{0}`) **153 จุดใน 19 ไฟล์** ซึ่งเป็น
fatal parse error ตั้งแต่ PHP 8.0 และจะระเบิดทันทีที่ `Upload_excel.php:56` require
บวกกับ CI 3.1.6 เองรองรับ PHP 8 ตั้งแต่ 3.1.12 ขึ้นไป

ตรวจแล้วว่า `application/` และ `system/` **สะอาด** ไม่มี `each()`, `create_function()`,
`mysql_*`, `ereg()` หรือ curly-brace offset เลย ตัวขวางคือ `lib/` ล้วน ๆ

### ส่ง source เข้า image ด้วย build context ไม่ใช่ bind mount

```bash
DOCKER_BUILDKIT=1 docker build -f web/Dockerfile --build-context ws=web \
  -t samsonitetracking-ci3:8dad4e3 "$CI3_SOURCE_ROOT"
```

| เหตุผล | รายละเอียด |
|---|---|
| ไม่ผิด AC-DKR-005 | เป็นการอ่านตอน build ไม่ใช่ bind mount ตอน runtime จึงไม่ต้องบันทึก deviation เพิ่ม |
| บังคับ WP-00F ได้จริง | `web/Dockerfile.dockerignore` ตัด `SECRETS-LOCAL.md`, `tools/` (phpMyAdmin), `demo/`, `uploads/` และ dead library ออกตั้งแต่ยังไม่เข้า daemon |
| ได้ artifact ตรวจสอบได้ | image tag ผูกกับ commit `8dad4e3` |

`.dockerignore` วางไว้ที่รากของ build context ไม่ได้เพราะรีโป CI3 เป็น read-only จึงใช้
ความสามารถของ BuildKit ที่อ่าน `<ชื่อ Dockerfile>.dockerignore` จากที่เดียวกับ Dockerfile

รีโป CI3 ถูกตรวจ `rev-parse HEAD` + `status --short` ทั้งก่อนและหลัง build ต้องเหมือนเดิม
ไม่งั้นสคริปต์หยุดทันที

### Config ที่ override (ไม่แก้โค้ดแอปแม้แต่บรรทัดเดียว)

| ไฟล์ | แก้อะไร | ทำไม |
|---|---|---|
| `application/config/database.php` | เขียนใหม่ทั้งไฟล์ให้อ่านจาก `getenv()` | ไฟล์เดิมไม่อยู่ใน git และเป็น placeholder ล้วน (`'DB_HOST'` ฯลฯ) จึงต่อ DB ไม่ได้ตั้งแต่แรก |
| | `char_set` → `utf8mb4`, `dbcollat` → `utf8mb4_general_ci` | ของเดิมเป็น `utf8` = utf8mb3 ไม่ตรงกับ DB |
| | `pconnect` → `FALSE` | connection แบบ persistent ค้างข้าม request ทำให้อ่านผลตอนซ้อมยาก |
| `application/config/config.php` | `sess_save_path` → `/var/www/html/application/sess/` | ของเดิม hardcode `/home/track/public_html/application/sess/` ของเซิร์ฟเวอร์เดิม และ session ถูก autoload จึงถูกแตะ **ทุก request** |
| | `log_threshold` → `4` | ของเดิมเป็น `0` (ปิด log) ทำให้ตรวจ error ไม่ได้ |

### Apache

`a2enmod rewrite` + `AllowOverride All` — Debian image ตั้ง `AllowOverride None` มาให้
ซึ่งจะทำให้ `.htaccess` ทั้งไฟล์ถูกเมินเงียบ ๆ รวมถึง front controller ของ CI3

`SetEnv CI_ENV development` ต้องตั้งที่ vhost เพราะ `index.php:56` อ่านจาก
`$_SERVER['CI_ENV']` ไม่ใช่ `getenv()` การตั้ง env ของ container เฉย ๆ ไม่มีผล

## ความปลอดภัยของข้อมูลระหว่างทดสอบ

- ทดสอบ login ด้วยการตั้ง bcrypt ของรหัสผ่านชั่วคราวให้ user **หนึ่งคน** (`userId=13`)
  โดยสร้าง hash ด้วย PHP ตัวเดียวกับที่จะ verify ไม่ hardcode
- ก่อนแตะข้อมูลใด ๆ สร้าง backup ก่อนเสมอ แล้ว restore กลับหลังทดสอบ
- ตรวจยืนยันว่ารหัสผ่านชั่วคราวหายไปแล้วหลัง restore (`S10 temp password rolled back`)
- artifact ไม่มี `SECRETS-LOCAL.md`, `tools/pma/`, `demo/`, `uploads/` ของจริง

## การแยกตัวจากโปรเจกต์อื่น

เทียบกับสภาพเครื่องก่อนเริ่มโปรเจกต์: มีของเพิ่มทั้งหมด **4 รายการ เป็นของเราล้วน**
(container `db` + `web`, volume `mariadb_data` + `backup_data`) ไม่มีของ project อื่น
หายหรือเปลี่ยน และ `dbctl.sh diff` ผ่านทุกรอบ

## สิ่งที่ยังไม่ได้ทำ

- **ไม่ใช่ full parity suite** — WP-00C (characterization catalog + approved fixtures)
  ยังไม่มี จึงยังนิยาม "parity 100%" ตามที่ Gate 1D ขอไม่ได้ รอบนี้พิสูจน์ว่าระบบทำงานได้
  บน DB ใหม่ ไม่ใช่ว่าทุกพฤติกรรมเหมือนเดิมทุกจุด
- ไม่ได้ทดสอบ Excel import (`Upload_excel`) ซึ่งเป็นเส้นทางที่ใช้ temp table 4 ใบและ PHPExcel
- ไม่ได้ทดสอบส่งอีเมล (PHPMailer) และ flow reset password
- ไม่ได้ทดสอบ report/export ที่ข้อมูลเยอะ
- PHP 7.4 หมดอายุการสนับสนุนแล้ว ใช้เป็นเครื่องซ้อมของ CI3 เท่านั้น ห้ามใช้เป็น runtime
  ของ CI4 ซึ่งต้องเป็น PHP 8.5 ตาม target stack
