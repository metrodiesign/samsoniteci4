# TPL-06 Order lifecycle, forms และ print

ย้าย CI3 order pages 10 files ด้วย order profile แยกจาก admin profile และคง exact upload/dependency chain.

## Source และ target

| Scope | CI4 target |
|---|---|
| Create/edit | order_new.php, order_edit.php |
| List/lifecycle | orders.php, orders_rating_modal.php |
| Print | order_print.php |
| Upload | partials/order_upload.php |

## งานและ gate

1. Comparator ตรวจ field name/default, status transition, upload preview และ print markup.
2. ขับ jQuery upload, validation, modal, table search/sort/pagination และ status action จริง.
3. ตรวจ local asset graph และ byte pin.
4. Capture DOM/network/visual pair ตาม shared fixture เดียวกัน.
