<?php

/** @var list<array<string, mixed>> $rows */
/** @var array<string, mixed>|null $row */
/** @var string $search */
/** @var int $actorRole */
/** @var int|null $actorBranch */
$action = '/users' . ($row === null ? '' : '/' . (int) $row['userId']);
?>
<section aria-labelledby="users-title">
    <h1 id="users-title">Users</h1>
    <form method="get" action="/users">
        <label for="user-search">Search</label>
        <input id="user-search" name="search" value="<?= esc($search) ?>" maxlength="128">
        <button type="submit">Search</button>
    </form>
    <?php if ($actorRole !== 3): ?>
        <form method="post" action="<?= esc($action) ?>">
            <?= csrf_field() ?>
            <?php foreach (['username', 'name', 'email', 'mobile', 'group_id', 'role_id', 'branch_id'] as $field): ?>
                <?php $source = $field === 'role_id' ? 'roleId' : $field; ?>
                <label for="user-<?= esc($field) ?>"><?= esc($field) ?></label>
                <input id="user-<?= esc($field) ?>" name="<?= esc($field) ?>" value="<?= esc($row[$source] ?? ($field === 'branch_id' ? $actorBranch : '')) ?>" <?= in_array($field, ['group_id', 'role_id', 'branch_id'], true) ? 'type="number"' : '' ?>>
            <?php endforeach ?>
            <label for="user-password">Password</label>
            <input id="user-password" name="password" type="password" autocomplete="new-password">
            <label for="user-password-confirmation">Confirm password</label>
            <input id="user-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password">
            <button type="submit"><?= $row === null ? 'Create' : 'Update' ?></button>
        </form>
    <?php endif ?>
    <ul>
        <?php foreach ($rows as $item): ?>
            <li><a href="/users/<?= (int) $item['userId'] ?>"><?= esc($item['name']) ?></a> — <?= esc($item['email']) ?></li>
        <?php endforeach ?>
    </ul>
</section>
