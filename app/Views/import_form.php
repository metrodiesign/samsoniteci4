<?php /** @var string $kind */ /** @var string $caption */ /** @var string $legacyPreview */
?>
<section>
    <div class="card">
        <h3 class="box-title"><?= esc($caption) ?></h3>
<form role="form" id="addUser" method="post" action="<?= base_url($legacyPreview) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label for="import-file">XLSX or XLS workbook</label>
    <input id="import-file" name="file" type="file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" required>
    <button type="submit">Upload</button>
    <button type="reset">Reset</button>
</form>    </div>
</section>
