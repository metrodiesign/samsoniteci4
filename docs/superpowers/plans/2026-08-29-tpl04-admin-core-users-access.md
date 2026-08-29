# TPL-04 Admin core, users, access และ application 404

รักษา admin shell, dashboard, users, access denied และ application 404 โดยใช้ CI3 AdminLTE hierarchy.

## Source และ target

| CI3 source | CI4 target |
|---|---|
| dashboard.php | app/Views/dashboard.php |
| addNew.php, editOld.php, users.php | users_form.php, users_list.php |
| access.php | access_denied.php |
| 404.php | errors/html/error_404.php |

## งานและ gate

1. Comparator ต้องตรวจ session-derived menu, branch autocomplete และ exact shell hierarchy.
2. JSON/AJAX denial ต้องไม่ render HTML shell.
3. ทดสอบ role, status, content negotiation และ user form mutation.
4. Browser proof ต้องขับ sidebar, DataTables และ responsive layout.
