<?php

/** @var list<string> $fields */
/** @var array<string, mixed>|null $row */
$action = '/backgrounds' . ($row === null ? '' : '/' . (int) $row['id']);

// [addText, editText] per field, verbatim from CI3 master/add_background.php และ edit_background.php
// (คง typo/doubled space; _th 6 field ไม่มี UI ใน CI3 = invented ตามแบบ EN)
/** @var array<string, array{0: string, 1: string}> $backgroundLabels */
$backgroundLabels = [
    'image_track_laptop' => ['image track aptop (en)', 'laptop size 1920px (en)'],
    'image_track_mobile' => ['image track mobile  (en)', 'mobile size 480px (en)'],
    'image_trackstatus_laptop' => ['image trackstatus laptop  (en)', 'laptop size 1920px (en)'],
    'image_trackstatus_mobile' => ['image trackstatus mobile  (en)', 'mobile size 480px (en)'],
    'image_contact_laptop' => ['image contact laptop  (en)', 'laptop size 1920px (en)'],
    'image_contact_mobile' => ['image contact mobile  (en)', 'mobile size 480px (en)'],
    'image_track_laptop_th' => ['image track laptop (th)', 'laptop size 1920px (th)'],
    'image_track_mobile_th' => ['image track mobile (th)', 'mobile size 480px (th)'],
    'image_trackstatus_laptop_th' => ['image trackstatus laptop (th)', 'laptop size 1920px (th)'],
    'image_trackstatus_mobile_th' => ['image trackstatus mobile (th)', 'mobile size 480px (th)'],
    'image_contact_laptop_th' => ['image contact laptop (th)', 'laptop size 1920px (th)'],
    'image_contact_mobile_th' => ['image contact mobile (th)', 'mobile size 480px (th)'],
];
?>
<section aria-labelledby="page-title">
    <form method="post" action="<?= esc($action) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label for="background-status">Status</label>
        <select id="background-status" name="status" required>
            <option value="1" <?= (int) ($row['status'] ?? 1) === 1 ? 'selected' : '' ?>>Publishing</option>
            <option value="2" <?= (int) ($row['status'] ?? 1) === 2 ? 'selected' : '' ?>>Unpublish</option>
        </select>
        <?php
        // CI3 groups the image fields under three cards; `$sections` keeps the CI3 wording and
        // the field-name prefix each card owns.
        $sections = [
            'image_track_' => 'ENTER BACKGROUND: TRACK & TRACE',
            'image_trackstatus_' => 'ENTER BACKGROUND: TRACK STATUS',
            'image_contact_' => 'ENTER BACKGROUND: CONTACT US',
        ];
        ?>
        <?php foreach ($sections as $prefix => $sectionTitle): ?>
            <h3 class="box-title"><?= esc($sectionTitle) ?></h3>
            <?php foreach ($fields as $field): ?>
                <?php if (! str_starts_with($field, $prefix)) {
                    continue;
                } ?>
                <?php $labelText = $backgroundLabels[$field][$row === null ? 0 : 1] ?? $field; ?>
                <label for="background-<?= esc($field) ?>"><?= esc($labelText) ?></label>
                <input id="background-<?= esc($field) ?>" name="<?= esc($field) ?>" type="file" accept="image/png">
            <?php endforeach ?>
        <?php endforeach ?>
        <button type="submit">Submit</button>
        <button type="reset">Reset</button>
    </form>
</section>
