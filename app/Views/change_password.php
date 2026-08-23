<?php /** @var bool $changed */ ?>
<section aria-labelledby="password-title">
    <h1 id="password-title">Change password</h1>
    <?php if ($changed): ?><p role="status">Password changed</p><?php endif ?>
    <form method="post" action="/change-password">
        <?= csrf_field() ?>
        <label for="current-password">Current password</label>
        <input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
        <label for="new-password">New password</label>
        <input id="new-password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
        <label for="confirm-password">Confirm password</label>
        <input id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128" required>
        <button type="submit">Change password</button>
    </form>
</section>
