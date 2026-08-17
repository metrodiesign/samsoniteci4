# Samsonite Tracking — Workflow Design สำหรับ CI4 (สกัดจาก CI3)

> Source: `samsoniteci3/application/` ทั้งระบบ — controllers 23 ตัว + models + views + routes ที่ CI3 pin `8dad4e331a90f5c6765954454910b451eb0ff8e5` (สกัดจาก working tree 2026-08-16 ตรวจแล้วว่าไม่อ้าง path ที่ถูกลบเลย); สัญญาของ pin อยู่ `outputs/reference/2026-08-17_ci3-reference-baseline_v1.md`
> Scope: workflow ของระบบครบทุก module ระดับ design พร้อม mapping ไป CI4 ต่อ workflow — ใช้คู่กับแผน migration `2026-08-09_ci3-to-ci4-upgrade-plan_v3.md`
> Generated: 2026-08-17

ชุดเอกสารนี้ตอบคำถาม "ระบบเดิมทำงานยังไงต่อ action และ CI4 ต้อง implement อะไร" — แต่ละ workflow บันทึกพฤติกรรมจริงรวม bug/quirk เป็น baseline สำหรับ Functional Parity (G-02) และปิดท้ายด้วยตาราง Mapping → CI4 จุดที่การ migrate ต้องเปลี่ยนพฤติกรรม (เปิด CSRF, เลิก temp table แชร์, แก้ fatal bug) ระบุเป็น decision point ไว้ในที่เดียวกัน

## ไฟล์ในชุดนี้

| # | Theme | Diagrams | ครอบคลุม controller | File |
|---|---|---|---|---|
| 1 | Auth + User | login seq, password reset seq, user CRUD activity | Login, User (ส่วน auth/CRUD/history) | [01-auth-user.md](01-auth-user.md) |
| 2 | Order + Tracking | status lifecycle, สร้าง order activity, bulk transition activity | Order (ส่วนงานซ่อม), Request_order_model | [02-order-tracking.md](02-order-tracking.md) |
| 3 | Excel Import | กลไกสองขั้นร่วม, confirm update status, confirm new order | Upload_excel | [03-excel-import.md](03-excel-import.md) |
| 4 | Public Website | tracking seq + activity, contact activity, rating seq | Track, Track_th, Contact, Contact_th, Rating, Error, welcome | [04-public-site.md](04-public-site.md) |
| 5 | Master Data + CMS | CRUD pattern activity + ตารางความต่าง 12 entity | Branch, Branchtype, Brand, Condition, Producttype, Provider, Statustype, Estimateprice, Fixed, Book, Menu, Background_web | [05-master-data.md](05-master-data.md) |
| 6 | Reports + Export | report filter + export activity + ตารางรายงานครบ | User (ส่วน report), Order (ส่วน report/export) | [06-reports.md](06-reports.md) |

## Coverage matrix — ทุก controller ไปที่ไหน

ทุก public method ของทุก controller ต้องตอบได้ว่าอยู่ § ไหนหรือถูกจำหน่ายเป็น RETIRE — ตารางนี้คือตัวตรวจความครบ

