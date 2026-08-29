# TPL-00 และ TPL-01 Foundation Implementation Plan

> **สำหรับ agentic workers:** ต้องใช้ `superpowers:subagent-driven-development` หรือ `superpowers:executing-plans` ทำทีละ task และผ่าน review gate ก่อน checkpoint commit

**Goal:** ทำให้ canonical inventory ครบ 108 CI3 templates ล็อก functional contracts ที่พิสูจน์แล้ว และคืน shared admin/order layout กับ runtime asset behavior ให้ตรง CI3 ก่อน migrate page groups ที่เหลือ

**Architecture:** CI4 backend คง security และ operation layer เดิม แต่สร้าง CI3-compatible view model แล้ว render DOM hierarchy จาก CI3 source โดยตรง Layout แยก admin กับ order profile และใช้ exact-version local assets เฉพาะ runtime graph ที่มี caller

**Tech Stack:** PHP 8.5.7, CodeIgniter 4.7.4, PHPUnit 11.5.56, Python 3 stdlib, Bootstrap 3.3.4, AdminLTE CI3 bundle, jQuery, DataTables 1.10.16, FixedColumns 3.2.4

**Spec:** `docs/superpowers/specs/2026-08-27-strict-ci3-template-preservation-design.md`

## Global Constraints

- CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` เป็น presentation authority
- Template denominator ต้องรวม tracked `.php` และ `.html` ใต้ `application/views/**` เท่ากับ 108
- Frontend Dependency Upgrade: `NONE`
- Frontend Dependency Replacement: `NONE`
- ใช้ compatibility adapter ก่อนแก้ CI3 template
- ห้าม redesign, modernize, rewrite jQuery หรือ refactor CSS/HTML
- ห้ามคัด asset tree ที่ไม่มี caller
- ห้ามใช้ HTTP test แทน DOM/JavaScript/visual proof
- ทุก task ต้องผ่าน focused test, full gate, audit, verify และ review ก่อน commit
- ห้าม reset synthetic DB ในแผนนี้ เพราะต้องได้รับ human confirmation

---

## File Structure

| Path | หน้าที่ |
|---|---|
| `scripts/generate-ci3-presentation-inventory.py` | สร้าง canonical 108-template inventory |
| `tests/wp00c/test_presentation_inventory.py` | ล็อก denominator, HTML records และ candidate semantics |
| `tests/ci4/PublicTrackingHttpTest.php` | ล็อก CI3 no-trim tracking contract |
| `tests/ci4/RouteHttpTest.php` | สร้าง route fixture ที่ครบสำหรับ `bookListing` |
| `app/Presentation/AdminLayoutPresenter.php` | แปลง session/menu/branch data เป็น CI3-compatible layout data |
| `app/Controllers/BaseController.php` | เรียก presenter และเลือก admin/order profile |
| `app/Views/layout.php` | CI3 admin DOM shell ที่ adapt สำหรับ CI4 |
| `app/Views/layout_order.php` | CI3 order DOM shell และ order-only dependencies |
| `app/Views/partials/admin_legacy_scripts.php` | exact CI3 script order และ DataTables initialization |
| `app/Views/partials/order_legacy_scripts.php` | exact order browse upload chain |
| `tests/ci4/MenuHttpTest.php` | admin shell/asset/DOM contract |
| `tests/ci4/OrderHttpTest.php` | order shell/upload dependency contract |
| `tests/ci4/AccessDeniedHttpTest.php` | shared presenter และ 403 negotiation regression |
| `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json` | generated canonical inventory evidence |
| `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.md` | human-readable inventory summary |

## Task 1: แก้ canonical template denominator

**Files:**

- Modify: `scripts/generate-ci3-presentation-inventory.py:34-41,107-128,147-169`
- Create: `tests/wp00c/test_presentation_inventory.py`
- Create: `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json`
- Create: `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.md`

**Interfaces:**

- Consumes: CI3 root, CI4 root และ output path จาก CLI เดิม
- Produces: JSON schema version 2 ที่ `summary.ci3_templates == 108` และ `ci3_templates` มีทั้ง PHP/HTML

- [ ] **Step 1: เขียน failing test สำหรับ 108 templates**

```python
from pathlib import Path
import json
import subprocess
import tempfile
import unittest


class PresentationInventoryTest(unittest.TestCase):
    def test_inventory_includes_php_and_html_templates(self):
        repo = Path(__file__).resolve().parents[2]
        ci3 = repo.parent / "samsoniteci3"
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / "inventory.json"
            subprocess.run([
                "python3", str(repo / "scripts/generate-ci3-presentation-inventory.py"),
                "--ci3-root", str(ci3),
                "--ci4-root", str(repo),
                "--output", str(output),
            ], check=True)
            payload = json.loads(output.read_text())

        self.assertEqual(108, payload["summary"]["ci3_templates"])
        sources = {row["source"] for row in payload["ci3_templates"]}
        self.assertIn("application/views/index.html", sources)
        self.assertIn("application/views/pdf-form.html", sources)
```

- [ ] **Step 2: รัน test ให้เห็น failure**

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
```

Expected: FAIL เพราะ payload ปัจจุบันมี `ci3_views == 103` และไม่มี `ci3_templates`

- [ ] **Step 3: เปลี่ยน generator ให้ inventory tracked template suffix ครบ**

ใช้ suffix allowlist:

```python
TEMPLATE_SUFFIXES = {".php", ".html"}

template_paths = [
    path for path in ci3_views.rglob("*")
    if path.is_file() and path.suffix.lower() in TEMPLATE_SUFFIXES
]
```

เปลี่ยน payload keys เป็น `ci3_templates`, `ci4_templates`, `summary.ci3_templates`, `summary.ci4_templates` และเพิ่ม `template_type` เป็น `php` หรือ `html`

- [ ] **Step 4: รัน test ให้ผ่าน**

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
```

Expected: `OK`

- [ ] **Step 5: regenerate evidence v2**

```bash
python3 scripts/generate-ci3-presentation-inventory.py \
  --ci3-root /Users/king_developer/Desktop/Project/samsoniteci3 \
  --ci4-root /Users/king_developer/Desktop/Project/samsoniteci4 \
  --output outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json
```

สร้าง summary Markdown จาก JSON โดยรายงาน denominator, disposition counts, HTML 5 records และห้ามเปลี่ยน target candidate เป็น PASS

- [ ] **Step 6: รัน WP00C tests**

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py tests/wp00c/test_closure.py tests/wp00c/test_junit_evidence.py tests/wp00c/test_route_disposition.py
```

Expected: PASS ทั้งหมด

- [ ] **Step 7: review และ checkpoint commit**

หลัง auditor/verifier/reviewer ผ่าน ให้ gitops commit:

```text
wip(strict-template): t1 inventory denominator passed
```

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

## Task 5: คืน CI3 admin DOM shell และ scripts

**Files:**

- Modify: `app/Views/layout.php:1-118`
- Modify: `app/Views/partials/admin_legacy_scripts.php:1-16`
- Modify: `tests/ci4/MenuHttpTest.php`
- Modify: `tests/ci4/AccessDeniedHttpTest.php`

**Interfaces:**

- Consumes: output จาก `AdminLayoutPresenter::present()`
- Produces: CI3 AdminLTE hierarchy และ script initialization order

- [ ] **Step 1: เพิ่ม exact shell assertions**

ตรวจ tag/class/link/script order สำคัญจาก:

- `application/views/includes/header.php:9-270`
- `application/views/includes/footer.php:3-64`

ห้าม assert เพียงข้อความ heading

- [ ] **Step 2: แทน layout ด้วย CI3 source เป็นฐาน**

คงโครง:

```html
<body class="skin-blue sidebar-mini">
  <div class="wrapper">
    <header class="main-header">...</header>
    <aside class="main-sidebar">...</aside>
    <div class="content-wrapper">
      ...CI4 rendered content...
    </div>
    <footer class="main-footer">...</footer>
  </div>
</body>
```

เปลี่ยนเฉพาะ:

- `base_url()` และ `site_url()` แบบ CI4
- session/model reads เป็น presenter data
- logout ใช้ POST + CSRF โดยคงตำแหน่งและ visual structure
- DB menu reads เป็น `menuItems`
- branch autocomplete เป็น JSON จาก presenter
- content seam สำหรับ page view

ลบ custom inline SVG, checkbox sidebar และ custom topbar เพราะไม่ใช่ CI3 template

- [ ] **Step 3: คืน exact DataTables initialization**

หลังโหลด DataTables 1.10.16 และ FixedColumns 3.2.4 เพิ่ม:

```javascript
$(document).ready(function() {
    var table = $('#example').DataTable({
        scrollY: "300px",
        scrollX: true,
        responsive: true,
        className: 'mdl-data-table__cell--non-numeric',
        scrollCollapse: true,
        paging: true,
        buttons: ['colvis'],
        fixedColumns: {
            leftColumns: 1,
            leftColumns: 2,
            leftColumns: 3
        }
    });
});
```

คง duplicate `leftColumns` ตาม CI3 source เพราะเป็น JavaScript contract แม้ runtime ใช้ค่าท้ายสุด

- [ ] **Step 4: รัน focused tests**

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/MenuHttpTest.php tests/ci4/AccessDeniedHttpTest.php
```

Expected: PASS shell, asset order, 403 negotiation และไม่มี custom replacement DOM

- [ ] **Step 5: browser smoke admin shell**

ใช้ browser MCP ตรวจ:

- `.wrapper`, `.main-header`, `.main-sidebar`, `.content-wrapper`, `.main-footer`
- `$.fn.dataTable.version == '1.10.16'`
- `$.fn.dataTable.FixedColumns.version` ตรง 3.2.4 bundle
- `#example` ที่มีอยู่ถูก initialize
- sidebar toggle, Back, user dropdown และ active menu ทำงาน

เก็บ console/network และ screenshot desktop/mobile ห้ามออก PASS หาก runtime ยัง stale

- [ ] **Step 6: review และ checkpoint commit**

```text
wip(strict-template): t5 admin shell passed
```

## Task 6: ปิด runtime asset closure ของ shared admin shell

**Files:**

- Add: runtime filesที่ current views/CSS อ้างใต้ `public/assets/**`
- Add: `public/uploads/web/contact_laptop.png`
- Add: `public/uploads/web/contact_mobile.png`
- Add: `public/uploads/web/track_laptop.png`
- Add: `public/uploads/web/track_mobile.png`
- Modify: asset checksum testsใน `tests/ci4/MenuHttpTest.php`
- Create: `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md`

**Interfaces:**

- Consumes: recursive view/CSS dependency graph
- Produces: tracked runtime closure ที่ทุก local URL resolve และ version/checksum ตรง CI3

- [ ] **Step 1: เขียน failing tracked-asset test**

Test ต้องล้มเมื่อ asset resolve บน disk แต่ `git ls-files --error-unmatch` ไม่พบ โดยตรวจเฉพาะ runtime graph ไม่ใช่ทุก vendor file

- [ ] **Step 2: สร้าง graph จาก view/CSS references**

รวม direct references และ recursive `url()`/`@import` จาก shared admin/public/auth layouts

Expected closure ปัจจุบัน: 106 runtime files โดย 91 files ยัง untracked ต้องตรวจจำนวนใหม่จาก source ก่อน add

- [ ] **Step 3: add เฉพาะ closure ที่มี caller**

ห้าม add:

- `assets/plugins/` ทั้ง 693 files
- source SCSS/LESS
- examples/docs/tests/specimen
- `multifreezer.js` ที่ CI3 comment ไว้
- `cms-logo.png` ที่อยู่ใน HTML comment

- [ ] **Step 4: บันทึก exact versions/checksums/provenance**

Evidence ต้องครอบ:

- Bootstrap 3.3.4
- DataTables 1.10.16
- FixedColumns 3.2.4
- Font Awesome 4.2/4.3/4.7 ตาม caller
- html5shiv 3.7.2
- Respond.js 1.4.2
- Source Sans Pro license

หาก bundle ไม่มี license text ให้สถานะ provenance/license เป็น `BLOCKED` ห้ามสร้าง license ขึ้นเอง

- [ ] **Step 5: รัน asset graph tests และ clean-check simulation**

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/MenuHttpTest.php tests/ci4/PasswordResetPageHttpTest.php \
  tests/ci4/ContactHttpTest.php tests/ci4/PublicTrackingHttpTest.php \
  tests/ci4/AccessDeniedHttpTest.php
```

Expected: ทุก referenced local asset มีจริง, tracked และ checksum/version ผ่าน

- [ ] **Step 6: review และ checkpoint commit**

```text
wip(strict-template): t6 shared assets passed
```

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

## Task 8: Foundation full verification

**Files:**

- Modify: `outputs/reference/2026-08-27_tpl00-tpl01-foundation-evidence_v1.md`

**Interfaces:**

- Consumes: Task 1-7 checkpoint results
- Produces: foundation verdict ที่แยก functional, template, DOM, CSS, JavaScript และ visual axes

- [ ] **Step 1: รัน static/full automated gates**

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
composer test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
bash scripts/ci-check.sh
git diff --check
```

Expected: exit 0 ทุก command; MariaDB transient failure ต้อง rerun isolated concurrency gateเพื่อเก็บหลักฐานก่อนตัดสิน

- [ ] **Step 2: รัน browser pair สำหรับ shared shells**

ใช้ CI3 `18404` และ rebuilt CI4 `18405` ที่ source identity เดียวกับ checkpoint

Capture admin shell, order add/edit, public shell และ standalone auth ที่ `1440x900` กับ `390x844`, DPR 1

- [ ] **Step 3: เก็บ DOM/network/interaction evidence**

ห้าม normalize class, hierarchy, text, field, menu หรือ visible data

ถ้า fixture state ยัง drift ให้ verdict browser เป็น `BLOCKED` และห้ามใช้ stale screenshots

- [ ] **Step 4: สรุป Output Contract**

เอกสาร evidence ต้องลงท้าย:

```text
STATUS: DONE | BLOCKED | NEEDS-INPUT
FUNCTIONAL_PARITY: PASS | FAIL | NOT-VERIFIED
TEMPLATE_PARITY: PASS | FAIL | NOT-VERIFIED
VISUAL_PARITY: PASS | FAIL | NOT-VERIFIED
UNAPPROVED_TEMPLATE_CHANGES: <number>
UNAPPROVED_DEPENDENCY_UPGRADES: <number>
```

- [ ] **Step 5: audit, verify และ review**

BLOCKING finding เชิงวิเคราะห์ต้องผ่าน skeptic ตาม pipeline rules ผลรันจริงที่ RED เข้า rework โดยตรง

- [ ] **Step 6: checkpoint commit**

เมื่อทุก automated gate ผ่านและ browser blockerถูกบันทึกตามจริง:

```text
wip(strict-template): tpl00-tpl01 foundation passed
```

## หลัง Foundation

สร้าง implementation plan แยกสำหรับ TPL-02 ถึง TPL-09 ตาม denominator และ file mapping ใน design spec แต่ละแผนต้องอ่าน CI3 source ทั้งไฟล์ก่อนแก้ CI4 target และใช้ per-page failing comparator ก่อน implementation
