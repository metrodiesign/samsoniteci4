# CI3 <-> CI4 Route Map (wp03e-visual)

อ้างอิง repo: CI4 `samsoniteci4` branch `develop` (`app/Config/Routes.php`, `app/Controllers/`),
CI3 `samsoniteci3` pin `ee1c95e` (`application/config/routes.php`, `application/controllers/`) — read-only

Fixture ที่มีจริง: users 4 คน (`wp00c-admin` role 1, `wp00c-a`/`wp00c-b` role 2 คนละ branch + อีก 1),
orders 9, branches 2 — ใช้กำหนดคอลัมน์ "ข้อมูลที่ต้องมีก่อนถ่าย"

## 1. ตาราง main

| page-id | CI3 URL | CI4 URL | role | ข้อมูลที่ต้องมีก่อนถ่าย | ชนิด | หมายเหตุ |
|---|---|---|---|---|---|---|
| tracking-home-en | `/` | `/` | anonymous | ไม่ต้อง | form | CI3: default_controller=`track` (`application/config/routes.php:41`) -> `Track::index()`. CI4: `Routes.php:6` `Tracking::form`. alias CI4: `track`,`tracking` (`Routes.php:8,10`) |
| tracking-home-th | `/track_th` | `/track_th` | anonymous | ไม่ต้อง | form | CI3: default routing controller `Track_th::index()` (`Track_th.php:25`). CI4: `Routes.php:9` `Tracking::formThai`. alias CI4: `tracking-th` (`Routes.php:11`) |
| tracking-result-en | `/track/trackstatus/{trackId}` | `/tracking/{trackId}` | anonymous | order ที่มี trackId ตรงกับ 1 ใน 9 orders fixture | list | CI3 form POST ไป `track/trackstatus` จริง (`views/en/track.php:2`) route `rackstatus` เป็น dead route (ดูข้อ 2). CI4: `Routes.php:12` `Tracking::english` |
| tracking-result-th | `/track_th/trackstatus/{trackId}` | `/tracking-th/{trackId}` | anonymous | เหมือนข้างบน (TH) | list | CI3: `Track_th::trackstatus()` ไม่รับ `$trackID` เป็น param (`Track_th.php:39`) ใช้ POST `searchText` เท่านั้น ต่างจาก `Track::trackstatus($trackID='')`; ต้องยืนยันว่า GET ด้วย segment ใช้ได้จริงก่อนถ่าย — ดูข้อ 4. CI4: `Routes.php:13` `Tracking::thai` |
| rating-form | `/rating/{orderId}` | `/rating/{trackId}` | anonymous | order สถานะที่ให้ rate ได้ | form | **CI3 หน้านี้ broken**: `Rating::index()` เรียก `redirect(base_url())` เป็นบรรทัดแรกเสมอ (`Rating.php:24-25`) โค้ดส่วนที่เหลือ unreachable — เข้า URL นี้ใน CI3 จะเด้งไปหน้า track เสมอ ไม่ใช่หน้า rating จริง ดูข้อ 4 |
| contact-form-en | `/contact` | `/contact` | anonymous | ไม่ต้อง | form | CI3: `application/config/routes.php:226` -> `Contact::index()`. CI4: `Routes.php:17` |
| contact-form-th | `/contact_th` | `/contact-th` | anonymous | ไม่ต้อง | form | CI3: `routes.php:230`. CI4: `Routes.php:19` (alias `contact_th` ที่ `Routes.php:21`) |
| login | `/login` | `/login` | anonymous | ไม่ต้อง | form | CI3: default routing -> `Login::index()`. CI4: `Routes.php:91` |
| forgot-password | `/forgotPassword` | `/forgot-password` | anonymous | ไม่ต้อง | form | CI3: `routes.php:67` -> `Login::forgotPassword()`. CI4: `Routes.php:132` `PasswordReset::forgotForm` |
| reset-password | `/resetPasswordConfirmUser/{activationId}/{email}` | `/reset-password?token=...` | anonymous | token/activation id ที่ยัง valid — สร้างผ่าน flow จริงหรือ mock DB row | form | CI3: `routes.php:70` -> `Login::resetPasswordConfirmUser()`. CI4: `Routes.php:133` `PasswordReset::resetForm`; token รูปแบบเปลี่ยนจาก activation_id+email เป็น hex token 64 ตัว (`PasswordReset.php:41-45`) |
| dashboard | `/dashboard` | `/dashboard` | admin หรือ branch (any authenticated) | ไม่ต้อง | form | CI3: `routes.php:48` -> `User::index()`. CI4: `Routes.php:94` `Dashboard::index` |
| orders-new | `/Orders` | `/orders/new` | admin หรือ branch | ไม่ต้อง | form | CI3: `routes.php:141` -> `Order::Orders()`. CI4: `Routes.php:59` (alias เดิม `Orders` คงไว้ที่ `Routes.php:61`) |
| orders-edit | `/editOrdersOld/{id}` | `/orders/{id}` | admin หรือ branch (branch ถูก scope ด้วย branchID ของ order) | order 1 รายการที่ branch ของ user เข้าถึงได้ | form | CI3: `routes.php:144-145`. CI4: `Routes.php:66` `Order::editForm` |
| orders-print | `/OrderPrint/{id}` | `/orders/{id}/print` | admin หรือ branch | order 1 รายการ | print-export | CI3: `routes.php:146`. CI4: `Routes.php:68` `Order::print` |
| order-listing-status1 | `/ordersListing` | `/ordersListing` | admin หรือ branch | orders สถานะ 1 อย่างน้อย 1 รายการ | list | CI3: `routes.php:139-140`. CI4: `Routes.php:84` `Order::listing/1` (alias `sendorderListing` เหมือนกันที่ `Routes.php:85`, CI3 คู่กันที่ `routes.php:132-133`) |
| order-listing-status2 | `/TrackingListing` | `/TrackingListing` | admin หรือ branch | orders สถานะ 2 | list | CI3: `routes.php:117-118`. CI4: `Routes.php:86` |
| order-listing-status3 | `/TrackingcloseListing` | `/TrackingcloseListing` | admin หรือ branch | orders สถานะ 3 | list | CI3: `routes.php:120-121`. CI4: `Routes.php:87` |
| order-listing-status4 | `/TrackingreturnListing` | `/TrackingreturnListing` | admin หรือ branch | orders สถานะ 4 | list | CI3: `routes.php:123-124`. CI4: `Routes.php:88` |
| order-listing-status5 | `/TrackingcompleteListing` | `/TrackingcompleteListing` | admin หรือ branch | orders สถานะ 5 | list | CI3: `routes.php:126-127`. CI4: `Routes.php:89` |
| order-listing-status7 | `/TrackingCompletedListing` | `/TrackingCompletedListing` | admin หรือ branch | orders สถานะ 7 | list | CI3: `routes.php:129-130`. CI4: `Routes.php:90` |
| report-tracking-listing | `/ReportTrackingListing` | `/ReportTrackingListing` | admin หรือ branch | orders + report data | list | CI3: `routes.php:114-116`. CI4: `Routes.php:117` `Order::reportTrackingListing` |
| imports-status | `/UploadexcelListing` | `/imports/status` | admin หรือ branch | ไม่ต้อง (ว่างได้) | form | CI3: `routes.php:160`. CI4: `Routes.php:72` (alias เดิม `UploadexcelListing` ที่ `Routes.php:75`) |
| imports-price | `/UploadexcelpriceListing` | `/imports/price` | admin หรือ branch | ไม่ต้อง | form | CI3: `routes.php:260`. CI4: `Routes.php:78` (alias `UploadexcelpriceListing` ที่ `Routes.php:78` เดิม) |
| imports-new-order | `/UploadneworderexcelListing` | `/imports/new-order` | admin หรือ branch | ไม่ต้อง | form | CI3: `routes.php:164`. CI4: `Routes.php:81` |
| change-password | `/loadChangePass` | `/change-password` | admin หรือ branch | ไม่ต้อง | form | CI3: `routes.php:59`. CI4: `Routes.php:56` |
| login-history-own | `/login-history` | `/login-history` | admin หรือ branch | มี login record อย่างน้อย 1 ครั้ง | list | CI3: `routes.php:63`. CI4: `Routes.php:53` `Users::ownHistory` |
| users-history-of-user | `/login-history/{userId}` | `/users/{id}/history` | admin (ดู user อื่น ต้อง role 1 — `Users.php` ใช้ role/branch scope ผ่าน `store()->history`) | user id ที่มี login record | list | CI3: `routes.php:64-65`. CI4: `Routes.php:51` |
| contact-listing | `/contactListing` | `/contactListing` | admin หรือ branch | มี contact submission อย่างน้อย 1 | list | CI3: `routes.php:224-225`. CI4: `Routes.php:25` (alias `contact-list` ที่ `Routes.php:24`) |
| menu-listing | `/menuListing` | `/menu` | **admin เท่านั้น (role 1)** | ไม่ต้อง | form | CI3: `routes.php:234`. CI4: `Routes.php:35`; CI4 บังคับ `assertAdmin()` จริง (`Menu.php:81-85`) — ดูข้อ 4 เรื่อง role ต่างจาก CI3 |
| menu-edit | `/editMunuOld/{id}` | `/menu/{id}` | admin เท่านั้น | menu 1 รายการ | form | CI3: `routes.php:239-240`. CI4: `Routes.php:37` |
| background-listing | `/BackgroundListing` | `/backgrounds` | **admin เท่านั้น (role 1)** | ไม่ต้อง | form | CI3: `routes.php:247`. CI4: `Routes.php:39`; `Background.php:141-146` บังคับ role 1 จริง — ดูข้อ 4 |
| background-edit | `/editBackgroundOld/{id}` | `/backgrounds/{id}` | admin เท่านั้น | background 1 รายการ | form | CI3: `routes.php:251-252`. CI4: `Routes.php:41` |
| users-listing | `/userListing` | `/users` | admin หรือ branch | ไม่ต้อง | form | CI3: `routes.php:50-51`. CI4: `Routes.php:45`; ไม่มี `assertAdmin` ใน `Users.php` (เทียบ `MasterData.php:88-89` comment "open to every role") |
| users-edit | `/editOld/{id}` | `/users/{id}` | admin หรือ branch (scope ผ่าน `findAccessible`, `Users.php:20`) | user 1 รายการ | form | CI3: `routes.php:55-56`. CI4: `Routes.php:48` |
| master-branch-listing | `/branchListing` | `/master/branch` | admin หรือ branch (ทุก role เขียนได้ — `MasterData.php:88-89`) | branch fixture 2 รายการ | list | CI3: `routes.php:74-75`. CI4: `Routes.php:27`; `master_data.php:19` มีฟอร์ม create inline ในหน้าเดียวกัน — CI3 add-new แยกหน้า (ดูข้อ 3) |
| master-branch-edit | `/editBranchOld/{id}` | `/master/branch/{id}` | admin หรือ branch | branch 1 รายการ | form | CI3: `routes.php:79-80`. CI4: `Routes.php:29` |
| master-branchtype-listing | `/branchtypeListing` | `/master/branchtype` | admin หรือ branch | อย่างน้อย 1 branchtype | list | CI3: `routes.php:84-85`. CI4: `Routes.php:27` |
| master-branchtype-edit | `/editBranchtypeOld/{id}` | `/master/branchtype/{id}` | admin หรือ branch | 1 รายการ (มีรูปภาพประกอบ — field `branch_type_image`) | form | CI3: `routes.php:89-90`. CI4: `Routes.php:29`; อัปโหลดรูปผ่าน `branch-type-image/{name}` (`Routes.php:32`, asset ไม่นับเป็นหน้า) |
| master-statustype-listing | `/statustypeListing` | `/master/statustype` | admin หรือ branch | อย่างน้อย 1 statustype | list | CI3: `routes.php:94-95`. CI4: `Routes.php:27` |
| master-statustype-edit | `/editStatustypeOld/{id}` | `/master/statustype/{id}` | admin หรือ branch | 1 รายการ | form | CI3: `routes.php:99-100`. CI4: `Routes.php:29` |
| master-producttype-listing | `/producttypeListing` | `/master/producttype` | admin หรือ branch | อย่างน้อย 1 producttype | list | CI3: `routes.php:104-105`. CI4: `Routes.php:27` |
| master-producttype-edit | `/editProducttypeOld/{id}` | `/master/producttype/{id}` | admin หรือ branch | 1 รายการ | form | CI3: `routes.php:109-110`. CI4: `Routes.php:29` |
| master-book-listing | `/bookListing` | `/bookListing` | admin หรือ branch (route `bookListing` มี filter เพิ่ม `web-auth`) | book fixture อย่างน้อย 1 | list | CI3: `routes.php:150-151`. CI4: `Routes.php:33-34` (alias `master/book` ก็ใช้ได้ผ่าน `Routes.php:27`) |
| master-book-edit | `/editBookOld/{id}` | `/master/book/{id}` | admin หรือ branch | 1 รายการ | form | CI3: `routes.php:155-156`. CI4: `Routes.php:29` |
| master-brand-listing | `/brandListing` | `/master/brand` | admin หรือ branch | อย่างน้อย 1 brand | list | CI3: `routes.php:168-169`. CI4: `Routes.php:27` |
| master-brand-edit | `/editBrandOld/{id}` | `/master/brand/{id}` | admin หรือ branch | 1 รายการ | form | CI3: `routes.php:173-174`. CI4: `Routes.php:29` |
| master-condition-listing | `/conditionListing` | `/master/condition` | admin หรือ branch | อย่างน้อย 1 condition | list | CI3: `routes.php:178-179`. CI4: `Routes.php:27` |
| master-condition-edit | `/editConditionOld/{id}` | `/master/condition/{id}` | admin หรือ branch | 1 รายการ | form | CI3: `routes.php:183-184`. CI4: `Routes.php:29` |
| master-estimateprice-listing | `/estimatepriceListing` | `/master/estimateprice` | admin หรือ branch | อย่างน้อย 1 estimateprice | list | CI3: `routes.php:188-189`. CI4: `Routes.php:27` |
| master-estimateprice-edit | `/editEstimatepriceOld/{id}` | `/master/estimateprice/{id}` | admin หรือ branch | 1 รายการ | form | CI3: `routes.php:193-194`. CI4: `Routes.php:29` |
| master-fixed-listing | `/fixedListing` | `/master/fixed` | admin หรือ branch | อย่างน้อย 1 fixed | list | CI3: `routes.php:198-199`. CI4: `Routes.php:27` |
| master-fixed-edit | `/editFixedOld/{id}` | `/master/fixed/{id}` | admin หรือ branch | 1 รายการ | form | CI3: `routes.php:203-204`. CI4: `Routes.php:29` |
| master-provider-listing | `/providerListing` | `/master/provider` | admin หรือ branch | อย่างน้อย 1 provider | list | CI3: `routes.php:208-209`. CI4: `Routes.php:27` |
| master-provider-edit | `/editProviderOld/{id}` | `/master/provider/{id}` | admin หรือ branch | 1 รายการ | form | CI3: `routes.php:213-214`. CI4: `Routes.php:29` |
| report-ratings | `/user/report` | `/user/report` | admin หรือ branch | ratings/orders data ในช่วงวันที่ default | list | CI3: default routing -> `User::report()` (`User.php:71`). CI4: `Routes.php:96` `Reports::matrix/ratings` |
| report-jobs-by-day | `/user/report_job_byday` | `/user/report_job_byday` | admin หรือ branch | orders data | list | CI3: `User.php:572`. CI4: `Routes.php:97` |
| report-pending | `/user/report_job_pending` | `/user/report_job_pending` | admin หรือ branch | orders สถานะค้าง | list | CI3: `User.php:669`. CI4: `Routes.php:98` |
| report-pending-total | `/user/report_total_job_pending` | `/user/report_total_job_pending` | admin หรือ branch | orders สถานะค้าง | list | CI3: `User.php:748`. CI4: `Routes.php:99` |
| report-in-progress-average | `/user/report_in_progress_average` | `/user/report_in_progress_average` | admin หรือ branch | orders ระหว่างดำเนินการ | list | CI3: `User.php:1260`. CI4: `Routes.php:100` |
| report-in-progress | `/user/report_in_progress_job` | `/user/report_in_progress_job` | admin หรือ branch | orders ระหว่างดำเนินการ + status filter | list | CI3: `User.php:1340`. CI4: `Routes.php:101` |
| report-summary | `/reportsummary` | `/reportsummary` | admin หรือ branch | orders data | list | CI3: `Order.php:1341`. CI4: `Routes.php:102-104` |
| export-ratings | `/user/excel_ratings` | `/user/excel_ratings` | admin หรือ branch | ratings data | print-export | CI3: `User.php:438`. CI4: `Routes.php:106-107` `Reports::legacyExport/ratings`; ไฟล์ผลลัพธ์เป็น `.xls` attachment ต้อง capture ผ่านหน้า intermediate หรือข้ามการถ่าย screenshot โดยตรง — ดูข้อ 4 |
| export-in-progress | `/user/excel_in_progress_job` | `/user/excel_in_progress_job` | admin หรือ branch | orders ระหว่างดำเนินการ | print-export | CI3: `User.php:1445` (default routing, ไม่มี key ใน `$route` array). CI4: `Routes.php:108-109` |
| export-tracking | `/Order/excel_report` | `/Order/excel_report` | admin หรือ branch | orders data | print-export | CI3: `Order.php:1181`. CI4: `Routes.php:110-111` |
| export-summary | `/Order/excel_report_sum` | `/Order/excel_report_sum` | admin หรือ branch | orders data | print-export | CI3: `Order.php:1257`. CI4: `Routes.php:112-113` |

