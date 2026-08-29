# รายงาน Task 6: ปิด shared runtime asset closure

งานนี้เพิ่ม tracked-asset gate แบบ runtime-driven, rerun recursive graph จาก current views/CSS, ตรึง dependency checksums และจัดทำ provenance/license evidence โดยไม่ stage, commit, push หรือเปลี่ยน frontend dependency

## สถานะ

| แกน | สถานะ | หลักฐาน |
|---|---|---|
| Runtime asset resolution | ผ่าน | 109 files มีบน disk และ recursive graph ปิดครบ |
| Recursive CSS `url()` และ `@import` | ผ่าน | ครอบ DataTables images, Font Awesome fonts, Source Sans Pro และ jQuery UI images |
| Git tracked closure | `BLOCKED` | 16 files tracked, 93 runtime files untracked |
| Source Sans Pro license | เตรียมแล้ว | `OFL.txt` จริงและ hash pinned แต่ยัง untracked |
| Version/checksum pins | ผ่านตาม CI3 active pin | dependency test 19 assertions |
| Provenance | ผ่านระดับ runtime identity | CI3 local bytes และ live versioned CDN hashes ตรง |
| Full bundled license text | `BLOCKED` บางกลุ่ม | Font Awesome, DataTables และ FixedColumns ไม่มี full license file ใน bundle |
| Focused suite | ผ่านใน clean-check simulation | `94 tests, 3115 assertions` |
| PHPStan | ผ่าน | `[OK] No errors` |
| Full PHPUnit | ผ่านใน clean-check simulation | `404 tests, 7525 assertions` |
| Full `ci-check` | ยังไม่ปิด | temporary index ทำให้ CI3 identity subprocess เห็น index ผิด repository |

สถานะรวม: `IMPLEMENTED_AWAITING_GITOPS_AND_LICENSE_DISPOSITION`

## Binding requirements ที่ยึด

- ใช้ CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` เป็น presentation authority
- ไม่ใช้เลข `106/91` จาก brief โดยไม่ rerun
- ไม่คัด plugin tree, demo, docs, source SCSS/LESS หรือ asset ที่ไม่มี caller
- ไม่ stage หรือ commit real repository index
- ไม่ upgrade หรือ replace Bootstrap, DataTables, FixedColumns, Font Awesome, html5shiv หรือ Respond.js
- รักษา duplicate font และ upload paths ที่ CSS relative graph อ้าง
- ใช้ Source Sans Pro `OFL.txt` จริง ห้ามสร้าง license text
- รายงาน provenance/license ที่ยังไม่พิสูจน์เป็น `BLOCKED`

## หลักฐาน RED

### RED รอบแรกของ parser

Test แรกปฏิเสธ absolute local URL จาก `base_url()` เพราะ helper รุ่นแรกแยก external host ไม่ถูก

```text
Tests: 1, Assertions: 8, Failures: 1
http://example.invalid/assets/bootstrap/css/bootstrap.min.css
```

แก้เฉพาะ test helper ให้ยอมรับ host เดียวกับ `base_url()` และยังปฏิเสธ protocol-relative หรือ external host

### RED รอบ recursive graph

รอบถัดไป closure ยังไม่รวม DataTables image เพราะ relative CSS reference ถูก filter ก่อน resolve parent stylesheet

```text
Tests: 1, Assertions: 800, Failures: 1
public/assets/datatables/1.10.16/images/sort_both.png
```

แก้ helper ให้ relative reference จาก parent CSS เดินต่อได้ จากนั้น mutation target ของ `url()` และ `@import` ถูกขับจริง

### RED ที่ต้องการตาม brief

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/MenuHttpTest.php tests/ci4/PasswordResetPageHttpTest.php \
  tests/ci4/ContactHttpTest.php tests/ci4/PublicTrackingHttpTest.php \
  tests/ci4/AccessDeniedHttpTest.php
```

```text
Tests: 94, Assertions: 3115, Failures: 1
```

