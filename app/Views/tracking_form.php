<?php

/** @var string $language */
/** @var string $trackId */
/** @var string|null $backgroundImage */
/** @var string|null $backgroundImageMobile */

$isThai   = $language === 'th';
$action   = $isThai ? base_url('tracking-th') : base_url('tracking');
$popup    = base_url('assets/images/' . ($isThai ? 'popup_th.png' : 'popup_en.png'));
$notFound = $isThai ? 'ไม่พบหมายเลขติดตาม' : 'Tracking ID not found';
?>
<?php if ($backgroundImage !== null): ?>
    <style>#track { background-image: url('/background-image/<?= esc($backgroundImage) ?>'); background-size: cover; }</style>
<?php endif ?>
<?php if ($backgroundImageMobile !== null): ?>
    <style>@media (max-width: 850px) { #track { background-image: url('/background-image/<?= esc($backgroundImageMobile) ?>'); } }</style>
<?php endif ?>
<form role="form" id="addtrack" action="<?= $action ?>" method="get">
    <section id="track">
        <div class="container">
            <div class="row">
                <div class="con-center-track">
                    <div class="topic-txt-hm">TRACK &amp; TRACE</div>
                    <div class="topic-txt-sm">Track Your Tracking Number</div>

                    <input type="text" name="tracking_id" id="searchText" class="search-txt form-control required"
                           value="<?= esc($trackId) ?>" maxlength="100" required
                           placeholder="<?= $isThai ? 'ระบุรหัสติดตามของคุณ' : 'Your Tracking ID' ?>"
                           style="height: 70px; text-transform: uppercase;">

                    <div>
                        <button type="button" id="btnModal" class="main-btn-sm"><?= $isThai ? 'วิธีตรวจสอบสถานะ' : 'HOW TO CHECK' ?></button>
                        <input type="submit" class="main-btn-sm" value="<?= $isThai ? 'ติดตาม' : 'CHECK NOW' ?>">
                    </div>

                    <?php if ($trackId !== ''): ?>
                        <p role="status"><?= esc($notFound) ?></p>
                    <?php endif ?>

                    <div class="mobile-only">
                        <div class="btn-mobile">
                            <a href="<?= $isThai ? base_url('contact-th') : base_url('contact') ?>">
                                <div class="control-txt">
                                    <div class="txt-sub-menu">CONTACT US</div>
                                </div>
                            </a>
                        </div>
                        <div class="btn-mobile">
                            <a href="https://www.samsonite.co.th/">
                                <div class="control-txt">
                                    <div class="txt-sub-menu">SHOPPING</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <dialog id="howToCheck" class="how-to-check">
                        <div class="topic-txt-hm">HOW TO CHECK</div>
                        <img class="rs-bg-size" src="<?= $popup ?>" alt="HOW TO CHECK">
                        <button type="button" id="btnModalClose" class="btn custom-btn">Close</button>
                    </dialog>

                    <script>
                        (function () {
                            var dialog = document.getElementById('howToCheck');
                            document.getElementById('btnModal').addEventListener('click', function () {
                                dialog.showModal();
                            });
                            document.getElementById('btnModalClose').addEventListener('click', function () {
                                dialog.close();
                            });
                        })();
                    </script>
                </div>
            </div>
        </div>
    </section>
</form>
