# TPL-01 runtime asset closure v1

เอกสารนี้แยกหลักฐาน Task 6 ก่อน commit, checkpoint `6799684` และ current Task 7 delta เทียบ CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` โดยไม่ upgrade หรือ replace frontend dependency

## สรุปสถานะตามเวลา

| ช่วง | Runtime closure | Candidate หรือ checkpoint | สถานะ |
|---|---:|---:|---|
| Historical Task 6 ก่อน commit | 109 files | 103 files | temporary-index simulation ผ่าน |
| Task 6 checkpoint `6799684` | 109 files | 103 files committed | closure และ license evidence ถูก commit แล้ว |
| Current Task 7 delta | 119 files | 22 files | เพิ่ม order runtime 10 files และ code/test/evidence 12 files |

- Frontend dependency upgrade: 0
- Frontend dependency replacement: 0
- Real Git index ไม่ถูกเปลี่ยนระหว่าง Task 7 fix round 2

## Historical Task 6 ก่อน commit

Task 6 ปิด shared runtime graph ก่อนมี order profile caller:

| แกน | จำนวน | หลักฐาน |
|---|---:|---|
| Runtime closure | 109 files | tracked เดิม 16 และ untracked 93 |
| License evidence นอก graph | 7 files | Source Sans Pro 1 และ license set 6 |
| Exact candidate | 103 files | untracked runtime 93, license 7, `main.css`, test และ evidence |
| Required index inputs | 118 paths | runtime 109, license 7, test 1 และ evidence 1 |

สถานะ historical คือ `PREPARED_AWAITING_REVIEW_AND_GITOPS`; ตัวเลขนี้อธิบายช่วงก่อน commit เท่านั้น

## Task 6 committed checkpoint

Commit `6799684` บันทึก exact Task 6 candidate 103 files แล้ว ทำให้ shared runtime closure 109 files และ required index inputs 118 paths อยู่ใน Git checkpoint ครบ

- `public/assets/css/main.css` ตรง worktree ณ checkpoint
- Task 6 focused suite, full PHPUnit, PHPStan และ `scripts/ci-check.sh` ผ่านจาก real index
- order assets 10 filesยังไม่อยู่ใน commit นี้

## Current Task 7 delta

Order profile เพิ่ม runtime closure จาก 109 เป็น 119 files โดยเพิ่ม exact CI3 order assets 10 files

| กลุ่ม | จำนวน | Paths |
|---|---:|---|
| Production | 8 | `Routes.php`, `Order.php`, `OrderImageStore.php`, order views และ partials |
| Exact order assets | 10 | CSS 1, images 2, browse JavaScript 5 และ validation JavaScript 2 |
| Tests | 3 | `OrderHttpTest.php`, `MenuHttpTest.php`, `RouteHttpTest.php` |
| Evidence | 1 | ไฟล์นี้ |
| รวม | 22 | exact Task 7 staging candidate |

Exact order asset additions:

- `public/assets/css/style.css`
- `public/assets/images/bg-form.png`
- `public/assets/img/icons.png`
- `public/assets/js/browse/jquery.knob.js`
- `public/assets/js/browse/jquery.ui.widget.js`
- `public/assets/js/browse/jquery.iframe-transport.js`
- `public/assets/js/browse/jquery.fileupload.js`
- `public/assets/js/browse/script.js`
- `public/assets/js/addOrder.js`
- `public/assets/js/admin_addOrder.js`

Current runtime state เทียบ `6799684` คือ tracked closure 109 files และ untracked exact order assets 10 files; current Task 7 code/test/evidence อยู่ใน working tree และยังไม่ stage

## วิธีสร้าง graph

Test `MenuHttpTest::testSharedRuntimeAssetClosureExistsAndIsGitTracked` render HTML จริงแล้วเดิน CSS แบบ recursive

| Profile | Runtime source |
|---|---|
| Admin | `GET /dashboard` |
| Order | render `layout_order` สำหรับ central และ branch caller |
| Standalone auth | `GET /login`, `/forgot-password`, `/reset-password` |
| Public Contact | `GET /contact`, `/contact-th` |
| Public Tracking | `GET /tracking`, `/tracking-th` |
| Public non-legacy | render `layout_public` |
| Error views | render `access_denied` และ `errors/html/error_404` |

กติกา graph:

- อ่าน `src`, `href`, `<style>` และ `style="..."`
- เดิน CSS `url()` และ `@import` จน closure ปิด พร้อม cycle guard
- ตัด `data:` แบบ case-insensitive และ fragment-only reference
- เทียบ absolute URL ด้วย exact scheme, host และ port ของ `base_url()`
- รับ HTML entrypoint เฉพาะ `/assets/` และ `/uploads/web/`
- strip query/fragment ก่อน resolve และ fail เมื่อ traversal ออกนอก `public/`
- ตรวจทุก required path ว่าอยู่ใน Git index
- ตรวจ `git diff --quiet --no-ext-diff` ต่อ required path เพื่อยืนยัน index blob ตรง worktree

Direct parser regressions ครอบ quoted `@import`, หลาย `url()`, `DATA:`, origin mismatch, dynamic prefix, query/fragment, traversal และ CSS cycle

## Current runtime closure 119 files

### Runtime paths by group

| จำนวน | กลุ่ม | สมาชิก |
|---:|---|---|
| 8 | `public/assets/bootstrap/` | CSS 2, Glyphicons 5 formats, JavaScript 1 |
| 2 | `public/assets/css/` | `multifreezer.css`, `style.css` |
| 7 | `public/assets/datatables/1.10.16/` | CSS 1, JavaScript 1, sort images 5 |
| 2 | `public/assets/datatables-fixedcolumns/3.2.4/` | CSS 1, JavaScript 1 |
| 6 | `public/assets/dist/` | CSS 3, images 2, JavaScript 1 |
| 6 | `public/assets/font-awesome/` | CSS 2, fonts 4 |
| 6 | `public/assets/font-awesome/4.3.0/` | CSS 1, fonts 5 |
| 6 | `public/assets/font-awesome/4.7.0/` | CSS 1, fonts 5 |
| 6 | `public/assets/fontawesome/` | CSS 1, fonts 5 |
| 8 | `public/assets/fonts/source-sans-pro/` | stylesheet 1, TTF 7 |
| 1 | `public/assets/html5shiv/3.7.2/` | `html5shiv.min.js` |
| 1 | `public/assets/img/` | `icons.png` ของ order upload preview |
| 6 | `public/assets/images/` | `404.png`, `access.png`, `bg-form.png`, tracking images 2, `print-logo.jpg` |
| 8 | root `public/assets/js/` | Contact/Tracking scripts, order validation scripts 2, jQuery 2 files, validate 2 files |
| 5 | `public/assets/js/browse/` | exact order upload preview/progress chain |
| 20 | `public/assets/js/jquerydatepicker/` | JavaScript/CSS 6, recursive images 14 |
| 1 | `public/assets/respond/1.4.2/` | `respond.min.js` |
| 4 | `public/uploads/web/` | Contact desktop/mobile และ Tracking desktop/mobile |

Recursive jQuery UI image set 14 files:

- `animated-overlay.gif`
- `ui-bg_flat_0_aaaaaa_40x100.png`
- `ui-bg_flat_75_ffffff_40x100.png`
- `ui-bg_glass_55_fbf9ee_1x400.png`
- `ui-bg_glass_65_ffffff_1x400.png`
- `ui-bg_glass_75_dadada_1x400.png`
- `ui-bg_glass_75_e6e6e6_1x400.png`
- `ui-bg_glass_95_fef1ec_1x400.png`
- `ui-bg_highlight-soft_75_cccccc_1x100.png`
- `ui-icons_222222_256x240.png`
- `ui-icons_2e83ff_256x240.png`
- `ui-icons_454545_256x240.png`
- `ui-icons_888888_256x240.png`
- `ui-icons_cd0a0a_256x240.png`

### Historical Task 6 tracked runtime 16 filesก่อน commit

รายการนี้คือ 16 files ที่ tracked อยู่ก่อน exact Task 6 candidate; หลัง checkpoint `6799684` shared runtime closure 109 filesถูก tracked ครบ

- `public/assets/css/main.css`
- `public/assets/css/public.css`
- `public/assets/fonts/stylesheet.css`
- DB Helvethaica fonts 4 filesใต้ `public/assets/fonts/`
- `public/assets/images/bg-login.jpg`
- `public/assets/images/eng.png`
- `public/assets/images/img-contact-1.png`
- `public/assets/images/img-contact-2.png`
- `public/assets/images/img-footer.png`
- `public/assets/images/main-logo.png`
- `public/assets/images/popup_en.png`
- `public/assets/images/popup_th.png`
- `public/assets/images/thai.png`

`bg-login.jpg` เข้าสู่ graph จาก `style="background-image: url(...)"` ใน login และ forgot-password หลังแก้ parser

`public/assets/css/admin.css` tracked อยู่ก่อน แต่ไม่มี current rendered caller จึงไม่อยู่ใน runtime closure และไม่อยู่ใน candidate

## Historical Task 6 exact candidate 103 files

| จำนวน | กลุ่ม | เหตุผล |
|---:|---|---|
| 93 | shared untracked runtime closure | browser runtime caller มีจริงก่อน order profile |
| 1 | `public/assets/fonts/source-sans-pro/OFL.txt` | Source Sans Pro license |
| 6 | `public/assets/licenses/` | full license texts และ exact bundle licenses |
| 1 | `public/assets/css/main.css` | `.jpg` เป็น `.png`; index blob ต้องตรง worktree |
| 1 | `tests/ci4/MenuHttpTest.php` | parser, checksum และ index-blob gates |
| 1 | `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md` | durable evidence |
| รวม | 103 | exact candidate ที่ถูก commit เป็น `6799684` |

License candidate 6 files:

- `public/assets/licenses/bootstrap-3.3.7-LICENSE`
- `public/assets/licenses/datatables-1.10.16-license.txt`
- `public/assets/licenses/fixedcolumns-3.2.4-License.txt`
- `public/assets/licenses/respond-1.4.2-LICENSE-MIT`
- `public/assets/licenses/MIT.txt`
- `public/assets/licenses/OFL-1.1.txt`

Historical Task 6 required index inputs รวม 118 paths: runtime closure 109, license evidence 7, test 1 และ evidence 1

## Must not add

| กลุ่ม | เหตุผล |
|---|---|
| `public/assets/css/admin.css` | ไม่มี current runtime caller และเป็น WIP คนละ scope |
| `public/assets/plugins/**` | ไม่มี current caller และเป็น plugin tree ขนาดใหญ่ |
| SCSS, LESS, examples, docs, tests, specimen, demo | ไม่ใช่ runtime closure |
| `multifreezer.js` | CI3 caller อยู่ใน HTML comment |
| `cms-logo.png` | CI3 caller อยู่ใน HTML comment |
| Dependency version อื่น | ห้าม upgrade หรือ replacement |

## Version และ provenance

### Prior repo evidence ที่อ้างตรง

| กลุ่ม | Evidence path | Section |
|---|---|---|
| Bootstrap, Font Awesome 4.3, html5shiv, Respond.js | `outputs/reference/2026-08-27_wp03h-standalone-auth-traceability_v1.md` | `Dependency recovery round 2` และ `Local asset checksums` |
| Font Awesome 4.7, Source Sans Pro, uploads | `outputs/reference/2026-08-27_wp03h-standalone-auth-traceability_v1.md` | `Dependency recovery round 3: recursive local CSS graph` |
| DataTables และ FixedColumns | `outputs/reference/2026-08-27_wp03m-admin-layout-adapter-evidence_v1.md` | `Asset evidence` |
| Contact `main.css` adapter | `outputs/reference/2026-08-27_wp03i-contact-presentation-traceability_v1.md` | `Template และ dependency` |

### Upload provenance จาก CI3 pin

Upload target ทั้ง 4 files มี source ที่ตรวจซ้ำได้ใน CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` และตรงกับ inventory v2 ซึ่งผูกกับ pin เดียวกันที่ `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.md:3-12`:

| CI4 target paths | CI3 pinned source | SHA-256 | Inventory v2 JSON | ผล |
|---|---|---|---|---|
| `public/uploads/web/contact_laptop.png`, `public/uploads/web/contact_mobile.png` | `assets/images/bg-contact.png` | `2520b9e21373a7822bf2388cd043684a8e0bcdc41071c6a562d539964e7f038f` | `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json:2788-2793` | ทั้งคู่ byte-identical กับ source |
| `public/uploads/web/track_laptop.png`, `public/uploads/web/track_mobile.png` | `assets/images/bg-tracking.png` | `16b99ac15ba78c5dd6a462de19b8c349747b7621301a7a1cb3858e09753c813a` | `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.json:2844-2849` | ทั้งคู่ byte-identical กับ source |

ตรวจซ้ำได้ด้วยคำสั่งต่อไปนี้จาก CI3 checkout และ CI4 root ตามลำดับ:

```bash
git show ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6:assets/images/bg-contact.png | shasum -a 256
git show ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6:assets/images/bg-tracking.png | shasum -a 256
shasum -a 256 public/uploads/web/contact_laptop.png public/uploads/web/contact_mobile.png
shasum -a 256 public/uploads/web/track_laptop.png public/uploads/web/track_mobile.png
```

`MenuHttpTest::testSharedFrontendDependencyPinsMatchCi3RuntimeArtifacts` pin SHA-256 ของ target ทั้ง 4 แล้ว จึงเปลี่ยน hash ใดก็ทำให้ regression RED โดยตรง

### Order assets จาก CI3 pin

Order profile ใช้ target 10 files จาก source path เดียวกันใน CI3 pin โดย checksum ตรงทุกไฟล์:

| CI4 target | CI3 pinned source | SHA-256 | License classification |
|---|---|---|---|
| `public/assets/css/style.css` | `assets/css/style.css` | `a0ca03a6569a9520ea1aaac734cfcb114d9418475eec43eae41201d1c65050b6` | first-party project asset, ไม่มี third-party license header |
| `public/assets/images/bg-form.png` | `assets/images/bg-form.png` | `65fd6f960ea58421a1ba10a8414332b05e9de97150c098578db73305048fa1c0` | first-party project image, exact 937-byte blobจาก CI3 pin |
| `public/assets/img/icons.png` | `assets/img/icons.png` | `8e729e7a5839f3cb37c416b51461501f1bffcfc290ca973dd2b3cbbf5bcd24dd` | first-party project asset, ไม่มี third-party license header |
| `public/assets/js/browse/jquery.knob.js` | `assets/js/browse/jquery.knob.js` | `9a9bcdeb2150048832cd9c5b6f56db8e20e2ade75a60ca1eb014ad49b9b65c16` | third-party, embedded MIT/GPL declaration, ใช้ MIT option |
| `public/assets/js/browse/jquery.ui.widget.js` | `assets/js/browse/jquery.ui.widget.js` | `95694c8567c94e0bcdff9fa4711be1d0060509931b8d19b450109b8552a8ef71` | third-party, embedded MIT declaration |
| `public/assets/js/browse/jquery.iframe-transport.js` | `assets/js/browse/jquery.iframe-transport.js` | `0ddd3dc005842bd02b0bba0fa65951f4b64714504c887af0dfcbd97f390325c4` | third-party, embedded MIT declaration |
| `public/assets/js/browse/jquery.fileupload.js` | `assets/js/browse/jquery.fileupload.js` | `912fd62966a08f15145b4aefcac50e45893dfb5732869ec658b48ac1362ebb07` | third-party, embedded MIT declaration |
| `public/assets/js/browse/script.js` | `assets/js/browse/script.js` | `9a455e73fb66fe42f287f22cd96065e6f65039992a10ca687ce05df4dc8101ec` | first-party project script, ไม่มี third-party license header |
| `public/assets/js/addOrder.js` | `assets/js/addOrder.js` | `86fdf03e7cdbf2bfb66fde74cee6374cbc24cdea2395f9b9d2e63caad1bb89e0` | first-party project script, คง known parse defect ตาม pin |
| `public/assets/js/admin_addOrder.js` | `assets/js/admin_addOrder.js` | `4b07a289e72973be7f60963ff9156d70eedbe7adcd1779d38ccd0bfae5f33b42` | first-party project script, ไม่มี third-party license header |

ตรวจซ้ำจาก CI3 checkout และ CI4 root ได้ด้วยคำสั่ง:

```bash
for path in assets/css/style.css assets/images/bg-form.png assets/img/icons.png assets/js/browse/jquery.knob.js assets/js/browse/jquery.ui.widget.js assets/js/browse/jquery.iframe-transport.js assets/js/browse/jquery.fileupload.js assets/js/browse/script.js assets/js/addOrder.js assets/js/admin_addOrder.js; do
  /usr/bin/git -C ../samsoniteci3 show "ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6:$path" | shasum -a 256
done
shasum -a 256 public/assets/css/style.css public/assets/images/bg-form.png public/assets/img/icons.png public/assets/js/browse/*.js public/assets/js/addOrder.js public/assets/js/admin_addOrder.js
```

`MenuHttpTest::testSharedFrontendDependencyPinsMatchCi3RuntimeArtifacts` pin target checksumเดิม 9 files, `OrderHttpTest::testCreateAndEditUsePinnedCi3BackgroundFormAsset` pin `bg-form.png` เพิ่มอีก 1 file และ `testOrderValidationScriptsPreservePinnedSyntaxBehavior` ตรึงว่า `admin_addOrder.js` ผ่าน `node --check` ส่วน `addOrder.js` ยัง fail ที่ `customerTel` ตาม source authority

### Exact DataTables CDN retrieval

ดึงวันที่ `2026-08-27` ด้วยคำสั่งรูปแบบนี้:

```bash
curl --fail-with-body -sS -L -o "$file" -w '%{http_code}' "$url"
shasum -a 256 "$file"
```

| HTTP | SHA-256 | Exact URL |
|---:|---|---|
| 200 | `618d62ceaca1223e16de2c8939a1963a95c34b0ac75852f835f93e5b42f20871` | `https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css` |
| 200 | `a9c575c2bf9b9f836806dc58aa0866cb558806fc5ea1ef2f4250a8c0b1be7278` | `https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js` |
| 200 | `595704c3f3cf4cb65c7d9c8508a99e7480e150095473faed31a07c21b13389b8` | `https://cdn.datatables.net/1.10.16/images/sort_asc.png` |
| 200 | `a65b8f4f84d6427a81c360282fc5394d51bf99dada5f159e6aa0fce3c396825c` | `https://cdn.datatables.net/1.10.16/images/sort_asc_disabled.png` |
| 200 | `3e016c23ae51417382b640ae2d19eb48047532c37ad53894bd185586559ccffb` | `https://cdn.datatables.net/1.10.16/images/sort_both.png` |
| 200 | `d08ed0e21f187dd309030d465224da8085119a15a17d616ba0e477bb50c6f10d` | `https://cdn.datatables.net/1.10.16/images/sort_desc.png` |
| 200 | `6c0f0c1b21ef6807057afc8ddc1a925d1dbd21cb11e9270ec84ff4ac40d9a3fa` | `https://cdn.datatables.net/1.10.16/images/sort_desc_disabled.png` |
| 200 | `2cac99438be2f9aacaf1a63f220f5a4e0fb5f54d443ecde09652a650b0509f8b` | `https://cdn.datatables.net/fixedcolumns/3.2.4/css/fixedColumns.dataTables.min.css` |
| 200 | `e44ec8df1b3ae7c386f670b1e9d4b4cad0b55fa28f934f31fd9a893c81c50298` | `https://cdn.datatables.net/fixedcolumns/3.2.4/js/dataTables.fixedColumns.min.js` |

Local hashes ตรง remote ทั้ง 9 files และ DataTables image 5 hashesอยู่ใน automated test

## License closure

License files ดึงวันที่ `2026-08-27` ด้วย `curl --fail-with-body -sS -L`; ทุก URL ตอบ HTTP `200` และ local SHA-256 ถูก pin ใน `MenuHttpTest`

| Bundle/caller | Exact upstream/tag | Full license path | SHA-256 |
|---|---|---|---|
| Bootstrap 3.3.7 | `twbs/bootstrap` tag `v3.3.7`, SHA `0b9c4a4007c44201dce9a6cc1a38407005c26c86` | `public/assets/licenses/bootstrap-3.3.7-LICENSE` | `8a68c27ad022244b78dc5c9adb4cc5a4c92a994e3e11d356b291f227b2b04eaf` |
| DataTables 1.10.16 | tag object `e6f02439...`, commit `75a665f64f02982c0f4666b15a25c4670e5e6b18` | `public/assets/licenses/datatables-1.10.16-license.txt` | `c6a873f21550ed804f76013c36e14225704c1aa551fdb870e0c626eb91c19247` |
| FixedColumns 3.2.4 | tag object `69c3242b...`, commit `8284753fde71c1d42ead1b9e89c306764df3f1d2` | `public/assets/licenses/fixedcolumns-3.2.4-License.txt` | `e8e92f97216f9ea00cb2735b933a91ec8e3869bed37b6d63a90f76f41508f2de` |
| Respond.js 1.4.2 | tag object `3eda118b...`, commit `20b7f4a192bb910c8c7e067b961de38519d334e4` | `public/assets/licenses/respond-1.4.2-LICENSE-MIT` | `96ef890049d3089064f9965e661057d5c3d42be86421c10cc8e9400a104f0b36` |
| Font Awesome CSS 4.2/4.3/4.7 | exact tag README/config ระบุ MIT | `public/assets/licenses/MIT.txt` | `b05785f9f18e6716bab63424b11454513b9943a222595b70411009202fc592b5` |
| Font Awesome fonts 4.2/4.3/4.7 | exact tag README/config ระบุ SIL OFL 1.1 | `public/assets/licenses/OFL-1.1.txt` | `8eea8287e5876b539670cadb82e99f9a7afddec6f6730811be1daf25d2e9bcfd` |
| html5shiv 3.7.2 | tag `3.7.2`, SHA `40df868a76f1f19ea23b123d1480d44a7195f349`; header ระบุ MIT/GPL2 | `public/assets/licenses/MIT.txt` ตาม MIT option | `b05785f9f18e6716bab63424b11454513b9943a222595b70411009202fc592b5` |
| Source Sans Pro | `google/fonts` commit `a7bc06df72f7e1b4e20342e2b04423e579222316` | `public/assets/fonts/source-sans-pro/OFL.txt` | `fce9f9e2fb268507a89fceea0b3eccc044f39fc3492968a04fd9e04df5ae95fa` |

Font Awesome exact tags:

- `v4.2.0`: tag object `0b924144...`, commit `a65bd93d81e9e6bd5ebfa41757a4474960b973b4`
- `v4.3.0`: tag object `e9665bad...`, commit `41b9ed01103e6820c3cb043ba7ddab30ecd3f4c0`
- `v4.7.0`: tag object `a3fe90fa...`, commit `a8386aae19e200ddb0f6845b5feeee5eb7013687`

Font Awesome และ html5shiv exact tags ไม่มี standalone full license text จึงใช้ declaration จาก exact tag จับคู่ full canonical text จาก SPDX `license-list-data` tag `v3.27.0`, tag object `60e0de29...`, commit `d46e94e2c78ceede1cfc63cfa0396472d2798d4c` ห้ามตีความว่า SPDX เป็น provenance ของ runtime bytes

Exact license URLs:

- `https://raw.githubusercontent.com/twbs/bootstrap/v3.3.7/LICENSE`
- `https://raw.githubusercontent.com/DataTables/DataTables/1.10.16/license.txt`
- `https://raw.githubusercontent.com/DataTables/FixedColumns/3.2.4/License.txt`
- `https://raw.githubusercontent.com/scottjehl/Respond/1.4.2/LICENSE-MIT`
- `https://raw.githubusercontent.com/spdx/license-list-data/v3.27.0/text/MIT.txt`
- `https://raw.githubusercontent.com/spdx/license-list-data/v3.27.0/text/OFL-1.1.txt`

## Bootstrap version conflict

Brief ระบุ Bootstrap 3.3.4 แต่ active CI3 bytes และ local headersระบุ Bootstrap 3.3.7 ตาม presentation authority จึงคง 3.3.7 และไม่ downgrade

## TDD และ verification

### Historical Task 6 RED/GREEN

| Gate | Historical result |
|---|---|
| Parser regressions | `OK (11 tests, 79 assertions)` |
| Dependency/license pins | `OK (1 test, 34 assertions)` |
| Closure บน exact temporary index | `OK (1 test, 1669 assertions)` |
| Focused asset suites | `OK (105 tests, 3898 assertions)` |
| Full PHPUnit | `OK (415 tests, 8316 assertions)` |
| Full `scripts/ci-check.sh` | ผ่านก่อน commit และผ่านจาก real checkpoint |

### Task 7 fix round 2 RED

| Finding | RED result |
|---|---|
| duplicate filename identity | ยกเลิก in-flight preview ชื่อซ้ำแล้ว completed file ถูกลบจาก final queue |
| existing-image render | หน้า edit ไม่มี `src="/order-image/{name}"` สำหรับ valid association |
| update failure rollback | regression เดิมผ่าน ยืนยันว่า prior association/file คงอยู่และลบเฉพาะไฟล์ใหม่ |

### Task 7 fix round 2 GREEN

| Gate | ผล |
|---|---|
| Duplicate/existing-image focused | `OK (6 tests, 75 assertions)` |
| `OrderHttpTest.php` | `OK (74 tests, 1235 assertions)` |
| Focused Order/Menu/Route | `OK (112 tests, 4268 assertions)` |
| Tracked runtime closure | `OK (1 test, 2116 assertions)` |
| Full PHPUnit | `OK (426 tests, 8982 assertions)` |
| PHPStan | `[OK] No errors` |
| Full `scripts/ci-check.sh` | ผ่านทุก gateด้วย exact temporary candidate 21 files |
| Exact CI3 order assets | `MATCH` ครบ 9 files |
| JavaScript syntax contract | browse 5 filesและ `admin_addOrder.js` ผ่าน; `addOrder.js` คง pinned failure ที่ `customerTel` |
| Task 7 patch whitespace | ผ่าน; `script.js` ใช้ path-scoped legacy whitespace setting |
| Real Git index | `write-tree` ก่อน/หลังเป็น `c6ce38a8953cb1dedf08e35446b3195347139425`; cached diff ว่าง |

Temporary index เริ่มจาก `6799684`, add whole-file paths 21 files และ apply เฉพาะ route hunk 1 hunk โดย wrapper ชั่วคราว unset `GIT_INDEX_FILE` เฉพาะ Git call ที่ชี้ CI3 checkout

### Browser finding: `bg-form.png` closure

Browser matrixพบ create/edit ของ centralและ branchร้องขอ `/assets/images/bg-form.png` แต่ candidateเดิมไม่มี target จึงเพิ่ม exact raw blobจาก CI3 pinและ regressionใน `OrderHttpTest`.

| Gate | ผล |
|---|---|
| RED ก่อนเพิ่ม asset | `FAILURES! Tests: 1, Assertions: 5, Failures: 1` เพราะ target fileไม่มี |
| Focused GREEN | `OK (1 test, 6 assertions)` |
| Full `OrderHttpTest.php` | `OK (76 tests, 1246 assertions)` |
| Raw CI3 pin identity | `MATCH`, 937 bytes, SHA-256 `65fd6f960ea58421a1ba10a8414332b05e9de97150c098578db73305048fa1c0` |

ค่า SHA-256 `258c80d40a1455fc6c03e0ca1530cf1a00cffa96394358a0225e67ca1b39894e` ใน implement briefมาจาก outputของ RTK binary rendering ขนาด 969 bytes ไม่ใช่ Git blob. Raw `/usr/bin/git show`, `git cat-file`, working-tree source และ presentation inventory v1/v2 ตรงกันที่ค่า `65fd6f...` และขนาด 937 bytes จึงใช้ raw CI3 pinเป็น authority.

## Browser proof status

Anonymous runtime probe ที่ `/orders/new` ได้ HTTP `401` จึงยังไม่มี authenticated browser proof โดยไม่ใช้ credential หรือแตะ shared DB

สถานะที่ยัง `BLOCKED` ครอบ file select, drag/drop, native `DataTransfer`, progress knob, CSRF refresh, repeated/multiple files, duplicate names, success/error, in-flight abort, post-completion delete และ final create/edit submit ใน browser จริง
