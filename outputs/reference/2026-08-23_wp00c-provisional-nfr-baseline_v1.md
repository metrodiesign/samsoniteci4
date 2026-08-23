# WP-00C Provisional NFR Baseline

เอกสารนี้ freeze ค่าเริ่ม benchmark ตามคำสั่งผู้ใช้และ upgrade plan. สถานะ `PROVISIONAL`, ไม่ใช่ final approved NFR หรือ production-like environment manifest.

## Baseline metrics

| Metric | Baseline | Pass rule |
|---|---:|---|
| Public/login/list p95 | ≤ 2s | CI4 regression เทียบ CI3 ≤ 10% ที่ peak profile เดียวกัน |
| Mutation p95 | ≤ 3s | รวม durable delivery intent/audit |
| Report HTML p95 | ≤ 10s | dataset/filter เดียวกัน, totals exact |
| Export completion | ≤ 120s | maximum approved rows, filename/cells/totals exact |
| Memory | web ≤ 256MiB/request; export ≤ 512MiB/job | ไม่มี OOM หรือ unbounded growth |
| Acceptance 5xx | 0 | ทุก required run |
| Stabilization 5xx | ≤ 0.1% | ไม่เกิน approved CI3 baseline |
| Concurrency | approved peak ×2 | duplicate/lost update/cross-user contamination/double-send = 0 |
| DB restore RTO | ≤ 120m | timed restore และ integrity queries ผ่าน |

Source: `/Users/king_developer/Desktop/Project/samsoniteci4/outputs/diagrams/2026-08-09_ci3-to-ci4-upgrade-plan_v3.md:1163`.

## Case execution baseline

| Case | Workload | Required evidence | Result rule |
|---|---|---|---|
| `RPT-EDGE-001` | HTML/export reports: empty, bad date, cross-branch, large search, 100-row pagination | p50/p95, memory, query count, `EXPLAIN` plan, exact totals, unauthorized rows `0` | Every metric stays in baseline; three matching PASS hashes |
| `PERF-CI3-001` | public tracking, login, list, mutation, report, export | p50/p95, throughput, memory, 5xx, query count/plan, restore time | Same dataset/environment/formula for CI3 and CI4; three matching PASS hashes |

## Environment boundary

| Fact | Value | Status |
|---|---|---|
| CI4 image | `samsonitetracking-ci4:4.7.4-php8.5.7` | local compose fact |
| CI4 PHP image | `php:8.5.7-cli-bookworm` digest pinned | local Dockerfile fact |
| DB limit | 4 CPU, 2GiB | local compose fact |
| CI3 web limit | 2 CPU, 1GiB | local compose fact |
| Workload counts, request mix, image IDs, DB/PHP config, topology, session mode and config hash | `docs/benchmark-manifest.md` | frozen workload manifest |
| Generator SHA, fixture SHA and CI4 web CPU/RAM limit | `PENDING` | mandatory before executable NFR run |

Local compose facts do not prove production-like identity. Synthetic `116` rows remain functional evidence only, not capacity profile.

## Closure input still required

1. Create deterministic sanitized/synthetic generator and seal its SHA-256 plus fixture SHA-256.
2. Fix CI4 web CPU/RAM limit; record CI3 registry digest when benchmark uses remote registry.
3. Business, Engineering, QA, Security, DBA, Operations approve sealed values; agent then runs three deterministic rounds.

STATUS: PROVISIONAL
