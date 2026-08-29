# WP-03E Batch 1 Presentation Traceability v1

วันที่: 2026-08-26

เอกสารนี้ผูก presentation contract ของ 10 หน้าที่จะใช้เป็น visual parity batch แรก. ใช้ CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` เป็น source of truth และใช้ route scenario จาก `2026-08-25_wp03e-visual-route-map.md`. เป็น static source evidence; ยังไม่มี DOM หรือ visual verdict ใหม่.

## กติกาการอ่าน

- ทุก path CI3 อ้างจาก `/Users/king_developer/Desktop/Project/samsoniteci3/` และเป็น read-only
- `BLOCKED` หมายถึงพบว่า target ปัจจุบันไม่ได้ใช้ CI3 template/dependency เดิมเป็นฐานอย่างพิสูจน์ได้ หรือยังไม่มี network/runtime evidence ของ asset ที่โหลดจริง
- ไม่ใช้ `UNKNOWN`: รายการที่ยังยืนยันไม่ได้ถูกระบุ `BLOCKED` พร้อมสาเหตุ
- `CI3 CSS/JS` รวม asset ที่ layout โหลดร่วมกับ asset ที่ view โหลดเฉพาะหน้า
- CI4 CSS/JS เป็นข้อเท็จจริงของ target ปัจจุบัน ไม่ใช่การอนุมัติให้เปลี่ยน dependency

## Shared layout trace

| Contract | CI3 source | CI3 assets/dependencies ที่ loader อ้าง | CI4 target | Disposition |
|---|---|---|---|---|
| Public EN | `application/libraries/BaseController.php:137-139` -> `web/header.php` + body + `web/footer.php` | jQuery 3.2.1, Bootstrap (public path), Font Awesome (public path), `main.css`, `fonts/stylesheet.css`, `dist/js/app.min.js`, `jquery.validate.js`, `validation.js` | `app/Views/layout_public.php` | `BLOCKED`: CI4 layout โหลดเฉพาะ `fonts/stylesheet.css`, `main.css`, `public.css` และแทน Font Awesome ด้วย inline SVG; ไม่ได้ preserve dependency contract |
| Public TH | `BaseController.php:143-145` -> `web/header_th.php` + body + `web/footer.php` | เหมือน Public EN | `app/Views/layout_public.php` | `BLOCKED`: เหตุเดียวกับ Public EN |
| Admin | `BaseController.php:109-113` -> `includes/header.php` + body + `includes/footer.php` | Bootstrap 3.3.4, AdminLTE, CustomAdmin, Font Awesome 4.3.0, jQuery 1.10.2, jQuery UI/timepicker, DataTables 1.10.16, FixedColumns 3.2.4, `app.min.js`, jQuery Validate, validation.js | `app/Views/layout.php` ผ่าน `app/Controllers/BaseController.php::layout()` | `BLOCKED`: CI4 layout โหลด `admin.css` และ fonts เท่านั้น; dependency contract เดิมไม่ถูกใช้ |
| Standalone login/reset | `application/controllers/Login.php` เรียก `load->view()` โดยตรง | แต่ละ view โหลด Bootstrap, AdminLTE, CustomAdmin, jQuery 2.1.4 และ asset ตามด้านล่าง | `app/Views/layout.php` เมื่อไม่ login | `BLOCKED`: CI4 ใช้ bare branch ของ admin layout ไม่ใช่ CI3 standalone markup/dependency |

## รายหน้า

| Page ID | Route: CI3 -> CI4 | CI3 controller / view / layout | CI3 CSS, JS, dependency | CI4 controller / view / layout | Trace result |
|---|---|---|---|---|---|
| `tracking-home-en` | `/` -> `/` | `Track::index()`; `application/views/en/track.php`; `web/header.php` + `web/footer.php` | shared Public EN; เพิ่ม `images/bg-tracking.png`, `images/popup_en.png`, `js/addtrack.js` | `Tracking::form()` -> `tracking_form.php` -> `layout_public.php` | `BLOCKED`: CI4 ใช้ dynamic `/background-image/*`, inline dialog script และ GET `tracking_id`; ต้องพิสูจน์/restore CI3 template, `addtrack.js`, asset path และ dependency ก่อน PASS |
| `tracking-home-th` | `/track_th` -> `/track_th` | `Track_th::index()`; `application/views/th/track.php`; `web/header_th.php` + `web/footer.php` | shared Public TH; เพิ่ม `images/bg-tracking.png`, `images/popup_th.png`, `js/addtrack.js` | `Tracking::formThai()` -> `tracking_form.php` -> `layout_public.php` | `BLOCKED`: เหมือน EN; CI3 form ใช้ POST flow แต่ CI4 ใช้ GET `tracking_id` |
| `tracking-result-en` | `/track/trackstatus/{trackId}` -> `/tracking/{trackId}` | `Track::trackstatus($trackID)`; `application/views/en/trackstatus.php`; `web/header.php` + `web/footer.php` | shared Public EN; ไม่มี view-specific literal asset | `Tracking::english()` -> `tracking_result.php` -> `layout_public.php` | `BLOCKED`: ต้องเทียบ DOM และ runtime status timeline กับ CI3 source; shared public dependency ไม่ถูก preserve |
| `tracking-result-th` | POST `/track_th/trackstatus` -> `/tracking-th/{trackId}` | `Track_th::trackstatus()`; `application/views/th/trackstatus.php`; `web/header_th.php` + `web/footer.php` | shared Public TH; ไม่มี view-specific literal asset | `Tracking::thai()` -> `tracking_result.php` -> `layout_public.php` | `BLOCKED`: CI3 GET segment ใช้ไม่ได้จริงตาม visual manifest; CI4 GET segment ใช้ได้ จึงเป็น behavior difference ที่ต้อง disposition แยกก่อน visual PASS |
| `contact-form-en` | `/contact` -> `/contact` | `Contact::index()`; `application/views/en/contact.php`; `web/header.php` + `web/footer.php` | shared Public EN; เพิ่ม `images/bg-contact.png`, `images/img-contact-1.png`, `images/img-contact-2.png`, `js/addContact.js`; Google Maps URL | `Contact::form()` -> `contact.php` -> `layout_public.php` | `BLOCKED`: CI4 controller/view มี server-side form + CSRF; ต้องใช้ CI3 markup/JS เป็นฐานและ record adapter ที่จำเป็น |
| `contact-form-th` | `/contact_th` -> `/contact-th` | `Contact_th::index()`; `application/views/th/contact.php`; `web/header_th.php` + `web/footer.php` | shared Public TH; เพิ่ม asset และ JS ชุดเดียวกับ EN | `Contact::formThai()` -> `contact.php` -> `layout_public.php` | `BLOCKED`: CI4 route alias `/contact_th` มี แต่ target primary URL เปลี่ยน; ต้องบันทึก route contract และ preserve view/dependency |
| `login` | `/login` -> `/login` | `Login::index()` / `isLoggedIn()`; `application/views/login.php`; ไม่มี `loadViews()` wrapper | Bootstrap, AdminLTE, CustomAdmin, Font Awesome local, jQuery 2.1.4, `images/bg-login.jpg`, `images/img-footer.png`, `css/main.css` | `Login::index()` -> `login.php` -> bare `layout.php` | `BLOCKED`: CI4 template/layout/dependency ไม่ใช่ CI3 source; ต้อง trace form markup และ submit/error flow ก่อน visual batch |
| `forgot-password` | `/forgotPassword` -> `/forgot-password` | `Login::forgotPassword()`; `application/views/forgotPassword.php`; ไม่มี wrapper | ชุดเดียวกับ login | `PasswordReset::forgotForm()` -> `forgot_password.php` -> bare `layout.php` | `BLOCKED`: CI4 ใช้ inline `fetch()` ไป JSON endpoint แทน CI3 POST form; ต้องบันทึก disposition ของ corrected security flow โดยไม่เปลี่ยน presentation contract |
| `dashboard` | `/dashboard` -> `/dashboard` | `User::index()`; `application/views/dashboard.php`; `includes/header.php` + `includes/footer.php` | shared Admin; `User::index()` อ้าง fallback `assets/images/bg-dashbord.png` | `Dashboard::index()` -> `dashboard.php` -> `layout.php` | `BLOCKED`: admin layout/dependencies ไม่ preserve; ต้อง map tile markup, menu, footer และ background asset ก่อน visual PASS |
| `change-password` | `/loadChangePass` -> `/change-password` | `User::loadChangePass()`; `application/views/changePassword.php`; `includes/header.php` + `includes/footer.php` | shared Admin; เพิ่ม `images/bg-form.png` | `Users::changePassword()` -> `change_password.php` -> `layout.php` | `BLOCKED`: CI4 has CSRF/current-password policy changes; ต้องใช้ compatibility adapter เพื่อรักษา CI3 template/field order และบันทึก security exception แยก |

## Required dependency disposition for this batch

| Dependency / asset family | CI3 version/evidence | Required next evidence | Current disposition |
|---|---|---|---|
| jQuery | 3.2.1 in public header; 1.10.2 admin header; 2.1.4 standalone login/reset | CI3/CI4 network log ต่อหน้าและ asset checksum | `BLOCKED` |
| Bootstrap | Admin header comment 3.3.4; public local path version not identified | local file banner/hash และ runtime load order | `BLOCKED` |
| AdminLTE / CustomAdmin | local `assets/dist/` from admin and standalone views | local version/banner/hash และ CI4 serving path | `BLOCKED` |
| Font Awesome | 4.3.0 local admin; public local path; CustomAdmin imports CDN 4.7.0 | network log และ CDN/local preservation decision | `BLOCKED` |
| DataTables / FixedColumns | CDN 1.10.16 / 3.2.4 from admin layout | confirm whether each batch-1 page initializes `#example` and load network resources | `BLOCKED` |
| `addtrack.js`, `addContact.js` | literal view references | capture CI3 event behavior then preserve or add a documented compatibility shim | `BLOCKED` |
| public/admin image assets | literal references listed per page/layout | verify source SHA-256 against target path or record local serving adapter | `BLOCKED` |

## Visual batch entry gate

Batch 1 ต้องไม่ถูกประกาศ `PASS` จาก screenshot เดิมเพียงอย่างเดียว. ก่อนถ่าย/ตัดสินรอบใหม่ต้องมีต่อ page:

1. source-to-target mapping ที่เปลี่ยน disposition จาก `BLOCKED` พร้อม before/change/after evidence
2. CI3 และ CI4 network log ที่พิสูจน์ CSS, JS, font และ image ที่โหลดจริง
3. DOM normalization allowlist สำหรับ CSRF, dynamic background URL และ framework-only differences
4. screenshot คู่บน fixture, role, browser และ viewport เดียวกัน
5. JavaScript interaction capture สำหรับ tracking dialog/submit, contact submit/validation, login และ forgot-password

STATUS: OPEN
