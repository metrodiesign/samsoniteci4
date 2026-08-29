<?php
/**
 * @var string $submissionId
 * @var list<array<string, mixed>> $books
 * @var list<array<string, mixed>> $types
 * @var list<array<string, mixed>> $brands
 * @var list<array<string, mixed>> $branches
 * @var list<array<string, mixed>> $branchTypes
 * @var string $requestDate
 * @var list<array<string, mixed>> $conditions
 * @var list<array<string, mixed>> $estimatePrices
 * @var list<array<string, mixed>> $fixedItems
 * @var string $caption
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
<div class="content-wrapper">
    <div class="background-form" style="background-image: url(<?= base_url('assets/images/bg-form.png') ?>);"></div>
    <section class="content-header">
        <h1><i class="fa fa-cart-arrow-down"></i> NEW REQUEST REPAIR</h1>
    </section>
    <section class="content">
    <div class="card">
        <h3 class="box-title"><?= esc($caption) ?></h3>
    <form id="addOrder" method="post" action="<?= base_url('addNewOrders') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="submission_id" value="<?= esc($submissionId) ?>">

        <h3>Urgent/ซ่อมด่วน</h3>
        <label class="custom-form"><input type="checkbox" name="detailAgent" value="1"> Urgent/ซ่อมด่วน</label>

        <?php // Labels verbatim from CI3 tracking/add_order.php, mixed case included: the uppercase
              // rendering there comes from CSS text-transform, not from the text itself. ?>
        <label for="order-request_date">request Date/วันที่ส่งซ่อม <span class="remark">*</span></label>
        <input id="order-request_date" name="requestDate" value="<?= esc($requestDate) ?>" placeholder="DD/MM/YYYY" readonly>

        <label for="order-branch_type">Branch Type/ประเภทของสาขา <span class="remark">*</span></label>
        <select id="order-branch_type" name="branch_type">
            <option value="0">Select Branch Type</option>
            <?php foreach ($branchTypes as $branchType): ?>
                <option value="<?= (int) $branchType['branch_type_id'] ?>"><?= esc((string) $branchType['branch_type_details']) ?></option>
            <?php endforeach ?>
        </select>

        <label for="order-number_id">number ID/เลขที</label>
        <input id="order-number_id" name="numberID" inputmode="numeric" pattern="[0-9]+" maxlength="96" required>

        <label for="order-book_id">book Short/เล่มที่</label>
        <select id="order-book_id" name="bookshort" required>
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
            <input id="order-<?= esc($field) ?>" name="<?= esc([
                'customer_name' => 'customerFullname', 'customer_tel' => 'customerTel',
                'customer_email' => 'email',
            ][$field] ?? $field) ?>">
        <?php endforeach ?>

        <label for="order-detailTypeId">CATEGORY/ประเภท</label>
        <?php $select('detailTypeId', $types, 'type_id', 'type_details', '') ?>

        <label for="order-detailBrandId">BRAND/ยี่ห้อ</label>
        <?php $select('detailBrandId', $brands, 'brand_id', 'brand_details', '') ?>

        <label for="order-branch_id">Branch/สาขา <span class="remark">*</span></label>
        <select id="order-branch_id" name="branch_id">
            <?php foreach ($branches as $branch): ?>
                <option value="<?= (int) $branch['branch_id'] ?>" data-branch-type="<?= (int) $branch['branch_type'] ?>" data-branch-short="<?= esc((string) ($branch['default_suffix'] ?? ''), 'attr') ?>"><?= esc((string) $branch['branch_name']) ?></option>
            <?php endforeach ?>
        </select>

        <label for="order-branch_short">branch short/ตัวย่อสาขา <span class="remark">*</span></label>
        <input id="order-branch_short" name="branchshort" readonly>

        <label for="order-customer_tel2">MOBILE TEL 2/เบอร์โทรศัพท์ลูกค้า2</label>
        <input id="order-customer_tel2" name="customerTelTwo">

        <label for="order-detail_date_purchase">PURCHASED DATE/วันที่ซื้อ (dd/mm/yyyy)</label>
        <input id="order-detail_date_purchase" name="detailDatePurchase" placeholder="dd/mm/yyyy">

        <label for="order-detail_sku_name">SKU Name/ชื่อสินค้า</label>
        <input id="order-detail_sku_name" name="detailSKUName">

        <fieldset>
            <legend>Waranty Type/ประเภทการรับประกัน</legend>
            <label><input type="radio" name="warantyType" value="0" checked> ไม่มี</label>
            <label><input type="radio" name="warantyType" value="1"> มี</label>
        </fieldset>
        <label for="order-detail_number_waranty">Number Waranty/หมายเลขประกัน</label>
        <input id="order-detail_number_waranty" name="detailNumberWaranty">

        <fieldset>
            <legend>Condition/อาการที่ส่งซ่อม</legend>
            <?php $checks($conditions, 'condition', 'condition_id', 'condition_details') ?>
            <label for="order-condition_other">อื่นๆ</label>
            <input id="order-condition_other" name="detailConditionOther">
        </fieldset>

        <fieldset>
            <legend>Estimate Price/ประเมินราคาส่งซ่อม</legend>
            <?php $checks($estimatePrices, 'estimateprice', 'estimateprice_id', 'estimateprice_details') ?>
            <label for="order-estimateprice_other">อื่นๆ</label>
            <input id="order-estimateprice_other" name="detailEstimatePriceOther">
        </fieldset>

        <fieldset>
            <legend>Fixed/สภาพ,ตำหนิ</legend>
            <?php $checks($fixedItems, 'fixed', 'fixed_id', 'fixed_details') ?>
            <label for="order-fixed_other">อื่นๆ</label>
            <input id="order-fixed_other" name="detailFixedOther">
        </fieldset>

        <label for="order-detail_equipment">Equipment/อุปกรณ์ที่มาพร้อมกับสินค้า</label>
        <textarea id="order-detail_equipment" name="detailEquipment"></textarea>

        <label for="order-create_by_user">Created by/พนักงานผู้รับสินค้า</label>
        <input id="order-create_by_user" name="create_by_user">

        <input id="order-image" name="detail_image[]" type="file" accept="image/png,image/jpeg,image/gif" multiple hidden>
        <button type="submit">Submit</button>
        <button type="reset">Reset</button>
    </form>
    <?= view('partials/order_upload', ['submissionId' => $submissionId, 'targetId' => 'order-image']) ?>
    </div>
    </section>
</div>
<script>
(() => {
    const form = document.getElementById('addOrder');
    const branch = document.getElementById('order-branch_id');
    const branchType = document.getElementById('order-branch_type');
    const branchShort = document.getElementById('order-branch_short');
    const book = document.getElementById('order-book_id');
    const number = document.getElementById('order-number_id');
    const preview = document.getElementById('order-id-preview');
    const sync = () => {
        // CI3 narrows the branch list by branch type and fills branch short from the branch.
        // "0" is CI3's Select Branch Type placeholder and means no filter.
        const typeId = branchType.value;
        let firstVisible = null;
        for (const option of branch.options) {
            const visible = typeId === '0' || option.dataset.branchType === typeId;
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible && firstVisible === null) firstVisible = option;
        }
        if (branch.selectedOptions[0]?.disabled) branch.value = firstVisible ? firstVisible.value : '';
        branchShort.value = branch.selectedOptions[0]?.dataset.branchShort ?? '';
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
    branchType.addEventListener('change', sync);
    branch.addEventListener('change', sync);
    book.addEventListener('change', sync);
    number.addEventListener('input', sync);
    form.addEventListener('reset', () => setTimeout(sync, 0));
    sync();
})();
</script>