| Controller | Methods หลัก | อยู่ที่ | RETIRE / dead ที่ยืนยันแล้ว |
|---|---|---|---|
| Login | index, isLoggedIn, loginMe, forgotPassword, resetPasswordUser, resetPasswordConfirmUser, createPasswordUser | §1.1, §1.3 | `get_random_password` (internal, เรียกตรงได้แต่ return เปล่า) |
| User | userListing, addNew, addNewUser, checkEmailExists, editOld, editUser, deleteUser, loadChangePass, changePassword, loginHistoy, contactListing, get_list_branch/book/branchshort/bookshort, logout (สืบทอด) | §1.4 + ตาราง flow ประกอบ | `pageNotFound` (มี route แต่ 404 override ชี้ `error`) |
| User (reports) | index (dashboard), report, excel_ratings, report_job_byday, report_job_pending, report_total_job_pending, report_in_progress_average, report_in_progress_job, excel_in_progress_job | §6.2 | — |
| Order | ordersListing, sendorderListing, TrackingListing, TrackingcloseListing, TrackingreturnListing, TrackingcompleteListing, TrackingCompletedListing, Orders, addNewOrders, sendorderUpdate, sendorderUpdateStatus, sendorder_deliver, OrderPrint, editOrdersOld, editOrders, deleteOrders, do_upload_multi, get_orderIDShow | §2.1–2.4 | `pageNotFound`, `excel_report_sum` (ไม่มี route/caller), `ReportTrackingListingTest` (ไม่มี UI ชี้ + query พังเมื่อไม่เลือก status) |
| Order (reports) | ReportTrackingListing, reportsummary, excel_report | §6.2 | — |
| Upload_excel | Listing 3 หน้า + DataAdd/Confirm 3 คู่ | §3.1–3.3 | `pageNotFound` |
| Track / Track_th | index, trackstatus | §4.1–4.2 | `pageNotFound` ทั้งคู่; route `rackstatus`/`rackstatus_th` ชี้ method ที่ไม่มี (ตกไป 404) |
| Contact / Contact_th | index, addContact | §4.3 | `pageNotFound` ทั้งคู่ |
| Rating | addRating | §4.4 | `index` (redirect ทันที — หน้า rating ลูกค้าถูกปิด), `pageNotFound` |
| Error / welcome | index, isLoggedIn / index | §4 ตาราง Error/welcome | `Welcome::index` ทั้ง controller |
| Book | index, bookListing, BookNew, addNewBook, editBookOld, editBook, deleteBook | §5.2 | `pageNotFound`; route `bookListing/(:num)` ชี้ `order/bookListing` ที่ไม่มี |
| Branch, Branchtype, Brand, Condition, Producttype, Provider, Statustype, Estimateprice, Fixed | listing, add form, add POST, edit form, edit POST, delete | §5.1–5.2 | `pageNotFound` ทุกตัว; `getX()` dead ใน model 5 ตัว (ตัวจริงอยู่ `request_order_model`) |
| Menu | menuListing, addNewMenu, addMenu, editMunuOld, editMenu | §5.3 | สำเนาหลงไม่มี route 7 ตัว: index, deleteUser, loadChangePass, changePassword, loginHistoy, get_list_branch, get_list_book |
| Background_web | BackgroundListing, BackgroundNew, addBackground, editBackgroundOld, editBackground, deleteBackground | §5.4 | UI ไม่มีปุ่มเข้า BackgroundNew/delete (เข้า URL ตรงได้) |

รายการ RETIRE ทั้งหมดต้องปิดด้วย Function ID + retirement proof ในเอกสาร `2026-08-17_function-disposition-evidence_v2.md` (G-09) — ตารางนี้เป็น index ไม่ใช่ตัวแทนหลักฐาน

## Cross-cutting — พฤติกรรมร่วมที่ทุก module อ้าง

เขียนครั้งเดียวที่นี่ ไฟล์ theme อ้างกลับมา ไม่ทำซ้ำ 23 รอบ

