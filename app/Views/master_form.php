<?php

/** @var array{pk: string, label: string, pkLabel?: string, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, fk?: string, formText?: string, listText?: string}>} $definition */
/** @var array<string, mixed>|null $row */
/** @var string $type */
/** @var array<string, list<array<string, mixed>>> $fkOptions */
/** @var string $caption */
/** @var string $legacyAction */
$action = base_url($legacyAction);
?>
<section aria-labelledby="page-title">
    <div class="card">
        <h3 class="box-title"><?= esc($caption) ?></h3>
    <form role="form" method="post" class="form-grid" action="<?= esc($action) ?>"<?= $type === 'branchtype' ? ' enctype="multipart/form-data"' : '' ?>>
        <?= csrf_field() ?>
        <?php if ($row !== null): ?>
            <input type="hidden" name="<?= esc($definition['pk']) ?>" value="<?= (int) $row[$definition['pk']] ?>">
        <?php endif ?>
        <?php foreach ($definition['fields'] as $field => $rule): ?>
            <?php if (($rule['visible'] ?? true) === false) { continue; } ?>
            <div class="field">
            <?php $inputName = $type === 'provider' && $field === 'provider_datail' ? 'provider_details' : $field; ?>
            <label for="<?= esc($field) ?>"><?= esc($rule['formText'] ?? $field) ?></label>
            <?php if (isset($rule['fk'])): ?>
                <select
                    id="<?= esc($field) ?>"
                    name="<?= esc($inputName) ?>"
                    <?= ($rule['required'] ?? false) ? 'required' : '' ?>
                >
                    <option value="">Select</option>
                    <?php foreach ($fkOptions[$field] ?? [] as $option): ?>
                        <option value="<?= (int) $option['value'] ?>"<?= (string) ($row[$field] ?? '') === (string) $option['value'] ? ' selected' : '' ?>><?= esc((string) $option['label']) ?></option>
                    <?php endforeach ?>
                </select>
            <?php else: ?>
                <input
                    id="<?= esc($field) ?>"
                    name="<?= esc($inputName) ?>"
                    type="<?= $rule['kind'] === 'int' ? 'number' : 'text' ?>"
                    value="<?= esc($row[$field] ?? '') ?>"
                    <?= ($rule['required'] ?? false) ? 'required' : '' ?>
                    <?= isset($rule['max']) ? 'maxlength="' . (int) $rule['max'] . '"' : '' ?>
                >
            <?php endif ?>
            </div>
        <?php endforeach ?>
        <?php if ($type === 'branchtype'): ?>
            <div class="field">
            <label for="master-branch-type-image">PNG image</label>
            <input id="master-branch-type-image" name="branch_type_image" type="file" accept="image/png">
            <?php if ($row !== null && is_string($row['branch_type_image'] ?? null) && $row['branch_type_image'] !== ''): ?>
                <img src="/branch-type-image/<?= esc(rawurlencode((string) $row['branch_type_image']), 'attr') ?>" alt="Branch type image">
            <?php endif ?>
            </div>
        <?php endif ?>
        <div class="form-actions">
            <button type="submit">Submit</button>
            <button type="reset">Reset</button>
        </div>
    </form>
    </div>
</section>
