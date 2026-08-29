## Task 2: ล็อก Tracking no-trim contract

**Files:**

- Modify: `tests/ci4/PublicTrackingHttpTest.php:86-115`
- Verify: `app/Controllers/Tracking.php:49-56`

**Interfaces:**

- Consumes: `tracking_id` และ legacy `searchText`
- Produces: whitespace-containing known ID ต้องเข้า no-data flowเหมือน CI3 ไม่ถูก trim แล้ว lookup

- [ ] **Step 1: เพิ่ม failing regression cases**

เพิ่มใน `$queries`:

```php
'/tracking?tracking_id=%20WP00C-TRACK-005%20',
'/tracking?searchText=%20WP00C-TRACK-005%20',
```

Assertion เดิมต้องยืนยันว่า response ไม่เห็น `SYNTHETIC RETURN`

- [ ] **Step 2: พิสูจน์ test แดงบน committed implementation**

รัน test กับ source ก่อน working-tree change หรือใช้ mutation-check ชั่วคราวที่คืน `trim()` แล้วต้องเห็น test fail ห้าม commit mutation

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/PublicTrackingHttpTest.php \
  --filter testInvalidQueryShapesAndRouteValuesStayInPublicNoDataFlowWithoutReflection
```

Expected เมื่อใช้ `trim()`: FAIL เพราะ timeline ถูกพบ

- [ ] **Step 3: คืน working implementation และรันเขียว**

`Tracking::requestedTrackingId()` ต้องไม่เรียก `trim()` และต้องใช้ exact allowlist `[A-Za-z0-9._-]` สูงสุด 100

Expected: PASS และไม่เกิด lookup/reflection สำหรับ whitespace input

- [ ] **Step 4: รัน focused suite**

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/PublicTrackingHttpTest.php
```

Expected: PASS 28 testsเดิมรวม regression ใหม่

- [ ] **Step 5: review และ checkpoint commit**

```text
wip(strict-template): t2 tracking contract passed
```

