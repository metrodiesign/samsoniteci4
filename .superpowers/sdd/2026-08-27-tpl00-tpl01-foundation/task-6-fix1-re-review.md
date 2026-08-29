# Re-review Task 6 fix round 1

เอกสารนี้ทบทวน fix round 1 แบบ read-only เทียบ brief, design contract, review เดิม, current diff package, repository state และผลรันจริง โดยไม่แก้ source, stage หรือ commit

## คำตัดสิน

| แกน | คำตัดสิน | เหตุผลหลัก |
|---|---|---|
| Findings เดิม | 4 `ADDRESSED`, 1 `NOT ADDRESSED` | temp-index, style/count, parser และ license closure ปิดแล้ว แต่ upload checksum/provenance ยังไม่ปิด |
| New Critical/Important | ไม่มี finding class ใหม่ | ประเด็น upload เป็นส่วนที่ยังเปิดของ finding เดิมข้อ 5 |
| Approved candidate count | **0 files** | binding provenance/checksum requirement ยังไม่ครบ จึงห้ามใช้ checkpoint message `t6 shared assets passed` |
| Provisional candidate หลังแก้ | **103 files** | จำนวนและ exact set เดิมถูกต้อง ไม่ต้องเพิ่มไฟล์ใหม่หากแก้เฉพาะ test/evidence ที่อยู่ใน candidate แล้ว |
| Final verdict | **REQUEST CHANGES** | ต้อง pin upload 4 paths และแก้ provenance evidence ให้ชี้ CI3 pin ที่ตรวจซ้ำได้ก่อน gitops |

## Verdict ราย finding เดิม

| ข้อ | Severity เดิม | สถานะ | หลักฐานสรุป |
|---:|---|---|---|
| 1 | Critical | `ADDRESSED` | index-blob gate จับ `main.css` ที่ไม่ถูก add และ exact temporary index 103 files ผ่าน |
| 2 | Important | `ADDRESSED` | scan `style` attribute แล้ว, `bg-login.jpg` อยู่ใน closure และ count 109/118/103 ตรง |
| 3 | Important | `ADDRESSED` | parser origin/path/query/fragment/traversal/cycle และ direct regressions ผ่าน |
| 4 | Important | `ADDRESSED` | license set 7 filesครบ, hash pinned, mapping MIT/OFL ถูกกับ local declarations |
| 5 | Important | `NOT ADDRESSED` | DataTables images ปิดแล้ว แต่ upload 4 paths ไม่มี direct checksum pin และ provenance evidence ระบุ blocker ผิดเหตุ |

### Finding 1: temp-index false-green และ `main.css`

สถานะ `ADDRESSED`

- `tests/ci4/MenuHttpTest.php:287-327` ตรวจทั้ง path อยู่ใน index และ index blob ตรง worktree
- exact temporary index 103 files ผ่าน `OK (1 test, 1669 assertions)`
- temporary index 102 filesที่จงใจไม่ add `public/assets/css/main.css` ล้มด้วย `Git index blob differs from worktree runtime input.`
  - ความหมาย: gate จับกรณี index ยังถือ `main.css` blob เก่าได้จริง
- real index hash ก่อนและหลัง simulation เท่ากัน และ `git diff --cached --name-only` ว่าง
- `public/assets/css/main.css:614` ใช้ `contact_mobile.png` แล้ว จึงไม่เหลือ clean-check mismatch แบบ review เดิม

### Finding 2: style attribute, `bg-login.jpg` และ count

สถานะ `ADDRESSED`

- `tests/ci4/MenuHttpTest.php:330-345` มี direct regression สำหรับ style attribute และหลาย CSS references
- `tests/ci4/MenuHttpTest.php:744-774` scan `src`, `href`, `<style>` และ `style="..."`
- `app/Views/login.php:15` และ `app/Views/forgot_password.php:15` เป็น caller ของ `bg-login.jpg`
- closure test assert 109 files และ assert `public/assets/images/bg-login.jpg` โดยตรงที่ `tests/ci4/MenuHttpTest.php:272-284`

