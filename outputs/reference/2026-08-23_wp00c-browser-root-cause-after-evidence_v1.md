# WP-00C Browser Root Cause and After Evidence

เอกสารนี้สรุปผลก่อนและหลังการแก้, ผลกระทบต่อระบบ, root cause ที่พิสูจน์แล้ว และสถานะ formal closure ณ 2026-08-23. การทดสอบใช้ isolated CI4/MariaDB runtime กับ synthetic data เท่านั้น.

## Verdict

WP-00C ยังปิด formal ไม่ได้: `2/53`. Machine evidence รอบล่าสุดเป็น `51 PASS`, `2 BLOCKED`, `0 FAIL`; semantic hash ของทุก case ตรงกันทั้ง 3 รอบ.

สาเหตุที่ formal count ไม่เพิ่มไม่ใช่ test failure. Closure gate บังคับให้แต่ละ case มีผล `PASS` ครบ 3 รอบ, hash ตรง และ approvals ครบตาม role; ปัจจุบันมี approval records `7/149`, จึงเหลือ `142` approvals.

## Root cause ที่พิสูจน์แล้ว

| Root cause | หลักฐานก่อนแก้ | หลักฐานยืนยัน | ผลแก้ |
|---|---|---|---|
| Runtime menu ใช้ legacy DB link ตรง | OpenAI Browser พบ sidebar link ตาย 13 เส้นทาง และยัง expose `ReportTrackingListingTest` ที่ 404 | `app/Views/layout.php` เรียก `MenuStore::visible()` แล้ว render `menu_link`; pre-fix store ไม่ normalize route disposition | Normalize ที่ `MenuStore::visible()` จุดเดียว, deduplicate และตัด retired link |
| Backend มี lifecycle/report contract แต่ Browser ไม่มี control ให้ใช้ | Backend tests ผ่าน แม้ order queues ไม่มี transition form, summary ไม่มี filters, report pages ไม่มี export link | Red tests ใหม่ได้ 6 failures: retired menu, lifecycle forms, 3 report controls และ 1 harness URL assertion | เติม native HTML forms/links โดย reuse endpoints, CSRF และ report services เดิม |
| Machine tests เดิมตรวจ seam ไม่ครบ | Menu test seed canonical path; order testยิง POST endpointตรง; report testตรวจ response exportแต่ไม่ตรวจ linkจากหน้า | Browser เดินธุรกิจไม่ได้ทั้งที่ mapped cases เป็น PASS | Tests ใหม่ assert visible action/link/field และ Browser เดิน lifecycleจริง `1→2→3→4→5` |
| PHPUnit failure เรื่อง missing encryption key เป็น harness conflict | Full suiteใน Browser runtimeได้ `202` แทน `503` 1 test | Runtimeตั้ง `encryption_key=SET`; overrideเฉพาะ test processให้ค่าว่างแล้ว testผ่าน `503` contract | รัน missing-key suiteด้วย empty test env; ไม่แก้ product code |
| Evidence mapper รอบแรกใช้ test tree เก่าใน container | Mapperรายงาน missing identities 32 cases แต่ hostมี test filesครบ | Container bind mountเฉพาะ `app`; หลัง copy host test tree จำนวน testเพิ่ม `91→121` | Align test provenanceก่อนสร้าง JUnit evidenceใหม่ |
| Browser import เริ่มด้วย admin กลาง | Browser POST status XLSX ตอบ `invalid_import` แม้ไฟล์และ header ถูกต้อง | `ci4_users` id `9001` มี `role_id=1, branch_id=NULL`; `Imports` ส่ง `BranchID` เข้า `ImportWorkflow`; workflow ปฏิเสธ owner ที่ไม่มี branch; fixture/spec ระบุ actor import คือ `wp00c-a` id `9002`, branch `1` | สลับ Browser test actor เป็น operator A; ไม่แก้ source และไม่ขยายสิทธิ์ admin โดยไม่มี approval |

Hypothesis ว่า `/sendorderListing` ต้องแสดง status 2 ถูกหักล้าง. CI3 baseline กำหนดหน้านี้เป็น status 1 logistics queue; หลังส่ง provider orderต้องหายจากหน้านี้และไป `/TrackingListing` status 2.

