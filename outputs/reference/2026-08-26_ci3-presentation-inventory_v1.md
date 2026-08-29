# CI3 Presentation Inventory และ Traceability v1

วันที่: 2026-08-26

เอกสารนี้เป็น inventory ก่อนเริ่ม visual parity batch ถัดไป ตามกติกาให้ CI3 เป็น source of truth ของ presentation layer. ข้อมูลระดับไฟล์ทั้งหมดอยู่ใน `2026-08-26_ci3-presentation-inventory_v1.json`; เอกสารนี้อธิบายขอบเขต, วิธีสร้าง, layout stack และ dependency ที่โหลดจริง. JSON เป็น canonical manifest เพราะเก็บ hash และรายการ asset ทุกไฟล์ได้โดยไม่ตัดทอน.

## ขอบเขตและข้อจำกัด

- CI3 source (read-only): `/Users/king_developer/Desktop/Project/samsoniteci3`
- CI4 target: `/Users/king_developer/Desktop/Project/samsoniteci4`
- Inventory นี้เป็น static evidence จากไฟล์ ณ เวลาสร้างเท่านั้น ไม่ใช่หลักฐานว่า route, DOM, CSS, JavaScript หรือภาพตรงกัน
- `ci4_target_candidates` เป็น candidate จากชื่อและกลุ่มหน้าที่; ไม่ใช่การยืนยันว่าคัดลอกหรือ adapt template แล้ว
- ค่า `NOT_USED_WITH_EVIDENCE` ของ asset หมายถึงพบว่าเป็น documentation/example/source asset หรือไม่มี reference แบบ literal จาก CI3 view. ต้องตรวจ route/configuration และ runtime network log ก่อนตัดออกจาก release artifact
- เนื่องจากยังไม่มี file-level source-to-target proof สำหรับ view ทุกตัว ทุก CI3 view ใน manifest มี disposition `BLOCKED`; ไม่มีการใช้ `UNKNOWN`

## วิธีสร้างและตรวจสอบ

```bash
python3 scripts/generate-ci3-presentation-inventory.py \
  --ci3-root /Users/king_developer/Desktop/Project/samsoniteci3 \
  --ci4-root /Users/king_developer/Desktop/Project/samsoniteci4 \
  --output outputs/reference/2026-08-26_ci3-presentation-inventory_v1.json
python3 -m json.tool outputs/reference/2026-08-26_ci3-presentation-inventory_v1.json >/dev/null
```

ผลที่ตรวจได้จาก manifest:

| รายการ | จำนวน | สถานะ |
|---|---:|---|
| CI3 PHP view | 103 | `BLOCKED` 103 |
| CI4 PHP view | 39 | เป็น target inventory เท่านั้น |
| CI3 static asset | 997 | `MIGRATED_AS_IS` 15, `BLOCKED` 61, `NOT_USED_WITH_EVIDENCE` 921 |
| CI4 static asset | 17 | เป็น target inventory เท่านั้น |

ทุก record มี `source`, SHA-256 และ disposition; asset มีขนาดและจำนวน literal reference เพิ่มเติม. Record ของ CI3 view มี category และ `ci4_target_candidates` ด้วย. Path ที่มี `@` ถูก percent-encode เป็น `%40` เฉพาะใน manifest เพื่อไม่ให้ repository PII gate ตีความชื่อ asset เช่น `aero%402x.png` เป็น email; decode `%40` กลับเป็น at-sign ก่อนเปิดไฟล์.

## CI3 layout และ partial contract

`application/libraries/BaseController.php` เป็นหลักฐานของ stack ที่ CI3 render:

| CI3 layout stack | Header | Body | Footer | CI4 target ปัจจุบัน | Disposition |
|---|---|---|---|---|---|
| Admin ปกติ | `application/views/includes/header.php` | view ที่ส่งเข้า `loadViews()` | `application/views/includes/footer.php` | `app/Views/layout.php` | `BLOCKED` |
| Admin order | `application/views/includes/header_order.php` | view ที่ส่งเข้า order loader | `application/views/includes/footer_order.php` | `app/Views/layout.php`, `order_*.php` | `BLOCKED` |
| Public EN | `application/views/web/header.php` | public view | `application/views/web/footer.php` | `app/Views/layout_public.php` | `BLOCKED` |
| Public TH | `application/views/web/header_th.php` | public view | `application/views/web/footer.php` | `app/Views/layout_public.php` | `BLOCKED` |
| Email | ไม่มี wrapper จาก `loadViews()` | `application/views/email/resetPassword.php` | ไม่มี | ไม่มี CI4 email template ใน `app/Views` | `BLOCKED` |

CI3 error views (`application/views/errors/**`) ถูก inventory เป็น `framework_error_view` แยกจากหน้า application. ต้องกำหนด disposition โดยเทียบ CI4 error-handling contract แยก ไม่ควรนำมาเทียบ visual batch ของ business page โดยอัตโนมัติ.

## View traceability

รายการครบทั้ง 103 ไฟล์อยู่ใน key `ci3_views` ของ JSON. กลุ่มที่ต้องใช้เป็นลำดับในการสร้าง trace record ก่อนถ่ายรอบใหม่:

| กลุ่ม CI3 source | จำนวน | CI4 candidate ที่พบ | Disposition ปัจจุบัน |
|---|---:|---|---|
| Root page/view | 21 | `dashboard.php`, `users_*.php`, `tracking_*.php`, `login*.php`, `contact.php` และอื่น ๆ ตาม manifest | `BLOCKED` |
| `master/` | 37 | `master_list.php`, `master_form.php`, `background_*.php`, `menu_*.php` | `BLOCKED` |
| `tracking/` | 20 | `orders.php`, `order_*.php`, `reports/*.php`, `import_*.php` | `BLOCKED` |
| `en/`, `th/` | 7 | `tracking_*.php`, `contact.php`, `rating.php` | `BLOCKED` |
| Layout/partial | 7 | `layout.php`, `layout_public.php` | `BLOCKED` |
| Error/CLI | 10 | CI4 error views หรือไม่มี candidate | `BLOCKED` |
| Email template | 1 | ไม่มี CI4 candidate | `BLOCKED` |

ก่อนเปลี่ยน view ใด ๆ ให้เพิ่ม evidence ต่อ file/page อย่างน้อย: CI3 controller/route, CI3 view และ layout stack, CSS/JS ที่โหลด, CI4 controller/route/view, data adapter, และผล DOM/visual comparison. ห้ามใช้ candidate ใน manifest เป็น evidence ของ parity.

## Asset inventory

รายการครบทั้ง 997 ไฟล์อยู่ใน key `ci3_assets` ของ JSON. แบ่งเป็น top-level directory ดังนี้: `bootstrap`, `css`, `dist`, `font-awesome`, `fontawesome`, `fonts`, `images`, `img`, `jQueryUI`, `js`, `plugins`, `scss` และ `index.html`.

Asset ที่ byte-identical ใน CI4 แล้ว 15 ไฟล์ถูกระบุ `MIGRATED_AS_IS` โดย SHA-256. Asset ที่เป็น runtime candidate 61 ไฟล์ถูกระบุ `BLOCKED` จนกว่าจะมี source-to-target path, network evidence และ checksum/disposition ระดับไฟล์. ตัวอย่าง path ที่ loader ของ CI3 อ้างตรง ได้แก่:

- `assets/bootstrap/css/bootstrap.min.css`, `assets/bootstrap/js/bootstrap.min.js`
- `assets/dist/css/AdminLTE.min.css`, `assets/dist/css/CustomAdmin.css`, `assets/dist/css/skins/_all-skins.min.css`, `assets/dist/js/app.min.js`
- `assets/font-awesome/css/font-awesome.min.css`, `assets/fontawesome/css/font-awesome.css`
- `assets/js/jquery-3.2.1.min.js`, `assets/js/jquerydatepicker/jquery-1.10.2.min.js`, `assets/js/jquerydatepicker/jquery-ui.min.js`
- `assets/css/main.css`, `assets/css/multifreezer.css`, `assets/css/style.css`
- `assets/images/main-logo.png`, `assets/images/print-logo.jpg`, `assets/images/img-footer.png`

