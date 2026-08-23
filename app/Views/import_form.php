<?php /** @var string $kind */ ?>
<section><h1>Import <?= esc($kind) ?></h1>
<form method="post" action="/imports/<?= esc($kind) ?>/preview" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label for="import-file">XLSX or XLS workbook</label>
    <input id="import-file" name="file" type="file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" required>
    <button type="submit">Preview</button>
</form></section>
