<?php /** @var array<string, mixed> $row */ ?>
<section aria-labelledby="order-edit-title">
    <h1 id="order-edit-title">Edit <?= esc($row['trackID']) ?></h1>
    <form method="post" action="/orders/<?= (int) $row['request_id'] ?>">
        <?= csrf_field() ?>
        <?php foreach ([
            'customer_name' => 'customerFullname', 'customer_tel' => 'customerTel',
            'customer_email' => 'customerEmail', 'type_id' => 'detailTypeId',
            'brand_id' => 'detailBrandId', 'note' => 'detailNote',
        ] as $field => $column): ?>
            <label for="edit-<?= esc($field) ?>"><?= esc($field) ?></label>
            <input id="edit-<?= esc($field) ?>" name="<?= esc($field) ?>" value="<?= esc($row[$column] ?? '') ?>">
        <?php endforeach ?>
        <button type="submit">Update order</button>
    </form>
</section>
