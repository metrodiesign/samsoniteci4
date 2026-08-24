# Prompt งานอัปเกรด CI3 เป็น CI4

Prompt สำหรับส่งให้ session หรือ agent ใหม่ขับงาน migration ทั้งโครงการตาม charter `outputs/diagrams/2026-08-09_ci3-to-ci4-upgrade-plan_v3.md`. คัดลอกทั้งไฟล์ตั้งแต่หัวข้อ Objective ลงไปใช้ได้ทันที; อัปเดตส่วน "สถานะจริง" ทุกครั้งที่ gate เปลี่ยน.

## Objective

ขับงานย้ายระบบ Samsonite Tracking จาก CodeIgniter 3 ไป CodeIgniter 4 บน PHP 8.5 + MariaDB 11.4 LTS ให้จบทุก Phase (0-7) ตาม migration charter `2026-08-09_ci3-to-ci4-upgrade-plan_v3.md` โดยรักษา Functional Parity 100% และ UX/UI Parity 100% พร้อมหลักฐานปิดทุก gate
ทำทีละ work package (WP) ตามลำดับ dependency; แต่ละ WP ต้องมีหลักฐานรันจริงก่อนประกาศ PASS

## Context

Repo หลัก (worktree ที่ใช้ทำงาน): `/Users/king_developer/Desktop/Project/samsoniteci4` — branch ปัจจุบัน `develop`, default branch `main`
ห้ามใช้ repository เดิมใต้ `/Users/king_developer/Desktop/Project/docker-compose-lamp/www` เป็น workspace/bind/build (เป็นของ Compose project `viriyah`)

**Workspace identity ที่ถือเป็นจริง**: path ของ worktree คือ `/Users/king_developer/Desktop/Project/samsoniteci4` (ไม่ใช่ชื่อ `samsonitetracking-ci4-migration` ที่ §22 ของแผนเขียนไว้ตอนออกแบบ — ชื่อนั้นถูกใช้เป็น **Compose project name** แทน) Compose project name ที่ถูกต้องคือ `samsonitetracking-ci4-migration` ยืนยันได้ที่ `compose.yaml:1`, `db/dbctl.sh:15`, `db/privacy-purge-local.sh:7` และ `db/dbctl.sh` บังคับ `COMPOSE_PROJECT_NAME` ให้ตรงค่านี้ ก่อนคำสั่ง Docker ที่มี `-p` ทุกครั้งให้รัน `docker compose ls` ยืนยันก่อน

**Working tree สกปรกโดยตั้งใจ**: ไฟล์ untracked (`app/`, `public/`, `spark`, `composer.json`, `composer.lock`, `Dockerfile.ci4`, `phpunit.xml.dist`, `tests/ci4/`, `writable/`, `docs/`, `outputs/reference/*` ล่าสุด) คือ **work product ที่ยังไม่ commit** ห้าม `git clean`, `git reset --hard`, `git checkout .` หรือ drop stash ทุกกรณี — งานแรกที่น่าจะต้องทำคือ commit scaffold ชุดนี้ขึ้น feature branch แล้วเปิด PR

### เอกสารบังคับ อ่านก่อนเริ่มทุกครั้ง (absolute path)