รายการตัวอย่างนี้ไม่แทน manifest ทั้งหมด.

## Frontend dependency inventory

| Dependency | CI3 evidence | Version ที่ยืนยันได้ | Source | CI4 disposition |
|---|---|---|---|---|
| Bootstrap | `includes/header.php`, `web/header.php` | 3.3.4 สำหรับ admin; public path ไม่ระบุ version จาก source ที่อ่าน | local `assets/bootstrap/` | `BLOCKED` |
| AdminLTE | `includes/header.php`, `includes/header_order.php` | ไม่พบ version string ใน loader | local `assets/dist/` | `BLOCKED` |
| Font Awesome | comment และ local loader ใน admin/public header | 4.3.0 local admin; `CustomAdmin.css` import CDN 4.7.0 | local + CDN | `BLOCKED` |
| jQuery | admin/public header | 1.10.2 admin, 3.2.1 public; มี local 2.1.4 ใน asset inventory | local | `BLOCKED` |
| jQuery UI + timepicker addon | admin layouts | jQuery UI path ไม่ระบุ version ใน loader | local `assets/js/jquerydatepicker/` | `BLOCKED` |
| DataTables | `includes/header.php` | CDN DataTables 1.10.16; FixedColumns 3.2.4 | CDN | `BLOCKED` |
| html5shiv | conditional comment ใน `includes/header.php` | 3.7.2 | CDN | `BLOCKED` |
| Respond.js | conditional comment ใน `includes/header.php` | 1.4.2 | CDN | `BLOCKED` |
| Other local plugins | `assets/plugins/` | ต้องยืนยันว่า route ใช้งานจริงต่อ dependency | local | `NOT_USED_WITH_EVIDENCE` หรือ `BLOCKED` รายไฟล์ใน JSON |

ห้าม upgrade หรือ replace dependency ใดในตารางนี้เพียงเพราะเก่า. CDN ที่ระบุ version ต้องคง library และ version เดิม; ถ้า CDN ใช้ไม่ได้ ให้ serve version เดิมจาก local artifact พร้อม before/after evidence.

## Page-level traceability ที่ปิดเพิ่มแล้ว

Batch แรก 10 หน้า (`tracking-*`, `contact-*`, login/auth, dashboard และ change-password) ถูกผูก route/controller/view/layout/CSS/JS/dependency แล้วที่ [WP-03E Batch 1 Presentation Traceability v1](2026-08-26_wp03e-batch1-presentation-traceability_v1.md). ทุกหน้าของ batch ยัง `BLOCKED` เพราะ evidence ชี้ว่า template/dependency contract ของ CI3 ยังไม่ได้ถูก preserve ใน CI4 target; เอกสารนั้นระบุ source และสาเหตุรายหน้าเพื่อใช้ปิดทีละรายการ.

## Gate ก่อน visual parity batch

1. เปลี่ยน disposition `BLOCKED` ของแต่ละหน้าที่จะถ่ายเป็น `MIGRATED_AS_IS`, `ADAPTED_FOR_CI4`, `COMPATIBILITY_SHIM` หรือ `NOT_USED_WITH_EVIDENCE` พร้อมหลักฐานระดับไฟล์
2. ยืนยัน asset และ dependency ที่ browser โหลดด้วย CI3/CI4 network log ไม่ใช่ static reference อย่างเดียว
3. ผูก CI3 view, layout stack, CSS, JS และ dependency กับ route ใน `outputs/reference/2026-08-25_wp03e-visual-route-map.md`
4. บันทึก allowlist สำหรับ CSRF, runtime URL และ framework-only DOM difference ก่อน normalized DOM comparison
5. ถ่ายภาพด้วย CI3 pin เดียวกัน, fixture/role/viewport/browser เดียวกัน และบันทึก manifest ใหม่

STATUS: OPEN