## 2. รายการที่ตัดออกไม่ถ่าย

- `health` (`Routes.php:7`) — health check ตอบ JSON ล้วน ไม่มี HTML
- error/404 page ของ framework ทั้งสองฝั่ง: CI4 default exception page, CI3 `Error.php` (`404_override`, `routes.php:42`)
- CI3 `access.php`, `email/resetPassword.php` — dead view ยืนยันแล้วก่อนหน้านี้ (ตามบริบทที่ได้รับ)
- CI3 `rackstatus`/`rackstatus/{id}` และ `rackstatus_th`/`rackstatus_th/{id}` (`routes.php:218-222`) — ชี้ไป `Track::rackstatus()` / `Track_th::rackstatus()` ซึ่งไม่มี method นี้อยู่จริงในทั้งสองคอนโทรลเลอร์ (มีแต่ `trackstatus()`) เป็น broken/dead route จริง เว็บใช้ `track/trackstatus` ตรงผ่าน default routing (ยืนยันจาก `views/en/track.php:2`)
- JSON/AJAX action endpoint ที่ไม่มี HTML เป็นของตัวเอง: `api/branches`, `api/books`, `users/email-exists` (`Routes.php:54-55,47`), `password-reset/csrf`, `password-reset/request`, `password-reset/complete` (`Routes.php:134-136`), CI3 `get_list_branch/{id}`, `get_list_book/{id}`, `get_list_branchshort/{id}`, `get_list_bookshort/{id}` (`User.php:1198-1232`)
- asset/binary endpoint: `branch-type-image/{name}`, `background-image/{name}`, `order-image/{name}` (`Routes.php:32,44,69`), `imports/file/{name}` (`Routes.php:71`)
- POST-only action ที่ไม่มี view เป็นของตัวเอง (redirect กลับหน้าเดิมเสมอ): `sendorderUpdate`, `sendorderUpdateStatus`, `sendorder_deliver` (`Routes.php:63-65`), `orders/{id}/delete`, `master/{type}/{id}/delete`, `users/{id}/delete`, `backgrounds/{id}/delete` และคู่ CI3 `deleteOrders`, `deleteBranch`/`deleteBranchtype`/ฯลฯ, `deleteUser`, `deleteBackground`
- CI3 `resetPasswordUser` (POST, `routes.php:68`) และ `createPasswordUser` (POST, `routes.php:72`) — action เท่านั้น ไม่มี view ของตัวเอง เป็นตัวส่งของฟอร์ม `forgot-password`/`reset-password`

