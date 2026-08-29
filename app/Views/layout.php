<?php
/**
 * @var string $pageTitle
 * @var string $title
 * @var string $content
 * @var bool $isLoggedIn
 * @var string $name
 * @var string $role_text
 * @var string $last_login
 * @var int $GroupID
 * @var int|null $BranchID
 * @var string $BranchName
 * @var string $subtitle
 * @var string $actions
 * @var list<array{group_id: int, group_name: string, icon: string, items: list<array{menu_name: string, menu_link: string}>}> $menuItems
 * @var bool $showBranchAutocomplete
 * @var list<array{label: string, value: string}> $branchOptions
 * @var bool $accessDeniedProfile
 * @var bool $contentOwnsWrapper
 */

$accessDeniedProfile = $accessDeniedProfile ?? false;
$contentOwnsWrapper = $contentOwnsWrapper ?? false;
$branchOptionsJson = json_encode(
    $branchOptions,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES,
);
if (! is_string($branchOptionsJson)) {
    $branchOptionsJson = '[]';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= esc($pageTitle) ?></title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <link href="<?= base_url('assets/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet" type="text/css">
    <link href="<?= base_url('assets/datatables/1.10.16/css/jquery.dataTables.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/datatables-fixedcolumns/3.2.4/css/fixedColumns.dataTables.min.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/font-awesome/css/font-awesome.min.css') ?>" rel="stylesheet" type="text/css">
    <link href="<?= base_url('assets/dist/css/AdminLTE.min.css') ?>" rel="stylesheet" type="text/css">
    <link href="<?= base_url('assets/dist/css/CustomAdmin.css') ?>" rel="stylesheet" type="text/css">
    <link href="<?= base_url('assets/css/main.css') ?>" rel="stylesheet" type="text/css">
    <link href="<?= base_url('assets/css/multifreezer.css') ?>" rel="stylesheet" type="text/css">
    <link href="<?= base_url('assets/dist/css/skins/_all-skins.min.css') ?>" rel="stylesheet" type="text/css">
    <script type="text/javascript" src="<?= base_url('assets/js/jquerydatepicker/jquery-1.10.2.min.js') ?>"></script>
    <link rel="stylesheet" media="all" type="text/css" href="<?= base_url('assets/js/jquerydatepicker/jquery-ui.css') ?>">
    <link rel="stylesheet" media="all" type="text/css" href="<?= base_url('assets/js/jquerydatepicker/jquery-ui-timepicker-addon.css') ?>">
    <script type="text/javascript" src="<?= base_url('assets/js/jquerydatepicker/jquery-ui.min.js') ?>"></script>
    <script type="text/javascript" src="<?= base_url('assets/js/jquerydatepicker/jquery-ui-timepicker-addon.js') ?>"></script>
    <script type="text/javascript" src="<?= base_url('assets/js/jquerydatepicker/jquery-ui-sliderAccess.js') ?>"></script>
    <style>
        .error{
            color:red;
            font-weight: normal;
        }
    </style>
    <script type="text/javascript">
        var baseURL = <?= json_encode(base_url(), JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <!--[if lt IE 9]>
        <script src="<?= base_url('assets/html5shiv/3.7.2/html5shiv.min.js') ?>"></script>
        <script src="<?= base_url('assets/respond/1.4.2/respond.min.js') ?>"></script>
    <![endif]-->
</head>
<?php if ($isLoggedIn): ?>
<body class="skin-blue sidebar-mini">
<div class="wrapper">
    <header class="main-header">
        <a href="<?= base_url() ?>" class="logo" style="padding: 0; background-color: #fff; color: #014c8f; border-right: 1px solid #014c8f;">
            <img class="img-responsive" src="<?= base_url('assets/images/print-logo.jpg') ?>" alt="">
        </a>
        <nav class="navbar navbar-static-top" role="navigation" style="background-color: #fff; color: #014c8f; border-bottom: 1px solid #014c8f;">
            <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button" style="color: #000;">
                <span class="sr-only">Toggle navigation</span>
            </a>
            <button class="btn btn-sm btn-default" onclick="history.back(-1)">Back</button>
            <?php if ($BranchName !== ''): ?>
            <div class="logo" style="background-color: #fff; color: #014c8f;">
                <span class="logo-lg"><b>BRANCH <?= esc($BranchName) ?></b></span>
            </div>
            <?php endif ?>
            <div class="navbar-custom-menu">
                <ul class="nav navbar-nav">
                    <li class="dropdown tasks-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
                            <i class="fa fa-history"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="header"> Last Login : <i class="fa fa-clock-o"></i> <?= $last_login === '' ? 'First Time Login' : esc($last_login) ?></li>
                        </ul>
                    </li>
                    <?php if ($showBranchAutocomplete): ?>
                    <li class="dropdown tasks-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
                            <i class="fa fa-university"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <script>
                                $(document).ready(function() {

                                  var xsource = <?= $branchOptionsJson ?>;

                                    $("input#autocomplete").autocomplete({
                                        source: xsource,
                                        select: function( event, ui ) {
                                            window.location.href = ui.item.value;
                                        }
                                    });
                                });
                            </script>
                            <input id="autocomplete" class="form-control" placeholder="Search">
                        </ul>
                    </li>
                    <?php endif ?>
                    <li class="dropdown user user-menu">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                            <img src="<?= base_url('assets/dist/img/avatar.png') ?>" class="user-image" alt="User Image">
                            <span class="hidden-xs"><?= esc($name) ?></span>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="user-header">
                                <img src="<?= base_url('assets/dist/img/avatar.png') ?>" class="img-circle" alt="User Image">
                                <p>
                                    <?= esc($name) ?>
                                    <small><?= esc($role_text) ?></small>
                                </p>
                            </li>
                            <li class="user-footer">
                                <div class="pull-right">
                                    <a href="<?= base_url('loadChangePass') ?>" class="btn btn-default btn-flat"><i class="fa fa-key"></i> Change Password</a>
                                </div>
                            </li>
                        </ul>
                    </li>
                    <li class="btn-flat">
                        <a href="<?= base_url('logout') ?>"><i class="fa fa-sign-out"></i> Sign out</a>
                    </li>
                </ul>
            </div>
        </nav>
    </header>
    <aside class="main-sidebar">
        <section class="sidebar">
            <ul class="sidebar-menu">
                <li class="header">MENU</li>
                <?php foreach ($menuItems as $group): ?>
                <li class="treeview active">
                    <a href="#"><i class="<?= esc($group['icon'], 'attr') ?>"></i><span><?= esc($group['group_name']) ?></span>
                        <span class="pull-right-container">
                            <i class="fa fa-angle-left pull-right"></i>
                        </span>
                    </a>
                    <ul class="treeview-menu">
                        <?php foreach ($group['items'] as $item): ?>
                        <li><a href="<?= base_url($item['menu_link']) ?>"><?= esc($item['menu_name']) ?></a></li>
                        <?php endforeach ?>
                    </ul>
                </li>
                <?php endforeach ?>
            </ul>
        </section>
    </aside>
    <?php if ($accessDeniedProfile || $contentOwnsWrapper): ?>
        <?= $content ?>
    <?php else: ?>
    <div class="content-wrapper">
        <section class="content-header">
            <h1><?= esc($title) ?><?php if ($subtitle !== ''): ?>
                <small><?= esc($subtitle) ?></small><?php endif ?></h1>
            <?php if ($actions !== ''): ?><?= $actions ?><?php endif ?>
        </section>
        <section class="content">
            <?= $content ?>
        </section>
    </div>
    <?php endif ?>
    <footer class="main-footer">
        <section id="footer">
            <div class="bg-footer">
                <img class="" src="<?= base_url('assets/images/img-footer.png') ?>">
                <div class="txt-cen-footer">NEED HELP ? CALL OUR CUSTOMER CENTRE AT</div>
                <div class="txt-num">02-761-9999</div>
            </div>
        </section>
    </footer>
    <?php require __DIR__ . '/partials/admin_legacy_scripts.php'; ?>
</div>
</body>
<?php else: ?>
<body class="bare">
    <main><?= $content ?></main>
    <footer class="site-footer">
        <img src="<?= base_url('assets/images/img-footer.png') ?>" alt="">
        <span>NEED HELP ? CALL OUR CUSTOMER CENTRE AT</span>
        <strong>02-761-9999</strong>
    </footer>
</body>
<?php endif ?>
</html>
