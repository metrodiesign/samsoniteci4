<section class="card login-card" aria-labelledby="login-title">
    <h1 id="login-title">Sign in</h1>
    <p class="muted">Use your Samsonite Tracking account.</p>

    <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert" role="alert"><?= esc($error) ?></div>
    <?php endif ?>

    <form action="<?= site_url('loginMe') ?>" method="post">
        <?= csrf_field() ?>
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" maxlength="128" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" maxlength="128" autocomplete="current-password" required>
        </div>
        <button type="submit">Sign in</button>
    </form>
</section>
