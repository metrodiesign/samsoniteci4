# Task 7 exact-gate root cause

รายงานนี้จำแนก `35` failures และ `2` errors ของ exact candidate แล้วแยก contamination ใน base commit ออกจาก Task 7 candidate อย่างเคร่งครัด. ไม่ได้รัน PHPUnit เต็มชุด, PHPStan, `scripts/ci-check.sh`, Tasks 1–6 หรือ Browser matrix ซ้ำ.

## Candidate identity และข้อจำกัดของ raw log

| รายการ | ค่า |
|---|---|
| Base commit | `6799684db6de09936122d2ae25a5461a878b0eb3` |
| Exact candidate tree | `e51837eec685b090f1072a8b2887fa7008f4c587` |
| Assembly | temporary `GIT_INDEX_FILE` จาก base, whole-file 21 paths, แล้ว apply cached `task-7-route.patch` |
| Task 7 composition | whole-file Menu, Order และ Route tests ยังคงอยู่; ไม่มี dirty hunk ของ WP03H, WP03J หรือ WP00C ถูก fold เข้า candidate |
| Gate evidence | `task-7-exact-gates-report.md:39-41` ระบุ `396 tests, 35 failures, 2 errors` |
| Index safety | real index `c6ce38a8953cb1dedf08e35446b3195347139425` ไม่เปลี่ยน |

Raw full-gate report บันทึกเพียงจำนวนรวมและ error สองรายการ ไม่ได้เก็บรายการ failure ครบทุกชื่อ. เพื่อจำแนกชื่อและ signature จึงรันเฉพาะ focused archive classes ที่จำเป็นหลังจากมี exact result อยู่แล้ว; ยอดรวม focused ตรงกับ raw gate คือ `35 F + 2 E = 37`.

## Classification summary

| Class | Failure | Error | รวม | Root cause | Corrective owner |
|---|---:|---:|---:|---|---|
| A. Task 4 access-denied omission | 25 | 1 | 26 | `404c077` commit responder/tests แต่ไม่รวม filters และ view | Task 4 corrective follow-up |
| B. Task 2 tracking contamination and adapter omission | 5 | 0 | 5 | `e2cd894` commit test rewrite เกิน scope และไม่ commit minimal query adapter | Task 2 corrective follow-up |
| C. Task 4 login assertions contamination | 1 | 0 | 1 | `404c077` เปลี่ยน assertion login 3 จุดเกิน Task 4 | Task 4 corrective follow-up |
| D. Task 7 Node harness gap | 3 | 0 | 3 | image ไม่มี `nodejs` | Task 7 harness correction |
| E. Task 7 WP00C image-copy gap | 1 | 1 | 2 | image ไม่ copy route disposition JSON | Task 7 harness correction |
| F. Task 7 404 test fixture omission | 0 | 1 | 1 | direct error view renderไม่ส่ง `$message` | Task 7 test correction |
| G. Task 6 closure-count contamination | 1 | 0 | 1 | count `118` พึ่ง templates นอก exact candidate | Task 7 test correction |
| H. Task 2 parent presentation assertion | 1 | 0 | 1 | restored test assert not-found markupนอก query adapter scope | Task 2 corrective follow-up |
| I. Task 6 stale recursive asset list | 1+ | 0 | 1+ | fixed asset listพึ่ง WP03 templates นอก exact candidate | Task 7 test correction |
| J. Task 7 Git harness gap | 1 | 0 | 1 | tracked-closure testเรียก Git แต่ imageไม่มี executable | Task 7 harness correction |
| K. Task 7 archive Git-metadata gap | 1 | 0 | 1 | archive imageไม่มี `.git` สำหรับ tracked-state subprocess | Task 7 test correction |
| **รวมเดิม** | **35** | **2** | **37** |  |  |

## Detailed classification

### A. Task 4 access-denied omission — 25 failures, 1 error

