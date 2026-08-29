# Traceability — WP03I Contact presentation

เอกสารนี้ผูก CI3 pin `ee1c95e` กับ Contact EN/TH ของ CI4. ขอบเขตคือ public chrome และ Contact เท่านั้น.

## Route และ render

| CI3 | CI4 | หลักฐาน/ผล |
|---|---|---|
| `GET /contact` | `GET /contact` | `Contact::form()` ใช้ `legacyContactProfile` |
| `GET /contact_th` | `GET /contact-th`, alias `/contact_th` | `Contact::formThai()` ใช้ DOM TH เดียวกัน |
| `POST /addContact` | `POST /contact`, alias `/addContact` | route explicit และ CSRF/workflow เดิม |
| `POST /addContact_th` | `POST /contact-th`, alias `/addContact_th` | route explicit และ CSRF/workflow เดิม |

## Template และ dependency

| CI3 source | CI4 target | การพิสูจน์ |
|---|---|---|
| `web/header.php`, `header_th.php`, `footer.php` | `app/Views/layout_public.php` | header/footer hierarchy, `#menu-btn`, Font Awesome hooks, script order |
| `en/contact.php`, `th/contact.php` | `app/Views/contact.php` | `#addContact`, field id/name/class/order, text, map control |
| `assets/js/*`, Bootstrap, Font Awesome, DB Helvethaica | `public/assets/**` | SHA-256 locked ใน `ContactHttpTest`; AdminLTE comment-only adapter มี payload invariant |
| `assets/css/main.css` | existing `public/assets/css/main.css` | ไม่แก้; ใช้ adapter เดิม `contact_mobile.jpg` เป็น `contact_mobile.png` |

## Security และความต่างที่อนุญาต

- เพิ่ม hidden CSRF และ `submission_id`, `maxlength`/`required`, escaped value และ generic status/error output.
- คง `ContactSubmissionWorkflow`: validation, idempotency, transaction และ encrypted delivery intent.
- Google Map เป็น input control นอก form แบบ CI3 จึงนำทางด้วย user action โดยไม่ submit form.
- Contact ไม่โหลด `public.css`; Tracking/Rating ยังคง profile เดิมเพื่อไม่รบกวน batch ที่เลื่อนออกไป.
- `assets/dist/js/app.min.js` มาจาก CI3 SHA-256 `54101b5ffbeed57ac37b68edb22598cce27c6b859e57108d8b499dc850d48df9`; local SHA-256 `9c26866018e993de41ee60adb86a74963699fd920a4210337527d3550c70e9e6` ลบเฉพาะ upstream `@Email` comment เพื่อผ่าน PII guard. Runtime payload ตั้งแต่ `"use strict"` SHA-256 `4de4779418ce0c6a42f2f146f05b110f96fb3a9f8e8de264fdec8c3daff0407d` ตรง CI3; author, AdminLTE v2.1.0 และ MIT license คงเดิม.

## Verification

`php vendor/bin/phpunit tests/ci4/ContactHttpTest.php tests/ci4/PublicTrackingHttpTest.php tests/ci4/RatingHttpTest.php` ผ่าน `20 tests, 700 assertions`.

- DOM/asset test เดิน `link`/`script`/`img` และ CSS `url()` แบบ recursive, reject external runtime URL และยืนยัน local resolution/checksum.
- Browser runtime ไม่มี backend ตาม state จึงยังไม่มี normalized DOM/network capture หรือ screenshot 1440x900/390x844: `NOT VERIFIED`.
