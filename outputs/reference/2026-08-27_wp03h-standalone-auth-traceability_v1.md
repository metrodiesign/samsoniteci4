# WP03H standalone auth traceability v1

เอกสารนี้ผูกหน้า auth 3 หน้าและ asset ที่ใช้จริงจาก CI3 pin `ee1c95e59ec0eb51a8886e24ed9dda0a5b49d1a6` ไป CI4. สถานะภาพเป็นผลจาก implementation handoff; verifier ยังต้องปิด normalized DOM และ visual comparison.

## Route และ source mapping

| หน้า | CI3 route/controller/model/view | CI4 route/controller/workflow/view | disposition |
|---|---|---|---|
| Login | `/login` -> `Login::index/loginMe` -> `Login_model::loginMe` -> `application/views/login.php` | `GET /login`, `POST /loginMe` -> `Login::index/authenticate` -> `LoginService` -> `app/Views/login.php` | `PRESERVE`, เพิ่ม CSRF/session regeneration/rate limit/generic error |
| Forgot password | `/forgotPassword`, `POST /resetPasswordUser` -> `Login::forgotPassword/resetPasswordUser` -> `Login_model::checkEmailExist/resetPasswordUser` -> `application/views/forgotPassword.php` | `GET /forgot-password`, `GET /forgotPassword`, `POST /resetPasswordUser` -> `PasswordReset::forgotForm/requestResetForm` -> `ResetRequestWorkflow` -> `app/Views/forgot_password.php` | `PRESERVE`, เปลี่ยน response เป็น enumeration-resistant generic result |
| Reset password | `/resetPasswordConfirmUser/{activation}/{email}`, `POST /createPasswordUser` -> `Login::resetPasswordConfirmUser/createPasswordUser` -> `Login_model::checkActivationDetails/createPasswordUser` -> `application/views/newPassword.php` | `GET /reset-password?token=`, legacy GET reset alias -> redirect `/forgotPassword`, `POST /createPasswordUser` -> `PasswordReset::resetForm/completeResetForm` -> `PasswordResetWorkflow` -> `app/Views/reset_password.php` | `CORRECT_AND_REBASELINE`, ใช้ hashed expiring single-use token และไม่ฝัง email ใน URL |
| Reset email | `email/resetPassword.php` | `app/Views/email/reset_password.php` -> `ResetEmailRenderer` -> loopback delivery | `ADAPTED_FOR_CI4`, canonical token link และไม่มี recipient/name ใน body |

JSON API เดิม `POST /password-reset/request` และ `POST /password-reset/complete` เรียก private operation ชุดเดียวกับ legacy form adapters. Auto routing ยังคงปิด.

## Template และ DOM disposition

| contract | Login | Forgot | Reset |
|---|---|---|---|
| full standalone document | `PRESERVE` | `PRESERVE` | `PRESERVE` |
| CI3 body class และ hierarchy | `login-page`, banner, login box, footer | `login-page`, banner, login box, footer | `login-page`, login box, ไม่มี footerตาม CI3 |
| CI3 field contract | `username`, `password` | `login_email` | `email`, `activation_code`, `password`, `cpassword` |
| form endpoint | `/loginMe` | `/resetPasswordUser` | `/createPasswordUser` |
| security allowlist | hidden CSRF, generic error | hidden CSRF, generic result | hidden CSRF, canonical token, readonly server email, blank passwords, generic invalid result |
| normalized DOM proof | focused contract assertionsผ่าน | focused contract assertionsผ่าน | focused contract assertionsผ่าน; full CI3/CI4 normalizationรอ verifier |
| visual proof 1440x900 / 390x844 | รอ verifier | รอ verifier | รอ verifier |

ไม่มีหน้าใดใช้ `layout.php` หรือ `public/assets/css/admin.css`.

## Dependency order

| หน้า | CSS ตามลำดับ | JavaScript ตามลำดับ |
|---|---|---|
| Login | Bootstrap -> Font Awesome local -> AdminLTE -> CustomAdmin -> main | jQuery 2.1.4 -> Bootstrap |
| Forgot | Bootstrap -> Font Awesome local -> AdminLTE -> CustomAdmin -> main | jQuery 2.1.4 -> Bootstrap |
| Reset | Bootstrap -> Font Awesome 4.3.0 local -> AdminLTE | html5shiv 3.7.2 -> Respond.js 1.4.2 -> jQuery 2.1.4 -> Bootstrap |

ไม่มี frontend dependency upgrade หรือ replacement. `AdminLTE.min.css` และ `CustomAdmin.css` เปลี่ยนเฉพาะ import URL เป็น local เพื่อไม่ให้ auth document ออก third-party runtime request.

## Dependency recovery round 2

หน้า Reset โหลด dependency ที่ CI3 pin จาก local versioned path แล้ว. Font Awesome CSS และ script ทั้งสองคง license/header ของ upstream artifact.

