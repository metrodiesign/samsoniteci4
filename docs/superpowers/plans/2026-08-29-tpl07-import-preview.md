# TPL-07 Import และ preview

รักษา CI3 import/preview pages 6 files และ spreadsheet upload flow.

## Source และ target

| Scope | CI4 target |
|---|---|
| Import forms | app/Views/import_form.php |
| Preview/error | app/Views/import_preview.php |

## งานและ gate

1. Comparator ตรวจ file control, preview table, error text และ confirm/cancel route.
2. ทดสอบ upload type/size validation, preview, confirm idempotency และ rollback.
3. ขับ preview interaction และ capture DOM/network/visual.
