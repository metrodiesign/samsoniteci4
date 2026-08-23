# CI4 Report Tracking After Evidence

เอกสารนี้บันทึก CI4 after-comparator สำหรับ Report Tracking ด้วย synthetic WP-00C fixture. ครอบคลุม implementation, security controls, deterministic execution และ OpenAI Browser anonymous flow.

## Verdict

**CI4 Report Tracking after-parity: PASS `2/2`.** Case `RPT-TRACKING-TEST-001` และ `RPT-TRACKING-001` ผ่าน target comparator แล้ว.

**WP-00C full closure: NOT COMPLETE `2/53`.** อีก `51` cases ยังไม่มี CI4 implementation/comparator และ approval ครบตาม Definition of Done.

## Identity

| Field | Value |
|---|---|
| CI3 source pin | `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` |
| CI3 database | isolated rehearsal, `31` tables |
| CI4 image | `samsonitetracking-ci4:4.7.4-php8.5.7` |
| CI4 image ID | `sha256:cdd0bb6262ecff92eb9ce8f24ec72862e351ae41c06e16e5c0e87e7243ceae68` |
| CI4 database | `samsonite_ci4`, `36` tables |
| Fixture | `WP00C-FIXTURE-2026-08-21-v1` |
| Comparator | `scripts/wp00c-report-tracking.py --target ci4` |

## Comparator matrix

| Scenario | Expected ordered tracks | Result |
|---|---|---|
| Empty status | `009` ถึง `001` | PASS, 9 rows |
| Single status `2` | `009,002` | PASS, 2 rows |
| Multiple status `2,3` | `009,003,002` | PASS, 3 rows |
| Malformed status | `009` ถึง `001` | PASS, unsafe filter omitted |
| Exact search | `004` | PASS, 1 row |
| Date `03–05/08/2026` | `005,004,003` | PASS, 3 rows |
| Route branch 1 | `006` ถึง `001` | PASS, 6 rows |
| Route branch 2 | `009,008,007` | PASS, 3 rows |
| Session branch 1 | `006` ถึง `001` | PASS, 6 rows |
| Session branch 2 | `009,008,007` | PASS, 3 rows |
| Page parameter `25` | branch 1, first No `26` | PASS, no real pagination retained |
| Legacy Test route | root และ `Order/` aliases | PASS, HTTP 404 |

Calculated values ของ `WP00C-TRACK-007` ผ่าน: TotalDay `2`, CMGTotalDay `0`.

## Determinism and side effects

รัน CI4 comparator 3 รอบด้วย fixture และ request order เดียวกัน.

| Round | Verdict | Requests | Semantic SHA-256 |
|---:|---|---:|---|
| 1 | PASS | 11 | `73d36d4644a0ccf1426229b525d056fd8c94c3ead0a1625e6c4906525f8a069b` |
| 2 | PASS | 11 | `73d36d4644a0ccf1426229b525d056fd8c94c3ead0a1625e6c4906525f8a069b` |
| 3 | PASS | 11 | `73d36d4644a0ccf1426229b525d056fd8c94c3ead0a1625e6c4906525f8a069b` |

- **Normalization**: ตัดเฉพาะ per-response CSRF-dependent `body_sha256` ก่อนสร้าง semantic hash.
- **Database**: report requests เปลี่ยน target tables `0/36`; runner คืน password hashes, login history และ rate-limit buckets ตรง initial checksums.
- **Isolation**: CI4 migrations อยู่ฐาน `samsonite_ci4`; CI3 rehearsal คง `31` tables.
- **Bootstrap**: source fixture verify ผ่าน `116` rows; fresh clone probe ได้ `31` legacy tables, cleanup สำเร็จ และ target เดิม `36` tables ไม่ถูกเขียนทับ.
- **Outbound**: email/SMS calls `0`.

## Authentication and security

| Control | Evidence |
|---|---|
| Explicit login/session routes | `/login`, POST `/loginMe`, POST `/logout`, `/dashboard` |
| Session fixation | regenerate session ID หลัง credential verification |
| Credential ownership | one-time legacy identity import; login อ่าน CI4-owned password hash เท่านั้น |
| Reset compatibility | reset hash ไม่ถูก legacy login ย้อนทับ |
| Brute-force control | fixed-window limit ต่อ IP และต่อ IP+identity |
| CSRF | built-in CSRF filter บน login, logout และ report POST |
| Branch isolation | non-admin route branch mismatch ตอบ 404 |
| SQL injection | status parser + Query Builder binding; malformed status ไม่เข้า `whereIn` |
| XSS | rendered DB/input values ผ่าน context HTML escaping |

## Automated verification

```text
BusinessParityHttpTest: 15 tests, 105 assertions
Full PHPUnit: 75 tests, 393 assertions
Repository CI gate: PASS
PHP lint: 108 files, 0 syntax errors
Composer audit: no security advisories
```

## OpenAI Browser

| Check | Result |
|---|---|
| Reload `/dashboard` without active session | redirect `/login` |
| Login DOM | heading, username, password และ submit controls present |
| Console warnings/errors | `0` |
| Authenticated dashboard/report interaction | pending user sign-in in explicit OpenAI Browser session |

## Remaining closure

CI4 after-parity ปิดเฉพาะ Report Tracking 2 cases. WP-00C ยังต้องผ่านอีก `51` cases, explicit-route reconciliation, deterministic 3-round evidence และ required Business/QA/Security/DBA approvals ก่อนประกาศ `53/53`.