## ก่อนและหลัง

| พื้นที่ | ก่อน | หลัง | ผลกระทบระบบ |
|---|---|---|---|
| Sidebar | Legacy paths จาก `tbl_menu` ไป 404; Test report linkยังแสดง | Legacy master/user/background/menu pathsชี้ canonical routes; Test linkไม่แสดง | เปลี่ยนเฉพาะ navigation output, ไม่ rewrite DB และไม่มี migration |
| Login history | Menu link `/login-history` 404 | Routeอ่าน historyของ actorปัจจุบันผ่าน branch-scoped store | เพิ่ม read-only endpoint; auth และ authorization filtersยังบังคับ server-side |
| Order lifecycle | List/print/editมี แต่ userส่ง statusต่อจาก Browserไม่ได้ | Roles 1/2เห็น CSRF formsสำหรับ provider, start repair, complete repair, deliver | ใช้ workflowเดิม; transaction, state guard, branch scope และ status logไม่เปลี่ยน |
| Report summary | ไม่มี filter form/export control | Search/date/status/brand/type/branch filters, export link และ paginationรักษา filters | Query serviceเดิม; เพิ่ม read queriesสำหรับ option lists, ไม่เปลี่ยน schema |
| Ratings/in-progress/tracking reports | Export backendมี แต่หน้าไม่มีทางเข้า | แสดง export linksพร้อม query filters | Download response contractเดิม; discoverabilityดีขึ้น |
| Dependencies/config | ไม่มี dependencyใหม่ | ไม่มี dependencyใหม่ | Lock file, schema และ deployment configไม่เปลี่ยนจากงานนี้ |

## OpenAI Browser evidence

| Flow | ผล |
|---|---|
| Public tracking EN/TH, unknown, wildcard, 404 routes | PASS; timelineเรียงถูก, ไม่มี partial match |
| Contact EN/TH forms, EN submit และ listing search | PASS; synthetic EN contactและ delivery intentเขียนครั้งเดียว; TH submitครอบด้วย PHPUnit |
| Rating page/submit | PASS; 8 scores, 1 comment, order `5→7`, ไม่มี extra status log |
| Login, invalid/deleted generic error, logout, history | PASS; protected redirectและ login history deltaถูกต้อง |
| Roles/branch isolation | PASS; viewer/operator cross-branch user/order/reportได้ 404 |
| User, master, menu, background status | PASS สำหรับ create/edit/read และ status toggle; ไม่มี destructive action |
| Order create/replay/edit/print | PASS; replayไม่เพิ่ม order/log/intent |
| Order lifecycleหลังแก้ | PASS; Browserเห็นทุก action, DBยืนยัน status/log `1→2→3→4→5` |
| Report filtersหลังแก้ | PASS; summary exact 1 rowสำหรับ tracking/date/status/brand/type/branchชุดเดียว |
| Export controlsหลังแก้ | PASS ด้าน DOM/link; backend XLS contractผ่าน PHPUnit |
| XLSX upload status, price, new order | PASS; Browser preview ทุกชุด `Accepted: 1`, `Rejected: 0`; confirm สร้าง owned batch และ DB delta ตรง contract |
| PNG upload branch type, background, repair order | PASS; Browser form create สำเร็จ; DB เก็บ hashed filename, stored byte hash ตรง fixture และ permission `0640` |
| Published tracking background | PASS; Browser เปิด `/track`; response อ้าง `/background-image/a624bd426109f6f181f52422667a4899.png` |
| Browser console | `0` error logs ใน flowsที่ตรวจ |

Browser downloadไม่ได้รับ permission จึงไม่กด exportซ้ำ; backend XLS contractผ่าน PHPUnit. Browser uploadใช้ user-selected synthetic files เพราะ guard ปฏิเสธ agent file chooser; ไม่มี HTTP/CDP/Browser อื่นเป็น bypass.

## Browser upload evidence

