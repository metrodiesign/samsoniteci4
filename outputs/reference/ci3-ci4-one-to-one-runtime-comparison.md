# ผลเปรียบเทียบ CI3 → CI4 แบบ one-to-one จาก runtime จริง

รายงานนี้ fail closed: หลักฐาน direct-render, `page.setContent()` และ target-file existence ไม่ถูกนับเป็น runtime proof.

## สรุป

| รายการ | ผล |
|---|---:|
| tracked templates | 108 |
| excluded with evidence | 10 |
| runtime-required | 98 |
| unique source mappings | 98 |
| unique target mappings | 98 |
| actual CI3 caller traces | 98/98 |
| actual CI4 caller traces | 98/98 |
| uncalled source with evidence | 0 |
| caller reconciliation | 98/98 |
| runtime comparisons executed | 98/98 |
| DOM comparisons executed | 98/98 |
| generic collapsed mappings | 0 |
| Playwright actual route/state | 88 |
| Playwright screenshots | 352 |
| Playwright interaction statuses | {'PASS': 292, 'NOT_APPLICABLE': 60} |
| Playwright console events | 0 |
| Playwright failed requests | 0 |
| Playwright reused screenshot paths | 0 |
| OpenAI Browser covered templates | 0/93 |
| OpenAI Browser actual route/state | 0 |
| OpenAI Browser screenshots | 0 |
| required desktop screenshots | 0 |
| required mobile screenshots | 0 |
| unclassified viewport screenshots | 0 |
| reused/stale screenshots | 0 |
| console errors | 0 |
| failed network requests | BLOCKED: in-app Browser backend did not expose request failures |
| overall PASS / FAIL / BLOCKED | 5 / 0 / 93 |

## Gate verdict

- OpenAI Browser evidence coverage is incomplete
- one or more comparisons are not PASS

## Mutation checks

| mutation | gate result |
|---|---|
| `duplicate_target` | PASS |
| `generic_view` | PASS |
| `uncalled_target` | PASS |
| `dom_node_class_text` | PASS |
| `reused_screenshot` | PASS |
| `origin_tamper` | PASS |

## หลักฐานที่ถูกปฏิเสธ

| path | เหตุผล |
|---|---|
| `scripts/render-strict-presentation-scenario.php` | direct template include/eval |
| `scripts/capture-strict-presentation-scenarios.mjs` | page.setContent() |
| `evidence/strict-parity/views/runtime-results.json` | derived from direct render and page.setContent() |

## Generic views ที่ production ยังเรียก

| view | caller candidates |
|---|---|
| `logout_bridge` | `app/Controllers/Login.php:86` |
| `orders` | `app/Controllers/Order.php:118` |
| `rating` | `app/Controllers/Rating.php:30` |

## รายการ 98 templates

