# รายงาน Task 7 Browser manual-login helper

สร้าง scratch helper สำหรับ materialize exact candidate, เปิด disposable isolated runtime หลังผู้ใช้ตั้ง one-time passwordเอง และ cleanup เฉพาะ resource ของรอบนี้ โดยไม่แก้ production candidate หรือ Git index

## สถานะ

| รายการ | ผล |
|---|---|
| Start helper | สร้างแล้วและผ่าน static contract test |
| Cleanup helper | สร้างแล้วและผ่าน static contract test |
| Helper test | RED ก่อน helper และ GREEN หลัง helper |
| Start helper execution | ไม่รันตามข้อห้ามใน brief |
| Docker resources | ไม่สร้าง ไม่แก้ และไม่ลบในรอบ implement นี้ |
| Git staging/commit/push | ไม่ทำ |

## ไฟล์ที่สร้าง

| ไฟล์ | หน้าที่ |
|---|---|
| `task-7-browser-manual-start.sh` | สร้าง exact candidate และ disposable runtime พร้อม hidden password prompt |
| `task-7-browser-manual-cleanup.sh` | ลบเฉพาะ resource prefix ของรอบนี้และตรวจ shared project |
| `task-7-browser-manual-helper-test.sh` | ตรวจ shell syntax, password boundary, tree identity, Docker prefix และ cleanup scope |
| `task-7-browser-manual-report.md` | บันทึกหลักฐานและคำสั่งสำหรับผู้ใช้ |

ไฟล์ทั้งสี่ถูก ignore โดย `.superpowers/sdd/.gitignore`

## TDD evidence

### RED

รัน test หลังสร้าง test file แต่ก่อนสร้าง helper:

```bash
/Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh
```

ผล:

```text
FAIL: missing /Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh
FAIL: missing /Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-cleanup.sh
RED: 2 assertion(s), 2 failure(s)
```

### GREEN

รัน test หลังสร้าง helper:

```bash
/Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh
```

ผล:

```text
PASS: 27 assertions
```

## Contract ที่ helper ปิด

| Contract | Implementation |
|---|---|
| Exact source | temporary index เริ่มจาก `6799684db6de09936122d2ae25a5461a878b0eb3`, add 21 whole files และ apply `task-7-route.patch` |
| Tree gate | ต้องได้ `e51837eec685b090f1072a8b2887fa7008f4c587` ก่อน archive และ build |
| Password input | ไม่รับ argument, environment หรือ file; รับ one-time passwordด้วย `read -s` จาก interactive terminalเท่านั้น |
| Password handling | ส่ง plaintextผ่าน pipelineเข้า `password_hash()`, unset plaintextทันที และ pipe hashเข้า isolated DBโดยไม่เขียนลง diskหรือ metadata |
| Docker isolation | container, network, volume และ imageทุกตัวใช้ prefix `samsonite-task7-browser-manual-`; DB portไม่ publish |
| Runtime versions | `php:8.5.7-cli-bookworm` จาก `Dockerfile.ci4`; MariaDB digestตรง prior browser evidence |
| Schema wiring | import `db/local-schema-only.sql`, รัน CI4 migrations และใช้ MariaDB flagsจาก `compose.yaml` |
| Login wiring | seed `ci4_users` ตาม `LoginService` ด้วย synthetic central userและ branch user |
| Browser fixture | seed lookupขั้นต่ำ, order `99001` และ existing PNG associationสำหรับ create/edit matrix |
| Runtime metadata | เก็บเฉพาะ URL, usernames, fixture ID, treeและ resource names; ไม่มี password, hash, secretหรือ token |
| Health gate | bind appที่ `127.0.0.1` บน free port และรอ `/health` ผ่าน |
| Failure cleanup | ERR, INT และ TERM cleanup เฉพาะ partial resource prefix ของรอบนี้ |
| Manual continuation | helperจบหลัง healthผ่านโดยคง appและ DBทำงาน; ไม่เปิด browserและไม่กรอก credential |
| Final cleanup | idempotent, ห้าม broad Docker prune, ลบ exact prefix และตรวจ shared containersทั้งสามยัง running |

