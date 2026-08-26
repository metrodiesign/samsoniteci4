<?php

/** @var list<array<string, mixed>> $rows */
?>
<section aria-labelledby="page-title">
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ฺId</th>
                <th>Track</th>
                <th>Tracks tatus</th>
                <th>Contact</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
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
                    <td><a href="/backgrounds/<?= (int) $item['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
    </div>
</section>
