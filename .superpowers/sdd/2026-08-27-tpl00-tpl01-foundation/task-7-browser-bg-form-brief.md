# Task 7 Browser finding: bg-form asset closure

Browser matrixบน exact candidateพบ `GET /assets/images/bg-form.png` ตอบ `404` ทั้ง centralและ branch create/edit จึงเป็น load-bearing runtime asset closure finding

## Root cause

- Callerจริง: `app/Views/order_new.php` และ `app/Views/order_edit.php`
- CI3 authorityมี `assets/images/bg-form.png`
- CI4 candidateไม่มี `public/assets/images/bg-form.png`
- CI3 SHA-256: `258c80d40a1455fc6c03e0ca1530cf1a00cffa96394358a0225e67ca1b39894e`
- CI3 size: `937` bytes

## Required changes

1. เขียน failing regressionใน `tests/ci4/OrderHttpTest.php` ก่อนเพิ่ม asset
   - create/edit HTMLต้องอ้าง `/assets/images/bg-form.png`
   - target fileต้องมีจริง
   - SHA-256ต้องตรง CI3 pin
   - mutationหรือลบ targetต้องทำ test RED
2. คัด exact bytesจาก CI3 pinไป `public/assets/images/bg-form.png`
3. ห้ามแก้ template/CSS/JavaScriptหรือสร้าง substitute image
4. อัปเดต `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md`
   - runtime closure `118` เป็น `119`
   - exact order assets `9` เป็น `10`
   - Task 7 candidate `21` เป็น `22`
   - `public/assets/images/` จาก `5` เป็น `6`
   - เพิ่ม path/checksum/provenanceของ `bg-form.png`
5. อัปเดต scratch helper `task-7-browser-manual-start.sh`
   - `WHOLE_FILE_PATHS` เพิ่ม asset
   - whole-file count `20` เป็น `21`
   - regenerate exact candidate treeจาก base `6799684` + 21 whole files + route patch
   - อัปเดต `EXPECTED_CANDIDATE_TREE` เป็น hashใหม่
6. อัปเดต scratch helper tests/report constantsและ count assertionsให้ตรง treeใหม่
7. Candidateรวมต้องเป็น 22 paths: 21 whole files + route patchหนึ่ง hunk
8. ห้าม stage, commit, pushหรือแก้ real Git index

## Verification

- RED ก่อน assetต้อง failเพราะ missing file
- GREEN focused `OrderHttpTest.php` อย่างน้อย method asset contractและ full class
- helper contract testsและ `bash -n` ผ่าน
- temporary-index treeตรง helper expected tree
- `git diff --cached --exit-code` ผ่าน และ real index treeคง `c6ce38a8953cb1dedf08e35446b3195347139425`
- report full evidenceที่ `task-7-browser-bg-form-report.md`

ห้ามรัน Browser runtimeหรือ cleanup Dockerใน implement roundนี้
