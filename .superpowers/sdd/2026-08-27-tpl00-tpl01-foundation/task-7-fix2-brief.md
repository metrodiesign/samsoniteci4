# Brief แก้ Task 7 รอบ 2

แก้ findings จาก re-review รอบ 1 โดยแตะเฉพาะ Task 7 delta ห้าม stage, commit, push, revert งานอื่น หรือเปลี่ยน exact CI3 asset bytes

## เอกสารที่ต้องอ่าน

1. `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix1-re-review.md`
2. `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix1-report.md`
3. `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/progress.md` เฉพาะ Task 7 rulings
4. current Task 7 production/tests/evidence
5. CI3 authority commit `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`

## คำตัดสินที่ผูกงาน

### Duplicate filename queue

แก้ adapter ให้ preview nodeผูกกับ File identityเฉพาะ ห้ามใช้ `file.name` เป็น key

- ต้องถูกกับไฟล์ชื่อซ้ำแต่ bytesต่างกัน
- ครอบ repeated selection, drag/drop, in-flight abort และ post-completion delete
- ห้ามแก้ exact `public/assets/js/browse/script.js`
- เพิ่ม executable regression ที่พิสูจน์ว่าลบ previewตัวใด final queueลบ Fileตัวเดียวกัน

### Existing image ในหน้า edit

คืนการแสดงภาพเดิมผ่าน secure `/order-image/{name}` ตาม CI3 observable behavior

- parse valid existing namesจาก `detailImage`
- escape URL/markup และไม่ renderชื่อ malformed
- no-upload เก็บ associationเดิม
- replacement เปลี่ยน DB associationแต่คง prior fileบน diskตาม CI3 behaviorและ rulingใน ledger
- update failureเก็บ prior association/fileและลบเฉพาะไฟล์ใหม่
- แก้ test/commentให้ไม่เรียก prior fileว่า orphan และเพิ่ม render assertions

### Browser-proof claims

- ถอนหรือปรับ mutation claimsที่ PHPUnitไม่ได้ขับ DataTransfer/cancel/deleteจริง
- แยก automated server proofจาก browser proofชัดเจน
- หากทำ executable browser testบน isolated current-source runtimeได้โดยไม่สร้าง credentialหรือแตะ shared DB ให้รัน
- หาก authยัง block ให้คง `BLOCKED` และระบุ matrixที่ยังไม่ขับ ห้ามอ้าง PASS

### Durable evidence

ปรับ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md` ให้แยก:

- historical Task 6 closure/candidate ก่อน commit
- Task 6 committed checkpoint `6799684`
- current Task 7 delta และ exact asset additions

ตัวเลขหรือ Git stateที่เขียนเป็น currentต้องตรงผลรันจริง ห้ามเปลี่ยน historical evidenceย้อนหลังจนเสียความหมาย

### Route scope contamination

`app/Config/Routes.php` มี hunksจากงานก่อน Task 7 ได้แก่ tracking `(:any)` และ password-reset aliases

- ห้าม revertหรือแก้ hunksเหล่านั้น
- สร้าง Task 7 review/staging manifestที่ระบุ route hunkเฉพาะ `POST order/do_upload_multi/(:segment)` และ route testที่เกี่ยว
- verification packageต้องไม่เสนอ whole-file stagingของ unrelated hunks
- หากทำ clean patchจาก base `6799684` ไม่ได้โดยไม่ทับงานอื่น ให้รายงาน exact patch/hunkสำหรับ gitops ห้าม stageจริง

## Verification

1. TDD RED/GREEN สำหรับ duplicate identityและ existing-image render
2. focused Order/Menu/Route tests
3. full PHPUnit
4. PHPStan
5. `scripts/ci-check.sh` ผ่าน temporary indexที่ประกอบเฉพาะ Task 7 deltaและ route hunk
6. JavaScript syntax contract และ exact CI3 checksums
7. real Git indexก่อน/หลังต้องเหมือนเดิม
8. `git diff --check` บน Task 7 patch; exact legacy whitespaceห้าม rewrite

## Coverage self-check

ไล่ create/edit, central/branch, single/multiple/repeated files, duplicate names, success/error/abort/delete, CSRF refresh, existing-image valid/malformed, no-upload, replacement และ update failure ระบุ branchใดมี executable browser proofกับ branchใดยัง `BLOCKED`

## Report

เขียนภาษาไทยที่:

`.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-fix2-report.md`

ตอบกลับสั้น: สถานะ, ไฟล์ที่แก้, tests, exact Task 7 staging manifest/hunk และ concerns
