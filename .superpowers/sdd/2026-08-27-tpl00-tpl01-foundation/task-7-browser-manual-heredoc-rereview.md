# Re-review unquoted SQL heredoc ของ Task 7

ตรวจเฉพาะ runtime error `condition: command not found` ใน canonical scratch start helper โดยไม่รัน start/cleanup helper และไม่แตะ Docker resource

## Verdict

**ADDRESSED**

- Unquoted SQL heredoc เริ่มที่ `/Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:305`
- Statement ที่มี reserved identifier ใช้ `INSERT INTO \`condition\`` ที่ `:314` จึงส่ง backticks แบบ literal ไปยัง MariaDB ไม่เกิด Bash command substitution
- ไม่พบ bare `INSERT INTO `condition`` ใน source heredoc

## Regression evidence

| Check | ผล |
|---|---|
| การขยาย heredoc แบบ isolated | PASS: escaped backticks expand to literal SQL identifier |
| Syntax | `bash -n` ผ่านสำหรับ start, helper-test และ cleanup |
| Helper regression | `PASS: 59 assertions` |
| Root-cause guard | `/Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh:286-288` ปฏิเสธ bare identifier และ bare backticks พร้อมบังคับ `\`condition\`` |

ไม่รัน start/cleanup helper และไม่สร้างหรือแก้ Docker resource ตามขอบเขตตรวจ
