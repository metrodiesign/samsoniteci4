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
    <table>
        <thead>
            <tr>
                <th><?= esc($definition['pk']) ?></th>
                <?php foreach ($definition['fields'] as $field => $rule): ?>
                    <th><?= esc($field) ?></th>
                <?php endforeach ?>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= (int) $item[$definition['pk']] ?></td>
                    <?php foreach ($definition['fields'] as $field => $rule): ?>
                        <td><?= esc((string) ($item[$field] ?? '')) ?></td>
                    <?php endforeach ?>
                    <td>
                        <a href="/master/<?= esc($type) ?>/<?= (int) $item[$definition['pk']] ?>">Edit</a>
                        <form method="post" action="/master/<?= esc($type) ?>/<?= (int) $item[$definition['pk']] ?>/delete" onsubmit="return confirm('Delete this record?')">
                            <?= csrf_field() ?>
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</section>