| File | Test name หรือ dataset | Count | Signature |
|---|---|---:|---|
| `tests/ci4/AccessDeniedHttpTest.php` | `testAuthorizationDenialRendersCi3BodyInAuthenticatedAdminChrome` | 1 F | expected HTML, actual JSON |
| `tests/ci4/AccessDeniedHttpTest.php` | `testBranchlessDenialUsesTheSameHtmlRepresentationWithoutController` | 1 F | expected HTML, actual JSON |
| `tests/ci4/AccessDeniedHttpTest.php` | `testAcceptNegotiationIsExplicitAndFailClosed` 20 datasets | 20 F | HTML casesตอบ JSON, JSON body ไม่ตรง fixed JSON contract |
| `tests/ci4/AccessDeniedHttpTest.php` | `testAjaxAndUnauthenticatedRequestsCannotSelectHtml` | 1 F | expected `{"error":"forbidden"}`, actual pretty JSON |
| `tests/ci4/AccessDeniedHttpTest.php` | `testMethodDoesNotChangeDenialRepresentationOrStatus` | 1 F | expected HTML, actual JSON |
| `tests/ci4/AccessDeniedHttpTest.php` | `testNormalLayoutDoesNotInheritTheAccessDeniedProfileAcrossRenders` | 1 F | expected access-denied header one instance, actual zero |
| `tests/ci4/MenuHttpTest.php` | `testSharedRuntimeAssetClosureExistsAndIsGitTracked` | 1 E | `Invalid file: "access_denied.php"` |

- **Missing paths**: `app/Filters/AuthorizationFilter.php`, `app/Filters/BranchlessFilter.php` และ `app/Views/access_denied.php` เวอร์ชัน working tree.
- **Root cause evidence**: `404c077` เปลี่ยนเพียง `BaseController`, `AccessDeniedResponder`, `AdminLayoutPresenter` และ tests; ไม่รวม two filters หรือ view. Candidate filter จึงยังตอบ `setJSON()` ตรง และ view direct render ล้ม.
- **Corrective scope**: Task 4 follow-up เพิ่ม 3 paths นี้ร่วมกับ corrective assertion revert ใน Class C; ไม่สร้าง independent WP03K checkpoint.
- **Confidence**: สูง. Focused outcome เท่ากับ `25 F + 1 E` และ caller sweep ครบทั้ง authorization กับ branchless filter.

### B. Task 2 tracking contamination and adapter omission — 5 failures

| File | Test name | Count | Signature |
|---|---|---:|---|
| `tests/ci4/PublicTrackingHttpTest.php` | `testCanonicalQueryTakesPrecedenceAndLegacyQueryUsesTheSameExactLookup` | 1 F | ไม่พบ `SYNTHETIC RETURN` |
| `tests/ci4/PublicTrackingHttpTest.php` | `testInvalidQueryShapesAndRouteValuesStayInPublicNoDataFlowWithoutReflection` | 1 F | markup marker ยังปรากฏใน old presentation output |
| `tests/ci4/PublicTrackingHttpTest.php` | `testTrackingFormShowsLanguageSpecificPopupAndLegacyControls` | 1 F | expected legacy title/control hierarchy |
| `tests/ci4/PublicTrackingHttpTest.php` | `testTrackingFormBackgroundCascadeKeepsStaticMobileFallbackForEveryPublishedCombination` | 1 F | missing mobile background cascade |
| `tests/ci4/PublicTrackingHttpTest.php` | `testResultUsesCi3HierarchyLanguageLabelsAndNoTrackingIdParagraph` | 1 F | old outputยังมี tracking-ID paragraph |

- **Test contamination**: `e2cd894:tests/ci4/PublicTrackingHttpTest.php` เปลี่ยน public presentation, asset graph และ hierarchy assertions เกิน intended Task 2 no-trim regression. Five exact failuresข้างต้นยังคงจัดเป็น contaminated WP03J assertions.
- **Production omission**: `6799684:app/Controllers/Tracking.php:30-36` อ่านเพียง `tracking_id`, trim input และไม่มี legacy `searchText` fallback หรือ raw-string allowlist. ดังนั้น Task 2 ไม่ใช่เพียง test correction.
- **Minimal production hunk**: ปรับเฉพาะ `Tracking::fromQuery()` ให้ canonical ใช้ก่อนเมื่อไม่ใช่ `null`, fallback ไป `searchText` เมื่อ canonical เป็น `null`, validate raw string ด้วย `/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/D` และไม่เรียก `trim()`. ใช้ timeline/render เดิมต่อไป.
- **Excluded production scope**: ห้ามเปลี่ยน `fromSegment()`, `fromValue()`, `notFound`, title, profile, view หรือ dirty WP03J paths.
- **Corrective test**: extract `tests/ci4/PublicTrackingHttpTest.php` blob from `e2cd894^`, append the following method before the class closing brace, then write that blob to the temporary index. This avoids reading any dirty WP03J version.

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

