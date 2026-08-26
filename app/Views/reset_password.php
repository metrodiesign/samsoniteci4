<div class="login-banner" role="presentation"></div>
<div class="login-logo">Tracking</div>
<section class="card login-card" aria-label="Reset Password">
    <p class="muted">Choose a new password for your account.</p>

    <div class="alert" id="reset-message" role="status" hidden></div>

    <form id="reset-form" novalidate>
        <input type="hidden" id="token" name="token" value="<?= esc($token, 'attr') ?>">
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="128" autocomplete="email" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" maxlength="128" autocomplete="new-password" required>
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" maxlength="128" autocomplete="new-password" required>
        </div>
        <button type="submit">Submit</button>
    </form>
</section>
<script>
(function () {
    var csrfUrl = <?= json_encode(site_url('password-reset/csrf'), JSON_UNESCAPED_SLASHES) ?>;
    var completeUrl = <?= json_encode(site_url('password-reset/complete'), JSON_UNESCAPED_SLASHES) ?>;
    var form = document.getElementById('reset-form');
    var box = document.getElementById('reset-message');

    var messages = {
        invalid_or_expired_reset: 'This reset link is invalid or has expired. Please request a new one.',
        invalid_password: 'Password does not meet the requirements or the confirmation does not match.',
        too_many_requests: 'Too many attempts. Please try again later.',
        reset_service_unavailable: 'The reset service is temporarily unavailable. Please try again later.'
    };

    function show(text) { box.textContent = text; box.hidden = false; }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var button = form.querySelector('button');
        button.disabled = true;

        fetch(csrfUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (csrf) {
                var headers = { 'Content-Type': 'application/json' };
                headers[csrf.header] = csrf.token;

                return fetch(completeUrl, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({
                        email: document.getElementById('email').value,
                        token: document.getElementById('token').value,
                        password: document.getElementById('password').value,
                        password_confirmation: document.getElementById('password_confirmation').value
                    })
                });
            })
            .then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    if (res.ok) {
                        show('Your password has been reset. You can now sign in.');
                    } else {
                        show(messages[data.error] || 'Unable to reset password. Please try again.');
                    }
                });
            })
            .catch(function () {
                show('Unable to reset password. Please try again.');
            })
            .then(function () { button.disabled = false; });
    });
})();
</script>