| ไฟล์ | ใช้ทำอะไร |
|---|---|
| `/Users/king_developer/Desktop/Project/samsoniteci4/outputs/diagrams/2026-08-09_ci3-to-ci4-upgrade-plan_v3.md` | charter หลัก: roadmap Phase 0-7, WP ทั้งหมด, §9 Verification Gate, §14 Acceptance Criteria 210 ข้อ, §17-18 assurance/process, §19 Evidence-First RCA, §20 Point evidence, §21 Function disposition, §22 Docker isolation |
| `/Users/king_developer/Desktop/Project/samsoniteci4/outputs/diagrams/2026-08-09_ci3-to-ci4-upgrade-plan_work-history_v1.md` | ประวัติการเปลี่ยนแผน + before/after evidence contract |
| `/Users/king_developer/Desktop/Project/samsoniteci4/outputs/reference/2026-08-23_wp00c-closure-package_v1.md` | สถานะ gate ล่าสุดของ WP-00C |
| `/Users/king_developer/Desktop/Project/samsoniteci4/outputs/reference/2026-08-22_wp00c-remaining-assessment_v1.md` | **สถานะ ณ 08-22 ตัวเลขล้าสมัย** (เขียน `2/53`) — ถูกแทนที่โดย closure package `51/53` ของ 08-23; ใช้เอกสารนี้เฉพาะ execution order และรายละเอียด case ห้ามใช้ตัวเลขสถานะ และห้ามรัน 43 cases ที่ PASS ไปแล้วซ้ำ |
| `/Users/king_developer/Desktop/Project/samsoniteci4/outputs/reference/2026-08-21_wp00c-execution-runbook_v1.md` | ขั้นตอนรัน WP-00C |
| `/Users/king_developer/Desktop/Project/samsoniteci4/outputs/reference/2026-08-21_ci3-source-repin-pr3_v1.md` | CI3 source pin + business decision เรื่อง Report Tracking |
| `/Users/king_developer/Desktop/Project/samsoniteci4/outputs/reference/2026-08-22_ci3-open-gaps-disposition_v1.md` | disposition ของ gap: IDOR, reset token, mailer, coverage |
| `/Users/king_developer/Desktop/Project/samsoniteci4/outputs/reference/2026-08-23_wp00c-benchmark-manifest_v1.md`, `.../2026-08-23_wp00c-provisional-nfr-baseline_v1.md` | benchmark manifest และ NFR baseline ชั่วคราว |
| `/Users/king_developer/Desktop/Project/samsoniteci4/docs/ci4-scaffold.md` | วิธีรัน CI4 scaffold ที่มีอยู่แล้ว (compose, migrate, ports, key) |
| `/Users/king_developer/Desktop/Project/samsoniteci4/outputs/diagrams/2026-08-22_function-disposition-evidence_v3.md` | Function inventory: live `1165/1165`, retired `247` |

### สถานะจริง ณ 2026-08-23 (ห้ามสมมติใหม่ ตรวจซ้ำก่อนใช้)

- **Phase 0**: WP-00C ยัง `OPEN 51/53` — closure gate exit `1`; `51 PASS`, `2 BLOCKED`, `0 FAIL`; approval records ครบ `149/149`
  - `RPT-EDGE-001` และ `PERF-CI3-001` `BLOCKED` ทั้ง 3 รอบ เพราะขาด **input จากมนุษย์**: approved volume profile, request mix, p50/p95 + memory + query-count/plan budget, environment manifest
  - งานอื่นของ Phase 0 (source inventory, DB baseline, DB foundation, point/function baseline) มีหลักฐานสะสมอยู่ใน `outputs/reference/` และ `evidence/` — ตรวจสถานะจริงจากไฟล์ก่อนถือว่าปิดแล้ว
- **Phase 1**: scaffold ทั้งชุด commit แล้วบน PR #9 (`feature/ci4-scaffold-wp00c-closure` -> `develop`) — CI `repository-safety` เขียวบน HEAD; pin `CodeIgniter 4.7.4`, `PHP 8.5.7`, `PHPUnit 11.5.56`
  - มี Report Tracking vertical slice, authorization filter, CI4-owned authentication แล้ว; **business parity ยังไม่ครบ**
  - ปิดบางส่วนแล้ว (2026-08-23): WP-01A assertion mysqli/mysqlnd + `composer check-platform-reqs`; WP-01F `DBDebug => ENVIRONMENT !== 'production'` + guard; WP-01G replacement-coverage test 174 mapped routes; WP-01I ledger ผูกเข้า `ci-check.sh` (skip เมื่อไม่มี CI3 checkout); WP-01D `scripts/ci4-web-boundary-check.sh` + evidence (`outputs/reference/2026-08-23_wp01d-web-boundary-evidence_v1.md`, production topology รอ BLK-008); WP-01J `db/dbctl.sh ci4-port-preflight` (read-only allocator 18405-18419)
  - WP-01E ปิดแล้ว (user อนุมัติ 2026-08-23): branch protection `develop`/`main` required check `repository-safety` + `enforce_admins`, เพิ่ม `phpstan` level 5 + baseline เข้า gate ทั้งสอง branch, image freshness guard เทียบ `composer.lock` (`outputs/reference/2026-08-23_wp01e-quality-gate-evidence_v1.md`)
  - Timestamp contract ตัดสินแล้ว (user เลือก parity): CI4 เก็บ `Asia/Bangkok` เหมือน CI3 — `appTimezone` เป็นแหล่งเดียว, `gmdate`/`DateTimeZone('UTC')` ถูกแทนหมด, ตรึงด้วย `TimezoneContractTest` + audit PASS (`outputs/reference/2026-08-23_timezone-parity-evidence_v1.md`); follow-up LOW: ตั้ง `default-time-zone='+07:00'` ที่ MariaDB target ใน Gate 1D
  - ค้างรอ input: WP-01H (รอ WP-00N registry ลงนาม), WP-00C 2 cases (user แจ้งจะเตรียม volume profile + budget ให้)
