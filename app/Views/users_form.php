<?php

/** @var array<string, mixed>|null $row */
/** @var int $actorRole */
/** @var int|null $actorBranch */
$isNew = $row === null;
$action = '/users' . ($isNew ? '' : '/' . (int) $row['userId']);
$userLabels = [
    'username' => 'Username',
    'name' => 'Full Name',
    'email' => 'Email address',
    'mobile' => 'Mobile Number',
    'group_id' => 'User Group',
    'role_id' => 'Role',
    'branch_id' => 'Branch',
];
/** @var string $caption */
?>
<section aria-labelledby="page-title">
    <div class="card">
        <h3 class="box-title"><?= esc($caption) ?></h3>
    <form method="post" action="<?= esc($action) ?>">
        <?= csrf_field() ?>
        <?php foreach (['username', 'name', 'email', 'mobile', 'group_id', 'role_id', 'branch_id'] as $field): ?>
            <?php $source = $field === 'role_id' ? 'roleId' : $field; ?>
            <label for="user-<?= esc($field) ?>"><?= esc($userLabels[$field]) ?></label>
            <input id="user-<?= esc($field) ?>" name="<?= esc($field) ?>" value="<?= esc($row[$source] ?? '') ?>" <?= in_array($field, ['group_id', 'role_id', 'branch_id'], true) ? 'type="number"' : '' ?>>
        <?php endforeach ?>
        <label for="user-password">Password</label>
        <input id="user-password" name="password" type="password" autocomplete="new-password" <?= $isNew ? 'required' : '' ?>>
        <label for="user-password-confirmation">Confirm password</label>
        <input id="user-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" <?= $isNew ? 'required' : '' ?>>
        <button type="submit">Submit</button>
        <button type="reset">Reset</button>
    </form>
    </div>
</section>
