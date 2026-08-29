# Task 2 corrective checkpoint

ซ่อม checkpoint `e2cd894` แบบ follow-up โดยแยก WP03J contamination ออกจาก Tracking no-trim contract และรักษา dirty working tree ปัจจุบันทุกไฟล์

## ขอบเขต

สร้าง candidate จาก `HEAD` ผ่าน temporary index เท่านั้น ห้ามแก้ working-tree source/test โดยตรง

ไฟล์ใน corrective candidate มีเพียง:

1. `app/Controllers/Tracking.php`
2. `tests/ci4/PublicTrackingHttpTest.php`

ห้ามรวม `tracking_form.php`, `tracking_result.php`, `layout_public.php` หรือ WP03J path อื่น

## Test candidate

เริ่ม `tests/ci4/PublicTrackingHttpTest.php` จาก blob ของ `e2cd894^` แล้วเพิ่ม method เดียว:

```php
public function testCanonicalAndLegacyQueryAdapterPreservesPrecedenceAndNoTrimContract(): void
{
    $canonical = $this->get('/tracking?tracking_id=WP00C-TRACK-005&searchText=WP00C-TRACK-999');
    $canonical->assertStatus(200);
    $canonical->assertSee('SYNTHETIC RETURN');

    $legacy = $this->get('/tracking?searchText=WP00C-TRACK-005');
    $legacy->assertStatus(200);
    $legacy->assertSee('SYNTHETIC RETURN');

    foreach ([
        '/tracking?tracking_id=%20WP00C-TRACK-005%20&searchText=WP00C-TRACK-005',
        '/tracking?searchText=%20WP00C-TRACK-005%20',
        '/tracking?tracking_id%5B%5D=WP00C-TRACK-005&searchText=WP00C-TRACK-005',
    ] as $url) {
        $response = $this->get($url);
        $response->assertStatus(200);
        $response->assertSee('TRACK &amp; TRACE');
        $response->assertDontSee('SYNTHETIC RETURN');
        $response->assertDontSee('SYNTHETIC CUSTOMER FIVE');
    }
}
```

คง parent method `testSearchRejectsUnknownWildcardAndOversizedTrackingIdsWithoutPartialMatch()` เพื่อพิสูจน์ wildcard และความยาวเกิน 100

## Production candidate

แก้เฉพาะ `Tracking::fromQuery()` จาก `HEAD` ให้เป็น:

```php
private function fromQuery(string $language): string
{
    $canonical = $this->request->getGet('tracking_id');
    $value     = $canonical === null ? $this->request->getGet('searchText') : $canonical;
    $trackId   = is_string($value)
        && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/D', $value) === 1
            ? $value
            : '';
    $timeline = $trackId === '' ? [] : (new TrackingLookup(db_connect()))->timeline($trackId);

    return $this->render($language, $trackId, $timeline);
}
```

ห้ามเพิ่ม `fromSegment()`, `fromValue()`, `notFound`, title/profile, hierarchy, background หรือ view change จาก dirty WP03J implementation

## TDD gate

### RED

ประกอบ candidate ที่มี restored-parent test + method ใหม่ แต่ยังใช้ `Tracking.php` จาก `HEAD`

รัน:

```bash
vendor/bin/phpunit --configuration phpunit.xml.dist \
  tests/ci4/PublicTrackingHttpTest.php \
  --filter 'testCanonicalAndLegacyQueryAdapterPreservesPrecedenceAndNoTrimContract|testSearchRejectsUnknownWildcardAndOversizedTrackingIdsWithoutPartialMatch'
```

ต้อง RED ด้วย behavior ที่ขาดจริง:

- canonical whitespace ถูก `trim()` แล้วเห็น `SYNTHETIC RETURN`
- valid legacy `searchText` ไม่เดิน lookup เดียวกัน

### GREEN

เพิ่ม production hunk แล้วรันคำสั่งเดิม ต้อง GREEN

### Mutation proof

พิสูจน์อย่างน้อย mutation เหล่านี้ทีละตัวและคืนกลับทุกครั้ง:

- `trim($value)` ทำ whitespace case RED
- อ่านเฉพาะ canonical ทำ valid legacy RED
- ให้ legacy ชนะ ทำ canonical precedence RED
- fallback เมื่อ canonical invalid แทนเฉพาะ `null` ทำ invalid canonical case RED
- ผ่อน regex ทำ parent wildcard/oversized test RED

## Candidate และ patch artifacts

- ใช้ unique temporary index จาก `HEAD`
- เขียน test blob จาก parent + method ใหม่ลง temporary index
- สร้าง RED tree ก่อน production hunk และ GREEN treeหลัง production hunk
- ห้ามใช้ dirty working treeทั้งก้อน
- สร้าง final patch ที่ `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-2-corrective.patch`
- patch ต้องเปลี่ยนเพียงสอง pathsในขอบเขต
- ตรวจ `git apply --check` และ `git diff --check` กับ candidate
- real index treeต้องเท่าเดิมก่อนและหลัง

## Verification environment

ใช้ exact Git tree/archive candidate และ unique Docker image tag หาก local vendor ไม่เหมาะ ห้ามใช้ shared Docker project/container และต้องลบเฉพาะ image/containerที่สร้างเอง

ห้ามรัน Tasks 1–6 เต็มชุด ห้ามรัน Browser matrix ห้ามรัน full PHPUnit

## รายงาน

เขียน `.superpowers/sdd/2026-08-27-tpl00-tpl01-foundation/task-2-corrective-report.md` โดยมี:

- RED command และ decisive failure
- GREEN command และจำนวน test/assertion
- mutation commands/resultsทุกตัว
- exact RED/GREEN tree hashes
- final patch path list
- real index treeก่อน/หลัง
- exclusion confirmation
- concerns

ห้าม commit, stage real index หรือ push; controller จะส่ง review และ delegate gitops หลัง gateผ่าน
