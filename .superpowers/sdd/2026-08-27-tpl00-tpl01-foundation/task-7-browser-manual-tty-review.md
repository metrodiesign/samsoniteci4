# รีวิว Non-TTY Password Prompt

เอกสารนี้รีวิวแบบอ่านอย่างเดียวสำหรับการแก้ password prompt ของ browser manual helper และหลักฐาน regression ที่เกี่ยวข้อง

## Non-TTY prompt fix

| เกณฑ์ | สถานะ | หลักฐาน |
|---|---|---|
| TTY ใช้ hidden `read -s` | PASS | `task-7-browser-manual-start.sh:71-77` ใช้ `IFS= read -r -s ONE_TIME_PASSWORD` |
| no-TTY บน macOS ใช้ hidden AppleScript dialog | PASS | `task-7-browser-manual-start.sh:79-85` ตรวจ `/usr/bin/osascript` และเรียก `display dialog` พร้อม `with hidden answer` |
| password ไม่ออก stdout, log, file, argv, env หรือ AppleScript source | PASS | `task-7-browser-manual-start.sh:84` เก็บเฉพาะผลลัพธ์ dialog ใน memory, `:345` ส่ง password ผ่าน stdin แล้ว `:346` ล้าง plaintext; structural validator จำกัด sink ที่ `task-7-browser-manual-helper-test.sh:52-84` |
| no-TTY และไม่มี `osascript` ล้มเหลวก่อนสร้าง Docker resource | PASS | เรียก prompt ที่ `task-7-browser-manual-start.sh:228`; การสร้าง network และ volume เริ่มที่ `:251-252` |
| cancel/error เข้าสู่ EXIT cleanup | PASS | trap ถูกติดตั้งก่อน prompt ที่ `task-7-browser-manual-start.sh:223-228`; command substitution ที่ AppleScript error จะคืน nonzero ภายใต้ `set -e` และเข้า `cleanup_on_exit` |
| structural credential validator ไม่เปิด broad bypass | PASS | validator whitelist การใช้งาน `$ONE_TIME_PASSWORD` และ `$PASSWORD_HASH` แบบ exact match ที่ `task-7-browser-manual-helper-test.sh:52-84` และ mutation proof ครอบ sink สำคัญที่ `:169-180` |
| regression proof ไม่ false-green | FAIL | มี test runtime สำหรับ non-TTY ที่ `task-7-browser-manual-helper-test.sh:109-141` แต่ไม่มีการเรียกใช้ก่อนสรุปผลที่ `:143-229` |

## Findings

### Critical

ไม่มี

### Important

- `task-7-browser-manual-helper-test.sh:109-141`: ฟังก์ชัน `assert_non_tty_fallback_is_captured` ไม่ถูกเรียก จึงไม่ทดสอบ fallback จริง แม้ `bash task-7-browser-manual-helper-test.sh` จะรายงาน `PASS: 53 assertions` ได้
- **ผลกระทบ**: การเสียหายของ command substitution, การ capture response, หรือ flow no-TTY อาจผ่านแต่การตรวจแบบ string ยังเขียว
- **การแก้ขั้นต่ำ**: เรียก `assert_non_tty_fallback_is_captured` หลัง static assertions และก่อน final failure gate

### Minor

ไม่มี

## Verification

```bash
bash -n .superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh .superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh
# ผ่าน ไม่มี output

bash .superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh
# PASS: 53 assertions
```

ผล `PASS: 53 assertions` ยังไม่ยืนยัน non-TTY fallback เพราะฟังก์ชันทดสอบ runtime ดังกล่าวไม่ได้ถูก execute

## Final verdict

- **Spec: FAIL**
- **Quality: CHANGES REQUIRED**

ต้องเรียก runtime regression test ของ no-TTY fallback แล้วรัน helper test ใหม่ให้ assertion ครอบ flow จริงก่อนอนุมัติ