## 3. หน้าที่ไม่มีคู่ (unpaired)

CI3 มีแต่ CI4 ไม่มี (URL แยกหน้า แต่ CI4 รวมเข้าไปในหน้า listing เดียวกันแล้ว — ฟอร์ม create inline ยืนยันที่ `master_data.php:19`, `users.php:12-18`, `background.php:10`, `menu.php:12-17`):

- `/BranchNew` (`routes.php:76`), `/add_new_branchtype` (`routes.php:86`), `/add_new_statustype` (`routes.php:96`), `/add_new_producttype` (`routes.php:106`), `/BookNew` (`routes.php:152`), `/add_new_brand` (`routes.php:170`), `/add_new_condition` (`routes.php:180`), `/add_new_estimateprice` (`routes.php:190`), `/add_new_fixed` (`routes.php:200`), `/add_new_provider` (`routes.php:210`) — ฟอร์ม add-new แยกหน้าของ 10 master-data entity
- `/addNew` (users, `routes.php:52`) — ฟอร์ม add-new user แยกหน้า
- `/addNewMenu` (`routes.php:236`) — ฟอร์ม add-new menu แยกหน้า
- `/BackgroundNew` (`routes.php:249`) — ฟอร์ม add-new background แยกหน้า
- `/welcome` — `Welcome::index()` (`welcome.php`) `isLoggedIn()` ถูก comment ปิดไว้ (`welcome.php:9`) จึงเข้าถึงได้แบบ anonymous จริง แต่ render หน้า placeholder "welcome_message" ที่ไม่เกี่ยวกับ business flow ใด ๆ — ไม่มี route คู่กันใน CI4
- `/ReportTrackingListingTest` (`routes.php:264-266`) — ดูเหมือน route ทดสอบซ้ำของ `ReportTrackingListing` ไม่มีใน CI4 `Routes.php`

