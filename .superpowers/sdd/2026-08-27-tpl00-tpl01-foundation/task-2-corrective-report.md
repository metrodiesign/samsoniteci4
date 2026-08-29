# Task 2 corrective candidate

รายงานนี้บันทึก candidate ผ่าน temporary index จาก `HEAD` โดยไม่แก้ source/test ใน working tree และไม่แตะ real Git index. ผลทดสอบพิสูจน์ query adapter ที่เพิ่มได้จริง แต่ exact focused filter ตาม brief ยังไม่ GREEN เพราะ parent test มี failure ของ presentation contract ที่อยู่นอก scope ที่อนุญาต.

## Candidate และขอบเขต

| รายการ | ค่า |
|---|---|
| Base `HEAD` | `6799684db6de09936122d2ae25a5461a878b0eb3` |
| Parent test blob | `e2cd894^` (`09e35254135b39e562aef8c58012f2b29c14662b`) |
| RED tree | `9a100dae401b8810ecfa51bcf7dd7641066c99d5` |
| GREEN tree | `0c34d5c808b08ccfc8c74c325296cdad1643fd41` |
| RED path | `tests/ci4/PublicTrackingHttpTest.php` |
| GREEN paths | `app/Controllers/Tracking.php`, `tests/ci4/PublicTrackingHttpTest.php` |
| Final patch | `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-2-corrective.patch` |
| Docker images | `samsoniteci4-task2-corrective-{red,green,mut1,mut2,mut3,mut4,mut5}:20260828-01` |

สร้าง test จาก blob ของ `e2cd894^` แล้ว append `testCanonicalAndLegacyQueryAdapterPreservesPrecedenceAndNoTrimContract()` ตาม brief verbatim ก่อน closing class brace. GREEN tree เปลี่ยนเฉพาะ `Tracking::fromQuery()` ตาม hunk ที่กำหนด: canonical มี precedence เมื่อไม่ใช่ `null`, legacy `searchText` เป็น fallback เฉพาะ `null`, raw string ผ่าน allowlist และไม่มี `trim()`.

## RED evidence

สร้าง archive จาก RED tree และ build image `samsoniteci4-task2-corrective-red:20260828-01` ด้วย `Dockerfile.ci4`.

```bash
docker run --rm samsoniteci4-task2-corrective-red:20260828-01 vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/PublicTrackingHttpTest.php --filter 'testCanonicalAndLegacyQueryAdapterPreservesPrecedenceAndNoTrimContract|testSearchRejectsUnknownWildcardAndOversizedTrackingIdsWithoutPartialMatch'
```

ผลคือ `2 tests, 18 assertions, 1 failure` ที่ method ใหม่บรรทัด 209:

```text
Text 'SYNTHETIC RETURN' is not seen in response.
```

นี่พิสูจน์ missing legacy `searchText` lookup บน implementation เดิมโดยตรง ไม่ใช่ syntax หรือ setup error. Method หยุดที่ legacy assertion ก่อนถึง whitespace cases; code ใน `HEAD` ยืนยัน additional defect ว่า canonical ใช้ `trim()`.

## GREEN evidence

สร้าง archive จาก GREEN tree และ build image `samsoniteci4-task2-corrective-green:20260828-01`.

คำสั่ง exact ตาม brief ไม่ผ่านทั้งหมด:

```bash
docker run --rm samsoniteci4-task2-corrective-green:20260828-01 vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/PublicTrackingHttpTest.php --filter 'testCanonicalAndLegacyQueryAdapterPreservesPrecedenceAndNoTrimContract|testSearchRejectsUnknownWildcardAndOversizedTrackingIdsWithoutPartialMatch'
```

ผลคือ `2 tests, 24 assertions, 1 failure` ที่ parent method บรรทัด 63:

```text
Text 'Tracking ID not found' is not seen in response.
```

รัน method ของ corrective contract แยกเพื่อแยก consequence ของ hunk ออกจาก parent defect:

```bash
docker run --rm samsoniteci4-task2-corrective-green:20260828-01 vendor/bin/phpunit --configuration phpunit.xml.dist tests/ci4/PublicTrackingHttpTest.php --filter testCanonicalAndLegacyQueryAdapterPreservesPrecedenceAndNoTrimContract
```

ผลคือ `OK (1 test, 16 assertions)`. จึงยืนยัน canonical precedence, legacy fallback, no-trim whitespace rejection, array canonical rejection และ fallback เฉพาะ `null` สำหรับ hunk ที่เสนอได้จริง.

