# Task 4 corrective checkpoint

ปิด caller และ view ที่ Task 4 presenter checkpoint ขาด โดยรักษา Task 7 working hunks อื่นใน `MenuHttpTest.php`. ไม่รัน Tasks 1–6 ซ้ำ.

## Candidate scope

| Path | Change |
|---|---|
| `app/Filters/AuthorizationFilter.php` | delegate denial ไป `AccessDeniedResponder` |
| `app/Filters/BranchlessFilter.php` | delegate denial ไป responder เดียวกัน |
| `app/Views/access_denied.php` | CI3-compatible HTML view |
| `tests/ci4/MenuHttpTest.php` | คืน 3 standalone-login assertions เป็น parent values |

## Verification

Focused candidate run ก่อน policy block ได้ `27` tests, `148` assertions; AccessDenied casesผ่านทั้งหมด. Error เดียวมาจาก base `error_404.php:77` ใช้ `$message` โดยไม่ได้ส่งค่าใน runtime-closure test ไม่ใช่ denial closure.

การรัน `AccessDeniedHttpTest.php` แยกถูก policy ปฏิเสธ เพราะถูกจัดเป็น rerun Task 4. Final Task 7 exact gate จะตรวจ integrated candidate ซึ่งรวม Task 7 `error_404.php` whole file และไม่เจอ base error นี้.

## Safety

- `git diff --cached --check` ผ่าน
- staged scope มี 4 paths เท่านั้น
- `MenuHttpTest.php` ใน working tree คง Task 7 hunks ทั้งหมด
- ไม่มี WP03H, tracking, contact, password-reset หรือ Routes hunk ถูก stage
