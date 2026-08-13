# ประวัติการปรับแผน CI3 เป็น CI4: Evidence-First RCA

> Work ID: DOC-RCA-20260809-001  
> เอกสารหลัก: `2026-08-09_ci3-to-ci4-upgrade-plan_v3.md`  
> เวลาตรึงผล after: 2026-08-09T22:32:53+07:00  
> สถานะ: PARTIAL_HISTORY / HISTORY_GAP

ไฟล์นี้เก็บหลักฐานก่อนและหลังการเพิ่มข้อบังคับ root cause, impact analysis และ work history ในแผนอัปเกรด. บันทึกเฉพาะสิ่งที่ตรวจพบ; ค่าที่ไม่เคย capture ระบุ `UNKNOWN`.

## สรุปสั้น

| หัวข้อ | ผล |
|---|---|
| Requirement basis | ผู้ใช้กำหนดให้ทุกงานหา root cause จากหลักฐาน, ห้ามเดา, สรุป before/after impact และเก็บประวัติ |
| Change class | Process/document rule |
| Source/runtime impact | ไม่มีการแก้ PHP, JavaScript, database schema, config หรือ runtime |
| เอกสารหลักก่อน | v3.3, 147 AC, PC-01 ถึง PC-10 ตาม task transcript |
| เอกสารหลักหลัง | v3.4, 163 AC, PC-01 ถึง PC-12, AC-RCA-001 ถึง AC-RCA-016 |
| Before SHA-256 | `UNKNOWN`; ไม่ได้ capture ก่อน edit และไฟล์ไม่อยู่ใน Git index |
| After SHA-256 | `00ca4816298282337c3d272de4cd142d674e025f4203a237451adb1d08eb9e96` |
| Verification | Mermaid 13/13 blocks ผ่าน; markdownlint 0 issue; ID/count checks ผ่าน |
| Closure | เอกสารผ่าน; process จริงยังเป็น `RCA Protocol Defined, Evidence Not Yet Produced` |

## หลักฐาน requirement และ problem basis

### Requirement ที่อนุมัติ

ผู้ใช้ระบุโดยตรงว่า:

- ทุกการทำงานต้องหา root cause ที่แท้จริง
- ห้ามเดาและต้องมีหลักฐาน
- ต้องสรุปผลกระทบก่อนและหลังการเปลี่ยนแปลง
- ต้องเก็บประวัติการทำงานทั้งก่อนและหลังเสมอ

งานนี้เป็น `Process/document rule`; หลักฐานเริ่มงานจึงเป็น approved requirement และ observed document gap ตาม §19.10 ของแผน ไม่อ้างว่าเป็น application defect.

### สิ่งที่พบก่อนแก้

| Evidence ID | Status | Observation | Source/ข้อจำกัด |
|---|---|---|---|
| EV-BEFORE-001 | `OBSERVED_FACT` | หัวเอกสารเป็น v3.3 | ผลอ่านไฟล์ใน task transcript ก่อน edit |
| EV-BEFORE-002 | `OBSERVED_FACT` | มี 147 AC และ PC-01 ถึง PC-10 | ผลนับใน task transcript ก่อน edit |
| EV-BEFORE-003 | `OBSERVED_FACT` | มี RCA/rework แบบทั่วไป แต่ไม่มี status vocabulary, evidence hierarchy, causal proof standard, before/after schema และ append-only journal ที่บังคับครบ | ผลค้นและอ่านโครงเอกสารใน task transcript ก่อน edit |
| EV-BEFORE-004 | `OBSERVED_FACT` | ไม่ได้ capture SHA-256 ก่อน mutation | ไม่มี before hash ใน task record |
| EV-BEFORE-005 | `OBSERVED_FACT` | ไฟล์แผน v3 ไม่อยู่ใน Git index | `git status --short` แสดง `??`; `git ls-files --error-unmatch` exit 1 |

### History gap และ root-cause status

ข้อเท็จจริงที่พิสูจน์ได้: baseline hash ไม่ถูก capture ก่อน edit และ Git กู้ pre-edit blob ไม่ได้เพราะไฟล์เป็น untracked.

สาเหตุเชิงองค์กรหรือเหตุผลที่ไม่มี control นี้ตั้งแต่รอบก่อนยังไม่มี causal evidence พอ จึงระบุ `UNKNOWN_BLOCKED`; ไม่แต่ง root cause. สิ่งที่ยืนยันได้มีเพียง control gap: workflow เดิมไม่มี hard gate บังคับ capture-before-mutation.

การแก้ช่องว่างย้อนหลังทำไม่ได้อย่างซื่อสัตย์. Corrective control ตั้งแต่รอบนี้คือ PC-12, AC-RCA-015 และ §19.9; งานถัดไปต้องสร้าง journal และ baseline hash ก่อน mutation แรก.

## Before/after change record

| Dimension | Before | After | Actual impact |
|---|---|---|---|
| Document version | v3.3 | v3.4 | เปลี่ยน governance contract |
| Total AC | 147 | 163 | เพิ่ม 16 RCA AC |
| Process controls | PC-01 ถึง PC-10 | PC-01 ถึง PC-12 | เพิ่ม evidence-before-conclusion และ mandatory history |
| RCA status language | ไม่มีชุดบังคับเฉพาะ | 7 statuses ใน §19.2 | แยก fact, hypothesis, proven cause, unknown, containment และ verified fix |
| Proof standard | RCA ทั่วไป | reproduction, mechanism, intervention, negative control, alternatives, regression และ independent check | ห้ามปิดงานด้วย correlation หรือ test บางส่วน |
| Before-impact analysis | ไม่มี matrix บังคับครบ domain | 12 impact domains ใน §19.7 | ต้องอนุมัติ expected diff และ no-change contract ก่อนแก้ |
| After verification | ไม่มี result classes บังคับ | `EXPECTED_MATCH`, `UNEXPECTED_DIFF`, `INCONCLUSIVE`, `REGRESSION` | unexpected result reset gate |
| Work history | ไม่มี append-only schema | §19.9 journal fields, storage และ integrity rules | บังคับ trace intake ถึง closure |
| Emergency action | ไม่แยก containment ชัด | §19.10 กำหนด reversible containment และ RCA SLA | ลดการประกาศ fixed ก่อน proof |
| Gate integration | generic evidence gates | Gate 0–5 ผูก RCA/history และ reset rules | closure ยากขึ้นเมื่อ evidence ขาด |
| Blocker count | 16 | 16 | ไม่เปลี่ยน blocker inventory |
| Application behavior | ไม่เปลี่ยน | ไม่เปลี่ยน | ไม่มี source/runtime mutation |

## ผลกระทบที่คาดก่อนแก้

| Impact domain | Expected change | Expected no-change | Risk/control |
|---|---|---|---|
| Documentation | เพิ่ม section, AC, metrics, deliverables และ gate packet fields | รายงานระบบเดิมและ migration strategy หลักคงเดิม | ตรวจ count, cross-reference และ Markdown |
| Delivery process | ทุก work item ต้องมี evidence, causal status และ before/after record | owner/gate model เดิมคงใช้ | lead time อาจเพิ่ม; ห้ามลดหลักฐานเพื่อเร่งงาน |
| Quality | ลด guessing, symptom fix และ untracked regression | parity target 100% ไม่ลด | AC-RCA 16/16 เป็น hard gate |
| Security/privacy | เพิ่ม safe evidence, redaction และ secret incident rules | ห้ามเก็บ secret/PII ใน evidence | rotate/revoke หากพบ secret exposure |
| Runtime/data | ไม่มีการแก้ code, schema, config หรือ production data | behavior, routes และ data ไม่เปลี่ยน | ยืนยันจาก file scope และ Git status |
| Tooling | ใช้ checker เดิม; markdownlint แบบ ephemeral pinned version | ไม่เพิ่ม dependency ใน repository | บันทึก tool version และ failed invocation |

ค่า lead time และ risk reduction เป็น `EXPECTED`; ยังไม่มี production execution data จึงไม่รายงานเป็น actual benefit.

