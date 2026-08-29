# รายงาน Task 2: ล็อก Tracking no-trim contract

รายงานนี้บันทึกการเพิ่ม regression cases สำหรับ query `tracking_id` และ legacy `searchText` ที่มี leading/trailing whitespace รวมถึงหลักฐาน TDD และ mutation-check

## ขอบเขตและการเปลี่ยนแปลง

- แก้เฉพาะ `tests/ci4/PublicTrackingHttpTest.php`
- เพิ่ม query `/tracking?tracking_id=%20WP00C-TRACK-005%20`
- เพิ่ม query `/tracking?searchText=%20WP00C-TRACK-005%20`
- ใช้ assertion เดิม `assertDontSee('SYNTHETIC RETURN')` กับทุก query ใน `$queries`
- ไม่แก้ production implementation ใน `app/Controllers/Tracking.php`

## การตรวจ implementation

- `app/Controllers/Tracking.php:51` รับค่า string แบบ exact โดยไม่เรียก `trim()`
- `app/Controllers/Tracking.php:52` ใช้ allowlist `[A-Za-z0-9._-]` และจำกัดความยาวสูงสุด 100 ตัวอักษร
- `app/Controllers/Tracking.php:56` ทำ lookup เฉพาะเมื่อค่าที่ผ่าน allowlist ไม่เป็นค่าว่าง
- canonical `tracking_id` และ legacy `searchText` ถูกส่งผ่าน query flow เดียวกัน

## TDD และ mutation-check

### Baseline หลังเพิ่ม regression cases

รัน targeted test กับ working implementation ก่อนทำ mutation

```text
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/PublicTrackingHttpTest.php --filter testInvalidQueryShapesAndRouteValuesStayInPublicNoDataFlowWithoutReflection
```

ผลลัพธ์:

```text
OK (1 test, 80 assertions)
```

### Red: mutation เรียก `trim()` ชั่วคราว

เปลี่ยนชั่วคราวจาก `$value` เป็น `trim($value)` ที่ `app/Controllers/Tracking.php:51` แล้วรันคำสั่งเดิม

```text
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/PublicTrackingHttpTest.php --filter testInvalidQueryShapesAndRouteValuesStayInPublicNoDataFlowWithoutReflection
```

ผลลัพธ์:

```text
There was 1 failure:

1) Tests\Ci4\PublicTrackingHttpTest::testInvalidQueryShapesAndRouteValuesStayInPublicNoDataFlowWithoutReflection
Text 'SYNTHETIC RETURN' is unexpectedly seen in response.
Failed asserting that false is true.

Tests: 1, Assertions: 44, Failures: 1.
```

ผลนี้ยืนยันว่า regression test จับการ trim ได้จริง เพราะ whitespace รอบ known ID ถูกลบ แล้ว lookup พบ timeline ซึ่งทำให้ `SYNTHETIC RETURN` ปรากฏใน response

### Green: คืน working implementation

คืน `app/Controllers/Tracking.php:51` เป็นการรับค่า `$value` แบบ exact แล้วรัน targeted test

```text
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/PublicTrackingHttpTest.php --filter testInvalidQueryShapesAndRouteValuesStayInPublicNoDataFlowWithoutReflection
```

ผลลัพธ์:

```text
OK (1 test, 80 assertions)
```

## Focused suite

```text
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/PublicTrackingHttpTest.php
```

ผลลัพธ์:

```text
OK (28 tests, 459 assertions)
```

ทั้ง 28 tests ผ่าน รวม regression cases ใหม่ของ canonical และ legacy query

## Diff และ self-review

- `git diff --check -- tests/ci4/PublicTrackingHttpTest.php app/Controllers/Tracking.php` ผ่านโดยไม่มี output
- ตรวจซ้ำด้วย `rg` แล้วไม่พบ `trim(` ใน `app/Controllers/Tracking.php`
- mutation ถูกคืนกลับแล้ว ไม่ทิ้งการเปลี่ยนแปลงชั่วคราวไว้
- diff ที่ตั้งใจทำใน Task 2 มีเพียง query ใหม่สองรายการใน `$queries`
- ไม่มีการเพิ่ม reflection, mock หรือ production branch ใหม่
- ไม่ได้ commit ตามข้อกำหนดของ Task 2

## Concerns

ไม่มี blocker ที่ค้างอยู่

ข้อสังเกตเดียวคือ regression cases ใหม่ยิง English endpoint `/tracking` ตาม brief โดยตรง และไม่ได้เพิ่ม whitespace case สำหรับ `/tracking-th`; suite เดิมยังครอบคลุม Thai known tracking flow และ legacy lookup อยู่แล้ว หากต้องล็อก whitespace contract ฝั่ง Thai โดยเฉพาะ ควรเพิ่มเป็น requirement แยกเพื่อไม่ขยาย scope ของ Task 2
