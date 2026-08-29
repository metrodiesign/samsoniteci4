## Task 8: Foundation full verification

**Files:**

- Modify: `outputs/reference/2026-08-27_tpl00-tpl01-foundation-evidence_v1.md`

**Interfaces:**

- Consumes: Task 1-7 checkpoint results
- Produces: foundation verdict ที่แยก functional, template, DOM, CSS, JavaScript และ visual axes

- [ ] **Step 1: รัน static/full automated gates**

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
composer test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
bash scripts/ci-check.sh
git diff --check
```

Expected: exit 0 ทุก command; MariaDB transient failure ต้อง rerun isolated concurrency gateเพื่อเก็บหลักฐานก่อนตัดสิน

- [ ] **Step 2: รัน browser pair สำหรับ shared shells**

ใช้ CI3 `18404` และ rebuilt CI4 `18405` ที่ source identity เดียวกับ checkpoint

Capture admin shell, order add/edit, public shell และ standalone auth ที่ `1440x900` กับ `390x844`, DPR 1

- [ ] **Step 3: เก็บ DOM/network/interaction evidence**

ห้าม normalize class, hierarchy, text, field, menu หรือ visible data

ถ้า fixture state ยัง drift ให้ verdict browser เป็น `BLOCKED` และห้ามใช้ stale screenshots

- [ ] **Step 4: สรุป Output Contract**

เอกสาร evidence ต้องลงท้าย:

```text
STATUS: DONE | BLOCKED | NEEDS-INPUT
FUNCTIONAL_PARITY: PASS | FAIL | NOT-VERIFIED
TEMPLATE_PARITY: PASS | FAIL | NOT-VERIFIED
VISUAL_PARITY: PASS | FAIL | NOT-VERIFIED
UNAPPROVED_TEMPLATE_CHANGES: <number>
UNAPPROVED_DEPENDENCY_UPGRADES: <number>
```

- [ ] **Step 5: audit, verify และ review**

BLOCKING finding เชิงวิเคราะห์ต้องผ่าน skeptic ตาม pipeline rules ผลรันจริงที่ RED เข้า rework โดยตรง

- [ ] **Step 6: checkpoint commit**

เมื่อทุก automated gate ผ่านและ browser blockerถูกบันทึกตามจริง:

```text
wip(strict-template): tpl00-tpl01 foundation passed
```

## หลัง Foundation

สร้าง implementation plan แยกสำหรับ TPL-02 ถึง TPL-09 ตาม denominator และ file mapping ใน design spec แต่ละแผนต้องอ่าน CI3 source ทั้งไฟล์ก่อนแก้ CI4 target และใช้ per-page failing comparator ก่อน implementation
