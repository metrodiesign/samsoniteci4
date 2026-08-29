<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Sign out</title></head>
<body>
<form id="logout" action="<?= site_url('logout') ?>" method="post">
    <?= csrf_field() ?>
    <noscript><button type="submit">Sign out</button></noscript>
</form>
<script>document.getElementById('logout').submit();</script>
</body>
</html>
