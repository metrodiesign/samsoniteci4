<?php

/** @var string $language */
/** @var string $submissionId */
/** @var bool $submitted */
/** @var array<string, string> $values */
/** @var array<string, string> $errors */

$thai   = $language === 'th';
$values = $values ?? [];
$errors = $errors ?? [];
$text   = $thai
    ? [
        'repair'      => 'ศูนย์บริการซ่อม',
        'address'     => ["3388/25-37,\u{00A0}51-53 อาคารสิรินรัตน์ ชั้น 8", 'ถนนพระราม 4 คลองตัน คลองเตย', 'กรุงเทพฯ 10110', 'โทร. 02-229-7190-95'],
        'map'         => 'แผนที่',
        'relation'    => ['ลูกค้าสัมพันธ์'],
        'contactInfo' => ['อีเมล : INFO.THAILAND@SAMSONITE.COM', 'เปิด : จันทร์-ศุกร์ 9.00 - 17.00 น.', 'โทร. 02-761-9999'],
        'moreTopic'   => 'ข้อมูลเพิ่มเติม',
        'moreSub'     => 'กรอกข้อมูลของคุณ',
        'fullname'    => 'ชื่อ-สกุล *',
        'email'       => 'อีเมล *',
        'phone'       => 'เบอร์โทรศัพท์ *',
        'detail'      => 'รายละเอียด *',
        'send'        => 'ส่ง',
        'received'    => 'รับข้อความแล้ว',
        'errors'      => [
            'fullname' => 'กรุณากรอกชื่อ (ไม่เกิน 128 ตัวอักษร)',
            'email'    => 'กรุณากรอกอีเมลที่ถูกต้อง (ไม่เกิน 128 ตัวอักษร)',
            'phone'    => 'กรุณากรอกเบอร์โทรศัพท์ที่ถูกต้อง (7-32 ตัว: ตัวเลข เว้นวรรค + -)',
            'detail'   => 'กรุณากรอกรายละเอียด (ไม่เกิน 4000 ตัวอักษร)',
        ],
    ]
    : [
        'repair'      => 'REPAIR CENTER',
        'address'     => ['3388/25-97, 51-53 SIRINRAT BLDG., 8TH FLR.,', 'RAMA 4 RD., KLONG-TON, KLONG-TOEY,', 'BANGKOK 10110', 'TEL. 02-229-7190-95'],
        'map'         => 'Google Map',
        'relation'    => ['CUSTOMER', 'RELATION'],
        'contactInfo' => ['EMAIL : INFO.THAILAND@SAMSONITE.COM', 'OPEN : MON - FRI 9.00 AM - 5.00 PM', 'TEL. 02-761-9999'],
        'moreTopic'   => 'MORE INFOMATION',
        'moreSub'     => 'FILL YOUR INFORMATION',
        'fullname'    => 'NAME & SURNAME *',
        'email'       => 'E-mail *',
        'phone'       => 'PHONE NUMBER *',
        'detail'      => 'DETAIL *',
        'send'        => 'SEND NOW',
        'received'    => 'Message received',
        'errors'      => [
            'fullname' => 'Please enter your name (max 128 characters).',
            'email'    => 'Please enter a valid email address (max 128 characters).',
            'phone'    => 'Please enter a valid phone number (7-32 chars: digits, spaces, + or -).',
            'detail'   => 'Please enter your message (max 4000 characters).',
        ],
    ];
$hasErrors = $errors !== [];
?>
<section id="contact">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 con-box-wrapper">
                <div class="col-sm-12 col-xs-12 col-lg-6 contact-wrapper" style="border-right: 1px solid #CCCCCC;">
                    <div class="txt-con-service">
                        <img class="ico-topic-size" src="<?= base_url('assets/images/img-contact-1.png') ?>" alt="">
                        <div class="txt-hm-topic">
                            <?= esc($text['repair']) ?>
                        </div>
                    </div>
                    <div class="ico-txt-detail">
                        <?php foreach ($text['address'] as $line): ?>
                            <div class=""><?= esc($line) ?></div>
                        <?php endforeach ?>
                    </div>
                    <div<?= $thai ? ' class="text-right"' : ' class="" style="text-align: right"' ?>>
                        <input type="submit" class="main-btn-sm" value="<?= esc($text['map']) ?>" onclick="window.location.href='https://goo.gl/maps/uH7TMBuW1w22'">
                    </div>
                </div>
                <div class="col-sm-12 col-xs-12 col-lg-6 contact-wrapper" style="padding-left: 50px;">
                    <div class="txt-con-service">
                        <img class="ico-topic-size" src="<?= base_url('assets/images/img-contact-2.png') ?>" style="width: 70px;" alt="">
                        <div class="txt-hm-topic">
                            <?php foreach ($text['relation'] as $line): ?>
                                <div class=""><?= esc($line) ?></div>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div class="ico-txt-detail">
                        <?php foreach ($text['contactInfo'] as $line): ?>
                            <div class=""><?= esc($line) ?></div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 con-box-info">
                <div class="topic-txt-hm"><?= esc($text['moreTopic']) ?></div>
                <div class="topic-txt-sm"><?= esc($text['moreSub']) ?></div>
                <form role="form" id="addContact" action="<?= $thai ? base_url('contact_th/addContact') : base_url('contact/addContact') ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="submission_id" value="<?= esc($submissionId) ?>">
                    <div class="control-input">
                        <input type="text" name="fullname" id="fullname" class="main-input form-control required" value="<?= esc($values['fullname'] ?? '') ?>" maxlength="128" required placeholder="<?= esc($text['fullname']) ?>">
                    </div>
                    <div class="control-input">
                        <input type="text" name="email" class="main-input form-control required email" id="email" value="<?= esc($values['email'] ?? '') ?>" maxlength="128" required placeholder="<?= esc($text['email']) ?>">
                    </div>
                    <div class="control-input">
                        <input type="text" name="phone" id="phone" class="main-input form-control required" value="<?= esc($values['phone'] ?? '') ?>" maxlength="32" required placeholder="<?= esc($text['phone']) ?>">
                    </div>
                    <div class="control-input">
                        <textarea type="text" name="detail" id="detail" class="main-input form-control required" rows="5" maxlength="4000" required placeholder="<?= esc($text['detail']) ?>"><?= esc($values['detail'] ?? '') ?></textarea>
                    </div>
                    <input type="submit" class="main-btn-sm" value="<?= esc($text['send']) ?>">
                </form>
            </div>
        </div>

        <div class="col-md-4">
            <?php if ($submitted): ?>
                <div class="alert alert-success alert-dismissable" role="status">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                    <?= esc($text['received']) ?>
                </div>
            <?php endif ?>
            <div class="row">
                <div class="col-md-12">
                    <?php if ($hasErrors): ?>
                        <div class="alert alert-danger alert-dismissable" role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                            <?php foreach (array_keys($errors) as $field): ?>
                                <div><?= esc($text['errors'][$field] ?? 'Invalid contact submission.') ?></div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</section>