Failure เดียวมาจาก `git ls-files --error-unmatch` ซึ่งรายงาน 93 runtime files และ Source Sans Pro `OFL.txt` ว่ายังไม่ tracked ตรงตาม contract ของ Task 6

## การเปลี่ยนแปลง

### Tracked-asset gate

เพิ่ม `MenuHttpTest::testSharedRuntimeAssetClosureExistsAndIsGitTracked`

- render admin, auth, Contact, Tracking, public non-legacy และ error profiles จริง
- เก็บ `src` และ `href` จาก rendered HTML
- เก็บ `url()` จาก inline style
- เดิน CSS `url()` และ `@import` แบบ recursive
- fail เมื่อ reference เป็น external host, path ออกนอก `public/`, file หาย หรือ Git ไม่รู้จัก path
- ใช้ `ROOTPATH` และ repository-relative path ไม่มี hardcoded absolute path
- ตรวจ `OFL.txt` เป็น required evidence file เพิ่มจาก runtime closure

### Dependency pins

เพิ่ม `MenuHttpTest::testSharedFrontendDependencyPinsMatchCi3RuntimeArtifacts`

- pin Bootstrap CSS/JavaScript
- pin DataTables และ FixedColumns CSS/JavaScript
- pin Font Awesome 4.2.0, 4.3.0 และ 4.7.0 entrypoints
- pin html5shiv 3.7.2 และ Respond.js 1.4.2
- pin Source Sans Pro stylesheet และ `OFL.txt`
- assert version headers ที่ใช้ตัดสิน active runtime version

### Evidence

สร้าง `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md`

เอกสารแยก must-add, already tracked, must-not-add, version, checksum, provenance, license, RED/GREEN และ blockers

## Graph counts

| รายการ | จำนวน |
|---|---:|
| Rendered runtime closure | 109 |
| Tracked runtime files | 16 |
| Untracked runtime files | 93 |
| License evidence นอก runtime graph | 1 |
| Must-add รวม | 94 |
| Disk files ที่ตั้งใจไม่อยู่ใน runtime graph | 2 |

สองไฟล์นอก runtime graph คือ:

- `public/assets/css/admin.css` ซึ่ง tracked อยู่ก่อนแล้วและ current profiles ไม่อ้าง
- `public/assets/fonts/source-sans-pro/OFL.txt` ซึ่งเป็น required license evidence ไม่ใช่ browser runtime file

## Exact must-add groups

| จำนวน | กลุ่ม |
|---:|---|
| 8 | `public/assets/bootstrap/` |
| 1 | `public/assets/css/multifreezer.css` |
| 7 | `public/assets/datatables/1.10.16/` |
| 2 | `public/assets/datatables-fixedcolumns/3.2.4/` |
| 6 | `public/assets/dist/` |
| 6 | `public/assets/font-awesome/` |
| 6 | `public/assets/font-awesome/4.3.0/` |
| 6 | `public/assets/font-awesome/4.7.0/` |
| 6 | `public/assets/fontawesome/` |
| 9 | `public/assets/fonts/source-sans-pro/` รวม `OFL.txt` |
| 1 | `public/assets/html5shiv/3.7.2/` |
| 5 | untracked runtime images ใต้ `public/assets/images/` |
| 6 | root JavaScript files ใต้ `public/assets/js/` |
| 20 | `public/assets/js/jquerydatepicker/` |
| 1 | `public/assets/respond/1.4.2/` |
| 4 | `public/uploads/web/` |

รายชื่อสมาชิก exact และ checksum อยู่ใน `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md`

## Version และ provenance findings

### Bootstrap conflict

Brief ระบุ Bootstrap 3.3.4 แต่ active CI3 artifact headers ระบุ Bootstrap 3.3.7 และ local CI4 bytes ตรง CI3 ทุกไฟล์ที่ตรวจ

การตัดสินใจ:

