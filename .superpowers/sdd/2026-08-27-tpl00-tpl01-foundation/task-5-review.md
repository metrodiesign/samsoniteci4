# Review Task 5: CI3 admin DOM shell และ scripts

เอกสารนี้ตรวจ resulting files ของ Task 5 เทียบ brief, CI3 authority และ strict-template spec โดยตรวจทั้ง source, tests และผลรันล่าสุด แต่ไม่อ้าง visual PASS

## Verdict

| แกน | Verdict | เหตุผลหลัก |
|---|---|---|
| Spec compliance | **FAIL** | ยังไม่มี browser smoke, exact regression coverage ยังไม่ครบ, empty-branch DOM ต่างจาก CI3 และ full CI gate ยัง RED |
| Code quality | **CHANGES REQUIRED** | มี authorization gap จาก `GroupID`, test หนึ่งตัวไม่ขับ rendered production output และ contract สำคัญยังขาด regression |

จำนวน finding:

| Severity | จำนวน |
|---|---:|
| Critical | 0 |
| Important | 4 |
| Minor | 2 |

## Spec compliance

### ส่วนที่ผ่าน

- AdminLTE hierarchy หลักกลับมาเป็น `.wrapper`, `.main-header`, `.main-sidebar`, `.content-wrapper` และ `.main-footer`
- custom inline SVG, checkbox sidebar, custom topbar และ authenticated `.site-footer` ถูกนำออก
- asset และ script order ตรง CI3 source โดย DataTables 1.10.16 และ FixedColumns 3.2.4 ใช้ local mirror เวอร์ชันเดิม
- DataTables initialization ใน production code มี option ครบและคง `leftColumns` ซ้ำสามครั้ง
- logout อยู่ขวาบนใกล้ตำแหน่ง CI3 และเปลี่ยนเป็น POST พร้อม CSRF
- menu text, icon attribute, branch label, user data และ branch JSON ใช้ escape context ที่เหมาะสม
- branch data มาจากตาราง `branch`; CI3 authority ไม่ได้กำหนด status filter หรือ explicit order เพิ่มเติม
- JSON, AJAX และ anonymous denial return ก่อนสร้าง `AdminLayoutPresenter`; authenticated HTML เท่านั้นที่ render admin chrome
- ไม่พบ dependency upgrade, dependency replacement, jQuery rewrite หรือ order browse dependency change

### Missing

| รายการ | หลักฐาน | ผลกระทบ |
|---|---|---|
| Browser smoke ตาม brief Step 5 | report ระบุว่ายังไม่ตรวจ | ยังยืนยัน runtime version, FixedColumns bundle, initialization และ interaction ไม่ได้ |
| Full CI gate ที่ผ่าน | `scripts/ci-check.sh` หยุดที่ PII guard | ยังผ่าน Gate 3 ของ strict-template spec ไม่ครบ |
| Empty-branch DOM parity | `app/Views/layout.php:91` | central group สูญเสีย branch widget เมื่อ query คืนรายการว่าง ต่างจาก CI3 |
| Exact DataTables regression | `tests/ci4/MenuHttpTest.php:217` | test ไม่ล็อก `className`, `scrollCollapse` และ `paging` |
| Stored-XSS regression ของ autocomplete | `tests/ci4/MenuHttpTest.php:226` | production code ปลอดภัย แต่ test ไม่ขับ malicious DB value ผ่าน JSON script context |
| Rendered modal assertion | `tests/ci4/OrderHttpTest.php:1169` | test อ่าน source file แทน output ที่ผู้ใช้ได้รับ |
| Empty `class` attribute ใน footer image | `app/Views/layout.php:180` | raw DOM ยังต่างจาก CI3 `footer.php:10` |

### Extra

ไม่พบ production feature, dependency หรือ presentation replacement ที่เกิน Task 5 ส่วน test JSON denial ใน `OrderHttpTest` เป็น adjacent security regression และไม่เปลี่ยน behavior

## Findings

### Important 1: `GroupID` ที่ไม่ valid เปิด branch autocomplete ได้

- **Location**: `app/Presentation/AdminLayoutPresenter.php:21-30`
- **ปัญหา**: gate ใช้เพียง `$groupId <= 3`; ค่า `0` และค่าติดลบจึงถูกมองเป็น central group
- **Failure scenario**: session ที่มี user, role, BranchID และ sessionVersion ถูกต้อง แต่ `GroupID=0` ผ่าน `AuthenticationFilter` เพราะ filter และ `ShadowUserStore::matchesActiveSession()` ไม่ตรวจ GroupID จากนั้น presenter query และเผย branch ทุกสาขา
- **Minimal fix**: validate positive GroupID ที่ authentication boundary และ bind GroupID กับ active `ci4_users` row; presenter ควรป้องกันซ้ำด้วยช่วง `1..3`
- **Class sweep**: ตรวจ `AuthenticationFilter`, `LoginService`, `ShadowUserStore`, `AdminLayoutPresenter`, `MenuStore::branches()` และ `branchName()` แล้ว จุดที่ขาด binding คือ GroupID; role, BranchID และ sessionVersion ถูก bind แล้ว