- **Proof dimensions**: valid canonical plus conflicting legacy proves normal precedence; valid legacy alone proves fallback; canonical whitespace plus valid legacy and array canonical plus valid legacy prove fallback occurs only for `null`, never invalid canonical input.
- **Confidence**: สูง. Corrective removes five unrelated presentation assertions while adding the smallest executable query-adapter contract that base codeขาด.

### C. Task 4 login assertions contamination — 1 failure

| File | Test name | Count | Signature |
|---|---|---:|---|
| `tests/ci4/MenuHttpTest.php` | `testAnonymousLayoutRendersNoNavigationAndNeedsNoMenuTables` | 1 F | expected `class="banner-cms"`, candidate login bodyต่างจาก assertion |

- **Root cause evidence**: `git diff 404c077^ 404c077 -- tests/ci4/MenuHttpTest.php` ที่ hunk `@@ -244,10 +270,10 @@` เปลี่ยน assertion login exactly 3 บรรทัด: `login-banner` เป็น `class="banner-cms"`, `>Tracking<` เป็น `<b>Tracking</b>` และ `forgot-password` เป็น `forgotPassword`. Assertion `Forgot Password` ไม่เปลี่ยน.
- **Corrective scope**: return the three changed assertions to parent values in the same Task 4 corrective follow-up as Class A. ไม่เพิ่ม `Login.php`, `login.php` หรือ WP03H paths.
- **Confidence**: สูง. Dirty Task 7 candidate Menu file ไม่ได้เป็นต้นทาง; incompatible assertions อยู่ใน Task 4 base commit.

### D. Task 7 Node harness gap — 3 failures

| File | Test name | Count | Signature |
|---|---|---:|---|
| `tests/ci4/MenuHttpTest.php` | `testOrderValidationScriptsPreservePinnedSyntaxBehavior` | 1 F | `/usr/bin/env: 'node': No such file or directory` |
| `tests/ci4/OrderHttpTest.php` | `testUploadAdapterFollowsExactCallbacksAcrossOperationAndContextBoundaries` | 1 F | exit `127`, no Node executable |
| `tests/ci4/OrderHttpTest.php` | `testUploadAdapterDeletesTheFileBoundToTheClickedDuplicateNamePreview` | 1 F | exit `127`, no Node executable |

- **Root cause evidence**: `Dockerfile.ci4:6-11` apt install list lacks `nodejs`, while exact Task 7 tests execute Node behavior.
- **Corrective scope**: amend the existing Debian apt line to install `nodejs`; host Node and deleting behavioral tests are not valid substitutes.
- **Verification requirement**: capture `node --version` from the built candidate image before rerunning the three methods. Package version resolves from the Debian repository at build time; the base-image digest does not pin the apt package version.
- **Confidence**: สูง. Three callers, one pre-execution signature, one runner dependency.

### E. Task 7 WP00C image-copy gap — 1 failure, 1 error

| File | Test name | Count | Signature |
|---|---|---:|---|
| `tests/ci4/RouteHttpTest.php` | `testAll178Ci3ExplicitRoutesHaveDeterministicCi4Disposition` | 1 F | `/app/tests/wp00c/ci4-route-disposition.json` absent |
| `tests/ci4/RouteHttpTest.php` | `testEveryMappedCi3ReplacementResolvesToADefinedCi4Route` | 1 E | `file_get_contents(...ci4-route-disposition.json)` fails |

