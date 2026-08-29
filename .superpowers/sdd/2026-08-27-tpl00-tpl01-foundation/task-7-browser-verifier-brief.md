# Task 7 Authenticated Browser verifier

รัน matrix นี้หลังผู้ใช้กรอก one-time passwordบนหน้า loginด้วยตนเองแล้วเท่านั้น ผลจาก Node simulationหรือ direct handler invocationใช้แทน actual browserไม่ได้

## Authority และ source identity

- CI3 presentation pin: `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6`
- CI4 candidate tree: `d572679d4c4bfdbd1d603961754f2a57fd6bcfef`
- Browser runtimeต้องรายงาน treeเดียวกันก่อนเริ่ม
- Viewports: `1440x900` และ `390x844`, DPR 1
- ห้ามอ่าน, copy, log, screenshotหรือส่ง one-time password

## Preconditions

1. อ่าน nonsecret runtime metadataจาก helper
2. ตรวจ `/health` ผ่าน
3. ตรวจ source treeตรง exact candidate
4. ให้ผู้ใช้ login central userเอง
5. ยืนยัน final URLและ authenticated shellโดยไม่อ่านข้อความใน password field

## Login และ selectors

- Login form POST `loginMe`: `input[name=username]`, `input[name=password]`, `input[type=submit]`
- Loginสำเร็จ redirect `/dashboard`
- Create form: `#addOrder`, POST `/orders/new`
- Create upload input: `#order-image[name="detail_image[]"]`
- Edit form: `#addOrder`, POST `/orders/{request_id}`
- Edit upload input: `#edit-image[name="detail_image[]"]`
- Preview form: `#upload`, drop zone `#drop`, native input `input[name=upl]`, queue `#upload ul`
- Preview row: `li.working` มี progress input, imageและ direct cancel/delete span
- Central callerมี `BranchID == null` และโหลด `admin_addOrder.js`
- Branch callerมี `BranchID != null` และโหลด `addOrder.js`

Payloadขั้นต่ำต้องมี `submission_id` แบบ 32 hex, number/book/customer/type/brand/branch fields, note, SKU, creator และ arrays `condition[]`, `estimateprice[]`, `fixed[]`

## Central create

ตรวจ actual browserบน `/orders/new`:

1. DOM selectorsและ script orderตรง CI3 order profile
2. native file selectionสร้าง preview
3. FileReader late-context pathยังได้ preview, progressและ Knob
4. upload preview requestใช้ POST, authenticatedและส่ง CSRF
5. responseหมุน CSRF แล้ว requestถัดไปใช้ tokenใหม่
6. completed preview deleteลบ Fileจาก final queue
7. duplicate filename, same File occurrenceและ multiple filesไม่ลบข้าม operation
8. drag/dropทำงาน
9. pending abortทำงานเมื่อจับ timingได้ ถ้าจับไม่ได้ให้ `NOT-VERIFIED` ห้ามเดา
10. rejected/failed previewไม่เข้า final queue
11. final submitสร้าง orderและผูกชื่อไฟล์ที่ serverสร้าง
12. DB/storageมี side effectเท่าที่ contractกำหนด ไม่มี temp preview persistence

## Central edit

ตรวจ actual browserบน synthetic order edit:

1. existing imageแสดงตาม CI3
2. replacement fileผ่าน preview flow
3. final submitผูก associationใหม่
4. prior fileยังคงอยู่ตาม signed rulingของ Task 7
5. ไม่มีชื่อ temp client pathหรือ filesystem pathรั่วใน DOM/network/response

## Branch caller

1. logoutแล้วให้ผู้ใช้ login branch userเอง
2. หน้า createโหลด exact `addOrder.js` ที่มี known syntax defectตาม CI3 authority
3. บันทึก console syntax errorตามจริง
4. ยืนยัน browse script tagถัดไปยังโหลดและ interactionที่ไม่พึ่ง defectยังทำงาน
5. ห้ามแก้ defectหรืออ้างหน้าไร้ error

## Security matrix

| Case | Expected |
|---|---|
| Anonymous preview POST | `401` |
| Authenticated preview POST ไม่มี CSRF | reject ก่อน validation/persistence |
| Preview GET | ไม่สำเร็จ เพราะ routeเป็น POST-only |
| Valid preview POST | validationเท่านั้น ไม่ persistและไม่คืน server path |
| Final create/edit | validateซ้ำและสร้าง server filename |

## Responsive และ evidence

ทำ central create/editและ branch createที่ desktop/mobileเมื่อ stateเอื้อ เก็บเฉพาะ:

- screenshotsที่ไม่มี credential, cookie, CSRFหรือ sensitive headers
- DOM snapshotที่ redact nondeterministic tokenก่อน persist
- console messagesที่ไม่มี secret
- network summaryแบบ method, path, status, content type และ body field namesเท่านั้น
- DB/storage counts/hashที่ไม่รวม password hashหรือ session data

ห้าม persist raw request headers, cookies, authorization data, password field valueหรือ full CSRF token

## Verdict

แต่ละ matrix rowใช้ `PASS`, `FAIL`, `NOT-VERIFIED` หรือ `BLOCKED` พร้อม evidence path

Task 7ผ่านได้เมื่อ:

- ไม่มี load-bearing `FAIL`
- ไม่มี load-bearing `NOT-VERIFIED`
- security matrixครบ
- central create/editและ branch callerครบ
- desktop/mobileครบ
- cleanupผ่านและ shared Docker projectยัง healthy

ถ้า pending abortเป็นกรณีเดียวที่จับ timingไม่ได้ ให้รายงานแยกและส่ง final reviewerตัดสิน ห้ามเปลี่ยนเป็น PASSเอง
