# รายงาน Task 5: คืน CI3 admin DOM shell และ scripts

งานนี้รับช่วง partial จาก implementer เดิม แล้วปิด CI3 AdminLTE hierarchy, branch data, logout security, denial early return และ DataTables contract โดยไม่ commit หรือ push

## สถานะ

| แกน | สถานะ | หลักฐาน |
|---|---|---|
| CI3 admin DOM shell | ผ่าน | focused tests 48 tests, 595 assertions |
| Logout POST และ CSRF | ผ่าน | DOM assertion, route regression และ full PHPUnit |
| Branch name และ autocomplete | ผ่าน | fixture จากตาราง `branch` และ rendered HTML assertions |
| JSON, AJAX และ anonymous denial | ผ่าน | DB query listener ยืนยันว่าไม่แตะ presenter tables |
| PHPStan | ผ่าน | ไม่มี error ด้วย memory limit 512M |
| Full PHPUnit | ผ่าน | 400 tests, 6517 assertions |
| Browser visual smoke | ยังไม่ตรวจ | runtime ที่เปิดอยู่พิสูจน์ไม่ได้ว่าเป็น working tree ปัจจุบัน จึงไม่ใช้เป็นหลักฐาน |

## การประเมิน partial ก่อนรับช่วง

| ส่วน | สภาพที่พบ | การตัดสินใจ |
|---|---|---|
| `app/Views/layout.php` | โหลด asset CI3 บางส่วนแล้ว แต่ยังเป็น custom shell: `<body class="admin">`, checkbox sidebar, inline SVG, custom topbar และ custom footer | แทน shell ทั้งส่วนด้วย CI3 source เป็นฐาน |
| `app/Views/partials/admin_legacy_scripts.php` | มี Bootstrap, AdminLTE, validation, DataTables และ active-menu script แต่ลำดับยังไม่ตรง CI3 และไม่มี DataTables initialization | คืนลำดับและ initialization จาก CI3 |
| `app/Master/MenuStore.php` | มี `branchName()` และ `branches()` จาก partial แล้ว | เก็บไว้และเพิ่ม guard สำหรับ fixture/schema ที่ไม่มี `branch_name` |
| `app/Presentation/AdminLayoutPresenter.php` | map session, menu, branch name และ autocomplete จาก store แล้ว | ตรวจ contract และใช้ต่อโดยไม่สร้าง source ปลอม |
| `tests/ci4/MenuHttpTest.php` | มี shell assertions บางส่วนแล้ว แต่ production shell ยังไม่ผ่าน | ใช้เป็น RED จริงและขยาย asset, branch, logout assertions |
| `tests/ci4/AccessDeniedHttpTest.php` | มี HTML negotiation และ early-return tests แล้ว | ขยาย DB listener ให้ตรวจตาราง `branch` ด้วย |
| `app/Presentation/AccessDeniedResponder.php` | JSON/AJAX/anonymous return ก่อนสร้าง presenter อยู่แล้ว | ตรวจ flow และไม่แก้เกินจำเป็น |
| `app/Controllers/BaseController.php` | ใช้ `AdminLayoutPresenter` จาก Task 4 อยู่แล้ว | ตรวจ seam และไม่แก้ |

## หลักฐาน RED

### RED แรกจาก partial เดิม

คำสั่ง:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/MenuHttpTest.php tests/ci4/AccessDeniedHttpTest.php
```

ผลลัพธ์:

```text
FAILURES!
Tests: 47, Assertions: 512, Failures: 5.
```

ความหมาย: test จับ custom shell เดิมได้จริง โดยล้มที่ body class, sidebar hierarchy, content header และ access-denied shell

### RED ของ branch rendering

เพิ่ม test ที่ต้องเห็น autocomplete ของ admin และ branch label ของ branch user จาก fixture จริง

```text
Tests: 1, Assertions: 1, Failures: 1.
Failed asserting that response contains id="autocomplete".
```

ความหมาย: presenter มีข้อมูลแล้ว แต่ layout เดิมยังไม่ render ข้อมูล branch

### RED ของ IE compatibility assets

เพิ่ม order assertion สำหรับ local mirror ของ html5shiv 3.7.2 และ Respond.js 1.4.2

```text
Tests: 1, Assertions: 18, Failures: 1.
assets/html5shiv/3.7.2/html5shiv.min.js
```

ความหมาย: shell ยังขาด caller ของ asset ที่ CI3 header อ้าง

## การเปลี่ยนแปลง GREEN

### CI3 header และ shell

- ใช้ `<body class="skin-blue sidebar-mini">`, `.wrapper`, `.main-header`, `.main-sidebar` และ `.content-wrapper`
- ใช้ CI3 logo, Back button, last-login dropdown, branch dropdown, user dropdown และ Font Awesome classes
- ลบ custom inline SVG, checkbox sidebar, `.topbar`, `.site-footer` และ `<main class="content">` จาก authenticated shell
- คง content seam ของ CI4 โดยวาง title, subtitle, actions และ rendered page content ใน CI3 content hierarchy

### Asset order

ลำดับ head ใช้ CI3 เป็นฐาน:

1. Bootstrap 3.3.4
2. DataTables 1.10.16
3. FixedColumns 3.2.4
4. Font Awesome
5. AdminLTE, CustomAdmin, main, multifreezer และ skins
6. jQuery datepicker, jQuery UI และ timepicker files
7. inline `.error` style และ `baseURL`
8. local html5shiv 3.7.2 และ Respond.js 1.4.2 ภายใต้ IE conditional comment

CDN ของ DataTables และ IE shims เปลี่ยนเป็น local mirror เวอร์ชันเดิมตาม policy ใน spec ไม่มี frontend upgrade หรือ replacement

### Footer และ scripts

- คืน `.main-footer`, `#footer`, `.bg-footer`, help text และหมายเลขโทรศัพท์ตาม CI3
- รักษาลำดับ Bootstrap, AdminLTE app, validation, active-menu script, DataTables และ FixedColumns
- คืน DataTables initialization ครบทุก option
- คง `leftColumns` ซ้ำสามครั้งตาม source แม้ JavaScript runtime ใช้ค่าท้ายสุด

