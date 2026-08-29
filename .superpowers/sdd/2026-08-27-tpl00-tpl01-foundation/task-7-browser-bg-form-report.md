# รายงาน Task 7 Browser bg-form asset closure

ปิด Browser finding ที่ create/edit ของ central และ branch ร้องขอ `/assets/images/bg-form.png` แล้วได้ 404 โดยเพิ่ม exact raw blob จาก CI3 pin พร้อม regression, evidence และ exact candidate tree ใหม่ โดยไม่แตะ Browser runtime, Docker หรือ real Git index

## สถานะ

| รายการ | ผล |
|---|---|
| Finding | ปิดแล้ว |
| TDD | RED ก่อน asset และ GREEN หลังเพิ่ม exact bytes |
| Focused regression | ผ่าน |
| Full `OrderHttpTest.php` | ผ่าน |
| Helper tests และ syntax | ผ่าน |
| Exact candidate | 21 whole files + route patch, รวม 22 paths |
| Candidate tree | `e51837eec685b090f1072a8b2887fa7008f4c587` |
| Real Git index | ไม่เปลี่ยน |
| Stage, commit, push | ไม่ทำ |
| Browser runtime และ Docker | ไม่รันและไม่แตะ |

## Root cause และ caller

Caller ที่มีอยู่แล้วอ้าง asset เดียวกัน:

- `app/Views/order_new.php`: create form
- `app/Views/order_edit.php`: edit form
- Target ที่ขาด: `public/assets/images/bg-form.png`
- CI3 source authority: `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6:assets/images/bg-form.png`

ไม่แก้ template, CSS หรือ JavaScript เพราะ caller ถูกต้องอยู่แล้ว; root cause คือ candidate ขาด runtime asset

## Source authority

Raw CI3 blob มีค่าต่อไปนี้:

| Field | ค่า |
|---|---|
| Size | 937 bytes |
| SHA-256 | `65fd6f960ea58421a1ba10a8414332b05e9de97150c098578db73305048fa1c0` |
| CI4 target | byte-identical กับ raw CI3 blob |
| Inventory corroboration | presentation inventory v1 และ v2 pin ค่าเดียวกัน |

ค่า `258c80d40a1455fc6c03e0ca1530cf1a00cffa96394358a0225e67ca1b39894e` ใน brief ไม่ใช่ Git blob จริง แต่เป็น checksum ของ stream ขนาด 969 bytes ที่ RTK สร้างเมื่อ render binary จาก `git show`. การตรวจด้วย `/usr/bin/git show`, `git cat-file`, working-tree source และ inventory ทั้งสองชุดตรงกันที่ `65fd6f...` และ 937 bytes จึงยึด raw CI3 pin ตาม requirement ที่ต้องคัด exact bytes

คำสั่งตรวจ byte identity:

```bash
shasum -a 256 public/assets/images/bg-form.png
stat -f '%z' public/assets/images/bg-form.png
cmp -s public/assets/images/bg-form.png <(/usr/bin/git -C ../samsoniteci3 show ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6:assets/images/bg-form.png)
```

ผล:

```text
65fd6f960ea58421a1ba10a8414332b05e9de97150c098578db73305048fa1c0
937
cmp exit 0
```

## TDD evidence

### RED ก่อนเพิ่ม asset

เพิ่ม `OrderHttpTest::testCreateAndEditUsePinnedCi3BackgroundFormAsset` ก่อนสร้าง target แล้วรันเฉพาะ method

```bash
vendor/bin/phpunit tests/ci4/OrderHttpTest.php --filter testCreateAndEditUsePinnedCi3BackgroundFormAsset
```

ผลล้มด้วยสาเหตุที่ต้องการ:

```text
Failed asserting that file "/Users/king_developer/Desktop/Project/samsoniteci4/public/assets/images/bg-form.png" exists.
FAILURES!
Tests: 1, Assertions: 5, Failures: 1.
```

HTML assertions ผ่านครบทั้ง central/branch และ create/edit ก่อนถึง file assertion จึงพิสูจน์ว่า RED เกิดจาก target asset หาย ไม่ใช่ route หรือ template

