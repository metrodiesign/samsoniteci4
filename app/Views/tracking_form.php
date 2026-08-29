<?php

/** @var string $language */
/** @var string $trackId */
/** @var bool $notFound */
/** @var string|null $backgroundImage */
/** @var string|null $backgroundImageMobile */

$isThai          = $language === 'th';
$action          = $isThai ? base_url('track_th/trackstatus') : base_url('track/trackstatus');
$popup           = base_url('assets/images/' . ($isThai ? 'popup_th.png' : 'popup_en.png'));
$notFoundMessage = $isThai ? 'ไม่พบหมายเลขติดตาม' : 'Tracking ID not found';
?>
<style>
    #track { background-image: url('<?= base_url('assets/images/bg-tracking.png') ?>'); }
<?php if ($backgroundImage !== null): ?>
    #track { background-image: url('<?= base_url('background-image/' . rawurlencode($backgroundImage)) ?>'); }
<?php endif ?>
    @media (max-width: 850px) {
        #track { background-image: url('<?= base_url('assets/images/bg-tracking-mb.png') ?>'); }
<?php if ($backgroundImageMobile !== null): ?>
        #track { background-image: url('<?= base_url('background-image/' . rawurlencode($backgroundImageMobile)) ?>'); }
<?php endif ?>
    }
</style>
<form role="form" id="addtrack" action="<?= $action ?>" method="post">
    <section id="track">
        <div class="container">
            <div class="row">
                <div class="con-center-track">
                    <div class="topic-txt-hm">TRACK &amp; TRACE</div>
                    <div class="topic-txt-sm">Track Your Tracking Number</div>

                    <input type="text" name="searchText" id="searchText" class="search-txt form-control required"
                           value="<?= esc($trackId) ?>"
                           placeholder="<?= $isThai ? 'ระบุรหัสติดตามของคุณ' : 'Your Tracking ID' ?>"
                           style="height: 70px; text-transform: uppercase;">

                    <div class="">
                        <button type="button" id="btnModal" class="main-btn-sm" data-toggle="modal" data-target="#exampleModal"><?= $isThai ? 'วิธีตรวจสอบสถานะ' : 'HOW TO CHECK' ?></button>
                        <input type="submit" class="main-btn-sm" value="<?= $isThai ? 'ติดตาม' : 'CHECK NOW' ?>">
                    </div>

                    <div class="mobile-only">
                        <div class="btn-mobile">
                            <a href="<?= base_url('contact') ?>">
                                <div class="control-txt">
                                    <i class="fa fa-envelope-o edit-ico"></i>
                                    <div class="txt-sub-menu">CONTACT US</div>
                                </div>
                            </a>
                        </div>
                        <div class="btn-mobile">
                            <a href="https://www.houseofsamsonite.co.th/">
                                <div class="control-txt">
                                    <i class="fa fa-shopping-bag edit-ico"></i>
                                    <div class="txt-sub-menu">SHOPPING</div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="exampleModal" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title topic-txt-hm">HOW TO CHECK</h5>
                                </div>
                                <div class="modal-body">
                                    <div class="" style="text-align: left; line-height: 1; font-size: 1.2em; color: #7b7b7b;">
                                        <img class="rs-bg-size" src="<?= $popup ?>">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn custom-btn" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script type="text/javascript">
                        $('#btnModal').click(function() {
                            $('#myModal').modal('show');
                        });
                    </script>
                </div>
            </div>
            <?php if ($notFound): ?>
                <div class="col-md-4">
                    <div class="alert alert-danger alert-dismissable">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <?= esc($notFoundMessage) ?>
                    </div>
                </div>
            <?php endif ?>
        </div>
    </section>
</form>