- ใช้ active CI3 pin 3.3.7 ตาม authority order
- ไม่ downgrade เป็น 3.3.4
- บันทึก conflict แทนการแก้ dependency โดยไม่มี human decision

### CDN-to-local identity

fetch live versioned URLs นอก sandbox เพราะ sandbox บล็อก `cdn.datatables.net`

- DataTables 1.10.16 CSS, JavaScript และ image 5 files มี SHA-256 ตรง local ทุกไฟล์
- FixedColumns 3.2.4 CSS และ JavaScript มี SHA-256 ตรง local ทั้งสองไฟล์
- ไม่มี version upgrade หรือ behavior replacement

### License status

| กลุ่ม | ผล |
|---|---|
| Source Sans Pro | ผ่าน มี `OFL.txt` จริง hash `fce9f9e...` |
| Font Awesome | `BLOCKED` เพราะมีเพียง header สรุป SIL OFL/MIT ไม่มี full bundled license text |
| DataTables | `BLOCKED` เพราะมีเพียง URL `datatables.net/license` ไม่มี full bundled license text |
| FixedColumns | `BLOCKED` เพราะมีเพียง URL `datatables.net/license` ไม่มี full bundled license text |
| Bootstrap, html5shiv, Respond.js | runtime identity ผ่าน แต่ full bundled license text ไม่ได้ถูกเพิ่มใน Task 6 |

ไม่มีการสร้างหรือดาวน์โหลด license file ใหม่ลง repository

## Clean-check simulation

ข้อห้ามไม่ให้ stage real index ทำให้ tracked gate ไม่สามารถ GREEN บน real index ก่อน gitops ได้ จึงใช้ Git index ชั่วคราวใต้ `$TMPDIR`

ขั้นตอน:

1. สร้าง temporary index จาก `HEAD` ด้วย `git read-tree HEAD`
2. add เฉพาะ 94 must-add files และ test file เข้า temporary index
3. รัน PHPUnit โดยส่ง `GIT_INDEX_FILE` ให้ subprocess
4. เปรียบเทียบ real index tree ก่อนและหลัง

ผล:

```text
OK (94 tests, 3115 assertions)
real-index-unchanged=yes
```

นี่พิสูจน์ว่า gate GREEN เมื่อ exact closure ถูก tracked โดยไม่ stage งานจริง

## ผลทดสอบ

### Dependency pin focused

```text
OK (1 test, 19 assertions)
```

### Focused asset graph ด้วย real index

```text
Tests: 94, Assertions: 3115, Failures: 1
```

Failure เดียวคือ tracked closure ยังไม่ commit

### Focused asset graph ด้วย temporary index

```text
OK (94 tests, 3115 assertions)
real-index-unchanged=yes
```

### PHPStan

```bash
vendor/bin/phpstan analyse --configuration phpstan.neon.dist \
  --no-progress --memory-limit=512M
```

```text
[OK] No errors
```

### Full PHPUnit ด้วย temporary index

```text
OK (404 tests, 7525 assertions)
real-index-unchanged=yes
```

### Full ci-check

รอบ sandbox หยุดที่:

```text
grep: .env.example: Operation not permitted
FAIL: CI4 loopback port placeholder is missing
```

ความหมาย: sandbox อ่าน `.env.example` ไม่ได้ ไม่ใช่ code failure

รอบนอก sandbox ผ่าน shell, dependency, PHPStan, lint, Docker aliases, DB isolation, environment placeholders, concurrency workers และ schema-only gates แล้วหยุดที่:

```text
FAIL CI3 identity pin=ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6 dirty=True
```

สาเหตุคือ full script สืบทอด temporary `GIT_INDEX_FILE` ของ CI4 ไปยัง subprocess ที่ตรวจ CI3 จึงอ่าน index ผิด repository ตรวจ `git status --short` ของ CI3 แยกแล้วไม่มี output

Full `ci-check` ต้อง rerun หลัง gitops commit closure จริงโดยไม่ใช้ temporary index

