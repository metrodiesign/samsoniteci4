<?php

/** @var array{pk: string, label: string, pkLabel?: string, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, fk?: string, formText?: string, listText?: string}>} $definition */
/** @var array<string, mixed>|null $row */
/** @var string $type */
/** @var array<string, list<array<string, mixed>>> $fkOptions */
/** @var string $caption */
$action = '/master/' . rawurlencode($type) . ($row === null ? '' : '/' . (int) $row[$definition['pk']]);
?>
<section aria-labelledby="page-title">
    <div class="card">
        <h3 class="box-title"><?= esc($caption) ?></h3>
    <form method="post" action="<?= esc($action) ?>"<?= $type === 'branchtype' ? ' enctype="multipart/form-data"' : '' ?>>
        <?= csrf_field() ?>
        <?php foreach ($definition['fields'] as $field => $rule): ?>
            <label for="master-<?= esc($field) ?>"><?= esc($rule['formText'] ?? $field) ?></label>
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
        <button type="submit">Submit</button>
        <button type="reset">Reset</button>
    </form>
    </div>
</section>
