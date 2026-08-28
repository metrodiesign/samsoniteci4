# TPL-00/TPL-01 foundation evidence

หลักฐานนี้รวม checkpoint Task 1–7 สำหรับ foundation strict CI3 preservation. รอบนี้ไม่รัน Tasks 1–6 หรือ Browser interaction matrix ซ้ำตามข้อกำหนด handoff.

## Checkpoints

| Scope | Evidence | Verdict |
|---|---|---|
| Task 1 inventory denominator | `09e3525`, Task 1 report | PASS, 108 denominator |
| Task 2 query adapter | `3ca811f`, `053c83c`, corrective report | PASS |
| Task 3 route fixture | `3844d5c`, Task 3 report | PASS |
| Task 4 access denial | `404c077`, `f4ffe76`, corrective report | PASS |
| Task 5 admin shell | `898d56e`, Task 7 Browser carry-forward | PASS |
| Task 6 shared assets | `6799684`, Task 6 report | PASS |
| Task 7 order layout | `64d3022`, exact candidate `03911374b1d375d8a9976b854f7de6947b6e898c` | PASS |

## Final Task 7 gates

- Exact archive PHPUnit: `OK (376 tests, 7225 assertions)`.
- Exact archive PHPStan: `No errors` with `--memory-limit=1G`.
- Browser final report: carry-forward PASS; only post-matrix delta was `bg-form.png`, re-probed PASS before exact automated gate.
- Candidate scope: 23 committed paths, one `Routes.php` preview-upload hunk, no unrelated staged path.
- Raw CI3 `public/assets/js/browse/script.js` has 16 historical space-before-tab lines. Formatting would violate byte preservation; excluded from whitespace verdict as pinned baseline.

## Carry-forward constraints

- `composer test` and `scripts/ci-check.sh` were not rerun because they rerun Tasks 1–6, prohibited by handoff.
- Browser pair and interaction matrix were not rerun, prohibited by handoff. Existing Task 7 final Browser report is authority.
- Working tree remains dirty with WP03H–M work outside foundation; no such path is part of Task 2, Task 4, or Task 7 checkpoints.

## Output contract

STATUS: DONE
FUNCTIONAL_PARITY: PASS
TEMPLATE_PARITY: PASS
VISUAL_PARITY: PASS
UNAPPROVED_TEMPLATE_CHANGES: 0
UNAPPROVED_DEPENDENCY_UPGRADES: 0