| # | source | target | actual caller | runtime | DOM | interaction | visual | OpenAI Browser | overall |
|---:|---|---|---|---|---|---|---|---|---|
| 1 | `application/views/404.php` | `app/Views/ci3/404.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 2 | `application/views/addNew.php` | `app/Views/ci3/addNew.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 3 | `application/views/changePassword.php` | `app/Views/ci3/changePassword.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 4 | `application/views/dashboard.php` | `app/Views/ci3/dashboard.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 5 | `application/views/editOld.php` | `app/Views/ci3/editOld.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 6 | `application/views/en/contact.php` | `app/Views/ci3/en/contact.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 7 | `application/views/en/track.php` | `app/Views/ci3/en/track.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 8 | `application/views/en/trackstatus.php` | `app/Views/ci3/en/trackstatus.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 9 | `application/views/errors/cli/error_404.php` | `app/Views/ci3/errors/cli/error_404.php` | PASS | PASS | PASS | NOT_APPLICABLE | NOT_APPLICABLE | NOT_APPLICABLE | PASS |
| 10 | `application/views/errors/cli/error_db.php` | `app/Views/ci3/errors/cli/error_db.php` | PASS | PASS | PASS | NOT_APPLICABLE | NOT_APPLICABLE | NOT_APPLICABLE | PASS |
| 11 | `application/views/errors/cli/error_exception.php` | `app/Views/ci3/errors/cli/error_exception.php` | PASS | PASS | PASS | NOT_APPLICABLE | NOT_APPLICABLE | NOT_APPLICABLE | PASS |
| 12 | `application/views/errors/cli/error_general.php` | `app/Views/ci3/errors/cli/error_general.php` | PASS | PASS | PASS | NOT_APPLICABLE | NOT_APPLICABLE | NOT_APPLICABLE | PASS |
| 13 | `application/views/errors/cli/error_php.php` | `app/Views/ci3/errors/cli/error_php.php` | PASS | PASS | PASS | NOT_APPLICABLE | NOT_APPLICABLE | NOT_APPLICABLE | PASS |
| 14 | `application/views/errors/html/error_404.php` | `app/Views/ci3/errors/html/error_404.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 15 | `application/views/errors/html/error_db.php` | `app/Views/ci3/errors/html/error_db.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 16 | `application/views/errors/html/error_exception.php` | `app/Views/ci3/errors/html/error_exception.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 17 | `application/views/errors/html/error_general.php` | `app/Views/ci3/errors/html/error_general.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 18 | `application/views/errors/html/error_php.php` | `app/Views/ci3/errors/html/error_php.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 19 | `application/views/excel_in_progress_job.php` | `app/Views/ci3/excel_in_progress_job.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 20 | `application/views/excel_report_rating.php` | `app/Views/ci3/excel_report_rating.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 21 | `application/views/forgotPassword.php` | `app/Views/ci3/forgotPassword.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 22 | `application/views/includes/footer.php` | `app/Views/ci3/includes/footer.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 23 | `application/views/includes/footer_order.php` | `app/Views/ci3/includes/footer_order.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 24 | `application/views/includes/header.php` | `app/Views/ci3/includes/header.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 25 | `application/views/includes/header_order.php` | `app/Views/ci3/includes/header_order.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 26 | `application/views/login.php` | `app/Views/ci3/login.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 27 | `application/views/loginHistory.php` | `app/Views/ci3/loginHistory.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 28 | `application/views/master/add_background.php` | `app/Views/ci3/master/add_background.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 29 | `application/views/master/add_book.php` | `app/Views/ci3/master/add_book.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 30 | `application/views/master/add_branch.php` | `app/Views/ci3/master/add_branch.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 31 | `application/views/master/add_branchtype.php` | `app/Views/ci3/master/add_branchtype.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 32 | `application/views/master/add_brand.php` | `app/Views/ci3/master/add_brand.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 33 | `application/views/master/add_condition.php` | `app/Views/ci3/master/add_condition.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 34 | `application/views/master/add_estimateprice.php` | `app/Views/ci3/master/add_estimateprice.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 35 | `application/views/master/add_fixed.php` | `app/Views/ci3/master/add_fixed.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 36 | `application/views/master/add_menus.php` | `app/Views/ci3/master/add_menus.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 37 | `application/views/master/add_producttype.php` | `app/Views/ci3/master/add_producttype.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 38 | `application/views/master/add_provider.php` | `app/Views/ci3/master/add_provider.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 39 | `application/views/master/add_statustype.php` | `app/Views/ci3/master/add_statustype.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 40 | `application/views/master/background_web.php` | `app/Views/ci3/master/background_web.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 41 | `application/views/master/books.php` | `app/Views/ci3/master/books.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 42 | `application/views/master/branch.php` | `app/Views/ci3/master/branch.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 43 | `application/views/master/branchtype.php` | `app/Views/ci3/master/branchtype.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 44 | `application/views/master/brand.php` | `app/Views/ci3/master/brand.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 45 | `application/views/master/condition.php` | `app/Views/ci3/master/condition.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 46 | `application/views/master/contactlist.php` | `app/Views/ci3/master/contactlist.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 47 | `application/views/master/ecit_menus.php` | `app/Views/ci3/master/ecit_menus.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 48 | `application/views/master/edit_background.php` | `app/Views/ci3/master/edit_background.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 49 | `application/views/master/edit_book.php` | `app/Views/ci3/master/edit_book.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 50 | `application/views/master/edit_branch.php` | `app/Views/ci3/master/edit_branch.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 51 | `application/views/master/edit_branchtype.php` | `app/Views/ci3/master/edit_branchtype.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 52 | `application/views/master/edit_brand.php` | `app/Views/ci3/master/edit_brand.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 53 | `application/views/master/edit_condition.php` | `app/Views/ci3/master/edit_condition.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 54 | `application/views/master/edit_estimateprice.php` | `app/Views/ci3/master/edit_estimateprice.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 55 | `application/views/master/edit_fixed.php` | `app/Views/ci3/master/edit_fixed.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 56 | `application/views/master/edit_producttype.php` | `app/Views/ci3/master/edit_producttype.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 57 | `application/views/master/edit_provider.php` | `app/Views/ci3/master/edit_provider.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 58 | `application/views/master/edit_statustype.php` | `app/Views/ci3/master/edit_statustype.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 59 | `application/views/master/estimateprice.php` | `app/Views/ci3/master/estimateprice.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 60 | `application/views/master/fixed.php` | `app/Views/ci3/master/fixed.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 61 | `application/views/master/menus.php` | `app/Views/ci3/master/menus.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 62 | `application/views/master/producttype.php` | `app/Views/ci3/master/producttype.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 63 | `application/views/master/provider.php` | `app/Views/ci3/master/provider.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 64 | `application/views/master/statustype.php` | `app/Views/ci3/master/statustype.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 65 | `application/views/newPassword.php` | `app/Views/ci3/newPassword.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 66 | `application/views/report.php` | `app/Views/ci3/report.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 67 | `application/views/report_in_progress_average.php` | `app/Views/ci3/report_in_progress_average.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 68 | `application/views/report_in_progress_job.php` | `app/Views/ci3/report_in_progress_job.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 69 | `application/views/report_job_byday.php` | `app/Views/ci3/report_job_byday.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 70 | `application/views/report_job_pending.php` | `app/Views/ci3/report_job_pending.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 71 | `application/views/report_total_job_pending.php` | `app/Views/ci3/report_total_job_pending.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 72 | `application/views/th/contact.php` | `app/Views/ci3/th/contact.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 73 | `application/views/th/track.php` | `app/Views/ci3/th/track.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 74 | `application/views/th/trackstatus.php` | `app/Views/ci3/th/trackstatus.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 75 | `application/views/tracking/add_order.php` | `app/Views/ci3/tracking/add_order.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 76 | `application/views/tracking/edit_order.php` | `app/Views/ci3/tracking/edit_order.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 77 | `application/views/tracking/excel_report_tracking.php` | `app/Views/ci3/tracking/excel_report_tracking.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 78 | `application/views/tracking/excel_reportsummary.php` | `app/Views/ci3/tracking/excel_reportsummary.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 79 | `application/views/tracking/order.php` | `app/Views/ci3/tracking/order.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 80 | `application/views/tracking/print_order.php` | `app/Views/ci3/tracking/print_order.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 81 | `application/views/tracking/report_tracking_test.php` | `app/Views/ci3/tracking/report_tracking_test.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 82 | `application/views/tracking/reportsummary.php` | `app/Views/ci3/tracking/reportsummary.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 83 | `application/views/tracking/send_order.php` | `app/Views/ci3/tracking/send_order.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 84 | `application/views/tracking/show_price_upload_excel.php` | `app/Views/ci3/tracking/show_price_upload_excel.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 85 | `application/views/tracking/show_upload_excel.php` | `app/Views/ci3/tracking/show_upload_excel.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 86 | `application/views/tracking/show_upload_neworder_excel.php` | `app/Views/ci3/tracking/show_upload_neworder_excel.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 87 | `application/views/tracking/tracking.php` | `app/Views/ci3/tracking/tracking.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 88 | `application/views/tracking/tracking_completed.php` | `app/Views/ci3/tracking/tracking_completed.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 89 | `application/views/tracking/trackingclose.php` | `app/Views/ci3/tracking/trackingclose.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 90 | `application/views/tracking/trackingrepair.php` | `app/Views/ci3/tracking/trackingrepair.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 91 | `application/views/tracking/trackingreturn.php` | `app/Views/ci3/tracking/trackingreturn.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 92 | `application/views/tracking/upload_excel.php` | `app/Views/ci3/tracking/upload_excel.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 93 | `application/views/tracking/upload_neworder_excel.php` | `app/Views/ci3/tracking/upload_neworder_excel.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 94 | `application/views/tracking/upload_price_excel.php` | `app/Views/ci3/tracking/upload_price_excel.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 95 | `application/views/users.php` | `app/Views/ci3/users.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 96 | `application/views/web/footer.php` | `app/Views/ci3/web/footer.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 97 | `application/views/web/header.php` | `app/Views/ci3/web/header.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |
| 98 | `application/views/web/header_th.php` | `app/Views/ci3/web/header_th.php` | PASS | PASS | PASS | PASS | PASS | BLOCKED | BLOCKED |

