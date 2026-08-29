# Task 7 Browser manual-login continuation

งานนี้สร้าง scratch helper สำหรับปิด authenticated Browser gate ของ Task 7 โดยไม่แก้ production candidate และไม่ commit ไฟล์ helper

## Context

- Repository: `/Users/king_developer/Desktop/Project/samsoniteci4`
- Branch: `feature/wp03g-order-fields`
- Base commit: `6799684db6de09936122d2ae25a5461a878b0eb3`
- Exact candidate tree: `d572679d4c4bfdbd1d603961754f2a57fd6bcfef`
- Candidate: 20 whole files และ route patch `task-7-route.patch`
- Existing shared Docker project: `samsonitetracking-ci4-migration`
- Prior browser report: `browser-task7-20260828-report.md`
- Prior evidence: `browser-task7-20260828/`
- Handoff: `HANDOFF-2026-08-28.md`

## Required files

สร้างใต้ workspace นี้เท่านั้น:

1. `task-7-browser-manual-start.sh`
2. `task-7-browser-manual-cleanup.sh`
3. `task-7-browser-manual-helper-test.sh`
4. `task-7-browser-manual-report.md`

ไฟล์ทั้งหมดเป็น git-ignored scratch work ห้าม stage หรือ commit

## Start helper contract

1. ใช้ Bash แบบ fail-fast และ cleanup เฉพาะ partial resources เมื่อ startup ล้ม
2. materialize exact candidate จาก base commit, 20 whole-file pathsใน approved package และ route patchเดิม
3. ตรวจ generated tree ต้องเท่ากับ `d572679d4c4bfdbd1d603961754f2a57fd6bcfef` ก่อน build
4. สร้าง disposable Docker resourcesที่ชื่อขึ้นต้น `samsonite-task7-browser-manual-` เท่านั้น
5. ห้ามใช้หรือแก้ shared DB, volume, network หรือ containerของ projectอื่น
6. ใช้ image/runtime versionเดียวกับ browser runก่อนหน้าเมื่อพิสูจน์จาก repoหรือ evidenceได้
7. รับ one-time passwordจาก terminalด้วย hidden prompt `read -s`
8. ห้ามรับ passwordผ่าน argument, environment variable หรือ file
9. ห้ามพิมพ์, trace, log, persist หรือเขียน plaintext passwordลง disk
10. สร้าง password hashใน memory/pipeline แล้ว unset plaintextทันที
11. seedเฉพาะ isolated DBด้วย synthetic central user, synthetic branch user และ order fixtureขั้นต่ำที่ browser matrixต้องใช้
12. เขียน metadataที่ไม่เป็น secret เช่น URL, username, fixture ID, container namesและ source treeลง runtime metadata fileได้
13. เปิด app portบน localhostที่ไม่ชน projectอื่น และรอ `/health` ผ่าน
14. เมื่อพร้อม ให้ helperจบ processโดยคง runtimeทำงานต่อสำหรับ browser verifier
15. ห้ามเปิด browserหรือกรอก credentialแทนผู้ใช้

## Cleanup helper contract

1. อ่านเฉพาะ nonsecret metadataของรอบนี้
2. ลบเฉพาะ container, network, volume, image และ temp treeที่ prefixตรงรอบนี้
3. idempotent
4. ยืนยันไม่มี resource prefixของรอบนี้เหลือ
5. ยืนยัน containerของ `samsonitetracking-ci4-migration` ยังทำงาน
6. ลบ runtime metadataและ scratch hash artifactถ้ามี แต่คง report/evidenceที่ไม่เป็น secret

## Test contract

เขียน testก่อน helper แล้วให้ testพิสูจน์อย่างน้อย:

- helper syntaxผ่าน `bash -n`
- start helperไม่มี password argument/env/file input
- password promptใช้ `read -s`
- ไม่มี `set -x`
- resource prefixถูกจำกัดตาม contract
- exact candidate tree constantและ route patchถูกตรวจ
- cleanup helperไม่ใช้ broad Docker cleanup และไม่อ้างชื่อ projectอื่นเป็น deletion target
- runtime metadataไม่เก็บ passwordหรือ hash

รัน testให้เห็น RED ก่อนสร้าง helper แล้วรัน GREEN หลังสร้าง helper

## Verification และ report

- ห้ามรัน start helper เพราะต้องให้ผู้ใช้กรอก passwordเอง
- รัน helper testและ shell syntax checks
- ตรวจ `git diff --cached` ว่างและ real index treeไม่เปลี่ยน
- Reportต้องบันทึกไฟล์ที่สร้าง, RED/GREEN evidence, checksที่รัน, assumptions, exact commandที่ผู้ใช้ต้องรัน และ cleanup command
- ตอบกลับเป็นภาษาไทยแบบสั้น: status, report path, test summary, concerns
- ห้าม dispatch subagentหรือ commit
