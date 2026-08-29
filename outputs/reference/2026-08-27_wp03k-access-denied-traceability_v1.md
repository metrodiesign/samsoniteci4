# WP03K Access Denied traceability

เอกสารนี้เชื่อม CI3 presentation ของหน้า Access Denied กับ CI4 response seam หลัง authorization ถูกปฏิเสธ. ขอบเขตคือผู้ใช้ authenticated ที่ client เลือก HTML อย่างชัดเจนเท่านั้น.

## แหล่งอ้างอิง

| รายการ | ค่า |
|---|---|
| CI3 source | `/Users/king_developer/Desktop/Project/samsoniteci3` |
| CI3 pin | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` |
| CI3 body | `application/views/access.php` |
| CI3 asset | `assets/images/access.png` |
| SHA-256 asset | `cd1c2f92a6f5c4dd695037905e764db9a1e6b3810870f4bf329dad53b6b96c8c` |

## Source → target

| CI3 | CI4 | การรักษาหรือการแก้ไข |
|---|---|---|
| `BaseController::loadThis()` | `AccessDeniedResponder` จากสอง filter | filter ตัดสิน deny ก่อน responder เสมอ |
| `views/access.php` | `app/Views/access_denied.php` | คง `.content-wrapper`, `content-header`, copy และ alt text |
| Admin header/footer | `app/Views/layout.php` | ใช้ admin chrome เดิมและ profile ปิด `.page-header` ที่ซ้ำ |
| `assets/images/access.png` | `public/assets/images/access.png` | คัดลอก byte-identical ตาม SHA-256 ข้างต้น |

## Contract ที่เปลี่ยนโดยเจตนา

- CI4 คง HTTP `403`; ไม่ย้อนกลับไปสู่พฤติกรรม CI3 ที่ไม่ได้กำกับ status.
- JSON เป็น body คงที่ `{"error":"forbidden"}` สำหรับ request ที่ไม่เลือก HTML, AJAX, session ที่ไม่ authenticated หรือ header ที่ไม่ชัดเจน.
- HTML ต้องมี `text/html` ที่ valid และ q มากกว่า JSON อย่างชัดเจน; tie, wildcard, malformed, q ไม่ valid และ duplicate fail closed เป็น JSON.
- title ใช้ `Access Denied | Samsonite Tracking` ตาม CI4 admin layout ไม่คืน title legacy.

## ขอบเขตที่ไม่แตะ

- `AuthenticationFilter`, `ApiCsrfFilter`, generic 404, routing, controller-level business error และ Rating.
- `app/Views/master_form.php`, `app/Views/users_form.php`, `public/assets/css/admin.css`, `public/assets/css/main.css` และ `tests/ci4/UserHttpTest.php`.
- WP03H auth, WP03I Contact/public chrome และ WP03J Tracking.

## หลักฐานการตรวจ

| คำสั่ง | ผล |
|---|---|
| `php vendor/bin/phpunit tests/ci4/AccessDeniedHttpTest.php tests/ci4/AuthorizationFilterTest.php` | ผ่าน `45 tests, 249 assertions` |
| Branchless subset ใน `OrderHttpTest` | ผ่าน `7 tests, 43 assertions` |
| `php vendor/bin/phpunit tests/ci4/MenuHttpTest.php` | ผ่าน `14 tests, 95 assertions` |
| `composer test` | ผ่าน `379 tests, 6151 assertions` |
| `vendor/bin/phpstan analyse --memory-limit=1G`, `bash scripts/ci-check.sh` และ `git diff --check` | ผ่าน ไม่มี error |

## ช่องว่าง environment

Browser backend ไม่มีใน session นี้. Normalized DOM, asset network, interaction และ visual ที่ `1440x900` / `390x844` จึงเป็น `NOT VERIFIED`; HTTP/static assertions ไม่ถูกนับเป็น visual PASS.