Arithmetic ปัจจุบันถูกต้อง:

| รายการ | จำนวน | สูตร |
|---|---:|---|
| Runtime closure | 109 | tracked 16 + untracked 93 |
| Required index inputs | 118 | runtime 109 + license evidence 7 + test 1 + evidence 1 |
| Untracked asset/license inputs | 100 | runtime untracked 93 + Source Sans Pro license 1 + license set 6 |
| Exact checkpoint candidate | 103 | untracked 100 + `main.css` 1 + test 1 + evidence 1 |

### Finding 3: parser edge cases และ direct regressions

สถานะ `ADDRESSED`

- `tests/ci4/MenuHttpTest.php:338-410` ครอบ quoted `@import`, หลาย `url()`, case-insensitive `DATA:`, scheme/host/port mismatch, protocol-relative URL, exact static prefix, query/fragment, traversal และ CSS cycle
- `tests/ci4/MenuHttpTest.php:814-830` แยก capture groups ของ `@import` และ `url()` โดยไม่กิน reference ถัดไป
- `tests/ci4/MenuHttpTest.php:833-874` เทียบ exact scheme/host/port, จำกัด HTML entrypoint ที่ `/assets/` และ `/uploads/web/`, decode path และ fail เมื่อ traversal ออกจาก `public/`
- parser regressions ผ่าน `OK (11 tests, 79 assertions)`
- parser รวม dependency/license test ผ่าน `OK (12 tests, 109 assertions)`
- asset candidate ไม่มี symlink จึงไม่มี symlink escape ใน current set

ไม่พบ false negative ใหม่ใน current rendered profiles หรือ recursive CSS graph

### Finding 4: mandatory license closure

สถานะ `ADDRESSED`

License evidence 7 filesครบ:

- Source Sans Pro ใช้ upstream `OFL.txt` ที่มี copyright statement และ full OFL text
- Bootstrap 3.3.7, DataTables 1.10.16, FixedColumns 3.2.4 และ Respond.js 1.4.2 ใช้ exact bundle license files
- Font Awesome 4.2/4.3/4.7 local CSS headersระบุ CSS เป็น MIT และ fonts เป็น SIL OFL 1.1 จึง map ไป canonical SPDX `MIT.txt` และ `OFL-1.1.txt` ได้ตรง declaration
- html5shiv 3.7.2 local header ระบุ dual `MIT/GPL2`; การเลือก MIT option ถูกต้องและไม่ต้อง bundle GPL2 พร้อมกัน
- SPDX files ถูกใช้เป็น canonical license text เท่านั้น ไม่ถูกอ้างเป็น provenance ของ runtime bytes

Automated gate ที่ `tests/ci4/MenuHttpTest.php:435-442` pin SHA-256 ของ license files ทั้ง 6 และ Source Sans Pro licenseถูก pinที่ `tests/ci4/MenuHttpTest.php:434`

DataTables และ FixedColumns local license contentsมี upstream copyright/permission textครบ และตรง status/hash ที่บันทึกใน `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:181-190`

`public/assets/licenses/MIT.txt` มี canonical placeholder `<year> <copyright holders>` ตาม SPDX text ไม่ใช่ fabricated project copyright; local Font Awesome/html5shiv declarations เป็นตัวผูก license identifier กับ artifacts

Full `scripts/ci-check.sh` ผ่าน `secret file policy` และ `candidate-tree PII guard` กับ exact candidate 103 files จึงไม่พบ secret หรือ PII policy violation ใน license files

ข้อจำกัดของ re-review นี้คือ upstream network fetch ถูกปฏิเสธ จึงไม่ได้ fetch URLs ซ้ำ แต่ exact URLs, tags, commits, HTTP status, local hashes และ local declarations อยู่ใน evidence และ automated pinsครบ