### Important 2: empty branch result เปลี่ยน CI3 header hierarchy

- **Location**: `app/Views/layout.php:91-111`
- **ปัญหา**: view ใช้ `$branchOptions !== []` เป็นทั้ง authorization gate และ data-state gate
- **Failure scenario**: central user GroupID 1-3 เข้าระบบตอนตาราง `branch` ว่างหรือ schema guard คืน `[]`; CI4 ซ่อน university dropdown และ `#autocomplete` แต่ CI3 `header.php:101-147` ยัง render widget สำหรับ GroupID 1-3
- **Minimal fix**: presenter ส่ง boolean เช่น `showBranchAutocomplete` แยกจากรายการข้อมูล และ render `xsource = []` เมื่อ authorized แต่ไม่มี row
- **Class sweep**: เทียบ history, branch, user, logout widgets และ sidebar กับ CI3 แล้ว widget อื่นคง hierarchy; mismatch แบบ data-empty พบเฉพาะ branch autocomplete

### Important 3: `OrderHttpTest` ไม่ตรวจ rendered production branch แล้ว

- **Location**: `tests/ci4/OrderHttpTest.php:1166-1172`
- **ปัญหา**: assertion เปลี่ยนจาก HTTP body ไปอ่าน `app/Views/orders_rating_modal.php` โดยตรง จึงล็อก file path และไม่พิสูจน์ว่า response ใช้ partial นี้จริง
- **Failure scenario**: controller หยุด include modal หรือ include partial อื่นที่มี inline `<style>`; source file เดิมยังสะอาด ทำให้ test ผ่านทั้งที่ rendered page ผิด
- **Minimal fix**: extract modal region จาก `$body` แล้ว assert ว่า modal และ script ที่ render จริงไม่มี inline `<style>` โดยยอมให้ shared CI3 header มี `.error` style
- **Class sweep**: ตรวจ Task 5 changes ใน `MenuHttpTest`, `AccessDeniedHttpTest` และ `OrderHttpTest`; source-inspection ที่แทน production assertion พบเฉพาะจุดนี้

### Important 4: exact DataTables contract ยังไม่มี regression ครบ

- **Location**: `tests/ci4/MenuHttpTest.php:212-219`
- **ปัญหา**: test ตรวจบาง option แต่ไม่ตรวจ `className`, `scrollCollapse`, `paging` และไม่ล็อกลำดับ option ทั้ง block
- **Failure scenario**: option ใด optionหนึ่งถูกลบหรือย้ายออกจาก initialization แต่ focused tests ยังผ่าน ทั้งที่ brief กำหนด exact initialization
- **Minimal fix**: assert initialization block ครบตาม snippet ใน brief หรือเพิ่ม missing options พร้อม order assertion ใน block เดียว
- **Class sweep**: เทียบทุก option ที่ `app/Views/partials/admin_legacy_scripts.php:18-33` กับ CI3 `footer.php:46-61`; production code ครบ แต่ regression ขาดสาม option

### Minor 1: footer image ขาด empty class attribute จาก CI3

- **Location**: `app/Views/layout.php:180`
- **ปัญหา**: CI3 `footer.php:10` มี `<img class="" ...>` แต่ CI4 ลบ `class` attribute
- **Failure scenario**: raw DOM comparator หรือ script ที่ตรวจ `hasAttribute('class')` เห็น unapproved difference แม้ visual effect ปัจจุบันเท่ากัน
- **Minimal fix**: คืน `class=""` ตาม authority หรือบันทึก approved normalization/disposition ก่อนตัดออก
- **Class sweep**: เทียบ footer tag, id, classes, text, phone และ image source ครบแล้ว พบ difference ที่ไม่มี disposition เฉพาะ attribute นี้

### Minor 2: negative shell assertions กว้างเกิน shared shell

- **Location**: `tests/ci4/MenuHttpTest.php:221-223`
- **ปัญหา**: test ห้าม `<svg`, `id="sidebar-toggle"` และ `class="topbar"` ทั้ง response แทนการ scope เฉพาะ admin shell
- **Failure scenario**: page content ที่ CI3 ใช้ SVG อย่างถูกต้องทำให้ admin-shell test ล้ม ทั้งที่ sidebar ไม่มี custom replacement DOM
- **Minimal fix**: extract `.main-header` และ `.main-sidebar` แล้วตรวจ negative fragments เฉพาะบริเวณนั้น
- **Class sweep**: ตรวจ positive hierarchy assertions และ negative replacements ทั้งชุด; positive assertions scope ตามลำดับได้ ส่วน broad false-positive risk อยู่ใน negative loop นี้

