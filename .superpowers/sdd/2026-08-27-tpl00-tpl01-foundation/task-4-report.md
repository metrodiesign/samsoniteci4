# รายงาน Task 4: Admin layout presenter

งานนี้เพิ่ม shared presenter สำหรับ normal admin HTML และ authenticated access-denied HTML โดยรักษา status, content negotiation, escaping และเส้นทาง JSON/AJAX/anonymous เดิมไว้ ไม่ได้แก้ CI3 AdminLTE DOM shell ซึ่งเป็นขอบเขต Task 5

## สถานะ

| รายการ | ผล |
|---|---|
| TDD RED | ผ่านหลักฐาน: test ใหม่เรียก class ที่ยังไม่มีและล้มตรงสาเหตุ |
| TDD GREEN | ผ่าน 46 tests, 530 assertions |
| Auth/session regression | ผ่าน 36 tests, 195 assertions |
| PHPStan | ผ่าน ไม่มี error |
| Full PHPUnit | ผ่าน 398 tests, 6452 assertions |
| Commit/push | ไม่ทำตามข้อบังคับ |

## Interface ที่ใช้งานจริง

### Presenter

```php
public function present(
    array $session,
    string $title,
    string $content,
    string $profile = 'admin',
): array
```

Input จาก login flow ใช้ key จริงเท่านั้น:

- `isLoggedIn`
- `name`
- `roleText`
- `lastLogin`
- `GroupID`
- `BranchID`

Output มีข้อมูล CI3-compatible และ compatibility key สำหรับ view ปัจจุบัน:

| Key | ที่มา |
|---|---|
| `pageTitle`, `title` | argument `$title` |
| `content` | argument `$content`; เป็น trusted rendered HTML ตาม contract เดิม |
| `isLoggedIn` | session `isLoggedIn` |
| `name` | session `name` หรือ `''` |
| `role_text` | session `roleText` หรือ `''` |
| `last_login` | session `lastLogin` หรือ `''` |
| `GroupID` | session `GroupID` หรือ `0` |
| `BranchID` | session `BranchID` หรือ `null` |
| `BranchName` | `''` เพราะ login flow ไม่มี key นี้ |
| `menuItems` | `MenuStore::visible($groupId, $branchId)` เฉพาะ authenticated session |
| `branchOptions` | `[]` เพราะยังไม่มี source จริงใน interface ที่อนุมัติ |
| `layoutProfile` | argument `$profile` |

### BaseController

```php
protected function layout(
    string $title,
    string $content,
    array $page = [],
    string $profile = 'admin',
): string
```

- ใช้ `AdminLayoutPresenter` สร้าง shared view model
- เก็บ `subtitle`, `actions` และ `accessDeniedProfile` contract เดิม
- เลือก `layout` สำหรับ profile ปกติ และ `layout_order` เมื่อ profile เป็น `order`

### AccessDeniedResponder

ลำดับ data flow:

1. ตรวจ authentication, AJAX และ `Accept` ด้วย logic เดิม
2. JSON/AJAX/anonymous ออกจาก method ก่อนสร้าง presenter หรือ `MenuStore`
3. เฉพาะ authenticated HTML จึงสร้าง access-denied content และเรียก shared presenter
4. เติม `accessDeniedProfile = true` แล้ว render `layout`
5. ตอบ status `403` และ `text/html` เหมือนเดิม

## ไฟล์ที่เปลี่ยน

| ไฟล์ | การเปลี่ยน |
|---|---|
| `app/Presentation/AdminLayoutPresenter.php` | เพิ่ม shared presenter ขั้นต่ำ |
| `app/Controllers/BaseController.php` | เปลี่ยนจากประกอบ layout data ซ้ำมาเรียก presenter และเพิ่ม profile seam |
| `app/Presentation/AccessDeniedResponder.php` | ใช้ presenter เฉพาะ authenticated HTML branch |
| `tests/ci4/MenuHttpTest.php` | เพิ่ม presenter contract test ที่ล็อก session key จริงและ menu data |
| `tests/ci4/AccessDeniedHttpTest.php` | เพิ่ม test ว่า JSON, AJAX และ anonymous denial ไม่ query menu data |