DB runtime credentialเป็นค่าที่ helperสุ่มขึ้นเองและส่งตรงเข้า isolated Docker containers โดยไม่รับจากผู้ใช้และไม่เก็บใน metadata ส่วน one-time login passwordไม่ผ่าน environment variableทุกกรณี

## Verification evidence

| Check | ผล |
|---|---|
| Helper contract test | `PASS: 61 assertions` |
| `bash -n` ทั้งสาม script | ผ่าน, exit `0` และไม่มี output |
| Exact candidate dry run | `whole_file_paths=21` และ treeตรง expected |
| Git ignore | ทั้งสี่ไฟล์ match `.superpowers/sdd/.gitignore:1:*` |
| Real index tree | `c6ce38a8953cb1dedf08e35446b3195347139425` |
| Cached diff | ว่าง, `git diff --cached --exit-code` ผ่าน |
| ShellCheck | ไม่ได้รัน เพราะเครื่องไม่มี `shellcheck`; ไม่ใช่ gateใน brief |

Exact candidate dry run:

```text
whole_file_paths=21
candidate_tree=e51837eec685b090f1072a8b2887fa7008f4c587
```

## คำสั่งสำหรับผู้ใช้

### Start

รันจาก interactive terminal แล้วกรอก one-time passwordที่ hidden prompt ค่าเดียวกันนี้ใช้ login synthetic usersทั้งสอง:

```bash
/Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh
```

เมื่อขึ้น `READY` ให้อ่าน URL, usernames และ edit fixtureจาก outputหรือไฟล์ nonsecret metadata:

```text
/Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-runtime.env
```

### Cleanup

หลัง Browser matrixเสร็จ ให้รัน:

```bash
/Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-cleanup.sh
```

Cleanupจะลบเฉพาะ resourceของรอบที่ metadataระบุ จากนั้นตรวจว่าไม่มี prefixนั้นเหลือและ shared project `samsonitetracking-ci4-migration` ยังทำงานครบ

## Assumptions และ concerns

- Shared containersต้องชื่อ `samsonitetracking-ci4-migration-ci4-1`, `samsonitetracking-ci4-migration-web-1` และ `samsonitetracking-ci4-migration-db-1` ตาม prior evidence; startจะ failก่อนสร้าง resourceถ้าตัวใดไม่ running
- Hostต้องมี Bash, Git, PHP, Docker และ curl; image buildอาจต้องใช้ Docker cacheหรือ networkตามสภาพเครื่อง
- Free localhost portเลือกก่อน `docker run`; มี race windowสั้นมาก ถ้า portถูกยึดพร้อมกัน startจะ failและ cleanup partial resourcesอัตโนมัติ
- ยังไม่มี runtime proofจาก start helper เพราะ briefห้ามรัน; Browser verifierต้องยืนยัน login, create/edit, branch caller, security matrix, desktop/mobile และ cleanupจริงต่อไป

## Fix round 1: safe exact-tree streaming

หลักฐานรอบนี้แทนที่ข้อความก่อนหน้าที่กล่าวถึง host temp tree และจำนวน assertion เดิม โดยไม่รัน start/cleanup helper และไม่แตะ Docker resource

### การแก้ไข

| Finding | ผลแก้ |
|---|---|
| Concurrent start ownership | reserve metadata ด้วย `noclobber` ก่อนสร้าง resource, ปฏิเสธ cleanup ขณะ ownerยังทำงาน และตรวจ `resource_prefix` กับ `owner_pid` ก่อน publish/remove |
| Host temp deletion | ตัด `TEMP_ROOT`, `SOURCE_TREE`, `tar -xf` และ `rm -rf` ออกทั้งหมด |
| Exact candidate workflow | stream `git archive` เข้า `docker build` และ stream schemaจาก exact Git treeเข้า isolated DB |
| Interrupted startup | ใช้ EXIT cleanup guard, trap `HUP`/`INT`/`TERM`, disarm หลัง metadata publish และเก็บ early nonsecret recovery record |
| Credential regression proof | allowlist การใช้ตัวแปร credential และ mutation checks สำหรับ file, log, argv, env และ metadata sink |

