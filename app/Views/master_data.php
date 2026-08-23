<?php

/** @var array{pk: string, label: string, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, fk?: string}>} $definition */
/** @var list<array<string, mixed>> $rows */
/** @var array<string, mixed>|null $row */
/** @var string $search */
/** @var string $type */
/** @var int $page */
/** @var array<string, list<array<string, mixed>>> $fkOptions */
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
            <?php if (isset($rule['fk'])): ?>
                <select
                    id="master-<?= esc($field) ?>"
                    name="<?= esc($field) ?>"
                    <?= ($rule['required'] ?? false) ? 'required' : '' ?>
                >
                    <option value="">Select</option>
                    <?php foreach ($fkOptions[$field] ?? [] as $option): ?>
                        <option value="<?= (int) $option['value'] ?>"<?= (string) ($row[$field] ?? '') === (string) $option['value'] ? ' selected' : '' ?>><?= esc((string) $option['label']) ?></option>
                    <?php endforeach ?>
                </select>
            <?php else: ?>
                <input
                    id="master-<?= esc($field) ?>"
                    name="<?= esc($field) ?>"
                    type="<?= $rule['kind'] === 'int' ? 'number' : 'text' ?>"
                    value="<?= esc($row[$field] ?? '') ?>"
                    <?= ($rule['required'] ?? false) ? 'required' : '' ?>
                    <?= isset($rule['max']) ? 'maxlength="' . (int) $rule['max'] . '"' : '' ?>
                >
            <?php endif ?>
        <?php endforeach ?>
        <?php if ($type === 'branchtype'): ?>
            <label for="master-branch-type-image">PNG image</label>
            <input id="master-branch-type-image" name="branch_type_image" type="file" accept="image/png">
            <?php if ($row !== null && is_string($row['branch_type_image'] ?? null) && $row['branch_type_image'] !== ''): ?>
                <img src="/branch-type-image/<?= esc(rawurlencode((string) $row['branch_type_image']), 'attr') ?>" alt="Branch type image">
            <?php endif ?>
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
                <?php if ($type === 'branchtype'): ?>
                    <th>branch_type_image</th>
                <?php endif ?>
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
                    <?php if ($type === 'branchtype'): ?>
                        <td>
                            <?php if (is_string($item['branch_type_image'] ?? null) && $item['branch_type_image'] !== ''): ?>
                                <img src="/branch-type-image/<?= esc(rawurlencode((string) $item['branch_type_image']), 'attr') ?>" alt="Branch type image" height="100">
                            <?php endif ?>
                        </td>
                    <?php endif ?>
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
    <?php if (count($rows) === 50): ?><a href="/master/<?= esc($type) ?>?<?= $search === '' ? '' : 'search=' . rawurlencode($search) . '&amp;' ?>page=<?= $page + 1 ?>">Next</a><?php endif ?>
</section>