## คำสั่ง

```bash
python3 scripts/run-one-to-one-runtime-comparison.py --trace-manifest evidence/runtime-comparison/17841aef-de1a-43fc-a433-9552ff73325c/runtime-traces.json --playwright-evidence evidence/runtime-comparison/17841aef-de1a-43fc-a433-9552ff73325c/automated-browser-results.json --verification-results evidence/runtime-comparison/17841aef-de1a-43fc-a433-9552ff73325c/verification/verification-command-results.json --self-test
```

Exit code: `1`

## Baseline regression commands

| command | exit code | evidence |
|---|---:|---|
| `composer test` | `2` | `evidence/runtime-comparison/17841aef-de1a-43fc-a433-9552ff73325c/verification/composer-test.log` (`ae3a3273c66d9738b6df89853ca77c05a9e50ff80a3521bbb29c87f29ffa7d58`) |
| `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` | `0` | `evidence/runtime-comparison/17841aef-de1a-43fc-a433-9552ff73325c/verification/phpstan.log` (`16b52437dd86eb4818b495b2037c476aa750428f3be2f1626136f5a57f98609a`) |
| `bash scripts/ci-check.sh` | `2` | `evidence/runtime-comparison/17841aef-de1a-43fc-a433-9552ff73325c/verification/ci-check.log` (`9c0725a20ee87f7ddf69185893f8c724a9bd4eb33ea2ef8915ce546ab071ed7d`) |
| `python3 -m unittest tests.wp00c.test_runtime_dom_comparator` | `0` | `evidence/runtime-comparison/17841aef-de1a-43fc-a433-9552ff73325c/verification/dom-comparator-tests.log` (`ea28bd24d31722a8f72d46319b24463bf89d88e060729840934798cdf9a610cc`) |
| `git diff --check` | `0` | `evidence/runtime-comparison/17841aef-de1a-43fc-a433-9552ff73325c/verification/git-diff-check.log` (`e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`) |

STATUS: BLOCKED
