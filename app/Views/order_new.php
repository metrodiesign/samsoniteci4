<?php /** @var string $submissionId */ ?>
<section aria-labelledby="order-new-title">
    <h1 id="order-new-title">New repair order</h1>
    <form method="post" action="/orders/new" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="submission_id" value="<?= esc($submissionId) ?>">
        <?php foreach (['number_id', 'order_id', 'book_id', 'customer_name', 'customer_tel', 'customer_email', 'type_id', 'brand_id', 'branch_id', 'note'] as $field): ?>
            <label for="order-<?= esc($field) ?>"><?= esc($field) ?></label>
            <input id="order-<?= esc($field) ?>" name="<?= esc($field) ?>" <?= in_array($field, ['type_id', 'brand_id', 'branch_id'], true) ? 'type="number"' : '' ?>>
        <?php endforeach ?>
        <label for="order-image">Repair image</label>
        <input id="order-image" name="detail_image" type="file" accept="image/png">
        <button type="submit">Create order</button>
    </form>
</section>
