# CI3 Open Gaps Disposition

เอกสารนี้กำหนด disposition สำหรับ IDOR, password-reset token, mailer และ mutation coverage ของ CI3 active pin. เป็น decision record สำหรับ CI4 correction/rebaseline; ยังไม่ใช่หลักฐานว่า baseline ปิดแล้ว.

## สถานะรวม

| Decision | ประเด็น | Disposition | สถานะปิด |
|---|---|---|---|
| `D-SEC-001` | Cross-branch IDOR | `CORRECT_AND_REBASELINE` | รอ CI4 policy, implementation, Security/Business/QA approval |
| `D-AUTH-001` | Reset-token TTL และ single use | `CORRECT_AND_REBASELINE` | รอ CI4 workflow และ negative tests |
| `D-INT-001` | PHPMailer และ delivery failure | `REPLACE_AND_REBASELINE` | รอ email boundary, loopback evidence และ approval |
| `D-COV-001` | Side-effect route coverage | `EXECUTE_AND_CLOSE` | รอ 61 route entries, 3 deterministic rounds และ case approvals |

คำว่า `DISPOSITIONED` หมายถึงมี target behavior แล้ว. ไม่เท่ากับ `BASELINE_CLOSED` จนกว่าจะมี implementation, evidence และ approval ครบ.

## Decision D-SEC-001 — Cross-branch IDOR

### CI3 evidence

CI3 กรอง branch ใน listing บางหน้า แต่ direct object endpoints รับ `userId` หรือ `orderId` แล้วอ่าน/เขียนโดยไม่ตรวจ ownership. Browser พบ branch A อ่าน user/order ของ branch B และ branch B อ่านของ branch A.

หลักฐาน source: `samsoniteci3/application/controllers/User.php:964-1001`, `samsoniteci3/application/controllers/User.php:1088-1097`, `samsoniteci3/application/controllers/Order.php:947-1018`, `samsoniteci3/application/controllers/Order.php:1143-1155`.

### Decision

- Central role ที่ได้รับอนุมัติให้ดูทุกสาขาอ่านข้อมูลทุกสาขาได้.
- Branch-scoped role อ่าน/แก้ไข/ลบได้เฉพาะ object ของสาขาตัวเองและ action ที่ route policy อนุญาต.
- Cross-branch object request ตอบ `404 Not Found` หรือ JSON equivalent เพื่อไม่เผย existence ของ object.
- Authorization ต้องตรวจหลัง validate identifier และก่อน model read/write ทุก endpoint; sidebar visibility ไม่นับเป็น authorization.
- CI3 IDOR คงไว้เป็น historical baseline; ห้าม patch CI3 เพื่อทำให้ evidence หาย.

### Acceptance criteria

| Probe | Own branch | Cross branch |
|---|---|---|
| User view/edit/delete | allow ตาม role policy | `404`, DB delta `0` |
| Order view/edit/delete/print | allow ตาม role policy | `404`, DB delta `0` |
| Login history by user ID | allow ตาม approved visibility | `404`, no foreign rows/body markers |
| Listing/search/pagination | own-branch rows only | no foreign row, no count leak |

ต้องรัน matrix `route × method × role × branch × object ownership` อย่างน้อย role 1, branch role 2 และ read-only role ที่อนุมัติ. Security, Business และ QA ต้อง sign off ก่อนเปลี่ยนเป็น closed.

## Decision D-AUTH-001 — Reset-token policy

### CI3 evidence

CI3 สร้าง token ด้วย `random_string('alnum', 15)`, เก็บ plaintext, ไม่ตรวจ expiry และยอมรับ old token. Password reset lookup และ delete ใช้ email เป็นหลัก.

หลักฐาน source: `samsoniteci3/application/controllers/Login.php:128-161`, `samsoniteci3/application/controllers/Login.php:261-311`, `samsoniteci3/application/models/Login_model.php:90-108`.

### Decision

- TTL ของ reset token = `30 minutes` นับจาก `created_at`.
- Token เป็น opaque value จาก CSPRNG อย่างน้อย 32 bytes; DB เก็บเฉพาะ token hash ไม่เก็บ plaintext.
- Token ใช้ได้ครั้งเดียว; success, expiry, revoke หรือ password reset ใหม่ทำให้ token เดิมใช้ไม่ได้.
- Request ใหม่ revoke token ที่ยัง active ของ user เดิมก่อนสร้าง token ใหม่.
- Token ผูกกับ user, purpose และ reset request; wrong email, malformed, expired และ reused token ต้อง deny.
- Known/unknown email ใช้ user-visible response เดียวกัน เพื่อไม่เปิด user enumeration; ส่ง email เฉพาะ known active account ผ่าน delivery boundary.
- Successful reset update password hash ใน transaction เดียวกับ token consume และ revoke active sessions ของ user นั้น.
- Email/reset log ห้ามมี plaintext token หรือ password.

### Acceptance criteria

| Scenario | Expected |
|---|---|
| Unknown email | generic response, no account disclosure |
| Valid token within 30 minutes | one password update, token consumed |
| Expired/old/reused/wrong-email token | deny, password unchanged |
| Two reset requests | newest token only; previous token denied |
| Replay successful POST | no second password update or token side effect |
| Concurrent consume | one winner, one deny; no duplicate success |