## การเปลี่ยนแปลงที่ทำจริง

- เปลี่ยน version marker เป็น v3.4
- เพิ่ม PC-11 และ PC-12
- เพิ่ม records, deliverables, metrics และ gate packet สำหรับ RCA/impact/history
- เพิ่ม §19 Evidence-First Root Cause และ Change History Protocol
- เพิ่ม Mermaid flow จาก evidence intake ถึง verified closure
- เพิ่ม AC-RCA-001 ถึง AC-RCA-016
- อัปเดตยอด AC จาก 147 เป็น 163 ทุกจุดที่เกี่ยวข้อง
- เพิ่มลิงก์มายัง work-history file นี้
- ปรับ spacing ไทย–English ในส่วนที่แก้

ไม่มีการแก้ application source, database schema, credential, deployment config หรือ production state.

## ผล after ที่ตรวจได้

| Check | Result | Evidence |
|---|---|---|
| Main plan identity | PASS | 1,775 lines; SHA-256 `00ca4816298282337c3d272de4cd142d674e025f4203a237451adb1d08eb9e96` |
| AC definitions | PASS | 163 definitions; AC-RCA 16 |
| Process controls | PASS | 12 definitions |
| Blockers | PASS | 16 definitions; count ไม่เปลี่ยน |
| Stale markers | PASS | ไม่พบ `147 AC` หรือ `v3.3` ในไฟล์ after |
| Heading structure | PASS | ไม่พบ heading ระดับ 4 ขึ้นไป |
| Markdown symbols | PASS | ไม่พบ `Extended_Pictographic`; ไฟล์ `.md` ไม่มี emoji |
| Thai–English spacing | PASS | diagnostic ไม่พบคำติดตาม pattern ที่กำหนด |
| Mermaid self-test | PASS | valid labeled flowchart ผ่าน; invalid flowchart ถูก reject |
| Migration-plan diagrams | PASS | 6/6 blocks |
| Legacy-report diagrams | PASS | 7/7 blocks |
| Markdown lint | PASS | markdownlint-cli2 0.23.2, markdownlint 0.41.1, MD013/MD060 disabled; 0 issues |

ผลนี้พิสูจน์ความสอดคล้องของเอกสาร ไม่พิสูจน์ว่า migration runtime สำเร็จ. สถานะ L3 ต้องได้ execution evidence จริงครบ 163 AC และ Gate 5.

## Command history และผลลัพธ์

| Event | Action | Result | Interpretation |
|---|---|---|---|
| J-001 | อ่าน/ค้นแผน v3.3 และนับ AC/PC | พบ gap ตาม EV-BEFORE-001 ถึง 003 | approved requirement มีผลต่อเอกสารจริง |
| J-002 | แก้แผนด้วย `apply_patch` | เพิ่ม v3.4 และ §19 | source mutation จำกัดหนึ่งไฟล์แผน |
| J-003 | `perl` spacing diagnostic | พบคำไทย–English ติดกันในส่วนใหม่ | แก้ spacing; ไม่เปลี่ยน semantics |
| J-004 | `node scripts/check-mermaid.mjs --self-test` | exit 0 | checker แยก valid/invalid fixture ได้ |
| J-005 | รัน Mermaid checker กับสองรายงาน | exit 0; 13/13 blocks | Mermaid syntax ผ่าน |
| J-006 | `npx --no-install markdownlint-cli2 ...` | exit 1; local package ไม่มี | tooling precondition fail; ไม่ใช่ content fail |
| J-007 | `npx --yes markdownlint-cli2@0.23.2 --disable MD013 MD060 ...` | exit 1; tool มอง option เป็น file patterns และรายงาน 1,871 issues | invocation ผิด; root cause ยืนยันจาก `Finding: --disable ...` |
| J-008 | รัน pinned markdownlint พร้อม config ที่ปิด MD013/MD060 | exit 0; 0 issues | corrected invocation ผ่าน |
| J-009 | นับ AC/RCA/PC/BLK และค้น stale markers | AC 163, RCA 16, PC 12, BLK 16; stale 0 | structural acceptance ผ่าน |
| J-010 | ตรึง SHA-256 และ line count | hash/1,775 lines ตามตาราง after | after identity พร้อมตรวจซ้ำ |
| J-011 | ตรวจ `Extended_Pictographic` | พบ bidirectional-arrow symbol หนึ่งจุดและเปลี่ยนเป็น `source-to-manifest-to-target` | ทำตามกฎห้าม emoji โดย semantics เดิม |

Ephemeral lint config มีค่า `MD013=false` และ `MD060=false`; ลบหลังใช้. ไม่มี dependency หรือ config file ถูกเพิ่มใน repository.

## Residual risks และ closure rule

| Risk/gap | Status | Required action |
|---|---|---|
| Pre-edit SHA-256 ไม่มี | `HISTORY_GAP` | ยอมรับข้อจำกัดรอบนี้; ห้ามสร้างค่าปลอม |
| Journal เริ่มหลัง mutation แรก | `PARTIAL_HISTORY` | งานถัดไปสร้าง J-001 และ baseline hash ก่อนแก้ |
| ไฟล์ยัง untracked | `OBSERVED_FACT` | review ก่อน commit; Git commit/PR จะให้ immutable source history |
| Runtime RCA evidence ยังไม่มี | `Evidence Not Yet Produced` | ดำเนิน Gate 0–5 และ AC-RCA-001 ถึง 016 กับทุก work item |
| Business/data/operations evidence ยังขาด | `BLOCKED` ตาม BLK-001–016 | owner ส่งหลักฐานและ approver ลงนาม |

Document change ปิดได้ในระดับ L0. Migration success ห้ามประกาศจน AC 163/163 ผ่าน, AC-RCA 16/16 ผ่าน, unexpected diff/history gap ใน release scope เป็น 0 และ Gate 5 ลงนาม.

## Work item DOC-STACK-20260810-002 — Intake และ before state

> Received: 2026-08-10T23:16:09+07:00  
> State transition: `NEW` → `IN_PROGRESS`  
> Change class: normative migration-plan scope change

ผู้ใช้ยืนยัน target stack เป็นข้อกำหนดจริง: PHP 8.5.x, CodeIgniter 4.7.4+, MariaDB 11.4.x LTS, MySQLi, mysqlnd, utf8mb4 และ InnoDB.

### Frozen before identity

| Artifact | Before identity |
|---|---|
| Migration plan | v3.4, 1,775 lines, 163 AC, 16 blockers |
| Plan SHA-256 | `00ca4816298282337c3d272de4cd142d674e025f4203a237451adb1d08eb9e96` |
| Journal SHA-256 ก่อน append | `d8666c9a9d3f3a4f9e63aeffc88c3668be7daa05eb9cea9f8a211037472b0523` |
| Git state | plan และ journal เป็น untracked files |

### Evidence-backed requirement gap

| Evidence ID | Status | Before observation |
|---|---|---|
| EV-STACK-001 | `OBSERVED_FACT` | แผนล็อก PHP 8.5.x และ CI4 ขั้นต่ำ 4.7.4 แล้ว |
| EV-STACK-002 | `OBSERVED_FACT` | Target Stack ระบุเพียง MySQL schema เดิม ไม่ล็อก MariaDB 11.4.x LTS |
| EV-STACK-003 | `OBSERVED_FACT` | ค้น target plan ไม่พบ MySQLi หรือ mysqlnd requirement |
| EV-STACK-004 | `OBSERVED_FACT` | ADR-009 เลื่อน charset upgrade ไปหลัง parity |
| EV-STACK-005 | `OBSERVED_FACT` | Database engine migration อยู่ใน Out of scope และ InnoDB ไม่ใช่ target contract |

Root cause ของ document mismatch คือ scope contract เดิมแยก database engine/charset modernization ออกจาก framework parity และ runtime manifest ไม่ระบุ PHP database client stack. หลักฐานคือ Out of scope, Non-goals, Target Stack และ ADR-009 ใน before hash เดียวกัน; ไม่ใช่การคาดเดา runtime.

