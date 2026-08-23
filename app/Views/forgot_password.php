<section class="card login-card" aria-labelledby="forgot-title">
    <h1 id="forgot-title">Forgot Password</h1>
    <p class="muted">Enter your account email and we will send reset instructions.</p>

    <div class="alert" id="reset-message" role="status" hidden></div>

    <form id="forgot-form" novalidate>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="128" autocomplete="email" required autofocus>
        </div>
        <button type="submit">Submit</button>
    </form>
    <p><a href="<?= site_url('login') ?>">Login</a></p>
</section>
<script>
(function () {
    var csrfUrl = <?= json_encode(site_url('password-reset/csrf'), JSON_UNESCAPED_SLASHES) ?>;
    var requestUrl = <?= json_encode(site_url('password-reset/request'), JSON_UNESCAPED_SLASHES) ?>;
    var GENERIC = 'If the account exists, reset instructions will be sent.';
    var form = document.getElementById('forgot-form');
    var box = document.getElementById('reset-message');

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var button = form.querySelector('button');
        button.disabled = true;

        fetch(csrfUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (csrf) {
                var headers = { 'Content-Type': 'application/json' };
                headers[csrf.header] = csrf.token;

                return fetch(requestUrl, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({ email: document.getElementById('email').value })
                });
            })
            .catch(function () { /* keep the response generic on any failure */ })
            .then(function () {
                box.textContent = GENERIC;
                box.hidden = false;
                button.disabled = false;
            });
    });
})();
</script>
