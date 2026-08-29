# Review Task 6: Shared Runtime Asset Closure

เอกสารนี้ review implementation, test helper, runtime asset set และ evidence ของ Task 6 แบบ read-only เทียบ brief และ strict CI3 preservation design โดยตรวจทั้ง current worktree, real Git index และ temporary-index simulation

## คำตัดสิน

| แกน | คำตัดสิน | เหตุผลหลัก |
|---|---|---|
| Spec compliance | **FAIL** | graph ที่ test สร้างจริงมี 108 runtime files ไม่ใช่ 109, ไม่อ่าน `style` attribute และ temporary index ผ่านลวงเมื่อไม่รวม `main.css` |
| Code quality | **CHANGES REQUIRED** | parser ยังผิดใน edge cases ที่ brief บังคับ, checksum/provenance gate ไม่ครบ และ license blockers ยังไม่มี mandatory release disposition |
| Approved candidate count | **0 files ณ รอบนี้** | ยังไม่อนุมัติ checkpoint commit จน Critical และ Important findings ปิดครบ |
| Provisional candidate set หลังแก้ | **97 files** | 94 untracked assets รวม `OFL.txt`, `public/assets/css/main.css`, `tests/ci4/MenuHttpTest.php` และ evidence document |

Bootstrap `3.3.7` ถูกต้องตาม active CI3 pin แม้ brief ระบุ `3.3.4`; ห้าม downgrade เพราะ authority สูงสุดคือ CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`

## Findings

### Critical 1: Temporary-index simulation ผ่านลวงและ candidate set ขาด `main.css`

- **Location**: `tests/ci4/MenuHttpTest.php:244-299`, `public/assets/css/main.css:614`, `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:177-181`
- **ปัญหา**: `git ls-files --error-unmatch` ตรวจเพียงว่าชื่อ path อยู่ใน index ไม่ได้ตรวจว่า tracked file content ที่สร้าง graph ตรงกับ content ใน index
- **Failure scenario**: temporary index ที่ add 94 assets และ test แต่ไม่ add `main.css` ผ่าน `OK (1 test, 980 assertions)` เพราะ test render และอ่าน `main.css` จาก worktree
- **Clean checkout result**: `HEAD:public/assets/css/main.css` ยังอ้าง `contact_mobile.jpg` แต่ทั้ง Git และ disk ไม่มีไฟล์นี้ จึง fail ใน clean checkout หลัง commit ชุด 94 files ตามรายงาน

หลักฐาน:

```text
HEAD main.css: background: url(../../uploads/web/contact_mobile.jpg)
jpg_tracked=no
jpg_disk=no
```

ความหมาย: simulation ไม่พิสูจน์ clean checkout consistency ตาม design Gate 0

**Minimal fix**:

1. รวม `public/assets/css/main.css` ใน exact checkpoint candidate
2. สร้าง clean-tree simulation จาก staged tree content ไม่ใช่ worktree content หรืออย่างน้อยยืนยัน `git diff --quiet -- <ทุก tracked runtime input>` ก่อนประกาศ GREEN
3. แก้ report/evidence จาก “94 must-add files พร้อม commit” เป็น staged set ที่รวม tracked runtime modifications ด้วย

### Important 1: Test graph จริงมี 108 files และตก `bg-login.jpg`

- **Location**: `tests/ci4/MenuHttpTest.php:622-640`, `app/Views/login.php:15`, `app/Views/forgot_password.php:15`, `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:9-12`
- **ปัญหา**: `runtimeAssetReferences()` อ่าน `src`, `href` และ `<style>...</style>` แต่ไม่อ่าน CSS ใน `style="..."`
- **Failure scenario**: login และ forgot-password อ้าง `assets/images/bg-login.jpg` ผ่าน `style="background-image: url(...)"`; ถ้าไฟล์ถูกลบ test ยังผ่าน tracked closure เพราะ path ไม่เคยเข้า graph
- **ผลต่อ evidence**: wrapper ที่จับ arguments ของ `git ls-files` พบ `runtime_closure=108`, `bg_login=False`, `OFL=True`; เอกสารกลับอ้าง 109 runtime files, tracked 16 และรวม `bg-login.jpg`

หลักฐาน:

```text
git_paths=109 runtime_closure=108 bg_login=False ofl=True
```

ความหมาย: 109 paths ที่ส่งให้ Git คือ runtime 108 files รวม license 1 file ไม่ใช่ runtime 109 files บวก license

**Minimal fix**:

- extract `style` attribute จาก rendered HTML แล้วส่งค่าเข้า `cssAssetReferences()`
- เพิ่ม assertion ตรงว่า closure มี `public/assets/images/bg-login.jpg`
- rerun graph แล้วแก้ count ใน report/evidence จากผลจริงเท่านั้น

### Important 2: Parser contract ที่ brief บังคับยังไม่มี regression coverage และมี edge-case bugs จริง

- **Location**: `tests/ci4/MenuHttpTest.php:679-719`
- **ปัญหา**: test ปัจจุบันขับเพียง current happy-path graph; ไม่มี test ตรงสำหรับ external origin, traversal escape, CSS cycle, query, fragment, case-insensitive data URL, dynamic route exclusion หรือ quoted `@import`

Failure scenarios ที่ reproduce ได้:

| กรณี | ผลปัจจุบัน | ผลที่ต้องการ |
|---|---|---|
| `@import "a.css"; ... url(b.png) ... url(c.png)` | ได้ `a.css,c.png`, ตก `b.png` | เก็บทั้งสาม references |
| `url(DATA:image/png;base64,...)` | ไม่ถูกตัดเพราะเช็ค `data:` แบบ case-sensitive | ตัด data URL แบบ case-insensitive |
| same host แต่คนละ scheme/port | ผ่านเพราะเทียบเฉพาะ host | ปฏิเสธ origin ที่ไม่ตรง `base_url()` |
| `/background-image/assets/x.png` | ถูกมองเป็น static asset เพราะใช้ `str_contains('assets/')` | exclude dynamic route ด้วย exact path prefix |
| style attribute | ไม่ถูก scan | รวมใน graph |

Traversal normalization และ cycle guard มี implementation แต่ไม่มี test ที่ทำให้ branch เหล่านี้ RED เมื่อถูกลบ จึงยังไม่เป็นหลักฐานตาม coverage contract

**Minimal fix**:

- เพิ่ม public test เดียวแบบ data provider ครอบ edge cases ข้างต้น พร้อม query/fragment และ `../` ที่ออกนอก `public/`
- แยก regex ของ quoted `@import` ออกจาก `url()` หรือใช้ parser pattern ที่ไม่กิน reference ถัดไป
- เปรียบ exact origin และรับเฉพาะ path prefix `/assets/` หรือ `/uploads/web/`
- ใช้ case-insensitive scheme check สำหรับ `data:`

### Important 3: License blockers ถูกพบแต่ยังไม่มี mandatory release gate

- **Location**: `docs/superpowers/specs/2026-08-27-strict-ci3-template-preservation-design.md:90-100`, `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:119-135`, `tests/ci4/MenuHttpTest.php:301-330`
- **ปัญหา**: design บังคับทุก bundle มี license evidence แต่ automated test pin เฉพาะ Source Sans Pro `OFL.txt`
- **สถานะจริง**: evidence table ระบุ `BLOCKED` สำหรับ Bootstrap, DataTables, FixedColumns, Font Awesome, html5shiv และ Respond.js ไม่ใช่เพียงสามกลุ่มตาม summary
- **Failure scenario**: gitops สามารถ commit checkpoint ชื่อ `t6 shared assets passed` ทั้งที่ license requirements ยังเปิด เพราะไม่มี test หรือ signed disposition gate ป้องกัน

**Minimal fix**:

เลือกอย่างใดอย่างหนึ่งก่อน checkpoint:

1. เพิ่ม license text ของ upstream ที่พิสูจน์ source/version ได้จริง พร้อม checksum pin
2. บันทึก signed human disposition ต่อ bundle ว่า embedded header/URL เพียงพอหรือยอมรับ blocker อย่างไร

ห้ามสร้าง license text เอง และต้องคงสถานะ Task 6 เป็น `BLOCKED` จนกว่าจะมีข้อยุติ

### Important 4: Checksum และ provenance claims ยังไม่ reproducible ครบ

- **Location**: `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:119-154`, `tests/ci4/MenuHttpTest.php:301-330`
- **ปัญหา**: evidence บันทึก DataTables image hashes แต่ test ไม่ pin 5 images; binary เหล่านี้เปลี่ยนได้โดย dependency test 19 assertions ยัง GREEN
- **ปัญหา**: provenance หลายแถวใช้คำว่า `prior evidence` โดยไม่อ้าง path/line และ live CDN claims ไม่มี exact URL, retrieval command, HTTP status หรือ durable response evidence ในเอกสารนี้
- **Failure scenario**: asset ถูกแทน bytes หลัง review หรือ CDN เปลี่ยน response แต่เอกสารยังอ้าง identity เดิมโดยไม่มี gate ตรวจจับ

**Minimal fix**:

- เพิ่ม DataTables image hashes เข้า checksum table ที่ test รันจริง
- อ้าง evidence เดิมด้วย exact path และ section เช่น standalone-auth traceability แทนคำว่า `prior evidence`
- บันทึก exact versioned CDN URLs, retrieval command, retrieval date/status และ local SHA-256 ที่เปรียบเทียบ
- สำหรับ upload snapshot ให้อ้าง archive identifier/path ที่ตรวจซ้ำได้ ไม่ใช่เพียงคำว่า archived snapshot

### Minor 1: Test helper ซ้ำ parser ที่มีอยู่หลายชุดและ maintenance cost สูง

- **Location**: `tests/ci4/MenuHttpTest.php:622-719`, `tests/ci4/PasswordResetPageHttpTest.php:243-287`, `tests/ci4/PublicTrackingHttpTest.php:284-423`
- **ปัญหา**: repository มี asset parsers หลายชุดที่ behavior ไม่เท่ากัน ทำให้แก้ bug ในชุดหนึ่งแล้วอีกชุดยัง under-scan หรือ fail-open ได้
- **Minimal fix**: รอบนี้ยังไม่ต้องสร้าง production abstraction; อย่างน้อยให้ test cases contract เดียวกันถูก reuse ผ่าน data provider หรือ test trait ขนาดเล็กเมื่อแก้ parser

## สิ่งที่ตรวจแล้วผ่าน

| หัวข้อ | ผล |
|---|---|
| Active CI3 pin | CI3 repository อยู่ที่ `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` และ clean |
| Bootstrap authority | local CSS/JavaScript hashes ตรง CI3 pin และ header ระบุ `3.3.7`; การไม่ใช้ brief `3.3.4` ถูกต้อง |
| Real-index RED | tracked gate fail ด้วย 94 pathspec errors ตรง 93 runtime files และ `OFL.txt` |
| Dependency pin test | `OK (1 test, 19 assertions)` |
| Candidate over-inclusion | untracked asset setมี 94 files ตรง candidate list ไม่มี vendor tree เกินชุด |
| Order scope | ไม่พบ order browse chain หรือ `assets/css/style.css` ใน candidate |
| Duplicate paths | contact และ track desktop/mobile paths คงอยู่ครบและ bytes ซ้ำตามที่ CSS ต้องใช้ |
| Git ignore | candidate ตัวอย่างไม่ถูก ignore; `.gitignore` ไม่มี rule กลืนชุดนี้ |
| Secret/PII spot-check | ไม่พบ email address หรือ private-key marker ใน candidate text assets |
| Diff hygiene | `git diff --check` ผ่านสำหรับ `main.css` และ `MenuHttpTest.php` |
| `admin.css` | tracked แต่ current rendered profiles ไม่อ้าง จึงต้องไม่อยู่ใน Task 6 staged set |

## Staged candidate set

### ยังไม่อนุมัติในรอบนี้

Approved candidate count คือ **0** เพราะ Critical 1 และ Important 1-4 ยังเปิด

### Provisional exact set หลังแก้ findings

| กลุ่ม | จำนวน | เงื่อนไข |
|---|---:|---|
| 93 runtime assets ที่ยัง untracked | 93 | rerun graph หลัง parser fix แล้ว set ต้องยังตรง current rendered closure |
| Source Sans Pro `OFL.txt` | 1 | ใช้ไฟล์จริง hash `fce9f9e2...` |
| `public/assets/css/main.css` | 1 | ต้องรวมการแก้ `.jpg` เป็น `.png` เพื่อ clean checkout |
| `tests/ci4/MenuHttpTest.php` | 1 | ต้องแก้ parser และเพิ่ม edge-case regression tests |
| `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md` | 1 | ต้องแก้ count, provenance, license status และ commands |
| รวม provisional | **97** | ไม่รวม license files เพิ่มเติมที่อาจเกิดจาก human disposition |

ต้อง exclude `public/assets/css/admin.css`, `public/assets/plugins/**`, `multifreezer.js`, `cms-logo.png`, order browse assets, docs/demo/source trees และ dependency version อื่น

หากข้อยุติ license เลือกเพิ่ม authentic license files จำนวน staged set ต้องเพิ่มตามไฟล์จริงและ rerun graph/checksum/gates ใหม่ ห้ามยึดเลข 97 แบบตายตัวหลัง scope เปลี่ยน

## Verification ที่ต้องรันก่อนขอ re-review

1. รัน focused suite บน real index หลัง stage exact candidate
2. สร้าง clean checkout จาก staged tree แล้วรัน focused suiteซ้ำ
3. รัน full PHPUnit, PHPStan และ `scripts/ci-check.sh` โดยไม่ส่ง temporary CI4 `GIT_INDEX_FILE` ไปยัง CI3 subprocess
4. rerun wrapper/count check เพื่อยืนยัน runtime closure และ evidence count ตรงกัน
5. ยืนยัน license disposition และ provenance references ครบก่อนใช้ checkpoint message `wip(strict-template): t6 shared assets passed`
