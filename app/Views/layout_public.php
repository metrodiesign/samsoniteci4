<?php
/**
 * Public layout สำหรับ Tracking / Contact / Rating (design §2.1)
 * รับ: $title, $content, $language ('en'|'th', default 'en')
 * CSS: assets/css/main.css (header/footer + page), fonts/stylesheet.css, css/public.css (grid shim)
 * ไม่มี bootstrap / jQuery / fontawesome — icon เป็น inline SVG, hamburger เป็น CSS :checked
 */
$language    = ($language ?? 'en') === 'th' ? 'th' : 'en';
$trackLink   = $language === 'th' ? base_url('tracking-th') : base_url('tracking');
$contactLink = $language === 'th' ? base_url('contact-th') : base_url('contact');
$contactText = $language === 'th' ? 'ติดต่อเรา' : 'CONTACT US';
?>
<!doctype html>
<html lang="<?= $language ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> | Samsonite</title>
    <link href="<?= base_url('assets/fonts/stylesheet.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/css/main.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/css/public.css') ?>" rel="stylesheet">
</head>
<body>
<section id="header">
    <header class="header">
        <a href="<?= base_url() ?>" class="position-logo">
            <img class="logo-size" src="<?= base_url('assets/images/main-logo.png') ?>" alt="Samsonite">
        </a>

        <input class="menu-btn" type="checkbox" id="menu-btn">
        <label class="menu-icon" for="menu-btn"><span class="navicon"></span></label>

        <ul class="menu">
            <li>
                <a href="<?= $trackLink ?>">
                    <div class="control-txt">
                        <svg class="edit-ico" width="1em" height="1em" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M256 176a80 80 0 1 0 0 160 80 80 0 0 0 0-160zm208 96 40-32-40-70-48 16a160 160 0 0 0-40-24l-8-50h-80l-8 50a160 160 0 0 0-40 24l-48-16-40 70 40 32a161 161 0 0 0 0 48l-40 32 40 70 48-16a160 160 0 0 0 40 24l8 50h80l8-50a160 160 0 0 0 40-24l48 16 40-70-40-32a161 161 0 0 0 0-48z"/></svg>
                        <div class="txt-sub-menu">TRACK &amp; TRACE</div>
                    </div>
                </a>
            </li>
            <li>
                <a href="<?= $contactLink ?>">
                    <div class="control-txt">
                        <svg class="edit-ico" width="1em" height="1em" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M48 96a16 16 0 0 0-16 16v288a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16V112a16 16 0 0 0-16-16H48zm8 40 200 150 200-150v24L256 320 56 160v-24z"/></svg>
                        <div class="txt-sub-menu"><?= esc($contactText) ?></div>
                    </div>
                </a>
            </li>
            <li>
                <a href="https://www.samsonite.co.th/">
                    <div class="control-txt">
                        <svg class="edit-ico" width="1em" height="1em" viewBox="0 0 448 512" fill="currentColor" aria-hidden="true"><path d="M144 128v16h160v-16a80 80 0 0 0-160 0zm-48 16v-16a128 128 0 0 1 256 0v16h64l24 320H8l24-320h64z"/></svg>
                        <div class="txt-sub-menu">SHOPPING</div>
                    </div>
                </a>
            </li>
            <li style="margin: 0 auto; text-align: center;">
                <a href="<?= base_url('tracking') ?>" style="display: inline-block">
                    <div class="control-lang">
                        <img src="<?= base_url('assets/images/eng.png') ?>" class="img-lang" alt="English">
                        <div class="txt-lang">English</div>
                    </div>
                </a>
                <a href="<?= base_url('tracking-th') ?>" style="display: inline-block">
                    <div class="control-lang">
                        <img src="<?= base_url('assets/images/thai.png') ?>" class="img-lang" alt="ไทย">
                        <div class="txt-lang">ไทย</div>
                    </div>
                </a>
            </li>
        </ul>
    </header>
</section>

<?= $content ?>

<section id="footer">
    <div class="bg-footer">
        <img src="<?= base_url('assets/images/img-footer.png') ?>" alt="">
        <div class="txt-cen-footer">NEED HELP ? CALL OUR CUSTOMER CENTRE AT</div>
        <div class="txt-num">02-761-9999</div>
    </div>
</section>
</body>
</html>
