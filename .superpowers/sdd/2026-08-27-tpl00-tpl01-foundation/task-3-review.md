# Review Task 3: RouteHttpTest fixture

เอกสารนี้ตรวจ scoped diff ของ Task 3 เทียบกับ brief และ query ที่ route `/bookListing/2` เรียกจริง โดยตรวจแบบ read-only

## Verdict

| แกนตรวจ | ผล | เหตุผลย่อ |
|---|---|---|
| Spec compliance | PASS | แก้เฉพาะ fixture, สร้าง prefixed `branch` ใหม่ทุก `setUp()` และมี row ที่เชื่อมกับ `book.branch_id = 1` |
| Code quality | APPROVED | schema, cache reset, prefix handling และลำดับ DDL/insert ถูกต้อง ไม่มี finding |

## การตรวจเทียบ contract

- **ขอบเขต**: diff แตะเฉพาะ `tests/ci4/RouteHttpTest.php:26-37` ไม่มี production code หรือ assertion เปลี่ยน
- **ต้นเหตุเดิม**: หลักฐานก่อนแก้ยืนยัน `no such table: db_branch` ที่ `MasterDataStore::options()` และ route เรียก path นี้จริง
- **schema/query**: `MasterCatalog` กำหนด `branch` ใช้ `branch_id` เป็น primary key และ `branch_name` เป็น label ที่ `app/Master/MasterCatalog.php:61-74`
- **schema/query**: `MasterDataStore::options()` เลือก `branch_id AS value, branch_name AS label` ที่ `app/Master/MasterDataStore.php:46-57` ซึ่ง fixture มีครบ
- **row/query**: row `branch_id = 1` ตรงกับ `book.branch_id = 1` ที่ `tests/ci4/RouteHttpTest.php:30-40`; controller จึงหา mapping ได้ที่ `app/Controllers/MasterData.php:156-163`
- **prefix handling**: DDL ใช้ `prefixTable('branch')` ร่วมกับ `escapeIdentifiers()` ที่ `tests/ci4/RouteHttpTest.php:26-28`; insert ผ่าน `table('branch')` จึงอยู่บนตาราง prefixed เดียวกันตาม connection contract
- **cache/reset**: `resetDataCache()` อยู่หลัง DDL ของทั้ง `book` และ `branch` ก่อน insert ที่ `tests/ci4/RouteHttpTest.php:23-30`
- **isolation**: ทุก `setUp()` ลบและสร้างทั้งสองตารางใหม่ จึงไม่พึ่ง table หรือ row ที่ test ก่อนหน้าทิ้งไว้
- **portability**: DDL ใช้ `INTEGER`, `VARCHAR` และ `PRIMARY KEY` ที่ SQLite/MySQL รองรับตามขอบเขต test contract; ไม่เพิ่ม syntax เฉพาะฐานข้อมูล
- **scope creep**: columns เพิ่มเติมเป็น six-column branch fixture contract ตาม brief ไม่ได้ขยาย production schema หรือเพิ่ม dependency

## หลักฐานการรัน

Lead รันใน current tree แล้วตามรายงาน Task 3

```text
focused: OK (1 test, 8 assertions)
class:   OK (5 tests, 596 assertions)
```

ผลทั้งสองชุดสอดคล้องกับเงื่อนไข focused failure เดิม, fixture ใหม่ และการไม่พึ่ง test order

## Findings

ไม่มี finding ระดับ blocking, major, minor หรือ nit
