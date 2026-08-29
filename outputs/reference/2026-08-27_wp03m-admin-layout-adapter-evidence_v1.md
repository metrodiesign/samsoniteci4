# WP-03M Admin layout compatibility adapter evidence v1

วันที่: 2026-08-27

## Scope

CI3 admin layout source คือ `application/views/includes/header.php` และ `application/views/includes/footer.php` ที่ถูกประกอบโดย `application/libraries/BaseController.php::loadViews()`. CI4 target คือ `app/Views/layout.php` และ `app/Views/partials/admin_legacy_scripts.php`.

## Before evidence และ root cause

`app/Views/layout.php` เดิมโหลดเพียง `assets/fonts/stylesheet.css` และ `assets/css/admin.css`. จึงไม่มี CI3 runtime dependency ต่อไปนี้: Bootstrap 3.3.4, AdminLTE 2.1.0, CustomAdmin, skin-blue, jQuery 1.10.2, jQuery UI/timepicker, jQuery Validate, DataTables 1.10.16 และ FixedColumns 3.2.4. CI3 header/footer อ้าง dependency เหล่านี้โดยตรง.

## Change

- `layout.php` โหลด CSS/JS ตามลำดับ CI3 และสร้าง `baseURL` global
- เพิ่ม `admin_legacy_scripts.php` เพื่อโหลด footer script และ active-link behavior แบบ CI3 เฉพาะ authenticated admin layout
- คัดลอก CI3 asset ที่อ้างตรงเป็น local artifact: jQuery UI/timepicker, `multifreezer.css`, skin-blue, avatar และ print logo
- DataTables 1.10.16 และ FixedColumns 3.2.4 ซึ่ง CI3 อ้างผ่าน versioned CDN ถูก mirror เป็น local path โดยคง version เดิม รวม image dependency ของ DataTables CSS ครบ
- `jquery-ui.css` 1.10.3 ของ CI3 อ้าง `images/animated-overlay.gif` แต่ CI3 checkout ไม่มีไฟล์นี้ จึง mirror artifact 1.10.3 จาก official jQuery CDN เป็น local path; ไฟล์อื่นใน image graph คัดจาก CI3 ตามเดิม
- `multifreezer.css` ถูกตัด public upstream author email ออกจาก comment เท่านั้นเพื่อผ่าน repository PII guard; CSS payload และ behavior ไม่เปลี่ยน
- CI gate allowlist `0123456789` เฉพาะ literal digit alphabet ของ jQuery UI 1.10.3 date formatter เพื่อไม่ให้ false-positive เป็น phone number; asset byte/runtime behavior คงเดิม
- `admin.css` ยังคงโหลดท้ายสุดเป็น compatibility adapter สำหรับ CI4-owned controller/view contract; ไม่มีการเปลี่ยน version ของ CI3 dependency

## Asset evidence

| Asset | SHA-256 | Disposition |
|---|---|---|
| `assets/css/multifreezer.css` | `368c8ca64dbc17e7366d5ad6dcd28059f2d533ddc626951f9b34b90680993ad3` | `ADAPTED_FOR_CI4` (comment only) |
| `assets/dist/css/skins/_all-skins.min.css` | `4736672260ab0cf94ad37de85f33a0c5aeb75d70320fc6480956680a1ef41f31` | `MIGRATED_AS_IS` |
| `assets/datatables/1.10.16/css/jquery.dataTables.min.css` | `618d62ceaca1223e16de2c8939a1963a95c34b0ac75852f835f93e5b42f20871` | `PRESERVED_LOCAL_CDN` |
| `assets/datatables/1.10.16/js/jquery.dataTables.min.js` | `a9c575c2bf9b9f836806dc58aa0866cb558806fc5ea1ef2f4250a8c0b1be7278` | `PRESERVED_LOCAL_CDN` |
| `assets/datatables-fixedcolumns/3.2.4/css/fixedColumns.dataTables.min.css` | `2cac99438be2f9aacaf1a63f220f5a4e0fb5f54d443ecde09652a650b0509f8b` | `PRESERVED_LOCAL_CDN` |
| `assets/datatables-fixedcolumns/3.2.4/js/dataTables.fixedColumns.min.js` | `e44ec8df1b3ae7c386f670b1e9d4b4cad0b55fa28f934f31fd9a893c81c50298` | `PRESERVED_LOCAL_CDN` |
| `assets/js/jquerydatepicker/images/animated-overlay.gif` | `c7bcc76fb23c0430b36ec448eb79f8bc34129dae95da10f3c14ed0eacdf2f1b9` | `PRESERVED_LOCAL_CDN` |

## After evidence

```text
php -l app/Views/layout.php
php -l app/Views/partials/admin_legacy_scripts.php
php vendor/bin/phpunit tests/ci4/MenuHttpTest.php tests/ci4/OrderHttpTest.php tests/ci4/UserHttpTest.php
```

ผล: PHP syntax ผ่านทั้งสองไฟล์; focused test ผ่าน `92 tests, 1410 assertions`; `AccessDeniedHttpTest` ผ่าน `30 tests, 419 assertions` รวม recursive local asset graph.

## Browser evidence

คำสั่ง probe:

```text
command -v chromium
command -v google-chrome
command -v playwright
find node_modules -maxdepth 2 -type d -name playwright -o -name @playwright
```

ผล: ไม่พบ executable หรือ local Playwright package. มีเพียง artifact เก่าใน `.playwright-mcp/`; ไม่ใช่ browser runtime ที่ใช้เปิด CI3/CI4 ได้. ดังนั้นยังเก็บ screenshot, network log, normalized DOM และ Bootstrap/DataTables interaction จริงไม่ได้.

สถานะ browser evidence: `BLOCKED` โดยต้องมี Chromium/Playwright browser backend ที่เปิด CI3 `127.0.0.1:18404` และ CI4 `127.0.0.1:18405` ได้. ห้ามสร้างภาพหรือ network result แทน runtime จริง.

## Parity result

- Functional regression: `PASS` เฉพาะ focused HTTP tests
- Dependency source/local integrity: `PASS` ระดับ static artifact
- DOM/CSS/JavaScript/Visual browser parity: `NOT-VERIFIED`
- Full admin DOM template parity: `OPEN`; adapter นี้แก้ dependency/asset contract ก่อน ไม่ใช่หลักฐานว่า hierarchy ของ CI3 admin header/sidebar/footer ตรงแล้ว