- **Phase 2-6**: หลาย slice merge เข้า `develop` แล้ว (ยืนยันจาก `git log --oneline --merges develop`); **Phase 7 ยังไม่เริ่ม**
  - Phase 2 (public web): public tracking (PR #19), contact/email validation (PR #14)
  - Phase 3 (session/authz/master/reset/view): session contract (PR #13), authorization + role 3 write (PR #11, #18), master data (PR #17, #20, #22), password reset + audit (PR #12); WP-03E view boundary เหลือ `app/Views/layout.php` (task ปัจจุบัน) + paired visual comparison ที่รอ dependency
  - Phase 4 (order/notifications): trackID branch-prefix (PR #24), order lifecycle complete flow (PR #28) + form parity (PR #31), SMS transport (PR #30)
  - Phase 5 (import): xlsx import (PR #27), import file retention (PR #26)
  - Phase 6 (report/export): report parity (PR #29) + fixes (PR #32), export memory ceiling (PR #25), search parity (PR #33)
  - Phase 7: ยังไม่เริ่ม
- CI3 source อยู่คนละ repo: `metrodiesign/samsoniteci3` pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`, image `samsonitetracking-ci3:ee1c95e` — หลักฐานที่ผลิตบน pin เก่า `8dad4e3` ต้อง rerun

### Gotcha ที่หาเองไม่ได้และพลาดแล้วเสียงานทั้งรอบ

1. **Docker isolation (§22, WP-01J, BLK-017)**: เครื่องนี้มี Compose project ของงานอื่นอย่างน้อย 7 ตัว — ใช้ `-p` และ `-f` เจาะจงเสมอ, publish เฉพาะ `127.0.0.1`, ห้าม `prune`/`stop`/`rm`/`restart` แบบ global, เก็บ before/after diff ของ container/network/listener ทุกครั้งที่แตะ Docker
2. **Port**: CI3 = `127.0.0.1:18404`, CI4 = `127.0.0.1:18405` — ตรวจซ้ำทันทีก่อน `up`; ชนแล้วเลือกเลขต่ำสุดที่ผ่านจาก `18405-18419` ห้ามหยุด/แก้ owner เดิม
3. **ห้ามรัน CI4 migrations ลงฐาน CI3 rehearsal** — จะทำ WP-00C source checksum เปลี่ยนและล้ม baseline; CI4 ใช้ฐานแยก `samsonite_ci4` ผ่าน `db/dbctl.sh ci4-db-bootstrap`
4. **Synthetic data เท่านั้น** — ห้ามข้อมูลลูกค้าจริง, ห้าม PII ใน repo, ห้ามเปิด provider จริง (SMTP/SMS) ใช้ loopback stub; runtime ตอนนี้ปิด `mail()`, `allow_url_fopen=off`, `sendmail_path=/bin/false` — ห้ามปลดเพื่อให้ test ผ่าน
5. **ห้ามสร้าง approval, NFR budget หรือผลทดสอบแทนมนุษย์** — case ที่ต้องรออนุมัติให้รายงาน `BLOCKED` พร้อมระบุ input ที่ต้องการ
6. **Known CI3 defects ห้ามย้ายเข้า CI4 เป็น parity**: Status/New Order replay duplicate writes, shared temp batch ข้ามผู้ใช้, missing CSRF, New Order redirect warning — ต้องแก้ใน CI4 พร้อม `CORRECT_AND_REBASELINE` record
7. **Report Tracking**: CI4 มีหน้าเดียว; ห้ามสร้าง route/page/menu ชื่อ `Test`; ต้องคง corrected status-filter contract (empty/single/multiple/malformed) ห้ามคืน defect `IN ()` เดิม
8. **Import ต้องใช้ actor ที่มี branch** (`wp00c-a`, branch `1`) เพราะ admin มี `BranchID=NULL` — ห้ามแก้ source เพื่อให้ admin import ผ่าน ยังไม่มี decision ให้ขยายสิทธิ์
9. **ห้ามเปิด Auto Routing Legacy** — ใช้ explicit route + verb เท่านั้น
10. **DB platform conversion เป็น release แยกจาก CI4 cutover** — ผ่าน Gate 1D ก่อนจึงเริ่มย้าย write ownership; หนึ่ง route มี write owner ระบบเดียว ห้าม double write

## Scope

- **In**: `/Users/king_developer/Desktop/Project/samsoniteci4` ทั้ง repo — `app/`, `public/`, `tests/`, `scripts/`, `db/`, `docs/`, `outputs/`, `evidence/`, `compose.yaml`, `Dockerfile.ci4`, `composer.json|lock`, `.env.example`
- **Out**:
  - repo/ไฟล์นอก path ข้างบน (repo อื่น, `~/.claude`, global config) — ต้องถามก่อนทุกครั้ง
  - Docker resource ของ Compose project อื่นบนเครื่องนี้
  - แก้ CI3 source (คนละ repo `metrodiesign/samsoniteci3`) — ต้องการแก้ให้เสนอเป็น PR แยกและอัปเดต pin
  - redesign UX/UI, business process, authentication product ใหม่, business-schema redesign, data cleansing ที่ไม่ได้เกิดจาก target-stack conversion
  - `.env` และไฟล์ secret จริง

## Constraints

- ตอบ user เป็นภาษาไทย; เอกสาร `.md` ที่สร้างใหม่เขียนไทย ไม่มี emoji (commit message / PR title-body / code comment ตาม repo convention = อังกฤษ)
- **Git**: ห้าม push ตรงเข้า `main` และ `develop` ทุกกรณี, ห้าม force push, ห้าม commit โดยไม่ผ่าน review — ทำงานบน feature branch แล้วเปิด PR
- **Secrets**: ห้าม commit secret ทุกชนิด, ห้าม hardcode credential, ห้าม log token/PII; `.env`/`.env.*` อยู่ใน `.gitignore` แล้ว commit ได้แค่ `.env.example`
- **Destructive ops**: `DROP`/`DELETE`/`TRUNCATE`/`rm -rf`/`git reset --hard` ต้องยืนยันเป้าหมายและมี backup/rollback ก่อน
- **Evidence-First (§19)**: ทุก work item ต้องมี before evidence, root cause จากหลักฐานจริง, change, after evidence, actual impact, สถานะปิด — ห้ามเดา; ค่าที่ไม่เคย capture ให้เขียน `UNKNOWN` ไม่ใช่ประมาณเอา
- คำตัดสินฝั่ง PASS ที่อ้างพฤติกรรม runtime ต้องมีผลรันจริงประกอบ กลไกที่ไม่เคยรันไม่นับเป็นหลักฐาน
- Dependency ใหม่ต้องตรวจ license + maintenance ก่อนเพิ่ม; `composer.lock` commit เสมอ; ห้าม pin ลอย `latest`
- ห้ามปล่อย `.only`/`.skip` ค้างใน test; coverage ห้ามต่ำกว่า baseline
- ไม่สร้าง abstraction, generic repository หรือ event bus ที่ยังไม่มี use case จริง

## Success criteria

รันได้จริงทุกข้อ เก็บ output เป็นหลักฐาน:

```bash
cd /Users/king_developer/Desktop/Project/samsoniteci4

# 1) repository gate (shell syntax, composer validate/audit, spark routes, phpunit, schema/PII/secret/transport/Compose/route allowlist)
bash scripts/ci-check.sh            # ต้อง exit 0

# 2) CI4 runtime
composer install && php spark --version   # ต้องขึ้น CodeIgniter v4.7.4
php spark routes                          # ต้องมี GET health -> Health::index และไม่มี auto-routing legacy
composer test                             # phpunit ตาม phpunit.xml.dist ต้องเขียว
curl --fail-with-body http://127.0.0.1:18405/health

# 3) WP-00C closure gate
python3 scripts/wp00c-closure.py \
  --catalog tests/wp00c/catalog.json \
  --round evidence/wp00c/round-1.json \
  --round evidence/wp00c/round-2.json \
  --round evidence/wp00c/round-3.json \
  --approvals evidence/wp00c/approvals.json
# เป้าหมายสุดท้าย: WP-00C 53/53, FAIL/BLOCKED/NOT_RUN = 0, exit 0

# 4) function disposition gate
php scripts/check-function-disposition.php    # missing/duplicate/hash/ID mismatch = 0

# 5) concurrency
bash scripts/ci4-concurrency-check.sh
```

เกณฑ์ปิดงานระดับโครงการ:

- WP-00C `53/53` PASS, approval ครบทุก case
- Acceptance Criteria `210/210` ใน §14/§17 ผ่าน
- Point registry: `D=R`, orphan/unowned/duplicate point = `0`
- Function ledger: `RETIRE_PROPOSED` / `UNKNOWN_BLOCKED` / orphan target = `0`
- Gate 1D (DB foundation) ลงนาม, CI3 parity 100%, P0/P1/data diff = `0`
- Shadow comparison (WP-07B): unapproved functional หรือ user-visible difference = `0`
- Docker non-interference: container/network/volume/listener ของ project อื่น diff = `0`

## ลำดับงาน

1. ตรวจสถานะจริงก่อน: รัน gate ทั้ง 5 ชุดข้างบน แล้วเทียบกับ closure package/remaining assessment — รายงานส่วนต่าง
2. ปิดค้าง Phase 0: WP-00C 2 cases ที่ `BLOCKED` — ขอ input จากมนุษย์ (volume profile, budgets, request mix, environment manifest) แล้วรัน 3 รอบ deterministic เก็บ hash
3. Phase 1 (WP-01A ถึง WP-01J): ปิด runtime/framework/config/web boundary/quality gate/runtime policy/route-filter skeleton/point-function evidence automation/Docker isolation ให้ครบ พร้อม required CI check ที่ block merge จริง
4. Phase 2-3: public tracking, contact/email, session, authorization, master data, password reset, view boundary
5. Phase 4-6: tracking ID atomic, order lifecycle, notifications, PhpSpreadsheet import, batch isolation, file storage, report queries, export
6. Phase 7: staging rehearsal, shadow comparison, cutover, stabilization, CI3 retirement, success-proof closure, function retirement closure

แต่ละ WP: อ่าน exit criteria ใน charter -> ทำ -> รัน gate -> เขียน evidence file ใน `outputs/reference/` หรือ `evidence/` -> commit บน feature branch -> เปิด PR

## Output contract

รายงานกลับทุกครั้งด้วยรูปแบบนี้ ไม่ต้องเล่ากระบวนการ:

1. **WP ที่แตะรอบนี้** — ID + ชื่อ + สถานะ (`PASS` / `OPEN` / `BLOCKED`)
2. **หลักฐาน** — คำสั่งที่รัน + บรรทัดผลลัพธ์ชี้ขาด + absolute path ของ evidence file (ไม่ต้อง dump log ยาว)
3. **Before/after impact** — สิ่งที่เปลี่ยน และสิ่งที่ยืนยันว่าไม่เปลี่ยน
4. **ค้าง/บล็อก** — ระบุ input ที่ต้องการจากมนุษย์ให้เจาะจง (ใคร role ไหน ค่าอะไร)
5. **ขั้นถัดไป** — WP ถัดไปตาม dependency

ปิดท้ายด้วยบรรทัดเดียว: `STATUS: DONE | BLOCKED | NEEDS-INPUT`
