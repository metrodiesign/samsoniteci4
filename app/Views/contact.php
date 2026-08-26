<?php

/** @var string $language */
/** @var string $submissionId */
/** @var bool $submitted */
/** @var array<string, string> $values */
/** @var array<string, string> $errors */
$thai     = $language === 'th';
$values   = $values ?? [];
$errors   = $errors ?? [];
$messages = $thai
    ? [
        'fullname' => 'กรุณากรอกชื่อ (ไม่เกิน 128 ตัวอักษร)',
        'email'    => 'กรุณากรอกอีเมลที่ถูกต้อง (ไม่เกิน 128 ตัวอักษร)',
        'phone'    => 'กรุณากรอกเบอร์โทรศัพท์ที่ถูกต้อง (7-32 ตัว: ตัวเลข เว้นวรรค + -)',
        'detail'   => 'กรุณากรอกรายละเอียด (ไม่เกิน 4000 ตัวอักษร)',
    ]
    : [
        'fullname' => 'Please enter your name (max 128 characters).',
        'email'    => 'Please enter a valid email address (max 128 characters).',
        'phone'    => 'Please enter a valid phone number (7-32 chars: digits, spaces, + or -).',
        'detail'   => 'Please enter your message (max 4000 characters).',
    ];
// ข้อความทั้งสองภาษาคัดจาก CI3 `application/views/{en,th}/contact.php` คำต่อคำ
// รวมทั้งเลขที่อยู่ที่ EN กับ TH ไม่ตรงกัน (25-97 กับ 25-37) และ typo "INFOMATION"
$text = $thai
    ? [
        'repair'      => 'ศูนย์บริการซ่อม',
        'address'     => ['3388/25-37, 51-53 อาคารสิรินรัตน์ ชั้น 8', 'ถนนพระราม 4 คลองตัน คลองเตย', 'กรุงเทพฯ 10110', 'โทร. 02-229-7190-95'],
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
    ];
$fieldError = static function (string $field) use ($errors, $messages): string {
    if (! isset($errors[$field])) {
        return '';
    }

    return '<p class="alert" role="alert">' . esc($messages[$field]) . '</p>';
};
?>
<section id="contact" aria-labelledby="contact-title">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 con-box-wrapper">
                <div class="col-sm-12 col-xs-12 col-lg-6 contact-wrapper" style="border-right: 1px solid #CCCCCC;">
                    <div class="txt-con-service">
                        <img class="ico-topic-size" src="<?= base_url('assets/images/img-contact-1.png') ?>" alt="">
                        <div class="txt-hm-topic" id="contact-title"><?= esc($text['repair']) ?></div>
                    </div>
                    <div class="ico-txt-detail">
                        <?php foreach ($text['address'] as $line): ?>
                            <div><?= esc($line) ?></div>
                        <?php endforeach ?>
                    </div>
                    <div class="text-right">
                        <a class="main-btn-sm" href="https://goo.gl/maps/uH7TMBuW1w22"><?= esc($text['map']) ?></a>
                    </div>
                </div>
                <div class="col-sm-12 col-xs-12 col-lg-6 contact-wrapper" style="padding-left: 50px;">
                    <div class="txt-con-service">
                        <img class="ico-topic-size" src="<?= base_url('assets/images/img-contact-2.png') ?>" style="width: 70px;" alt="">
                        <div class="txt-hm-topic">
                            <?php foreach ($text['relation'] as $line): ?>
                                <div><?= esc($line) ?></div>
                            <?php endforeach ?>
                        </div>
                    </div>
                    <div class="ico-txt-detail">
                        <?php foreach ($text['contactInfo'] as $line): ?>
                            <div><?= esc($line) ?></div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 con-box-info">
                <div class="topic-txt-hm"><?= esc($text['moreTopic']) ?></div>
                <div class="topic-txt-sm"><?= esc($text['moreSub']) ?></div>
                <?php if ($submitted): ?>
                    <p role="status"><?= esc($text['received']) ?></p>
                <?php endif ?>
                <form method="post" action="<?= $thai ? '/contact-th' : '/contact' ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="submission_id" value="<?= esc($submissionId) ?>">
                    <div class="control-input">
                        <input id="contact-name" class="main-input" name="fullname" maxlength="128"
                               value="<?= esc($values['fullname'] ?? '') ?>" required
                               placeholder="<?= esc($text['fullname']) ?>" aria-label="<?= esc($text['fullname']) ?>">
                        <?= $fieldError('fullname') ?>
                    </div>
                    <div class="control-input">
                        <input id="contact-email" class="main-input" name="email" type="email" maxlength="128"
                               value="<?= esc($values['email'] ?? '') ?>" required
                               placeholder="<?= esc($text['email']) ?>" aria-label="<?= esc($text['email']) ?>">
                        <?= $fieldError('email') ?>
                    </div>
                    <div class="control-input">
                        <input id="contact-phone" class="main-input" name="phone" maxlength="32"
                               value="<?= esc($values['phone'] ?? '') ?>" required
                               placeholder="<?= esc($text['phone']) ?>" aria-label="<?= esc($text['phone']) ?>">
                        <?= $fieldError('phone') ?>
                    </div>
                    <div class="control-input">
                        <textarea id="contact-detail" class="main-input" name="detail" maxlength="4000" rows="5" required
                                  placeholder="<?= esc($text['detail']) ?>" aria-label="<?= esc($text['detail']) ?>"><?= esc($values['detail'] ?? '') ?></textarea>
                        <?= $fieldError('detail') ?>
                    </div>
                    <button type="submit" class="main-btn-sm"><?= esc($text['send']) ?></button>
                </form>
            </div>
        </div>
    </div>
</section>
