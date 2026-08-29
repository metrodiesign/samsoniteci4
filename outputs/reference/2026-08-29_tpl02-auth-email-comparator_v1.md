# TPL-02 Auth และ Reset Email Comparator

เอกสารนี้บันทึก source mapping และผลรันจริงของ TPL-02 บน CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`. หลักฐาน browser, interaction, network และ visual ยังเป็น `BLOCKED` ตาม environment constraint.

## Source identity

| รายการ | ค่า |
|---|---|
| CI3 source | `/Users/king_developer/Desktop/Project/samsoniteci3` |
| CI3 commit | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` |
| CI3 worktree | clean |
| CI4 base commit | `1b3dd75615556235de7e854fb85aad861a624dcb` |
| PHP | `8.5.7` |
| CodeIgniter | `4.7.4` |
| CI4 email SHA-256 | `dda49d415d2c155b9b67168e795be0548ac8ac327b564cb82bc3ab027e57f6a0` |
| Comparator SHA-256 | `fbc7b58c01fa0ef96a7f892e8c569363d7f0d2766eb421bd489d5b7bb18f04f7` |

ตรวจ source identity ด้วยคำสั่ง:

```bash
git -C /Users/king_developer/Desktop/Project/samsoniteci3 rev-parse HEAD
git -C /Users/king_developer/Desktop/Project/samsoniteci3 status --short
```

## Source mapping

| Contract | CI3 source | CI4 target | Comparator |
|---|---|---|---|
| XHTML identity และ namespace | `application/views/email/resetPassword.php:1-4` | `app/Views/email/reset_password.php:1-4` | `ResetEmailRendererTest.php:27-34` |
| Header nested tables และ preheader | `application/views/email/resetPassword.php:9-40` | `app/Views/email/reset_password.php:9-40` | `ResetEmailRendererTest.php:35-43` |
| Content nested tables และ inline styles | `application/views/email/resetPassword.php:42-69` | `app/Views/email/reset_password.php:42-69` | `ResetEmailRendererTest.php:44-53` |
| Spacing rows และ tail div | `application/views/email/resetPassword.php:78-83` | `app/Views/email/reset_password.php:78-83` | `ResetEmailRendererTest.php:54-57` |
| Generic copy และ canonical token-only link | CI3 data seam `:58-61` | security adaptation `app/Views/email/reset_password.php:58-61` | `ResetEmailRendererTest.php:49-52,60-86` |

CI3 recipient/name/message seam ไม่ถูกคัดกลับ. CI4 ใช้ generic copy, `esc($resetUrl, 'attr')` และ canonical `/reset-password?token=` เท่านั้น.

## Comparator result

Comparator เริ่มจาก failing test ก่อน production patch:

```text
Tests: 2, Assertions: 19, Failures: 1.
Failed asserting rendered body starts with XHTML 1.0 Transitional doctype.
```

ผลหลังแก้:

| Check | Result |
|---|---|
| Focused auth PHPUnit | `PASS`, 38 tests, 576 assertions |
| Reset email comparator หลัง restore mutation | `PASS`, 2 tests, 68 assertions |
| Full PHPUnit | `PASS`, 436 tests, 9088 assertions |
| PHPStan level 5 | `PASS`, no errors |
| Repository CI gate | `PASS`, exit 0 |

คำสั่ง:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/PasswordResetPageHttpTest.php tests/ci4/PasswordResetHttpTest.php tests/ci4/ResetEmailRendererTest.php tests/ci4/ResetTokenStoreTest.php
vendor/bin/phpunit --configuration phpunit.xml.dist
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
scripts/ci-check.sh
```

## Security property และ mutation-check

| จุดตรวจ | Mutation | Expected | Result |
|---|---|---|---|
| XHTML identity | เปลี่ยน Transitional doctype เป็น HTML5 doctype | comparator แดง | `RED`, 1 failure; restore แล้ว |
| Metadata non-disclosure | ต่อ `recipient` query parameterเข้ากับ canonical reset URL | comparator แดง | `RED`, 2 failures; restore แล้ว |

Property comparator ใช้ valid `ResetDelivery` สองชุด. ทุกชุดตรวจ tokenหนึ่งครั้งใน canonical href และห้ามมี `intentId`, `idempotencyKey`, `requestId`, recipient, encoded recipient, legacy activation route หรือ query parameter ถัดจาก token.

## Browser และ inventory status

| Axis | Status | เหตุผล |
|---|---|---|
| Normalized browser DOM | `BLOCKED` | ไม่มี browser capability ใน session |
| JavaScript interaction | `BLOCKED` | ไม่มี browser capability ใน session |
| Network capture | `BLOCKED` | ไม่มี browser capability ใน session |
| Visual pair | `BLOCKED` | ไม่มี browser capability ใน session |

Inventory v2 ไม่มี diff. Record ของ `login.php`, `forgotPassword.php`, `newPassword.php` และ `email/resetPassword.php` ยังเป็น `BLOCKED`; เอกสารนี้ไม่ประกาศ browser หรือ visual PASS.