| หัวข้อ | ข้อเท็จจริง CI3 | ทิศทาง CI4 |
|---|---|---|
| Auth gate | `isLoggedIn` (session) เป็น gate เดียวที่ทำงาน; `isAdmin`/`isTicketter` เป็น dead gate — role 1/2/3 เท่ากันหมด (รายละเอียด §1.2) | auth Filter ต่อ route group; ไม่ port dead gate |
| Data scope | `BranchID` ใน session กรองราย query ใน model — จุดที่ลืม = IDOR (edit/delete user, order actions) | scope ชั้นเดียวใน Model; จุด IDOR = decision record |
| CSRF / XSS | `csrf_protection = FALSE`, `global_xss_filtering = FALSE` ทั้งระบบ | เปิด CSRF filter (G-04) + regression ทุกฟอร์ม/AJAX (delete 13 endpoint + ฟอร์ม public) |
| SQL injection pattern | ยัด SQL ทั้งประโยคใน `db->select()` / LIKE ต่อสตริงดิบ — กระจาย `Request_order_model` ~30 method, ทุก master model, `User_model` (เรียกทุก page load ผ่าน sidebar) | Query Builder + binding ทั้งหมด — พื้นที่ regression ใหญ่สุด ตรึง result ต่อ method ด้วย test ก่อนแปลง |
| Temp table แชร์ | `temp_status_log` (public tracking) + `temp_updatestatus_*` 3 ใบ (excel) — `empty_table` ทั้งใบ แชร์ข้ามผู้ใช้ | per-request query / batch identifier — อนุมัติแล้วใน plan v3 §1 |
| Email | PHPMailer vendored ใน `lib/` (นอก application) SMTP config เป็น literal placeholder + CC hardcode — production อาจไม่เคยส่งสำเร็จ ต้อง verify ก่อนตรึง parity | Composer + `.env` |
| SMS | `cias_helper::sms()` curl thaibulksms ข้อความ 3 แบบ credential ใน helper | service + `.env` + log ผล; ข้อความคงคำต่อคำ |
| Excel lib | PHPExcel 1.8.x (2015, EOL) vendored ใน `lib/` | PhpSpreadsheet ผ่าน Composer (plan v3 research ระบุแล้ว) |
| Uploads | ทุกจุดใช้ `$_FILES` + `move_uploaded_file` ตรง เช็คนามสกุลจากชื่อไฟล์ เก็บใต้ web root ไฟล์เก่าไม่ลบ | UploadedFile + validate + นอก `public/` (CI4 ≥ 4.7.4 ตาม plan) |
| View wrapper | `loadViews` (admin header/footer), `load_web_Views`/`load_web_th_Views` (public EN/TH), `load_order_Views`, `load_print_Views`/`loadViewspeint` (เดี่ยว) | CI4 layout ต่อกลุ่มตามการจัดชั้นในแถวนี้ — mapping view รายไฟล์เป็นงานของ spec ต่อ slice (view 117 ไฟล์ตาม plan v3 §1) |
| Routes | 178 route ใน `routes.php` + default routing ที่เปิด method เป็น URL อัตโนมัติ (AJAX หลายตัวพึ่งทางนี้) | explicit routes ทั้งหมด — ห้ามเปิด Auto Routing Legacy (plan v3 non-goal); AJAX endpoint ที่ไม่มี route ต้องได้ explicit route ใหม่ครบ (ไล่จาก §): `login/get_random_password` (ไม่สร้าง), `user/report*`, `user/excel_*`, `user/get_list_*`, `order/do_upload_multi`, `order/excel_report`, `order/get_orderIDShow` |
| Route bugs เดิม | `rackstatus*` ชี้ method ไม่มี, `bookListing/(:num)` ชี้ผิด controller, `ReportTrackingListing`/`reportsummary` ใช้ `$1/$1` | ไม่ replicate route ที่พัง — บันทึก decision + ทดสอบว่า URL เดิมที่พังตกไป 404 เหมือนเดิม |

## วิธีใช้ชุดเอกสารนี้

1. **ตอนเขียน spec ต่อ slice** (plan v3 ใช้ vertical slice + route-level strangler): เปิดไฟล์ theme ของ module นั้น — ตาราง contract + diagram คือ acceptance baseline, ตาราง Mapping → CI4 คือโครง implement, หัวข้อ decision ใน Notes ต้อง resolve ก่อนเริ่มเขียนโค้ด
2. **ตอนเขียน parity test**: ใช้ตาราง failure path + quirk เป็น test case โดยตรง — quirk ที่ตัดสินใจคงไว้ต้องมี test ตรึงเท่ากับ happy path
3. **ตอนปิด G-09**: coverage matrix ด้านบนต้องตรงกับ disposition ใน `2026-08-17_function-disposition-evidence_v2.md` — พบไม่ตรงให้ปรับ disposition doc พร้อมหลักฐาน ไม่ใช่แก้ตารางนี้เงียบ ๆ
4. **ความสัมพันธ์กับ legacy report**: `2026-08-09_legacy-system-report_v3.md` เป็นภาพรวมระบบ + diagram หลัก 7 ตัว — ชุดนี้ลงลึกต่อ action ที่ระดับ implement ได้ ถ้าขัดกันให้ถือ file:line ในชุดนี้ (สกัดใหม่ 2026-08-16) แล้วบันทึกข้อขัดแย้งลง work history

## Notes

- ตัวเลขบรรทัด (file:line) อ้างอิง `samsoniteci3` ที่ CI3 pin `8dad4e33` — เปลี่ยน pin เมื่อไรต้อง re-verify ก่อนใช้ (ยืนยัน pin ด้วย `git -C $CI3_SOURCE_ROOT rev-parse HEAD`)
- Diagram ทุก block ผ่าน `node scripts/check-mermaid.mjs` แล้ว
- schema จริงของ DB ยืนยันไม่ได้จาก repo (ไม่มีไฟล์ `.sql`) — จุดที่เอกสารระบุ "ตรวจ schema ตอน baseline" เป็นงานของ phase discovery ใน plan v3

**Render**: GitHub / Obsidian / VS Code Mermaid
