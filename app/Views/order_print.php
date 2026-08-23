<?php /** @var array<string, mixed> $row */ ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title><?= esc($row['trackID']) ?></title></head>
<body>
    <h1>Repair order <?= esc($row['trackID']) ?></h1>
    <dl>
        <dt>Order</dt><dd><?= esc($row['orderIDShow']) ?></dd>
        <dt>Customer</dt><dd><?= esc($row['customerFullname']) ?></dd>
        <dt>Phone</dt><dd><?= esc($row['customerTel']) ?></dd>
        <dt>Status</dt><dd><?= (int) $row['action_status'] ?></dd>
    </dl>
</body></html>
