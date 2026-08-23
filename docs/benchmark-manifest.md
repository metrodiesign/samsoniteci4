# Benchmark Manifest

Manifest สำหรับเปรียบเทียบ CI3 กับ CI4 บน workload เดียวกัน. ค่า workload ถูก freeze แล้ว; ยังห้ามประกาศผล NFR จน seal fixture และบันทึก hash ครบ.

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
| Branch distribution | uniform, 250 users, 5,000 orders และ 20,000 status logs ต่อ branch |
| Status-log density | 4 logs/order |
| File volume | `0` — workload นี้ไม่มี upload/download fixture |
| Generator SHA-256 | `PENDING` — generator ยังไม่มีใน repository |
| Fixture SHA-256 | `PENDING` — ต้อง hash SQL dump หลัง generate และก่อนทุก run |

Generator ต้อง deterministic: fixed seed, UTC timestamps, explicit collation และ emit SQL ตาม `db/local-schema-only.sql`. ห้ามใช้ dump production หรือ PII.

## Request mix

| Flow | Method | Endpoint | Weight |
|---|---|---|---:|
| Public tracking | GET | `/tracking?tracking_id={track_id}` | 30% |
| Login | POST | `/loginMe` | 10% |
| Order list | GET | `/orders?status={1..8}&page={n}&search={term}` | 20% |
| Create order | POST | `/orders/new` | 10% |
| Report HTML | GET | `/reportsummary/0?sdate={date}&edate={date}` | 20% |
| Export | GET | `/reports/tracking/export?sdate={date}&edate={date}` | 10% |
| Total |  |  | 100% |

- **Concurrency**: `40` virtual users.
- **Warm-up**: `60s`, metric ไม่รวมช่วงนี้.
- **Measured duration**: `300s`, หลัง warm-up.
- **Run count**: `3` clean runs ต่อ CI3 และ CI4, restore fixture ก่อนแต่ละ run.
- **Authenticated flows**: login สร้าง session และ CSRF token ต่อ virtual user; POST ห้าม replay token หรือ order identifier ข้าม user.

## Metric formula

| Metric | Formula |
|---|---|
| p50 / p95 | nearest-rank percentile ของ end-to-end HTTP duration เฉพาะ measured requests ที่ response จบ |
| Throughput | completed HTTP requests / `300s`, report ทั้ง total และแยก flow |
| Memory | peak RSS ของ web container และ DB container, plus peak PHP request memory สำหรับ export |
| 5xx rate | count HTTP status `500..599` / completed HTTP requests × 100 |
| Query count | MariaDB statements per request, median และ p95 แยก flow; exclude connection setup และ benchmark observer queries |
| Export completion | request start ถึง response body read ครบ; verify `100,000` rows, headers, filename และ totals |

ใช้ wall-clock monotonic timer, ไม่ follow redirect โดยเงียบ, report network/timeout error แยกจาก HTTP 5xx. CI4 ผ่านเมื่อค่าตาม `outputs/reference/2026-08-23_wp00c-provisional-nfr-baseline_v1.md` และ regression เทียบ CI3 ไม่เกินเกณฑ์ในไฟล์นั้น.

## Production-like environment manifest

| Field | Value |
|---|---|
| CI3 image ID | `sha256:b1d2dc80ed680305bad8ad2769d56d483ffa976e8c3041cf6595234071851ba8` |
| CI4 image ID | `sha256:cdd0bb6262ecff92eb9ce8f24ec72862e351ae41c06e16e5c0e87e7243ceae68` |
| CI4 PHP base image | `php:8.5.7-cli-bookworm@sha256:1ff2cdf2754bcac61128bfda455d418639c0f45a8b1583b4b90f9873a2ba1368` |
| MariaDB image | `mariadb:11.4.12@sha256:67873d30a17f6a9c331f06363b2fa15f38abca415529966d67c84f87f82439fe` |
| PHP | `8.5.7`; `intl`, `mbstring`, `mysqli`, `zip` |
| DB limit | 4 CPU, 2 GiB RAM |
| CI3 web limit | 2 CPU, 1 GiB RAM |
| CI4 web limit | not set — must be fixed before comparison run |
| Storage | Docker named volume, local driver, MariaDB data at `/var/lib/mysql` |
| Topology | one CI3 or CI4 web container plus one MariaDB container on project-scoped backend network |
| Session mode | CI3 file session `ci_session`; CI4 file session under `writable/session` |
| Schema/index version | `db/local-schema-only.sql` SHA-256 `1ad6c783aa50e26f9c5d6919b1f7df0b34755f34d5d6a0b8ac9975e8823971aa` plus CI4 migrations at tested commit |
| Config SHA-256 | `1288e0698c9988515958366755bfed269aa21809d3c2096d2138ad3bede85c3d` |

MariaDB config: `utf8mb4`, `utf8mb4_general_ci`, `NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION`, InnoDB `DYNAMIC`, 1 GiB buffer pool, 512 MiB log file, 64 MiB max packet, `skip-name-resolve`. `innodb-flush-log-at-trx-commit=2` เป็น rehearsal-only setting, ไม่ใช่ production likeness.

`Config SHA-256` คำนวณจาก byte stream ของ filename NUL และ content ตามลำดับ: `compose.yaml`, `Dockerfile.ci4`, `app/Config/Database.php`, `app/Config/Session.php`, `db/local-schema-only.sql`. CI3/CI4 image ID เป็น local Docker content ID; ต้องบันทึก registry digest เพิ่มเมื่อ benchmark รันบน remote registry.

## Seal gate

ก่อน run ต้องเติม generator SHA, fixture SHA, CI4 web CPU/RAM limit และ CI3 registry digest ถ้ามี. หลังเติมค่า hash แล้วห้ามแก้ fixture, schema, image, config หรือ workload ระหว่าง CI3 กับ CI4.

STATUS: WORKLOAD FROZEN, FIXTURE UNSEALED
