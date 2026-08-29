# TPL-08 Reports และ export documents

ย้าย CI3 reports/export 12 files โดยคง filter, malformed-input behavior และ document output.

## Source และ target

| Scope | CI4 target |
|---|---|
| Report matrix | app/Views/reports/matrix.php |
| Summary/tracking | reports/summary.php, reports/tracking.php |
| Export | reports/export.php |

## งานและ gate

1. Capture CI3 malformed-date และ empty-result behavior ก่อนแก้.
2. Comparator ตรวจ form/query default, table headers/order, totals และ export bytes.
3. ทดสอบ report authorization, filter, CSV/XLS output และ error contract.
4. Browser proof ขับ filter/export และ capture visual pair.