### Approved expected impact ของ DOC-DOCKER-20260813-005

| Domain | Expected change | Expected no-change |
|---|---|---|
| Scope/architecture | เพิ่ม MariaDB 11.4.x LTS, MySQLi/mysqlnd, utf8mb4 และ InnoDB เป็น mandatory target | Functional Parity 100% และ vertical-slice strategy คงเดิม |
| Delivery sequence | แยก database foundation release ก่อน CI4 route migration | ห้ามรวม destructive DB conversion กับ CI4 application cutover |
| Controls | เพิ่ม work packages, risks, blockers, deliverables, ADR, primary sources และ AC-STACK 7 ข้อ | Gate 0–5 และ RCA protocol คงเดิม |
| Totals | AC จาก 163 เป็น 170; blocker count คง 16 โดยขยาย BLK-001/008/014 | AC เดิมไม่ถูกลบหรือผ่อนเกณฑ์ |
| Runtime/source/data | ไม่มีการแก้ PHP source, config, schema หรือ database ใน work item นี้ | production behavior และ data ไม่เปลี่ยน |

Exact utf8mb4 collation ยังไม่มีผู้ใช้อนุมัติ จึงต้องอยู่ใน BLK-001 และห้ามเลือกแทน. MariaDB 11.4 เปลี่ยน target scope จริง แต่ estimate เพิ่มยังเป็น `UNKNOWN_BLOCKED` จนมี current DB version, DDL, table size, collation และ maintenance-window evidence.

## Work item DOC-STACK-20260810-002 — After state และ closure

> Verified: 2026-08-10T23:25:54+07:00  
> State transition: `IN_PROGRESS` → `DOCUMENT_CHANGE_VERIFIED`  
> Runtime status: `NOT_EXECUTED`

### Actual before/after result

| Dimension | Before | After | Result |
|---|---|---|---|
| Plan version | v3.4 | v3.5 | expected match |
| Plan identity | 1,775 lines; SHA-256 `00ca4816298282337c3d272de4cd142d674e025f4203a237451adb1d08eb9e96` | 1,847 lines; SHA-256 `e514e45f508f73a0c13d09e3ad3ace47c6b2a916aaa18654f0b3491323febb92` | sealed after identity |
| Total AC | 163 | 170 | เพิ่ม AC-STACK 7 ข้อ |
| Blockers | 16 | 16 | ขยาย BLK-001/008/010/014; ไม่เพิ่ม duplicate blocker |
| Target database | generic MySQL/current schema | MariaDB 11.4.x LTS + utf8mb4 + InnoDB | mandatory contract |
| PHP DB client | ไม่ล็อก | CI4 MySQLi + mysqlnd | mandatory build/runtime assertion |
| Delivery sequence | CI4 foundation หลัง baseline | database foundation release + Gate 1D ก่อน CI4 route migration | แยก causal impact/rollback |
| Estimate | database engine migration excluded | mandatory `DB-TBD`/`UNKNOWN_BLOCKED` จนมี evidence | ไม่สร้าง ROM ปลอม |
| Runtime/source/schema/data | ไม่เปลี่ยน | ไม่เปลี่ยน | document-only change |

### Actual content impact

- เพิ่ม target stack ใน scope, goals, summary, architecture diagram และ Target Stack table.
- เพิ่ม WP-00J ถึง WP-00M สำหรับ inventory, rehearsal, conversion และ database foundation release.
- เพิ่ม risk R-25 ถึง R-27 และยกระดับ charset risk R-15.
- เพิ่ม Gate 1D, quorum, reset rule, stop rule, evidence package และ separate rollback contract.
- เพิ่ม AC-STACK-001 ถึง AC-STACK-007 และอัปเดตยอด AC ทุกจุดเป็น 170.
- เพิ่ม ADR-015 และเปลี่ยน ADR-003/009 ให้ตรง mandatory database target.
- เพิ่ม official CI4/PHP/MariaDB sources; ระบุว่า MariaDB 11.4 ต้องตั้ง utf8mb4 explicit และห้ามถือ in-place downgrade เป็น rollback.

ไม่มี PHP source, JavaScript, credential, database config, schema, data, container หรือ production state ถูกแก้.

### Verification evidence

| Check | Result |
|---|---|
| AC identity | 170 definitions, 170 unique, duplicate 0 |
| Stack AC | AC-STACK 7/7 definitions |
| Existing controls | PC 12, blockers 16; count ไม่เปลี่ยน |
| Mermaid | self-test ผ่าน; migration plan 6/6 blocks ผ่าน parser |
| Markdown | markdownlint-cli2 0.23.2, MD013/MD060 disabled, 0 issues |
| Structure | H4 0, emoji 0, Thai–English spacing issue 0 |
| Stale/contradiction patterns | `163`, `v3.4`, generic old-MySQL target และ deferred-charset wording = 0 ใน after plan |
| Primary links | official CodeIgniter/PHP/MariaDB pages; checked source content and target URLs |

### Append-only event log

| Event | Action | Evidence/result |
|---|---|---|
| J2-001 | รับ requirement และ freeze before identity ก่อนแก้ plan | before plan hash/line/AC/blocker ถูกบันทึกใน section intake |
| J2-002 | ตรวจ plan gap ด้วย exact search | PHP/CI4 ตรง; MariaDB/MySQLi/mysqlnd/InnoDB ขาด; utf8mb4 ขัด deferred ADR |
| J2-003 | ตรวจ official primary sources | CI4 MySQLi/config, PHP mysqlnd, MariaDB 11.4 LTS/upgrade/charset/InnoDB รองรับ requirement |
| J2-004 | แก้ normative plan ด้วย `apply_patch` | scope/roadmap/control/AC/source เปลี่ยนตาม approved expected impact |
| J2-005 | รัน Mermaid, Markdown, ID, stale, spacing และ symbol gates | ทุก gate exit 0; final identity ตามตาราง above |
| J2-006 | ตรวจ source URL ด้วย HTTP probe | URLs ตอบ HTTP 200; request หนึ่งรายการแตะ timeout หลังรับ response body ขนาดใหญ่ จึงใช้ web fetch result เป็น content evidence |
| J2-007 | Seal document change | state = `DOCUMENT_CHANGE_VERIFIED`; runtime = `NOT_EXECUTED` |

ก่อน user clarification มี read-only live-DB probe หนึ่งครั้งที่จบด้วย PHP parse error เพราะ shell single-quote ครอบ SQL literal ซ้อนกัน. Scope ถูกแก้เป็น target-plan verification จึงหยุด probe ไม่ retry; ไม่มี database query สำเร็จและไม่มี state เปลี่ยน. ผล runtime probe ไม่ถูกใช้เป็นหลักฐาน target plan.

### Residual blockers

| Item | Status | Closure evidence |
|---|---|---|
| Exact MariaDB 11.4.x patch/image digest | `BLOCKED` | BLK-014 stack manifest + advisory review |
| Exact utf8mb4 collation | `BLOCKED` | BLK-001 Thai/search/sort/export comparison + Business/DBA/QA sign-off |
| Current-to-11.4 upgrade path | `BLOCKED` | current DB version/config/engine inventory + version-specific upgrade notes |
| Database effort/calendar | `UNKNOWN_BLOCKED` | production-size inventory, conversion rehearsal, downtime/rollback measurements |
| Runtime success | `Evidence Not Yet Produced` | Gate 0–5 รวม Gate 1D และ AC 170/170 |

Correction event: ข้อความปิด work item ก่อนหน้าที่อ้าง 163 AC เป็น snapshot v3.4 ของ `DOC-RCA-20260809-001`. Work item นี้ supersede release-plan target เป็น 170 AC โดยไม่แก้ event เก่า.

## Work item DOC-EVIDENCE-20260810-003 — Intake และ before state

> Received: 2026-08-10T23:32:59+07:00  
> State transition: `NEW` → `IN_PROGRESS`  
> Change class: process/document verification control