### Diff hygiene

```text
git diff --check
```

ไม่มี output

## Coverage self-check

- ลบ tracked check ออกทำให้ real-index RED หาย จึง test ผูกกับ `git ls-files --error-unmatch` จริง
- ตัด recursive CSS traversal ทำให้ DataTables image, Source Sans Pro font และ jQuery UI image assertions RED
- ตัด `@import` traversal ทำให้ Source Sans Pro และ Font Awesome 4.7.0 imported graph หาย
- ตัด `url()` traversal ทำให้ Bootstrap fonts, DataTables images และ uploads web หาย
- เปลี่ยน checksum หรือ version header ใดใน pin table ทำให้ dependency test RED
- HTML roots มาจาก runtime render ไม่ใช่ hardcoded list ของ vendor tree
- Dynamic application routes ที่ไม่ใช่ static file เช่น `/background-image/<hash>.png` ไม่ถูกบังคับเข้า Git closure

## Self-review

- แก้เฉพาะ `tests/ci4/MenuHttpTest.php` และเอกสาร Task 6
- ไม่แก้ production view, CSS, JavaScript หรือ binary asset bytes
- ไม่ stage, commit, push, reset, clean หรือ force push
- real index hash ไม่เปลี่ยนระหว่าง temporary-index simulations
- ไม่คัด `assets/plugins/**`, demo, docs, SCSS, LESS, specimen หรือ examples
- ไม่รวม `multifreezer.js` หรือ `cms-logo.png`
- ไม่ dedupe Font Awesome paths หรือ uploads desktop/mobile paths
- ไม่สร้าง abstraction หรือ production helper ใหม่
- ไม่อ้าง browser, DOM normalization, JavaScript interaction หรือ visual PASS
- ไม่อ้าง full `ci-check` PASS ก่อน real checkpoint commit

## Blockers และไม้ต่อ

1. gitops ต้อง add และ commit exact 94 must-add files
2. หลัง commit ต้อง rerun focused suite, full PHPUnit และ full `ci-check` บน real index
3. Font Awesome, DataTables และ FixedColumns ต้องมี human disposition เรื่อง full license text ก่อนเปลี่ยนสถานะจาก `BLOCKED`
4. Bootstrap 3.3.4 ใน brief ขัดกับ active pin 3.3.7 ต้องใช้ active pin ต่อ หรือรับ human decision หากต้องการเปลี่ยน
5. ก่อน checkpoint ต้อง rerun graph อีกครั้งหาก Task 5 layout files เปลี่ยนหลังรายงานนี้

## Fix round 1/5: ปิด review findings

รอบนี้แก้ Critical 1 และ Important 1-4 จาก `task-6-review.md` โดยไม่ stage, commit, push หรือเปลี่ยน real Git index

### สถานะหลังแก้

| แกน | ผล |
|---|---|
| Runtime closure | 109 files: tracked 16, untracked 93 |
| Style attribute | รวม `public/assets/images/bg-login.jpg` แล้ว |
| Required index inputs | 118 paths |
| Exact checkpoint candidate | 103 files |
| `public/assets/css/main.css` | อยู่ใน candidate และ index-blob gate |
| `public/assets/css/admin.css` | exclude จาก candidate |
| License evidence | Source Sans Pro 1 file และ license set ใหม่ 6 files |
| Upload provenance | `BLOCKED`; ไม่พบ durable archive identifier/path ใน repo |
| Real index | ไม่เปลี่ยนทุก temporary-index run |

### Parser และ graph fixes

- แยก regex ของ quoted `@import` กับ `url()` เพื่อไม่กิน reference ถัดไป
- scan CSS ใน `style="..."` และ assert `bg-login.jpg` ตรง
- ตัด `data:` แบบ case-insensitive
- เทียบ absolute origin ด้วย exact scheme, host และ port
- รับ HTML entrypoint เฉพาะ `/assets/` และ `/uploads/web/`
- strip query/fragment และ fail เมื่อ traversal ออกนอก `public/`
- เพิ่ม direct regression ของ CSS cycle และ mutation-check cycle guard