### Branch data

- `BranchName` อ่านจาก `branch.branch_name` ด้วย `BranchID` จริง
- autocomplete อ่านรายการ `branch_id` และ `branch_name` จริงจาก store
- central group เท่านั้นที่ได้ autocomplete ตาม CI3 condition
- branch user แสดง branch label และไม่ render autocomplete
- JSON ใช้ HEX escaping เพื่อไม่ให้ข้อมูล DB ปิด `<script>` หรือแทรก markup
- schema guard คืนค่าว่างเมื่อ fixture มีตาราง `branch` แบบย่อแต่ไม่มี `branch_name` แทนการ fabricate ค่า

### Access denied early return

- JSON, AJAX และ anonymous paths return ก่อนสร้าง `AdminLayoutPresenter`
- DB listener ตรวจ `group_menu`, `tbl_menu` และตาราง `branch`
- authenticated HTML denial เท่านั้นที่ render CI3 admin chrome
- request-controlled query, body และ header values ไม่ถูกสะท้อนใน denial page

## ความต่างด้าน security ที่ตั้งใจ

| CI3 behavior | CI4 behavior | เหตุผล |
|---|---|---|
| Logout เป็น GET anchor | Logout เป็น POST form พร้อม CSRF hidden field | ป้องกัน cross-site logout และคงตำแหน่งใกล้ CI3 |
| Branch JSON ต่อ string โดยตรง | ใช้ `json_encode()` พร้อม HEX flags | ป้องกัน script-context injection |
| View อ่าน session และ DB โดยตรง | Presenter และ store เตรียมข้อมูลก่อน render | รักษา output โดยไม่ให้ view query DB |
| Denial เดิมไม่แยก representation ชัด | anonymous, AJAX และ non-HTML return JSON ก่อน DB | fail closed และไม่สร้าง admin shell โดยไม่จำเป็น |
| DataTables และ IE assets อ้าง CDN | ใช้ local mirror exact version | deterministic runtime โดยไม่ upgrade dependency |

## ไฟล์งาน

| ไฟล์ | ผลงาน |
|---|---|
| `app/Views/layout.php` | แทน custom shell ด้วย CI3 AdminLTE hierarchy และ security adaptations |
| `app/Views/partials/admin_legacy_scripts.php` | คืน script order และ DataTables initialization |
| `app/Master/MenuStore.php` | ใช้ branch source จริงและรองรับ schema fixture แบบย่อ |
| `app/Presentation/AdminLayoutPresenter.php` | partial เดิมที่ตรวจแล้วว่า map session, menu และ branch data ถูกต้อง |
| `tests/ci4/MenuHttpTest.php` | เพิ่ม shell, asset order, logout, branch และ DataTables assertions |
| `tests/ci4/AccessDeniedHttpTest.php` | ล็อก CI3 denial shell และ early return ก่อน branch/menu query |
| `tests/ci4/OrderHttpTest.php` | จำกัด assertion เรื่อง inline style ให้ตรวจ modal source ไม่ชน CI3 header style |

ไฟล์ต่อไปนี้ถูกอ่านเพื่อตรวจ flow แต่ไม่มีการแก้ในช่วงรับต่อนี้: `app/Controllers/BaseController.php`, `app/Presentation/AccessDeniedResponder.php`, `app/Filters/AuthorizationFilter.php`, `app/Filters/BranchlessFilter.php`, `app/Controllers/Login.php` และ session writer ใน `app/Authentication/LoginService.php`

