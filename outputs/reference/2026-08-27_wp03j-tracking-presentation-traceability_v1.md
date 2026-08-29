# WP03J Tracking presentation traceability

เอกสารนี้บันทึกการย้าย public Tracking EN/TH จาก CI3 pin ที่ล็อกไว้สู่ CI4 พร้อมระบุ deviation ที่อนุมัติและหลักฐาน verification. Rating ไม่อยู่ใน batch นี้.

## Source และ mapping

| CI3 source | CI4 target | Verdict |
|---|---|---|
| `application/views/en/track.php` | `app/Views/tracking_form.php` | form EN, `#addtrack`, `#searchText`, mobile controls, modal และ copy ถูกย้าย |
| `application/views/th/track.php` | `app/Views/tracking_form.php` | form TH, popup และ copy ภาษาไทยถูกเลือกตาม language |
| `application/views/en/trackstatus.php` | `app/Views/tracking_result.php` | result hierarchy, timeline classes, date และ empty state ถูกย้าย |
| `application/views/th/trackstatus.php` | `app/Views/tracking_result.php` | Thai label ถูกเลือกจาก `status_name_th` |
| `application/views/web/header.php` และ `footer.php` | `app/Views/layout_public.php` | shared local CI3 chrome/dependency graph และ Bootstrap 3 modal prerequisite |
| `assets/js/addtrack.js` | `public/assets/js/addtrack.js` | bytes ตรง CI3; form คง `searchText` เพื่อให้ validation เดิมทำงาน |
| `Track.php`, `Track_th.php` | `app/Controllers/Tracking.php`, `app/Config/Routes.php` | explicit CI4 routes, query adapter, strict validation และ route normalization |

CI3 source ถูกอ่านจาก `/Users/king_developer/Desktop/Project/samsoniteci3` ที่ pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` แบบ read-only.

## Approved deviations

- Form submit เป็น `GET /tracking` หรือ `GET /tracking-th`; `tracking_id` เป็น canonical และ `searchText` เป็น compatibility fallback.
- ID ต้องตรง regex, ยาวไม่เกิน 100 ตัว และไม่ trim control/whitespace ก่อน validate; array, wildcard, markup, CR/LF และ encoded slash ได้ public no-data flow โดยไม่ reflect input.
- `(:any)` และ raw URI normalization ทำให้ segment ที่มี encoded slash ไม่ตกเป็น prefix ที่ดูเหมือน valid ID.
- Result EN ใช้ `status_name`, TH ใช้ `status_name_th`; complete ทั้งสองภาษาเทียบ English `status_name` แบบ case-insensitive.
- Form เริ่มจาก static CI3 `bg-tracking*.png`; published `BackgroundStore` laptop/mobile ที่ผ่าน validation override ผ่าน `/background-image/<hash>.png` เพื่อคง CI4 admin background contract. Result banner ยังคงใช้ `BackgroundStore` เท่านั้น.
- Result ไม่แสดง tracking-ID paragraph และไม่ใช้ `<picture>` หรือ `<source>` ที่ CI3 ไม่มี.

## Exact assets

| Asset | SHA-256 | Verdict |
|---|---|---|
| `assets/js/addtrack.js` | `8570b028d3f67cbbe6aa2cc72f3ca70f2d3302ab94546440da72b98de4a20130` | ตรง CI3 |
| `assets/images/bg-tracking.png` | `16b99ac15ba78c5dd6a462de19b8c349747b7621301a7a1cb3858e09753c813a` | ตรง CI3 |
| `assets/images/bg-tracking-mb.png` | `58e2c7e48ff6ee4791ff8cbd13215b0f301cef956bafc47f55de690e810eb7c3` | ตรง CI3 |
| `assets/images/popup_en.png` | `7ec545b28528c595c1d2e0aeb01d8f8a72ec80105ba81eac2ad4312755aab025` | ตรง CI3 |
| `assets/images/popup_th.png` | `e5078cb83be73c233f3d9421d77edba35a130a365f99bfb6fc896a68bb2ac85e` | ตรง CI3 |

## Evidence

| Check | Result |
|---|---|
| Focused Tracking, Contact, Rating HTTP | ผ่านหลัง final diff: `27 tests, 1228 assertions` |
| Tracking plus Background, Contact, Rating | ผ่าน: `33 tests, 1293 assertions` ระหว่างตรวจ compatibility override |
| `composer test` | ผ่าน: `333 tests, 5935 assertions` |
| `vendor/bin/phpstan analyse --memory-limit=1G` | ผ่าน: `No errors` |
| `bash scripts/ci-check.sh` | ผ่าน รวม PII guard, public-tracking concurrency และ repository safety |
| Asset graph | recursive DOM/CSS test ตรวจ external URL, missing file, CSS/HTML quoting forms และ SHA fail closed |

## Browser gap

Browser backend ไม่มีใน environment นี้. Normalized DOM via browser, network capture, Bootstrap interaction จริง และ visual comparison ที่ 1440x900/390x844 ยังเป็น `NOT VERIFIED`; HTTP/static/code gates ไม่ใช่หลักฐาน visual PASS.
