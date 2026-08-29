# แผนที่ CI3 view ไป CI4 dedicated runtime และ evidence

- CI3 authority: `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`
- runtime-required: `102`
- dedicated runtime: `102/102 PASS`
- same-run DOM/interaction/desktop/mobile: `102/102 PASS`

| ลำดับ | CI3 source | CI4 target | scenario | caller kind | runtime | DOM | interaction | desktop/mobile |
|---:|---|---|---|---|---|---|---|---|
| 1 | `application/views/404.php` | `app/Views/ci3/404.php` | `404` | framework HTML error | PASS | PASS | PASS | PASS |
| 2 | `application/views/access.php` | `app/Views/ci3/access.php` | `access` | browser page | PASS | PASS | PASS | PASS |
| 3 | `application/views/addNew.php` | `app/Views/ci3/addNew.php` | `addNew` | browser page | PASS | PASS | PASS | PASS |
| 4 | `application/views/changePassword.php` | `app/Views/ci3/changePassword.php` | `changePassword` | browser page | PASS | PASS | PASS | PASS |
| 5 | `application/views/contact.php` | `app/Views/ci3/contact.php` | `contact` | browser page | PASS | PASS | PASS | PASS |
| 6 | `application/views/dashboard.php` | `app/Views/ci3/dashboard.php` | `dashboard` | browser page | PASS | PASS | PASS | PASS |
| 7 | `application/views/editOld.php` | `app/Views/ci3/editOld.php` | `editOld` | browser page | PASS | PASS | PASS | PASS |
| 8 | `application/views/email/resetPassword.php` | `app/Views/ci3/email/resetPassword.php` | `email__resetPassword` | email | PASS | PASS | PASS | PASS |
| 9 | `application/views/en/contact.php` | `app/Views/ci3/en/contact.php` | `en__contact` | browser page | PASS | PASS | PASS | PASS |
| 10 | `application/views/en/rating.php` | `app/Views/ci3/en/rating.php` | `en__rating` | browser page | PASS | PASS | PASS | PASS |
| 11 | `application/views/en/track.php` | `app/Views/ci3/en/track.php` | `en__track` | browser page | PASS | PASS | PASS | PASS |
| 12 | `application/views/en/trackstatus.php` | `app/Views/ci3/en/trackstatus.php` | `en__trackstatus` | browser page | PASS | PASS | PASS | PASS |
| 13 | `application/views/errors/cli/error_404.php` | `app/Views/ci3/errors/cli/error_404.php` | `errors__cli__error_404` | framework CLI error | PASS | PASS | PASS | PASS |
| 14 | `application/views/errors/cli/error_db.php` | `app/Views/ci3/errors/cli/error_db.php` | `errors__cli__error_db` | framework CLI error | PASS | PASS | PASS | PASS |
| 15 | `application/views/errors/cli/error_exception.php` | `app/Views/ci3/errors/cli/error_exception.php` | `errors__cli__error_exception` | framework CLI error | PASS | PASS | PASS | PASS |
| 16 | `application/views/errors/cli/error_general.php` | `app/Views/ci3/errors/cli/error_general.php` | `errors__cli__error_general` | framework CLI error | PASS | PASS | PASS | PASS |
| 17 | `application/views/errors/cli/error_php.php` | `app/Views/ci3/errors/cli/error_php.php` | `errors__cli__error_php` | framework CLI error | PASS | PASS | PASS | PASS |
| 18 | `application/views/errors/html/error_404.php` | `app/Views/ci3/errors/html/error_404.php` | `errors__html__error_404` | framework HTML error | PASS | PASS | PASS | PASS |
| 19 | `application/views/errors/html/error_db.php` | `app/Views/ci3/errors/html/error_db.php` | `errors__html__error_db` | framework HTML error | PASS | PASS | PASS | PASS |
| 20 | `application/views/errors/html/error_exception.php` | `app/Views/ci3/errors/html/error_exception.php` | `errors__html__error_exception` | framework HTML error | PASS | PASS | PASS | PASS |
| 21 | `application/views/errors/html/error_general.php` | `app/Views/ci3/errors/html/error_general.php` | `errors__html__error_general` | framework HTML error | PASS | PASS | PASS | PASS |
| 22 | `application/views/errors/html/error_php.php` | `app/Views/ci3/errors/html/error_php.php` | `errors__html__error_php` | framework HTML error | PASS | PASS | PASS | PASS |
| 23 | `application/views/excel_in_progress_job.php` | `app/Views/ci3/excel_in_progress_job.php` | `excel_in_progress_job` | export | PASS | PASS | PASS | PASS |
| 24 | `application/views/excel_report_rating.php` | `app/Views/ci3/excel_report_rating.php` | `excel_report_rating` | export | PASS | PASS | PASS | PASS |
| 25 | `application/views/forgotPassword.php` | `app/Views/ci3/forgotPassword.php` | `forgotPassword` | browser page | PASS | PASS | PASS | PASS |
| 26 | `application/views/includes/footer.php` | `app/Views/ci3/includes/footer.php` | `includes__footer` | composed partial | PASS | PASS | PASS | PASS |
| 27 | `application/views/includes/footer_order.php` | `app/Views/ci3/includes/footer_order.php` | `includes__footer_order` | composed partial | PASS | PASS | PASS | PASS |
| 28 | `application/views/includes/header.php` | `app/Views/ci3/includes/header.php` | `includes__header` | composed partial | PASS | PASS | PASS | PASS |
| 29 | `application/views/includes/header_order.php` | `app/Views/ci3/includes/header_order.php` | `includes__header_order` | composed partial | PASS | PASS | PASS | PASS |
| 30 | `application/views/login.php` | `app/Views/ci3/login.php` | `login` | browser page | PASS | PASS | PASS | PASS |
| 31 | `application/views/loginHistory.php` | `app/Views/ci3/loginHistory.php` | `loginHistory` | browser page | PASS | PASS | PASS | PASS |
| 32 | `application/views/master/add_background.php` | `app/Views/ci3/master/add_background.php` | `master__add_background` | browser page | PASS | PASS | PASS | PASS |
| 33 | `application/views/master/add_book.php` | `app/Views/ci3/master/add_book.php` | `master__add_book` | browser page | PASS | PASS | PASS | PASS |
| 34 | `application/views/master/add_branch.php` | `app/Views/ci3/master/add_branch.php` | `master__add_branch` | browser page | PASS | PASS | PASS | PASS |
| 35 | `application/views/master/add_branchtype.php` | `app/Views/ci3/master/add_branchtype.php` | `master__add_branchtype` | browser page | PASS | PASS | PASS | PASS |
| 36 | `application/views/master/add_brand.php` | `app/Views/ci3/master/add_brand.php` | `master__add_brand` | browser page | PASS | PASS | PASS | PASS |
| 37 | `application/views/master/add_condition.php` | `app/Views/ci3/master/add_condition.php` | `master__add_condition` | browser page | PASS | PASS | PASS | PASS |
| 38 | `application/views/master/add_estimateprice.php` | `app/Views/ci3/master/add_estimateprice.php` | `master__add_estimateprice` | browser page | PASS | PASS | PASS | PASS |
| 39 | `application/views/master/add_fixed.php` | `app/Views/ci3/master/add_fixed.php` | `master__add_fixed` | browser page | PASS | PASS | PASS | PASS |
| 40 | `application/views/master/add_menus.php` | `app/Views/ci3/master/add_menus.php` | `master__add_menus` | browser page | PASS | PASS | PASS | PASS |
| 41 | `application/views/master/add_producttype.php` | `app/Views/ci3/master/add_producttype.php` | `master__add_producttype` | browser page | PASS | PASS | PASS | PASS |
| 42 | `application/views/master/add_provider.php` | `app/Views/ci3/master/add_provider.php` | `master__add_provider` | browser page | PASS | PASS | PASS | PASS |
| 43 | `application/views/master/add_statustype.php` | `app/Views/ci3/master/add_statustype.php` | `master__add_statustype` | browser page | PASS | PASS | PASS | PASS |
| 44 | `application/views/master/background_web.php` | `app/Views/ci3/master/background_web.php` | `master__background_web` | browser page | PASS | PASS | PASS | PASS |
| 45 | `application/views/master/books.php` | `app/Views/ci3/master/books.php` | `master__books` | browser page | PASS | PASS | PASS | PASS |
| 46 | `application/views/master/branch.php` | `app/Views/ci3/master/branch.php` | `master__branch` | browser page | PASS | PASS | PASS | PASS |
| 47 | `application/views/master/branchtype.php` | `app/Views/ci3/master/branchtype.php` | `master__branchtype` | browser page | PASS | PASS | PASS | PASS |
| 48 | `application/views/master/brand.php` | `app/Views/ci3/master/brand.php` | `master__brand` | browser page | PASS | PASS | PASS | PASS |
| 49 | `application/views/master/condition.php` | `app/Views/ci3/master/condition.php` | `master__condition` | browser page | PASS | PASS | PASS | PASS |
| 50 | `application/views/master/contactlist.php` | `app/Views/ci3/master/contactlist.php` | `master__contactlist` | browser page | PASS | PASS | PASS | PASS |
| 51 | `application/views/master/ecit_menus.php` | `app/Views/ci3/master/ecit_menus.php` | `master__ecit_menus` | browser page | PASS | PASS | PASS | PASS |
| 52 | `application/views/master/edit_background.php` | `app/Views/ci3/master/edit_background.php` | `master__edit_background` | browser page | PASS | PASS | PASS | PASS |
| 53 | `application/views/master/edit_book.php` | `app/Views/ci3/master/edit_book.php` | `master__edit_book` | browser page | PASS | PASS | PASS | PASS |
| 54 | `application/views/master/edit_branch.php` | `app/Views/ci3/master/edit_branch.php` | `master__edit_branch` | browser page | PASS | PASS | PASS | PASS |
| 55 | `application/views/master/edit_branchtype.php` | `app/Views/ci3/master/edit_branchtype.php` | `master__edit_branchtype` | browser page | PASS | PASS | PASS | PASS |
| 56 | `application/views/master/edit_brand.php` | `app/Views/ci3/master/edit_brand.php` | `master__edit_brand` | browser page | PASS | PASS | PASS | PASS |
| 57 | `application/views/master/edit_condition.php` | `app/Views/ci3/master/edit_condition.php` | `master__edit_condition` | browser page | PASS | PASS | PASS | PASS |
| 58 | `application/views/master/edit_estimateprice.php` | `app/Views/ci3/master/edit_estimateprice.php` | `master__edit_estimateprice` | browser page | PASS | PASS | PASS | PASS |
| 59 | `application/views/master/edit_fixed.php` | `app/Views/ci3/master/edit_fixed.php` | `master__edit_fixed` | browser page | PASS | PASS | PASS | PASS |
| 60 | `application/views/master/edit_producttype.php` | `app/Views/ci3/master/edit_producttype.php` | `master__edit_producttype` | browser page | PASS | PASS | PASS | PASS |
| 61 | `application/views/master/edit_provider.php` | `app/Views/ci3/master/edit_provider.php` | `master__edit_provider` | browser page | PASS | PASS | PASS | PASS |
| 62 | `application/views/master/edit_statustype.php` | `app/Views/ci3/master/edit_statustype.php` | `master__edit_statustype` | browser page | PASS | PASS | PASS | PASS |
| 63 | `application/views/master/estimateprice.php` | `app/Views/ci3/master/estimateprice.php` | `master__estimateprice` | browser page | PASS | PASS | PASS | PASS |
| 64 | `application/views/master/fixed.php` | `app/Views/ci3/master/fixed.php` | `master__fixed` | browser page | PASS | PASS | PASS | PASS |
| 65 | `application/views/master/menus.php` | `app/Views/ci3/master/menus.php` | `master__menus` | browser page | PASS | PASS | PASS | PASS |
| 66 | `application/views/master/producttype.php` | `app/Views/ci3/master/producttype.php` | `master__producttype` | browser page | PASS | PASS | PASS | PASS |
| 67 | `application/views/master/provider.php` | `app/Views/ci3/master/provider.php` | `master__provider` | browser page | PASS | PASS | PASS | PASS |
| 68 | `application/views/master/statustype.php` | `app/Views/ci3/master/statustype.php` | `master__statustype` | browser page | PASS | PASS | PASS | PASS |
| 69 | `application/views/newPassword.php` | `app/Views/ci3/newPassword.php` | `newPassword` | browser page | PASS | PASS | PASS | PASS |
| 70 | `application/views/report.php` | `app/Views/ci3/report.php` | `report` | browser page | PASS | PASS | PASS | PASS |
| 71 | `application/views/report_in_progress_average.php` | `app/Views/ci3/report_in_progress_average.php` | `report_in_progress_average` | browser page | PASS | PASS | PASS | PASS |
| 72 | `application/views/report_in_progress_job.php` | `app/Views/ci3/report_in_progress_job.php` | `report_in_progress_job` | browser page | PASS | PASS | PASS | PASS |
| 73 | `application/views/report_job_byday.php` | `app/Views/ci3/report_job_byday.php` | `report_job_byday` | browser page | PASS | PASS | PASS | PASS |
| 74 | `application/views/report_job_pending.php` | `app/Views/ci3/report_job_pending.php` | `report_job_pending` | browser page | PASS | PASS | PASS | PASS |
| 75 | `application/views/report_total_job_pending.php` | `app/Views/ci3/report_total_job_pending.php` | `report_total_job_pending` | browser page | PASS | PASS | PASS | PASS |
| 76 | `application/views/th/contact.php` | `app/Views/ci3/th/contact.php` | `th__contact` | browser page | PASS | PASS | PASS | PASS |
| 77 | `application/views/th/track.php` | `app/Views/ci3/th/track.php` | `th__track` | browser page | PASS | PASS | PASS | PASS |
| 78 | `application/views/th/trackstatus.php` | `app/Views/ci3/th/trackstatus.php` | `th__trackstatus` | browser page | PASS | PASS | PASS | PASS |
| 79 | `application/views/tracking/add_order.php` | `app/Views/ci3/tracking/add_order.php` | `tracking__add_order` | browser page | PASS | PASS | PASS | PASS |
| 80 | `application/views/tracking/edit_order.php` | `app/Views/ci3/tracking/edit_order.php` | `tracking__edit_order` | browser page | PASS | PASS | PASS | PASS |
| 81 | `application/views/tracking/excel_report_tracking.php` | `app/Views/ci3/tracking/excel_report_tracking.php` | `tracking__excel_report_tracking` | export | PASS | PASS | PASS | PASS |
| 82 | `application/views/tracking/excel_reportsummary.php` | `app/Views/ci3/tracking/excel_reportsummary.php` | `tracking__excel_reportsummary` | export | PASS | PASS | PASS | PASS |
| 83 | `application/views/tracking/order.php` | `app/Views/ci3/tracking/order.php` | `tracking__order` | browser page | PASS | PASS | PASS | PASS |
| 84 | `application/views/tracking/print_order.php` | `app/Views/ci3/tracking/print_order.php` | `tracking__print_order` | browser page | PASS | PASS | PASS | PASS |
| 85 | `application/views/tracking/report_tracking_test.php` | `app/Views/ci3/tracking/report_tracking_test.php` | `tracking__report_tracking_test` | browser page | PASS | PASS | PASS | PASS |
| 86 | `application/views/tracking/reportsummary.php` | `app/Views/ci3/tracking/reportsummary.php` | `tracking__reportsummary` | browser page | PASS | PASS | PASS | PASS |
| 87 | `application/views/tracking/send_order.php` | `app/Views/ci3/tracking/send_order.php` | `tracking__send_order` | browser page | PASS | PASS | PASS | PASS |
| 88 | `application/views/tracking/show_price_upload_excel.php` | `app/Views/ci3/tracking/show_price_upload_excel.php` | `tracking__show_price_upload_excel` | browser page | PASS | PASS | PASS | PASS |
| 89 | `application/views/tracking/show_upload_excel.php` | `app/Views/ci3/tracking/show_upload_excel.php` | `tracking__show_upload_excel` | browser page | PASS | PASS | PASS | PASS |
| 90 | `application/views/tracking/show_upload_neworder_excel.php` | `app/Views/ci3/tracking/show_upload_neworder_excel.php` | `tracking__show_upload_neworder_excel` | browser page | PASS | PASS | PASS | PASS |
| 91 | `application/views/tracking/tracking.php` | `app/Views/ci3/tracking/tracking.php` | `tracking__tracking` | browser page | PASS | PASS | PASS | PASS |
| 92 | `application/views/tracking/tracking_completed.php` | `app/Views/ci3/tracking/tracking_completed.php` | `tracking__tracking_completed` | browser page | PASS | PASS | PASS | PASS |
| 93 | `application/views/tracking/trackingclose.php` | `app/Views/ci3/tracking/trackingclose.php` | `tracking__trackingclose` | browser page | PASS | PASS | PASS | PASS |
| 94 | `application/views/tracking/trackingrepair.php` | `app/Views/ci3/tracking/trackingrepair.php` | `tracking__trackingrepair` | browser page | PASS | PASS | PASS | PASS |
| 95 | `application/views/tracking/trackingreturn.php` | `app/Views/ci3/tracking/trackingreturn.php` | `tracking__trackingreturn` | browser page | PASS | PASS | PASS | PASS |
| 96 | `application/views/tracking/upload_excel.php` | `app/Views/ci3/tracking/upload_excel.php` | `tracking__upload_excel` | browser page | PASS | PASS | PASS | PASS |
| 97 | `application/views/tracking/upload_neworder_excel.php` | `app/Views/ci3/tracking/upload_neworder_excel.php` | `tracking__upload_neworder_excel` | browser page | PASS | PASS | PASS | PASS |
| 98 | `application/views/tracking/upload_price_excel.php` | `app/Views/ci3/tracking/upload_price_excel.php` | `tracking__upload_price_excel` | browser page | PASS | PASS | PASS | PASS |
| 99 | `application/views/users.php` | `app/Views/ci3/users.php` | `users` | browser page | PASS | PASS | PASS | PASS |
| 100 | `application/views/web/footer.php` | `app/Views/ci3/web/footer.php` | `web__footer` | composed partial | PASS | PASS | PASS | PASS |
| 101 | `application/views/web/header.php` | `app/Views/ci3/web/header.php` | `web__header` | composed partial | PASS | PASS | PASS | PASS |
| 102 | `application/views/web/header_th.php` | `app/Views/ci3/web/header_th.php` | `web__header_th` | composed partial | PASS | PASS | PASS | PASS |

รายละเอียด caller และ artifact ของแต่ละ scenario อยู่ใน `evidence/strict-parity/views/runtime-results.json` โดยไม่ใช้ route ซ้ำแทน partial, email, export หรือ error view

STATUS: DONE
