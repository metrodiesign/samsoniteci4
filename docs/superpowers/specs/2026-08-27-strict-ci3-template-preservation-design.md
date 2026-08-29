# การรักษา Template CI3 แบบเคร่งครัดบน CI4

เอกสารนี้แปลง migration contract ของผู้ใช้เป็นขอบเขตระบบและกติกาการลงมือใน repository ปัจจุบัน โดย CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` เป็น presentation authority สูงสุด

## เป้าหมาย

CI4 ต้องรักษา behavior และ browser output ของ CI3 สำหรับ route, layout, view, DOM, CSS, JavaScript, assets และ frontend dependencies โดยเปลี่ยนเฉพาะ backend integration ที่ CI4 บังคับ

เกณฑ์ปิดโครงการ:

| แกน | เกณฑ์ |
|---|---|
| Functional parity | 100% |
| Business parity | 100% |
| Template traceability | 108/108 tracked CI3 template files |
| Required template coverage | 100% |
| Unapproved UI difference | 0 |
| Unapproved dependency upgrade | 0 |
| Unapproved template replacement | 0 |
| Unapproved redesign | 0 |

## Authority และขอบเขต

ลำดับ authority:

1. CI3 runtime behavior ที่ capture ด้วย state เดียวกัน
2. CI3 source ที่ pin ไว้
3. signed defect disposition หรือ human decision
4. CI4 compatibility requirement
5. maintainability

ห้ามใช้ CI4 scaffold, modern frontend pattern หรือความเห็นว่า dependency เก่าเป็นเหตุเปลี่ยน presentation contract

Backend ของ CI4 เปลี่ยนได้เฉพาะ route, controller, store, validation, session, authentication, authorization, transaction, logging, error handling และ test infrastructure โดย user-visible behavior ต้องไม่เปลี่ยนหากไม่มี disposition

## Root cause ที่ design นี้แก้

CI4 เดิมย้าย business behavior แต่สร้าง presentation layer ใหม่แบบ approximation แทนรักษา CI3 view hierarchy และ dependency graph ผลคือ visual gate รุ่นแรกตรวจเพียง heading, table heading และ viewport width แต่ไม่พิสูจน์ source traceability, full DOM หรือ JavaScript behavior

แนวแก้คือย้าย ownership กลับไปที่ CI3 template และให้ CI4 backend ปรับข้อมูลเข้าหา template ไม่ใช่แก้ template ให้เข้าหา backend

## Architecture

### Backend operation layer

Controller และ service ของ CI4 รับผิดชอบ security, validation, transaction และ persistence ต่อไป ห้ามคัดลอก backend defect จาก CI3 หากมี signed correction

Public interfaces ที่ต้องคง:

- route, method, query parameter และ POST parameter ที่ผู้ใช้เห็น
- redirect sequence และ status code
- validation timing และข้อความ
- sort, filter, pagination และ default value
- authorization outcome และ content negotiation

### Presentation compatibility layer

`BaseController::layout()` และ controller-specific render method สร้าง CI3-compatible view model ก่อน render

View model ต้องส่งข้อมูลที่ CI3 template เคยอ่าน เช่น:

- `pageTitle`
- `name`
- `role_text`
- `last_login`
- `GroupID`
- `BranchID`
- `BranchName`
- menu groups และ menu items
- branch autocomplete options
- page content

ห้ามให้ view query DB เองแบบ CI3 หากแปลงเป็น data adapter ฝั่ง controller ได้ เพราะ output contract ไม่ต้องเปลี่ยน

### Layout profiles

ใช้ profile ตาม CI3 source ไม่รวมทุกหน้าไว้ใน layout เดียว:

| Profile | CI3 source | CI4 target |
|---|---|---|
| Admin | `application/views/includes/header.php` + `footer.php` | `app/Views/layout.php` และ partial ที่คัดจาก source |
| Order | `application/views/includes/header_order.php` + `footer_order.php` | order-specific layout/partial |
| Public EN/TH | `application/views/web/header.php`, `header_th.php`, `footer.php` | `app/Views/layout_public.php` |
| Standalone auth | `login.php`, `forgotPassword.php`, `newPassword.php` | standalone CI4 views |
| Email | `application/views/email/resetPassword.php` | CI4 reset email template |
| Framework errors | `application/views/errors/**` | CI4 framework error views หรือ evidence disposition |

Admin layout ปัจจุบันที่ใช้ custom `<aside class="sidebar">` และ inline SVG ไม่ใช่ CI3 DOM contract ต้องแทนด้วย CI3 AdminLTE hierarchy ที่ adapt helper/data เท่านั้น

### Asset closure

คัดเฉพาะ runtime dependency graph ที่มี caller จาก CI3:

- ใช้ exact library/version เดิม
- CDN เดิมที่ไม่เหมาะกับ deterministic capture เปลี่ยนเป็น local mirror ได้เมื่อ library, version และ checksum ตรง
- เก็บ stylesheet-relative duplicate เมื่อจำเป็นต่อ path resolution
- ไม่คัด demo, docs, source SCSS/LESS หรือ plugin tree ที่ไม่มี caller
- ทุก bundle ต้องมี version, checksum, source/provenance และ license evidence

Frontend Dependency Upgrade และ Frontend Dependency Replacement ต้องเป็น `NONE` เว้นแต่มี human decision

### Template inventory

Canonical denominator คือ tracked files ใต้ `application/views/**` ทั้ง `.php` และ `.html` รวม 108 ไฟล์

แต่ละ record ต้องมีสถานะเดียวจาก:

- `MIGRATED_AS_IS`
- `ADAPTED_FOR_CI4`
- `COMPATIBILITY_SHIM`
- `NOT_USED_WITH_EVIDENCE`
- `BLOCKED`

Target candidate ไม่ใช่ migration proof และ HTTP test ผ่านไม่ใช่ template parity proof

## Work package

| WP | Scope | จำนวน CI3 files | Dependency |
|---|---|---:|---|
| TPL-00 | Inventory, scenario catalog และ verification harness | 108 denominator | ไม่มี |
| TPL-01 | Shared admin/order/public layouts และ partials | 7 | TPL-00 |
| TPL-02 | Standalone auth และ reset email | 4 | TPL-01 public assets บางส่วน |
| TPL-03 | Public Contact, Tracking และ Rating | 8 | TPL-01 |
| TPL-04 | Admin core, users, access และ application 404 | 9 | TPL-01 |
| TPL-05 | Master, menu, background และ contact listing | 37 | TPL-01 |
| TPL-06 | Order lifecycle, forms และ print | 10 | TPL-01 order profile |
| TPL-07 | Import และ preview | 6 | TPL-01 |
| TPL-08 | Reports และ export documents | 12 | TPL-01 |
| TPL-09 | Framework errors และ static HTML dispositions | 15 | TPL-00 |

จำนวนรวม 108 ไฟล์ ไม่มีไฟล์ตก denominator

## Verification gates

### Gate 0: Source identity

บันทึก CI3 SHA, CI4 SHA, image digest, compose hash, DB version, PHP version, CI version, browser version, timezone, locale, viewport และ DPR

Working tree ที่ dirty ใช้พัฒนาได้ แต่ห้ามใช้สร้าง release-grade PASS evidence จนมี reviewed checkpoint commit

### Gate 1: Scenario catalog

หนึ่ง scenario ต่อ observable page state ไม่ใช่หนึ่ง alias route

แต่ละ row ต้องมี:

- CI3 และ CI4 route/method
- role, group และ branch
- fixture IDs
- query หรือ form input
- expected status/final URL
- wait selector
- interaction IDs
- template sources
- disposition

Denominator ต้องมี owner/disposition ครบก่อน capture

### Gate 2: Same data/state

Restore synthetic fixtures รุ่นเดียวกันใน CI3 และ CI4 แล้วตรวจ row count/hash ต่อ table รวม side-effect tables

DB reset เป็น destructive local operation ต้องได้รับ human confirmation ก่อนรัน

### Gate 3: Functional and HTTP

รัน focused tests, full PHPUnit, PHPStan และ `scripts/ci-check.sh` ต่อ checkpoint

ผลผ่านต้องไม่กลบ behavior ที่ยังไม่มี CI3 comparator เช่น malformed report date หรือ Rating contract

### Gate 4: DOM

เก็บ raw HTML และ normalized DOM คู่ CI3/CI4

อนุญาต normalize เฉพาะ nondeterministic infrastructure value เช่น CSRF token, session token, approved origin และ runtime-generated identifier

ห้าม normalize business text, class, visible date, row order, field/default, menu หรือ status

Allowlist ต้องระบุ page, selector, attribute, reason และ decision ID และต้อง fail หาก rule ไม่ถูกใช้

### Gate 5: JavaScript interaction

ต้องขับ modal, validation, DataTables, FixedColumns, search, sort, pagination, upload preview, report filter/export, reset single-use flow และ page-specific interactions จริง

การโหลด script สำเร็จแต่ widget ไม่ทำงานถือว่า FAIL

### Gate 6: Visual

Capture คู่ใน run เดียวกันที่ `1440x900` และ `390x844`, DPR 1, browser/locale/timezone/state เดียวกัน

ใช้ Pillow `ImageChops.difference` ตรวจ dimension และ unmatched pixel ค่า default 0 หลัง approved mask เท่านั้น

### Gate 7: Traceability closure

ต่อ scenario ต้องมี source mapping, command, runtime result, DOM diff, interaction result, screenshot pair, visual diff, network/asset result, side-effect result และ hashes

ขาด axis ใดให้ใช้ `NOT-VERIFIED` หรือ `BLOCKED` ห้ามใช้ `PASS`

## Known functional blockers

| Blocker | สถานะ | การปิด |
|---|---|---|
| Tracking whitespace | working tree แก้ no-trim แล้ว แต่ไม่มี direct regression | เพิ่ม known-ID leading/trailing-space test และ rebuild runtime |
| Route alias test | fixture ขาด `db_branch` | เพิ่ม table/row fixture ให้ focused test ผ่าน |
| Reports malformed date | CI3 contract ยังไม่ capture | capture CI3 ก่อน preserve หรือขอ signed correction |
| Rating contract | public 2-score/5-block กับ authenticated 8-score ยังไม่มี signed choice | runtime evidence แล้วขอ human decision |
| Function disposition | after evidence ยัง 0/1165 | regenerate ledger จาก execution evidence |
| WP00C | 51/53 | ปิด `RPT-EDGE-001` และ `PERF-CI3-001` |
| Fixture state | expected 116 แต่พบ 154 rows | human-approved local synthetic reset |

## Git และ review

ทุก WP ทำตามลำดับ:

1. failing test หรือ failing comparator
2. minimal compatibility change
3. focused verification
4. full automated gate
5. runtime/DOM/visual evidence
6. auditor
7. verifier
8. reviewer
9. checkpoint commit บน feature branch

ห้าม push direct, force push, reset hard, clean work product หรือ commit ก่อน review

## สิ่งที่ไม่ทำ

- ไม่ modernize frontend
- ไม่เพิ่ม framework หรือ design system
- ไม่ upgrade dependency เพราะเก่า
- ไม่ rewrite jQuery
- ไม่ refactor CSS/HTML เพื่อความสะอาด
- ไม่คัด asset tree ที่ไม่มี caller
- ไม่ใช้ภาพ WP03F/WP03G เก่าปิด current PASS
- ไม่ประกาศ migration เสร็จจาก test ผ่านเพียงแกนเดียว