## ผลทดสอบ

### Focused Task 5

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/MenuHttpTest.php tests/ci4/AccessDeniedHttpTest.php
```

```text
OK (48 tests, 595 assertions)
```

ผลนี้ยืนยัน shell, asset graph, branch data, logout markup, denial negotiation และ early return

### Auth และ session regression

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/BusinessParityHttpTest.php \
  tests/ci4/SessionContractTest.php \
  tests/ci4/AuthorizationFilterTest.php \
  tests/ci4/RouteHttpTest.php \
  tests/ci4/PasswordResetHttpTest.php \
  tests/ci4/PasswordResetPageHttpTest.php
```

```text
OK (70 tests, 1273 assertions)
```

ผลนี้ยืนยัน session contract, authorization, POST-only logout และ auth/reset regressions

### PHPStan

คำสั่งรอบ parallel แรกชน configured memory limit 128M จึงรันใหม่แบบ standalone ด้วย limit ที่ชัดเจน

```bash
vendor/bin/phpstan analyse --configuration phpstan.neon.dist \
  --no-progress --memory-limit=512M
```

```text
[OK] No errors
```

ผลนี้ยืนยัน static analysis ผ่านหลังแก้ resource limit ของเครื่อง ไม่ใช่แก้ code เพื่อหลบ error

### Full PHPUnit

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist
```

```text
OK (400 tests, 6517 assertions)
```

ผลนี้เป็นการรันหลัง edit สุดท้ายทั้งหมด

### Diff hygiene

```bash
git diff --check
```

ไม่มี output หมายถึงไม่พบ whitespace error

## Self-review

- ไม่มี custom shell DOM เดิมเหลือใน authenticated layout
- ไม่มี dependency ใหม่ ไม่มี version upgrade และไม่แก้ order browse dependency
- Order test เปลี่ยนเฉพาะ scope ของ assertion เพราะ CI3 shared header ตั้งใจมี inline `.error` style
- branch autocomplete และ label มาจาก DB fixture จริง ไม่มี fallback text ที่สร้างขึ้นเอง
- central admin path query รายการ branch หนึ่งครั้ง ส่วน branch-user path queryชื่อ branch หนึ่งครั้งตาม control flow
- denial non-HTML paths ไม่แตะ presenter tables ตาม runtime DB listener
- logout ยังคงอยู่ตำแหน่งขวาบนและใช้ button styling ใกล้ CI3 anchor
- ไม่ commit, push, reset, clean หรือแตะ Docker project อื่น

## ข้อกังวลและสิ่งที่ยังไม่อ้างว่า PASS

- ไม่อ้าง visual PASS เพราะไม่ได้ capture screenshot คู่ CI3/CI4
- browser ที่เปิดอยู่ชี้ไป runtime บน port `18405` แต่ listener อยู่ใต้ Docker data และพิสูจน์ไม่ได้ว่าใช้ working tree ปัจจุบัน จึงไม่ใช้เป็น smoke evidence
- runtime asset tracked closure และ provenance เป็นขอบเขต Task 6 แม้ local files ที่ Task 5 อ้างมีอยู่และ focused asset graph ผ่าน
- order-specific layout profile เป็นงานถัดไป จึงไม่มีการแก้ order frontend dependency ใน Task 5

## Fix round 1/5: ปิด findings หลัง skeptic gate

รอบนี้แก้เฉพาะ I2, I3, I4, M1, WP00C email และ security regression ตามคำสั่ง โดยไม่แตะ I1 หรือ M2 และไม่ commit/push

### การแก้ไข

| Finding | การแก้ | Regression ที่ครอบ |
|---|---|---|
| I2 | เพิ่ม `showBranchAutocomplete` แยกจาก `branchOptions`; central group ยัง render widget เมื่อรายการว่าง | `testAdminLayoutPresenterMapsTheRealLoginSessionContract`, `testCentralGroupKeepsAutocompleteWhenBranchListIsEmpty` |
| I3 | ตรวจ modal region และ script จาก HTTP response จริง แทนการอ่าน source file | `testCompleteListingExposesBulkCompleteFormAlongsideRatingButton` |
| I4 | ล็อก DataTables initialization ทั้ง block รวม option และลำดับครบ | `testAdminShellRestoresCi3HierarchyAssetsAndScripts` |
| M1 | คืน `<img class="" ...>` ใน footer | `testAdminShellRestoresCi3HierarchyAssetsAndScripts` |
| Full-CI RED | เปลี่ยน Git fixture email เป็น `test@example.invalid` | `tests/wp00c/test_presentation_inventory.py` ทั้งไฟล์ |
| Security regression | ส่ง malicious `branch_name` จาก DB ผ่าน rendered autocomplete แล้วตรวจ script breakout, raw HTML, JSON HEX และ decoded behavior | `testBranchAutocompleteHexEscapesMaliciousDatabaseLabels` |

### หลักฐาน RED และ mutation

คำสั่ง focused ก่อนแก้ production:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/MenuHttpTest.php \
  --filter '/(testAdminLayoutPresenterMapsTheRealLoginSessionContract|testAdminShellRestoresCi3HierarchyAssetsAndScripts|testCentralGroupKeepsAutocompleteWhenBranchListIsEmpty)/'
```