- **Root cause evidence**: Git tree `e51837e...` already contains `tests/wp00c/ci4-route-disposition.json`; `Dockerfile.ci4:22` copies only `tests/ci4`, therefore archive image lacks the JSON.
- **Corrective scope**: add one exact `COPY` for `tests/wp00c/ci4-route-disposition.json` to the same path in image. Do not copy the entire directory.
- **Confidence**: สูง. Both readers fail at the identical image boundary.

## Corrected minimal closure

The following is the minimal combined path plan. It preserves Task 7's approved whole-file composition and keeps all unrelated dirty work out.

| Corrective unit | Exact paths or change | Outcomes addressed |
|---|---|---:|
| Task 2 corrective test patch | temporary-index patch: restore `tests/ci4/PublicTrackingHttpTest.php` to `e2cd894^`, add standalone query-adapter method only | 5 F contamination |
| Task 2 production hunk | temporary-index hunk in `app/Controllers/Tracking.php`, limited to `Tracking::fromQuery()` | missing no-trim/fallback contract |
| Task 4 corrective follow-up | `app/Filters/AuthorizationFilter.php`, `app/Filters/BranchlessFilter.php`, `app/Views/access_denied.php`; revert three changed login assertions in `tests/ci4/MenuHttpTest.php` | 26 outcomes + 1 F |
| Task 7 harness correction | `Dockerfile.ci4`: add `nodejs` to existing apt line and exact `COPY tests/wp00c/ci4-route-disposition.json tests/wp00c/ci4-route-disposition.json` | 3 F + 1 F + 1 E |
| Existing Task 7 | 21 whole files plus `task-7-route.patch` | retained unchanged |

### Task 4 and Task 7 composition ordering

1. แก้เฉพาะ 3 assertion lines บน working `tests/ci4/MenuHttpTest.php` ให้กลับเป็น parent login values โดยรักษา Task 7 hunks อื่นทั้งหมดในไฟล์.
2. Stage และ commit Task 4 เฉพาะ 3-line assertion hunk, filters 2 ไฟล์ และ `app/Views/access_denied.php`; ห้าม stage whole Menu file ใน Task 4 checkpoint.
3. หลัง Task 4 commit, working Menu ต้องคงทั้ง Task 7 edits และ corrected parent login values เพื่อให้การ stage whole-file Menu สำหรับ Task 7 ไม่ reintroduce WP03H values.
4. Temporary candidate ที่ add whole-file Menu blob ต้องใช้ corrected working blob; ถ้า add blob รุ่นก่อน correction ไปแล้ว ให้ apply 3-line correction หลัง add blob ก่อนสร้าง candidate tree.

Do not stage the dirty whole `Tracking.php` file. The Task 2 controller change must be a hunk assembled in the temporary candidate so WP03J work remains excluded. No WP03H, WP03J dirty presentation path, WP00C dirty production path, contact path, password-reset path or broad `Routes.php` hunk belongs in this closure.

## Ownership trace

| Item | Owner | Evidence |
|---|---|---|
| PublicTracking test rewrite | Task 2 corrective follow-up | `e2cd894` diff proves more than the intended two whitespace cases entered base |
| `Tracking::fromQuery()` adapter hunk | Task 2 corrective follow-up | `6799684:app/Controllers/Tracking.php:30-36` proves canonical-only trimmed lookup |
| Filters and access-denied view | Task 4 corrective follow-up | `404c077` committed responder/tests but omitted required caller/view paths; Task 4 report describes the responder flow at `task-4-report.md:70-89` |
| Three changed login assertions | Task 4 corrective follow-up | `git diff 404c077^ 404c077 -- tests/ci4/MenuHttpTest.php`, hunk `@@ -244,10 +270,10 @@` |
| Node and exact WP00C copy | Task 7 harness correction | `Dockerfile.ci4:6-11,20-25`; tests are in approved Task 7 whole-file set |
| Candidate route patch | Task 7 only | `task-7-route.patch:4-8`; never stage all `Routes.php` |

## Temporary-candidate experiment sequence

Every experiment starts from the same exact baseline: base `6799684`, the fixed 21 Task 7 whole-file paths and the cached route patch. Do not carry a prior experiment index into the next one.