## Security และ data-flow review

| แกน | ผลตรวจ |
|---|---|
| HTML text และ attribute escaping | ผ่านใน production code |
| Branch JSON ใน script context | ผ่านด้วย `JSON_HEX_*`; ขาด malicious-value regression |
| Menu URL generation | ผ่าน เพราะ `MenuStore::visible()` จำกัด link ด้วย allow-pattern ก่อน `base_url()` |
| Logout | ผ่าน: POST, CSRF และไม่มี GET logout anchor ใน rendered shell |
| Branch source และ query shape | ใช้ `branch_id`, `branch_name` จาก DB จริง; ไม่มี N+1 และ CI3 ไม่มี status/order filter |
| Denial early return | ผ่านจาก code trace และ focused tests สำหรับ JSON, AJAX และ anonymous |
| Invalid session values | ไม่ผ่านเฉพาะ GroupID binding ตาม Important 1 |

Query shape ต่อ render:

- central group: branch collection หนึ่ง data query หลัง schema checks และ menu collection แบบ batch; ไม่มี per-branch query
- branch group: branch label หนึ่ง data queryและ menu collection แบบ batch; ไม่ query branch collection
- non-HTML denial: return ก่อน `db_connect()` ใน `AccessDeniedResponder::respond()`

## Test quality review

- `MenuHttpTest` ขับ production HTTP route จริงสำหรับ shell, DB branch label, autocomplete, logout และ menu visibility
- `AccessDeniedHttpTest` ขับ filter/responder จริงและยืนยัน representation negotiation กับ early return
- `OrderHttpTest` ยังไม่ลด business assertions อื่น แต่ inline-style assertion ลดจาก rendered output เป็น implementation inspection ตาม Important 3
- ไม่มี `.only` หรือ `.skip` ใน package ที่ตรวจ
- focused tests ไม่ได้แทน browser runtime เพราะ filename version assertion ไม่พิสูจน์ `$.fn.dataTable.version`

## Verification evidence

### PHP syntax

รัน `php -l` กับ `layout.php`, `admin_legacy_scripts.php`, `MenuStore.php` และ `AdminLayoutPresenter.php`

```text
No syntax errors detected
```

ความหมาย: ไฟล์ production ที่ Task 5 แตะ parse ผ่านทั้งหมด และ `ci-check.sh` ยืนยัน php lint ของ `app` กับ `tests/ci4` ผ่านด้วย

### Focused PHPUnit

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/MenuHttpTest.php tests/ci4/AccessDeniedHttpTest.php
```

```text
OK (48 tests, 595 assertions)
```

ความหมาย: focused suite ผ่านบน working tree ที่ review

### PHPStan

```bash
vendor/bin/phpstan analyse --configuration phpstan.neon.dist \
  --no-progress --memory-limit=512M
```

```text
[OK] No errors
```

ความหมาย: static analysis ผ่านโดยไม่มี error

### Full PHPUnit

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist
```

```text
OK (400 tests, 6517 assertions)
```

ความหมาย: full PHPUnit ผ่านหลังอ่าน resulting files ล่าสุด

### Full CI gate

รัน `scripts/ci-check.sh` นอก sandbox หลังรอบแรกถูก sandbox บล็อก `.env.example`

```text
PASS CI3 PR3 repin and obsolete-patch removal
PASS secret file policy
tests/wp00c/test_presentation_inventory.py:63: email-like value
```

ความหมาย: CI gate ยัง RED ที่ candidate-tree PII guard จาก `test@example.test` ในไฟล์ Task 1 ที่ tracked อยู่แล้ว ไม่ใช่ diff ของ Task 5 แต่ strict Gate 3 ยังถือว่าผ่านไม่ครบ

### Browser และ visual

- ไม่ได้รัน browser smoke เพราะ runtime ที่มีอยู่ยังพิสูจน์ไม่ได้ว่าใช้ working tree นี้
- ไม่อ้าง runtime, interaction หรือ visual PASS

## ข้อสรุปก่อนส่งกลับ implementer

ต้องแก้ Important 1-4 และ Minor 1 ก่อน re-review ส่วน Minor 2 แก้พร้อม test scope ได้โดยไม่แตะ production behavior จากนั้นรัน focused PHPUnit, PHPStan, full PHPUnit และ `scripts/ci-check.sh` ใหม่ พร้อม browser smoke บน runtime ที่ยืนยัน source identity ได้
