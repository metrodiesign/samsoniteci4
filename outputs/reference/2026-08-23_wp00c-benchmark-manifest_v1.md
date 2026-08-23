# WP-00C Benchmark Manifest

Manifest นี้บันทึก workload ที่ผู้ใช้ freeze สำหรับเปรียบเทียบ CI3 กับ CI4. ใช้ synthetic data เท่านั้น; fixture ยัง unsealed จึงยังห้ามประกาศ NFR PASS.

## Dataset profile

| Field | Value |
|---|---:|
| Users | 5,000 |
| Branches | 20 |
| Orders | 100,000 |
| Status logs | 400,000 |
| Report rows | 100,000 |
| Export rows | 100,000 |
| Date range | `2024-01-01..2026-08-23` |
| Branch distribution | uniform: 250 users, 5,000 orders, 20,000 status logs/branch |
| Status-log density | 4 logs/order |
| File volume | 0 |
| Generator SHA-256 | `cae0447f4dc86eb235fbdbbfb1a0094c520b5273abe57b5ac7a889baa07e8095` |
| Fixture SHA-256 | `f34ab449140b6989b27ba1db74ababd2305d3163ac545bdb7f0ca02dec7e1f2b` |

Generator ต้อง deterministic: fixed seed, UTC timestamps, explicit collation และ SQL compatible กับ `db/local-schema-only.sql`. ห้ามใช้ production dump หรือ PII.

## Request mix and metrics

| Flow | Method | Endpoint | Weight |
|---|---|---|---:|
| Public tracking | GET | `/tracking?tracking_id={track_id}` | 30% |
| Login | POST | `/loginMe` | 10% |
| Order list | GET | `/orders?status={1..8}&page={n}&search={term}` | 20% |
| Create order | POST | `/orders/new` | 10% |
| Report HTML | GET | `/reportsummary/0?sdate={date}&edate={date}` | 20% |
| Export | GET | `/reports/tracking/export?sdate={date}&edate={date}` | 10% |

Concurrency `40`, warm-up `60s`, measured duration `300s`, 3 clean runs per runtime, restore fixture before every run. Login creates distinct session/CSRF token per virtual user; POST never replays a token or order ID across users.

| Metric | Definition |
|---|---|
| p50/p95 | nearest-rank percentile of completed measured end-to-end HTTP duration |
| Throughput | completed requests / 300s, total and per flow |
| Memory | peak web/DB container RSS plus export PHP request peak |
| 5xx | HTTP `500..599` / completed requests × 100 |
| Query count | statements/request, median and p95 per flow; excludes connect/observer queries |
| Export completion | request start through complete body read; verify 100,000 rows, headers, filename, totals |

Use monotonic wall clock. Report timeout/network failures separately; do not silently follow redirects. Baseline targets are in `/Users/king_developer/Desktop/Project/samsoniteci4/outputs/reference/2026-08-23_wp00c-provisional-nfr-baseline_v1.md`.

## Environment manifest

| Field | Value | Verification |
|---|---|---|
| CI3 image ID | `sha256:b1d2dc80ed680305bad8ad2769d56d483ffa976e8c3041cf6595234071851ba8` | local inspect matches |
| CI4 image ID | `sha256:cdd0bb6262ecff92eb9ce8f24ec72862e351ae41c06e16e5c0e87e7243ceae68` | local inspect matches |
| CI4 PHP base | `php:8.5.7-cli-bookworm@sha256:1ff2cdf2754bcac61128bfda455d418639c0f45a8b1583b4b90f9873a2ba1368` | Dockerfile matches |
| MariaDB image | `mariadb:11.4.12@sha256:67873d30a17f6a9c331f06363b2fa15f38abca415529966d67c84f87f82439fe` | local repo digest matches |
| PHP extensions | `intl`, `mbstring`, `mysqli`, `zip` | Dockerfile matches |
| DB limit | 4 CPU, 2GiB | compose matches |
| CI3 web limit | 2 CPU, 1GiB | compose matches |
| CI4 web limit | 2 CPU, 1GiB | `tests/wp00c/benchmark-compose.override.yaml` |
| Storage/topology | named Docker volume; one web plus MariaDB on project backend network | compose matches |
| Session mode | CI3 `ci_session`; CI4 `writable/session` | supplied workload constraint |
| Schema/index version | `db/local-schema-only.sql` SHA-256 `1ad6c783aa50e26f9c5d6919b1f7df0b34755f34d5d6a0b8ac9975e8823971aa` | local hash matches |
| Config SHA-256 | `1288e0698c9988515958366755bfed269aa21809d3c2096d2138ad3bede85c3d` | local NUL-delimited hash matches |

MariaDB contract: `utf8mb4`, `utf8mb4_general_ci`, `NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION`, InnoDB `DYNAMIC`, 1GiB buffer pool, 512MiB log file, 64MiB max packet, `skip-name-resolve`. `innodb-flush-log-at-trx-commit=2` is rehearsal-only, not production likeness.

## Seal gate

1. Generator, fixture hashes, counts, CI3 image ID, and CI4 CPU/RAM limit are sealed.
2. Freeze schema, image, config, fixture, and workload across CI3/CI4 runs.
3. Run three clean rounds; retain metric, query-plan, and semantic-hash evidence.

Approval declaration received from `Software Engineer` on `2026-08-23` for `RPT-EDGE-001` and `PERF-CI3-001`. It applies after this manifest is sealed.

Fixture files: `/Users/king_developer/Desktop/Project/samsoniteci4/evidence/wp00c/benchmark-fixture.sql`, `/Users/king_developer/Desktop/Project/samsoniteci4/evidence/wp00c/benchmark-fixture.json`. Generator: `/Users/king_developer/Desktop/Project/samsoniteci4/tests/wp00c/generate_benchmark_fixture.py`.

STATUS: FIXTURE SEALED, BENCHMARK NOT RUN