Temporary Git index เป็น regular fileที่ชื่อเจาะจงต่อ run และลบด้วย `rm -f` เฉพาะ pathนั้น ไม่มี dynamic directory deletion

### RED ก่อนแก้ helper

```text
FAIL: exact candidate archive is not streamed into docker build
FAIL: schema is not streamed from the exact candidate tree
FAIL: start helper materializes or recursively deletes a host tree
FAIL: start helper has no atomic metadata reservation
FAIL: metadata reservation is not atomic
FAIL: start helper does not compare metadata ownership
FAIL: start helper has no EXIT cleanup guard
FAIL: start helper does not handle terminal hangup
FAIL: EXIT cleanup guard is not disarmed after publish
FAIL: early recovery metadata does not identify startup state
FAIL: early recovery metadata does not record its nonsecret owner
FAIL: cleanup accepts or recursively deletes a host tree
FAIL: 45 assertion(s), 12 failure(s)
```

Follow-up testสำหรับ cleanupที่อาจปล่อย reservation ขณะ ownerยังทำงานเป็น RED ก่อนเพิ่ม guard:

```text
FAIL: cleanup can release a live startup reservation
FAIL: 46 assertion(s), 1 failure(s)
```

### Mutation evidence

สำเนา helperที่เพิ่ม sinkต้องทำให้ full helper testเป็น RED จริงทุกแบบ

```text
plaintext-file: RED
hash-file: RED
log: RED
argv: RED
env: RED
metadata: RED
```

Mutation ใช้เฉพาะ placeholder variable name ไม่มี plaintext passwordหรือ hashจริง

### GREEN และ verification

| Check | ผล |
|---|---|
| Helper contract test | `PASS: 46 assertions` |
| `bash -n` ทั้งสาม script | PASS, exit `0`, ไม่มี output |
| Exact candidate dry run | `whole_file_paths=21`, treeตรง `e51837eec685b090f1072a8b2887fa7008f4c587` |
| Cached diff | ว่าง, `git diff --cached --exit-code` ผ่าน |
| Real index tree | `c6ce38a8953cb1dedf08e35446b3195347139425` |
| Start/cleanup execution | ไม่รันตามข้อห้าม |
| Docker resources | ไม่สร้าง ไม่แก้ และไม่ลบ |

### Known limit

`SIGKILL` trapไม่ได้ หาก processถูก killด้วย `SIGKILL` early metadataจะคงอยู่เพื่อให้ cleanup helperระบุ exact resourceของรอบนั้นได้ แต่ lifecycleจริงยังต้องพิสูจน์ตอนผู้ใช้รัน start/cleanup

## Fix round 2: structural credential validation

### สาเหตุและการแก้ไข

`secret_uses_are_allowed()` เคยอนุญาตด้วย substring จึงยอมรับ file sink ที่มี `"$CENTRAL_USERNAME" "$PASSWORD_HASH"` ประกอบอยู่ รอบนี้เปลี่ยนเป็นการ trim whitespace แล้วเปรียบเทียบทั้ง statement กับรายการที่ตั้งใจให้มี credential เพียงห้ารูปแบบ: password length check, in-memory hash pipeline, hash nonempty check และ SQL seed สอง statement

ไม่มีการ hardcode line number และบรรทัดที่มี credential นอก statement ที่กำหนดจะถูกปฏิเสธ รวมถึง file, log, argv, environment และ metadata sink

### RED ก่อนแก้ validator

เพิ่ม regression mutation โดยไม่มี credential จริง:

```bash
printf '%s %s\n' "$CENTRAL_USERNAME" "$PASSWORD_HASH" > "$WORKSPACE/hash-leak.txt"
```

ก่อนแก้ validator รัน helper test ได้ผล:

```text
FAIL: mutation ผ่านโดยไม่ถูกปฏิเสธ: credential-shaped hash file sink
FAIL: 47 assertion(s), 1 failure(s)
```

### GREEN และ mutation proof

