# TPL-05 Master, menu, background และ contact listing

ย้าย CI3 master-data pages 37 files โดย reuse CI3 admin shell และ exact caller asset graph.

## Work split

| Group | CI4 target |
|---|---|
| Master lists | app/Views/master_list.php |
| Master forms | app/Views/master_form.php |
| Menu | menu_list.php, menu_form.php |
| Background | background_list.php, background_form.php |
| Contact listing | contact_listing.php |

## งานและ gate

1. อ่าน CI3 source ทุก file ก่อนเปลี่ยน target group.
2. เพิ่ม comparator ต่อ list/form state, fields, default, upload และ row action.
3. ทดสอบ role matrix, CRUD, background publication และ local assets.
4. Capture DOM/interaction/visual ต่อ observable page state.
