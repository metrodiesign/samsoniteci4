# Re-review reserved identifier ของ Task 7

ตรวจเฉพาะการแก้ MariaDB `ERROR 1064` ใกล้ `condition` ใน scratch helper, schema และ regression test โดยไม่รัน start/cleanup helper และไม่แตะ Docker resource

## Verdict

**ADDRESSED**

- Schema ประกาศตารางด้วย backticks ที่ `/Users/king_developer/Desktop/Project/samsoniteci4/db/local-schema-only.sql:81` (`DROP TABLE IF EXISTS `condition``) และ `:84` (`CREATE TABLE `condition``)
- Exact candidate tree `d572679d4c4bfdbd1d603961754f2a57fd6bcfef` มี schema แบบเดียวกัน
- Start helper ใช้ `INSERT INTO `condition`` ที่ `/Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-start.sh:314`
- ไม่พบ raw SQL รูปแบบ `INSERT INTO condition (` ใน canonical scratch start/helper-test หรือ schema

## Regression evidence

| Check | ผล |
|---|---|
| Helper regression test | `PASS: 57 assertions` |
| `bash -n` start, helper-test, cleanup | PASS, exit `0` |
| Adversarial mutation: เปลี่ยน `INSERT INTO `condition`` เป็น unquoted | RED ตามคาด โดย test แจ้ง `start helper leaves reserved condition table unquoted` |
| Regression assertion | `/Users/king_developer/Desktop/Project/samsoniteci4/.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-browser-manual-helper-test.sh:286` ปฏิเสธ unquoted statement |
| Start/cleanup/Docker | ไม่รันและไม่แตะตามขอบเขตตรวจ |

การทำ mutation ใช้สำเนาชั่วคราวเท่านั้น ไม่เปลี่ยน source, Git index หรือ helper จริง
