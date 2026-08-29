## Task 7: สร้าง order-specific layout profile

**Files:**

- Create: `app/Views/layout_order.php`
- Create: `app/Views/partials/order_legacy_scripts.php`
- Modify: `app/Controllers/BaseController.php`
- Modify: order create/edit render callsใน `app/Controllers/Order.php`
- Add: `public/assets/css/style.css`
- Add: `public/assets/js/browse/jquery.knob.js`
- Add: `public/assets/js/browse/jquery.ui.widget.js`
- Add: `public/assets/js/browse/jquery.iframe-transport.js`
- Add: `public/assets/js/browse/jquery.fileupload.js`
- Add: `public/assets/js/browse/script.js`
- Modify: `tests/ci4/OrderHttpTest.php`

**Interfaces:**

- Consumes: presenter data และ order page content
- Produces: CI3 `header_order.php`/`footer_order.php` hierarchy และ upload preview/progress dependenciesเฉพาะ order form

- [ ] **Step 1: เขียน failing order layout assertions**

ตรวจ create/edit HTML ต้องมี:

```php
self::assertStringContainsString('assets/css/style.css', $html);
self::assertStringContainsString('assets/js/browse/jquery.knob.js', $html);
self::assertStringContainsString('assets/js/browse/jquery.fileupload.js', $html);
self::assertStringContainsString('assets/js/browse/script.js', $html);
self::assertStringContainsString('class="skin-blue sidebar-mini"', $html);
```

listing/other admin pages ต้องไม่มี browse chain

- [ ] **Step 2: รัน test ให้เห็น failure**

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/OrderHttpTest.php
```

Expected: FAIL เพราะ current order views ใช้ normal layout และไม่มี order dependency stack

- [ ] **Step 3: คัด CI3 order layout เป็นฐาน**

ใช้ `application/views/includes/header_order.php` และ `footer_order.php` เปลี่ยนเฉพาะ helper/data/content seam เช่นเดียวกับ Task 5

- [ ] **Step 4: คัด exact browse assets และ pin checksums**

คัด 6 files จาก CI3 active pinโดยไม่ rewrite JavaScript

ห้าม add `addOrder.js` จนมี browser evidence เพราะ source มี syntax error และยังไม่พิสูจน์ active caller

- [ ] **Step 5: รัน focused tests และ browser interaction**

ตรวจ file select, preview, progress, delete/cancel และ network behaviorโดยใช้ synthetic file ห้ามส่ง outbound request

- [ ] **Step 6: review และ checkpoint commit**

```text
wip(strict-template): t7 order layout passed
```

