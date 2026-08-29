# รีวิวซ้ำ Task 1 รอบแก้ 2

เอกสารนี้ตรวจเฉพาะการแก้ finding เรื่อง CI3 tracked dirty tree ในแพ็กเกจรอบแก้ 2 แบบ read-only. ผลตัดสินยึดทั้งพฤติกรรม generator และ regression test ที่พิสูจน์ contract ที่ร้องขอ.

## Verdict

| แกน | Verdict | เหตุผล |
|---|---|---|
| Finding เดิม | NOT ADDRESSED | guard ใน generator ถูกต้อง แต่ test ยังไม่พิสูจน์ว่า tracked dirty asset ถูกปฏิเสธ |
| New Critical/Important breakage | ไม่พบ | pathspec, return code และลำดับก่อน read/hash ปลอดภัยสำหรับ scope นี้ |
| ผลรวม | CHANGES REQUIRED | เพิ่ม regression test สำหรับ tracked asset ที่ dirty แบบ staged และ unstaged |

## พฤติกรรม generator

- **ตำแหน่ง**: `scripts/generate-ci3-presentation-inventory.py:61-69,145-160`
- **Staged และ unstaged**: `tracked_tree_is_clean()` ต้องได้ return code `0` จากทั้ง `git diff --quiet` และ `git diff --cached --quiet` จึงผ่าน
- **ขอบเขต**: ทั้งสองคำสั่งระบุ pathspec `application/views` และ `assets` จึงป้องกัน tracked change ในทั้งสองต้นไม้
- **ลำดับ**: guard ทำงานหลังยืนยัน CI3 pin และก่อนกำหนด CI3 paths, template discovery, filesystem read หรือ SHA-256
- **Untracked**: `git diff` และ `git diff --cached` ไม่รายงาน untracked file จึงไม่ปฏิเสธตาม contract
- **False-clean**: return code `1` ของ Git เมื่อพบ diff และ return code อื่นเมื่อ Git ล้มเหลว ล้วนไม่เท่ากับ `0` จึงคืน `False`; ไม่มีทางผ่านแบบ false-clean จาก return code

## การพิสูจน์ด้วย test

| กรณี | การขับ branch | ผล |
|---|---|---|
| Unstaged tracked template | แก้ `application/views/tracked.php` หลัง commit แล้วเรียก `module.main()` | ผ่าน, `SystemExit` และไม่มี output |
| Staged tracked template | แก้ไฟล์เดียวกัน, `git add` แล้วเรียก `module.main()` | ผ่าน, `SystemExit` และไม่มี output |
| Untracked template | สร้าง `application/views/untracked.html` แล้วเรียก `module.main()` | ผ่าน, สร้าง output และไม่นับไฟล์ |

- **หลักฐานที่รัน**:

```bash
python3 -m unittest tests/wp00c/test_presentation_inventory.py
```

ผลลัพธ์ `Ran 5 tests in 1.134s` และ `OK` หมายถึง test ทั้งห้าผ่าน.

- **ช่องว่าง**: fixture สร้าง `assets/tracked.css` เป็น tracked จริง แต่ไม่มี test ใดแก้ไฟล์ asset แบบ unstaged หรือ staged ดังนั้น test ปัจจุบันผ่านได้แม้ pathspec ของ implementation จะเผลอตรวจเพียง `application/views`.

## Finding ที่ยังเปิด

### Important: regression test ยังไม่ครอบ tracked asset

- **ตำแหน่ง**: `tests/wp00c/test_presentation_inventory.py:359-398`
- **ปัญหา**: สอง test dirty tree ขับเฉพาะ tracked template; contract ต้องปฏิเสธ tracked dirty content ใต้ `assets` ด้วย
- **ผลกระทบ**: implementation ปัจจุบันดูถูกต้องจาก static review แต่ regression suite ไม่กันการถดถอยที่ตัด `assets` ออกจาก pathspec
- **การแก้แคบที่สุด**: เพิ่ม test dirty asset แบบ unstaged และ staged โดยใช้ fixture เดิม, assert `SystemExit` และ assert ว่า output ไม่ถูกสร้าง
- **Class sweep**: ตรวจครบ staged, unstaged และ untracked branch แล้ว; ช่องว่างเดียวคือ representative path ของ `assets` ในสอง dirty branches

## New breakage เฉพาะ fix

- ไม่พบ Critical หรือ Important breakage ใหม่
- `subprocess.run()` รับ argument list โดยไม่มี `shell=True`
- การปฏิเสธเมื่อ Git error เป็น fail-closed จึงไม่ทำให้ evidence ถูกสร้างจากต้นไม้ที่ตรวจสถานะไม่ได้
