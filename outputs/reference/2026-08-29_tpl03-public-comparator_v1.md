# TPL-03 Contact และ Tracking Comparator

เอกสารนี้บันทึก source mapping, allowlist และผลรันจริงของ TPL-03 บน CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`. Rating และ browser axes ยังเป็น `BLOCKED`; inventory ไม่มีการเปลี่ยน disposition.

## Source identity

| รายการ | ค่า |
|---|---|
| CI3 source | `/Users/king_developer/Desktop/Project/samsoniteci3` |
| CI3 commit | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` |
| CI3 worktree | clean |
| CI4 base commit | `22aeffcdc8c126df4788268e3b0a8dccf6a86cb0` |
| PHP | `8.5.7` |

ตรวจ identity ด้วยคำสั่ง:

```bash
git -C /Users/king_developer/Desktop/Project/samsoniteci3 rev-parse HEAD
git -C /Users/king_developer/Desktop/Project/samsoniteci3 status --short
git rev-parse HEAD
```

## Source mapping

| Contract | CI3 authority | CI4 target | Comparator |
|---|---|---|---|
| Contact EN form และ validation shell | `application/views/en/contact.php:2-100` | `app/Views/contact.php:57-138` | `ContactHttpTest.php:68-164,216-247` |
| Contact TH form และ validation shell | `application/views/th/contact.php:2-99` | `app/Views/contact.php:57-138` | `ContactHttpTest.php:68-164,249-265` |
| Tracking EN form, modal และ popup | `application/views/en/track.php:1-98` | `app/Views/tracking_form.php:14-97` | `PublicTrackingHttpTest.php:148-230,592-603` |
| Tracking TH form, modal และ popup | `application/views/th/track.php:1-96` | `app/Views/tracking_form.php:14-97` | `PublicTrackingHttpTest.php:148-230,592-603` |
| Tracking EN known/complete/empty result | `application/views/en/trackstatus.php:2-81` | `app/Views/tracking_result.php:7-59` | `PublicTrackingHttpTest.php:24-51,139-146,258-300,561-577,605-615` |
| Tracking TH known/complete/empty result | `application/views/th/trackstatus.php:2-81` | `app/Views/tracking_result.php:7-59` | `PublicTrackingHttpTest.php:24-51,139-146,258-300,561-577,605-615` |
| Rating EN | `application/views/en/rating.php:1-203` | ไม่เปลี่ยน | `BLOCKED` รอ signed 2-score/8-score decision |

พบ source ที่ map ได้ 7 views. Design ระบุ TPL-03 จำนวน 8 files แต่ plan, inventory และ CI3 tracked views ให้ Contact 2 + Tracking 4 + Rating 1 = 7; ไม่สร้าง record ที่แปดเพื่อปรับตัวเลข.

## Comparator และ allowlist

| Scope | สิ่งที่ล็อก | Allowed deviation |
|---|---|---|
| Contact | EN/TH chrome, form/field order, action, class, copy, map, asset order, success direct-child และ validation alertใน `.row > .col-md-12` | CSRF, `submission_id`, constraints, escaped kept values และ generic feedback |
| Tracking form | EN/TH chrome, form/action, `searchText`, modal hierarchy, popup, visible copy และ asset order | canonical GET, legacy POST aliases และ strict exact no-trim lookup |
| Tracking result | known/complete/empty hierarchy, timeline order, language label, completion class และ empty placeholder | published `BackgroundStore` bannerแทน static banner; corrected EN/TH label/completion behavior |
| Public input | malformed, whitespace, array, wildcard, markup, CR/LF, encoded slash และ oversized inputไม่พบ recordและไม่ reflect/รั่ว PII | ไม่มี |

Comparator ใช้ `DOMDocument`/`DOMXPath` ตรวจ direct ancestor ของ Contact EN/TH normal, success, invalid; Tracking form/modal EN/TH; known result EN/TH และ empty result. Ordered helperเหลือเฉพาะ field/text/asset order.

Rework 1/5 reproduce auditได้ EN/TH invalid `inside shell count 0` และ testแดง 2 จุด. Production fixย้ายเฉพาะ validation alertเข้า `.row > .col-md-12`; success flashคง direct childของ `.col-md-4` ตาม CI3.

## ผลรัน

| Check | Result |
|---|---|
| Focused Contact/Tracking/Rating PHPUnit | `PASS`, 44 tests, 1,462 assertions |
| Full PHPUnit | `PASS`, 437 tests, 9,289 assertions |
| PHPStan | `PASS`, no errors |
| Repository CI gate | `PASS`, exit 0 |

คำสั่ง:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/ContactHttpTest.php tests/ci4/PublicTrackingHttpTest.php tests/ci4/RatingHttpTest.php
vendor/bin/phpunit --configuration phpunit.xml.dist
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
scripts/ci-check.sh
```

## Security property และ mutation-check

| จุดตรวจ | Mutation | Expected | Result |
|---|---|---|---|
| Contact validation parent | ย้าย EN/TH error alertออกจาก `.col-md-12` เป็น direct childโดยคง token order | XPath comparator แดง | `RED`, 2 tests, 2 failures; restore แล้ว focusedเขียว |
| Tracking modal parent | ย้าย `.modal-dialog` ออกนอก `#myModal` โดยคง token order | XPath comparator แดง | `RED`, 1 test, 1 failure; restore แล้ว focusedเขียว |
| Contact shell identity | เปลี่ยน `col-md-4` เป็น `col-md-4-mutated` ใน implementรอบ 1 | comparator แดง | `RED`, 1 test, 1 failure; restore แล้ว |
| Tracking language popup | บังคับ EN ใช้ `popup_th.png` ใน implementรอบ 1 | comparator แดง | `RED`, 1 test, 1 failure; restore แล้ว |

Contact invalid/replay/delivery-unavailable test ขับ transaction branch และตรวจ row count. Invalid marker `<script>CONTACT-MARKER</script>` ต้องกลับเป็น escaped kept valueและไม่มี executable reflection. Tracking adversarial test ขับ exact lookupด้วย malformed shapesทั้งหมดและห้าม status, customer name, emailหรือ markerปรากฏใน response.

## Blocked axes และ inventory

| Axis | Status | เหตุผล |
|---|---|---|
| Rating 2-score/8-score contract | `BLOCKED` | CI3 route, view, controller และ CI4 contractขัดกัน; ไม่มี signed decision |
| Normalized browser DOM | `BLOCKED` | ไม่มี browser capability |
| JavaScript modal interaction | `BLOCKED` | ไม่มี browser capability |
| Network capture | `BLOCKED` | ไม่มี browser capability |
| Visual pair | `BLOCKED` | ไม่มี browser capability |
| TPL-03 denominator | `BLOCKED` | map ได้ 7 views แต่ design ระบุ 8 |

Inventory v2 ไม่มี diff. เอกสารนี้ไม่ประกาศ Rating, browser, network, visual หรือ inventory closure เป็น `PASS`.