## Mutation evidence

ทุก mutation สร้างจากสำเนา GREEN temporary index ใหม่, archive ใหม่ และ image tag ใหม่ จึงไม่ carry state กลับเข้า candidate GREEN.

| Mutation | Tree | คำสั่ง filter | ผล | หลักฐาน |
|---|---|---|---|---|
| เพิ่ม `trim($value)` | `2e81f8e2060e985361b0aa08f49b3a21051983a9` | adapter method | RED, 1 F | whitespace case เห็น `SYNTHETIC RETURN` ซึ่งต้องห้าม |
| อ่านเฉพาะ canonical | `e4863a9fb0002d13d5f890f3bd99767592568fa2` | adapter method | RED, 1 F | valid legacy ไม่เห็น `SYNTHETIC RETURN` |
| ให้ legacy ชนะ | `cfc5b0cf12474011e558204101ad7b7c56cfe57e` | adapter method | RED, 1 F | canonical conflict ไม่เห็น `SYNTHETIC RETURN` |
| fallback เมื่อ canonical invalid | `d18fc189d7b06526de9354dcd96cc490b815d661` | adapter method | RED, 1 F | canonical whitespace พร้อม legacy เห็น `SYNTHETIC RETURN` |
| ผ่อน regex เป็น `^.{1,100}$` | `b62716c68bf21654550c13385f385eeb8b974433` | adapter method | false-green, 1 P | downstream `TrackingLookup::timeline()` มี allowlist เดิม จึง reject wildcard/oversized ซ้ำ |

Mutation 5 ไม่สามารถเป็น RED ตาม brief ได้ภายใต้ source จริง: `TrackingLookup::timeline()` ตรวจ allowlist `/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/D` ก่อน query อยู่แล้ว. แม้ `Tracking::fromQuery()` ผ่อน regex, wildcard และ oversized ยังถูก downstream ปฏิเสธและให้ output เดิม. Parent method ก็มี baseline presentation failure ก่อน mutation จึงใช้พิสูจน์ mutation นี้ไม่ได้โดยสุจริต.

## Patch และ safety checks

สร้าง patch จาก `git diff --binary HEAD <GREEN-tree>`; มีเพียงสอง paths ตาม contract:

```text
app/Controllers/Tracking.php
tests/ci4/PublicTrackingHttpTest.php
```

ตรวจผ่านด้วย temporary index:

```bash
GIT_INDEX_FILE=<temporary> git apply --cached --check .superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-2-corrective.patch
git diff --check HEAD 0c34d5c808b08ccfc8c74c325296cdad1643fd41
```

ทั้งสองคำสั่งผ่าน. Diffstat คือ `67 insertions, 393 deletions` ในสอง paths เท่านั้น; deletion ใน test คือการคืน contaminated `e2cd894` test file ไป parent blob แล้วเติม standalone adapter methodเดียว.

| Check | ก่อน | หลัง | ผล |
|---|---|---|---|
| Real index tree | `c6ce38a8953cb1dedf08e35446b3195347139425` | `c6ce38a8953cb1dedf08e35446b3195347139425` | ไม่เปลี่ยน |
| Working source/test โดยงานนี้ | ไม่แก้ | ไม่แก้ | ไม่เปลี่ยน |
| Candidate path sweep | 2 paths | 2 paths | ไม่มี WP03J presentation, segment หรือ view |

## Concerns และคำตัดสิน

- **BLOCKED for exact gate**: `testSearchRejectsUnknownWildcardAndOversizedTrackingIdsWithoutPartialMatch()` ที่ได้จาก required parent blob คาด `Tracking ID not found`, แต่ `Tracking::render()` ใน base ไม่ส่ง `$notFound` ไป `tracking_form`; failure นี้อยู่นอก exact `fromQuery()` hunk และห้ามแก้ view/render ตาม scope.
- **Mutation 5 false-green**: duplicate allowlist ใน `TrackingLookup` ทำให้ผ่อน controller regex แล้ว output ยังปลอดภัย; contract ที่บังคับให้ parent wildcard/oversized RED ไม่สอดคล้องกับ executable source.
- **Exclusions confirmed**: ไม่มี `tracking_form.php`, `tracking_result.php`, `layout_public.php`, route, presentation หรือ dirty WP03J path ใน final patch/candidate.
- **Cleanup pending**: ลบเฉพาะ images และ temporary resources ที่ task สร้างหลังทีมลีดอ่าน evidence แล้ว.
