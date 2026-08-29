<?php

/** @var string $content */
/** @var string|null $title */
/** @var string|null $language */
/** @var bool|null $legacyContactProfile */
/** @var bool|null $legacyTrackingProfile */

$language              = ($language ?? 'en') === 'th' ? 'th' : 'en';
$legacyContactProfile  = $legacyContactProfile ?? false;
$legacyTrackingProfile = $legacyTrackingProfile ?? false;
$legacyPublicProfile   = $legacyContactProfile || $legacyTrackingProfile;
$trackLink             = base_url($language === 'th' ? 'tracking-th' : 'tracking');
$contactLink           = base_url($language === 'th' ? 'contact-th' : 'contact');
$contactText           = $language === 'th' ? 'ติดต่อเรา' : 'CONTACT US';
?>
<!DOCTYPE html>
<html lang="<?= $language ?>">
<head>
    <title><?= esc($title ?? 'Samsonite') ?></title>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <script src="<?= base_url('assets/js/jquery-3.2.1.min.js') ?>" type="text/javascript"></script>
    <script src="<?= base_url('assets/bootstrap/js/bootstrap.min.js') ?>" type="text/javascript"></script>

    <link href="<?= base_url('assets/bootstrap/css/bootstrap.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/css/main.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/fontawesome/css/font-awesome.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/fonts/stylesheet.css') ?>" rel="stylesheet">
    <?php if (! $legacyPublicProfile): ?>
        <link href="<?= base_url('assets/css/public.css') ?>" rel="stylesheet">
    <?php endif ?>
</head>
<body>
<section id="header">
    <header class="header">
        <a href="<?= base_url() ?>" class="position-logo">
            <img class="logo-size" src="<?= base_url('assets/images/main-logo.png') ?>" alt="Samsonite">
        </a>

        <input class="menu-btn" type="checkbox" id="menu-btn">
        <label class="menu-icon" for="menu-btn">
            <span class="navicon"></span>
        </label>

        <ul class="menu">
            <li>
                <a href="<?= $trackLink ?>">
                    <div class="control-txt">
                        <i class="fa fa-cogs edit-ico"></i>
                        <div class="txt-sub-menu">TRACK &amp; TRACE</div>
                    </div>
                </a>
            </li>
            <li>
                <a href="<?= $contactLink ?>">
                    <div class="control-txt">
                        <i class="fa fa-envelope-o edit-ico"></i>
                        <div class="txt-sub-menu"><?= esc($contactText) ?></div>
                    </div>
                </a>
            </li>
            <li>
                <a href="https://www.samsonite.co.th/">
                    <div class="control-txt">
                        <i class="fa fa-shopping-bag edit-ico"></i>
                        <div class="txt-sub-menu">SHOPPING</div>
                    </div>
                </a>
            </li>
            <li style="margin: 0 auto; text-align: center;">
                <a href="<?= base_url() ?>" style="display: inline-block">
                    <div class="control-lang">
                        <img src="<?= base_url('assets/images/eng.png') ?>" class="img-lang" alt="">
                        <div class="txt-lang">English</div>
                    </div>
                </a>
                <a href="<?= base_url('tracking-th') ?>" style="display: inline-block">
                    <div class="control-lang">
                        <img src="<?= base_url('assets/images/thai.png') ?>" class="img-lang" alt="">
                        <div class="txt-lang">ไทย</div>
                    </div>
                </a>
            </li>
        </ul>
    </header>
    <?php if ($language === 'en'): ?>
        <script type="text/javascript">
            $(document).ready(function () {
                $('#menu ul li a').click(function (ev) {
                    $('#menu ul li').removeClass('selected');
                    $(ev.currentTarget).parent('li').addClass('selected');
                });
            });
        </script>
    <?php endif ?>
</section>

<?= $content ?>

<section id="footer">
    <div class="bg-footer">
        <img class="" src="<?= base_url('assets/images/img-footer.png') ?>" alt="">
        <div class="txt-cen-footer">NEED HELP ? CALL OUR CUSTOMER CENTRE AT</div>
        <div class="txt-num">02-761-9999</div>
    </div>
</section>

<script src="<?= base_url('assets/dist/js/app.min.js') ?>" type="text/javascript"></script>
<script src="<?= base_url('assets/js/jquery.validate.js') ?>" type="text/javascript"></script>
<script src="<?= base_url('assets/js/validation.js') ?>" type="text/javascript"></script>
<?php if ($legacyContactProfile): ?>
    <script src="<?= base_url('assets/js/addContact.js') ?>" type="text/javascript"></script>
<?php endif ?>
<?php if ($legacyTrackingProfile): ?>
    <script src="<?= base_url('assets/js/addtrack.js') ?>" type="text/javascript"></script>
<?php endif ?>
<script type="text/javascript">
    var windowURL = window.location.href;
    pageURL = windowURL.substring(0, windowURL.lastIndexOf('/'));
    var x = $('a[href="' + pageURL + '"]');
    x.addClass('active');
    x.parent().addClass('active');
    var y = $('a[href="' + windowURL + '"]');
    y.addClass('active');
    y.parent().addClass('active');
</script>
</body>
</html>
