## Task 6: ปิด runtime asset closure ของ shared admin shell

**Files:**

- Add: runtime filesที่ current views/CSS อ้างใต้ `public/assets/**`
- Add: `public/uploads/web/contact_laptop.png`
- Add: `public/uploads/web/contact_mobile.png`
- Add: `public/uploads/web/track_laptop.png`
- Add: `public/uploads/web/track_mobile.png`
- Modify: asset checksum testsใน `tests/ci4/MenuHttpTest.php`
- Create: `outputs/reference/2026-08-27_tpl01-asset-closure_v1.md`

**Interfaces:**

- Consumes: recursive view/CSS dependency graph
- Produces: tracked runtime closure ที่ทุก local URL resolve และ version/checksum ตรง CI3

- [ ] **Step 1: เขียน failing tracked-asset test**

Test ต้องล้มเมื่อ asset resolve บน disk แต่ `git ls-files --error-unmatch` ไม่พบ โดยตรวจเฉพาะ runtime graph ไม่ใช่ทุก vendor file

- [ ] **Step 2: สร้าง graph จาก view/CSS references**

รวม direct references และ recursive `url()`/`@import` จาก shared admin/public/auth layouts

Expected closure ปัจจุบัน: 106 runtime files โดย 91 files ยัง untracked ต้องตรวจจำนวนใหม่จาก source ก่อน add

- [ ] **Step 3: add เฉพาะ closure ที่มี caller**

ห้าม add:

- `assets/plugins/` ทั้ง 693 files
- source SCSS/LESS
- examples/docs/tests/specimen
- `multifreezer.js` ที่ CI3 comment ไว้
- `cms-logo.png` ที่อยู่ใน HTML comment

- [ ] **Step 4: บันทึก exact versions/checksums/provenance**

Evidence ต้องครอบ:

- Bootstrap 3.3.4
- DataTables 1.10.16
- FixedColumns 3.2.4
- Font Awesome 4.2/4.3/4.7 ตาม caller
- html5shiv 3.7.2
- Respond.js 1.4.2
- Source Sans Pro license

หาก bundle ไม่มี license text ให้สถานะ provenance/license เป็น `BLOCKED` ห้ามสร้าง license ขึ้นเอง

- [ ] **Step 5: รัน asset graph tests และ clean-check simulation**

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/MenuHttpTest.php tests/ci4/PasswordResetPageHttpTest.php \
  tests/ci4/ContactHttpTest.php tests/ci4/PublicTrackingHttpTest.php \
  tests/ci4/AccessDeniedHttpTest.php
```

Expected: ทุก referenced local asset มีจริง, tracked และ checksum/version ผ่าน

- [ ] **Step 6: review และ checkpoint commit**

```text
wip(strict-template): t6 shared assets passed
```