TTL `30 minutes` เป็น selected policy ของ record นี้; เปลี่ยนได้เฉพาะผ่าน Security/Business decision ใหม่.

## Decision D-INT-001 — Mailer boundary

### CI3 evidence

CI3 เรียก bundled PHPMailer 5.x ด้วย placeholder SMTP config, hardcoded CC และ legacy `__autoload()`. Browser reset request สร้าง DB row แต่แสดง deprecation/header warning และ redirect ไม่สมบูรณ์.

หลักฐาน source: `samsoniteci3/application/controllers/Login.php:204-230`, `samsoniteci3/application/controllers/Login.php:314-362`, `samsoniteci3/lib/PHPMailer/PHPMailerAutoload.php:45`.

### Decision

- ไม่ patch bundled PHPMailer ต่อ; active CI4 path ใช้ email adapter/service ที่มี maintenance support.
- DB mutation และ email delivery แยก boundary: transaction เขียน reset request และ outbox/delivery intent ก่อนตอบรับ.
- HTTP response สำเร็จเมื่อ delivery intent ถูก persist; ไม่ผูก business success กับ live SMTP response.
- Worker/provider retry ต้อง idempotent; timeout/failure เป็น retryable delivery state และมี manual recovery path.
- Password reset success ไม่ rollback เพราะ notification ล้มเหลว; ห้ามส่ง password plaintext ใน notification.
- ไม่มี hardcoded CC/from/SMTP credential; config อ่านจาก environment/secret manager.
- CI3/CI4 tests ใช้ loopback stub เท่านั้น; production transport ต้องถูกปิดใน local test runtime.

### Acceptance criteria

| Probe | Expected |
|---|---|
| Valid reset request | one token row + one delivery intent |
| Loopback provider success | one redacted message, no PHP warning |
| Provider timeout/failure | persisted retry state, stable user response, no duplicate intent |
| Password reset success | password/token transaction succeeds even if notification retryable |
| Logs/evidence | no token, password, credential or unredacted PII |
| Missing provider config | fail closed without outbound attempt |

## Decision D-COV-001 — Mutation coverage

### Scope

Route runner พบ side-effect entries `58` explicit และ `3` implicit. รวม `61` route entries ที่ต้องยิงผ่าน real HTTP/UI seam; ห้ามนับ GET probe หรือ controller/model call ตรงเป็น pass.

| Slice | Count | Route entries |
|---|---:|---|
| Auth/session/reset/password | 5 | `loginMe`, `logout`, `changePassword`, `resetPasswordUser`, `createPasswordUser` |
| User CRUD | 3 | `addNewUser`, `editUser`, `deleteUser` |
| Core master CRUD | 30 | `add/edit/delete` ของ `branch`, `branchtype`, `statustype`, `producttype`, `book`, `brand`, `condition`, `estimateprice`, `fixed`, `provider` |
| Order lifecycle | 6 | `sendorderUpdate`, `sendorderUpdateStatus`, `sendorder_deliver`, `addNewOrders`, `editOrders`, `deleteOrders` |
| Excel status/price/new-order | 6 | `ExcelDataAdd`, `ExcelConfirm`, `ExcelNewOrderDataAdd`, `ExcelNewOrderConfirm`, `ExcelPriceDataAdd`, `ExcelPriceConfirm` |
| Contact | 2 | `addContact`, `addContact_th` |
| Menu plus legacy implicit handlers | 4 | explicit `addMenu`, `editMenu`; implicit `menu/deleteUser`, `menu/changePassword` |
| Rating | 1 | `addRating` |
| Background | 3 | `addBackground`, `editBackground`, `deleteBackground` |
| Upload implicit handler | 1 | `order/do_upload_multi` |
| **รวม** | **61** | **58 explicit + 3 implicit** |

### Required evidence per entry

1. Real HTTP/UI request with method, session, role and branch recorded.
2. Positive and negative validation where route accepts input.
3. CSRF, authorization, duplicate/replay and malformed-input checks where applicable.
4. DB/file before-after delta, outbound stub count and visible redirect/DOM/JSON result.
5. Cleanup or restore to the sealed synthetic fixture; source SHA and container health unchanged.

### Closure gate

- Every route entry `61/61` has evidence; no `SKIPPED`, `BLOCKED` or unclassified result.
- Relevant WP-00C cases reach `PASS`; full catalog still requires `53/53` cases and approvals.
- Run three deterministic rounds from the same sealed fixture; outcomes and cleanup match.
- Required approval roles from catalog are complete: Business, Engineering, QA, Security, DBA only where specified.
- Mutation coverage cannot close IDOR, reset-token or mailer decisions by itself; those correction criteria must pass separately.

## Current status and next order

| Gate | Status |
|---|---|
| Disposition record | `PROPOSED_PENDING_APPROVAL` |
| CI3 route/auth evidence | captured with known defects |
| CI4 parity | not started |
| Mutation execution | not started; coverage contract defined |

ลำดับถัดไป:

1. รับ approval ของ `D-SEC-001`, `D-AUTH-001`, `D-INT-001` จาก Security, Business และ QA.
2. Implement CI4 authorization, reset-token และ email boundary ตาม decisions.
3. รัน mutation slices `D-COV-001` ผ่าน loopback provider และ synthetic fixture.
4. รัน full WP-00C comparator และเปลี่ยน closure หลัง evidence/approval ครบเท่านั้น.
