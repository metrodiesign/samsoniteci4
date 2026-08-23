<?php

/** @var array{pk: string, label: string, fields: array<string, array{kind: string, max?: int, required?: bool}>} $definition */
/** @var list<array<string, mixed>> $rows */
/** @var array<string, mixed>|null $row */
/** @var string $search */
/** @var string $type */
$action = '/master/' . rawurlencode($type) . ($row === null ? '' : '/' . (int) $row[$definition['pk']]);
?>
<section aria-labelledby="master-title">
    <h1 id="master-title">Master data: <?= esc($type) ?></h1>
    <form method="get" action="/master/<?= esc($type) ?>">
        <label for="master-search">Search</label>
        <input id="master-search" name="search" value="<?= esc($search) ?>" maxlength="128">
        <button type="submit">Search</button>
    </form>
    <form method="post" action="<?= esc($action) ?>"<?= $type === 'branchtype' ? ' enctype="multipart/form-data"' : '' ?>>
        <?= csrf_field() ?>
        <?php foreach ($definition['fields'] as $field => $rule): ?>
            <label for="master-<?= esc($field) ?>"><?= esc($field) ?></label>
            <input
                id="master-<?= esc($field) ?>"
                name="<?= esc($field) ?>"
                type="<?= $rule['kind'] === 'int' ? 'number' : 'text' ?>"
                value="<?= esc($row[$field] ?? '') ?>"
                <?= ($rule['required'] ?? false) ? 'required' : '' ?>
                <?= isset($rule['max']) ? 'maxlength="' . (int) $rule['max'] . '"' : '' ?>
            >
        <?php endforeach ?>
        <?php if ($type === 'branchtype'): ?>
            <label for="master-branch-type-image">PNG image</label>
            <input id="master-branch-type-image" name="branch_type_image" type="file" accept="image/png">
        <?php endif ?>
        <button type="submit"><?= $row === null ? 'Create' : 'Update' ?></button>
    </form>
    <ul>
        <?php foreach ($rows as $item): ?>
            <li><a href="/master/<?= esc($type) ?>/<?= (int) $item[$definition['pk']] ?>"><?= esc($item[$definition['label']]) ?></a></li>
        <?php endforeach ?>
    </ul>
</section>
