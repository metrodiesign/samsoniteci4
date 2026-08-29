# Re-review Fix Round 2: Task 7 Browser Manual Helper

ตรวจแบบ read-only เฉพาะ Important 4 เรื่อง credential mutation proof หลังแก้ validator โดยไม่รัน start/cleanup helper และไม่แตะ Docker

## สรุปคำตัดสิน

| Finding | สถานะ | หลักฐานย่อ |
|---|---|---|
| Important 4: credential mutation proof | ADDRESSED | Validator เทียบ statement ที่อนุญาตแบบ exact structural match และปฏิเสธ mutation ทั้ง 7 กรณี |

สถานะ Important 1–3 คงตามรายงาน fix round 1 และไม่อยู่ในขอบเขตการตรวจรอบนี้

## Validator structural match

`secret_uses_are_allowed()` ที่ `task-7-browser-manual-helper-test.sh:52-81` กรองเฉพาะบรรทัดที่อ้าง `ONE_TIME_PASSWORD` หรือ `PASSWORD_HASH` แล้ว trim whitespace ก่อนเทียบด้วย `case`

- อนุญาตเพียง validation expression, non-empty assertion, password-hash command และ SQL seed `printf` สอง statement ที่ประกาศครบทั้งบรรทัด
- statement ที่อ้าง credential เพิ่ม, redirect, append, export, argv หรือ text ประกอบ จะไม่เท่ากับ allowlist และคืน failure
- การเทียบไม่ใช้ substring allowlist และไม่พึ่ง hardcoded line number
- start helper ปัจจุบันผ่าน validator: `PASS: 47 assertions`

## Mutation proof

ทุก mutation ใช้สำเนาชั่วคราวของ start helper และ placeholder variable เท่านั้น ไม่มี password หรือ hash จริง และแต่ละกรณีถูกปฏิเสธด้วย exit `1` พร้อม `FAIL: start helper has an unapproved plaintext/hash sink`

| Mutation | ผล |
|---|---|
| plaintext file sink | REJECTED |
| hash file sink | REJECTED |
| log sink | REJECTED |
| argv sink | REJECTED |
| environment sink | REJECTED |
| metadata sink | REJECTED |
| credential-shaped hash file sink | REJECTED |

Mutation ใหม่ที่ตรวจ:

```bash
printf '%s %s\n' "$CENTRAL_USERNAME" "$PASSWORD_HASH" > "$WORKSPACE/hash-leak.txt"
```

## Verification

| Check | ผล |
|---|---|
| `bash -n` สำหรับ helper test, start และ cleanup | PASS, exit `0` |
| Current helper contract test | `PASS: 47 assertions` |
| Six mutations เดิม | REJECTED ครบ 6 กรณี |
| Mutation ใหม่ hash-leak | REJECTED |
| `git diff --check` | PASS, exit `0` |
| `git diff --cached --exit-code` | PASS, exit `0` |
| Start/cleanup execution | ไม่รันตามข้อห้าม |
| Docker | ไม่แตะ |

## Regression sweep

ไม่พบ Critical หรือ Important ใหม่จาก validator fix ในขอบเขต credential proof ที่ตรวจ

Verdict: ALL ADDRESSED

Counts: Critical 0, Important ADDRESSED 1, Important OPEN 0, New Critical/Important 0
