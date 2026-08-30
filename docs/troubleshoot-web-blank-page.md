# แก้ปัญหา web (CI3) ตอบ 200 แต่ body ว่าง

คู่มือนี้ครอบคลุมอาการที่ container `web` (CI3 legacy runtime, พอร์ต `18404`) ตอบ HTTP 200
ทุก route แต่ body ว่าง (`Content-Length: 0`) เช่น `/dashboard`, `/login` เพราะ bind mount
ของ hook ค้าง cache ไม่เห็นไฟล์ใหม่บน host

## อาการ

- เปิด `http://127.0.0.1:18404/dashboard` แล้วหน้าเปล่า ไม่มี error บนจอ
- `curl -sS -D - http://127.0.0.1:18404/dashboard` ได้ `HTTP 200` แต่ `Content-Length: 0`
- ทุก route เป็นแบบนี้หมด ไม่ใช่แค่ `/dashboard` — เพราะ hook ตัวที่พังคือ
  `display_override` ซึ่งคุม output ทุกหน้า
- `docker compose logs web` ไม่มี error/warning ให้เห็น (PHP fatal ไม่โผล่ เพราะ CI3
  เงียบข้ามการ render ไปเลย ไม่ throw)

## Root cause

`compose.runtime-comparison.yaml` bind mount ไฟล์เหล่านี้เข้า container `web`:

```yaml
volumes:
  - ./scripts/runtime-comparison/ci3-hooks.php:/var/www/html/application/config/hooks.php:ro
  - ./scripts/runtime-comparison/ci3-hooks:/var/www/html/application/hooks:ro
```

`ci3-hooks.php` ผูก `display_override` กับคลาส `ParityTraceHook` (ไฟล์
`scripts/runtime-comparison/ci3-hooks/ParityTraceHook.php`) ถ้าไฟล์นี้บน host ถูกสร้าง/แก้
**หลัง** container `web` start ไปแล้ว Docker Desktop bind mount บางครั้งไม่ sync ไฟล์ใหม่เข้า
container — `stat`/`cat` ตรง path ในตัว container จะได้ `No such file or directory` ทั้งที่
host มีไฟล์จริง (ไม่ใช่แค่ `ls` cache, เช็คตรง path ก็ไม่เห็นเหมือนกัน)

CI3 (`system/core/Hooks.php`) เช็คแค่ว่า key `display_override` มีอยู่ใน `$hook` array
(`isset` เท่านั้น) แล้ว return `TRUE` ให้ `CodeIgniter.php` เข้าใจว่า hook รับ output ไปจัดการ
เองแล้ว จึง**ข้าม** `$OUT->_display()` ซึ่งเป็นตัวที่ echo body จริงออกไป — ผลคือ 200 กับ
body ว่าง เพราะ hook ถูกลงทะเบียนไว้แต่ไฟล์ class จริงหายไปจาก mount

## วิธีวินิจฉัย

1. เช็คว่า route ไหนก็ตามได้ 200 แต่ body ว่างจริง

   ```bash
   curl -sS -D - -o /dev/null http://127.0.0.1:18404/dashboard
   ```

2. เช็คว่า `web` container มี overlay ของ `compose.runtime-comparison.yaml` mount อยู่ไหม

   ```bash
   docker inspect samsonitetracking-ci4-migration-web-1 \
     --format '{{range .Mounts}}{{.Source}} -> {{.Destination}}{{"\n"}}{{end}}'
   ```

   ต้องเห็นบรรทัด `ci3-hooks -> /var/www/html/application/hooks`

3. เช็คว่า container เห็นไฟล์ hook จริงไหม (ยืนยัน root cause)

   ```bash
   docker exec samsonitetracking-ci4-migration-web-1 \
     stat /var/www/html/application/hooks/ParityTraceHook.php
   ```

   ถ้าได้ `No such file or directory` ทั้งที่ host มีไฟล์ (`ls -la
   scripts/runtime-comparison/ci3-hooks/`) → confirm bind mount ค้าง cache

## วิธีแก้

Recreate container `web` ด้วย compose file เดิมที่ใช้ตอน start (ต้องมี overlay
`compose.runtime-comparison.yaml` ด้วย ไม่งั้น mount ของ hook หายไปเลย ไม่ใช่แค่ค้าง cache)

```bash
docker compose -f compose.yaml -f compose.runtime-comparison.yaml up -d --force-recreate web
```

## วิธี verify ว่าแก้แล้วจริง

```bash
docker exec samsonitetracking-ci4-migration-web-1 \
  stat /var/www/html/application/hooks/ParityTraceHook.php

curl -sS -o /dev/null -w "HTTP:%{http_code} SIZE:%{size_download}\n" \
  http://127.0.0.1:18404/dashboard
```

`stat` ต้อง return ข้อมูลไฟล์ปกติ และ `SIZE` ต้องไม่ใช่ `0` (ถ้ายังไม่ login จะได้ body ของ
หน้า redirect ไป `/login`, ไม่ใช่ 0 byte)

## ป้องกันไม่ให้เกิดซ้ำ

- หลังแก้ไฟล์ใต้ `scripts/runtime-comparison/ci3-hooks/` ให้ recreate container `web`
  ทุกครั้งด้วยคำสั่งด้านบน อย่าพึ่ง live bind mount sync
- ถ้าจะ debug ปัญหา "200 แต่ body ว่าง" ในอนาคต ให้เช็คก่อนว่า `hooks.php` ผูก
  `display_override` ไว้กับ class อะไร แล้วยืนยันว่าไฟล์นั้น**เห็นจริงในตัว container**
  (ใช้ `stat` ตรง path ไม่ใช่แค่ `ls`)
