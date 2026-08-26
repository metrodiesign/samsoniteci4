<?php
/**
 * @var string $title
 * @var string $content
 * @var bool $isLoggedIn
 * @var string $name
 * @var string $subtitle
 * @var string $actions
 * @var list<array{group_id: int, group_name: string, icon: string, items: list<array{menu_name: string, menu_link: string}>}> $menuItems
 */

// Map the group_type.icon_menu fontawesome string to an inline SVG (design §1.5).
// No fontawesome dependency; unknown values fall back to a neutral glyph.
$iconSvg = static function (string $icon): string {
    $paths = [
        'fa fa-dashboard'     => '<path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>',
        'fa fa-user'          => '<path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 5.5V22h18v-2.5c0-3-4-5.5-9-5.5z"/>',
        'fa fa-shopping-cart' => '<path d="M7 18a2 2 0 100 4 2 2 0 000-4zm10 0a2 2 0 100 4 2 2 0 000-4zM6.2 6l1.6 8h9.6l2-8H6.2zM4 3H1v2h2l1 11h14v-2H6l-.2-1H4V3z"/>',
        'fa fa-file-text'     => '<path d="M6 2h8l4 4v16H6V2zm7 1.5V7h3.5L13 3.5zM8 12h8v2H8v-2zm0 4h8v2H8v-2zm0-8h4v2H8V8z"/>',
        'fa fa-bar-chart'     => '<path d="M3 21h18v-2H3v2zM5 17h3V7H5v10zm5 0h3V3h-3v14zm5 0h3v-7h-3v7z"/>',
        'fa fa-upload'        => '<path d="M12 3l5 5h-3v6h-4V8H7l5-5zM5 18h14v2H5v-2z"/>',
        'fa fa-users'         => '<path d="M8 12a4 4 0 100-8 4 4 0 000 8zm8 0a3 3 0 100-6 3 3 0 000 6zM2 20v-1.5C2 16 5 14.5 8 14.5s6 1.5 6 4V20H2zm14 0v-1.5c0-1.6-.8-2.8-2-3.6 2.6.2 6 1.4 6 3.6V20h-4z"/>',
        'fa fa-cogs'          => '<path d="M12 8a4 4 0 100 8 4 4 0 000-8zm8.4 4l1.5-1.2-1.5-2.6-1.8.6a6.6 6.6 0 00-1.1-.6L17 4.5h-3l-.5 1.9c-.4.2-.7.4-1.1.6l-1.8-.6-1.5 2.6L10.1 12l-1.5 1.2 1.5 2.6 1.8-.6c.4.2.7.4 1.1.6l.5 1.9h3l.5-1.9c.4-.2.7-.4 1.1-.6l1.8.6 1.5-2.6L20.4 12z"/>',
    ];

    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
        . ($paths[$icon] ?? '<path d="M4 4h16v16H4z" fill="none" stroke="currentColor" stroke-width="2"/>')
        . '</svg>';
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> | Samsonite Tracking</title>
    <link rel="stylesheet" href="/assets/fonts/stylesheet.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<?php if ($isLoggedIn): ?>
<body class="admin">
    <input type="checkbox" id="sidebar-toggle" class="sidebar-toggle">
    <aside class="sidebar">
        <a class="brand" href="<?= site_url('dashboard') ?>">
            <img src="/assets/images/main-logo.png" alt="Samsonite">
        </a>
        <nav aria-label="Main navigation">
            <p class="menu-heading">MENU</p>
            <?php foreach ($menuItems as $group): ?>
                <ul class="menu-group">
                    <li class="menu-group-heading">
                        <p class="menu-group-title"><?= $iconSvg($group['icon']) ?><span><?= esc($group['group_name']) ?></span></p>
                    </li>
                    <?php foreach ($group['items'] as $item): ?>
                        <li><a href="<?= site_url($item['menu_link']) ?>"><?= esc($item['menu_name']) ?></a></li>
                    <?php endforeach ?>
                </ul>
            <?php endforeach ?>
        </nav>
    </aside>
    <header class="topbar">
        <label class="sidebar-toggle-btn" for="sidebar-toggle" aria-label="Toggle navigation">&#9776;</label>
        <a class="topbar-back" href="javascript:history.back()">Back</a>
        <span class="topbar-user"><?= esc($name !== '' ? $name : 'user') ?></span>
        <form action="<?= site_url('logout') ?>" method="post">
            <?= csrf_field() ?>
            <button class="link-button" type="submit">Sign out</button>
        </form>
    </header>
    <main class="content">
        <div class="page-header">
            <h1 id="page-title"><?= esc($title) ?></h1>
            <?php if ($subtitle !== ''): ?><small><?= esc($subtitle) ?></small><?php endif ?>
            <?php if ($actions !== ''): ?><div class="page-actions"><?= $actions ?></div><?php endif ?>
        </div>
        <?= $content ?>
    </main>
    <footer class="site-footer">
        <img src="/assets/images/img-footer.png" alt="">
        <span>NEED HELP ? CALL OUR CUSTOMER CENTRE AT</span>
        <strong>02-761-9999</strong>
    </footer>
</body>
<?php else: ?>
<body class="bare">
    <main><?= $content ?></main>
    <footer class="site-footer">
        <img src="/assets/images/img-footer.png" alt="">
        <span>NEED HELP ? CALL OUR CUSTOMER CENTRE AT</span>
        <strong>02-761-9999</strong>
    </footer>
</body>
<?php endif ?>
</html>
