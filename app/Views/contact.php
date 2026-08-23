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
$fieldError = static function (string $field) use ($errors, $messages): string {
    if (! isset($errors[$field])) {
        return '';
    }

    return '<p class="alert" role="alert">' . esc($messages[$field]) . '</p>';
};
?>
<section aria-labelledby="contact-title">
    <h1 id="contact-title"><?= $thai ? 'ติดต่อเรา' : 'Contact us' ?></h1>
    <?php if ($submitted): ?>
        <p role="status"><?= $thai ? 'รับข้อความแล้ว' : 'Message received' ?></p>
    <?php endif ?>
    <form method="post" action="<?= $thai ? '/contact-th' : '/contact' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="submission_id" value="<?= esc($submissionId) ?>">
        <label for="contact-name"><?= $thai ? 'ชื่อ' : 'Full name' ?></label>
        <input id="contact-name" name="fullname" maxlength="128" value="<?= esc($values['fullname'] ?? '') ?>" required>
        <?= $fieldError('fullname') ?>
        <label for="contact-email"><?= $thai ? 'อีเมล' : 'Email' ?></label>
        <input id="contact-email" name="email" type="email" maxlength="128" value="<?= esc($values['email'] ?? '') ?>" required>
        <?= $fieldError('email') ?>
        <label for="contact-phone"><?= $thai ? 'โทรศัพท์' : 'Phone' ?></label>
        <input id="contact-phone" name="phone" maxlength="32" value="<?= esc($values['phone'] ?? '') ?>" required>
        <?= $fieldError('phone') ?>
        <label for="contact-detail"><?= $thai ? 'รายละเอียด' : 'Message' ?></label>
        <textarea id="contact-detail" name="detail" maxlength="4000" required><?= esc($values['detail'] ?? '') ?></textarea>
        <?= $fieldError('detail') ?>
        <button type="submit"><?= $thai ? 'ส่งข้อความ' : 'Send message' ?></button>
    </form>
</section>
