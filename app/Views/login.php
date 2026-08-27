<div class="login-banner" role="presentation"></div>
<div class="login-logo">Tracking</div>
<section class="card login-card" aria-label="Sign in">
    <p class="muted">Use your Samsonite Tracking account.</p>

    <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert" role="alert"><?= esc($error) ?></div>
    <?php endif ?>

    <form action="<?= site_url('loginMe') ?>" method="post">
        <?= csrf_field() ?>
        <div class="field">
            <label for="username">USERNAME</label>
            <input id="username" name="username" maxlength="128" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">PASSWORD</label>
            <input id="password" name="password" type="password" maxlength="128" autocomplete="current-password" required>
        </div>
        <div class="login-actions">
            <a href="<?= site_url('forgot-password') ?>">Forgot Password</a>
            <button type="submit">Sign In</button>
        </div>
    </form>
</section>