| Artifact | CI3 pinned URL | Local path | Source/version | SHA-256 | Disposition |
|---|---|---|---|---|---|
| Font Awesome CSS | `https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css` | `assets/font-awesome/4.3.0/css/font-awesome.min.css` | `https://raw.githubusercontent.com/FortAwesome/Font-Awesome/v4.3.0/css/font-awesome.min.css`, 4.3.0 | `541ac58217a8ade1a5e292a65a0661dc9db7a49ae13654943817a4fbc6761afd` | `PRESERVE_LOCAL_SECURITY` |
| Font Awesome fonts | stylesheet-relative `../fonts/fontawesome-webfont.*` | `assets/font-awesome/4.3.0/fonts/` | `https://raw.githubusercontent.com/FortAwesome/Font-Awesome/v4.3.0/fonts/`, 4.3.0 | eot `cbb644d0ee730ea57dd5fbae35ef5ba4a41d57a254a6b1215de5c9ff8a321c2d`; svg `bfdef833219a6edffd9c3cbc28db72739d22bb4d20cc2e2f8d56a7a4d408a206`; ttf `9e540a087924a6e64790149d735cac022640e4fa6bff6bd65f5e9f41529bf0b3`; woff `e3870de89716b72cb61a4bba0e17c75783b361cdaba35ea96961c3070bd8ca18`; woff2 `aadc3580d2b64ff5a7e6f1425587db4e8b033efcbf8f5c332ca52a5ed580c87c` | `PRESERVE_LOCAL_SECURITY` |
| html5shiv | `https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js` | `assets/html5shiv/3.7.2/html5shiv.min.js` | `https://raw.githubusercontent.com/aFarkas/html5shiv/3.7.2/dist/html5shiv.min.js`, 3.7.2 | `e0eac80838c161f29e7c46d54fbc044d12cd164baae13255e562c6be3aa91809` | `PRESERVE_LOCAL_SECURITY` |
| Respond.js | `https://oss.maxcdn.com/respond/1.4.2/respond.min.js` | `assets/respond/1.4.2/respond.min.js` | `https://raw.githubusercontent.com/scottjehl/Respond/1.4.2/dest/respond.min.js`, 1.4.2 | `83a8807ef669fa70d0d9375347f5552897f76c6ae8e2e6f97ef592595462d8d1` | `PRESERVE_LOCAL_SECURITY` |

MaxCDN URL ถูก sandbox block ระหว่าง mirror. Artifact ถูกดึงจาก official version tag และตรึง SHA-256 ใน test; ไม่มีการ substitute version.

## Local asset checksums

| Asset ใต้ `public/assets` | SHA-256 จาก CI3 pin |
|---|---|
| `bootstrap/css/bootstrap.min.css` | `f75e846cc83bd11432f4b1e21a45f31bc85283d11d372f7b19accd1bf6a2635c` |
| `bootstrap/js/bootstrap.min.js` | `53964478a7c634e8dad34ecc303dd8048d00dce4993906de1bacf67f663486ef` |
| `bootstrap/fonts/glyphicons-halflings-regular.eot` | `13634da87d9e23f8c3ed9108ce1724d183a39ad072e73e1b3d8cbf646d2d0407` |
| `bootstrap/fonts/glyphicons-halflings-regular.svg` | `42f60659d265c1a3c30f9fa42abcbb56bd4a53af4d83d316d6dd7a36903c43e5` |
| `bootstrap/fonts/glyphicons-halflings-regular.ttf` | `e395044093757d82afcb138957d06a1ea9361bdcf0b442d06a18a8051af57456` |
| `bootstrap/fonts/glyphicons-halflings-regular.woff` | `a26394f7ede100ca118eff2eda08596275a9839b959c226e15439557a5a80742` |
| `bootstrap/fonts/glyphicons-halflings-regular.woff2` | `fe185d11a49676890d47bb783312a0cda5a44c4039214094e7957b4c040ef11c` |
| `font-awesome/css/font-awesome.css` | `295074933a25ae5d6646f86705412ae194ca64508e04984857c61ef495c66ec2` |
| `font-awesome/fonts/fontawesome-webfont.eot` | `e511891d3e01b0b27aed51a219ced5119e2c3d0460465af8242e9bff4cb61b77` |
| `font-awesome/fonts/fontawesome-webfont.svg` | `d5b5636ebb2e124810436200086b74a60dff9e8a8be7f4a1088bf5d3458bc3c8` |
| `font-awesome/fonts/fontawesome-webfont.ttf` | `4d6eb9e9d852a2a6f74e7c428456a2f07fc63a1613d10192d8ed3401d9da5ffa` |
| `font-awesome/fonts/fontawesome-webfont.woff` | `199411f659f41aaccb959bacb1b0de30e54f244352a48c6f9894e65ae0f8a9a1` |
| `js/jQuery-2.1.4.min.js` | `f16ab224bb962910558715c82f58c10c3ed20f153ddfaa199029f141b5b0255c` |
| `images/bg-login.jpg` | `476e5512aecb8bc658772d57716154e9af1ab5a5fad628237421766437f06819` |
| `images/img-footer.png` | `688aeee5af3396a6a47aeb61448ac6606ca0f00eb46d698781b88844bf5d1a7b` |

