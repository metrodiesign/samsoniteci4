## Task 3: ซ่อม RouteHttpTest fixture

**Files:**

- Modify: `tests/ci4/RouteHttpTest.php:20-34`
- Verify: `app/Master/MasterDataStore.php:53-57`
- Verify: `app/Controllers/MasterData.php:156-163`

**Interfaces:**

- Consumes: SQLite test DB และ `/bookListing/2`
- Produces: focused route correction test ที่ไม่พึ่ง table จาก test อื่น

- [ ] **Step 1: รัน focused test ให้เห็น fixture failure**

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/RouteHttpTest.php \
  --filter testKnownBrokenBookAliasIsCorrectedAndRackstatusIsRetired
```

Expected: ERROR `no such table: db_branch` ซึ่งหมายถึง fixture ขาด table ไม่ใช่ production route failure

- [ ] **Step 2: เพิ่ม branch table fixture ขั้นต่ำ**

หลังสร้าง `book` ให้สร้าง prefix table `branch` ตาม columns ที่ store อ่าน:

```php
$branch = $this->db->escapeIdentifiers($this->db->prefixTable('branch'));
$this->db->query("DROP TABLE IF EXISTS {$branch}");
$this->db->query("CREATE TABLE {$branch} (branch_id INTEGER PRIMARY KEY, branch_name VARCHAR(250), branch_short VARCHAR(20), branch_type INTEGER, default_suffix VARCHAR(20), status INTEGER)");
$this->db->table('branch')->insert([
    'branch_id' => 1,
    'branch_name' => 'SYNTHETIC ROUTE BRANCH',
    'branch_short' => 'WPA',
    'branch_type' => 1,
    'default_suffix' => 'WPA',
    'status' => 1,
]);
```

- [ ] **Step 3: รัน focused test ให้ผ่าน**

ใช้ command จาก Step 1

Expected: PASS และ body มี `WPA`

- [ ] **Step 4: รัน RouteHttpTest ทั้ง class**

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/RouteHttpTest.php
```

Expected: PASS ทุก test ไม่มี shared-state dependency

- [ ] **Step 5: review และ checkpoint commit**

```text
wip(strict-template): t3 route fixture passed
```

