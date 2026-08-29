# รีวิวซ้ำ Task 1 รอบแก้ 1

เอกสารนี้ตรวจเฉพาะแพ็กเกจแก้ไข Task 1 เทียบกับ review เดิม โดยจำกัดที่ canonical template inventory, source identity, JSON และ regression test แบบ read-only

## Verdict

| แกน | Verdict | เหตุผล |
|---|---|---|
| 4 findings เดิม | 3 ADDRESSED, 1 NOT ADDRESSED | การป้องกัน tracked source ที่แก้ใน working tree ยังไม่ครบ |
| New breakage ระดับ Critical/Important | 1 Important | JSON อาจอ้าง CI3 pin ถูกต้องทั้งที่ hash ของไฟล์ tracked มาจาก tree ที่ dirty |
| ผลรวม | CHANGES REQUIRED | ต้องปฏิเสธ tracked CI3 tree ที่มีการแก้ไขก่อนสร้าง evidence |

## สถานะ findings เดิม

| ลำดับ | Verdict | หลักฐาน |
|---:|---|---|
| 1. source discovery ใช้ Git tracked list ไม่รวม untracked | ADDRESSED | `tracked_template_paths()` ใช้ `git ls-files -z -- application/views` และ filter `.php`/`.html`; fixture ยืนยันว่า `untracked.html` ไม่นับ |
| 2. validate และบันทึก CI3 pin | NOT ADDRESSED | ตรวจ `HEAD` และบันทึก `ci3_commit_sha` แล้ว แต่ไม่ตรวจ tracked working tree และ index ว่าสะอาด |
| 3. canonical JSON deterministic ไม่มี clock drift | ADDRESSED | ตัด `generated_at` ออก, ลำดับ template/asset ถูก sort, และ test เทียบ bytes ของ JSON สองรอบ |
| 4. test เทียบ full tracked source set, pin และ untracked exclusion | ADDRESSED | test เทียบ set ทั้งชุดจาก `git ls-files`, assert pin กับ JSON และมี fixture สำหรับ untracked exclusion |

## Finding ที่ยังเปิด

### Important: pin ไม่ยืนยันเนื้อหา tracked tree

- **ตำแหน่ง**: `scripts/generate-ci3-presentation-inventory.py:133-146`
- **ปัญหา**: `rev-parse HEAD` ยืนยันเพียง commit ปลายทาง แต่ generator อ่านเนื้อหาและ SHA-256 จาก filesystem โดยตรง
- **ผลกระทบ**: หากแก้ `application/views/*.php` ที่ tracked โดยไม่ commit, JSON ยังบันทึก `ci3_commit_sha` เป็น pin แต่ record และ hash เป็นของเนื้อหา dirty จึงไม่ใช่ evidence ของ pin ที่อ้าง
- **การแก้แคบที่สุด**: ก่อน discovery ให้ปฏิเสธเมื่อ `git diff --quiet` หรือ `git diff --cached --quiet` ของ CI3 คืนค่าไม่เป็นศูนย์ โดยต้องไม่ปฏิเสธ untracked files เพราะข้อ 1 ตั้งใจไม่นับไฟล์เหล่านั้น
- **Class sweep**: ตรวจทุกจุดที่ generator อ่าน CI3 template และ asset แล้ว, ทั้งหมดอาศัย filesystem หลังตรวจ `HEAD` เพียงจุดเดียว จึงอยู่ใน failure class เดียวกัน

## การตรวจ new breakage

- **Command injection**: ไม่พบ เพราะทุกคำสั่ง Git ส่งเป็น argument list ให้ `subprocess.run()` และไม่มี `shell=True`
- **Git delimiter และ path parsing**: ผ่านการใช้ `-z` ร่วมกับ `split("\0")`; Git filename ไม่มี NUL และ suffix filter ตัด record ว่างท้าย output
- **Dirty tracked CI3 source**: พบ finding ข้างต้น; สถานะ CI3 ปัจจุบันสะอาดจึงยังไม่ทำให้ package ปัจจุบันผิด แต่ generator ไม่มี guard สำหรับการรันครั้งถัดไป
- **Schema consistency**: `schema_version: 2`, `ci3_commit_sha`, `summary.ci3_templates` และ `ci3_templates` สอดคล้องกัน; JSON ระบุ 108 templates และ HTML 5 รายการ
- **Markdown claim**: ตัวเลข, pin และข้อจำกัด static evidence สอดคล้องกับ JSON ปัจจุบัน; ไม่มีการอ้าง runtime หรือ visual parity เกินหลักฐาน

## หลักฐานที่รัน

| คำสั่ง | ผล |
|---|---|
| `python3 -m unittest tests/wp00c/test_presentation_inventory.py tests/wp00c/test_closure.py tests/wp00c/test_junit_evidence.py tests/wp00c/test_route_disposition.py` | ผ่าน 12 tests |
| `git diff --check -- scripts/generate-ci3-presentation-inventory.py tests/wp00c/test_presentation_inventory.py` | ไม่มี output, จึงไม่พบ whitespace error |
| `git -C /Users/king_developer/Desktop/Project/samsoniteci3 rev-parse HEAD` | ตรงกับ `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` |
| `git -C /Users/king_developer/Desktop/Project/samsoniteci3 diff --quiet` และ `git diff --cached --quiet` | ทั้งคู่คืนค่า `0`, CI3 tracked tree ปัจจุบันสะอาด |

## ขอบเขตที่ไม่ตัดสิน

- ไม่ตัดสิน runtime, DOM, JavaScript หรือ visual parity ตามขอบเขตของ Task 1