ผู้ใช้กำหนดให้แผนพิสูจน์ความสำเร็จได้จริงทุกจุดด้วยหลักฐาน มีประวัติก่อนและหลัง มีผลกระทบจริง และตรวจสาเหตุอื่นที่อยู่นอกสิ่งที่วางไว้เดิมโดยห้ามเดา.

### Frozen before identity ของ DOC-EVIDENCE-20260810-003

| Artifact | Before identity |
|---|---|
| Migration plan | v3.5, 1,847 lines, 170 AC, 16 blockers, PC-01 ถึง PC-12 |
| Plan SHA-256 | `e514e45f508f73a0c13d09e3ad3ace47c6b2a916aaa18654f0b3491323febb92` |
| Journal SHA-256 ก่อน append | `3f2471ec8dc931925890fbaa86bc475ad52e241290f2e631ab0ccd5689224d64` |
| Git state | plan และ journal เป็น untracked files; ไม่มี source/runtime baseline ถูกเปลี่ยนใน work item นี้ |

### Evidence-backed requirement gap ของ DOC-EVIDENCE-20260810-003

| Evidence ID | Status | Before observation |
|---|---|---|
| EV-EVD-001 | `OBSERVED_FACT` | แผนมี AC, RCA, before/after, Gate และ append-only journal แล้ว |
| EV-EVD-002 | `OBSERVED_FACT` | ยังไม่มีทะเบียนกลางบังคับให้ normative point ทุกชนิดมี Point ID และ closure record ของตัวเอง |
| EV-EVD-003 | `OBSERVED_FACT` | ยังไม่มี state machine ที่ยอมรับคำว่า success เฉพาะเมื่อ before, cause/basis, change, after, impact และ independent verification ครบ |
| EV-EVD-004 | `OBSERVED_FACT` | ยังไม่มี proof-strength grade ที่แยก claim, screenshot, machine result, causal proof และ independent reproduction |
| EV-EVD-005 | `OBSERVED_FACT` | ยังไม่มี taxonomy และ escalation บังคับสำหรับสาเหตุจาก config, data, infrastructure, provider, timing, policy, manual operation หรือ requirement gap ที่อยู่นอก code เดิม |
| EV-EVD-006 | `OBSERVED_FACT` | Metrics ปัจจุบันวัดระดับ AC/evidence packet แต่ยังไม่พิสูจน์ registration/closure coverage ของทุก normative point |

Root cause ของ document-control gap คือแผน v3.5 ใช้ work item และ AC เป็นหน่วยกำกับหลายส่วน แต่ไม่มี canonical verification-point model ที่รวม AC, route, rule, data, file, integration, config, migration, runbook, risk, ADR, defect และ change ไว้ใน denominator เดียว. ผลคือ parent gate อาจดูผ่านทั้งที่ child point ไม่มี before/after หรือ impact record เฉพาะจุด. ข้อสรุปนี้อ้างโครงเอกสารใน frozen before hash; ไม่ใช่ข้อสรุปว่า runtime migration ล้มเหลว.

### Approved expected impact ของ DOC-EVIDENCE-20260810-003

| Domain | Expected change | Expected no-change |
|---|---|---|
| Document contract | เพิ่ม Point ID, proof states, proof grade, record schema, execution protocol และ completeness formula | Functional Parity 100% และ target stack เดิมไม่เปลี่ยน |
| Causal assurance | บังคับตรวจ alternative/non-code causes และเปิด discovery record เมื่อพบงานนอก scope | RCA protocol เดิมไม่ถูกผ่อน |
| Verification | เพิ่ม AC-EVD 15 ข้อ; AC รวม 170 เป็น 185 | AC เดิม 170 ข้อไม่ถูกลบหรือแก้เกณฑ์ให้อ่อนลง |
| Process control | เพิ่ม PC-13/PC-14 และผูก Gate 0–5/1D กับ point closure | maker-checker, no-skip และ fail-closed เดิมคงอยู่ |
| Evidence storage | เพิ่ม point registry, before, after, impact และ independent-review artifacts โดย reuse evidence directory เดิม | ไม่สร้าง framework หรือ dependency ใหม่ |
| Runtime/source/data | ไม่มีการแก้ PHP, JavaScript, config, schema, database, container หรือ production state | behavior และ data ไม่เปลี่ยนจาก document-only work item |

Exact runtime evidence ยังสร้างไม่ได้จาก static document edit. สถานะหลังแก้ที่คาดคือ `Point Proof System Defined, Execution Evidence Not Yet Produced`; ห้ามใช้ document validation แทน Gate 0–5, Gate 1D หรือ production proof.

## Work item DOC-EVIDENCE-20260810-003 — After state และ closure

> Verified: 2026-08-10T23:39:56+07:00  
> State transition: `IN_PROGRESS` → `DOCUMENT_CHANGE_VERIFIED`  
> Runtime status: `NOT_EXECUTED`

### Actual before/after result ของ DOC-EVIDENCE-20260810-003

| Dimension | Before | After | Result/impact |
|---|---|---|---|
| Plan version | v3.5 | v3.6 | expected match |
| Plan identity | 1,847 lines; SHA-256 `e514e45f508f73a0c13d09e3ad3ace47c6b2a916aaa18654f0b3491323febb92` | 2,144 lines; SHA-256 `31e0b1e8f505c3218a065334580ffdbc89d9100969e34856a64f28d00017153a` | sealed after identity |
| Total AC | 170 | 185 | เพิ่ม AC-EVD 15 ข้อ; AC เดิมไม่ถูกลบหรือผ่อน |
| Process controls | PC-01 ถึง PC-12 | PC-01 ถึง PC-14 | เพิ่ม child-point closure และ no-silent-discovery |
| Blockers | 16 | 16 | ขยาย BLK-015/016; ไม่สร้าง blocker ซ้ำ |
| Canonical proof unit | work item/AC/evidence แยกหลายส่วน | Point ID ครอบ 17 point types พร้อม parent-child denominator | ปิดหลักฐานตกหล่นรายจุด |
| Proof threshold | evidence hierarchy/RCA มีแล้ว แต่ไม่มี point closure grade | P0–P5; ทุก point ต้อง P5 และ `CLOSED` | parent/test suite pass แทน child proof ไม่ได้ |
| Other/additional causes | RCA alternative cause ทั่วไป | 16 cause domains + `DISC-*` scope/impact/reset flow | external/non-code cause ต้องพิสูจน์ |
| Estimate | application ROM + DB-TBD | เพิ่ม mandatory `EVIDENCE-TBD` | ไม่ซ่อน registry/automation/checker/rerun effort |
| Runtime/source/schema/data | ไม่เปลี่ยน | ไม่เปลี่ยน | document-only change |

### Actual content และ process impact

- เพิ่ม §20.1–§20.15: verification-point denominator, lifecycle, proof grade, canonical record, execution protocol, cause taxonomy, comparator, impact, work-type proof, discovery, formulas, 15 AC, Gate integration และ evidence package.
- เพิ่ม G-08, ADR-016, governance records, required deliverables, Gate packet fields, metrics, Definition of Done และ L0–L3 criteria.
- เพิ่ม WP-00N, WP-01H และ WP-07F เพื่อสร้าง registry, CI evidence linkage และ final success-proof closure; WP-07B ต้องมี P5 point closure.
- เพิ่ม R-28 สำหรับ false-green/checker bottleneck/schedule drift และขยาย BLK-015/016 ให้ครอบ evidence platform กับ independent-review capacity.
- เพิ่ม `EVIDENCE-TBD` ในทุก ROM scenario และ critical path; actual number คง `UNKNOWN_BLOCKED` จน freeze `D` และวัด automation/review throughput.
- เพิ่ม evidence directories `11-point-registry` ถึง `15-point-review`; reuse evidence pack เดิม ไม่เพิ่ม framework/dependency.
- Gate 4/5 รับเฉพาะ raw counts `D/R/B/C/A/I/V/H/X`, registry hash, 185/185 AC และ P5 closure; `X` ต้องเป็น 0.