| Flow | Before | After | Proof |
|---|---|---|---|
| Status XLSX | order `91002`: status `2`, price `200.00` | status `4`, price `222.22`, repair/update/complete dates, logและ upload row อย่างละ 1 | batch `9954a8eb032cb6a5841e47c82b5973cd`, owner `9002/1`, fixture hash `799e16…4295` |
| Price XLSX | order `91003`: status `3`, price `300.00` | price `333.33`; statusและ log countคงเดิม | batch `ab936b6de29d1baa8d7f1b78b56752a9`, owner `9002/1`, fixture hash `c4c5be…4a63` |
| New-order XLSX | `WPA/BROWSER-XLSX-1001` ไม่มี row | create `G26080002`, branch `1`, status `4`, price `444.44`, log action `4` | batch `3a3c89ae0d2e98db2544d05b52a88146`, owner `9002/1`, fixture hash `3a8ba7…ae41` |
| Branch-type PNG | ไม่มี master row | create id `8`, file `0fe39c01852a9ad49e567edc3b5ae985.png` | 68 bytes, SHA-256 `431ced…5460`, mode `0640` |
| Background PNG | ไม่มี background id `97002` | create Publishing row `97002`, file `a624bd426109f6f181f52422667a4899.png` | 68 bytes, SHA-256 `431ced…5460`, mode `0640`, public `/track` reference |
| Repair-order PNG | ไม่มี `BROWSER-ORDER-PNG-1003` | create `G26080003`, status `1`, SMS intent pending, file `1e1a74ca755cf9a572c90150ffa71a26.png` | 68 bytes, SHA-256 `431ced…5460`, mode `0640` |

Machine-readable upload evidence: `evidence/wp00c/browser-upload-2026-08-23.json`.

## Automated evidence

| Gate | ผล |
|---|---|
| Initial focused RED | `16 tests`, `159 assertions`, `6 failures` |
| Focused GREEN หลัง root fix | `16/16`, `241 assertions` |
| Review-gap tests | `11/11`, `176 assertions` |
| Full CI4 รอบ 1 | `121/121`, `1,752 assertions` |
| Full CI4 รอบ 2 | `121/121`, `1,752 assertions` |
| Full CI4 รอบ 3 | `121/121`, `1,752 assertions` |
| Concurrency/loopback | PASS 3 รอบ: token/rate/order allocation/import replay+isolation/public tracking/email/SMS |
| Recovery | PASS 3 รอบ: seed `116`, cleanup `0`, restore `31 tables/0 rows`, CI3 pin clean |
| Case mapper | `51 PASS`, `2 BLOCKED`, `0 FAIL`, determinism mismatch `0` |
| Repository CI gate | PASS: dependency auditไม่พบ advisory, route/health, schema, secret/PII, outbound deny และ safety checksผ่าน |

Evidence files:

- `evidence/wp00c/round-1.json`
- `evidence/wp00c/round-2.json`
- `evidence/wp00c/round-3.json`
- `evidence/wp00c/closure.txt`

## Blockers ที่เหลือ

### RPT-EDGE-001

Functional bad-date, large-search, cross-branch และ 100-row pagination checksผ่าน. Performance acceptanceยังทำไม่ได้เพราะไม่มี approved generated-volume profile, latency/memory/query-plan budget และ production-like environment manifest.

### PERF-CI3-001

Synthetic 116-row functional suiteผ่าน แต่ใช้แทน capacity baselineไม่ได้. ต้องมี approved sanitized production-like volume profile และ environment manifestก่อนเปลี่ยน mappingจาก `BLOCKED` เป็น executable PASS gate.

### Approvals

Agentห้ามสร้าง human approvalแทน Business, Engineering, QA, Security, DBA หรือ Operations. Formal closureจึงคง `2/53` แม้ machine evidenceของ 51 casesผ่านครบ 3 รอบ.

## เงื่อนไขปิด 53/53

1. ให้ approved volume profile, NFR budgets และ environment manifestสำหรับ 2 performance cases.
2. รัน 2 casesจนได้ PASS hashตรง 3 รอบ.
3. ให้ named approversลง approval recordsที่ยังขาด.
4. รัน `scripts/wp00c-closure.py`; ปิดได้เมื่อ outputเป็น `WP-00C CLOSED 53/53` เท่านั้น.
