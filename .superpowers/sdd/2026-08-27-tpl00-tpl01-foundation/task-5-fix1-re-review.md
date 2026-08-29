# Re-review Task 5 fix round 1

เอกสารนี้ตรวจเฉพาะ package หลัง fix round 1 เทียบ open findings จาก review เดิม โดยถือผล skeptic gate ของ I1 และ M2 เป็นข้อยุติ และไม่ตัดสิน browser หรือ visual PASS

## Verdict

| แกน | Verdict | เหตุผล |
|---|---|---|
| Open findings | **ADDRESSED 6/6** | การแก้ production และ regression ตรง failure scenario เดิมครบ |
| New Critical/Important breakage | **NONE FOUND** | ไม่พบ boolean propagation, escaping, extraction, fixture หรือ WP00C regression ใหม่ |
| Code fix gate | **GREEN** | focused rerun ผ่าน 5 tests, 86 assertions และ WP00C ผ่าน 7 tests |
| Task 5 completion | **PENDING BROWSER SMOKE** | brief Step 5 ยังต้องยืนยัน runtime, interaction และ visual ก่อนปิด Task 5 |

## ผลตรวจ open findings

| รายการ | สถานะ | หลักฐาน |
|---|---|---|
| I2 แยก authorization ออกจาก empty data | **ADDRESSED** | `AdminLayoutPresenter` ส่ง `showBranchAutocomplete` แยกจาก `branchOptions`; view render widget จาก boolean และ render `xsource = []` ได้ |
| I3 ตรวจ rendered rating modal | **ADDRESSED** | test เรียก HTTP route จริง แล้ว extract `<dialog id="rating-modal">` พร้อม script จาก response ก่อนตรวจว่าไม่มี inline style |
| I4 exact DataTables contract | **ADDRESSED** | test ล็อก initialization block ครบทุก option และลำดับตาม brief; report มี mutation ลบ `className` แล้ว test แดง |
| M1 footer image | **ADDRESSED** | production คืน `<img class="" ...>` และ HTTP regression ตรวจ raw attribute |
| Full-CI RED จาก email fixture | **ADDRESSED** | fixture ใช้ reserved domain `test@example.invalid`; WP00C focused ผ่าน และ report บันทึก full CI ผ่านครบ |
| malicious branch name regression | **ADDRESSED** | test ส่งค่าจาก DB ผ่าน HTTP render จริง ตรวจ raw breakout/HTML ไม่หลุด แล้ว decode JSON คืน label และ URL เดิมได้ |

## รายละเอียดการตรวจ

### I2: branch autocomplete

`app/Presentation/AdminLayoutPresenter.php:23-32` คำนวณสิทธิ์แสดง widget ก่อน query และเก็บรายการข้อมูลแยกต่างหาก ส่วน `app/Views/layout.php:92-112` ใช้ boolean เป็น render gate โดยไม่ผูกกับจำนวน row

- central group ใช้เงื่อนไขเดียวกับ CI3 คือ `GroupID <= 3`
- รายการว่างยังได้ `branchOptionsJson` เป็น `[]`
- branch user ได้ boolean เป็น false และไม่ render `#autocomplete`
- regression ครอบ central group ที่มีข้อมูล, central group ที่ไม่มีข้อมูล และ branch user

I1 เรื่อง GroupID binding ถูก skeptic refute เอกฉันท์ จึงไม่เปิดกลับจากเงื่อนไขข้างต้น

### I3: rendered modal และ script

`tests/ci4/OrderHttpTest.php:1158-1174` ไม่อ่าน source file อีกต่อไป แต่ตรวจ body จาก `/TrackingcompleteListing`

- regex ต้องพบ exact modal id จึงไม่ผ่านเมื่อ modal หาย
- regex ต้องพบ script ที่ตาม modal จึงไม่ผ่านเมื่อ rendered script หายหรือถูกสลับออก
- assertion จำกัดพื้นที่ modal และ script ทำให้ shared CI3 `.error` style ใน head ไม่สร้าง false positive
- report บันทึก mutation เติม `<style>` ใน rendered modal แล้ว test แดง

รูปแบบ extraction ผูกกับ contract ที่ partial render dialog และ script ติดกัน ซึ่งตรง source ปัจจุบันที่ `app/Views/orders_rating_modal.php:20-133` และไม่กว้างจนจับ script คนละส่วนของหน้า

### I4: DataTables exact block

`tests/ci4/MenuHttpTest.php:219-237` ล็อก block ตาม brief ครบ:

1. `scrollY`
2. `scrollX`
3. `responsive`
4. `className`
5. `scrollCollapse`
6. `paging`
7. `buttons`
8. `fixedColumns` พร้อม `leftColumns` ซ้ำ 1, 2, 3

ข้อความ expected ตรงกับ rendered source ที่ `app/Views/partials/admin_legacy_scripts.php:18-34` รวมลำดับและ indentation ความเคร่งของ exact string เป็น requirement โดยตรง ไม่ใช่ brittleness ที่ขัด CI3 contract

