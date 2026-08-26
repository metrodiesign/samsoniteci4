<?php
/**
 * @var string $submissionId
 * @var list<array<string, mixed>> $books
 * @var list<array<string, mixed>> $types
 * @var list<array<string, mixed>> $brands
 * @var list<array<string, mixed>> $branches
 * @var list<array<string, mixed>> $conditions
 * @var list<array<string, mixed>> $estimatePrices
 * @var list<array<string, mixed>> $fixedItems
 */

$checks = static function (array $items, string $name, string $idKey, string $labelKey): void {
    foreach ($items as $item) {
        echo '<label><input type="checkbox" name="' . esc($name) . '[]" value="' . esc((string) $item[$idKey]) . '"> '
            . esc((string) $item[$labelKey]) . '</label> ';
    }
};

$select = static function (string $name, array $items, string $idKey, string $labelKey, string $current): void {
    echo '<select id="order-' . esc($name) . '" name="' . esc($name) . '">';
    foreach ($items as $item) {
        $value = (string) $item[$idKey];
        echo '<option value="' . esc($value) . '"' . ($value === $current ? ' selected' : '') . '>'
            . esc((string) $item[$labelKey]) . '</option>';
    }
    echo '</select>';
};
?>
<section aria-labelledby="page-title">
    <form id="order-new-form" method="post" action="/orders/new" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="submission_id" value="<?= esc($submissionId) ?>">

        <label class="custom-form"><input type="checkbox" name="detail_agent" value="1"> Urgent/ซ่อมด่วน</label>

        <label for="order-number_id">number ID/เลขที</label>
        <input id="order-number_id" name="number_id" inputmode="numeric" pattern="[0-9]+" maxlength="96" required>

        <label for="order-book_id">book Short/เล่มที่</label>
        <select id="order-book_id" name="book_id" required>
            <option value=""></option>
            <?php foreach ($books as $book): ?>
                <option value="<?= (int) $book['book_id'] ?>" data-book-detail="<?= esc((string) $book['book_detail'], 'attr') ?>" data-branch-id="<?= (int) $book['branch_id'] ?>"><?= esc((string) $book['book_detail']) ?></option>
            <?php endforeach ?>
        </select>

        <label for="order-id-preview">order ID/เลขที่ใบสั่งซ่อม</label>
        <output id="order-id-preview" aria-live="polite"></output>

        <?php
        // Label ต่อ field verbatim จาก CI3 tracking/add_order.php
        /** @var array<string, string> $orderLabels */
        $orderLabels = [
            'customer_name' => 'customer Fullname/ชื่อลูกค้า',
            'customer_tel' => 'MOBILE TEL/เบอร์มือถือลูกค้า',
            'customer_email' => 'customer Email/อีเมล์ลูกค้า',
            'note' => 'Note/หมายเหตุ',
        ];
        ?>
        <?php foreach (['customer_name', 'customer_tel', 'customer_email', 'note'] as $field): ?>
            <label for="order-<?= esc($field) ?>"><?= esc($orderLabels[$field] ?? $field) ?></label>
            <input id="order-<?= esc($field) ?>" name="<?= esc($field) ?>">
        <?php endforeach ?>

        <label for="order-type_id">CATEGORY/ประเภท</label>
        <?php $select('type_id', $types, 'type_id', 'type_details', '') ?>

        <label for="order-brand_id">BRAND/ยี่ห้อ</label>
        <?php $select('brand_id', $brands, 'brand_id', 'brand_details', '') ?>

        <label for="order-branch_id">สาขา</label>
        <?php $select('branch_id', $branches, 'branch_id', 'branch_name', '') ?>

        <label for="order-customer_tel2">MOBILE TEL 2/เบอร์โทรศัพท์ลูกค้า2</label>
        <input id="order-customer_tel2" name="customer_tel2">

        <label for="order-detail_date_purchase">PURCHASED DATE/วันที่ซื้อ (dd/mm/yyyy)</label>
        <input id="order-detail_date_purchase" name="detail_date_purchase" placeholder="dd/mm/yyyy">

        <label for="order-detail_sku_name">SKU Name/ชื่อสินค้า</label>
        <input id="order-detail_sku_name" name="detail_sku_name">

        <fieldset>
            <legend>Waranty Type/ประเภทการรับประกัน</legend>
            <label><input type="radio" name="waranty_type" value="0" checked> ไม่มี</label>
            <label><input type="radio" name="waranty_type" value="1"> มี</label>
        </fieldset>
        <label for="order-detail_number_waranty">Number Waranty/หมายเลขประกัน</label>
        <input id="order-detail_number_waranty" name="detail_number_waranty">

        <fieldset>
            <legend>Condition/อาการที่ส่งซ่อม</legend>
            <?php $checks($conditions, 'condition', 'condition_id', 'condition_details') ?>
            <label for="order-condition_other">อื่นๆ</label>
            <input id="order-condition_other" name="condition_other">
        </fieldset>

        <fieldset>
            <legend>Estimate Price/ประเมินราคาส่งซ่อม</legend>
            <?php $checks($estimatePrices, 'estimateprice', 'estimateprice_id', 'estimateprice_details') ?>
            <label for="order-estimateprice_other">อื่นๆ</label>
            <input id="order-estimateprice_other" name="estimateprice_other">
        </fieldset>

        <fieldset>
            <legend>Fixed/สภาพ,ตำหนิ</legend>
            <?php $checks($fixedItems, 'fixed', 'fixed_id', 'fixed_details') ?>
            <label for="order-fixed_other">อื่นๆ</label>
            <input id="order-fixed_other" name="fixed_other">
        </fieldset>

        <label for="order-detail_equipment">Equipment/อุปกรณ์ที่มาพร้อมกับสินค้า</label>
        <textarea id="order-detail_equipment" name="detail_equipment"></textarea>

        <label for="order-create_by_user">Created by/พนักงานผู้รับสินค้า</label>
        <input id="order-create_by_user" name="create_by_user">

        <label for="order-image">Repair image (up to 5)</label>
        <input id="order-image" name="detail_image[]" type="file" accept="image/png,image/jpeg,image/gif" multiple>
        <button type="submit">Submit</button>
        <button type="reset">Reset</button>
    </form>
</section>
<script>
(() => {
    const form = document.getElementById('order-new-form');
    const branch = document.getElementById('order-branch_id');
    const book = document.getElementById('order-book_id');
    const number = document.getElementById('order-number_id');
    const preview = document.getElementById('order-id-preview');
    const sync = () => {
        const branchId = branch.value;
        for (const option of book.options) {
            if (option.value === '') continue;
            const visible = branchId !== '' && option.dataset.branchId === branchId;
            option.hidden = !visible;
            option.disabled = !visible;
        }
        if (book.selectedOptions[0]?.disabled) book.value = '';
        const selected = book.selectedOptions[0];
        preview.value = selected?.dataset.bookDetail && /^[0-9]{1,96}$/.test(number.value)
            ? `${selected.dataset.bookDetail}/${number.value}`
            : '';
    };
    branch.addEventListener('change', sync);
    book.addEventListener('change', sync);
    number.addEventListener('input', sync);
    form.addEventListener('reset', () => setTimeout(sync, 0));
    sync();
})();
</script>
