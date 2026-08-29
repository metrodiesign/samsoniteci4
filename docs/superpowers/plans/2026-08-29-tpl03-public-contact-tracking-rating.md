# TPL-03 Public Contact, Tracking และ Rating

รักษา CI3 public chrome, Contact EN/TH, Tracking EN/TH/result และ Rating. Contact/Tracking ที่มีอยู่ต้องได้ browser comparator ก่อนปิด package.

## Source และ target

| Scope | CI3 source | CI4 target |
|---|---|---|
| Contact | en/contact.php, th/contact.php | app/Views/contact.php |
| Tracking | en/track*.php, th/track*.php | tracking_form.php, tracking_result.php |
| Rating | en/rating.php | app/Views/rating.php |
| Public chrome | web/header*.php, web/footer.php | app/Views/layout_public.php |

## งานและ gate

1. เขียน per-page DOM/asset comparator ก่อนแก้.
2. รักษา form names, popup/modal, star widget, text, CSS class และ jQuery interaction จาก CI3.
3. ทดสอบ public route, CSRF, validation, idempotency, no-reflection และ state mutation.
4. เก็บ DOM, network, interaction และ visual pair สอง viewport.

## Rating blocker

CI3 view ส่ง 2 score แต่ CI3 controller มี 8-score contract. ห้ามเลือกแทน signed disposition; record runtime evidence แล้วปิดเป็น BLOCKED จนมี decision.