### M1 และ WP00C

- `app/Views/layout.php:181` คืน empty `class` attribute ตรง CI3 `application/views/includes/footer.php:10`
- `tests/wp00c/test_presentation_inventory.py:63` เปลี่ยนเฉพาะ local Git fixture email เป็น reserved domain
- Git config ใช้ `git -C <temp-root> config` จึงอยู่ใน temporary repository ไม่แก้ global config
- test intent เรื่อง tracked/untracked template และ asset tree ไม่เปลี่ยน

### Security regression

`tests/ci4/MenuHttpTest.php:281-304` ใช้ payload ที่มี quote, `</script>`, `<script>` และ HTML tag จากตาราง `branch` แล้ว render `/dashboard`

- ตรวจว่า raw payload, script breakout และ raw HTML ไม่อยู่ใน response
- ตรวจว่าอักขระเปิด tag ใน closing script ถูกแปลงเป็น Unicode escape จาก JSON HEX escaping
- extract `xsource` จาก rendered script และใช้ `json_decode(..., JSON_THROW_ON_ERROR)`
- ยืนยัน decoded label และ destination URL กลับเป็นค่าที่คาดครบ

ชุดตรวจนี้ไม่ใช่ false positive จากการตรวจ source เพราะ failure path ผ่าน DB, presenter, layout และ HTTP response จริง

## New breakage review

### Boolean propagation

ไม่พบ breakage ใหม่

- normal layout กำหนด `accessDeniedProfile = false` ทุก render ที่ `app/Controllers/BaseController.php:63-67`
- HTML denial ใช้ presenter ชุดเดียวกันแล้วกำหนด `accessDeniedProfile = true` ที่ `app/Presentation/AccessDeniedResponder.php:20-34`
- JSON, AJAX และ anonymous denial return ก่อนสร้าง presenter จึงไม่ query branch/menu
- boolean autocomplete เป็นค่าต่อ render ใน array ไม่มี shared mutable state หรือค่าค้างข้าม denial/normal render

### Escaping และ extraction

ไม่พบ Critical/Important breakage ใหม่

- production encode branch options ด้วย `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS`, `JSON_HEX_QUOT` และ fallback เป็น `[]`
- malicious-value test parse JSON กลับจริง ไม่ได้สรุปว่าปลอดภัยจากการหา escaped substring เพียงอย่างเดียว
- modal extraction ระบุ exact id และต้องมี script หลัง dialog จึงตรวจ rendered component ที่ผู้ใช้ได้รับ

### Fixture isolation และ WP00C intent

ไม่พบ breakage ใหม่

- `MenuHttpTest::setUp()` drop/create ตาราง `branch` ใหม่ทุก test จึงแยกผลของ `truncate()` และ malicious row ออกจาก test อื่น
- WP00C fixture อยู่ใต้ `TemporaryDirectory` และใช้ repository-local Git config
- การเปลี่ยน domain ไม่แตะ inventory generation, pin, dirty-tree detection หรือ tracked-source assertions

M2 เรื่อง broad negative assertion ถูก skeptic refute เอกฉันท์ และไม่มี runtime evidence ใหม่ จึงไม่เปิดกลับ

## Verification

รันบน current working tree:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/MenuHttpTest.php tests/ci4/OrderHttpTest.php \
  --filter '/(testAdminLayoutPresenterMapsTheRealLoginSessionContract|testAdminShellRestoresCi3HierarchyAssetsAndScripts|testCentralGroupKeepsAutocompleteWhenBranchListIsEmpty|testBranchAutocompleteHexEscapesMaliciousDatabaseLabels|testCompleteListingExposesBulkCompleteFormAlongsideRatingButton)/'
```

```text
OK (5 tests, 86 assertions)
```

ความหมาย: regressions ของ I2, I3, I4, M1 และ malicious branch rendering ผ่านบน package ปัจจุบัน

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
```

```text
Ran 7 tests in 1.302s
OK
```

ความหมาย: reserved email ไม่ทำลาย WP00C test intent

`git diff --check` ไม่มี output จึงไม่พบ whitespace error

Full CI ไม่ได้รันซ้ำใน re-review นี้ แต่ `task-5-report.md` บันทึกผล final `scripts/ci-check.sh` ว่าผ่านทุก gate หลังเปลี่ยน reserved email แล้ว

## สิ่งที่ยังต้องทำก่อนปิด Task 5

ยังต้องรัน browser smoke ตาม brief Step 5 บน runtime ที่ยืนยันว่าใช้ current working tree:

- ตรวจ AdminLTE shell elements
- ตรวจ DataTables 1.10.16 และ FixedColumns 3.2.4 ที่ runtime
- ตรวจ `#example` initialization
- ตรวจ sidebar toggle, Back, user dropdown และ active menu
- เก็บ console, network และ screenshot desktop/mobile

ดังนั้น verdict ของ code fix คือ **GREEN** แต่ Task 5 ทั้งงานยังไม่ควรถูกปิดจน browser smoke ผ่าน
