# TPL-09 Framework errors และ static HTML dispositions

ปิด 15 CI3 framework error/static template records ด้วย migration หรือ evidence disposition.

## Work split

| Group | Action |
|---|---|
| HTML/CLI errors | map CI3 error contract to CI4 framework view or compatibility shim |
| Static index files | mark NOT_USED_WITH_EVIDENCE only when no runtime caller exists |
| PDF/static documents | preserve byte artifact or record signed non-use disposition |

## งานและ gate

1. Trace runtime caller ของทุก source record.
2. Comparator ต้องตรวจ status, content type, safe error body และ CLI behavior.
3. Static disposition ต้องมี caller search, command และ evidence hash.
4. Capture browser DOM/visual เฉพาะ HTML error pages ที่ user-visible.
