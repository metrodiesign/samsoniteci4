# WP-01D Web Boundary Evidence (v1)

หลักฐานยืนยันว่า web port ของ CI4 เปิดเฉพาะเนื้อหาใต้ `public/` — path นอก docroot เข้าตรงไม่ได้ (AC-REL-004)

## สรุปผล

| รายการ | ผล |
|---|---|
| Positive control `GET /health` | HTTP 200 |
| Path นอก public 15 ตัว (app/, vendor/, writable/, spark, composer.*, .env*, phpunit.xml.dist, tests/, scripts/, db/) | ไม่มีตัวไหนตอบ 200/301/302 |
| Gate exit code | 0 |

## Before

- ยังไม่มี external HTTP negative test — มีแต่โครง `public/index.php` + `.htaccess` ตาม scaffold
- ความเสี่ยง: config/source/credential path อาจถูกเสิร์ฟออก web port โดยไม่มี gate จับ

## Change

- เพิ่ม `scripts/ci4-web-boundary-check.sh` — ยิง HTTP จริงจากนอก container เข้า `${CI4_BASE_URL:-http://127.0.0.1:18405}`: positive control `/health` ต้อง 200 แล้วไล่ 15 path ต้องห้าม ตัวไหนตอบ 200/301/302 = FAIL ทันที
- wire เข้า `scripts/ci-check.sh`: รันเมื่อ runtime ตอบ `/health`; ไม่มี runtime (เช่น GitHub runner) รายงาน skip ชัดเจน ไม่หลอกว่าเขียว

## After (ผลรันจริง 2026-08-23)

```
$ bash scripts/ci4-web-boundary-check.sh
PASS web boundary: /health=200, 15 non-public paths denied

$ bash scripts/ci-check.sh; echo $?
...
0
```

- Runtime ที่ใช้ทดสอบ: container `samsonite-ci4-browser` (image `samsonitetracking-ci4:4.7.4-php8.5.7`, publish `127.0.0.1:18405->8080`, เสิร์ฟผ่าน `php spark serve` docroot `public/`)
- Skip path ตรวจแล้วด้วย `CI4_BASE_URL=http://127.0.0.1:1` — ได้ `PASS web boundary skipped: CI4 runtime not listening`

## ข้อจำกัดที่บันทึกไว้

- หลักฐานนี้ผูกกับ `php spark serve` (dev server) — production web server (Apache/nginx) ตาม BLK-008 ยังไม่ถูกสร้าง เมื่อสร้างแล้วต้องรัน check ชุดเดียวกันซ้ำบน topology จริงก่อนปิด AC-REL-004 ฉบับ production
- Container ที่ทดสอบ build จากโค้ด ณ เวลา build image — route ภายในอาจเก่ากว่า working tree แต่ไม่กระทบข้อพิสูจน์ boundary เพราะการปิดกั้น path นอก docroot มาจาก front controller ไม่ใช่ route table
