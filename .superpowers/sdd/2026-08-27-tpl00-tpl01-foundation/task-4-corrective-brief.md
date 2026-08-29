# Task 4 corrective checkpoint

ซ่อม checkpoint `404c077` แบบ follow-up ให้ presenter/responder contract มี caller adapters และ view ครบ พร้อมเอา standalone-login assertions ของ WP03H ออกจาก foundation โดยรักษา Task 7 hunks ใน working Menu

## ฐานงาน

เริ่มหลัง Task 2 corrective checkpoint แล้วเท่านั้น ใช้ `HEAD` ตอนเริ่มเป็นฐานใหม่

ไฟล์ใน corrective commit มีเพียง:

1. `app/Filters/AuthorizationFilter.php`
2. `app/Filters/BranchlessFilter.php`
3. `app/Views/access_denied.php`
4. `tests/ci4/MenuHttpTest.php` เฉพาะ 3-line assertion correction

## Production closure

ใช้ working-tree content ปัจจุบันของ filters และ view:

- `AuthorizationFilter::before()` ต้องเรียก `(new AccessDeniedResponder())->respond($request)` เมื่อ policy ปฏิเสธ
- `BranchlessFilter::before()` ต้องเรียก responder เดียวกันเมื่อ session มี `BranchID`
- `access_denied.php` ต้องเป็น static CI3-compatible content ที่ `AccessDeniedResponder` render

ห้ามเพิ่ม auth/password-reset/contact/tracking path อื่น

## Menu contamination correction

`404c077` เปลี่ยน exactly 3 assertions ผิด package ให้คืน parent values:

```php
self::assertStringContainsString('login-banner', $body);
self::assertStringContainsString('>Tracking<', $body);
self::assertStringContainsString('Forgot Password', $body);
self::assertStringContainsString('forgot-password', $body);
```

`Forgot Password` ไม่ได้เปลี่ยนใน `404c077`; คงไว้เดิม การแก้จริงมีเพียง 3 lines:

- `class="banner-cms"` เป็น `login-banner`
- `<b>Tracking</b>` เป็น `>Tracking<`
- `forgotPassword` เป็น `forgot-password`

## Working-tree preservation

แก้ 3 lines บน working `tests/ci4/MenuHttpTest.php` จริง โดยรักษา Task 7 hunksอื่นทั้งหมด

Corrective commit ต้องใช้ temporary index จาก `HEAD` และ stage Menu blobที่มีเฉพาะ 3-line correctionเทียบฐาน ห้าม stage current whole Menu เพราะมี Task 7 edits

หลัง commit working Menuต้องมี:

- Task 7 editsเดิมทั้งหมด
- corrected parent login values 3 lines

เงื่อนไขนี้ป้องกัน Task 7 whole-file stage ไม่ให้ reintroduce WP03H assertions

## TDD gate

### RED

ประกอบ exact baseline treeจาก `HEAD` ก่อน closure แล้วรันเฉพาะ:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/AccessDeniedHttpTest.php \
  tests/ci4/MenuHttpTest.php \
  --filter 'testAuthorizationDenialRendersCi3BodyInAuthenticatedAdminChrome|testBranchlessDenialUsesTheSameHtmlRepresentationWithoutController|testAcceptNegotiationIsExplicitAndFailClosed|testAjaxAndUnauthenticatedRequestsCannotSelectHtml|testMethodDoesNotChangeDenialRepresentationOrStatus|testNormalLayoutDoesNotInheritTheAccessDeniedProfileAcrossRenders|testSharedRuntimeAssetClosureExistsAndIsGitTracked|testAnonymousLayoutRendersNoNavigationAndNeedsNoMenuTables'
```

ต้อง RED ด้วย root causes ที่คาด:

- denial pathตอบ JSONแทน shared HTML responder
- direct view closureหา `access_denied.php` ไม่พบ
- login testคาด WP03H valuesที่ base loginยังไม่มี

### GREEN

เพิ่ม filters 2, view และ Menu correctionลง temporary index แล้วรันคำสั่งเดิม ต้อง GREEN

จากนั้นรัน focused class sweep:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/AccessDeniedHttpTest.php \
  tests/ci4/AuthorizationFilterTest.php \
  tests/ci4/BranchlessFilterTest.php
```

หากไม่มี `BranchlessFilterTest.php` ให้บันทึกว่าไม่มีและใช้ test methodsใน `AccessDeniedHttpTest.php` ที่ขับ callerจริง ห้ามสร้าง suiteใหม่โดยไม่มี requirement

## Candidate และ patch artifacts

- temporary files/indexอยู่ใต้ `$TMPDIR`
- สร้าง RED และ GREEN Git tree objects
- exact archive candidateเท่านั้น ห้ามอ้าง dirty treeทั้งก้อน
- final patch: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-4-corrective.patch`
- patchต้องเปลี่ยน 4 pathsตามขอบเขตและ Menuเฉพาะ 3 lines
- ตรวจ `git apply --check`, `git diff --check`, candidate path list และ real index treeก่อน/หลัง
- ใช้ unique Docker image tagและลบเฉพาะ resourcesที่สร้างเอง

## รายงาน

เขียน `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-4-corrective-report.md` โดยมี:

- base commit/tree
- RED decisive signatures
- GREEN focused resultsและ assertion counts
- class sweep result
- final candidate treeและ patch path list
- working Menu preservation proof
- real index treeก่อน/หลัง
- exclusions
- concerns

ห้าม commit, stage real index หรือ push; controller จะส่ง review และ delegate gitops หลัง gateผ่าน