CI4 มีแต่ CI3 ไม่มี: ไม่พบ — ทุก route ใน CI4 ที่เหลือ (นอกเหนือจากหัวข้อ 2) มี CI3 URL เดิมคู่กันเสมอ (หลายเส้นทางคง URL string เดิม 100% เพื่อ backward-compat เช่น `ordersListing`, `TrackingListing`, `UploadexcelListing`)

## 4. จุดที่ต้องให้คนตัดสิน

1. **`rating-form` เทียบ CI3 ไม่ได้ตรง ๆ** — `Rating::index()` ใน CI3 (`Rating.php:24-25`) `redirect(base_url())` เป็นบรรทัดแรกเสมอ โค้ด business logic ด้านล่างเป็น dead code เข้าหน้านี้จาก URL จริงจะเห็นแค่หน้า track เท่านั้น ไม่ใช่ฟอร์ม rating — ต้องตัดสินว่าจะ (ก) ถ่ายเฉพาะ CI4 ฝั่งเดียวแล้วละ CI3 ไว้ หรือ (ข) ถือว่า "หน้า rating ของ CI3 ที่ผู้ใช้จริงเห็น" คือหน้า track (แปลว่าไม่มีอะไรให้เทียบ)
2. **role ของ `menu-listing`/`menu-edit`/`background-listing`/`background-edit` เข้มขึ้นใน CI4** — CI3 มี `isAdmin()` gate เดิมแต่เป็น dead code เสมอ (`isAdmin()` return true เฉพาะ `role < 1` ซึ่งไม่เกิดกับ user ที่ login แล้ว — ดู `BaseController.php:62-64`) ทำให้ทุก role เข้าหน้านี้ได้จริงใน CI3; ส่วน CI4 มี `assertAdmin()` บังคับ role 1 จริง (`Background.php:141-146`, `Menu.php:81-85`) ต้องตัดสินว่าจะถ่าย CI3 ด้วย role ไหน (any role ก็เห็นหน้าจริง) เทียบกับ CI4 ต้องใช้ `wp00c-admin` เท่านั้น — เป็นความต่างเชิง business ที่ควรบันทึกแยกจาก visual diff ทั่วไป ไม่ใช่บั๊กของ CI4
3. **export/print pages (`export-ratings`, `export-in-progress`, `export-tracking`, `export-summary`)** — ปลายทางเป็นไฟล์ `.xls` attachment (`Reports.php:122-124`) ไม่ใช่หน้า HTML ที่ค้างให้ screenshot ได้ตรง ๆ ต้องตัดสินว่าจะ (ก) ข้ามการถ่าย visual ของกลุ่มนี้ทั้งหมด (ข) ถ่ายหน้าที่มีปุ่ม export (เช่น orders listing) แทนตัวไฟล์ หรือ (ค) เปิดไฟล์ที่ดาวน์โหลดมาถ่ายเนื้อหาแยกอีกที
4. **`tracking-result-th` ต้องยืนยันว่า GET ด้วย segment ใช้งานได้จริงหรือไม่** — `Track_th::trackstatus()` ไม่รับ parameter `$trackID` เลย (ต่างจาก `Track::trackstatus($trackID='')` ที่รับ) จึงไม่แน่ใจว่า `/track_th/trackstatus/{id}` จะเซ็ต `$data_searchText` ได้ถูกต้องหรือ fallback ไป error — ควรทดสอบเข้าจริงก่อนถ่าย
5. **จำนวน state ต่อหน้าที่มี inline create form (master data 10 entity + users + menu + background)** — แต่ละหน้า listing มี 2 state ที่ต่างกันชัดเจนคือ list-only (row===null) กับ list-with-row-prefilled (edit mode, row!==null) ต้องตัดสินว่าจะถ่ายทั้ง 2 state ต่อ entity (เพิ่มอีก ~13 ภาพ) หรือถ่ายแค่ state listing ตามที่ระบุในตาราง main แล้วถือว่า edit state ครอบคลุมด้วย `master-*-edit` row ที่มีอยู่แล้ว
