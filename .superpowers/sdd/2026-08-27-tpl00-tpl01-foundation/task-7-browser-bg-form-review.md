# Review Task 7 Browser bg-form asset closure

ตรวจ rework เทียบ brief, current files, regression, scratch helper, raw CI3 pin และ exact temporary-index candidate โดยไม่รัน Browser runtime, start/cleanup helper หรือ Docker

## คำตัดสิน

| แกน | ผล | เหตุผลย่อ |
|---|---|---|
| Spec | PASS | exact raw CI3 bytes, caller closure, TDD evidence, count และ candidate tree ครบ |
| Quality | APPROVED | retrieval ใช้ raw Git authority, checksum regression ทำงานจริง, candidate ปลอดภัยต่อ real index และไม่พบ scope creep |
| Verdict | PASS / APPROVED | พร้อมเข้าสู่ checkpoint gate ตาม exact candidate 22 paths |

## Spec review

| Requirement | ผล | หลักฐาน |
|---|---|---|
| Raw CI3 pin identity | PASS | target เป็น PNG 937 bytes, SHA-256 `65fd6f960ea58421a1ba10a8414332b05e9de97150c098578db73305048fa1c0` และ `cmp` กับ `git cat-file` ได้ exit 0 |
| Caller closure | PASS | create และ edit อ้าง path เดียวกันที่ `app/Views/order_new.php:34` และ `app/Views/order_edit.php:47` |
| Regression contract | PASS | HTML 4 combinations, file existence และ SHA-256 อยู่ที่ `tests/ci4/OrderHttpTest.php:655-666` |
| RED/GREEN evidence | PASS | missing-file RED และ GREEN บันทึกที่ `task-7-browser-bg-form-report.md:60-97`; focused runปัจจุบันผ่าน 1 test, 6 assertions |
| Evidence counts | PASS | runtime 119, candidate 22 และ order assets 10 ที่ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:7-11,38-61`; image group 6 ที่บรรทัด 110 |
| Asset provenance | PASS | path, CI3 source, checksum และ 937-byte provenance ที่ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:221-247` |
| Helper candidate constants | PASS | base, expected tree และ 21 whole pathsอยู่ที่ `task-7-browser-manual-start.sh:7-44` |
| Helper contract assertions | PASS | asset path, count 21 และ tree gateตรึงที่ `task-7-browser-manual-helper-test.sh:267-280` |
| Candidate composition | PASS | temporary indexได้ 21 whole files + route patch, changed pathsรวม 22 และ tree `e51837eec685b090f1072a8b2887fa7008f4c587` |
| Real index safety | PASS | treeก่อน/หลังเป็น `c6ce38a8953cb1dedf08e35446b3195347139425` และ cached diffว่าง |

## Quality review

### Binary retrieval integrity

RTK render binaryไม่ใช่ raw blob:

```text
rtk git show:       969 bytes, SHA-256 258c80d40a1455fc6c03e0ca1530cf1a00cffa96394358a0225e67ca1b39894e
/usr/bin/git show:  937 bytes, SHA-256 65fd6f960ea58421a1ba10a8414332b05e9de97150c098578db73305048fa1c0
git cat-file:       937 bytes, SHA-256 65fd6f960ea58421a1ba10a8414332b05e9de97150c098578db73305048fa1c0
```

การ override checksumใน brief จึงถูกต้องตาม requirement ที่ต้องใช้ exact raw CI3 pin; รายงานอธิบาย root causeนี้ที่ `task-7-browser-bg-form-report.md:31-58` และ evidenceหลักที่ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:347-358`

### Candidate และ scope

- Candidate มี production 8, assets 10, tests 3 และ evidence 1 รวม 22 paths ตรง `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:38-61`
- `bg-form.png` ถูกเพิ่มเป็น whole-file pathเดียว; routeยังเป็น patchหนึ่ง hunkที่ `task-7-route.patch:1-11`
- Reworkไม่เพิ่ม substitute image และไม่ต้องแก้ caller, CSS หรือ JavaScript เพราะ callerเดิมถูกต้อง
- Exact candidateมี blobที่ path runtimeจริง ขนาดและ checksumตรง raw CI3 จึงปิดสาเหตุ 404 ที่เกิดจาก missing target
- ไม่รัน authenticated browser re-probeตามข้อห้ามใน brief; ข้อนี้ไม่ขัดการอนุมัติ เพราะ findingระบุ root causeเป็น assetหายและ candidateปัจจุบันมี exact targetแล้ว

## Findings

| Severity | Path:line | Finding |
|---|---|---|
| Critical | ไม่มี | ไม่พบ |
| Important | ไม่มี | ไม่พบ |
| Minor | ไม่มี | ไม่พบ |

## Verification

| Command หรือ gate | ผล |
|---|---|
| Focused `OrderHttpTest` method | `OK (1 test, 6 assertions)` |
| Full `tests/ci4/OrderHttpTest.php` | `OK (76 tests, 1246 assertions)` |
| Helper contract test | `PASS: 61 assertions` |
| `bash -n` start/test/cleanup | PASS |
| Raw targetเทียบ CI3 `git cat-file` | MATCH, 937 bytes, SHA-256 `65fd6f...` |
| Temporary-index candidate | 21 whole files, 22 changed paths, tree `e51837e...` |
| Candidate asset blob | 937 bytes, SHA-256 `65fd6f...` |
| Real indexก่อน/หลัง | `c6ce38a...` ทั้งคู่; cached diffว่าง |
| `git diff --check` สำหรับ test/evidence | PASS |

## Final verdict

**Spec PASS. Quality APPROVED.** ไม่พบ finding ที่ต้องส่งกลับแก้ และ exact candidateพร้อมสำหรับ checkpoint โดยยังต้องคงข้อห้ามไม่ stageนอก candidate composition
