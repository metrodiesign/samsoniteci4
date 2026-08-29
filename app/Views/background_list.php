<?php

/** @var list<array<string, mixed>> $rows */
/** @var string $caption */
?>
<div class="content-wrapper">
    <div class="background-form" style="background-image: url(<?= base_url('assets/images/bg-form.png') ?>);"></div>
    <div class="content-form"><section class="content-header"><div class="row"><div class="col-xs-6">
        <h1><i class="fa fa-link"></i> background Web EN <small>Edit</small></h1>
    </div></div></section><section class="content"><div class="row"><div class="col-xs-12"><div class="box box-scroll">
        <div class="box-header"><h3 class="box-title"><?= esc($caption) ?></h3></div>
        <div class="box-body table-responsive no-padding"><table class="table table-hover"><tr>
            <th>ฺId</th><th>Track</th><th>Tracks tatus</th><th>Contact</th><th>Status</th><th class="text-center">Actions</th>
        </tr>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= (int) $item['id'] ?></td>
                    <td>
                        <?php if (is_string($item['image_track_laptop'] ?? null) && preg_match('/\A[a-f0-9]{32}\.png\z/D', $item['image_track_laptop']) === 1): ?>
                            <img src="/background-image/<?= esc(rawurlencode($item['image_track_laptop']), 'attr') ?>" alt="Track preview" height="100">
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if (is_string($item['image_trackstatus_laptop'] ?? null) && preg_match('/\A[a-f0-9]{32}\.png\z/D', $item['image_trackstatus_laptop']) === 1): ?>
                            <img src="/background-image/<?= esc(rawurlencode($item['image_trackstatus_laptop']), 'attr') ?>" alt="Track status preview" height="100">
                        <?php endif ?>
                    </td>
                    <td>
                        <?php if (is_string($item['image_contact_laptop'] ?? null) && preg_match('/\A[a-f0-9]{32}\.png\z/D', $item['image_contact_laptop']) === 1): ?>
                            <img src="/background-image/<?= esc(rawurlencode($item['image_contact_laptop']), 'attr') ?>" alt="Contact preview" height="100">
                        <?php endif ?>
                    </td>
                    <td><?= (int) ($item['status'] ?? 0) === 1 ? 'Publishing' : 'Unpublish' ?></td>
                    <td class="text-center"><a class="btn btn-sm btn-info" href="<?= base_url('editBackgroundOld/' . (int) $item['id']) ?>" title="Edit"><i class="fa fa-pencil"></i></a></td>
                </tr>
            <?php endforeach ?>
        </table></div>
    </div></div></div></section></div>
</div>