### GREEN หลังเพิ่ม exact bytes

คัด raw blob ด้วย `/usr/bin/git show` แล้วรัน method เดิม:

```text
OK (1 test, 6 assertions)
```

Regression ตรึง contract ต่อไปนี้:

- central create HTML อ้าง `/assets/images/bg-form.png`
- central edit HTML อ้าง `/assets/images/bg-form.png`
- branch create HTML อ้าง `/assets/images/bg-form.png`
- branch edit HTML อ้าง `/assets/images/bg-form.png`
- target file ต้องมีจริง
- SHA-256 ต้องตรง raw CI3 pin

การลบ target หรือเปลี่ยนหนึ่ง byteทำให้ test RED ที่ file existence หรือ checksum ตามลำดับ

## ไฟล์ที่เปลี่ยน

| Path | การเปลี่ยน |
|---|---|
| `tests/ci4/OrderHttpTest.php` | เพิ่ม regression สำหรับ HTML caller, target existence และ checksum |
| `public/assets/images/bg-form.png` | เพิ่ม exact 937-byte CI3 blob |
| `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md` | runtime 119, order assets 10, candidate 22, images group 6 และ provenance |
| `task-7-browser-manual-start.sh` | เพิ่ม assetใน `WHOLE_FILE_PATHS` และ expected tree ใหม่ |
| `task-7-browser-manual-helper-test.sh` | ตรึง asset path, whole-file count 21 และ expected treeใหม่ |
| `task-7-browser-manual-report.md` | อัปเดต count, tree และ helper verification |

ไฟล์ scratch ทั้งหมดอยู่ใต้ `.superpowers/sdd/` และถูก ignore; candidate treeรวมเฉพาะ production/test/evidence paths ตาม helper

## Verification

| Gate | ผล |
|---|---|
| Focused bg-form method | `OK (1 test, 6 assertions)` |
| Full `OrderHttpTest.php` | `OK (76 tests, 1246 assertions)` |
| Helper contract test | `PASS: 61 assertions` |
| `bash -n` start/test/cleanup | PASS, exit 0, ไม่มี output |
| Candidate whole-file count | 21 |
| Candidate changed path count | 22 |
| Candidate tree | `e51837eec685b090f1072a8b2887fa7008f4c587` |
| Candidate treeเทียบ helper constant | MATCH |
| Asset checksum, size, byte identity | MATCH |
| Candidate file whitespace | `git diff --check` ผ่าน |
| Required files | มีจริงครบ |
| Cached diff | ว่าง |
| Real index treeก่อน/หลัง | `c6ce38a8953cb1dedf08e35446b3195347139425` ทั้งคู่ |

Exact candidate ถูกสร้างด้วย temporary index จาก base `6799684db6de09936122d2ae25a5461a878b0eb3`, add 21 whole files และ apply `task-7-route.patch` เฉพาะ cached temporary index. Temporary index ถูกลบหลังตรวจและไม่มีคำสั่ง stage ต่อ real index

## Candidate composition

Candidate รวม 22 paths:

- Production 8 paths รวม route patchหนึ่ง hunk
- Exact order assets 10 paths รวม `public/assets/images/bg-form.png`
- Tests 3 paths
- Evidence 1 path

ตัวเลข runtime closure ปัจจุบัน:

| กลุ่ม | จำนวน |
|---|---:|
| Runtime closure | 119 files |
| Exact order assets | 10 files |
| `public/assets/images/` | 6 files |
| Task 7 candidate | 22 paths |

## Constraints และ concerns

- ไม่รัน Browser runtimeตาม brief จึงไม่มี browser re-probeหลังแก้ใน implement roundนี้
- ไม่รัน start/cleanup helper และไม่สร้าง แก้ หรือลบ Docker resource
- ไม่ stage, commit หรือ push
- ข้อขัดแย้ง checksumใน brief ถูก resolveด้วย raw Git blobและ inventory authority; หาก downstream toolใช้ `rtk git show` กับ binary ต้องเปลี่ยนเป็น `/usr/bin/git show` หรือ `git cat-file` เพื่อหลีกเลี่ยง rendered stream
