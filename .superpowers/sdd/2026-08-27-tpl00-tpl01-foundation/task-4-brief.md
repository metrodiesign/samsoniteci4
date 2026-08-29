## Task 4: สร้าง CI3-compatible admin layout presenter

**Files:**

- Create: `app/Presentation/AdminLayoutPresenter.php`
- Modify: `app/Controllers/BaseController.php:46-70`
- Modify: `app/Presentation/AccessDeniedResponder.php:9-105`
- Test: `tests/ci4/MenuHttpTest.php`
- Test: `tests/ci4/AccessDeniedHttpTest.php`

**Interfaces:**

- Consumes: session, `MenuStore`, current page title/content และ layout profile
- Produces: `array<string,mixed>` ที่มี `pageTitle`, `name`, `role_text`, `last_login`, `GroupID`, `BranchID`, `BranchName`, `menuItems`, `branchOptions`, `content`, `layoutProfile`

- [ ] **Step 1: เขียน tests สำหรับ CI3 layout data contract**

ตรวจ authenticated admin response ต้องมี:

```php
self::assertStringContainsString('class="skin-blue sidebar-mini"', $html);
self::assertStringContainsString('class="wrapper"', $html);
self::assertStringContainsString('class="main-header"', $html);
self::assertStringContainsString('class="main-sidebar"', $html);
self::assertStringContainsString('class="sidebar-menu"', $html);
self::assertStringContainsString('class="content-wrapper"', $html);
self::assertStringContainsString('class="main-footer"', $html);
self::assertStringContainsString('assets/images/print-logo.jpg', $html);
```

Access-denied HTML ต้องใช้ shell เดียวกัน ส่วน JSON negotiation ต้องไม่ query/render shell

- [ ] **Step 2: รัน tests ให้เห็น current custom-shell failure**

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/MenuHttpTest.php tests/ci4/AccessDeniedHttpTest.php
```

Expected: FAIL เพราะ current layout ใช้ `admin`, custom `<aside class="sidebar">`, inline SVG และ `main-logo.png`

- [ ] **Step 3: สร้าง presenter ขั้นต่ำ**

```php
final class AdminLayoutPresenter
{
    public function __construct(private readonly MenuStore $menus) {}

    /** @return array<string, mixed> */
    public function present(array $session, string $title, string $content, string $profile = 'admin'): array
    {
        $branchId = $session['BranchID'] === null ? null : (int) $session['BranchID'];

        return [
            'pageTitle' => $title,
            'content' => $content,
            'name' => (string) ($session['name'] ?? ''),
            'role_text' => (string) ($session['role_text'] ?? ''),
            'last_login' => (string) ($session['last_login'] ?? ''),
            'GroupID' => (int) ($session['GroupID'] ?? 0),
            'BranchID' => $branchId,
            'menuItems' => $this->menus->visible((int) ($session['GroupID'] ?? 0), $branchId),
            'layoutProfile' => $profile,
        ];
    }
}
```

ใช้ exact session key ที่มีจริงใน Login flow หาก key ใดไม่มี ให้ส่ง empty value แบบ CI3 display fallback ห้ามสร้างข้อมูลประมาณ

- [ ] **Step 4: ให้ BaseController และ AccessDeniedResponder ใช้ presenter เดียวกัน**

`BaseController::layout()` เลือก `layout` หรือ `layout_order` จาก profile

AccessDeniedResponder เรียก presenterเฉพาะ branch HTML ที่ authenticated และ content negotiation เลือก HTML แล้ว JSON branch ต้องตอบ 403 ก่อนสร้าง presenter

- [ ] **Step 5: รัน focused tests**

ใช้ command จาก Step 2

Expected: presenter/data tests ผ่าน JSON negotiation และ status 403 ไม่เปลี่ยน

- [ ] **Step 6: review และ checkpoint commit**

```text
wip(strict-template): t4 admin presenter passed
```