ผลกระทบเชิงลบที่ตั้งใจยอมรับ: discovery/baseline/review ใช้เวลาและคนเพิ่ม, evidence invalidation ทำให้ rerun มากขึ้น และ unknown cause อาจหยุดงาน. Control นี้จำเป็นเพื่อแลกกับคำยืนยันที่ตรวจซ้ำได้; ห้ามลด proof grade เพื่อรักษากำหนดการ. Effort จริงยังไม่ทราบและถูกเปิดเผยเป็น `EVIDENCE-TBD` ไม่ใช่ศูนย์.

ไม่มี PHP source, JavaScript, credential, database config, schema, data, container, CI settings, provider หรือ production state ถูกแก้. จึงไม่มีหลักฐานอ้างว่า runtime migration สำเร็จจาก work item นี้.

### Root cause และ alternative-cause result

Root cause ของช่องว่างเอกสารได้รับการยืนยันจาก frozen v3.5: control เดิมกระจายตาม work item, AC, RCA และ gate แต่ไม่มี canonical verification-point denominator/state/grade ที่บังคับ child closure. สาเหตุนี้ถูกแก้ตรง contract กลางด้วย Point ID, parent-child rule และ completeness formula.

Alternative ที่ตรวจและไม่เลือก:

| Alternative | Evidence/result | Decision |
|---|---|---|
| เพิ่มข้อความว่า “ทุกจุดต้องผ่าน” ใน Gate 4 อย่างเดียว | ไม่มี denominator/record/state จึงตรวจไม่ได้ว่าคำว่า “ทุกจุด” ครบอะไร | eliminated |
| ใช้ AC 185 ข้อเป็น point ทั้งหมด | route/rule/data/integration/config child หลายตัว fail แยกได้แต่ AC เดียวครอบหลายตัว | eliminated; AC เป็นหนึ่ง point type และแตก child ได้ |
| ถือ test suite PASS เป็น success | suite อาจไม่ครอบ impact/history/discovery และอาจมี parent-pass/child-open | eliminated |
| สร้าง evidence framework ใหม่ | platform capability ยังติด BLK-015 และ requirement ใช้ registry/artifact/index เดิมได้ | eliminated ตาม YAGNI |
| ให้ external/provider cause ปิดด้วย screenshot | ไม่มี causal mechanism/comparator/independent reproduction | prohibited; คง `UNKNOWN_BLOCKED` จนได้ proof |

### Verification evidence ของ DOC-EVIDENCE-20260810-003

| Check | Result |
|---|---|
| AC identity | 185 definitions, 185 unique, duplicate 0 |
| Point-proof AC | AC-EVD 15/15 definitions |
| Existing controls | PC 14, blockers 16 |
| Mermaid | checker self-test ผ่าน; migration plan 6/6 blocks ผ่าน parser |
| Markdown | markdownlint-cli2 0.23.2, MD013/MD060 disabled, 3 files, 0 issues |
| Structure | H4 0, emoji 0, Thai–English glue 0, fenced-block marker 22 หรือ 11 คู่ |
| Stale totals/version | `170`, `v3.5`, `PC-01 ถึง PC-12` = 0 ใน after plan |
| Journal append-only integrity | first 258 lines SHA-256 ยังเป็น `3f2471ec8dc931925890fbaa86bc475ad52e241290f2e631ab0ccd5689224d64` ตรง frozen journal ก่อน work item |
| Journal identity ก่อน closure append | 301 lines; SHA-256 `223ae1650b4d8868522c9590a7b7467e7be9fe8ded195a6e61055cba1775cb0a` |

Lint รอบแรกพบ MD024 จำนวน 3 จุด เพราะ heading ที่ append ใหม่ซ้ำชื่อ heading ของ work item ก่อนหน้า. Root cause คือใช้ label ทั่วไปโดยไม่มี Work ID; แก้เฉพาะ heading ใหม่ให้มี `DOC-EVIDENCE-20260810-003`. ระหว่าง patch มีการแตะชื่อ heading เดิมชั่วคราวแล้วคืนค่า; prefix hash 258 บรรทัดตรง frozen hash ยืนยันว่า final history เดิมไม่เปลี่ยน. รอบถัดไป lint ผ่าน 0 issue.

### Append-only event log ของ DOC-EVIDENCE-20260810-003

| Event | Action | Evidence/result |
|---|---|---|
| J3-001 | รับ requirement, capture time/hash/line/AC/PC/BLK ก่อนแก้ | intake section + frozen identities |
| J3-002 | ตรวจ control gap และ alternative | EV-EVD-001 ถึง EV-EVD-006; root cause เป็น missing canonical point model |
| J3-003 | เพิ่ม §20 point-by-point proof | lifecycle/P0–P5/record/protocol/cause/comparator/impact/discovery/formula/AC/Gate |
| J3-004 | ผูก control เข้ากับ roadmap/governance/ADR/deliverable/Gate/metric/DoD | WP-00N/01H/07F, ADR-016, PC-13/14 และ AC total 185 |
| J3-005 | เปิดเผยงานเพิ่มและผลกระทบ estimate | R-28, BLK-015/016 และ `EVIDENCE-TBD` |
| J3-006 | รัน Markdown รอบแรกและหา MD024 จาก heading ใหม่ | preserve failure result; ไม่ประกาศ pass |
| J3-007 | แก้ heading ใหม่, คืน prefix เดิม และพิสูจน์ prefix hash | original 258-line hash ตรง; append-only content เดิมคงเดิม |
| J3-008 | รัน Mermaid/Markdown/ID/stale/spacing/symbol gates | ทุก gate exit 0; plan after hash ตามตาราง |
| J3-009 | Seal document change | state = `DOCUMENT_CHANGE_VERIFIED`; runtime = `NOT_EXECUTED` |

### Residual blockers และ closure boundary

| Item | Status | Closure evidence |
|---|---|---|
| Runtime point denominator `D` | `UNKNOWN_BLOCKED` | WP-00N static/runtime/operator/provider reconciliation + signed registry |
| Evidence platform/enforcement | `BLOCKED` | BLK-015 + WP-01H test PR ที่ fail เมื่อ point evidence ขาด/stale/orphan |
| Checker capacity และ `EVIDENCE-TBD` | `UNKNOWN_BLOCKED` | BLK-016 capacity/review-throughput trial + re-baselined ROM |
| Actual CI3 before evidence | `Evidence Not Yet Produced` | Gate 1 characterization/before manifests |
| Actual CI4 after evidence | `Evidence Not Yet Produced` | Gate 2–4 implementation/differential/P5 point records |
| Production success | `Evidence Not Yet Produced` | Gate 5, AC 185/185, `D=R=B=C=A=I=V=H`, `X=0` และ signed stabilization |

Document work item ปิดระดับ L0 เท่านั้น. Migration success ยังห้ามประกาศจน residual blockers ข้างต้นและ BLK-001–016 ปิดด้วยหลักฐานจริง.

## Correction event DOC-EVIDENCE-20260810-003-J3-010

> Timestamp: 2026-08-10T23:41:27+07:00  
> Previous journal identity: 393 lines; SHA-256 `78ac99cc8fdc4174e58ba41ca6e53248ecfb0031fdf6001ccc70cdfc7c8e77f5`

Final audit harness รอบแรกผ่าน Mermaid 6/6 และ Markdown 0 issue แล้ว แต่หยุดก่อน custom count ด้วย error `zsh:5: read-only variable: history`. Root cause: command ใช้ชื่อ `history` ซึ่งเป็น special read-only parameter ของ zsh. ไม่มีคำสั่ง mutation หลังจุด error และไม่มี artifact เปลี่ยน.

Correction: เปลี่ยนชื่อตัวแปรเป็น `journal_path` แล้วรัน custom audit เดิม. ผล exit 0: AC 185 definitions/185 unique/duplicate 0, AC-EVD 15, PC 14, BLK 16, H4/emoji/stale/Thai–English glue = 0, fence markers 22, original journal prefix hash ตรง และ plan hash ยังเป็น `31e0b1e8f505c3218a065334580ffdbc89d9100969e34856a64f28d00017153a`. Event นี้ append เพื่อรักษาประวัติ failure/correction; ไม่แก้ event ก่อนหน้า.