`PasswordResetPageHttpTest::testLocalLegacyAssetsMatchCi3PinChecksums` ตรวจ checksum ชุดนี้ทุกครั้ง.

## Dependency recovery round 3: recursive local CSS graph

`PasswordResetPageHttpTest::testAuthCssGraphIsLocalResolvedAndVersionPinned` เริ่มจาก CSS ที่ทั้งสาม auth views โหลด, เดิน `@import` และ `url()`, แล้ว fail เมื่อพบ `http://`, `https://`, `//` หรือ artifact local หาย. ครอบ `AdminLTE.min.css`, `CustomAdmin.css` และ sibling chain ของ login/forgot/reset.

| Artifact | Local path | Source/retrieval | SHA-256 | License/disposition |
|---|---|---|---|---|
| Font Awesome 4.7.0 CSS | `assets/font-awesome/4.7.0/css/font-awesome.min.css` | official tag `v4.7.0`, commit `a8386aae19e200ddb0f6845b5feeee5eb7013687` | `799aeb25cc0373fdee0e1b1db7ad6c2f6a0e058dfadaa3379689f583213190bd` | upstream `README.md` SHA `610427fe917f2b51ef69b5bfa9a7bfe3fcba59b1ccaf86a500a47e5fa1da322b` บันทึก provenance ว่า CSS MIT/font SIL OFL 1.1 แต่ไม่ bundle เพราะ author email ชน candidate-tree PII policy; runtime CSS `ADAPTED_FOR_CI4` เฉพาะ import URL |
| Font Awesome 4.7.0 fonts | `assets/font-awesome/4.7.0/fonts/` | same official tag; eot/svg/ttf/woff/woff2 | eot `7bfcab6db99d5cfbf1705ca0536ddc78585432cc5fa41bbd7ad0f009033b2979`; svg `ad6157926c1622ba4e1d03d478f1541368524bfc46f51e42fe0d945f7ef323e4`; ttf `aa58f33f239a0fb02f5c7a6c45c043d7a9ac9a093335806694ecd6d4edc0d6a8`; woff `ba0c59deb5450f5cb41b3f93609ee2d0d995415877ddfa223e8a8a7533474f07`; woff2 `2adefcbc041e7d18fcf2d417879dc5a09997aa64d675b7a3c4b6ce33da13f3fe` | `PRESERVE_LOCAL_SECURITY` |
| Source Sans Pro stylesheet/fonts | `assets/fonts/source-sans-pro/` | Original `https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic` fetched 2026-08-27 with `Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36`, HTTP 403. Fallback official `google/fonts` commit `a7bc06df72f7e1b4e20342e2b04423e579222316` before removal; `METADATA.pb` proves 300/400/600/700 normal and 300/400/600 italic. | CSS `31105045a28207422c3da95d2dbade1d4b26790035a903d8c5263fe999675f8f`; OFL `fce9f9e2fb268507a89fceea0b3eccc044f39fc3492968a04fd9e04df5ae95fa`; all seven TTF SHA pinned by focused test | `OFL.txt`; `COMPATIBILITY_SHIM`, no floating/runtime reference |
| Local image references | `assets/dist/img/boxed-bg.jpg`, `uploads/web/` | CI3 pin for `boxed-bg.jpg`; archived CI3 deployment snapshot for ignored `uploads/web` assets | boxed `5487391fa5db6155709522886beebc00fd7433ffb7ae5f63e585497cd5174ae5`; contact `2520b9e21373a7822bf2388cd043684a8e0bcdc41071c6a562d539964e7f038f`; track `16b99ac15ba78c5dd6a462de19b8c349747b7621301a7a1cb3858e09753c813a` | `main.css` corrected stale `contact_mobile.jpg` to existing byte-identical `contact_mobile.png`; no auth selector uses it |

## Runtime และ visual disposition

- **HTTP/runtime**: focused FeatureTest ขับ GET/POST, route alias, CSRF, known/unknown workflow, rate limit, token lifecycle และ password/session mutationจริง.
- **Reset email**: `ResetEmailRendererTest` และ loopback worker ขับ standalone HTML, canonical link และ no-recipient body.
- **Browser capability**: runtime browser discovery คืน `No browser is available` และ browser list ว่าง.
- **DOM**: contract assertions และ dependency orderผ่าน; full normalized CI3/CI4 diff ยังไม่ถูก capture.
- **Visual**: 1440x900 และ 390x844 ยังไม่ถูก capture; ห้ามตีความเป็น PASS.
- **Round 3**: CSS dependency graph local และ checksum/version pinned; browser, normalized DOM และ visual disposition ยังไม่เปลี่ยน.

STATUS: IMPLEMENTED_AWAITING_BROWSER_VERIFICATION