`BaseController.php` และ `MenuHttpTest.php` มี WIP เดิมก่อน Task 4 ส่วน `AccessDeniedResponder.php` กับ `AccessDeniedHttpTest.php` เป็นไฟล์ untracked เดิม งานนี้ไม่ลบ ไม่ format และไม่ refactor ส่วนข้างเคียง

## หลักฐาน TDD

### RED

คำสั่ง:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/MenuHttpTest.php tests/ci4/AccessDeniedHttpTest.php
```

ผลสำคัญ:

```text
1) Tests\Ci4\MenuHttpTest::testAdminLayoutPresenterMapsTheRealLoginSessionContract
Error: Class "App\Presentation\AdminLayoutPresenter" not found

ERRORS!
Tests: 46, Assertions: 518, Errors: 1.
```

คำอธิบาย: test ล้มเพราะ production class ที่ต้องเพิ่มยังไม่มี ไม่ได้ล้มจาก fixture, syntax หรือ behavior ข้างเคียง

### GREEN

ใช้คำสั่ง focused เดิมหลังเพิ่ม presenter และต่อ caller

```text
OK (46 tests, 530 assertions)
```

คำอธิบาย: presenter contract, normal layout, access-denied HTML, JSON negotiation, AJAX และ anonymous path ผ่านพร้อมกัน

## หลักฐาน regression

### Auth และ session

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/BusinessParityHttpTest.php \
  tests/ci4/SessionContractTest.php \
  tests/ci4/AuthorizationFilterTest.php
```

```text
OK (36 tests, 195 assertions)
```

### Static analysis

```bash
vendor/bin/phpstan analyse --no-progress \
  app/Presentation/AdminLayoutPresenter.php \
  app/Presentation/AccessDeniedResponder.php \
  app/Controllers/BaseController.php
```

```text
[OK] No errors
```

คำอธิบาย: PHPStan ไม่พบ type หรือ control-flow error ใน production files ที่เปลี่ยน

### Full PHPUnit

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist
```

```text
OK (398 tests, 6452 assertions)
```

## Security และ data-flow self-review

| ประเด็น | ผลตรวจ |
|---|---|
| Status code | HTML และ JSON denial ยังคง `403`; anonymous web-auth ยังคง `401` |
| Content negotiation | ไม่แก้ parser หรือ fail-closed comparison เดิม |
| JSON/AJAX path | return ก่อน `db_connect()`, presenter, menu query และ layout render |
| Anonymous denial | ไม่สามารถเลือก HTML shell และไม่ query menu data |
| Menu scope | ส่ง `GroupID` และ normalized nullable `BranchID` เข้า `MenuStore::visible()` เหมือน behavior เดิม |
| Escaping | presenterไม่ render user data; `layout.php` ยัง `esc()` title, name, group name และ menu name |
| Raw HTML | `content`, `subtitle` และ `actions` รักษา trusted-HTML contract เดิม ไม่รับ request data ใหม่ |
| Request reflection | presenterรับเฉพาะ session, title, content และ internal profile ไม่มี query/body/header value ใหม่ |
| View DB access | view modelเตรียม menu dataก่อน render; viewไม่ query DB |

`testJsonAjaxAndAnonymousDenialsDoNotQueryMenuData()` ฟัง `DBQuery` event และปฏิเสธ query ที่อ้าง `group_menu` หรือ `tbl_menu` ในสาม denial branches

## ข้อกังวลและสิ่งที่จงใจไม่ทำ

- Task 4 brief มี shell assertions ที่ซ้ำกับ Task 5 แต่ข้อบังคับระบุห้าม implement Task 5 ล่วงหน้า จึงเพิ่มเฉพาะ presenter/data-flow tests และไม่แตะ `layout.php`
- `BranchName` และ `branchOptions` ใช้ empty fallback เพราะ login/session writer ไม่มีข้อมูลดังกล่าว การเติมค่าจาก DB หรือสร้าง session key ใหม่จะเกิน interface ที่พิสูจน์ได้
- `layout_order.php` ยังไม่มีจนถึง Task 15 ปัจจุบันไม่มี caller ส่ง profile `order`; selector seam พร้อมแต่ order render ยังไม่ควรถูกเรียกก่อน Task 15
- ยังไม่มี DOM parity claim สำหรับ `skin-blue sidebar-mini`, `main-header`, `main-sidebar`, `main-footer` หรือ `print-logo.jpg`; หลักฐานนั้นเป็นงาน Task 5
