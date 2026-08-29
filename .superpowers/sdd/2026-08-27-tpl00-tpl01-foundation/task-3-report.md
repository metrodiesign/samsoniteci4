# รายงาน Task 3: RouteHttpTest fixture

รายงานนี้บันทึกการแก้ fixture ของ `RouteHttpTest` หลักฐาน red ก่อนแก้ เหตุผลของ schema และผลการตรวจสอบหลังแก้

## ขอบเขตและไฟล์ที่แก้

- แก้เฉพาะ `tests/ci4/RouteHttpTest.php`
- เพิ่มการสร้างและเติมข้อมูล `db_branch` ใน `setUp()`
- ไม่แก้ production route, `MasterDataStore` หรือ `MasterData`
- ไม่ทำ commit ตามข้อกำหนดของ Task 3

## หลักฐาน red ก่อนแก้

รัน focused test ตาม brief ก่อนแก้ fixture

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/RouteHttpTest.php --filter testKnownBrokenBookAliasIsCorrectedAndRackstatusIsRetired
```

ผลลัพธ์สำคัญ

```text
There was 1 error:

1) Tests\Ci4\RouteHttpTest::testKnownBrokenBookAliasIsCorrectedAndRackstatusIsRetired
CodeIgniter\Database\Exceptions\DatabaseException: Unable to prepare statement: no such table: db_branch

/Users/king_developer/Desktop/Project/samsoniteci4/app/Master/MasterDataStore.php:56
/Users/king_developer/Desktop/Project/samsoniteci4/app/Controllers/MasterData.php:158
/Users/king_developer/Desktop/Project/samsoniteci4/tests/ci4/RouteHttpTest.php:130

ERRORS!
Tests: 1, Assertions: 0, Errors: 1.
```

ความหมายคือ route ไปถึง `MasterDataStore::options()` แล้ว แต่ SQLite ไม่มี table `db_branch` ใน fixture จึงเป็นปัญหา test fixture ไม่ใช่ production route failure

## เหตุผลของ branch schema

อ่าน query จริงที่ `app/Master/MasterDataStore.php:53-57` และ caller ที่ `app/Controllers/MasterData.php:156-163` ก่อนกำหนด schema

- `MasterDataStore::options('branch')` อ่าน table `branch`
- query ต้องใช้ `branch_id` เป็น `value` และ `branch_name` เป็น `label`
- `MasterData::renderList()` ใช้ผลลัพธ์ดังกล่าวแปลง `book.branch_id` เป็นชื่อ branch
- fixture ใช้ชื่อตารางแบบมี prefix ผ่าน `prefixTable('branch')` จึงได้ `db_branch` ตาม testing database
- ใช้หก columns ตาม branch fixture contract ใน brief: `branch_id`, `branch_name`, `branch_short`, `branch_type`, `default_suffix`, `status`
- `branch_id` และ `branch_name` เป็น columns ที่ query path อ่านโดยตรง ส่วน columns ที่เหลือคง shape ของ branch row ตาม contract โดยไม่สร้าง production schema ที่กว้างกว่านี้
- เติม row ที่ `branch_id = 1` ให้ตรงกับ `book.branch_id = 1` และใช้ค่า `WPA` ตาม expected body ของ test

ลำดับใน `setUp()` เป็นดังนี้

1. ลบและสร้าง `book`
2. ลบและสร้าง prefixed `branch`
3. เรียก `resetDataCache()` หลัง DDL ทั้งสองชุด
4. เติม branch row แล้วเติม book row
5. สร้าง admin session user

การลบและสร้างทั้งสอง table ทุกครั้งที่ `setUp()` ทำงานทำให้ test ไม่พึ่งลำดับหรือ state จาก test อื่น

## การแก้ไขที่ทำ

```php
$branch = $this->db->escapeIdentifiers($this->db->prefixTable('branch'));
$this->db->query("DROP TABLE IF EXISTS {$branch}");
$this->db->query("CREATE TABLE {$branch} (branch_id INTEGER PRIMARY KEY, branch_name VARCHAR(250), branch_short VARCHAR(20), branch_type INTEGER, default_suffix VARCHAR(20), status INTEGER)");
$this->db->resetDataCache();
$this->db->table('branch')->insert([
    'branch_id' => 1,
    'branch_name' => 'SYNTHETIC ROUTE BRANCH',
    'branch_short' => 'WPA',
    'branch_type' => 1,
    'default_suffix' => 'WPA',
    'status' => 1,
]);
```

## หลักฐานหลังแก้

### Safe checks ที่รันได้

ตรวจ syntax ของไฟล์

```bash
php -l tests/ci4/RouteHttpTest.php
```

ผลลัพธ์

```text
No syntax errors detected in tests/ci4/RouteHttpTest.php
```

ตรวจ whitespace ของ diff

```bash
git diff --check -- tests/ci4/RouteHttpTest.php
```

ผลลัพธ์: ไม่มี output และ exit code เป็นศูนย์

### Green focused test

หลังแก้ fixture แล้ว Lead เป็นผู้รัน command นี้ใน current tree เนื่องจาก agent ถูก permission classifier บล็อกก่อนเริ่ม PHPUnit เพราะ fixture มี `DROP TABLE IF EXISTS` กับ `book` และ `branch`

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/RouteHttpTest.php --filter testKnownBrokenBookAliasIsCorrectedAndRackstatusIsRetired
```

ผลลัพธ์

```text
OK (1 test, 8 assertions)
```

### RouteHttpTest ทั้ง class

Lead เป็นผู้รัน focused class command ใน current tree

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/RouteHttpTest.php
```

ผลลัพธ์

```text
OK (5 tests, 596 assertions)
```

ผลลัพธ์ทั้งสองรายการยืนยันว่า route fixture ทำงานได้และ test ไม่พึ่ง shared state จาก test อื่น

## Self-review

- fixture สร้าง `branch` ใหม่ในทุก `setUp()` จึงไม่พึ่ง test order
- ชื่อตารางใช้ `escapeIdentifiers()` และ `prefixTable()` เหมือน fixture `book`
- `resetDataCache()` อยู่หลัง DDL ทั้งหมดก่อนเริ่ม insert
- `branch_id` ของ branch row ตรงกับ foreign-key value ใน book row
- production route และ store ไม่ถูกแก้
- ไม่มี test ถูก skip หรือเปลี่ยน assertion
- diff ในไฟล์เดียวกันที่มีอยู่ก่อนหน้าไม่ได้ถูกปรับแก้นอกส่วน fixture

## Concerns และ handoff

- ไม่มี concern ด้าน schema จาก query ที่ตรวจแล้ว
- green runtime ได้รับการยืนยันจาก Lead: focused test `OK (1 test, 8 assertions)` และทั้ง class `OK (5 tests, 596 assertions)`
- ไม่มี commit ตามข้อกำหนดของ Task 3