| Check | ผล |
|---|---|
| Helper contract test | `PASS: 47 assertions` |
| plaintext-file mutation | `RED` |
| hash-file mutation | `RED` |
| log mutation | `RED` |
| argv mutation | `RED` |
| env mutation | `RED` |
| metadata mutation | `RED` |
| credential-shaped hash-file mutation | `RED` |
| `bash -n` ทั้งสาม script | PASS, exit `0`, ไม่มี output |
| `git diff --cached --exit-code` | PASS, cached diff ว่าง |
| Real index tree | `c6ce38a8953cb1dedf08e35446b3195347139425` |
| Start/cleanup execution | ไม่รันตามข้อห้าม |
| Docker resources | ไม่สร้าง ไม่แก้ และไม่ลบ |

## Non-TTY prompt fix

### สาเหตุและขอบเขต

Claude Code `!` shell runner ไม่มี controlling TTY จึงเข้า guard เดิม `[[ ! -t 0 ]]` และ fail ก่อนถึง hidden password prompt ทั้งที่ macOS มี `/usr/bin/osascript` ใช้งานได้แบบ non-dialog. รอบนี้ตัด guard ดังกล่าวออก โดยคง `read -s` สำหรับ TTY และใช้ `osascript display dialog ... with hidden answer` เฉพาะเมื่อไม่มี TTY

คำตอบจาก dialog ถูก capture ใน `ONE_TIME_PASSWORD` ใน memory เท่านั้น แล้วใช้ flow hash เดิม ไม่มี password ใน stdout, log, file, argv, environment หรือ AppleScript source. หากไม่มี TTY และไม่มี `/usr/bin/osascript` helper จะ fail ชัดเจนก่อนสร้าง Docker resource; cancellation หรือ error ของ dialog เกิดหลัง EXIT trap ถูก arm แล้ว จึงเข้าการ cleanup เดิม

### RED

เพิ่ม regression static สำหรับ no-TTY fallback แล้วรันกับ helper เดิม:

```bash
/Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh
```

ผล:

```text
FAIL: start helper rejects a non-TTY shell before its password fallback
FAIL: start helper has no TTY password path
FAIL: start helper has no clear non-TTY fallback failure
FAIL: non-TTY password fallback is not hidden
FAIL: non-TTY password result is not captured in memory
FAIL: start helper has no shared password prompt flow
FAIL: 53 assertion(s), 6 failure(s)
```

### GREEN และ verification

```bash
bash -n /Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh \
  /Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh \
  /Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-cleanup.sh
/Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh
git -C /Users/king_developer/Desktop/Project/samsoniteci4 diff --cached --exit-code
git -C /Users/king_developer/Desktop/Project/samsoniteci4 write-tree
```

ผล:

```text
PASS: 53 assertions
c6ce38a8953cb1dedf08e35446b3195347139425
```

Regression suite รวม mutation ที่ต้องถูกปฏิเสธสำหรับ plaintext/hash file, log, argv, environment, metadata และ credential-shaped hash file; validator อนุญาตเฉพาะ assignment ของ fallback ที่กำหนดและ password flow เดิม

ไม่รัน start/cleanup helper และไม่เปิด GUI dialog ระหว่าง implement

### คำสั่งที่ผู้ใช้ต้อง retry

```bash
! /Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh
```

เมื่อไม่มี TTY จะขึ้น macOS hidden dialog ให้กรอก one-time password แล้ว helper ทำงานต่อ; กด Cancel จะ exit และ cleanup ตาม flow เดิม

## `bg-form.png` candidate update

Browser findingเพิ่ม exact CI3 asset `public/assets/images/bg-form.png` เข้า whole-file candidate โดยไม่เปลี่ยน runtime flowของ helper.

| Check | ผล |
|---|---|
| Whole-file paths | 21 paths รวม `public/assets/images/bg-form.png` |
| Route patch | 1 hunkที่ `app/Config/Routes.php` |
| Candidate paths | 22 paths |
| Candidate tree | `e51837eec685b090f1072a8b2887fa7008f4c587` |
| Helper contract test | `PASS: 61 assertions` |
| `bash -n` | PASS ทั้ง start, helper test และ cleanup |

Helper testตรึงทั้ง asset path, whole-file count 21 และ expected treeใหม่. รอบนี้ไม่รัน start/cleanup helperและไม่แตะ Docker runtime.
