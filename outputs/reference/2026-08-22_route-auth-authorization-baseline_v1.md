# Route/Auth/Authorization Baseline

เอกสารนี้บันทึก CI3 behavior baseline บน active pin สำหรับ route, authentication, session และ authorization. ใช้ synthetic fixture เท่านั้น; CI4 absence/parity ยังเป็น downstream gate.

## Verdict

**CI3 baseline: `BASELINE_CAPTURED_WITH_OPEN_GAPS`**

| รายการ | ผล |
|---|---|
| CI3 source | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` และ worktree clean |
| Runtime | `samsonitetracking-ci3:ee1c95e` ที่ `127.0.0.1:18404` |
| Runner | `scripts/wp00c-route-auth.py` |
| Explicit routes | `178` records |
| Implicit candidates | `51` public controller methods |
| Database side effect จาก read-only probes | `0` tables |
| Cleanup | initial/final table checksums ตรงกัน |
| Closure | `OPEN_CI3_MUTATION_MAILER_RESET_AND_AUTHZ_DECISIONS` |
| Disposition | `outputs/reference/2026-08-22_ci3-open-gaps-disposition_v1.md` — `PROPOSED_PENDING_APPROVAL` |

Machine evidence อยู่ที่ `evidence/route-auth-authorization-baseline.json`.

## Coverage

| Case | ผล | ขอบเขต |
|---|---|---|
| `ROUTE-EXPLICIT-001` | `CAPTURED_WITH_SIDE_EFFECT_ROUTES_OPEN` | GET/read-only `118`; side-effect routes `58` ยังรอ dedicated mutation cases |
| `ROUTE-IMPLICIT-001` | `CAPTURED_WITH_SIDE_EFFECT_ROUTES_OPEN` | GET/read-only `48`; side-effect candidates `3` ยังไม่ยิง |
| `ROUTE-404-001` | `CAPTURED` | anonymous และ authenticated unknown URL |
| `ROUTE-DEFECT-001` | `CAPTURED` | `rackstatus`, `bookListing`, report aliases |
| `AUTH-LOGIN-001` | `PASS` | valid login, redirect, session contract, login history |
| `AUTH-LOGIN-002` | `PASS` | wrong password, unknown user, deleted user |
| `AUTH-SESSION-001` | `CAPTURED` | anonymous, active, synthetic expired cookie, logout |
| `AUTHZ-ROUTE-001` | `PASS` | protected read-only route × anonymous/role 1/2/3 |
| `AUTHZ-BRANCH-001` | `CAPTURED_KNOWN_SCOPE_AND_IDOR_BEHAVIOR` | branch listing และ direct object/history reads |

Read-only response classification: explicit GET `105` เป็น `HTTP_2XX`, `13` เป็น legacy error page; implicit GET `29` เป็น `HTTP_2XX`, `19` เป็น legacy error page. Legacy errors ถูกเก็บเป็น evidence ไม่ถูกแปลงเป็น CI4 requirement.

## Authentication และ session

Valid `wp00c-admin` ผ่าน `POST /loginMe` และเข้า `/dashboard` ด้วย HTTP `200`.

Required session keys ครบ 8 ตัว:

```text
userId, role, GroupID, BranchID, roleText, name, lastLogin, isLoggedIn
```

CI3 ยังมี internal key `__ci_last_regenerate`; runner ไม่ตีความว่าเป็น approved session-ID rotation. การเพิ่ม regeneration เป็น CI4 security correction ต้องมี after test แยก.

Invalid cases ทั้งสามถูกปฏิเสธและไม่เข้า protected dashboard:

| Case | Final path |
|---|---|
| wrong password | `/login` |
| unknown user | `/login` |
| deleted user | `/login` |

Anonymous และ synthetic expired session cookie ถูกส่งกลับ `/login`; active session เข้า `/dashboard`; `GET /logout` ทำลาย session และ request ถัดไปกลับ `/login`.

## Authorization และ branch scope

Anonymous ถูกปฏิเสธทุก protected read-only route ที่ใช้ใน matrix. Synthetic role 1, role 2 branch 1, role 2 branch 2 และ role 3 ผ่าน route reachability โดยไม่พบ login bounce.

| Probe | ผลที่สังเกต |
|---|---|
| `userListing` branch 1 | เห็นเฉพาะ synthetic operator A |
| `userListing` branch 2 | เห็นเฉพาะ synthetic operator B |
| branch 1 → `editOld/9003` | อ่านข้อมูล operator B ได้ |
| branch 2 → `editOld/9002` | อ่านข้อมูล operator A ได้ |
| branch 1 → `editOrdersOld/91007` | อ่าน order branch 2 ได้ |
| branch 2 → `editOrdersOld/91001` | อ่าน order branch 1 ได้ |
| branch 1 → `login-history/9003` | อ่าน login history operator B ได้ |

ผล cross-branch เป็น legacy IDOR baseline สำหรับ CI4 authorization decision; ไม่ใช่ security approval และห้ามใช้ sidebar visibility แทน server-side authorization.

`bookListing/(:num)` ถูกแยกเป็น known route defect เพราะ route ชี้ `order/bookListing/$1` และตอบ HTTP `200` พร้อม PHP warning จาก invalid callback; ไม่ใช้ผลนี้ยืนยัน authorization pass.

## Legacy route defects

| URL/probe | Observed CI3 behavior |
|---|---|
| unknown URL | HTTP `200` พร้อม PHP warning จาก `Error::index` callback collision |
| `rackstatus` / `rackstatus/1` | invalid callback warning |
| `bookListing/2` | invalid callback warning จาก target ผิด controller |
| `ReportTrackingListing/0/1` | HTTP `200`; route parameter quirk ถูกเก็บเป็น baseline |
| `reportsummary/0/1` | HTTP `200`; repeated parameter quirk ถูกเก็บเป็น baseline |

CI4 ต้องใช้ real `404`, explicit route และ approved correction/retirement; ห้ามสร้าง route จาก warning หรือเปิด Auto Routing Legacy.

## Browser verification

ทดสอบซ้ำบน OpenAI Browser วันที่ `2026-08-22` ด้วย synthetic users และ loopback runtime เท่านั้น. Machine evidence อยู่ที่ `evidence/route-auth-authorization-browser.json`.

| Case | ผล | หลักฐานสำคัญ |
|---|---|---|
| `AUTH-LOGIN-001` | `PASS` | role 2 branch A/B login เข้า `/dashboard` |
| `AUTH-SESSION-001` | `CAPTURED` | anonymous protected route และ logout กลับ `/login` |
| `AUTH-RESET-001` | `CAPTURED_WITH_CI3_MAILER_DEFECT` | valid email สร้าง activation row แต่ PHPMailer warning ทำให้ redirect ไม่สมบูรณ์ |
| `AUTH-RESET-002` | `CAPTURED_WITH_CORRECTION_REQUIRED` | valid และ old token เปิด new-password form; invalid token กลับ `/login`; ไม่ submit final password |
| `ROUTE-404-001` | `CAPTURED_KNOWN_CI3_DEFECT` | unknown route แสดง invalid callback warning |
| `AUTHZ-BRANCH-001` | `CAPTURED_KNOWN_IDOR` | listing มี branch scope แต่ direct cross-branch user/order/history ยังอ่านได้ |
| `USER-CRUD-001` | `CAPTURED_PENDING_DELETE_CONFIRMATION` | Browser add/edit ผ่าน; delete ไม่กดเพราะเป็น destructive action ที่ต้องยืนยันตอนกด |
| `USER-CRUD-002` | `CAPTURED_PARTIAL` | required-field validation ผ่าน; duplicate-email และ password-mismatch ยังไม่ปิด |
| `USER-PASSWORD-001` | `CAPTURED_VALIDATION_ONLY` | form/max-length validation ถูกจับ; ไม่ submit successful password mutation |

## Closure decision

CI3 route/auth/authorization behavior ถูกจับผ่าน Browser แล้ว และมี disposition record แล้ว แต่ยังปิด baseline ทั้งก้อนไม่ได้. เหตุผล:

- `AUTH-RESET-001` ยังไม่มี loopback outbound stub และ CI3 PHPMailer path มี legacy warning.
- `AUTH-RESET-002` ยังต้องตัดสิน TTL, single-use และ final password mutation contract.
- explicit side-effect routes `58` รายการ และ implicit side-effect candidates `3` รายการ ยังรอ dedicated mutation/CSRF/validation slices.
- `AUTHZ-BRANCH-001` ยืนยัน IDOR เดิมแล้ว; CI4 ต้อง deny cross-branch หลัง Security/Business approval.
- `USER-CRUD-001/002` และ `USER-PASSWORD-001` ยังมี mutation/negative branches ที่ไม่ครบ; user `9005` และ login rows รอบล่าสุดถูก cleanup แบบ exact scoped แล้ว.
- Disposition ของ IDOR, reset-token, mailer และ mutation coverage อยู่ใน `2026-08-22_ci3-open-gaps-disposition_v1.md`; สถานะยัง `PROPOSED_PENDING_APPROVAL`.

ดังนั้นยังไม่เริ่ม CI4 parity. ตอนนี้ disposition ถูกบันทึกแล้ว แต่ต้องมี approval และ execution evidence ก่อนเปลี่ยนเป็น closed.

## Safety และ downstream gate

- Browser password test ใช้ synthetic runtime-only และคืน user role/deletion matrix เดิม; password hash ปัจจุบันยังเป็น random bcrypt.
- Exact prior synthetic password hashes ไม่ได้อยู่ใน evidence; หากต้องการ byte-identical database checksum ต้อง reseed WP-00C จาก approved restore point ก่อน parity.
- Side-effect routes ไม่ถูกยิงด้วย GET แบบเดาสุ่ม; ต้องมี mutation/CSRF/validation slices แยก.
- Read-only probes เปลี่ยน database `0/31` tables.
- Evidence หลักปิดเฉพาะ read-only route/auth capture; ยังไม่ปิด WP-00C หรือ Gate 1D.

CI4 งานถัดไป:

1. สร้าง explicit route records ครบ `178` พร้อม HTTP verb และ filter mapping.
2. เพิ่ม auth filter สำหรับ protected route และ session contract regression.
3. เพิ่ม server-side authorization/branch ownership tests; cross-branch reads ต้อง deny ตาม approved correction.
4. ยืนยัน CI4 ไม่มี `ReportTrackingListingTest` route/page/menu และไม่มี implicit route.
5. รัน after comparator แล้วขอ Business/QA/Security approval ก่อนเปลี่ยน disposition.
