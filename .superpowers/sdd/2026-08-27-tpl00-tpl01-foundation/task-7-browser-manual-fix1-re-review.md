# Re-review Fix Round 1: Task 7 Browser Manual Helper

ตรวจแบบ read-only เฉพาะ Important 1–4 จาก review เดิม และตรวจ regression ระดับ Critical/Important ที่เกิดจาก fix โดยไม่รัน start/cleanup helper และไม่แตะ Docker

## สรุปคำตัดสิน

| Finding เดิม | สถานะ | หลักฐานย่อ |
|---|---|---|
| Important 1: concurrent ownership | ADDRESSED | reserve แบบ `noclobber`, compare owner ก่อน publish/remove และ cleanup ปฏิเสธ owner ที่ยังทำงาน |
| Important 2: unsafe host tree deletion | ADDRESSED | ไม่มี host temp tree หรือ recursive delete; ใช้ temporary index file และ stream exact tree |
| Important 3: interrupted lifecycle | ADDRESSED | มี EXIT/HUP guard, early recovery record, publish/disarm ordering และระบุ SIGKILL limit |
| Important 4: credential mutation proof | NOT ADDRESSED | allowlist แบบ substring ยังปล่อย hash file sink ที่เติมข้อมูลประกอบให้ผ่านครบ 46 assertions |

## Important 1: Concurrent ownership

**สถานะ: ADDRESSED**

- Atomic reservation เกิดก่อนสร้าง Docker resource ด้วย `set -o noclobber` ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:113-118` และถูกเรียกก่อน trap/resource work ที่ไฟล์เดียวกัน `:207-215`
- Start compare ทั้ง `resource_prefix` และ `owner_pid` ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:79-83` ก่อน replace metadata ตอน publish ที่ `:120-129`
- EXIT cleanup ลบ metadata เฉพาะเมื่อ cleanup สำเร็จและ ownership ยังตรง ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:194-197`
- Cleanup ปฏิเสธ reservation สถานะ `starting` เมื่อ owner PID ยังทำงาน ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-cleanup.sh:125-140` และ compare ownership ซ้ำก่อนลบ metadataที่ `:142-169`

ผลคือ start ตัวที่สองไม่สามารถแย่ง metadata และ cleanup ไม่สามารถปลด live startup reservation ตาม flow ปัจจุบัน

## Important 2: Host temp tree deletion

**สถานะ: ADDRESSED**

- Host artifact ที่ใช้สร้าง exact tree เหลือเพียง temporary Git index file ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:54-56`
- Index ถูกลบด้วย exact-path `rm -f` หลังตรวจ regular file/non-symlink ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:85-89`
- Schema และ build context ถูก stream จาก candidate tree โดยตรง ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:268-271`
- Cleanup ลบเฉพาะ Docker namesที่ derive แบบ exact จาก validated `RESOURCE_PREFIX` ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-cleanup.sh:125-160`
- Static guard ปฏิเสธ `TEMP_ROOT`, `SOURCE_TREE` และ `rm -rf` ใน start/cleanup ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh:132` และ `:161`

ไม่พบ host temp tree, metadata-controlled host path หรือ recursive directory delete ใน current helpers

## Important 3: Interrupted lifecycle

**สถานะ: ADDRESSED**

- EXIT cleanup guard cleanup partial resource, exact temporary index และ owned metadata ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:167-205`
- Trap ครอบคลุม EXIT, HUP, INT และ TERM ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:211-215`
- Early metadata สถานะ `starting` ถูก reserve ก่อน resource work ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:91-118`
- Ready metadata ถูก publish ก่อน disarm และถอด trap ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:379-381`
- Cleanup ใช้ early record กู้ recovery run และปฏิเสธเฉพาะ owner ที่ยัง live ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-cleanup.sh:99-140`
- SIGKILL ถูกระบุชัดว่า trap ไม่ได้ แต่ early record ช่วย recovery ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:215` และ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-report.md:200-202`

Ordering ปัจจุบันไม่เปิดช่อง ready runtime แบบไม่มี metadata: signal ก่อน disarm จะ cleanup ส่วน signal หลัง disarm เกิดหลัง ready metadata ถูก publish แล้ว

## Important 4: Credential mutation proof

**สถานะ: NOT ADDRESSED**

Current helper ไม่พบ plaintext passwordหรือ password hash sink นอก intended in-memory pipeline/isolated DB flow ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:328-343`

อย่างไรก็ตาม test proof ยัง false-green เพราะ `secret_uses_are_allowed()` ใช้ substring allowlist ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh:52-66` โดย case ที่ `:61` อนุญาตทุกบรรทัดที่มี `"$CENTRAL_USERNAME" "$PASSWORD_HASH"` ไม่ได้จำกัดว่าเป็น seed SQL pipe เท่านั้น

เพิ่ม mutation ต่อไปนี้ในสำเนาชั่วคราวของ start helper โดยไม่มี credential จริง:

```bash
printf '%s %s\n' "$CENTRAL_USERNAME" "$PASSWORD_HASH" > "$WORKSPACE/hash-leak.txt"
```

จากนั้นรัน full helper test ผ่าน `TASK7_START_HELPER_UNDER_TEST` ได้ผล:

```text
PASS: 46 assertions
```

mutation นี้เป็น password-hash file sink จริง แต่หลบ adversarial proof ได้เพราะมี substring ที่ allowlist ยอมรับ แม้ test จะมี six named mutations ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh:116-121` จึงยังไม่ปิด Required change ของ Important 4

## Verification

| Check | ผล |
|---|---|
| Helper contract test ปัจจุบัน | `PASS: 46 assertions` |
| `bash -n` ทั้งสาม script | PASS, exit `0`, ไม่มี output |
| Adversarial hash-file mutation แบบหลบ substring allowlist | false-green: `PASS: 46 assertions` |
| `git diff --cached --exit-code` | PASS, staged diff ว่าง |
| Real index tree | `c6ce38a8953cb1dedf08e35446b3195347139425` |
| Start/cleanup execution | ไม่รันตามข้อห้าม |
| Docker | ไม่แตะ |

## Regression sweep

ไม่พบ Critical หรือ Important ใหม่จาก fix ใน ownership, host filesystem boundary หรือ EXIT/HUP lifecycle นอก Important 4 ที่ยังเปิดอยู่

Verdict: OPEN FINDINGS

Counts: Critical 0, Important ADDRESSED 3, Important NOT ADDRESSED 1, New Critical/Important 0
