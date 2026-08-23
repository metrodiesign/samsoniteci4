<?php
$menuItems = service('session')->get('isLoggedIn') === true
    ? (new \App\Master\MenuStore(db_connect()))->visible((int) service('session')->get('GroupID'))
    : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> | Samsonite Tracking</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #172033; background: #f5f7fb; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        a { color: #174ea6; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .9rem 1.4rem; background: #111b35; color: #fff; }
        header a { color: #fff; text-decoration: none; }
        nav { display: flex; align-items: center; gap: 1rem; }
        main { width: min(1500px, 100%); margin: 0 auto; padding: 1.5rem; }
        .card { background: #fff; border: 1px solid #dfe4ee; border-radius: .65rem; box-shadow: 0 2px 12px rgb(20 37 63 / 6%); padding: 1.25rem; }
        .login-card { width: min(420px, 100%); margin: 8vh auto; }
        h1, h2 { margin-top: 0; }
        label { display: block; font-weight: 650; margin-bottom: .35rem; }
        input, select, button { font: inherit; }
        input, select { width: 100%; min-height: 2.7rem; padding: .55rem .7rem; border: 1px solid #aeb8ca; border-radius: .35rem; background: #fff; }
        button, .button { display: inline-flex; align-items: center; justify-content: center; min-height: 2.7rem; padding: .55rem 1rem; border: 0; border-radius: .35rem; background: #174ea6; color: #fff; cursor: pointer; text-decoration: none; }
        .link-button { min-height: auto; padding: 0; background: none; }
        .field { margin-bottom: 1rem; }
        .alert { padding: .75rem 1rem; margin-bottom: 1rem; border: 1px solid #c93737; border-radius: .35rem; color: #8b1515; background: #fff1f1; }
        .muted { color: #5b6578; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .9rem; margin: 1rem 0 1.5rem; }
        .metric { padding: 1rem; border-left: .3rem solid #174ea6; background: #fff; border-radius: .35rem; box-shadow: 0 1px 8px rgb(20 37 63 / 7%); }
        .metric strong { display: block; font-size: 1.8rem; }
        .filters { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1.4fr auto; gap: .75rem; align-items: end; }
        .table-wrap { overflow: auto; margin-top: 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: .88rem; white-space: nowrap; }
        th, td { padding: .6rem .7rem; border-bottom: 1px solid #e2e7f0; text-align: left; }
        th { position: sticky; top: 0; background: #edf2fa; color: #26324b; }
        tbody tr:hover { background: #f7f9fd; }
        @media (max-width: 850px) { .filters { grid-template-columns: 1fr; } main { padding: 1rem; } }
    </style>
</head>
<body>
<header>
    <a href="<?= site_url('dashboard') ?>"><strong>Samsonite Tracking</strong></a>
    <?php if (service('session')->get('isLoggedIn') === true): ?>
        <nav aria-label="Main navigation">
            <a href="<?= site_url('dashboard') ?>">Dashboard</a>
            <a href="<?= site_url('Order/ReportTrackingListing') ?>">Report Tracking</a>
            <?php foreach ($menuItems as $menuItem): ?>
                <a href="<?= site_url($menuItem['menu_link']) ?>"><?= esc($menuItem['menu_name']) ?></a>
            <?php endforeach ?>
            <form action="<?= site_url('logout') ?>" method="post">
                <?= csrf_field() ?>
                <button class="link-button" type="submit">Sign out</button>
            </form>
        </nav>
    <?php endif ?>
</header>
<main><?= $content ?></main>
</body>
</html>