### Finding 5: image checksum และ provenance reproducibility

สถานะ `NOT ADDRESSED`

ส่วนที่ปิดแล้ว:

- DataTables image 5 filesถูก pin โดยตรงที่ `tests/ci4/MenuHttpTest.php:420-424`
- evidence ระบุ exact versioned CDN URLs, HTTP `200`, retrieval command, date และ SHA-256 ที่ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:154-175`
- prior evidence references ระบุ exact path และ section ที่ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:143-152`

ส่วนที่ยังเปิด:

- upload 4 pathsไม่มี direct checksum assertion ใน focused tests
- `tests/ci4/MenuHttpTest.php:281-282` assert เพียง mobile pathsอยู่ใน closure ไม่ได้ pin bytes ของทั้ง 4 files
- staged replacement ของ upload bytesยังผ่าน `git diff --quiet` ได้เมื่อ index/worktree ตรงกัน เพราะ gate นี้ตรวจ consistency ไม่ใช่ expected checksum
- evidence ที่ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:152` และ `:248` ระบุว่าไม่พบ durable archive path จึง `BLOCKED` แต่ข้อสรุปนี้ไม่ครบ

Durable CI3 provenance มีอยู่จริง:

| CI4 target paths | CI3 pinned source | SHA-256 | ผล byte comparison |
|---|---|---|---|
| `public/uploads/web/contact_laptop.png`, `contact_mobile.png` | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6:assets/images/bg-contact.png` | `2520b9e21373a7822bf2388cd043684a8e0bcdc41071c6a562d539964e7f038f` | ทั้งสองไฟล์ byte-identical |
| `public/uploads/web/track_laptop.png`, `track_mobile.png` | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6:assets/images/bg-tracking.png` | `16b99ac15ba78c5dd6a462de19b8c349747b7621301a7a1cb3858e09753c813a` | ทั้งสองไฟล์ byte-identical |

หลักฐาน repo ที่มีอยู่แล้ว:

- `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.md:3-12` ผูก inventory กับ CI3 pin เดียวกัน
- `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json:2788-2793` บันทึก `assets/images/bg-contact.png`, hash และขนาด
- `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json:2844-2849` บันทึก `assets/images/bg-tracking.png`, hash และขนาด
- inventory v2 ทั้ง JSON และ Markdown เป็น tracked files อยู่แล้ว

ดังนั้นปัญหาไม่ใช่ “ไม่มี provenance” แต่เป็น Task 6 evidence/test ยังไม่ผูก upload copies กับ durable CI3 source ที่มีอยู่

Minimal fix ก่อน checkpoint:

1. เพิ่ม expected SHA-256 ของ upload 4 paths หรือ assert byte identity กับ expected hash ใน `testSharedFrontendDependencyPinsMatchCi3RuntimeArtifacts`
2. แก้ evidence ให้ cite exact CI3 pin/source paths และคำสั่ง reproducible เช่น `git show <pin>:<path>`
3. เปลี่ยน upload provenance จาก `BLOCKED` เป็นสถานะที่พิสูจน์แล้ว หลัง direct test ผ่าน
4. rerun focused suite, full PHPUnit, PHPStan และ full `scripts/ci-check.sh`

## Candidate set และ scope hygiene

Current untracked asset set 100 pathsตรง exact listใน `task-6-fix1-review.diff:1000-1100` แบบ set equality ไม่มี path ขาดหรือเกิน

| จำนวน | Candidate group |
|---:|---|
| 93 | untracked runtime assets |
| 1 | `public/assets/fonts/source-sans-pro/OFL.txt` |
| 6 | `public/assets/licenses/` |
| 1 | `public/assets/css/main.css` |
| 1 | `tests/ci4/MenuHttpTest.php` |
| 1 | `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md` |
| รวม | 103 |

Scope exclusions ถูกต้อง:

- `public/assets/css/admin.css` ไม่อยู่ใน required paths และไม่อยู่ใน candidate แม้ current review diff package จะแสดง WIP ของไฟล์นี้
- ไม่มี order browse chain หรือ `public/assets/css/style.css`
- ไม่มี `public/assets/plugins/**`, source SCSS/LESS, docs/demo/tests/specimen, `multifreezer.js` หรือ `cms-logo.png`
- ไม่มี dependency version อื่นหรือ over-inclusion

หากแก้ finding 5 เฉพาะ test/evidence เดิม จำนวน provisional candidate ยังคง **103 files** แต่ approved count รอบนี้ยังเป็น **0 files**

## Bootstrap authority

Bootstrap 3.3.7 ถูกต้องตาม authority:

- CI3 checkout อยู่ที่ pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` และ clean
- CI3 pinned tree กับ CI4 local assetsมี SHA-256 ตรงกันทั้ง `bootstrap.css`, `bootstrap.min.css` และ `bootstrap.min.js`
- local headers ระบุ `Bootstrap v3.3.7`
- brief ที่ระบุ 3.3.4 เป็น stale expectation จึงห้าม downgrade

## Verification ที่รันใน re-review

| Gate | ผลปัจจุบัน |
|---|---|
| Parser regressions | `OK (11 tests, 79 assertions)` |
| Parser + dependency/license pins | `OK (12 tests, 109 assertions)` |
| Real-index closure | RED ตามคาด เพราะ candidate ยัง untracked |
| Exact temporary index 103 | `OK (1 test, 1669 assertions)` |
| Temporary index ขาด `main.css` | RED ตรง index-blob mismatch |
| Focused asset suites | `OK (105 tests, 3894 assertions)` |
| PHPStan | `[OK] No errors` |
| Full PHPUnit | `OK (415 tests, 8312 assertions)` |
| Full `scripts/ci-check.sh` | ผ่านทุก gateด้วย exact temporary candidate 103 files |
| Secret/PII | ผ่านทั้ง `secret file policy` และ `candidate-tree PII guard` |
| Diff hygiene | `git diff --check` ไม่มี output |
| Real Git index | ไม่มี staged paths และไม่ถูกเปลี่ยน |

Full `ci-check` ใช้ temporary index และ wrapper ที่ unset `GIT_INDEX_FILE` เฉพาะ Git call ซึ่ง path ชี้ไป `samsoniteci3`; final run ผ่านถึง `PASS repository safety gate`

ผลรันเหล่านี้เป็น current หลัง final edits และตรงตัวเลขใน report แต่ไม่ลบ binding provenance/checksum gap ของ upload 4 paths

## Blocker และ final verdict

Blocker เดียวที่ยัง load-bearing คือ upload checksum/provenance closure ซึ่งขัดกับ:

- `docs/superpowers/specs/2026-08-27-strict-ci3-template-preservation-design.md:90-100` ที่บังคับ version, checksum, source/provenance และ license evidence ต่อ bundle
- `docs/superpowers/plans/2026-08-27-tpl00-tpl01-foundation.md:494-506` ที่บังคับ exact versions/checksums/provenance และให้ใช้ `BLOCKED` เมื่อ evidence ยังไม่ครบ
- Task 6 interface ที่ `docs/superpowers/plans/2026-08-27-tpl00-tpl01-foundation.md:469-472` ซึ่งต้องผลิต tracked closure ที่ version/checksum ตรง CI3

จึงไม่อนุญาต checkpoint code/assets แบบ `BLOCKED` ภายใต้ commit message `wip(strict-template): t6 shared assets passed` และไม่อนุมัติ staged candidate ในรอบนี้

**Final verdict: `REQUEST CHANGES`, approved candidate count `0 files`**

หลังเพิ่ม direct upload hash pins และแก้ evidence ให้ชี้ exact CI3 pinned sources สามารถขอ re-review โดย candidate setคง 103 filesได้
