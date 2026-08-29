# Review Task 7 Browser manual-login helper

ตรวจแบบ read-only ตาม brief ทั้งด้าน spec compliance และคุณภาพ/security ของ shell helper โดยไม่รัน start หรือ cleanup helper และไม่แตะ Docker resource

Spec: FAIL

Quality: CHANGES REQUIRED

## สรุปคำตัดสิน

| ระดับ | จำนวน |
|---|---:|
| Critical | 0 |
| Important | 4 |
| Minor | 1 |

ตัว helper ปัจจุบัน materialize exact candidate ได้ถูกต้อง และ password path จริงไม่พบ plaintext/hash leak แต่ยังไม่ผ่าน contract เพราะ lifecycle, concurrent ownership, cleanup boundary และ test proof มีช่องว่างที่ทำให้ resource ค้างหรือลบ path นอก scope ได้

## Findings

### Important 1: start พร้อมกันได้และแย่ง runtime metadata เดียวกัน

- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:53-59`
- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:106-107`
- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:276-298`

การเช็คว่า metadata ยังไม่มีเป็น check-then-act ที่ไม่มี lock ตลอดช่วง build ซึ่งอาจกินเวลาหลายนาที ดังนั้น start สอง process สามารถผ่านพร้อมกันและสร้าง resource คนละ `RUN_ID` ได้

process ที่พร้อมทีหลังจะ `mv` ทับ metadata ของ process แรก และถ้า process ใดล้ม `cleanup_partial()` จะลบ metadata กลางของอีก process โดยไม่ตรวจ `resource_prefix` เจ้าของไฟล์

- **ผลกระทบ**: cleanup รู้จักเพียงหนึ่งรอบ อีก runtime กลายเป็น orphan และ contract เรื่อง scoped/idempotent cleanup ไม่เป็นจริง
- **Required change**: ใช้ atomic lock/reservation ก่อนสร้าง resource และลบ metadata เฉพาะเมื่อไฟล์ยังระบุ `RESOURCE_PREFIX` ของ process นี้
- **Class sweep**: fixed metadata path ถูกใช้ที่ preflight, failure cleanup และ final publish แต่ไม่มี lock หรือ ownership compare ในทั้ง start และ cleanup helper

### Important 2: cleanup ยอมรับ temp path ใดก็ได้ที่ลงท้ายด้วย prefix แล้ว `rm -rf`

- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-cleanup.sh:106-118`
- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-cleanup.sh:134`

validation ตรวจเพียงว่า `TEMP_ROOT` ลงท้ายด้วย `/$RESOURCE_PREFIX` และไม่ใช่ `/` จึงยอมรับทั้ง absolute path นอก scratch root และ relative path เช่น `valuable/samsonite-task7-browser-manual-...`

metadata เป็น regular file และไม่ใช่ symlink แต่ไม่ได้ทำให้ค่าภายในเชื่อถือได้เมื่อไฟล์ถูกแก้ผิดหรือเสียหาย ก่อนสั่ง destructive delete ต้องพิสูจน์ parent path ที่ helper เป็นเจ้าของแบบ exact/canonical

- **ผลกระทบ**: metadata ที่ผิดสามารถทำให้ cleanup ลบ directory นอก disposable runtime ซึ่งขัด safe cleanup boundary โดยตรง
- **Required change**: derive temp path จาก `RESOURCE_PREFIX` ภายใต้ scratch root ที่กำหนด แล้วเทียบ exact canonical path ก่อนลบ แทนการเชื่อค่า path จาก metadata
- **Class sweep**: container, network, volume และ image ถูกเทียบ exact กับ `RESOURCE_PREFIX`; จุดที่ยังใช้ suffix-only validation คือ `TEMP_ROOT` ก่อน `rm -rf`

### Important 3: terminal hangup ทิ้ง partial resources โดยไม่มี metadata สำหรับกู้คืน

- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:113-115`
- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:276-300`
- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-cleanup.sh:52-56`

start trap เฉพาะ `ERR`, `INT` และ `TERM` แต่ไม่ครอบคลุม `HUP` หรือ EXIT guard ขณะที่ metadata ถูก publish หลัง health ผ่านเท่านั้น

ถ้า terminal ปิดระหว่าง DB, build, migration, seed หรือ hidden prompt shell จะจบโดยไม่เรียก `cleanup_partial()` และ cleanup helper ในกรณีไม่มี metadata ทำได้เพียงรายงานว่ามี prefixed resource เหลือ ไม่สามารถระบุรอบเพื่อลบ

- **ผลกระทบ**: network, volume, container, image และ temp tree อาจค้างบน shared host แม้ startup ไม่สำเร็จ
- **Required change**: ใช้ EXIT-based cleanup guard ที่ disarm หลัง metadata publish หรืออย่างน้อย trap `HUP` เพิ่ม พร้อม early ownership record สำหรับ recovery
- **Class sweep**: command failure ผ่าน `ERR` และ `INT`/`TERM` ถูกครอบคลุม; `HUP`, shell exit และ no-metadata recovery เป็นช่องว่างเดียวกัน ส่วน `SIGKILL` trap ไม่ได้และควรระบุเป็น known limit

### Important 4: helper test เป็น false-green ต่อ plaintext password persistence

- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh:66-71`
- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh:84-94`

password checks ยืนยันเพียงว่ามี hidden read/unset, ไม่มีชื่อ pattern บางชุด และ metadata block ระหว่าง marker ไม่มีคำต้องห้าม แต่ไม่ปฏิเสธ sink ที่เขียน `ONE_TIME_PASSWORD` หรือ `PASSWORD_HASH` ลงไฟล์นอก marker

static mutation proof เพิ่มบรรทัดที่เขียน `ONE_TIME_PASSWORD` ลง `leaked-password.txt` ก่อน unset ในสำเนาชั่วคราว แล้ว test เดิมยังจบด้วย `PASS: 27 assertions`

- **ผลกระทบ**: test contract ข้อ no plaintext/hash persistence ไม่ได้พิสูจน์จริง และ regression ด้าน credential สามารถผ่าน GREEN ได้
- **Required change**: เพิ่ม adversarial assertions สำหรับทุก use/sink ของตัวแปร password/hash และให้ test ล้มเมื่อมี file redirect, log, argv หรือ metadata path ที่ไม่ได้อนุญาต
- **Class sweep**: syntax, no `set -x`, constants/tree gate, prefix และ broad prune มี assertion; ช่องว่างที่ reproduce ได้อยู่ใน password/hash persistence proof

### Minor 1: resource absence check ใช้ substring และ `grep -q` pipeline

- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-cleanup.sh:28-49`
- **ตำแหน่ง**: `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-cleanup.sh:15-25`

`grep -Fq "$prefix"` ตรวจ substring ไม่ใช่ starts-with ownership และเมื่อใช้กับ `set -o pipefail` การหยุดอ่านเร็วของ `grep -q` อาจทำให้ producer ได้ SIGPIPE บน output ใหญ่

- **ผลกระทบ**: final assertion อาจ false-positive กับชื่อที่เพียงมี prefix อยู่กลางชื่อ หรือ false-negative จาก pipeline status ทำให้ cleanup proof ไม่น่าเชื่อถือเต็มที่
- **Required change**: capture listing แล้วตรวจ anchored prefix/exact name โดยไม่พึ่ง early-exit pipeline
- **Class sweep**: pattern เดียวกันอยู่ใน container, network, volume, image และ shared-container checks

## Spec compliance ที่ยืนยันแล้ว

| Contract | Evidence |
|---|---|
| Exact candidate materialization | temporary index dry run ได้ 20 paths และ tree `d572679d4c4bfdbd1d603961754f2a57fd6bcfef` |
| Real Git index ไม่เปลี่ยน | `git diff --cached --exit-code` ผ่าน และ index tree คือ `c6ce38a8953cb1dedf08e35446b3195347139425` |
| Hidden password boundary | start ปฏิเสธ arguments, บังคับ interactive stdin, ใช้ `IFS= read -r -s` และไม่ใช้ `set -x` |
| Plaintext/hash handling ปัจจุบัน | plaintext ผ่าน shell builtin pipe เข้า hash container, ไม่อยู่ argv/env/file และถูก unset ก่อน seed; hash ส่งเข้า DB ผ่าน stdin แล้ว unset |
| Runtime metadata ปัจจุบัน | marker blockมีเฉพาะ nonsecret URL, usernames, fixture, tree และ resource names |
| Docker naming | resource ที่ helperสร้างทุกตัวใช้ `samsonite-task7-browser-manual-` prefix; DB portไม่ publish |
| Runtime versions | PHP baseและ MariaDB digestตรง repo/prior evidence |
| Synthetic schema/seed | schema import, CI4 migrations, usersสอง role, branch/book/catalogues/order fixture และ image associationสอดคล้องกับ app queries |
| Shared project protection | start/cleanupอ้าง shared projectเพื่อ assert running เท่านั้น ไม่พบ deletion targetหรือ broad prune |
| Scratch boundary | helper/reportทั้งสี่ไฟล์ถูก ignore โดย `.superpowers/sdd/.gitignore:1:*` |

## Verification evidence

| Check | ผล |
|---|---|
| `bash -n` ทั้งสาม script | PASS, exit `0`, ไม่มี output |
| helper test | PASS: 27 assertions |
| adversarial false-green mutation | FAIL ของ test design: สำเนาที่เพิ่ม plaintext file write ยังรายงาน PASS: 27 assertions |
| exact candidate temporary-index dry run | `whole_file_paths=20`, candidate treeตรง expected |
| cached diff/index tree | cached diffว่าง, index treeไม่เปลี่ยน |
| Git ignore | helper/reportทั้งสี่ match ignore rule |
| file permissions | scriptsเป็น `0700`; reportเป็น `0644` และมีเฉพาะ nonsecret evidence |
| ShellCheck | SKIP เพราะเครื่องไม่มี `shellcheck` |
| start/cleanup execution | ไม่รันตามข้อห้าม จึงไม่มี runtime proof ของ Docker lifecycle |

## Gate

ห้ามส่งต่อให้ผู้ใช้รัน start helperจนกว่า Important ทั้ง 4 ข้อถูกแก้และ helper test เพิ่ม regression proof ที่ทำให้ adversarial plaintext mutationเป็น RED