RED แรกมี `11 tests, 6 failures`; failuresตรง style attribute, parser, scheme, port, dynamic prefix และ traversal leave/re-enter หลังแก้ผ่าน `OK (11 tests, 79 assertions)`

### Index-blob gate

Tracked gate ตรวจสองชั้น:

1. `git ls-files --error-unmatch` ยืนยันทุก required path อยู่ใน index
2. `git diff --quiet --no-ext-diff` ยืนยัน index blob ตรง worktree สำหรับทุก required input

Temporary index ที่ add ทุก candidate ยกเว้น `main.css` ล้มตรง:

```text
Git index blob differs from worktree runtime input.
real-index-unchanged=yes
```

Temporary index ที่ add exact candidate 103 files ผ่าน closure gate `OK (1 test, 1669 assertions)` และ real index ไม่เปลี่ยน

### Checksum และ provenance

- เพิ่ม DataTables sort image 5 hashesเข้า automated dependency test
- mutation `sort_both.png` ทำให้ test RED ตรง expected/actual hash
- บันทึก exact CDN URL, retrieval command, วันที่ `2026-08-27`, HTTP `200` และ SHA-256 ครบ 9 artifacts
- อ้าง prior evidence ด้วย exact path และ section ใน evidence v1
- downgrade upload 4 files เป็น `BLOCKED` เพราะคำว่า archived snapshot เดิมไม่มี durable path

### License closure

เพิ่ม full license filesจาก exact upstream tags เมื่อ upstream bundle มีไฟล์จริง:

- Bootstrap 3.3.7
- DataTables 1.10.16
- FixedColumns 3.2.4
- Respond.js 1.4.2

Font Awesome 4.2/4.3/4.7 และ html5shiv 3.7.2 ไม่มี standalone full license text ใน exact tags จึง map declaration จาก exact tag ไปยัง full canonical `MIT.txt` และ `OFL-1.1.txt` จาก SPDX `license-list-data` tag `v3.27.0` โดย pin source URL, tag commit และ local hash

html5shiv ใช้ MIT option ของ dual MIT/GPL2 declaration จึงไม่ bundle GPL text ที่ทำให้ repository phone-like PII guard false-positive

Dependency/license gate ผ่าน `OK (1 test, 30 assertions)`

### Exact candidate 103 files

| จำนวน | กลุ่ม |
|---:|---|
| 93 | untracked runtime assets |
| 1 | Source Sans Pro `OFL.txt` |
| 6 | `public/assets/licenses/` |
| 1 | `public/assets/css/main.css` |
| 1 | `tests/ci4/MenuHttpTest.php` |
| 1 | `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md` |