1. **A — Task 2 isolate**: build every tree from the same exact baseline and use the exact focused filter below.

   ```bash
   vendor/bin/phpunit --configuration phpunit.xml.dist \
     tests/ci4/PublicTrackingHttpTest.php \
     --filter 'testCanonicalAndLegacyQueryAdapterPreservesPrecedenceAndNoTrimContract|testSearchRejectsUnknownWildcardAndOversizedTrackingIdsWithoutPartialMatch'
   ```

   - **RED tree**: parent-restored test blob plus the verbatim standalone method, with base `Tracking.php`. The focused command must RED because valid legacy is ignored and canonical whitespace is trimmed into a successful lookup.
   - **GREEN tree**: add only the exact `fromQuery()` hunk. The focused command must GREEN; parent wildcard and over-100-character input prove the regex branch.
   - **Mutation 1**: add `trim($value)` in the Green hunk. Both whitespace URLs must RED.
   - **Mutation 2**: change fallback condition so invalid canonical falls through to valid legacy. The canonical-whitespace-plus-legacy and array-canonical-plus-legacy cases must RED.
   - **Insertion method**: obtain the parent blob with `git show e2cd894^:tests/ci4/PublicTrackingHttpTest.php`, insert the verbatim method before its final class brace in a temporary file, then update only the temporary index entry. Never stage the working-tree test file or whole dirty `Tracking.php`.
2. **B — Task 4 isolate**: apply only the three source/view paths and three-assertion revert. Run `AccessDeniedHttpTest.php` plus the two Menu methods; expect `25 F + 1 E + 1 F` closed.
3. **C — Node isolate**: apply only the `nodejs` Dockerfile change. Build archive image, capture `node --version`, then run exactly the three Node-dependent methods; expect three outcomes closed.
4. **D — WP00C isolate**: from fresh baseline, apply only the exact JSON `COPY` Dockerfile change. Assert image has `/app/tests/wp00c/ci4-route-disposition.json`, then run exactly the two Route methods; expect `1 F + 1 E` closed.
5. **E — combined**: only after A–D focused results are GREEN, combine their reviewed corrective patches with existing Task 7 composition and run full exact gates once.

## Decision, exclusions และ open uncertainties

### Decision

**ต้องมี corrective checkpoints ก่อน Task 7 checkpoint.** Task 2 and Task 4 base commits carry test/source incompleteness that exact archive gates expose; Task 7 must not hide them by folding unrelated dirty work. Task 7 additionally owns the smallest necessary Dockerfile harness correction because its approved behavioral tests require it.

### Explicit exclusions

- dirty WP03J presentation changes outside the exact `Tracking::fromQuery()` hunk
- WP03H standalone auth/password-reset paths and evidence
- contact, error-404, master/user-form, `admin.css` and `scripts/ci-check.sh`
- `.DS_Store`, `.pi/`, `.superpowers/` scratch and all unrelated trace reports
- all non-Task-7 `Routes.php` hunks

### Open uncertainties

- The Task 2 parent restore must preserve only the new standalone adapter method and two intended whitespace checks, without reviving another historical assertion change.
- The Task 4 follow-up must confirm the three reverted assertions do not erase an independently approved contract.
- Debian repository package version for Node must be recorded from the built image, not inferred from the host or base-image digest.
- After A–D, run the full exact suite once because early assertions can mask further failures.

## Class sweep

- **Task 2 contamination**: all five exact Tracking presentation failures remain excluded; the new standalone method exercises only canonical precedence, legacy fallback and no-trim rejection at the shared `fromQuery()` boundary.
- **Task 4 access denial**: swept `AuthorizationFilter`, `BranchlessFilter`, direct view render and all 20 Accept datasets; three omitted paths cover the shared responder boundary.
- **Task 4 login contamination**: all three altered anonymous-login assertions are reverted together; unchanged `Forgot Password` assertion remains untouched and no WP03H path is added.
- **Node**: candidate has exactly three Node callers, all closed by the single in-image executable prerequisite.
- **WP00C**: both Route readers use the same JSON; exact single-file image copy covers both without broad directory copy.
- **Candidate scope**: Menu, Order and Route whole files remain approved Task 7 inputs; no dirty WP03H/WP03J/WP00C hunk is in the candidate path set.
