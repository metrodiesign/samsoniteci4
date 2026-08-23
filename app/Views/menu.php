<?php

/** @var list<array<string, mixed>> $rows */
/** @var array<string, mixed>|null $row */
/** @var list<array{id: int, name: string}> $menuGroups */
$selected = array_filter(array_map('intval', explode(',', (string) ($row['group_type'] ?? ''))));
$action = '/menu' . ($row === null ? '' : '/' . (int) $row['id']);
?>
<section aria-labelledby="menu-title">
    <h1 id="menu-title">Menu groups</h1>
    <form method="post" action="<?= esc($action) ?>">
        <?= csrf_field() ?>
        <label for="menu-name">Name</label>
        <input id="menu-name" name="name" value="<?= esc($row['name'] ?? '') ?>" maxlength="250" required>
        <fieldset>
            <legend>Visible menu groups</legend>
            <?php foreach ($menuGroups as $group): ?>
                <label>
                    <input type="checkbox" name="group_type[]" value="<?= $group['id'] ?>" <?= in_array($group['id'], $selected, true) ? 'checked' : '' ?>>
                    <?= esc($group['name']) ?>
                </label>
            <?php endforeach ?>
        </fieldset>
        <button type="submit"><?= $row === null ? 'Create' : 'Update' ?></button>
    </form>
    <ul>
        <?php foreach ($rows as $item): ?>
            <li><a href="/menu/<?= (int) $item['id'] ?>"><?= esc($item['name']) ?></a></li>
        <?php endforeach ?>
    </ul>
</section>
