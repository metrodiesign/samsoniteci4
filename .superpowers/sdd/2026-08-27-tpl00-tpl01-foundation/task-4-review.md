# Review Task 4: Admin layout presenter

เอกสารนี้ตรวจ Task 4 แบบ task-scoped จาก brief, design spec, report, review package และ resulting files โดยตรวจ data flow และ security ของไฟล์ที่เกี่ยวข้องทั้งหมด แต่ไม่รวมการคืน CI3 AdminLTE DOM shell ซึ่งเป็น Task 5

## Verdict

| แกนตรวจ | ผล | เหตุผลย่อ |
|---|---|---|
| Spec compliance | PASS | มี shared presenter ขั้นต่ำ, ใช้ session key จาก writer จริง, ต่อ normal layout และ authenticated access-denied HTML, และคง early return ของ JSON/AJAX/anonymous |
| Code quality | APPROVED | data flow ตรงไปตรงมา, ไม่มี query ซ้ำ, escaping/trusted HTML contract ไม่เปลี่ยน และไม่มี finding ที่ต้องแก้ |

จำนวน finding:

| ระดับ | จำนวน |
|---|---:|
| Critical | 0 |
| Important | 0 |
| Minor | 0 |

## Spec compliance

### สิ่งที่ครบ

- `app/Presentation/AdminLayoutPresenter.php:7-40` เป็น presenter เดียวที่รับ session, title, content และ profile แล้วคืน view model ขั้นต่ำ
- `app/Controllers/BaseController.php:54-68` ใช้ presenter สำหรับ normal HTML และคง `subtitle`, `actions`, `accessDeniedProfile` ไว้
- `app/Presentation/AccessDeniedResponder.php:13-35` เรียก presenterเฉพาะ authenticated HTML หลังผ่าน content negotiation แล้ว
- `app/Presentation/AccessDeniedResponder.php:13-18` ตอบ JSON `403` ก่อน `db_connect()`, `MenuStore` และ presenter
- `app/Presentation/AccessDeniedResponder.php:39-42` ตัด anonymous และ AJAX ออกจาก HTML path ก่อนสร้าง presenter
- `app/Controllers/BaseController.php:67` มี profile seam สำหรับ `layout` และ `layout_order` ตาม brief โดยยังไม่มี caller เลือก `order`
- `app/Presentation/AdminLayoutPresenter.php:34-36` ส่ง `BranchName = ''` และ `branchOptions = []` แทนการสร้างข้อมูลที่ไม่มี writer จริง
- ไม่มีการแก้ frontend dependency, asset หรือ DOM shell ใน Task 4

### Session contract

Presenter อ่าน key ที่ `app/Authentication/LoginService.php:56-65` เขียนจริง:

| Output | Session source | ผลตรวจ |
|---|---|---|
| `isLoggedIn` | `isLoggedIn` | ตรง |
| `name` | `name` | ตรง |
| `role_text` | `roleText` | ตรง |
| `last_login` | `lastLogin` | ตรง |
| `GroupID` | `GroupID` | ตรง |
| `BranchID` | `BranchID` | ตรงและ normalize เป็น nullable integer |
| `BranchName` | ไม่มี writer | ใช้ empty fallback |
| `branchOptions` | ไม่มี writer | ใช้ empty fallback |

`tests/ci4/MenuHttpTest.php:110-133` ใช้ `roleText` และ `lastLogin` ตาม writer จริง พร้อมขับ `MenuStore::visible()` บนฐานข้อมูล test จริง ไม่ได้ใช้ fixture ที่ bypass presenter

### Missing และ extra

- **Missing**: ไม่มี
- **Extra**: ไม่มี
- shell assertions ใน `task-4-brief.md:18-29` ไม่ถูกนับเป็น missing เพราะ global constraint แยก CI3 AdminLTE DOM shell ไป Task 5 และห้าม implement ล่วงหน้า
- compatibility key `title` ที่ `app/Presentation/AdminLayoutPresenter.php:26` จำเป็นต่อ `app/Views/layout.php:39` จึงไม่ใช่ scope creep

## Class sweep