```text
ERROR: Undefined array key "showBranchAutocomplete"
FAIL: footer image ไม่มี class=""
FAIL: empty branch list ไม่ render university widget
Tests: 3, Assertions: 45, Errors: 1, Failures: 2
```

ความหมาย: test จับ I2 และ M1 จาก production เดิมได้ตรง claim

Mutation ลบ `className` ชั่วคราวจาก DataTables block:

```text
FAIL: expected exact DataTables initialization block was absent
Tests: 1, Assertions: 49, Failures: 1
```

Mutation เติม inline `<style>` ชั่วคราวใน rendered rating modal:

```text
FAIL: modal region contains "<style"
Tests: 1, Assertions: 6, Failures: 1
```

ทั้งสอง mutation ถูกคืนค่าทันที และ focused GREEN ยืนยัน source สุดท้ายแล้ว ส่วน mutation เอา `JSON_HEX_TAG` ออกถูก permission gate ปฏิเสธเพราะเป็นการลด security; regression จริงยังขับ malicious DB value ผ่าน HTTP render และผ่านบน production flags เดิม

### ผลทดสอบหลังแก้

| Gate | คำสั่ง | ผล |
|---|---|---|
| Regression ใหม่ | PHPUnit filter 3 tests | `OK (3 tests, 66 assertions)` |
| WP00C focused | `python3 -m unittest tests/wp00c/test_presentation_inventory.py` | `Ran 7 tests`, `OK` |
| Task 5 relevant | PHPUnit Menu, AccessDenied, Order | `OK (114 tests, 1649 assertions)` |
| PHPStan | `vendor/bin/phpstan analyse --configuration phpstan.neon.dist --no-progress --memory-limit=512M` | `[OK] No errors` |
| Full PHPUnit final | `vendor/bin/phpunit --configuration phpunit.xml.dist` | `OK (402 tests, 6528 assertions)` |
| Full CI final | `scripts/ci-check.sh` นอก sandbox | ทุก gate PASS รวม candidate-tree PII guard และ repository safety gate |
| Diff hygiene | `git diff --check` | ไม่มี output |

`scripts/ci-check.sh` รอบ sandbox แรกหยุดที่ `grep: .env.example: Operation not permitted`; รันใหม่แบบ unsandboxed ตาม environment constraint แล้วผ่านครบ

### ไฟล์ที่แก้ในรอบนี้

- `app/Presentation/AdminLayoutPresenter.php`
- `app/Views/layout.php`
- `tests/ci4/MenuHttpTest.php`
- `tests/ci4/OrderHttpTest.php`
- `tests/wp00c/test_presentation_inventory.py`

`app/Views/partials/admin_legacy_scripts.php` และ `app/Views/orders_rating_modal.php` ถูกใช้ทำ mutation-check แล้วคืนค่าเดิม ไม่มี diff เพิ่มจากรอบนี้

### Coverage self-check

- I2 ขับทั้ง central group ที่ list มีข้อมูล, central group ที่ list ว่าง และ branch user ที่ต้องไม่ render widget
- I3 mutation เติม inline style ใน modal ที่ render จริงทำให้ test แดง ขณะที่ shared header `.error` style ยังได้รับอนุญาต
- I4 mutation ลบ option กลาง block ทำให้ exact block assertion แดง และ production มี option ครบตาม brief
- Security regression ใช้ `</script>`, quote และ HTML จาก DB จริง; decoded JSON คืน label/value เดิมโดยไม่มี raw injection
- WP00C focused และ full CI ยืนยัน reserved email ปิด PII guard RED เดิม

### Self-review

- ไม่แก้ GroupID authentication binding ตาม I1 ที่ skeptic refute
- ไม่แก้ broad negative assertions ตาม M2 ที่ skeptic refute
- ไม่มี dependency ใหม่ ไม่มี refactor ข้างเคียง และไม่มี production change สำหรับ escaping เพราะ implementation เดิมปลอดภัย
- ไม่อ้าง browser, visual หรือ runtime interaction PASS; main session ยังต้องทำ browser smoke หลัง code review GREEN
- ไม่ commit, push, reset, clean หรือแตะ Docker project อื่น
