# WP-01E Quality Gate Evidence (v1)

หลักฐานปิด WP-01E: required CI check ที่ block merge จริง + lint/static analysis ใน gate

## สรุปผล

| รายการ | ผล |
|---|---|
| Branch protection `develop` + `main` | ตั้งแล้ว: required check `repository-safety`, strict, `enforce_admins=true`, ห้าม force push/delete |
| php lint sweep (`php -l` ทั้ง `app/` + `tests/ci4/`) | PASS ทั้ง local และใน image |
| phpstan level 5 + baseline | `[OK] No errors` ทั้ง local และใน image |
| Gate exit code | 0 |

## Before

- `develop`/`main` ไม่มี protection เลย (`gh api .../protection` ตอบ `Branch not protected`) — merge ได้แม้ CI แดง
- gate ไม่มี lint และ static analysis (มีแค่ composer validate/audit + phpunit)

## Change

- ตั้ง branch protection ผ่าน `gh api PUT repos/metrodiesign/samsoniteci4/branches/{develop,main}/protection` (user อนุมัติ 2026-08-23) — ไม่บังคับ approving review เพราะ repo ผู้ดูแลคนเดียว approve PR ตัวเองไม่ได้
- เพิ่ม dev dependency `phpstan/phpstan ^2.2` (license MIT, maintained — ตรวจก่อนเพิ่ม, user อนุมัติ) + `phpstan.neon.dist` (level 5, ยกเว้น `app/Views` และ `app/Config/Kint.php`, constant stub ที่ `scripts/phpstan-bootstrap.php`) + `phpstan-baseline.neon` ล็อก 18 finding เดิม (ตรวจแล้วไม่มี bug จริง — เป็น type-narrowing nit และ defensive check ซ้ำ) error ใหม่หลังจากนี้ fail gate
- เพิ่ม `php -l` sweep + phpstan เข้า `scripts/ci-check.sh` ทั้ง branch local (vendor) และ branch docker image
- image freshness guard: เทียบ `cksum composer.lock` ใน image กับใน repo — lock เปลี่ยนแล้ว image rebuild อัตโนมัติ (แก้ class ปัญหา vendor เก่าค้างใน image ทั้ง `ci-check.sh` และ `ci4-concurrency-check.sh`)

## After (ผลรันจริง 2026-08-23)

```
$ gh api repos/metrodiesign/samsoniteci4/branches/develop/protection --jq '...'
{"admins":true,"checks":["repository-safety"],"force":false,"strict":true}

$ vendor/bin/phpstan analyse --no-progress --memory-limit=1G
 [OK] No errors

$ docker run --rm ... samsonitetracking-ci4:4.7.4-php8.5.7 vendor/bin/phpstan analyse ...
 [OK] No errors

$ bash scripts/ci-check.sh; echo $?
0
```

## ค้าง

- พิสูจน์ "block merge จริง" เชิงประจักษ์: ต้องมี PR ที่ CI แดงแล้ว merge ถูกปฏิเสธ — จะได้หลักฐานอัตโนมัติจาก PR ถัดไปที่ CI ล้ม หรือทดสอบด้วย PR ทดลอง
