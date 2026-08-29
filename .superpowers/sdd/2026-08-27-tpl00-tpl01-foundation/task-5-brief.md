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

