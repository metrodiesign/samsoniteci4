# สรุปผลเทียบภาพ WP-03E visual parity (CI3 vs CI4)

หลักฐาน: 258 ภาพ / 65 หน้า / 2 viewport (`1440x900`, `390x844`)
ที่อยู่ภาพ `evidence/wp03e-visual/` รายละเอียดต่อ batch อยู่ใน `diff-batch1..5.md`

## ยอดรวม

| verdict | จำนวนหน้า |
|---|---|
| MATCH | 0 |
| MINOR | 1 |
| MAJOR | 54 |
| BEHAVIOR | 10 |

| ข้อเสนอ | จำนวนหน้า |
|---|---|
| FIX_CI4 | 39 |
| NEED_USER | 24 |
| CORRECT_AND_REBASELINE | 1 |
| ACCEPT | 1 |

## ตารางเต็ม

| batch | page-id | desktop | mobile | verdict | ข้อเสนอ |
|---|---|---|---|---|---|
| 1 | tracking-home-en | MINOR | MAJOR | MAJOR | FIX_CI4 |
| 1 | tracking-home-th | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 1 | tracking-result-en | MAJOR | MAJOR | MAJOR | NEED_USER |
| 1 | tracking-result-th | MINOR | MINOR | BEHAVIOR | CORRECT_AND_REBASELINE |
| 1 | contact-form-en | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 1 | contact-form-th | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 1 | login | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 1 | forgot-password | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 1 | dashboard | MAJOR | MAJOR | MAJOR | NEED_USER |
| 1 | change-password | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 2 | rating-form | BEHAVIOR | BEHAVIOR | BEHAVIOR | NEED_USER |
| 2 | reset-password | BEHAVIOR | BEHAVIOR | BEHAVIOR | NEED_USER |
| 2 | orders-new | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 2 | orders-edit | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 2 | orders-print | MINOR | MINOR | MINOR | ACCEPT |
| 2 | order-listing-status1 | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 2 | order-listing-status2 | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 2 | order-listing-status3 | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 2 | order-listing-status4 | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 2 | order-listing-status5 | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 2 | order-listing-status7 | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 2 | report-tracking-listing | MAJOR | MAJOR | MAJOR | NEED_USER |
| 3 | master-branch-listing | MAJOR | MAJOR | MAJOR | NEED_USER |
| 3 | master-branch-edit | MAJOR | MAJOR | MAJOR | NEED_USER |
| 3 | master-branchtype-listing | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-branchtype-edit | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-statustype-listing | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-statustype-edit | MAJOR | MAJOR | MAJOR | NEED_USER |
| 3 | master-producttype-listing | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-producttype-edit | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-book-listing | MAJOR | MAJOR | MAJOR | NEED_USER |
| 3 | master-book-edit | MAJOR | MAJOR | MAJOR | NEED_USER |
| 3 | master-brand-listing | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-brand-edit | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-condition-listing | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-condition-edit | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-estimateprice-listing | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-estimateprice-edit | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-fixed-listing | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-fixed-edit | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-provider-listing | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 3 | master-provider-edit | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 4 | imports-status | BEHAVIOR | MAJOR | BEHAVIOR | NEED_USER |
| 4 | imports-price | BEHAVIOR | MAJOR | BEHAVIOR | NEED_USER |
| 4 | imports-new-order | BEHAVIOR | MAJOR | BEHAVIOR | NEED_USER |
| 4 | login-history-own | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 4 | users-history-of-user | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 4 | contact-listing | MINOR | MAJOR | MAJOR | FIX_CI4 |
| 4 | menu-listing | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 4 | menu-edit | MINOR | MAJOR | MAJOR | FIX_CI4 |
| 4 | background-listing | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 4 | background-edit | MAJOR | MAJOR | MAJOR | NEED_USER |
| 4 | users-listing | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 4 | users-edit | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 5 | report-ratings | MAJOR | MAJOR | MAJOR | FIX_CI4 |
| 5 | report-jobs-by-day | MAJOR | MAJOR | MAJOR | NEED_USER |
| 5 | report-pending | MAJOR | MAJOR | MAJOR | NEED_USER |
| 5 | report-pending-total | MAJOR | MAJOR | MAJOR | NEED_USER |
| 5 | report-in-progress-average | MAJOR | MAJOR | MAJOR | NEED_USER |
| 5 | report-in-progress | MAJOR | MAJOR | MAJOR | NEED_USER |
| 5 | report-summary | MAJOR | MAJOR | MAJOR | NEED_USER |
| 5 | export-ratings | BEHAVIOR | BEHAVIOR | BEHAVIOR | NEED_USER |
| 5 | export-in-progress | BEHAVIOR | BEHAVIOR | BEHAVIOR | NEED_USER |
| 5 | export-tracking | BEHAVIOR | BEHAVIOR | BEHAVIOR | NEED_USER |
| 5 | export-summary | BEHAVIOR | BEHAVIOR | BEHAVIOR | NEED_USER |
