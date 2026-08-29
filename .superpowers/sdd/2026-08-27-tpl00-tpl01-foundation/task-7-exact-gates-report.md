# Task 7 exact candidate gates

รายงานนี้ตรวจเฉพาะ candidate tree `e51837eec685b090f1072a8b2887fa7008f4c587` ที่ประกอบจาก base และ temporary index โดยไม่ใช้ dirty working tree เป็นผล release-grade

## สถานะรวม

**STATUS: RED** — PHPStan และ integrity gates ผ่าน แต่ PHPUnit เต็มชุดและ `scripts/ci-check.sh` บน exact candidate ล้มเหลว

| Gate | สถานะ | ผลย่อ |
|---|---|---|
| Candidate assembly | GREEN | 21 whole-file paths + route patch สร้าง tree ตรง expected |
| PHPUnit เต็มชุด | RED | 396 tests, 35 failures, 2 errors, exit 2 |
| PHPStan เต็มชุด | GREEN | `No errors` |
| `scripts/ci-check.sh` เต็มชุด | RED | exit 2 หลัง shell syntax, composer audit และ PHPStan |
| `git diff --check` | GREEN | ไม่มี whitespace error |
| bg-form raw pin | GREEN | 937 bytes, SHA-256 และ `cmp` ตรง CI3 pin |
| Helper test และ `bash -n` | GREEN | 61 assertions ผ่าน |
| Candidate/index integrity | GREEN | 22 paths, route hunk เดียว, real index คงเดิม |

## Source identity และ setup

- **Base commit**: `6799684db6de09936122d2ae25a5461a878b0eb3`
- **Expected/assembled tree**: `e51837eec685b090f1072a8b2887fa7008f4c587`
- **Assembly**: temporary `GIT_INDEX_FILE` เริ่มจาก base, เพิ่ม 21 paths จาก `WHOLE_FILE_PATHS`, แล้ว apply cached `task-7-route.patch`
- **Execution source**: `git archive` ของ exact candidate ถูก stream เข้า Docker image หรือ extract ไป temporary archive เท่านั้น
- **Working tree**: ไม่ใช้เป็น input ของ test/PHPStan/CI ยกเว้น 21 paths เพื่อประกอบ temporary index ตาม helper contract

## Gate evidence

### PHPUnit และ PHPStan

PHPUnit ทำงานใน Docker image ที่ build จาก `git archive` ของ candidate โดยตรง

```bash
git -C "$repo" archive "$candidate" | docker build --file Dockerfile.ci4 --tag "$image" -
docker run --rm "$image" vendor/bin/phpunit --configuration phpunit.xml.dist
```

- **PHPUnit**: `396` tests, `35` failures, `2` errors, exit `2`
- **Observed errors**: `MenuHttpTest` หา `access_denied.php` ไม่พบ และ `RouteHttpTest` หา `tests/wp00c/ci4-route-disposition.json` ไม่พบ
- **PHPStan**: mount config/bootstrap จาก candidate archive เข้า image เดียวกัน; ผล `No errors`

### Full CI

รันจาก `scripts/ci-check.sh` ใน temporary candidate archive โดยตั้ง `DOCKER_CONFIG` ใต้ temporary root

```bash
DOCKER_CONFIG="$docker_config" bash "$archive/scripts/ci-check.sh"
```

- รอบ sandbox แรกถูก block ที่ Docker socket: `permission denied while trying to connect to the docker API at unix:///var/run/docker.sock`
- retry เดิมนอก sandbox ตาม policy ได้รันจริงและจบ exit `2`
- ผ่าน `PASS shell syntax`, Composer audit และ PHPStan ก่อนจบ; script redirect output ของ PHPUnit ไป `/dev/null` จึงใช้ full PHPUnit gate ด้านบนเป็น failure detail

### Candidate และ route scope

- `git diff --check $base $candidate` ผ่าน
- Changed path count: `22`
- Route diff มีเพียง insertion หนึ่งบรรทัด: `POST order/do_upload_multi/(:segment)` ไป `Order::previewUpload/$1` พร้อม filters `web-auth`, `authorized:write`, `csrf`
- ไม่มี route hunk อื่น จึงไม่พบ forbidden route hunk

### bg-form raw CI3 pin

```bash
git -C "$ci4" show "$candidate:public/assets/images/bg-form.png" | shasum -a 256
cmp -s <(git -C "$ci4" show "$candidate:public/assets/images/bg-form.png") \
  <(git -C "$ci3" show "ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6:assets/images/bg-form.png")
```

- Candidate และ CI3 pin มี SHA-256 `65fd6f960ea58421a1ba10a8414332b05e9de97150c098578db73305048fa1c0`
- ทั้งสองฝั่งขนาด `937` bytes และ `cmp` ผ่าน

### Helper และ index safety

- `bash -n` ผ่านสำหรับ start, cleanup และ helper-test scripts
- `task-7-browser-manual-helper-test.sh`: `PASS: 61 assertions`
- Real index ก่อนและหลัง: `c6ce38a8953cb1dedf08e35446b3195347139425`
- `git diff --cached --quiet` ผ่านทั้งก่อนและหลัง

## Cleanup

- Docker images ที่ตั้งชื่อเฉพาะ `samsonite-task7-exact-gates-*` ถูกลบโดย shell trap หลังแต่ละ gate
- Temporary archive, `DOCKER_CONFIG` และ temporary index ถูกลบหลังคำสั่งจบ
- ไม่แตะ Browser runtime, shared project resources, real Git index, staging area, source หรือ tests
