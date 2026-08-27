# TPL-01 runtime asset closure v1

เอกสารนี้บันทึก runtime asset graph จาก current CI4 views/CSS, exact checkpoint candidate, checksum, provenance และ license evidence เทียบ CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` โดยไม่ upgrade หรือ replace frontend dependency

## คำตัดสิน

| แกน | ผล | สถานะ |
|---|---:|---|
| Runtime closure | 109 files | resolve บน disk ครบ |
| Git tracked ใน closure | 16 files | 15 files ตรง index, `main.css` มี WIP ที่ต้องรวม candidate |
| Git untracked ใน closure | 93 files | `BLOCKED` จนกว่า gitops จะ add และ commit |
| License evidence นอก runtime graph | 7 files | Source Sans Pro 1 file และ license set ใหม่ 6 files |
| Frontend dependency upgrade | 0 | ผ่าน |
| Frontend dependency replacement | 0 | ผ่าน |
| Exact checkpoint candidate | 103 files | temporary-index simulation ผ่าน; real index ไม่ถูกเปลี่ยน |
| Upload snapshot provenance | 4 files | พิสูจน์แล้วจาก CI3 pin และ inventory v2 |

สถานะรวม: `PREPARED_AWAITING_REVIEW_AND_GITOPS`

## วิธีสร้าง graph

Test `MenuHttpTest::testSharedRuntimeAssetClosureExistsAndIsGitTracked` render HTML จริงแล้วเดิน CSS แบบ recursive

| Profile | Runtime source |
|---|---|
| Admin | `GET /dashboard` |
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

## Runtime closure 109 files

### Untracked runtime 93 files

| จำนวน | กลุ่ม | สมาชิก |
|---:|---|---|
| 8 | `public/assets/bootstrap/` | CSS 2, Glyphicons 5 formats, JavaScript 1 |
| 1 | `public/assets/css/` | `multifreezer.css` |
| 7 | `public/assets/datatables/1.10.16/` | CSS 1, JavaScript 1, sort images 5 |
| 2 | `public/assets/datatables-fixedcolumns/3.2.4/` | CSS 1, JavaScript 1 |
| 6 | `public/assets/dist/` | CSS 3, images 2, JavaScript 1 |
| 6 | `public/assets/font-awesome/` | CSS 2, fonts 4 |
| 6 | `public/assets/font-awesome/4.3.0/` | CSS 1, fonts 5 |
| 6 | `public/assets/font-awesome/4.7.0/` | CSS 1, fonts 5 |
| 6 | `public/assets/fontawesome/` | CSS 1, fonts 5 |
| 8 | `public/assets/fonts/source-sans-pro/` | stylesheet 1, TTF 7 |
| 1 | `public/assets/html5shiv/3.7.2/` | `html5shiv.min.js` |
| 5 | `public/assets/images/` | `404.png`, `access.png`, tracking images 2, `print-logo.jpg` |
| 6 | root `public/assets/js/` | Contact/Tracking scripts, jQuery 2 files, validate 2 files |
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

### Tracked runtime 16 files

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

## Exact checkpoint candidate 103 files

| จำนวน | กลุ่ม | เหตุผล |
|---:|---|---|
| 93 | untracked runtime closure | browser runtime caller มีจริง |
| 1 | `public/assets/fonts/source-sans-pro/OFL.txt` | Source Sans Pro license |
| 6 | `public/assets/licenses/` | full license texts และ exact bundle licenses |
| 1 | `public/assets/css/main.css` | `.jpg` เป็น `.png`; index blob ต้องตรง worktree |
| 1 | `tests/ci4/MenuHttpTest.php` | parser, checksum และ index-blob gates |
| 1 | `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md` | durable evidence |
| รวม | 103 | exclude `admin.css` และทุก must-not-add group |

License candidate 6 files:

- `public/assets/licenses/bootstrap-3.3.7-LICENSE`
- `public/assets/licenses/datatables-1.10.16-license.txt`
- `public/assets/licenses/fixedcolumns-3.2.4-License.txt`
- `public/assets/licenses/respond-1.4.2-LICENSE-MIT`
- `public/assets/licenses/MIT.txt`
- `public/assets/licenses/OFL-1.1.txt`

Required index inputs รวม 118 paths: runtime closure 109, license evidence 7, test 1 และ evidence 1

## Must not add

| กลุ่ม | เหตุผล |
|---|---|
| `public/assets/css/admin.css` | ไม่มี current runtime caller และเป็น WIP คนละ scope |
| `public/assets/plugins/**` | ไม่มี current caller และเป็น plugin tree ขนาดใหญ่ |
| SCSS, LESS, examples, docs, tests, specimen, demo | ไม่ใช่ runtime closure |
| `multifreezer.js` | CI3 caller อยู่ใน HTML comment |
| `cms-logo.png` | CI3 caller อยู่ใน HTML comment |
| Order browse chain และ `assets/css/style.css` | ขอบเขต Task 7 |
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

### RED ที่ reproduce

| Finding | RED result |
|---|---|
| style attribute | expected `bg-login.jpg`, parser คืน array ว่าง |
| quoted import/หลาย URL/DATA | ตก `b.png`, รับ `DATA:image/png` ผิด |
| exact origin | scheme และ port mismatch ไม่ throw |
| dynamic prefix | `/background-image/assets/app.css` ถูกนับเป็น static |
| traversal leave/re-enter | path ออกแล้วกลับ `public/` ผ่าน |
| CSS cycle mutation | `cycle guard mutation`, 1 failure |
| DataTables image mutation | `sort_both.png` expected hashผิด, 1 failure |
| Upload image mutation | SHA-256 ของ upload path ใด path หนึ่งเปลี่ยน ทำให้ expected hashผิด, 1 failure |
| temp index ขาด `main.css` | `Git index blob differs from worktree runtime input.` |

### GREEN ปัจจุบัน

| Gate | ผล |
|---|---|
| Parser regressions | `OK (11 tests, 79 assertions)` |
| Dependency/license pins | `OK (1 test, 34 assertions)` |
| Closure บน exact temporary index | `OK (1 test, 1669 assertions)` |
| Focused asset suites | `OK (105 tests, 3898 assertions)` |
| PHPStan | `[OK] No errors` |
| Full PHPUnit | `OK (415 tests, 8316 assertions)` |
| Full `scripts/ci-check.sh` | ผ่านทุก gate รวม secret file policy และ candidate-tree PII guard |
| `git diff --check` | ผ่าน ไม่มี output |
| Real index unchanged | `yes` ทุก temporary-index run |

Temporary index สร้างจาก `HEAD`, add exact candidate 103 files แล้วรัน test โดยส่ง `GIT_INDEX_FILE` เฉพาะ CI4 process ส่วน wrapper ชั่วคราว unset ตัวแปรนี้เฉพาะคำสั่ง Git/PHP ที่ตรวจ CI3 checkout เพื่อไม่ให้ index ของ CI4 รั่วข้าม repository

## Blockers และไม้ต่อ

1. gitops ต้อง add และ commit exact candidate 103 files หลัง review ผ่าน
2. ต้อง rerun focused suite, PHPStan, full PHPUnit และ `scripts/ci-check.sh` จาก real checkpoint index
3. Browser DOM, JavaScript interaction และ visual parity ยังไม่ใช่ผลของ Task 6
