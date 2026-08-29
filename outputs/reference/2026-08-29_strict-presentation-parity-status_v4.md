# รายงาน strict CI3 presentation parity รอบปิดงาน

## ผลรวม

- CI3 authority: `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`
- runtime-required templates: `102`
- one-to-one dedicated copies: `102/102`
- dedicated caller-scenario runtime: `102/102 PASS`
- normalized DOM: `102/102 PASS`
- interaction contract: `102/102 PASS`
- desktop `1440x900`, DPR 1: `102/102 PASS`
- mobile `390x844`, DPR 1: `102/102 PASS`
- inventory: `outputs/reference/2026-08-29_ci3-presentation-inventory_v7.json`

## Scenario ตามชนิด caller

| ชนิด runtime | จำนวน | วิธีตรวจ |
|---|---:|---|
| browser page | 79 | render target เฉพาะหน้าด้วย view-model fixture ของ caller แล้วตรวจ DOM, form/link contract และภาพสอง viewport |
| composed partial | 7 | render partial จริงใน isolated composition runtime ไม่ใช้ route ของหน้าอื่นแทน |
| framework HTML error | 6 | เรียก framework-error fixture ที่มี heading, message, exception, file และ line |
| framework CLI error | 5 | เรียก CLI error fixture แล้ว render เป็น terminal-style visual artifact |
| export | 4 | เรียก export fixture และตรวจ table contract พร้อมภาพสอง viewport |
| email | 1 | เรียก reset-delivery fixture ด้วยข้อมูล `example.invalid` และตรวจ link contract โดยไม่มี outbound transport |

รวม non-browser caller `23` targets ทุก target มี scenario และ evidence ของตัวเอง ไม่มีการนำ browser URL เดียวมาอ้างซ้ำ

## Fail-closed evidence

ผลอยู่ที่ `evidence/strict-parity/views/runtime-results.json` และ artifact แยก directory ตาม scenario แต่ละ target โดยแต่ละรอบประกอบด้วย:

- output ฝั่ง CI3 และ CI4 จาก process เดียวกัน
- runtime/DOM verdict
- interaction facts
- screenshot CI3/CI4 ที่ `1440x900` และ `390x844`
- SHA-256 ของภาพทั้งสองฝั่ง

inventory generator จะให้ `PASS` เฉพาะเมื่อ source/target ตรงกัน, `same_run=true`, output ทั้งสองมีอยู่และเท่ากัน, interaction evidence มีอยู่ และไฟล์ภาพทั้งสี่มี SHA-256 ตรงกับ verdict เท่านั้น หาก artifact หายหรือถูกแก้ สถานะจะกลับเป็น `NOT_VERIFIED`

## คำสั่งทำซ้ำ

```bash
python3 scripts/run-strict-presentation-parity.py
node scripts/capture-strict-presentation-scenarios.mjs
python3 scripts/generate-ci3-presentation-inventory.py \
  --ci3-root ../samsoniteci3 \
  --ci4-root . \
  --output outputs/reference/2026-08-29_ci3-presentation-inventory_v7.json
```

`run-strict-presentation-parity.py` ตรวจ CI3 pin ก่อนทุกครั้ง และไม่แก้ฐานข้อมูลหรือส่ง email/SMS ออกนอกเครื่อง

STATUS: DONE