| กรณี | หลักฐาน | ผล |
|---|---|---|
| Normal layout | `app/Controllers/BaseController.php:54-68`, `tests/ci4/AccessDeniedHttpTest.php:265-294` | ใช้ presenter และไม่รับ state ของ access-denied ข้าม render |
| Access-denied HTML | `app/Presentation/AccessDeniedResponder.php:20-35`, `tests/ci4/AccessDeniedHttpTest.php:47-87` | authenticated HTML ใช้ shared layout และคง `403` |
| JSON Accept | `app/Presentation/AccessDeniedResponder.php:13-18`, `tests/ci4/AccessDeniedHttpTest.php:89-133` | ตอบ JSON `403` โดยไม่ render layout |
| AJAX | `app/Presentation/AccessDeniedResponder.php:39-42`, `tests/ci4/AccessDeniedHttpTest.php:136-181` | fail closed เป็น JSON `403` และไม่ query menu |
| Anonymous | `app/Filters/AuthenticationFilter.php:23-45`, `tests/ci4/AccessDeniedHttpTest.php:155-181` | หยุดที่ authentication เป็น JSON `401` ก่อน responder/presenter |
| Malformed/equal q-value | `app/Presentation/AccessDeniedResponder.php:53-96`, `tests/ci4/AccessDeniedHttpTest.php:109-133` | malformed, duplicate และ equal q-value fail closed เป็น JSON |
| Menu DB failure | `app/Presentation/AdminLayoutPresenter.php:35`, `app/Master/MenuStore.php:105-188` | ไม่มี fallback ใหม่หรือการกลบ exception จึงไม่เปลี่ยน failure policy และไม่ render menu จากข้อมูลประมาณ |
| Session absence | `app/Presentation/AdminLayoutPresenter.php:20-36` | fallback เป็น false, empty string, `0`, `null` และ empty list โดยไม่ fabricate ค่า |
| Escaping/trusted HTML | `app/Views/layout.php:39,71-75,83-99` | title, name และ menu text ยัง escape; `content`, `subtitle`, `actions` คง trusted HTML contract เดิม |
| DI/testability | `app/Presentation/AdminLayoutPresenter.php:9`, `tests/ci4/MenuHttpTest.php:112` | `MenuStore` ถูก inject เข้า presenter และ test ใช้ connection ของ test โดยตรง |
| Double query/regression | `app/Controllers/BaseController.php:57-62`, `app/Presentation/AccessDeniedResponder.php:22-26` | caller แต่ละ path เรียก presenterหนึ่งครั้ง และ presenterเรียก `visible()` ได้สูงสุดหนึ่งครั้ง |

หมายเหตุเรื่อง menu DB failure: report ไม่มี injected-failure runtime test จึงไม่ใช้กรณีนี้อ้าง recovery หรือ graceful fallback; การอนุมัติอาศัยการตรวจว่า Task 4 ไม่เพิ่ม fallback และยังมี query path เดียวเหมือน contract เดิม

## Security และ data flow

### Early-return boundary

ลำดับใน access-denied path ถูกต้อง:

1. `prefersHtml()` ตรวจ valid authenticated session marker และ AJAX
2. parser ตรวจ `Accept` แบบ fail closed
3. JSON/AJAX คืน response ก่อนสร้าง presenter
4. authenticated HTML เท่านั้นจึงอ่าน session, connect DB และ query menu
5. render static access-denied content ใน shared layout แล้วตอบ `403 text/html`

`tests/ci4/AccessDeniedHttpTest.php:136-164` ฟัง `DBQuery` event รอบ JSON, AJAX และ anonymous requests จริง แล้วตรวจว่าไม่มี query ของ `group_menu` หรือ `tbl_menu`

### Escaping boundary

- Presenterไม่ render หรือ concatenate user data เข้ากับ HTML
- `app/Views/layout.php:39` escape title
- `app/Views/layout.php:71-75` escape group name และ menu name
- `app/Views/layout.php:83` escape display name
- `menu_link` ผ่าน allowlist pattern ที่ `app/Master/MenuStore.php:145-151` ก่อนใช้ใน view
- `content`, `subtitle` และ `actions` ยังเป็น trusted rendered HTML slots ตาม contract ที่ `app/Controllers/BaseController.php:48-52`
- Access-denied content มาจาก static view ไม่รับ query, body หรือ header value; test ไม่พบ marker ที่ request ควบคุมได้ใน `tests/ci4/AccessDeniedHttpTest.php:221-237`

## Test coverage และหลักฐาน

### Branch ที่ test ขับจริง

- Presenter mapping และ menu query: `tests/ci4/MenuHttpTest.php:110-133`
- Normal `BaseController::layout()`: `tests/ci4/AccessDeniedHttpTest.php:265-294`
- Authorization denial HTML: `tests/ci4/AccessDeniedHttpTest.php:47-74`
- Branchless denial HTML: `tests/ci4/AccessDeniedHttpTest.php:76-87`
- JSON negotiation, malformed header และ q-value: `tests/ci4/AccessDeniedHttpTest.php:89-133`
- JSON/AJAX/anonymous no-menu-query: `tests/ci4/AccessDeniedHttpTest.php:136-164`
- AJAX และ anonymous representation/status: `tests/ci4/AccessDeniedHttpTest.php:166-181`

`tests/ci4/MenuHttpTest.php:251-277` เรียก standalone `/login` และไม่ได้ใช้ presenter จึงไม่ถูกนำมาเป็นหลักฐานของ Task 4; การอนุมัติใช้ tests ด้านบนที่ขับ presenter และ responder path จริง

### ผลรันจาก report

Reviewerไม่ได้รัน suite ซ้ำตามขอบเขต read-only และตรวจหลักฐานที่บันทึกใน `task-4-report.md`:

```text
focused:        OK (46 tests, 530 assertions)
auth/session:   OK (36 tests, 195 assertions)
PHPStan:        [OK] No errors
full PHPUnit:   OK (398 tests, 6452 assertions)
```

ความหมาย: focused presenter/access-denied tests, auth/session regression, static analysis และ full PHPUnit ผ่านใน resulting tree ตาม report โดยไม่มี test skip ที่รายงาน

## Findings

ไม่พบ finding ระดับ Critical, Important หรือ Minor ภายใน Task 4