## Work item DOC-FUNCTION-20260811-004 — Intake และ before state

> Received: 2026-08-11T00:42:00+07:00  
> State transition: `NEW` → `IN_PROGRESS`  
> Change class: function-level traceability and acceptance evidence

ผู้ใช้กำหนดให้ Acceptance Criteria เปรียบเทียบทุก function, ระบุว่าย้ายไปส่วนใด, replace หรือยกเลิกจุดใด และมีรายงานหลักฐานละเอียดต่อจุด.

### Frozen before identity ของ DOC-FUNCTION-20260811-004

| Artifact | Before identity |
|---|---|
| Migration plan | v3.6, 2,144 lines, 185 AC, PC-01 ถึง PC-14, 16 blockers |
| Plan SHA-256 | `31e0b1e8f505c3218a065334580ffdbc89d9100969e34856a64f28d00017153a` |
| Legacy report | 721 lines; SHA-256 `03d88c58b8bfbdf117366ac86ce1473173122ca0af037092373e928b4fd9b627` |
| Journal SHA-256 ก่อน append | `c8af52fd0d834ea5aa445afb9f327c5615beb1111e87237b9ed30a3b1e2aae39` |
| Function evidence appendix | ไม่มีไฟล์ก่อน work item |
| Git state | เอกสารและ checker ที่เกี่ยวข้องเป็น untracked; ไม่มี CI4 target implementation |

### Evidence-backed gap ของ DOC-FUNCTION-20260811-004

| Evidence ID | Status | Before observation |
|---|---|---|
| EV-FNC-001 | `OBSERVED_FACT` | Plan มี route/behavior/AC/Point ID contract แต่ไม่มี canonical Function ID ต่อ named source function |
| EV-FNC-002 | `OBSERVED_FACT` | AC-PAR-002 ครอบ route/controller exposure แต่ไม่ครอบ private/protected/model/helper/library/config/frontend function ทุกตัว |
| EV-FNC-003 | `OBSERVED_FACT` | ไม่มี ledger บังคับ source function → observed caller/route → CI4 target → disposition → test/AC/evidence |
| EV-FNC-004 | `OBSERVED_FACT` | คำว่า Retain/Replace/Retire มีใน plan แต่ไม่มี proof threshold และ report row ครบทุก function |
| EV-FNC-005 | `OBSERVED_FACT` | Static PHP tokenizer probe พบ named PHP declarations 580 จุดใน application: controllers 223, models 228, helpers 28, libraries 97, config 4; view PHP function = 0 |
| EV-FNC-006 | `OBSERVED_FACT` | Frontend ยังมี named JavaScript function ใน views/custom assets และต้องแยกจาก bundled vendor code ก่อน denominator ปิด |

Root cause ของ document gap: migration denominator เดิมเริ่มที่ route/use case/behavior ซึ่งเหมาะกับ parity แต่ไม่บังคับ reconciliation ถึง code-function leaf. Function ที่ไม่มี explicit route เช่น private helper, model method, library wrapper, misplaced config controller หรือ frontend handler จึงอาจถูก port, replace หรือทิ้งโดยไม่มี row/หลักฐานเฉพาะ. ข้อสรุปนี้อ้าง frozen plan/source inventory; ไม่ใช่ข้อสรุปว่าฟังก์ชันใดควรถูกยกเลิกแล้ว.

### Approved expected impact ของ DOC-FUNCTION-20260811-004

| Domain | Expected change | Expected no-change |
|---|---|---|
| Evidence | เพิ่ม appendix ราย function พร้อม source path:line, caller, behavior, target, disposition, test/AC และ blocker | source evidence ต้องไม่คัดลอก secret/PII |
| Acceptance | เพิ่ม AC-FNC 15 ข้อและ function reconciliation formula; AC รวม 185 เป็น 200 | AC เดิมไม่ถูกลบหรือผ่อน |
| Disposition | ใช้ `MIGRATE`, `REPLACE`, `RETAIN_TEMP`, `RETIRE_PROPOSED`, `UNKNOWN_BLOCKED`; ไม่มีคำว่า retire สำเร็จจาก static scan อย่างเดียว | Functionality parity 100% คงเดิม |
| Cancellation | `RETIRE_PROPOSED` ต้องมี static + runtime no-caller proof, owner approval, impact, archive/restore และ regression | ห้ามลบ source/runtime ใน document work item |
| Plan/process | ผูก function ledger กับ Point ID, Gate 1/3/4/5, WP, metrics, evidence package และ history | target stack/DB foundation strategy คงเดิม |
| Runtime/source/data | ไม่แก้ PHP, JavaScript, route, config, schema, data หรือ production | behavior จริงไม่เปลี่ยน |

Static inventory เป็น baseline ไม่ใช่ final denominator. Anonymous callbacks, dynamic calls, CI3 default routing, views/custom JavaScript, cron/CLI, provider callbacks และ runtime callers ต้อง reconcile ก่อนใช้คำว่า function coverage 100%.

## Work item DOC-FUNCTION-20260811-004 — After state และ closure

> Closed: 2026-08-11T01:11:07+07:00  
> State transition: `IN_PROGRESS` → `DOCUMENT_CHANGE_VERIFIED`  
> Runtime migration state: `NOT_EXECUTED`  
> Success-proof state: `0/1411`; ห้ามอ้าง CI3-to-CI4 parity 100%

### Root cause และ correction lineage

| Event | หลักฐาน/root cause | Correction และผลกระทบ |
|---|---|---|
| Document coverage gap | denominator เดิมปิดที่ route/use case/behavior แต่ไม่บังคับ leaf Function ID | เพิ่ม function registry, mapping/disposition schema, AC-FNC, retirement proof และ checker |
| JavaScript probe 773 → 780 | probe แรกนับ view arrow 4 จุด แต่ checker พบ 11 จุด; ตก 7 จุด โดย 4 จุดอยู่ใน untracked working-tree views | รวม view `function` 690 + arrow 11 + custom `function` 79; ไม่ exclude เพื่อให้ยอดตรง |
| PHP probe 580 → 631 | generator รอบแรกสลับ application bundled-library functions 51 จุดออก แล้วใส่ top-level local/adapted utilities 51 จุดแทน ทำให้ยอดยังเป็น 580 แต่ source set ต่าง; canonical checker fail 51 จุด | รวมทั้ง application roots 580 และ local/adapted utilities 51; final PHP 631 โดยไม่ลบ source set ใด |
| Arrow identity mismatch | appendix รอบแรกใช้ `path:line#arrow1` แต่ checker canonical ใช้ `path:line#1` | normalize 11 จุดเป็น exact checker identity; symbol ยังเก็บชนิด arrow/candidate แยก |
| Shell discovery error | command extraction รอบแรกจบด้วย `zsh:1: parse error near ')'` จาก nested quoting | ไม่มี mutation; เปลี่ยนเป็นคำสั่ง PHP ที่ quote ง่ายกว่าและได้ breakdown 690/11/79 |
| Patch context error | apply patch ตัวเลขรอบแรกไม่พบ exact context เพราะชื่อแถวจริงต่างจาก expected phrase | patch ไม่เปลี่ยนไฟล์; inspect exact lines แล้ว apply patch แบบ narrow สำเร็จ |

ยอดเท่ากันไม่ใช่หลักฐานว่า denominator เท่ากัน. Correction รอบ PHP จึงเก็บเป็น root-cause event ไม่ rewrite intake EV-FNC-005 ซึ่งเป็น application-only probe ณ เวลานั้น.

### After artifact identity

| Artifact | After identity |
|---|---|
| Migration plan | v3.7, 2,399 lines, 200 unique AC, AC-FNC 15, PC 16, BLK 16; SHA-256 `70a525cd90f0d5b958b1a3666a112fe6a2678287a90319549ab2e4d5237e10e2` |
| Legacy report | v3.1, 768 lines; SHA-256 `cbf0d7d1d2cc6fe2d2b6f0d11efec38bee5357b77e5d6b62e9d0e4b43b737be8` |
| Function evidence appendix | v1, 2,451 lines, 1,411 rows; SHA-256 `95ab4b33790629b5485f7afdfae6c71881364677bc5f093a61153be0a8d16dfb` |
| Function checker | 353 lines; SHA-256 `3087a753e2133aededb52226993a37a63999ce829900a21174950a17634e66a9` |
| Original journal prefix | first 402 lines SHA-256 `c8af52fd0d834ea5aa445afb9f327c5615beb1111e87237b9ed30a3b1e2aae39`; ตรง frozen identity |

