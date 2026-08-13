# Samsonite Tracking — Primary-source research สำหรับย้าย CI3 ไป CI4 บน PHP 8.5

เอกสารนี้ตรวจแผนอัปเกรดฉบับ v2 เทียบกับ source code ปัจจุบันและเอกสารทางการ ณ 2026-08-09. เนื้อหาแยกข้อเท็จจริง ข้ออนุมาน คำแนะนำ และคำถามเปิดเพื่อใช้ปรับรายงานหลักโดยตรวจย้อนกลับได้.

## การควบคุมเอกสาร

| รายการ | ค่า |
|---|---|
| วันที่ตรวจ | 2026-08-09 |
| ระบบต้นทาง | CodeIgniter 3.1.6 ใน working tree |
| เป้าหมาย | CodeIgniter 4.7.4 บน PHP 8.5.x |
| แหล่งข้อมูล | CodeIgniter, PHP, Composer, PhpSpreadsheet, MySQL และ source code ใน repo |
| ขอบเขต | Compatibility, architecture gap, security, data integrity, testing, deployment, dependency |
| ไม่ครอบคลุม | Production data, credential value, schema จริง, runtime traffic และ infrastructure ที่ยังไม่ได้รับ |

### ป้ายสถานะหลักฐาน

| ป้าย | ความหมาย |
|---|---|
| Confirmed fact | พบตรงใน primary source หรือ source code |
| Inference | ข้อสรุปจาก facts มากกว่าหนึ่งจุด ต้องยืนยันด้วย runtime หรือเจ้าของระบบ |
| Recommendation | การจัดการที่เสนอ ไม่ใช่พฤติกรรมที่ framework บังคับทั้งหมด |
| Open question | ข้อมูลขาดและมีผลต่อแบบย้ายหรือ estimate |

### วิธีตรวจซ้ำ local inventory

คำสั่งใช้เฉพาะ source/config ที่ไม่ใช่ credential file และไม่อ่าน `.env`:

```bash
rg -c '^\$route\[' application/config/routes.php
rg -o '\$this->input->(post|get)' application --glob '*.php'
rg -o '\$this->session->userdata' application --glob '*.php'
rg -o '\$this->form_validation' application --glob '*.php'
rg -n 'trans_(start|complete|begin|commit|rollback)' application --glob '*.php'
rg -n 'curl_close|create_function|mcrypt_|set_magic_quotes_runtime' application lib --glob '*.php'
```

ผลนับเป็น snapshot ของ dirty working tree วันที่ตรวจ ไม่ใช่ runtime coverage หรือ clean-commit baseline.

## สรุปผลวิจัย

| ID | ประเภท | ข้อค้นพบ | ผลต่อแผน |
|---|---|---|---|
| F-01 | Confirmed fact | CI4 เป็น rewrite และไม่ backward-compatible กับ CI3; เอกสารทางการให้เริ่ม CI4 project ใหม่แล้ว convert components | ห้ามวางแผนเป็น in-place framework replacement |
| F-02 | Confirmed fact | PHP 8.5 ต้องใช้ CI4 4.7.0 ขึ้นไป; CI4 4.7.4 ออก 2026-07-07 พร้อม security fixes | Baseline ควรเป็น 4.7.4 ไม่ใช่เพียงขั้นต่ำ 4.7.0 |
| F-03 | Confirmed fact | ระบบใช้ CI3 API หนาแน่น: 178 route declarations, 351 request-input calls, 94 session reads และ 158 validation calls | ต้องย้ายเป็น vertical slice พร้อม characterization tests |
| F-04 | Confirmed fact | CSRF ถูกปิดใน config และ auth/branch context ผูกกับ session กับ custom `BaseController` | Identity slice ต้องมี negative security tests ก่อน cutover |
| F-05 | Confirmed fact | Order create/status เขียน order และ status log แยก method; SMS เป็น side effect หลัง DB write | Transaction boundary กับ retry/idempotency ต้องออกแบบระดับ use case |
| F-06 | Confirmed fact | Public tracking และ Excel import ล้าง shared temp tables ก่อนใช้ | มี cross-request/cross-user contamination risk |
| F-07 | Confirmed fact | Tracking ID สร้างจาก `select_max()` แล้วบวกหนึ่ง | เกิด race ได้หาก concurrent create ไม่มี DB uniqueness/atomic allocation |
| F-08 | Confirmed fact | Bundled PHPExcel, PHPMailer 5.2.10 และ legacy libraries มี API ที่ removed/deprecated บน PHP รุ่นใหม่ | ห้าม copy `lib/` ทั้งก้อนไป target; audit caller แล้ว replace/delete |
| F-09 | Correction | ระบบมี transaction อยู่หลาย model แล้ว ไม่ใช่ “ไม่มี transaction” | งานคือขยาย boundary ให้ครอบ business operation และตรวจ error handling |
| F-10 | Correction | CI4 controller ไม่จำเป็นต้องคืน `ResponseInterface` ทุกกรณี; `return view()` คืน rendered content ได้ | Contract ควรระบุ response ตามชนิด endpoint ไม่บังคับ type เดียว |