Evidence ระบุ exact member groups และ recursive image listครบที่ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md`

### Verification รอบสุดท้าย

| Gate | ผล |
|---|---|
| Parser regressions | `OK (11 tests, 79 assertions)` |
| Closure + dependency pins | `OK (2 tests, 1699 assertions)` |
| Focused asset suites | `OK (105 tests, 3894 assertions)` |
| PHPStan | `[OK] No errors` |
| Full PHPUnit | `OK (415 tests, 8312 assertions)` |
| Full `scripts/ci-check.sh` | ผ่านทุก gate |
| Secret/PII | `PASS secret file policy`, `PASS candidate-tree PII guard` |
| `git diff --check` | ผ่าน ไม่มี output |
| Real index unchanged | `yes` |

Full `ci-check` ใช้ wrapper ชั่วคราว unset `GIT_INDEX_FILE` เฉพาะ subprocess ที่ตรวจ CI3 checkout ป้องกัน CI4 temporary index รั่วไปทำให้ CI3 dirty ลวง โดยไม่แก้ production script

### Coverage self-check รอบแก้

- ลบ style-attribute scan ทำให้ direct test และ closure count 109 RED
- คืน parser regex เดิมทำให้ quoted import/multiple URL/DATA regression RED
- เทียบเฉพาะ host ทำให้ scheme/port origin tests RED
- ใช้ `str_contains` ทำให้ dynamic-prefix test RED
- ยอม traversal leave/re-enter ทำให้ direct traversal test RED
- ทำ cycle guard fail เมื่อ revisit ทำให้ cycle test RED
- เปลี่ยน DataTables image hash ทำให้ dependency test RED
- ไม่ add `main.css` เข้า temporary index ทำให้ index-blob gate RED

### Blocker ที่เหลือ

Upload snapshot provenance 4 filesยังเป็น `BLOCKED` จนมี durable repo evidence หรือ archive identifier/path ที่ตรวจซ้ำได้ Blocker นี้ไม่ถูกใช้เป็น provenance PASS และไม่กลบ runtime checksum/closure result

## Fix round 2/5: ปิด upload checksum และ CI3 provenance

รอบนี้แก้เฉพาะ `tests/ci4/MenuHttpTest.php` และ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md` โดยไม่เปลี่ยน production asset, ไม่ stage, commit หรือ push

### Upload checksum pins

`MenuHttpTest::testSharedFrontendDependencyPinsMatchCi3RuntimeArtifacts` pin SHA-256 โดยตรงครบทั้ง 4 target:

| CI4 target | SHA-256 |
|---|---|
| `public/uploads/web/contact_laptop.png` | `2520b9e21373a7822bf2388cd043684a8e0bcdc41071c6a562d539964e7f038f` |
| `public/uploads/web/contact_mobile.png` | `2520b9e21373a7822bf2388cd043684a8e0bcdc41071c6a562d539964e7f038f` |
| `public/uploads/web/track_laptop.png` | `16b99ac15ba78c5dd6a462de19b8c349747b7621301a7a1cb3858e09753c813a` |
| `public/uploads/web/track_mobile.png` | `16b99ac15ba78c5dd6a462de19b8c349747b7621301a7a1cb3858e09753c813a` |

การเปลี่ยน bytes ของ target ใดทำให้ expected SHA-256 ไม่ตรงและ test RED โดยตรง

### Provenance ที่ตรวจซ้ำได้

CI3 authority คือ pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`:

| CI4 targets | CI3 source | Inventory v2 | ผล |
|---|---|---|---|
| `contact_laptop.png`, `contact_mobile.png` | `assets/images/bg-contact.png` | JSON `:2788-2793` | ทั้งคู่ byte-identical |
| `track_laptop.png`, `track_mobile.png` | `assets/images/bg-tracking.png` | JSON `:2844-2849` | ทั้งคู่ byte-identical |

คำสั่ง reproducible:

```bash
git show ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6:assets/images/bg-contact.png | shasum -a 256
git show ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6:assets/images/bg-tracking.png | shasum -a 256
```

ผลตรงกับ pins ข้างต้นและ `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.md:3-12`; สถานะ provenance upload เปลี่ยนจาก `BLOCKED` เป็นพิสูจน์แล้ว

### Verification

| Gate | ผล |
|---|---|
| Upload/dependency checksum test | `OK (1 test, 34 assertions)` |
| Focused asset suites บน temporary index candidate 103 files | `OK (105 tests, 3898 assertions)` |
| PHPStan | `[OK] No errors` |
| Full PHPUnit บน temporary index candidate 103 files | `OK (415 tests, 8316 assertions)` |
| Full `scripts/ci-check.sh` บน temporary index candidate 103 files | ผ่านทุก gate |
| Real index | `real-index-unchanged=yes` ทุก temporary-index run |
| `git diff --check` | ผ่าน ไม่มี output |

Candidate count คง `103` files ไม่มีไฟล์เพิ่ม และไม่เปลี่ยน production assets
