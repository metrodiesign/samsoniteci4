<?php

/** @var array{pk: string, label: string, pkLabel?: string, listFields?: list<string>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, fk?: string, formText?: string, listText?: string}>} $definition */
/** @var list<array<string, mixed>> $rows */
/** @var string $search */
/** @var string $type */
/** @var int $page */
/** @var string $caption */
$listFields = $definition['listFields'] ?? array_keys($definition['fields']);
?>
<section aria-labelledby="page-title">
    <div class="card">
        <h3 class="box-title"><?= esc($caption) ?></h3>
        <form class="box-tools" method="get" action="/master/<?= esc($type) ?>">
            <label for="master-search">Search</label>
            <input id="master-search" name="search" value="<?= esc($search) ?>" maxlength="128">
            <button type="submit">Search</button>
        </form>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th><?= esc($definition['pkLabel'] ?? $definition['pk']) ?></th>
                <?php foreach ($listFields as $field): ?>
                    <th><?= esc($definition['fields'][$field]['listText'] ?? $field) ?></th>
                <?php endforeach ?>
                <?php if ($type === 'branchtype'): ?>
                    <th>Image</th>
                <?php endif ?>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= (int) $item[$definition['pk']] ?></td>
                    <?php foreach ($listFields as $field): ?>
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
    </div>
    <?php if (count($rows) === 50): ?><a href="/master/<?= esc($type) ?>?<?= $search === '' ? '' : 'search=' . rawurlencode($search) . '&amp;' ?>page=<?= $page + 1 ?>">Next</a><?php endif ?>
    </div>
</section>
