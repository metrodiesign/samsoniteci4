# Browser verification Task 7

ตรวจ exact candidate treeบน disposable isolated Docker runtime โดยใช้ actual browser ผลรวมเป็น `BLOCKED` เพราะ credential materializationถูก policyปฏิเสธก่อน authenticated matrix

## Source identity

| รายการ | ค่า |
|---|---|
| Base | `6799684db6de09936122d2ae25a5461a878b0eb3` |
| Candidate tree | `d572679d4c4bfdbd1d603961754f2a57fd6bcfef` |
| Whole-file paths | 20 |
| Route patch | `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-7-route.patch` |
| Build | สำเร็จจาก exact tree |

หลักฐานอยู่ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/browser-task7-20260828/`

## Matrix

| กลุ่ม | สถานะ | เหตุผล |
|---|---|---|
| Central create `/orders/new` | `NOT-VERIFIED` | ไม่มี authenticated browser session |
| Desktop/mobile upload UI | `NOT-VERIFIED` | ไม่มี authenticated browser session |
| Central editและ replacement | `NOT-VERIFIED` | ไม่มี authenticated browser session |
| Branch caller | `NOT-VERIFIED` | ไม่มี authenticated browser session |
| Anonymous preview POST | `PASS` | ได้ HTTP `401` |
| Authenticated missing CSRF | `NOT-VERIFIED` | ต้องมี authenticated session |
| Preview no-persistence/no-path | `NOT-VERIFIED` | ต้องมี authenticated request |
| POST-only browser proof | `NOT-VERIFIED` | Cleanupก่อนรันครบ |

## Runtime proof

Application healthผ่าน:

```json
{
  "status": "ok",
  "service": "ci4"
}
```

Fixtureสร้างใน isolated DBสำเร็จ:

```text
synthetic_users=2
order_fixtures=1
```

Anonymous preview endpointตอบ:

```text
401
```

## Blocker

Chromeเปิด isolated `/login` และ assetsหลักตอบ `200` แต่ policyปฏิเสธการนำ one-time synthetic passwordจากไฟล์ mode `600` ไปกรอกใน browser:

```text
Permission for this action was denied by the Claude Code auto mode classifier.
Reason: [Credential Materialization]
```

ความหมาย: verifierห้ามเปิดเผยหรือส่ง credentialจาก secure temporary storage แม้ resourceเป็น disposable isolated DB จึงหยุดโดยไม่พยายามข้าม policy

## Evidence

- `browser-task7-20260828/tree-identity.txt`
- `browser-task7-20260828/docker-build-status.txt`
- `browser-task7-20260828/login-1440x900.png`
- `browser-task7-20260828/login.a11y.txt`
- `browser-task7-20260828/cleanup-after.txt`

Consoleพบ asset `404` หนึ่งรายการบนหน้า login แต่ยังไม่ได้ระบุ resource จึงไม่จัดเป็น Task 7 defect

## Cleanup

ลบเฉพาะ application container, MariaDB container, network, volume, built image, temporary candidate tree, index, archive, fixturesและ credential fileที่สร้างรอบนี้แล้ว

ไม่มี resourceชื่อขึ้นต้น `samsonite-task7-browser-` เหลือ และ Docker projectเดิมยังทำงานครบ:

```text
samsonitetracking-ci4-migration-ci4-1 Up
samsonitetracking-ci4-migration-web-1 Up
samsonitetracking-ci4-migration-db-1 Up
```

## Verdict

`BLOCKED`

ต้องให้คนกรอก one-time synthetic credentialด้วยตนเองหรือเลือกคง Browser matrixเป็น `NOT-VERIFIED` ก่อนรัน matrixที่เหลือ
