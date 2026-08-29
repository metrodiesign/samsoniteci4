# รีวิวซ้ำ Task 1 รอบแก้ 3

เอกสารนี้ตรวจเฉพาะ finding ที่เหลือจากรอบแก้ 2: regression test ต้องพิสูจน์ว่า dirty tracked asset ทั้ง staged และ unstaged ถูกปฏิเสธแบบ fail-closed. ตรวจแพ็กเกจรอบแก้ 3 แบบ read-only.

## Verdict

| แกน | Verdict | เหตุผล |
|---|---|---|
| Finding เดิม | ADDRESSED | เพิ่ม test สำหรับ tracked asset ทั้ง unstaged และ staged โดยขับ guard จริงและตรวจการปฏิเสธครบ |
| New Critical/Important breakage | ไม่พบ | การแก้เพิ่มเฉพาะ regression test, ไม่เปลี่ยน production behavior |
| ผลรวม | PASS | ไม่มี finding ที่เปิดอยู่ใน scope นี้ |

## หลักฐาน test

| กรณี | การขับพฤติกรรมจริง | Assertion |
|---|---|---|
| Unstaged asset | เขียน `assets/tracked.css` หลัง fixture commit แล้วเรียก `module.main()` | `SystemExit`, stderr มีข้อความ dirty tree, ไม่มี output |
| Staged asset | เขียนไฟล์เดียวกัน, รัน `git add assets/tracked.css`, แล้วเรียก `module.main()` | `SystemExit`, stderr มีข้อความ dirty tree, ไม่มี output |

- **ตำแหน่ง**: `tests/wp00c/test_presentation_inventory.py:388-415`
- **Guard จริง**: `run_fixture_inventory()` import generator และเรียก `module.main()` โดยไม่ mock `tracked_tree_is_clean()` หรือ Git return code
- **Fail-closed**: ทั้งสอง test assert `SystemExit` และ `self.assertFalse(output.exists())`; จึงไม่ใช่เพียงตรวจข้อความ error
- **Scope**: fixture commit `assets/tracked.css` ก่อนแก้ไฟล์ จึงพิสูจน์ tracked asset ไม่ใช่ untracked file

## หลักฐานที่รัน

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
```

ผลลัพธ์ `Ran 7 tests in 1.378s` และ `OK` หมายถึงกรณี asset ใหม่ทั้งสองกรณี รวมถึง regression เดิมทั้งหมด ผ่านจริง.

## การตรวจ new breakage

- ไม่มีการแก้ `scripts/generate-ci3-presentation-inventory.py` ในรอบนี้
- test ใช้ temporary Git fixture เดิมและ standard library เท่านั้น
- ไม่พบ Critical หรือ Important breakage ที่เกิดจาก fix รอบนี้
