# Re-review Task 6 fix round 2

เอกสารนี้ตรวจ fix round 2 แบบ read-only โดยเทียบ finding ที่ยังเปิดจากรอบก่อนกับ current test, review package, evidence และ bytes ปัจจุบัน ไม่ได้แก้ source, stage หรือ commit

## คำตัดสิน

| แกน | คำตัดสิน | หลักฐาน |
|---|---|---|
| Finding เดิมข้อ 5 | `ADDRESSED` | upload ทั้ง 4 paths มี direct SHA-256 assertion และ provenance ผูก CI3 pin ได้ตรวจซ้ำ |
| New Critical/Important | ไม่มี | ไม่พบ ROOT path ผิด, mutation ที่ไม่ไว, คำสั่ง reproduce ผิดบริบท, path/inventory ไม่ตรง หรือ binary edit ใหม่ |
| Approved candidate count | **103 files** | exact set คงเดิม ไม่มีไฟล์ scope ใหม่ |
| Final verdict | **APPROVED** | Task 6 พร้อมส่ง gitops stage exact candidate 103 files |

## Finding เดิม: upload checksum และ provenance

สถานะ `ADDRESSED`

| CI4 target | direct SHA-256 assertion | CI3 pinned source | Inventory v2 |
|---|---|---|---|
| `public/uploads/web/contact_laptop.png` | `2520b9e21373a7822bf2388cd043684a8e0bcdc41071c6a562d539964e7f038f` | `assets/images/bg-contact.png` | JSON `:2788-2793` |
| `public/uploads/web/contact_mobile.png` | `2520b9e21373a7822bf2388cd043684a8e0bcdc41071c6a562d539964e7f038f` | `assets/images/bg-contact.png` | JSON `:2788-2793` |
| `public/uploads/web/track_laptop.png` | `16b99ac15ba78c5dd6a462de19b8c349747b7621301a7a1cb3858e09753c813a` | `assets/images/bg-tracking.png` | JSON `:2844-2849` |
| `public/uploads/web/track_mobile.png` | `16b99ac15ba78c5dd6a462de19b8c349747b7621301a7a1cb3858e09753c813a` | `assets/images/bg-tracking.png` | JSON `:2844-2849` |

- **Direct assertions**: `tests/ci4/MenuHttpTest.php:435-438` pin ครบสี่ target และ `:445-446` เปรียบ expected checksum กับ `hash_file('sha256', PUBLICPATH . $path)` โดยตรง
- **ROOT path**: key `uploads/web/...` รวมกับ `PUBLICPATH` ได้ `public/uploads/web/...` ตรง target จริง ไม่ใช่ path ชี้ผิด root
- **Mutation sensitivity**: การเปลี่ยน bytes ของ target ใดเปลี่ยนผล `hash_file()` และทำให้ `assertSame()` RED โดยตรง; evidence บันทึก mutation RED ที่ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:233-245`
- **Hash verification**: รัน `shasum -a 256` กับสี่ target แล้วได้ hash ตรง pin ทั้งหมด

## Provenance และคำสั่งตรวจซ้ำ

- **CI3 authority**: pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` ระบุชัดที่ evidence `:152-170` และ inventory Markdown `outputs/reference/2026-08-27_ci3-presentation-inventory_v2.md:3-12`
- **Exact sources**: contact pair ผูก `assets/images/bg-contact.png`; tracking pair ผูก `assets/images/bg-tracking.png` ตรงกับ inventory JSON และ checksum ของ source
- **Reproduction**: evidence ระบุบริบทถูกต้องว่าให้รันสองคำสั่ง `git show <pin>:assets/images/... | shasum -a 256` จาก CI3 checkout และสองคำสั่ง `shasum` ของ target จาก CI4 root ที่ `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md:161-168`
- **RTK**: คำสั่งใน evidence เป็น shell command มาตรฐานที่รันได้ตามตัว ไม่มีการบันทึก syntax ของ `rtk` ที่ทำให้ reproduce เปลี่ยนความหมาย
- **สถานะ**: evidence เปลี่ยนเฉพาะ upload provenance จาก `BLOCKED` เป็นพิสูจน์แล้วตาม CI3 source, hash และ inventory; ไม่อ้าง browser, visual parity หรือ provenance ของ asset อื่นเกินหลักฐาน

## Candidate และขอบเขต

Approved candidate คือ **103 files** ตาม staged groups ต่อไปนี้

| จำนวน | กลุ่ม |
|---:|---|
| 93 | untracked runtime closure |
| 1 | `public/assets/fonts/source-sans-pro/OFL.txt` |
| 6 | `public/assets/licenses/` |
| 1 | `public/assets/css/main.css` |
| 1 | `tests/ci4/MenuHttpTest.php` |
| 1 | `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md` |
| 103 | รวม |

- **Scope**: candidate count ยังเป็น `103`; `public/assets/css/admin.css` ยัง exclude และไม่มี path ใหม่จาก fix round 2
- **Binary hygiene**: `git diff --numstat` แสดงเฉพาะ `main.css` และ test ในขอบเขตที่ตรวจ; upload และ license เป็น untracked candidate ไม่มี tracked binary modification จากรอบนี้
- **Evidence consistency**: count, target paths, source paths, inventory line ranges และ assertion count `34` ตรงกันระหว่าง report/evidence/current test

## Verification และสถานะ index

| ตรวจ | ผล |
|---|---|
| Upload hashes ปัจจุบัน | ตรง direct pins ทั้ง 4 files |
| Dependency/upload test | `OK (1 test, 34 assertions)` ตาม evidence |
| Focused asset suites | `OK (105 tests, 3898 assertions)` ตาม evidence |
| Full PHPUnit | `OK (415 tests, 8316 assertions)` ตาม evidence |
| Full `scripts/ci-check.sh` | ผ่านตาม evidence ด้วย exact temporary candidate |
| Real-index closure ก่อน gitops | RED ตามคาด เพราะ candidate ยัง untracked, ไม่ใช่ failure ของ fix |

real-index RED ต้องคงเป็น pre-gitops signal ที่ถูกต้อง; หลัง gitops stage/commit exact candidate 103 files จึง rerun gates บน real index ตามไม้ต่อที่ evidence `:263-267` ระบุ

## Verdict

**`APPROVED` — finding เดิม `ADDRESSED`, ไม่มี finding Critical/Important ใหม่, approved candidate count `103 files`**
