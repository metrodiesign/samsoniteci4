<?php

/** @var list<string> $fields */
/** @var list<array<string, mixed>> $rows */
/** @var array<string, mixed>|null $row */
$action = '/backgrounds' . ($row === null ? '' : '/' . (int) $row['id']);
?>
<section aria-labelledby="background-title">
    <h1 id="background-title">Website backgrounds</h1>
    <form method="post" action="<?= esc($action) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label for="background-status">Status</label>
        <select id="background-status" name="status" required>
            <option value="1" <?= (int) ($row['status'] ?? 1) === 1 ? 'selected' : '' ?>>Publishing</option>
            <option value="2" <?= (int) ($row['status'] ?? 1) === 2 ? 'selected' : '' ?>>Unpublish</option>
        </select>
        <?php foreach ($fields as $field): ?>
            <label for="background-<?= esc($field) ?>"><?= esc($field) ?></label>
            <input id="background-<?= esc($field) ?>" name="<?= esc($field) ?>" type="file" accept="image/png">
        <?php endforeach ?>
        <button type="submit"><?= $row === null ? 'Create' : 'Update' ?></button>
    </form>
    <ul>
        <?php foreach ($rows as $item): ?>
            <li><a href="/backgrounds/<?= (int) $item['id'] ?>">Background <?= (int) $item['id'] ?></a></li>
        <?php endforeach ?>
    </ul>
</section>