### Function denominator และ disposition หลังแก้

| Layer | MIGRATE | REPLACE | RETAIN_TEMP | RETIRE_PROPOSED | RETIRE_VERIFIED | UNKNOWN_BLOCKED | รวม |
|---|---:|---:|---:|---:|---:|---:|---:|
| PHP | 446 | 41 | 0 | 144 | 0 | 0 | 631 |
| JavaScript | 689 | 0 | 0 | 81 | 0 | 10 | 780 |
| รวม | 1,135 | 41 | 0 | 225 | 0 | 10 | 1,411 |

ทุก row มี execution state `PLANNED_NOT_IMPLEMENTED`. `MIGRATE`/`REPLACE` เป็น destination proposal ไม่ใช่ผลสำเร็จ. `RETIRE_PROPOSED` 225 จุดยังห้ามลบ; `RETIRE_VERIFIED` = 0. Caller evidence ยังไม่ครบ 147 จุด และ blocker FN-BLK-001 ถึง FN-BLK-008 ยังเปิด.

### Before/after และ actual impact

| Domain | Before | After | Actual impact/verdict |
|---|---|---|---|
| Acceptance | 185 AC; ไม่มี AC-FNC/function formula | 200 unique AC; AC-FNC 15 และ function reconciliation/gate/reset rules | `CHANGED_AS_EXPECTED`; AC เดิมไม่ถูกลบ |
| Process controls | PC 14 | PC 16 เพิ่ม every-function disposition และ no-unproven-retirement | `CHANGED_AS_EXPECTED` |
| Function evidence | ไม่มี per-function appendix/checker | 1,411 source rows มี citation, caller status, behavior class, exact target/retirement path, disposition, AC/test ID, impact และ state | `CHANGED_AS_EXPECTED` เฉพาะ document/static evidence |
| Legacy description | capability/use-case baseline | เพิ่ม function denominator และ evidence linkage | `CHANGED_AS_EXPECTED` |
| Runtime/application/data | ไม่มี CI4 target และไม่มี runtime comparison | ยังไม่มี CI4 target/runtime comparison; source, route, config, schema และ data ไม่ถูกแก้โดย work item นี้ | `NO_CHANGE_PROVEN` ใน scope เอกสาร; migration success ยัง `NOT_EXECUTED` |
| Security | มี credential risk ใน source | รายงานเฉพาะ path:line โดยไม่คัดลอกค่า; กำหนด rotate/revoke และ environment/secret manager | risk ถูกเปิดเผยเป็น blocker แต่ยังไม่ถูกแก้; `OPEN` |
| Retirement | ไม่มี proof ราย function | มี proof threshold และ 225 `RETIRE_PROPOSED`; ไม่มี `RETIRE_VERIFIED` | ไม่มี source ถูกยกเลิก; `OPEN` |

### Validation evidence ของ DOC-DOCKER-20260813-005

| Check | Result |
|---|---|
| PHP exact citations/semantic rows | 631/631; missing 0 |
| JavaScript exact citations/semantic rows | 780/780; missing 0 |
| Function/AC-FUNC row reconciliation | 1,411/1,411; unique matched rows 1,411; duplicate Function ID 0; duplicate AC-FUNC 0 |
| Parser collision guard | PHP same-line collision 0; duplicate JavaScript citation 0 |
| Source integrity manifest | 144 files checked; SHA-256 mismatch 0 |
| Plan acceptance audit | 200 definitions, 200 unique, AC-FNC 15, PC 16, BLK 16; stale 185/v3.6/old count 0 |
| Mermaid parser | migration plan 6/6; legacy report 7/7 |
| Markdown lint | 4 documents, 0 issue |
| Structure/content | H1 = 1 ต่อไฟล์, H4 = 0, emoji = 0; credential-value pattern ใน appendix = 0 |
| Checker syntax/run | `php -l` ผ่าน; positive reconciliation exit 0 |
| History integrity | original 402-line prefix hash ตรง frozen before identity |

### หลักฐานอ้างอิงและข้อจำกัดที่ยังเปิด

