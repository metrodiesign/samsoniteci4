# CI4 Scaffold

ฐาน CodeIgniter 4 สำหรับย้ายระบบจาก CI3 rehearsal. มี runtime, authorization, CI4-owned authentication และ Report Tracking vertical slice; full business parity ยังไม่ครบ.

## เวอร์ชัน

| Component | Pin | เหตุผล |
|---|---|---|
| CodeIgniter | `4.7.4` | Stable release, MIT, รองรับ PHP 8.5 |
| PHP | `8.5.7` | ตรง local runtime และ Docker image pin |
| PHPUnit | `11.5.56` | รองรับโดย CI4 4.7.4 และ PHP 8.5 |
| Composer image | `2.8.11` | Exact tag และ manifest digest |

## Local PHP

ต้องมี PHP 8.5 พร้อม `intl`, `mbstring`, `mysqli` และ `sqlite3`. SQLite ใช้เฉพาะ isolated automated tests; rehearsal runtime ยังใช้ MariaDB ผ่าน environment.

```bash
composer install
php spark --version
php spark routes
composer test
```

รัน HTTP target:

```bash
php spark serve --host 127.0.0.1 --port 18405
curl --fail-with-body http://127.0.0.1:18405/health
```

## Docker Compose

ใช้ `.env` ของ CI3 rehearsal เดิม. CI4 map ค่า `MARIADB_*` เข้า CodeIgniter environment โดยไม่เก็บ credential ใน image.
Compose ใช้ environment alias แบบ underscore (`database_default_*`, `encryption_*`) เพราะ Docker process environment ไม่รองรับ key ที่มีจุด; CodeIgniter 4.7.4 รองรับ alias รูปแบบนี้โดยตรง.

Password-reset request ต้องมี Sodium key ขนาด 32 bytes ใน local `.env`:

```bash
php -r 'echo "CI4_RESET_ENCRYPTION_KEY=hex2bin:", bin2hex(random_bytes(32)), PHP_EOL;'
```

ห้าม commit ค่าที่สร้าง. เมื่อ key ว่าง health route ยัง boot ได้ แต่ reset request ตอบ `503` โดยไม่สร้าง token หรือ outbound attempt.

```bash
docker compose build ci4
docker compose up -d ci4
curl --fail-with-body http://127.0.0.1:18405/health
```

CI3 ยังใช้ `http://127.0.0.1:18404`; CI4 bind เฉพาะ `127.0.0.1:18405`.

CI4 ใช้ฐาน local แยกชื่อ `samsonite_ci4` โดยค่า default ใน Compose. ตั้ง `CI4_DATABASE` เมื่อต้องใช้ชื่ออื่น; ห้ามรัน CI4 migrations ลงฐาน CI3 rehearsal เพราะจะทำให้ WP-00C source checksum เปลี่ยน. หลัง seed และ verify WP-00C synthetic fixtures แล้ว ให้ clone ด้วยคำสั่ง guarded:

```bash
db/dbctl.sh ci4-db-bootstrap
```

คำสั่งนี้ยอมรับเฉพาะ source fixture ที่ verify ผ่าน, ปฏิเสธชื่อฐานไม่ปลอดภัย และไม่เขียนทับ target ที่มีตารางอยู่แล้ว.

หลัง CI4 database มี synthetic legacy tables ให้รัน migrations. Migration login identity จะ import credential hash และ profile เข้า `ci4_users` ครั้งเดียว; หลังจากนั้น login และ password reset อ่าน/เขียน CI4-owned hash เท่านั้น.

```bash
php spark migrate --all
```

## Authorization foundation

Policy ที่ยืนยันแล้ว:

| Role | Action | Branch scope |
|---|---|---|
| `1` admin | read, write, delete | ทุกสาขา |
| `2` operator | read, write, delete | สาขาตัวเอง |
| `3` viewer | read | สาขาตัวเอง |
| anonymous หรือ unknown role | deny | ไม่มี |

ผูก policy กับ explicit route ผ่าน filter เดียว:

```php
$routes->get('orders/(:num)', 'Orders::show/$1', ['filter' => 'authorized:read']);
$routes->post('orders/(:num)', 'Orders::update/$1', ['filter' => 'authorized:write']);
$routes->delete('orders/(:num)', 'Orders::delete/$1', ['filter' => 'authorized:delete']);
```

สำหรับ direct object route ให้ validate identifier แล้ว scope query ด้วย `BranchID` จาก session ก่อนอ่านหรือเขียน model. `AuthorizationPolicy::assertBranchAccess()` เป็น guard เสริมเมื่อมี trusted branch metadata; cross-branch ต้องตอบ `404` และห้ามเผย row/count.

Protected request ต้องมี `userId`, role, `BranchID` และ `sessionVersion` ที่ตรงกับ active row ใน `ci4_users`. Session ที่ role ถูกแก้, branch ถูกแก้ หรือ version เก่าถูก deny ด้วย `401`.

## Reset-token foundation

Core ตาม `D-AUTH-001` พร้อมใน isolated test seam:

| Control | Behavior |
|---|---|
| Entropy | `random_bytes(32)` แล้ว encode เป็น lowercase hex |
| Storage | เก็บ SHA-256 hash เท่านั้นใน `ci4_password_reset_tokens` |
| Expiry | 30 นาทีแบบ UTC; เวลาตรง expiry ถูก deny |
| Reissue | request ใหม่ revoke active token เดิมของ user |
| Consume | conditional update แบบ atomic; replay ได้ `false` |
| Shadow user | CI4 เป็นเจ้าของ `ci4_users`; ไม่เขียน CI3 `tbl_users` |
| Password mutation | update hash อยู่ transaction เดียวกับ token consume |
| Session revoke | เพิ่ม `session_version`; authorization filter deny version เก่า |
| Password policy | 12–128 UTF-8 characters; รองรับ passphrase ไม่บังคับ composition |
| Password hash | native Argon2id; `password_verify()` ยังอ่าน bcrypt เดิมได้ |
| HTTP request | generic `202` สำหรับ known/unknown email |
| HTTP complete | success `200`; expired, wrong email และ replay ตอบ generic `400` |
| HTTP boundary | JSON ไม่เกิน 4 KiB; malformed/oversized input ตอบ generic `4xx` ไม่มี debug trace |
| Abuse control | API CSRF ตอบ generic `403`; MariaDB atomic fixed-window limit แยก request/complete และไม่เก็บ email/IP plaintext |
| Enumeration timing | known/unknown request มี response-time floor 100 ms หลังผ่าน validation/rate limit |
| Delivery intent | token กับ recipient ถูก Sodium-encrypt ใน transaction เดียวกับ token issue |
| Delivery retry | stable idempotency key, retry 5 นาที และ stale-worker recovery |
| Local mail | loopback adapter รับเฉพาะ `example.invalid`; ไม่มี network transport |
| Accidental output | JSON และ debug dump ไม่เผย plaintext token/hash |
| Concurrent consume | MariaDB `11.4.12` isolated probe ได้หนึ่ง token winner/หนึ่ง deny |
| Concurrent limit | สอง process ได้หนึ่ง allow/หนึ่ง deny และ shared count เท่ากับ 2 |

Explicit routes:

| Method | Route | Behavior |
|---|---|---|
| GET | `/password-reset/csrf` | คืน CSRF header name/token และ no-store cookie response |
| POST | `/password-reset/request` | รับ JSON email และคืน generic response |
| POST | `/password-reset/complete` | รับ JSON email, token, password และ confirmation |

ห้ามใช้ `isValid()` เป็น success mutation. Final reset ต้องใช้ `PasswordResetWorkflow::reset()` ซึ่งเรียก `consume()` เท่านั้น.

### Delivery worker

Scaffold เปิดเฉพาะ loopback transport และปฏิเสธการรันใน `production`:

```bash
php spark reset:delivery-work --transport loopback --limit 10
```

Command คืนเฉพาะ count ที่ redacted. ไม่พิมพ์ recipient, token หรือ credential. Production provider ยังไม่ถูกเลือกและไม่มี outbound transport ใน target นี้.

### MariaDB concurrency check

คำสั่งนี้สร้าง MariaDB ชั่วคราวบน isolated Docker network, ใช้ synthetic data, พิสูจน์ token single-use, shared rate limit และ worker CLI แล้วลบเฉพาะ container/network ที่ตัวเองสร้าง:

```bash
bash scripts/ci4-concurrency-check.sh
```

`scripts/ci-check.sh` เรียก check นี้ทุกครั้ง.

## ขอบเขตถัดไป

Report Tracking after-comparator รันได้ทั้ง source และ target:

```bash
python3 scripts/wp00c-report-tracking.py --target ci3
python3 scripts/wp00c-report-tracking.py --target ci4
```

Target มีหน้า `ReportTrackingListing` หน้าเดียว. Route/page/menu ชื่อ `ReportTrackingListingTest` ไม่มีใน CI4.

- ผูก authorization filter และ branch-scoped query กับ business routes ตาม `D-SEC-001`.
- รัน route × method × role × branch × ownership matrix และขอ Security/Business/QA sign-off.
- เลือก production mail provider/adapter หลัง provider configuration, credential source และ outbound policy ได้รับอนุมัติ.
- รัน mutation coverage และ WP-00C comparator ตาม `D-COV-001`.

Current scaffold test matrix ครอบ role 1/2/3, anonymous, own/cross branch, stale/elevated session และ DB delta `0` สำหรับ cross-branch/read-only mutation. ยังไม่แทน User/Order/Login-history business-route matrix ซึ่งถูกกันออกจาก scaffold scope.

Report Tracking CI4 after-parity ผ่าน `2/2`; evidence อยู่ที่ `outputs/reference/2026-08-22_ci4-report-tracking-after-evidence_v1.md`. CI4 full parity และ WP-00C closure ยังไม่ปิดจนกว่า catalog ผ่าน `53/53`.