แหล่งหลัก: [CI3 to CI4 Upgrade Guide](https://codeigniter.com/user_guide/installation/upgrade_4xx.html), [CI4 Requirements](https://codeigniter.com/user_guide/intro/requirements.html), [CI4 4.7.4 Changelog](https://codeigniter.com/user_guide/changelogs/v4.7.4.html), `system/core/CodeIgniter.php:58`, `application/config/routes.php:41-266`.

## 1. Compatibility baseline

### Confirmed facts

| Fact | Evidence |
|---|---|
| Working tree ใช้ CodeIgniter 3.1.6 | `system/core/CodeIgniter.php:58` |
| CI4 เป็น rewrite และไม่ backward-compatible; official guide ใช้คำว่า converting และให้เริ่ม project ใหม่ | [Upgrading from 3.x to 4.x](https://codeigniter.com/user_guide/installation/upgrade_4xx.html) |
| CI4 4.7.4 เป็น release วันที่ 2026-07-07 | [CI4 4.7.4 Changelog](https://codeigniter.com/user_guide/changelogs/v4.7.4.html) |
| CI4 ต้องใช้ PHP 8.2+; PHP 8.5 ต้องใช้ CI4 4.7.0+ | [Server Requirements](https://codeigniter.com/user_guide/intro/requirements.html) |
| Conversion guide บางข้อความยังกล่าว PHP 8.1+ แต่ target Requirements ปัจจุบันกำหนด PHP 8.2+ | [CI3 to CI4 Upgrade Guide](https://codeigniter.com/user_guide/installation/upgrade_4xx.html), [Server Requirements](https://codeigniter.com/user_guide/intro/requirements.html) |
| `intl` และ `mbstring` เป็น required extensions; MySQL งานนี้ควรตรวจ `mysqlnd` และ extension ตาม feature ที่ใช้ | [Server Requirements](https://codeigniter.com/user_guide/intro/requirements.html) |
| CI4 4.7.4 แก้ security issue ใน proxy HTTPS detection, Query Builder `deleteBatch()` และ upload filename handling | [CI4 4.7.4 Changelog](https://codeigniter.com/user_guide/changelogs/v4.7.4.html) |

### Inference

CI4 4.7.0 ทำให้ PHP 8.5 “รองรับขั้นต่ำ” แต่ไม่ใช่ production baseline ที่เหมาะ ณ วันที่ตรวจ เพราะ 4.7.4 มี security fixes ภายหลัง. การใช้ 4.7.4 ลด known exposure โดยตรง; dependency ที่เหลือต้องผ่าน lock และ audit อีกชั้น. หลักฐาน: [CI4 Requirements](https://codeigniter.com/user_guide/intro/requirements.html), [CI4 4.7.4 Changelog](https://codeigniter.com/user_guide/changelogs/v4.7.4.html).

### Recommendation

1. สร้าง CI4 appstarter ใหม่ ไม่ copy `system/` หรือ bootstrap ของ CI3.
2. ตั้ง baseline เป็น CI4 4.7.4 + PHP 8.5.x และ commit `composer.lock`.
3. รัน platform check บน image เดียวกับ staging/production ไม่ใช้ผลจาก developer host แทน.
4. อัปเดต CI4 patch versionผ่าน PR เมื่อ `composer audit` และ regression suite ผ่าน.

Verification:

```bash
php --version
composer show codeigniter4/framework
composer check-platform-reqs
composer validate --strict
composer audit --locked
```

## 2. Project structure และ web boundary

### Confirmed facts

| พื้นที่ | ระบบเดิม | CI4 official behavior | Evidence |
|---|---|---|---|
| Application path | `application/` | `app/` และ namespaced classes | [CI3 to CI4 Upgrade Guide](https://codeigniter.com/user_guide/installation/upgrade_4xx.html) |
| Web entry point | project-root style | `index.php` อยู่ใต้ `public/`; web server ควรชี้ document root มาที่ `public/` | [Deployment](https://codeigniter.com/user_guide/installation/deployment.html) |
| Runtime files | session อยู่ใต้ application path | CI4 แยก runtime state ใต้ `writable/` | [CI3 to CI4 Upgrade Guide](https://codeigniter.com/user_guide/installation/upgrade_4xx.html) |
| Legacy library path | controller ใช้ `$_SERVER['DOCUMENT_ROOT'].'/lib/...'` | Composer/PSR-4 และ CI4 services แทน direct include | `application/controllers/Upload_excel.php:51-66`, `application/controllers/Login.php:204-206` |
| Uploaded spreadsheet | เขียน `uploads/excel/` relative path | UploadedFile `store()` ใช้ `writable/uploads` โดย default | [Upgrade File Upload](https://codeigniter.com/user_guide/installation/upgrade_file_upload.html) |

### Inference

การคง `DOCUMENT_ROOT` include หลังย้ายทำให้ path ผูกกับ deployment layout และอาจ expose project files หาก web root ตั้งผิด. `public/` cutover ต้องทดสอบ assets, downloads และ legacy upload URLs แยกจาก controller conversion. หลักฐาน: [Deployment](https://codeigniter.com/user_guide/installation/deployment.html), `application/controllers/Upload_excel.php:56-61`.

### Recommendation

- ชี้ Apache/Nginx document root ไป `public/` ตั้งแต่ Phase 1.
- เก็บ private uploads/imports ใต้ `writable/`; expose เฉพาะไฟล์ที่ business ยืนยันผ่าน download controller หรือ controlled public path.
- ห้าม copy `lib/`, `application/sess`, config และ logs เข้า `public/`.
- เพิ่ม external HTTP checks ว่า `/app`, `/vendor`, `/writable`, `/.env` และ backup-file patterns ถูก deny.

## 3. Routing, filters และ authorization

### Confirmed facts

| Fact | Evidence |
|---|---|
| มี 178 route declarations ใน current route file | static scan ของ `application/config/routes.php:41-266` |
| Route สอง parameter ส่ง `$1/$1` แทน `$1/$2` อย่างน้อย 3 จุด | `application/config/routes.php:116`, `application/config/routes.php:258`, `application/config/routes.php:266` |
| `bookListing/(:num)` ชี้ไป `order/bookListing/$1` | `application/config/routes.php:150-151` |
| Routes ระบุ `rackstatus` แต่ forms และ controller ใช้ `track/trackstatus` | `application/config/routes.php:218-222`, `application/views/en/track.php:2`, `application/controllers/Track.php:39-71` |
| Current routes ใช้ CI3 placeholder `(:any)` ใน reset-password routes | `application/config/routes.php:70-71` |
| CI4 upgrade guide ให้เปลี่ยน `(:any)` เป็น `(:segment)` เมื่อเจตนาคือหนึ่ง URI segment | [Upgrade Routing](https://codeigniter.com/user_guide/installation/upgrade_routing.html) |
| CI4 ปิด auto-routing โดย default ตั้งแต่ 4.2.0; Auto Routing Legacy มี warning ว่า filter/CSRF อาจถูก bypass | [URI Routing](https://codeigniter.com/user_guide/incoming/routing.html) |
| CI4 filters ทำงานก่อน/หลัง controller และใช้กับ CSRF, role restriction, rate limit ได้ | [Controller Filters](https://codeigniter.com/user_guide/incoming/filters.html) |
| Filter execution order เปลี่ยนตั้งแต่ 4.5.0; `spark filter:check` แม่นกว่า route listing เมื่อ pattern ซับซ้อน | [Controller Filters](https://codeigniter.com/user_guide/incoming/filters.html), [URI Routing](https://codeigniter.com/user_guide/incoming/routing.html) |
| Current auth gate อ่าน `isLoggedIn`, role, group และ branch จาก session ใน custom base class | `application/libraries/BaseController.php:36-56` |
| `isTicketter()` ใช้เงื่อนไข `role != admin OR role != manager` | `application/libraries/BaseController.php:73-78` |

### Inference

เงื่อนไข `isTicketter()` มีแนวโน้มคืน `true` สำหรับทุก role เดี่ยว เพราะค่าเดียวไม่อาจเท่ากับ admin และ manager พร้อมกัน. ต้องยืนยันค่าคงที่และ caller ก่อนจัดเป็น defect ที่ confirmed. หลักฐาน: `application/libraries/BaseController.php:73-78`.

Route defects เดิมอาจถูกกลบด้วย CI3 auto-routing. เมื่อย้ายเป็น defined routes จุดเหล่านี้จะกลายเป็น contract decision: รักษา legacy URL, redirect แบบตั้งใจ หรือประกาศแก้ defect. หลักฐาน: `application/config/routes.php:114-151`, [URI Routing](https://codeigniter.com/user_guide/incoming/routing.html).

### Recommendation

1. สร้าง route registry: method, URI, handler, auth filter, branch policy, write tables, owner runtime และ rollback target.
2. ใช้ defined verb routes; ไม่เปิด Auto Routing Legacy.
3. ย้าย login/role/branch checks ไป filters แต่คง business policy เดิมจน characterization tests ผ่าน.
4. รัน `php spark routes` และ `php spark filter:check <method> <route>` ใน CI สำหรับ critical routes.
5. เพิ่ม negative tests: anonymous, wrong role, wrong branch, method confusion และ direct-controller path.
6. แปลง `(:any)` เป็น `(:segment)` หรือ regex ที่แคบกว่า; ทดสอบ encoded slash และ malformed reset token.

## 4. Request, response และ views

### Confirmed facts

| พื้นที่ | Fact | Evidence |
|---|---|---|
| Request footprint | พบ `$this->input->post/get` 351 ครั้งใน application snapshot | static scan ของ `application/controllers/*.php` |
| Input behavior | CI4 IncomingRequest คืน raw input; ไม่มี automatic filtering | [IncomingRequest](https://codeigniter.com/user_guide/incoming/incomingrequest.html) |
| Input choice | `getVar()` มีไว้เพื่อ backward compatibility และอาจรวม GET/POST/COOKIE; new code ควรใช้ method ตรง source | [IncomingRequest](https://codeigniter.com/user_guide/incoming/incomingrequest.html) |
| HTTP method | CI4 current request API ใช้ `$request->is('post')`; conversion guideระบุ `getMethod()` คืน uppercase ตั้งแต่ 4.5.0 | [IncomingRequest](https://codeigniter.com/user_guide/incoming/incomingrequest.html), [CI3 to CI4 Upgrade Guide](https://codeigniter.com/user_guide/installation/upgrade_4xx.html) |
| Legacy response | custom `response()` เรียก `_display()` แล้ว `exit()` | `application/libraries/BaseController.php:28-31` |
| CI4 JSON | Response รองรับ `return $this->response->setJSON($data)` | [HTTP Responses](https://codeigniter.com/user_guide/outgoing/response.html), [Upgrade Output Class](https://codeigniter.com/user_guide/installation/upgrade_responses.html) |
| Redirect | CI4 ต้อง return `RedirectResponse`; header/cookie บน global response ไม่ถูก copy อัตโนมัติ | [HTTP Responses](https://codeigniter.com/user_guide/outgoing/response.html) |
| View | CI4 ใช้ `return view(...)`; rendered view เป็น response content ได้ | [Upgrade Views](https://codeigniter.com/user_guide/installation/upgrade_views.html) |

### Correction ต่อ v2

คำว่า “return `ResponseInterface`” ใน gap matrix ควรแก้เป็น “คืน response ให้ชัดตาม endpoint: `return view(...)`, `return $this->response->setJSON(...)`, file response หรือ `return redirect()`”. การบังคับ interface เดียวทุก method ไม่ใช่ requirement ของ CI4. หลักฐาน: [HTTP Responses](https://codeigniter.com/user_guide/outgoing/response.html), [Upgrade Views](https://codeigniter.com/user_guide/installation/upgrade_views.html).

### Recommendation

- Map input source ต่อ field; หลีกเลี่ยง `getVar()` ใน target.
- ใช้ validated data เท่านั้นสำหรับ write path.
- ย้าย custom JSON helper ให้ return CI4 Response โดยไม่ `_display()` และไม่ `exit()`.
- ทำ snapshot tests สำหรับ status code, redirect location, content type, download headers และ HTML critical fragments.

## 5. Models, queries และ transaction boundary

### Confirmed facts

| Fact | Evidence |
|---|---|
| มี 19 model files และ 18 classes สืบทอด `CI_Model` ใน snapshot | `application/models/*.php`, ตัวอย่าง `application/models/Request_order_model.php:3` |
| CI4 Model ต้องกำหนด primary key เพื่อใช้ features ครบ และกำหนด `$allowedFields` สำหรับ field protection | [Using CodeIgniter's Model](https://codeigniter.com/user_guide/models/model.html) |
| ระบบมี direct Query Builder/DB usage มากกว่า 1,000 calls และมี SQL string interpolation หลายจุด | ตัวอย่าง `application/models/Request_order_model.php:38-43`, `application/models/Rating_model.php:172` |
| CI4 query bindings escape bound values อัตโนมัติ | [Queries](https://codeigniter.com/user_guide/database/queries.html) |
| ระบบมี transactions อยู่แล้วในหลาย model | ตัวอย่าง `application/models/Rating_model.php:13-38`, `application/models/Request_order_model.php:905-923` |
| Create order เรียก insert order, insert status log และ SMS แยกกัน | `application/controllers/Order.php:726-744` |
| Status update เรียก update, lookup, insert log และ optional SMS ต่อรายการ | `application/controllers/Order.php:805-864` |
| CI4 ไม่ throw query exception ใน transaction โดย default ตั้งแต่ 4.3.0 แม้ `DBDebug` เปิด; ต้องตรวจ `transStatus()` หรือเลือก `transException(true)` | [Transactions](https://codeigniter.com/user_guide/database/transactions.html) |
| Transactions ต้องใช้ transaction-safe engine; สำหรับ MySQL official CI4 ระบุ InnoDB/BDB ไม่ใช่ MyISAM | [Transactions](https://codeigniter.com/user_guide/database/transactions.html) |

### Inference

ความเสี่ยงหลักไม่ใช่ “ไม่มี transaction” แต่ transaction อยู่ระดับ method และอาจไม่ครอบ business operation. ตัวอย่าง create order สำเร็จแต่ log หรือ SMS ล้มได้; SMS ไม่ควรอยู่ใน DB transaction ยาว แต่ต้องมี retry/idempotency state ที่ตรวจสอบได้. หลักฐาน: `application/controllers/Order.php:726-744`, [MySQL InnoDB](https://dev.mysql.com/doc/refman/8.4/en/innodb-introduction.html).

### Recommendation

- ใช้ CI4 Model เฉพาะ aggregate/CRUD ที่ได้ประโยชน์จาก `$allowedFields`; ใช้ Query Builder ตรงใน report/query-heavy code ได้ ไม่สร้าง generic repository.
- เปลี่ยน interpolated conditions เป็น Query Builder methods หรือ bound SQL พร้อม allowlist สำหรับ column/sort identifiers.
- กำหนด transaction ต่อ use case: order + status log commit พร้อมกัน; external SMS/email ส่งหลัง commit ผ่าน durable delivery record หรือ retry ที่ idempotent.
- ตรวจ `transStatus()` ทุก managed transaction หรือใช้ `transException(true)` อย่างตั้งใจ.
- ยืนยัน engine ของทุก write table ก่อนรับรอง atomicity.

## 6. Validation, CSRF และ output security

### Confirmed facts

| Fact | Evidence |
|---|---|
| Current config ปิด CSRF protection | `application/config/config.php:290-305` |
| Current controllers ใช้ CI3 form validation จำนวนมาก | ตัวอย่าง `application/controllers/Login.php:50-66`, `application/controllers/Contact.php:75-91` |
| Current code บาง flow ใช้ `xss_clean()` กับ input ก่อน query | `application/controllers/Order.php:89-97` |
| CI4 ใช้ Strict Rules โดย default ตั้งแต่ 4.3.0 และ strict rules ไม่ทำ implicit type conversion | [Validation](https://codeigniter.com/user_guide/libraries/validation.html) |
| CI4 validation ไม่เปลี่ยน input data; format rules ไม่ยอม empty string เว้นใช้ `permit_empty` | [Upgrade Validations](https://codeigniter.com/user_guide/installation/upgrade_validations.html) |
| CI3 validation callbacks ไม่ย้ายตรง; CI4 ใช้ callable/closure/rule classes และลำดับ evaluation ต่าง | [Upgrade Validations](https://codeigniter.com/user_guide/installation/upgrade_validations.html) |
| เมื่อใช้ request-wide validation ต้องอ่านค่าจาก `getValidated()` เพื่อกันค่าที่ไม่ได้ validate | [Validation](https://codeigniter.com/user_guide/libraries/validation.html) |
| CI4 เปิด CSRF ผ่าน filter; `form_open()` ใส่ hidden token ให้เมื่อ global CSRF filter เปิด | [Upgrade Security](https://codeigniter.com/user_guide/installation/upgrade_security.html) |
| CI4 CSRF ป้องกัน POST/PUT/PATCH/DELETE; หาก application ใช้ Session official docs ให้ใช้ session-based CSRF | [Security](https://codeigniter.com/user_guide/libraries/security.html) |

### Inference

การใช้ `xss_clean()` ไม่ทดแทน positive validation, SQL binding หรือ contextual output escaping. Current CSRF setting ทำให้ write routes ต้องถือเป็น unprotected จน runtime/infrastructure พิสูจน์ว่ามี control ชั้นอื่น. หลักฐาน: `application/config/config.php:302`, [Security Guidelines](https://codeigniter.com/user_guide/concepts/security.html).

### Recommendation

1. เปิด session-based CSRF สำหรับ browser form routes และระบุ exception เฉพาะ endpoint ที่มี alternate authentication จริง.
2. ใช้ Strict Rules และ `getValidated()`; แยก create/update rules ชัดเจน.
3. ใช้ allowlist สำหรับ enum/status/branch/sort และ server-side authorization หลัง validation.
4. Escape output ตาม context ใน views; ห้ามพึ่ง input cleaning เป็น XSS control หลัก.
5. Test CSRF token missing/invalid/replay, extra unvalidated fields, type confusion และ unauthorized branch ID.

## 7. Sessions และ authentication coexistence

### Confirmed facts

| Fact | Evidence |
|---|---|
| Session ถูก autoload พร้อม database | `application/config/autoload.php:45-67` |
| Current config ใช้ cookie `ci_session`, TTL 7200, file save path ใต้ `application/sess` และไม่ใช้ DB session | `application/config/config.php:252-261` |
| Current source ตั้ง `cookie_secure` เป็น false | `application/config/config.php:263-278` |
| Login บันทึก user, role, group, branch และ flag ลง session | `application/controllers/Login.php:68-94` |
| BaseController ใช้ session keys เหล่านี้เป็น access context | `application/libraries/BaseController.php:36-56` |
| CI4 เปลี่ยน session method names; หากใช้ Database Driver ต้องสร้าง session table ใหม่ | [Upgrade Sessions](https://codeigniter.com/user_guide/installation/upgrade_sessions.html) |
| CI4 sessions มี locking; request เดียวกันอาจรอกันจน session ถูก close | [Session Library](https://codeigniter.com/user_guide/libraries/sessions.html) |
| PHP 8.5 เตือนเมื่อ session key มี pipe character และ `session_start()` ตรวจ options เข้มขึ้น | [PHP 8.5 Backward Incompatible Changes](https://www.php.net/migration85.incompatible.php) |

### Inference

Current source ใช้ file session ไม่ใช่ DB session ดังนั้นคำเตือน “ต้องเปลี่ยน session table” ยังไม่ใช้กับ current config. อย่างไรก็ดี shared cookie/payload ระหว่าง CI3 และ CI4 ยังเสี่ยงจาก handler path, serialization, regeneration และ cookie settings ที่ต่างกัน. หลักฐาน: `application/config/config.php:252-261`, [Upgrade Sessions](https://codeigniter.com/user_guide/installation/upgrade_sessions.html).

### Recommendation

- ใช้ cookie name แยกสำหรับ CI3/CI4 ระหว่าง coexistence เว้นมี tested session bridge ที่เจ้าของ security อนุมัติ.
- ย้าย authenticated route group เป็นชุด; public tracking/contact มาก่อนเพื่อลด session coupling.
- Regenerate session ID หลัง login/privilege change และทำ destroy/logout tests.
- Production ต้องบังคับ HTTPS และ secure cookie settings ผ่าน environment-specific config.
- เพิ่ม concurrency test สำหรับ parallel AJAX และปิด session เร็วเมื่อไม่เขียน state เพิ่ม.

## 8. Database migrations และ schema compatibility

### Confirmed facts

| Fact | Evidence |
|---|---|
| CI3 migration config ถูกปิดและ version เป็น 0 | `application/config/migration.php:1-24` |
| Config ชี้ path ไป `application/migrations/`; static inventory ไม่พบ application migration files | `application/config/migration.php:27-40` |
| CI4 ใช้ timestamp migration names, `app/Database/Migrations` และ `php spark migrate` | [Upgrade Migrations](https://codeigniter.com/user_guide/installation/upgrade_migrations.html) |
| หาก CI3/CI4 ใช้ database เดียวและมี CI3 migration state ต้อง upgrade migration table definition/data | [Upgrade Migrations](https://codeigniter.com/user_guide/installation/upgrade_migrations.html) |
| CI4 migration runner มี `migrate`, `migrate:rollback`, `migrate:status`; distributed migration lock มี config และ default เป็น false | [Database Migrations](https://codeigniter.com/user_guide/dbmgmt/migration.html) |

### Correction ต่อ v2

คำว่า “baseline schema migrations + rollback” ต้องแยกสอง artifact:

1. Baseline schema snapshot สำหรับสร้าง test/staging database จาก current state.
2. Additive CI4 migrations สำหรับ change หลัง baseline.

ไม่ควรสร้าง `down()` ที่ลบ production data เพียงเพื่อให้ทุก migration reversible. Data-destructive rollback ต้องใช้ restore/forward-fix plan ที่อนุมัติ. Current source ยืนยันเพียง migration ถูกปิด ไม่ยืนยัน schema หรือ migration table จริง: `application/config/migration.php:11-37`.

### Recommendation

- Capture tables, columns, engines, PK/UK/FK, indexes, triggers, views, routines, charset/collation และ reference-data keys โดยไม่เก็บ PII ใน repo.
- ตรวจว่ามี CI3 `migrations` table ใน DB จริงหรือไม่ก่อนเลือก upgrade/initialize migration history.
- ใช้ additive schema ระหว่าง coexistence และบันทึก CI3-readable/CI4-readable ต่อ migration.
- ใช้ single migrator process หรือเปิด migration lock เมื่อ deployment มีหลาย replicas.
- รัน migrate/rollback บน restored backup; ห้ามใช้ `migrate:refresh` บน production.

## 9. Email, secret และ external integrations

### Confirmed facts

| Fact | Evidence |
|---|---|
| Bundled PHPMailer เป็น 5.2.10 | `lib/PHPMailer/class.phpmailer.php:28-34` |
| Active controllers include PHPMailer ด้วย `DOCUMENT_ROOT` | `application/controllers/Login.php:204-206`, `application/controllers/Contact.php:188-192`, `application/controllers/Contact_th.php:159-163` |
| พบ hardcoded SMTP credential ใน source โดยเอกสารนี้ไม่บันทึกค่า | `application/controllers/Login.php:213-214`, `application/controllers/Login.php:344-345`, `application/controllers/Contact.php:206-208`, `application/controllers/Contact_th.php:177-179` |
| CI4 Email migration ใช้ `service('email')`; method names และ SMTP behavior บางส่วนเปลี่ยน | [Upgrade Emails](https://codeigniter.com/user_guide/installation/upgrade_emails.html) |
| SMS helper ส่ง credential ใน POST string และใช้ cURL โดยมี connect timeout 15 วินาที | `application/helpers/cias_helper.php:30-47` |

### Recommendation

- P0: rotate/revoke exposed SMTP credentials; ตรวจ git history, deployment config และ logs โดยไม่คัดลอกค่าลง issue/report.
- ใช้ CI4 Email service และ environment/secret manager; ทดสอบ TLS mode กับ SMTP sandbox เพราะ config CI3 อาจใช้ตรงไม่ได้.
- เพิ่ม timeout ทั้ง connect/total, error mapping, safe logging และ idempotency สำหรับ SMS/email.
- ห้าม log request body, password, token, recipient PII หรือ provider response ที่อาจมี sensitive data.
- ลบ PHPMailer bundle จาก target หลัง caller parity ผ่าน; ไม่ patch PHPMailer 5.2.10 ต่อ.

## 10. Upload และ spreadsheet import

### Confirmed facts

| Fact | Evidence |
|---|---|
| Active import ตรวจเพียง filename extension `xls/xlsx`, ใช้ `move_uploaded_file()` และ PHPExcel | `application/controllers/Upload_excel.php:51-66` |
| Import ล้าง shared temp tables ก่อน load | `application/controllers/Upload_excel.php:60`, `application/controllers/Upload_excel.php:241`, `application/controllers/Upload_excel.php:399` |
| PHPExcel bundle มี copyright ถึงปี 2015 | `lib/PHPExcel.php:9-32` |
| CI4 UploadedFile ใช้ `isValid()`, `hasMoved()`, `getRandomName()` และย้ำว่า client filename ไม่ควรเชื่อถือ | [Working with Uploaded Files](https://codeigniter.com/user_guide/libraries/uploaded_files.html) |
| PhpSpreadsheet เป็น successor ของ PHPExcel; official project ระบุ maintained branch และ PHP minimum 8.1 | [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) |
| PhpSpreadsheet master กำหนด PHP `^8.1` และต้องใช้ extensions หลายตัว เช่น DOM, fileinfo, GD, mbstring, XML, Zip และ zlib | [PhpSpreadsheet composer.json](https://github.com/PHPOffice/PhpSpreadsheet/blob/master/composer.json) |

### Inference

Shared temp-table cleanup ทำให้ preview/confirm ของผู้ใช้หนึ่งคนลบหรือปน batch ของอีกคนได้. Extension-only validation เปิดรับไฟล์ที่ content ไม่ตรงชนิด. หลักฐาน: `application/controllers/Upload_excel.php:51-66`, `application/controllers/Upload_excel.php:232-247`.

### Recommendation

- เปลี่ยนเป็น PhpSpreadsheet รุ่น stable ที่ทดสอบ ณ implementation time และ lock exact resolved versions ใน `composer.lock`.
- ตรวจ size, extension, content-derived MIME, parseability, row/column ceiling และ formula policy ก่อน import.
- ใช้ random server filename ใต้ `writable/uploads`; เก็บ original name เป็น metadata ที่ escaped เท่านั้น.
- แยก batch ด้วย `batch_id`, owner, state, checksum และ timestamps; confirm ต้องอ้าง batch เดิมแบบ idempotent.
- ทดสอบ concurrent users, duplicate confirm, malformed ZIP/XML, oversized sheet และ partial-row validation.

## 11. PHP 8.5 compatibility

### PHP 8.5-specific facts

| Change | Local relevance | Evidence |
|---|---|---|
| Non-canonical casts `(boolean)`, `(integer)`, `(double)`, `(binary)` deprecated | พบจำนวนมากใน bundled PHPExcel/PHPMailer/ApnsPHP | [PHP 8.5 Deprecated Features](https://www.php.net/migration85.deprecated.php), `lib/PHPMailer/class.pop3.php:172-178`, `lib/PHPExcel/Reader/Excel2007.php:898-899` |
| `curl_close()` deprecated | active SMS/OAuth code เรียก function นี้ | [PHP 8.5 Deprecated Features](https://www.php.net/migration85.deprecated.php), `application/helpers/cias_helper.php:47`, `application/libraries/Google_oauth.php:178` |
| `finfo_close()` และ `imagedestroy()` deprecated | ต้อง scan target/runtime path ที่ใช้ file/image handling | [PHP 8.5 Deprecated Features](https://www.php.net/migration85.deprecated.php) |
| Cast float/string-float ไป int ที่ represent ไม่ได้จะ warning | import/report numeric conversion ต้องมี boundary tests | [PHP 8.5 Backward Incompatible Changes](https://www.php.net/migration85.incompatible.php) |
| Intl ต้องใช้ ICU 57.1+ | CI4 บังคับ ext-intl จึงต้องตรวจ runtime image | [PHP 8.5 Backward Incompatible Changes](https://www.php.net/migration85.incompatible.php), [CI4 Requirements](https://codeigniter.com/user_guide/intro/requirements.html) |
| Session options และ key behavior เข้มขึ้น | login/session regression required | [PHP 8.5 Backward Incompatible Changes](https://www.php.net/migration85.incompatible.php) |

### Blockers ที่เกิดก่อน PHP 8.5

| Blocker | สถานะ PHP | Local evidence | Primary source |
|---|---|---|---|
| Dynamic property `$this->excel` | deprecated ตั้งแต่ PHP 8.2 | `application/controllers/Upload_excel.php:56-57`, `application/controllers/Upload_excel.php:237-238` | [PHP 8.2 Deprecated Features](https://www.php.net/manual/en/migration82.deprecated.php) |
| `create_function()` | removed ตั้งแต่ PHP 8.0 | `lib/ApnsPHP-master/ApnsPHP/Message.php:403` | [PHP create_function](https://www.php.net/manual/en/function.create-function.php) |
| `each()` | removed ตั้งแต่ PHP 8.0 | `lib/PHPMailer/extras/htmlfilter.php:49`, `lib/PHPMailer/extras/htmlfilter.php:525` | [PHP each](https://www.php.net/manual/en/function.each.php) |
| `mcrypt_*` | ย้ายออกจาก core ไป PECL ตั้งแต่ PHP 7.2 และไม่ควรใช้ | `lib/aes_encrypt.php:119-145`, `lib/PHPMailer/extras/ntlm_sasl_client.php:71-82` | [PHP 7.2 Other Changes](https://www.php.net/manual/en/migration72.other-changes.php) |
| magic-quotes runtime functions | removed ก่อน PHP 8 | `lib/PHPMailer/class.phpmailer.php:2414-2426`, `lib/PHPExcel/Shared/PCLZip/pclzip.lib.php:4855-4885` | [PHP 8.0 Incompatible Changes](https://www.php.net/manual/en/migration80.incompatible.php) |

### Correction ต่อ v2

Section PHP 8.5 ใน v2 ควรแบ่ง “PHP 8.5-specific” กับ “legacy blockers inherited from PHP 7/8.0/8.2”. การรวมทั้งหมดใต้ 8.5 ทำให้ root cause และลำดับ remediation ไม่แม่น. หลักฐานอยู่ในตารางสองชุดด้านบน.

### Recommendation

- ทำ active-caller inventory ก่อน port library; dead dependency ให้ delete ไม่แก้.
- รัน application + dependencies ด้วย `E_ALL` บน PHP 8.5 production-like image.
- เพิ่ม tests สำหรับ numeric casts, date/locale, sessions, cURL failure, fileinfo, images และ spreadsheet parsing.
- ห้ามใช้ `#[AllowDynamicProperties]` เป็น migration shortcut สำหรับ app code; declare property หรือใช้ local variable/dependency injection ตามหน้าที่จริง.

## 12. Dependency reproducibility และ security audit

### Confirmed facts

| Fact | Evidence |
|---|---|
| Composer lock เก็บ exact resolved versions และควร commit สำหรับ application เพื่อให้ CI/production ใช้ dependency ชุดเดียวกัน | [Composer Basic Usage](https://getcomposer.org/doc/01-basic-usage.md) |
| `composer audit` ตรวจ security advisories, abandoned packages และ malware flags; `--locked` ตรวจจาก lock file | [Composer CLI](https://getcomposer.org/doc/03-cli.md) |
| Composer แนะนำ `validate` ก่อน commit/tag | [Composer CLI](https://getcomposer.org/doc/03-cli.md) |
| PhpSpreadsheet มี PHP และ extension requirements มากกว่า PHP version อย่างเดียว | [PhpSpreadsheet composer.json](https://github.com/PHPOffice/PhpSpreadsheet/blob/master/composer.json) |

### Recommendation

- Project root ต้องมี `composer.json` และ committed `composer.lock`; ไม่ใช้ floating `*`/`latest` สำหรับ production dependency.
- CI ใช้ `composer install` จาก lock ไม่ใช้ unrestricted `composer update`.
- Gate ขั้นต่ำ: `composer validate --strict`, `composer audit --locked`, `composer check-platform-reqs`.
- Review license/maintenance ของ direct dependencies และบันทึกเหตุผลก่อนเพิ่ม package.
- อย่า copy vendor directory จาก CI3; สร้าง target vendor ด้วย Composer จาก lock.

## 13. Testing และ deployment evidence

### Confirmed facts

| Fact | Evidence |
|---|---|
| ไม่พบ application-level PHPUnit config/test tree ใน static inventory; `lib/fbsdk/tests` เป็น third-party tests | repo file inventory ณ 2026-08-09 |
| CI4 ใช้ PHPUnit และมี `CIUnitTestCase` | [Testing Overview](https://codeigniter.com/user_guide/testing/overview.html) |
| CI4 feature tests เรียก full request lifecycle และตรวจ routing/response ได้ | [HTTP Feature Testing](https://codeigniter.com/user_guide/testing/feature.html) |
| CI4 มี DatabaseTestTrait สำหรับ test database, migrations/seeds และ assertions | [Testing Your Database](https://codeigniter.com/user_guide/testing/database.html) |
| Production deploy ควรชี้ document root ไป `public/`; `composer install --no-dev` เป็น official deployment step | [Deployment](https://codeigniter.com/user_guide/installation/deployment.html) |

### Correction ต่อ v2

คำว่า “ไม่พบ automated tests” ควรแก้เป็น “ไม่พบ application-level automated test suite ที่ใช้ยืนยัน business parity”. Repo มี third-party test artifacts และไฟล์ชื่อ `report_tracking_test.php` แต่ชื่อไฟล์ไม่พิสูจน์ว่าเป็น runnable test. Evidence: `lib/fbsdk/tests`, `application/views/tracking/report_tracking_test.php`.

### Recommendation

| Gate | Minimum evidence | Stop condition |
|---|---|---|
| Build | clean Composer install, PHP/CI versions, platform requirements | dependency/platform mismatch |
| Route | route registry diff, `spark routes`, filter checks | unexpected route/filter exposure |
| Characterization | approved input/output fixtures จาก CI3 | critical behavior ยังไม่มี baseline |
| DB integration | isolated MySQL matching production major, migration/status logs | transaction/constraint/rollback fail |
| Feature | login, branch auth, order, status, public tracking, import, rating, report | critical scenario fail |
| Concurrency | parallel order ID, tracking, import batches และ session requests | duplicate/cross-user contamination |
| Security | CSRF, IDOR, SQL injection, upload, secret scan, dependency audit | open P0/P1 finding |
| Release | staging rehearsal, backup restore, route rollback, tag/changelog | rollback path ไม่ผ่าน |

## 14. MySQL concurrency, uniqueness และ charset

### Confirmed facts

| Fact | Evidence |
|---|---|
| `getNumberTrackingNew()` ใช้ `select_max('trackID')` แล้วบวกหนึ่งใน application | `application/models/Request_order_model.php:1343-1369` |
| Public tracking ล้าง `temp_status_log` ทั้ง table ก่อน query | `application/models/Request_order_model.php:1463-1483` |
| Excel paths ล้าง shared temp tables ทั้ง table | `application/controllers/Upload_excel.php:60`, `application/controllers/Upload_excel.php:241`, `application/controllers/Upload_excel.php:399` |
| InnoDB รองรับ ACID transactions, row-level locking และ FK | [MySQL InnoDB Introduction](https://dev.mysql.com/doc/refman/8.4/en/innodb-introduction.html) |
| Unique/primary-key violation บน InnoDB ทำให้ statement rollback | [MySQL PRIMARY KEY and UNIQUE Constraints](https://dev.mysql.com/doc/refman/8.4/en/constraint-primary-key.html) |
| Locking read แบบ unique condition ล็อก record; non-unique/range อาจใช้ gap/next-key locks | [MySQL Locks Set by Statements](https://dev.mysql.com/doc/refman/8.4/en/innodb-locks-set.html) |
| Deadlock ยังเกิดได้; MySQL rollback victim และ application ต้องรองรับ retry | [MySQL InnoDB Deadlocks](https://dev.mysql.com/doc/refman/8.4/en/innodb-deadlocks.html) |
| MySQL แนะนำ `utf8mb4`; `utf8` เป็น deprecated alias ของ `utf8mb3` | [MySQL Character Sets](https://dev.mysql.com/doc/refman/8.4/en/charset.html) |

### Inference

`SELECT MAX + 1` ไม่มี atomic guarantee จาก code ที่เห็น. แม้เพิ่ม transaction อย่างเดียวก็ยังไม่พอหากไม่มี locking strategy/unique constraint และ retry. Shared temp-table `empty_table()` เป็น global mutation จึงขัดกับ concurrent request isolation. หลักฐาน: `application/models/Request_order_model.php:1343-1369`, `application/models/Request_order_model.php:1463-1483`.

### Recommendation

- ให้ DB บังคับ uniqueness ของ final tracking identifier หลัง duplicate scan และ business sign-off.
- ใช้ native `AUTO_INCREMENT`/sequence table/atomic row lock ตามรูปแบบ ID ที่ business ต้องการ; retry เฉพาะ duplicate/deadlock แบบ bounded.
- แทน global temp table ด้วย direct query หรือ rows scoped ด้วย `batch_id/request_id/owner`.
- ทำ concurrent create/import/public-tracking tests บน engine/version เดียวกับ production.
- แยก charset conversion เป็นงานหลัง parity; inventory collation, index width, comparison/order behavior และ malformed data ก่อนเปลี่ยนเป็น `utf8mb4`.

## 15. Source-backed gaps และ corrections ต่อรายงาน v2

| ID | ข้อความใน v2 หรือช่องว่าง | ปรับเป็น | Evidence |
|---|---|---|---|
| G-01 | Target “4.7.4 หรือ stable 4.7.x” | ใช้ 4.7.4 เป็น verified baseline ณ 2026-08-09; update patch ผ่าน CI/audit | [CI4 4.7.4 Changelog](https://codeigniter.com/user_guide/changelogs/v4.7.4.html) |
| G-02 | PHP requirements ระบุ version เป็นหลัก | เพิ่ม required `intl`, `mbstring` และ feature-specific extensions | [CI4 Requirements](https://codeigniter.com/user_guide/intro/requirements.html) |
| G-03 | Response target เป็น `ResponseInterface` | ระบุ response contract ตาม endpoint รวม `return view()` และ redirect return | [HTTP Responses](https://codeigniter.com/user_guide/outgoing/response.html), [Upgrade Views](https://codeigniter.com/user_guide/installation/upgrade_views.html) |
| G-04 | Validation plan ยัง generic | บังคับ Strict Rules, explicit input source และ `getValidated()` | [Validation](https://codeigniter.com/user_guide/libraries/validation.html) |
| G-05 | CSRF อยู่ใน architecture principle แต่ไม่มี current-state fact | ระบุ current config ปิด CSRF และเพิ่ม P0/P1 verification | `application/config/config.php:290-305` |
| G-06 | Filter plan ไม่ระบุ execution/check behavior | เพิ่ม filter order awareness และ `spark filter:check` | [Controller Filters](https://codeigniter.com/user_guide/incoming/filters.html) |
| G-07 | Route plan บอก explicit routes | เพิ่มเหตุผล security: Auto Routing Legacy อาจ bypass filter/CSRF | [URI Routing](https://codeigniter.com/user_guide/incoming/routing.html) |
| G-08 | Session table migration เป็นคำแนะนำทั่วไป | Current config ใช้ file session; DB table change เป็น conditional | `application/config/config.php:252-261`, [Upgrade Sessions](https://codeigniter.com/user_guide/installation/upgrade_sessions.html) |
| G-09 | Transaction risk อ่านเหมือนยังไม่มี transaction | ระบุมี transaction ใน model แล้ว แต่ business boundary ยังแตก | `application/models/Rating_model.php:13-38`, `application/controllers/Order.php:726-744` |
| G-10 | Transaction verification ไม่กล่าวถึง CI4 exception default | เพิ่ม `transStatus()`/`transException(true)` gate | [Transactions](https://codeigniter.com/user_guide/database/transactions.html) |
| G-11 | Migration plan รวม baseline กับ change migrations | แยก schema snapshot, migration history และ additive migrations | `application/config/migration.php:11-37`, [Database Migrations](https://codeigniter.com/user_guide/dbmgmt/migration.html) |
| G-12 | Migration concurrency ไม่ระบุ | เพิ่ม single migrator หรือ migration lock สำหรับ multi-process deploy | [Database Migrations](https://codeigniter.com/user_guide/dbmgmt/migration.html) |
| G-13 | “ไม่พบ automated tests” กว้างเกินไป | ระบุไม่พบ application-level runnable suite; third-party tests ไม่นับ parity | `lib/fbsdk/tests`, `application/views/tracking/report_tracking_test.php` |
| G-14 | PHP 8.5 section รวม blockers เก่าทั้งหมด | แยก PHP 8.5-specific จาก PHP 8.0/8.2 inherited blockers | [PHP 8.5 Deprecated](https://www.php.net/migration85.deprecated.php), [PHP 8.2 Deprecated](https://www.php.net/manual/en/migration82.deprecated.php) |
| G-15 | PhpSpreadsheet ระบุเพียง PHP `^8.1` | เพิ่ม extension matrix และ platform check ใน target image | [PhpSpreadsheet composer.json](https://github.com/PHPOffice/PhpSpreadsheet/blob/master/composer.json) |
| G-16 | Upload plan ระบุ MIME/size/name | เพิ่ม random server name, content/parse checks, row ceiling และ formula policy | [Working with Uploaded Files](https://codeigniter.com/user_guide/libraries/uploaded_files.html) |
| G-17 | Tracking ID fix ระบุ unique + atomic | เพิ่ม duplicate pre-scan, bounded deadlock/duplicate retry และ engine confirmation | [MySQL Unique Constraints](https://dev.mysql.com/doc/refman/8.4/en/constraint-primary-key.html), [MySQL Deadlocks](https://dev.mysql.com/doc/refman/8.4/en/innodb-deadlocks.html) |
| G-18 | Charset upgrade ไว้หลัง parity | เพิ่มเหตุผล official: `utf8` คือ deprecated alias ของ `utf8mb3`; ยังคงแยก project | [MySQL Character Sets](https://dev.mysql.com/doc/refman/8.4/en/charset.html) |
| G-19 | Dependency gate ระบุ lock/audit แบบสรุป | เพิ่ม `composer validate --strict`, `audit --locked`, `check-platform-reqs` | [Composer CLI](https://getcomposer.org/doc/03-cli.md), [Composer Basic Usage](https://getcomposer.org/doc/01-basic-usage.md) |
| G-20 | Security risk registerยังไม่กล่าว `isTicketter()` | เพิ่ม verification finding ก่อน port policy | `application/libraries/BaseController.php:73-78` |
| G-21 | Route conversion ยังไม่กล่าว placeholder semantics | เปลี่ยน CI3 `(:any)` เป็น CI4 `(:segment)` เมื่อรับหนึ่ง segment | `application/config/routes.php:70-71`, [Upgrade Routing](https://codeigniter.com/user_guide/installation/upgrade_routing.html) |
| G-22 | เอกสาร CI4 สองหน้าระบุ PHP floor ต่างกัน | ยึด Requirements ของ target release: PHP 8.2+, และ PHP 8.5 ต้อง CI4 4.7.0+ | [CI4 Upgrade Guide](https://codeigniter.com/user_guide/installation/upgrade_4xx.html), [CI4 Requirements](https://codeigniter.com/user_guide/intro/requirements.html) |

## 16. Priority recommendations

นิยาม: P0 ต้องเสร็จก่อนเริ่มหรือก่อนเปิด traffic ใด ๆ, P1 ต้องเสร็จก่อน cutover slice ที่เกี่ยวข้อง, P2 ทำหลัง parity เมื่อไม่เพิ่ม immediate risk.

### P0

| Action | เหตุผล | Verification |
|---|---|---|
| Rotate/revoke SMTP credentials และย้ายไป secret manager | Credential อยู่ใน source แล้ว; การลบไฟล์ไม่ลบ exposure เดิม | old credential ใช้ไม่ได้, secret scan ผ่าน, source/log ไม่มีค่า |
| สร้าง current schema/route/behavior baseline | ไม่มี application migrations/tests ที่พิสูจน์ parity | restored test DB, route registry และ approved fixtures พร้อม |
| เริ่ม CI4 4.7.4 บน PHP 8.5 จาก Composer lock | เป็น supported/security-fixed baseline ณ วันที่ตรวจ | clean install, platform checks, version output, audit green |
| กำหนด `public/` document root และ deny non-public paths | ลด source/config/runtime exposure | external HTTP deny checks ผ่าน |
| Audit active callers ของทุก bundled library | มี removed APIs; copy ทั้งก้อนสร้าง fatal/security debt | caller registry มี Retain/Replace/Delete พร้อม owner |
| กำหนด auth/branch policy จาก current behavior | session keys เป็น access boundary หลัก และ logic บางจุดน่าสงสัย | signed role/branch matrix + negative tests |

### P1

| Action | เหตุผล | Verification |
|---|---|---|
| Defined routes + filters + CSRF | current CSRF ปิด; legacy auto-route unsafe ใน CI4 | route/filter diff และ CSRF/auth negative tests ผ่าน |
| Strict validation + validated-only writes | IncomingRequest ไม่ filter อัตโนมัติ | extra-field/type-confusion tests ผ่าน |
| Transaction boundary ต่อ order/status/rating | current writes/log/side effects แยก call | failure-injection tests ไม่เกิด partial DB state |
| Atomic tracking ID + unique constraint/retry | `select_max + 1` race | parallel-create test ไม่มี duplicate |
| Replace global temp tables | current cleanup กระทบทุก request/user | concurrent tracking/import tests ไม่ปน |
| Replace PHPExcel/PHPMailer active paths | legacy codeมี removed/deprecated APIs | import/email parity + dependency audit ผ่าน |
| Session isolation/coexistence contract | CI3/CI4 config/payload อาจชนกัน | login/logout/expiry/regeneration/concurrency tests ผ่าน |
| Additive migrations + migration-runner control | shared DB ต้อง rollback route ได้ | CI3/CI4 smoke + migration/rollback rehearsal ผ่าน |

### P2

| Action | เหตุผล | Verification |
|---|---|---|
| Convert charset/collation เป็น `utf8mb4` แยก project | ลด migration variables แต่แก้ deprecated `utf8` ระยะยาว | data rehearsal, collation parity, index checks ผ่าน |
| Retire CI3 และลบ dead libraries/files | ลด attack surface หลัง no-caller proof | traffic/job/route registry ไม่มี CI3 owner |
| Performance tuning reports/imports | ทำหลัง correctness baseline เพื่อไม่ optimize behavior ผิด | approved p95/memory/row ceiling |
| พิจารณา auth product ใหม่ | ไม่ควรเปลี่ยน auth semantics พร้อม framework conversion | separate ADR/threat model/business approval |

## 17. Phase gates ที่เสนอ

| Gate | Entry | Exit evidence |
|---|---|---|
| Gate 0: Discovery complete | source snapshot ระบุ SHA | schema, routes, roles, integrations, active libraries, defects และ fixtures trace ได้ |
| Gate 1: Foundation secure | Gate 0 ผ่าน | CI4 4.7.4/PHP 8.5 build, public root, Composer lock/audit, logging และ migration runner ผ่าน |
| Gate 2: Public parity | Foundation ผ่าน | EN/TH tracking/contact parity, no shared temp table, email sandbox และ security tests ผ่าน |
| Gate 3: Identity parity | Public stable | login/session/logout/role/group/branch/menu matrix ผ่าน |
| Gate 4: Core write parity | Identity ผ่าน | order/status/log transactions, unique tracking ID, side-effect retry ผ่าน |
| Gate 5: Import/report parity | Core write stable | isolated batches, totals/export/performance และ malformed-file tests ผ่าน |
| Gate 6: Cutover ready | ทุก slice green | staging rehearsal, backup restore, route rollback, sign-off, tag/changelog พร้อม |
| Gate 7: CI3 retirement | observation window ผ่าน | no routes/jobs/data writes/dependencies/secrets อ้าง CI3 |

## 18. Open questions

| ID | คำถาม | เหตุผลที่ต้องรู้ | Owner ที่ควรตอบ |
|---|---|---|---|
| Q-01 | Production MySQL/MariaDB exact version และ engine ต่อ table คืออะไร | transaction/locking/migration behavior ขึ้นกับ engine/version | DBA/Ops |
| Q-02 | มี PK/UK/FK/index/trigger/view/routine อะไรจริง | source code ไม่แทน schema authority | DBA |
| Q-03 | `trackID` ต้องมี format/sequence ต่อเดือน/branch แบบใด และยอมมี gap หรือไม่ | เลือก atomic generator ให้ถูก business rule | Business/DBA |
| Q-04 | Production session handler, shared storage, proxy และ HTTPS termination คืออะไร | กำหนด cookie/proxy/session coexistence | Ops/Security |
| Q-05 | Route ใดถูกเรียกจาก bookmark, SMS, partner หรือ external system | ป้องกัน URL contract แตก | Product/Ops |
| Q-06 | `isTicketter()` intended truth table คืออะไร และมี caller ใด active | code condition น่าสงสัยแต่ยังต้องยืนยัน policy | Business/Security |
| Q-07 | Status IDs และ allowed transitions ที่ authoritative คืออะไร | ป้องกัน port current accidental behavior เป็น rule | Business/DBA |
| Q-08 | SMTP/SMS มี sandbox, timeout, rate limit, retry และ delivery ID หรือไม่ | ออกแบบ integration test/idempotency | Ops/Vendor |
| Q-09 | Excel max size/rows/formulas และ replay policy คืออะไร | กำหนด validation/resource ceiling | Business/Security |
| Q-10 | RTO/RPO, maintenance window และ rollback threshold เท่าไร | cutover runbook ยังไม่มีตัวเลขอนุมัติ | Operations/Business |
| Q-11 | มี cron/CLI/manual jobs นอก route inventory หรือไม่ | route-only strangler อาจพลาด writer | Ops/Engineering |
| Q-12 | Production charset/collation และ malformed Unicode มีหรือไม่ | ประเมิน `utf8mb4` work แยก | DBA |

## 19. Source index

### Official CodeIgniter

- [Server Requirements](https://codeigniter.com/user_guide/intro/requirements.html)
- [CI4 4.7.4 Changelog](https://codeigniter.com/user_guide/changelogs/v4.7.4.html)
- [Upgrading from 3.x to 4.x](https://codeigniter.com/user_guide/installation/upgrade_4xx.html)
- [URI Routing](https://codeigniter.com/user_guide/incoming/routing.html)
- [Upgrade Routing](https://codeigniter.com/user_guide/installation/upgrade_routing.html)
- [Controller Filters](https://codeigniter.com/user_guide/incoming/filters.html)
- [IncomingRequest](https://codeigniter.com/user_guide/incoming/incomingrequest.html)
- [HTTP Responses](https://codeigniter.com/user_guide/outgoing/response.html)
- [Using CodeIgniter's Model](https://codeigniter.com/user_guide/models/model.html)
- [Transactions](https://codeigniter.com/user_guide/database/transactions.html)
- [Queries and Bindings](https://codeigniter.com/user_guide/database/queries.html)
- [Validation](https://codeigniter.com/user_guide/libraries/validation.html)
- [Session Library](https://codeigniter.com/user_guide/libraries/sessions.html)
- [Database Migrations](https://codeigniter.com/user_guide/dbmgmt/migration.html)
- [Working with Uploaded Files](https://codeigniter.com/user_guide/libraries/uploaded_files.html)
- [Testing Overview](https://codeigniter.com/user_guide/testing/overview.html)
- [HTTP Feature Testing](https://codeigniter.com/user_guide/testing/feature.html)
- [Testing Your Database](https://codeigniter.com/user_guide/testing/database.html)
- [Deployment](https://codeigniter.com/user_guide/installation/deployment.html)
- [Security Guidelines](https://codeigniter.com/user_guide/concepts/security.html)
- [Security](https://codeigniter.com/user_guide/libraries/security.html)
- [Upgrade Validations](https://codeigniter.com/user_guide/installation/upgrade_validations.html)
- [Upgrade Sessions](https://codeigniter.com/user_guide/installation/upgrade_sessions.html)
- [Upgrade Migrations](https://codeigniter.com/user_guide/installation/upgrade_migrations.html)
- [Upgrade Emails](https://codeigniter.com/user_guide/installation/upgrade_emails.html)
- [Upgrade File Upload](https://codeigniter.com/user_guide/installation/upgrade_file_upload.html)
- [Upgrade Output Class](https://codeigniter.com/user_guide/installation/upgrade_responses.html)
- [Upgrade Views](https://codeigniter.com/user_guide/installation/upgrade_views.html)
- [Upgrade Security](https://codeigniter.com/user_guide/installation/upgrade_security.html)

### Official PHP, Composer และ PhpSpreadsheet

- [PHP 8.5 Migration Guide](https://www.php.net/migration85)
- [PHP 8.5 Backward Incompatible Changes](https://www.php.net/migration85.incompatible.php)
- [PHP 8.5 Deprecated Features](https://www.php.net/migration85.deprecated.php)
- [PHP 8.2 Deprecated Features](https://www.php.net/manual/en/migration82.deprecated.php)
- [PHP 8.0 Incompatible Changes](https://www.php.net/manual/en/migration80.incompatible.php)
- [PHP create_function](https://www.php.net/manual/en/function.create-function.php)
- [PHP each](https://www.php.net/manual/en/function.each.php)
- [PHP 7.2 Other Changes](https://www.php.net/manual/en/migration72.other-changes.php)
- [Composer Basic Usage](https://getcomposer.org/doc/01-basic-usage.md)
- [Composer CLI](https://getcomposer.org/doc/03-cli.md)
- [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet)
- [PhpSpreadsheet composer.json](https://github.com/PHPOffice/PhpSpreadsheet/blob/master/composer.json)

### Official MySQL

- [InnoDB Introduction](https://dev.mysql.com/doc/refman/8.4/en/innodb-introduction.html)
- [PRIMARY KEY and UNIQUE Constraints](https://dev.mysql.com/doc/refman/8.4/en/constraint-primary-key.html)
- [Locks Set by Statements](https://dev.mysql.com/doc/refman/8.4/en/innodb-locks-set.html)
- [InnoDB Deadlocks](https://dev.mysql.com/doc/refman/8.4/en/innodb-deadlocks.html)
- [Character Sets and Unicode](https://dev.mysql.com/doc/refman/8.4/en/charset.html)

### Local source anchors

| พื้นที่ | Anchors |
|---|---|
| Framework/routes | `system/core/CodeIgniter.php:58`, `application/config/routes.php:41-266` |
| Auth/session/security | `application/libraries/BaseController.php:36-78`, `application/controllers/Login.php:68-94`, `application/config/config.php:252-305` |
| Order/data integrity | `application/controllers/Order.php:726-864`, `application/models/Request_order_model.php:1343-1483` |
| Import/upload | `application/controllers/Upload_excel.php:51-66`, `application/controllers/Upload_excel.php:232-247`, `application/controllers/Upload_excel.php:390-405` |
| Dependencies | `lib/PHPExcel.php:9-32`, `lib/PHPMailer/class.phpmailer.php:28-34`, `application/helpers/cias_helper.php:30-47` |
| Migrations | `application/config/migration.php:1-40` |

## 20. Limitations

- ไม่ได้อ่าน production database, schema, table engine, index, trigger, data distribution หรือ PII.
- ไม่ได้อ่านหรือคัดลอกค่า credential; ใช้เฉพาะตำแหน่งไฟล์เพื่อ remediation.
- Working tree มีการแก้ไขจากผู้ใช้อยู่ก่อน research; findings เป็น snapshot ณ เวลาตรวจ ไม่ใช่ clean-commit baseline.
- Static scan ไม่ยืนยันว่า legacy library หรือ route ทุกตัวถูกเรียกจริง; ต้องใช้ access logs/caller tests เพิ่ม.
- ไม่ได้รัน full application บน PHP 8.5 กับ database, SMTP, SMS และ browser; `php -l` หรือ pattern scan ไม่พิสูจน์ runtime parity.
- MySQL sources อ้างอิง 8.4; ต้องยืนยัน production version ก่อนเลือก SQL syntax, online DDL และ locking assumptions.
- Estimate และ business priority ต้อง recalibrate หลังตอบ open questions, schema inventory และ characterization coverage.