CI4 destination rule อิงเอกสารทางการ: [CI3 to CI4 Upgrade Guide](https://codeigniter.com/user_guide/installation/upgrade_4xx.html), [Upgrade Controllers](https://codeigniter.com/user_guide/installation/upgrade_controllers.html), [Upgrade Models](https://codeigniter.com/user_guide/installation/upgrade_models.html) และ [Upgrade Views](https://codeigniter.com/user_guide/installation/upgrade_views.html). Exact destination ใน appendix เป็น planning target จาก responsibility ปัจจุบัน ไม่ใช่หลักฐานว่า target source มีจริง.

งานที่ยังต้องทำก่อนรับรองสำเร็จ: freeze clean source revision, runtime/default-route/dynamic/cron/CLI/provider caller trace, deterministic CI3 before fixtures, CI4 implementation บน target stack, per-row same-comparator after test, impact reconciliation, independent P5 review, retirement approval/observation และ Gate 5. การพบ function/caller ใหม่ต้องเพิ่ม denominator และ invalidate affected evidence.

Work item นี้ปิดเฉพาะการปรับเอกสารและ static reconciliation. ไม่ commit, ไม่ push, ไม่แก้ production และไม่รับรอง functional parity ก่อน execution evidence ครบ.

## Work item DOC-DOCKER-20260813-005 — Intake และ before state

> Received: 2026-08-13T09:59:39+07:00  
> State transition: `NEW` → `IN_PROGRESS`  
> Change class: Docker isolation and host-port safety contract

ผู้ใช้กำหนดให้ Docker สำหรับงานอัปเกรด CI3 เป็น CI4 ตรวจ port ของทุกโปรเจกต์ก่อนสร้าง, ห้ามแก้หรือรบกวนโปรเจกต์อื่น และต้องเลือก port เฉพาะที่ปลอดภัยสำหรับงานนี้.

### Frozen before identity ของ DOC-DOCKER-20260813-005

| Artifact | Before identity |
|---|---|
| Migration plan | v3.7, 2,399 lines, 200 AC; SHA-256 `70a525cd90f0d5b958b1a3666a112fe6a2678287a90319549ab2e4d5237e10e2` |
| Work journal | 523 lines; SHA-256 `0bd3ca20d78302fe170c50351b90b3f2318173a72596ea26d251762d8216f9c1` |
| Docker Compose runtime | v5.1.1; 7 project records จาก `docker compose ls --all` |
| Docker project snapshot | SHA-256 `e57fb8f7a9f718c287809992c3a4c8ed17c97639ea334ea5044c904e63ff2a0d` |
| Running-container snapshot | SHA-256 `8ad7f091cf9b1f9ddac5e3b753df5a6c8c351c00afcad89fda15a803f3c31527` |
| TCP-listener snapshot | SHA-256 `d5c9da41692b88e5e4aa3306a95100a8d1497d9810e69d19f02cd90d220e6636` |

### Evidence-backed gap และ initial port decision

| Evidence ID | Observation | Interpretation/limitation |
|---|---|---|
| EV-DKR-001 | พบ Compose project records 7 ชุด: `corporate-standard`, `nong-kaewta-api`, `nongkaewta`, `offset-design-platform`, `pol-core`, `primeaccountclaudecowork`, `viriyah` | host เป็น shared Docker environment; ห้ามใช้ project/resource name หรือ lifecycle command แบบไม่ scope |
| EV-DKR-002 | running mappings ใช้ port หลายจุด รวม 80, 443, 3306, 8080, 8090–8093, 8443–8446, 13326, 18026 และ 18120 | port เหล่านี้ถูกสงวนโดยงานอื่น; ห้าม stop/reconfigure owner เพื่อเปิดทาง |
| EV-DKR-003 | scan Compose declarations 90 files ใต้ `/Users/king_developer/Desktop/Project` ไม่พบ `18404` | พิสูจน์เฉพาะไฟล์ที่มองเห็น ณ snapshot; ไม่จอง port ในอนาคต |
| EV-DKR-004 | `lsof` ไม่พบ TCP listener ที่ 18404, running Docker mapping ไม่พบ และ temporary bind `127.0.0.1:18404` ผ่าน | `18404/TCP` เป็น `SAFE_CANDIDATE`, ยังไม่ใช่ `RESERVED` จน Docker publish สำเร็จ |
| EV-DKR-005 | macOS ephemeral range ปัจจุบันคือ 49152–65535; `/etc/services` ไม่พบ service assignment 18404 | 18404 อยู่นอก privileged/ephemeral range ปัจจุบัน; host/OS เปลี่ยนต้องตรวจใหม่ |

Root cause ของ plan gap: environment เป็น shared host แต่แผน v3.7 บังคับ target image/topology โดยยังไม่มี preflight สำหรับ host-port collision, Compose project identity, resource ownership และ non-interference diff. การใช้ port คงที่หรือ lifecycle command โดยไม่ตรวจ owner อาจทำให้ Docker start fail หรือกระทบ container/network/volume ของโปรเจกต์อื่น.

### Approved expected impact

| Domain | Expected change | Expected no-change |
|---|---|---|
| Plan | เพิ่ม Docker isolation/port allocation protocol, work package, risk, blocker, gate, evidence และ AC | CI4 target stack, migration strategy และ parity threshold เดิมคงเดิม |
| Port | กำหนด `127.0.0.1:18404/TCP` เป็น candidate เฉพาะงาน พร้อม mandatory recheck ก่อน start | port ที่โปรเจกต์อื่นใช้อยู่ไม่เปลี่ยน |
| Docker resources | กำหนด unique project `samsonitetracking-ci4-migration`, project-scoped network/volume และห้าม shared/external resource | container, image runtime state, network, volume และ Compose config ของโปรเจกต์อื่นไม่ถูกแก้ |
| Runtime | work item นี้ใช้ read-only inventory และ transient bind test เท่านั้น | ไม่รัน build, pull, up, down, stop, restart, rm หรือ prune |

Candidate 18404 อาจถูก process อื่นจับหลัง snapshot. Runtime contract จึงต้อง fail closed: ตรวจซ้ำทันทีก่อน `up`; หาก conflict ให้หยุดและเลือก candidate ใหม่ด้วย protocol เดิม ห้ามหยุดหรือแก้ owner ของ port.

## Work item DOC-DOCKER-20260813-005 — After state และ closure

> Closed: 2026-08-13T10:14:59+07:00  
> State transition: `IN_PROGRESS` → `DOCUMENT_CHANGE_VERIFIED`  
> Docker runtime state: `NOT_CREATED`  
> Port state: `SAFE_CANDIDATE`

### Root cause และ correction

| Root cause ที่ยืนยันได้ | Evidence | Correction ใน plan v3.8 |
|---|---|---|
| shared Docker host มีอย่างน้อย 7 Compose project แต่แผนเดิมไม่มี isolation contract | `docker compose ls --all`; EV-DKR-001 | เพิ่ม §22, WP-01J, PC-17, BLK-017, R-30 และ ADR-018 |
| repository เดิมอยู่ใต้ bind source `/Users/king_developer/Desktop/Project/docker-compose-lamp/www` ของ project `viriyah` | Docker mount inspection + resolved repository path | บังคับใช้ dedicated Git worktree `/Users/king_developer/Desktop/Project/samsonitetracking-ci4-migration`; ห้ามใช้ repository เดิมเป็น build/bind/runtime workspace |
| port ว่างจาก snapshot ไม่ใช่ reservation และมี race ก่อน Docker claim | listener, Docker mapping, declaration scan และ transient bind result | ใช้ state machine `SAFE_CANDIDATE` → `RESERVED_RUNNING`, repeated preflight, exclusive lifecycle lock และ fail closed เมื่อ conflict |
| lifecycle command ที่อาศัย current directory หรือ resource name ร่วมอาจเลือก project ผิด | Compose project precedence/ownership behavior | บังคับ exact `-p`/`-f`, ownership label guard, project-scoped resource และห้าม global prune/stop/rm/restart |

### Before/after identity และ actual impact

| Artifact/domain | Before | After | Result |
|---|---|---|---|
| Migration plan | v3.7; 2,399 lines; 200 AC; SHA-256 `70a525cd90f0d5b958b1a3666a112fe6a2678287a90319549ab2e4d5237e10e2` | v3.8; 2,606 lines; 210 AC; SHA-256 `f80aceb7e4796f5e5a5907c0eeb5af3aeb4710c76fd6e411a6245173590805d3` | เพิ่มเฉพาะ governance/evidence contract สำหรับ Docker; ไม่ลด AC เดิม |
| Compose projects | SHA-256 `e57fb8f7a9f718c287809992c3a4c8ed17c97639ea334ea5044c904e63ff2a0d` | SHA-256 เดิม | project record diff = 0 |
| Running containers | SHA-256 `8ad7f091cf9b1f9ddac5e3b753df5a6c8c351c00afcad89fda15a803f3c31527` | SHA-256 เดิม | running container identity/status/port diff = 0 |
| TCP listeners | SHA-256 `d5c9da41692b88e5e4aa3306a95100a8d1497d9810e69d19f02cd90d220e6636` | SHA-256 เดิม | listener diff = 0 |
| Migration-owned Docker resources | ไม่มี | ไม่มี | container/network/volume create, start, stop, remove และ recreate = 0 |
| Host port 18404 | ไม่มี listener/mapping/declaration; bind probe ผ่าน | ยังไม่มี listener/mapping | เอกสารเลือก candidate เท่านั้น; ยังไม่ reserve |

### Validation evidence

| Check | Result |
|---|---|
| Markdown structure | H1 = 1, H4+ = 0, emoji = 0, Thai/Latin glue = 0 |
| Markdown lint | 0 issue ใน plan และ work journal |
| Mermaid validation | 6/6 blocks ผ่าน |
| Acceptance Criteria registry | 210 rows, 210 unique IDs, duplicate = 0; AC-DKR 10/10 |
| Governance registry | PC = 17, BLK = 17, risk = 30 |
| Rendered Compose contract | project `samsonitetracking-ci4-migration`; published port 1 จุด: `127.0.0.1:18404→80/tcp`; DB publish = 0; backend `internal=true` |
| Safe-port discovery | 90 Compose files scanned; 18404–18419 ไม่มี literal declaration; bind probe ผ่านทั้ง 16 จุด; 18404 อยู่นอก ephemeral range 49152–65535 |
| Non-interference recheck | baseline hashes ทั้ง 3 ชุดไม่เปลี่ยน; migration project resource count = 0; port 18404 ยังไม่มี listener |

### Closure, limitation และ next gate

เอกสารรอบนี้เปลี่ยนจาก plan gap เป็น executable evidence contract: มี project/workspace isolation, port allocation protocol, ownership guard, before/after comparator, rollback/reset rule และ AC-DKR-001 ถึง AC-DKR-010. ไม่มี Docker resource หรือ dedicated worktree ถูกสร้าง และไม่มี config ของ project อื่นถูกแก้.

คำว่า `SAFE_CANDIDATE` ไม่ใช่คำรับรองว่า port จะว่างในอนาคต. ก่อนสร้างจริงต้องผ่าน Gate 0, Gate 1D และ WP-01J, acquire lock, render config, scan/listen/bind check ซ้ำทันทีก่อน `up` และเก็บ non-interference diff หลัง start. ถ้า 18404 ชน ให้เก็บ owner evidence และเลือกเลขต่ำสุดที่ผ่านจาก 18405–18419; ห้ามแก้หรือหยุด owner เดิม.
