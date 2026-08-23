<?php

/** @var string $language */
/** @var string $submissionId */
/** @var bool $submitted */
$thai = $language === 'th';
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
        <input id="contact-name" name="fullname" maxlength="128" required>
        <label for="contact-email"><?= $thai ? 'อีเมล' : 'Email' ?></label>
        <input id="contact-email" name="email" type="email" maxlength="128" required>
        <label for="contact-phone"><?= $thai ? 'โทรศัพท์' : 'Phone' ?></label>
        <input id="contact-phone" name="phone" maxlength="32" required>
        <label for="contact-detail"><?= $thai ? 'รายละเอียด' : 'Message' ?></label>
        <textarea id="contact-detail" name="detail" maxlength="4000" required></textarea>
        <button type="submit"><?= $thai ? 'ส่งข้อความ' : 'Send message' ?></button>
    </form>
</section>
