# TPL-02 Standalone auth และ reset email

ย้าย Login, Forgot Password, Reset Password และ reset email โดยคง CI3 document, route และ asset order. CI4 security seam ใช้ CSRF, generic response และ hashed single-use token.

## Source และ target

| CI3 source | CI4 target |
|---|---|
| login.php | app/Views/login.php |
| forgotPassword.php | app/Views/forgot_password.php |
| newPassword.php | app/Views/reset_password.php |
| email/resetPassword.php | CI4 reset delivery email renderer |

## งานและ gate

1. สร้าง failing comparator สำหรับ document, field, route, dependency order และ reset email body.
2. คัด CI3 DOM/asset graph ลง target; adapter ได้เฉพาะ CSRF, generic result และ single-use token.
3. ทดสอบ GET/POST, throttling, replay, CSRF และ email link จริงผ่าน loopback transport.
4. เก็บ normalized DOM, network, interaction และ screenshot CI3/CI4 ที่ 1440x900 กับ 390x844.

## ข้อห้าม

- ห้าม expose email หรือ token ใน legacy URL, error, log หรือ debug output.
- ห้ามถือ focused HTTP test เป็น visual parity proof.
