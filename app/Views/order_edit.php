<?php
/**
 * @var array<string, mixed> $row
 * @var list<array<string, mixed>> $types
 * @var list<array<string, mixed>> $brands
 * @var list<array<string, mixed>> $branches
 * @var list<array<string, mixed>> $conditions
 * @var list<array<string, mixed>> $estimatePrices
 * @var list<array<string, mixed>> $fixedItems
 * @var string $caption
 */

$fmtDate = static function ($raw): string {
    $raw = (string) ($raw ?? '');
    if ($raw === '' || strncmp($raw, '0000-00-00', 10) === 0) {
        return '';
    }

    return substr($raw, 8, 2) . '/' . substr($raw, 5, 2) . '/' . substr($raw, 0, 4);
};

$checks = static function (array $items, string $name, string $idKey, string $labelKey, string $selectedRaw): void {
    $selected = $selectedRaw === '' ? [] : explode('|', $selectedRaw);
    foreach ($items as $item) {
        $value = (string) $item[$idKey];
        echo '<label><input type="checkbox" name="' . esc($name) . '[]" value="' . esc($value) . '"'
            . (in_array($value, $selected, true) ? ' checked' : '') . '> '
            . esc((string) $item[$labelKey]) . '</label> ';
    }
};

$select = static function (string $name, array $items, string $idKey, string $labelKey, string $current, bool $disabled = false): void {
    echo '<select id="edit-' . esc($name) . '" name="' . esc($name) . '"' . ($disabled ? ' disabled' : '') . '>';
    foreach ($items as $item) {
        $value = (string) $item[$idKey];
        echo '<option value="' . esc($value) . '"' . ($value === $current ? ' selected' : '') . '>'
            . esc((string) $item[$labelKey]) . '</option>';
    }
    echo '</select>';
};

$warantyType = (string) ($row['warantyType'] ?? '0');
?>
<section aria-labelledby="page-title">
    <div class="card">
        <h3 class="box-title"><?= esc($caption) ?></h3>
    <form method="post" action="/orders/<?= (int) $row['request_id'] ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <h3>Urgent/ซ่อมด่วน</h3>
        <label class="custom-form"><input type="checkbox" name="detail_agent" value="1"<?= (string) ($row['detailAgent'] ?? '') === '1' ? ' checked' : '' ?>> Urgent/ซ่อมด่วน</label>

        <?php
        // Label verbatim จาก CI3 tracking/edit_order.php (customer_tel ต่างจากหน้า add)
        /** @var array<string, string> $editLabels */
        $editLabels = [
            'customer_name' => 'customer Fullname/ชื่อลูกค้า',
            'customer_tel' => 'customer Tel/เบอร์โทรลูกค้า',
            'customer_email' => 'customer Email/อีเมล์ลูกค้า',
            'note' => 'Note/หมายเหตุ',
        ];
        ?>
        <?php foreach ([
            'customer_name' => 'customerFullname', 'customer_tel' => 'customerTel',
            'customer_email' => 'customerEmail', 'note' => 'detailNote',
        ] as $field => $column): ?>
            <label for="edit-<?= esc($field) ?>"><?= esc($editLabels[$field] ?? $field) ?></label>
            <input id="edit-<?= esc($field) ?>" name="<?= esc($field) ?>" value="<?= esc((string) ($row[$column] ?? '')) ?>">
        <?php endforeach ?>

        <label for="edit-type_id">CATEGORY/ประเภท</label>
        <?php $select('type_id', $types, 'type_id', 'type_details', (string) ($row['detailTypeId'] ?? '')) ?>

        <label for="edit-brand_id">BRAND/ยี่ห้อ</label>
        <?php $select('brand_id', $brands, 'brand_id', 'brand_details', (string) ($row['detailBrandId'] ?? '')) ?>

        <label for="edit-branch_id">สาขา (แก้ไม่ได้)</label>
        <?php $select('branch_id', $branches, 'branch_id', 'branch_name', (string) ($row['branchID'] ?? ''), true) ?>

        <label for="edit-customer_tel2">MOBILE TEL 2/เบอร์โทรศัพท์ลูกค้า2</label>
        <input id="edit-customer_tel2" name="customer_tel2" value="<?= esc((string) ($row['customerTel2'] ?? '')) ?>">

        <label for="edit-detail_date_purchase">PURCHASED DATE/วันที่ซื้อ (dd/mm/yyyy)</label>
        <input id="edit-detail_date_purchase" name="detail_date_purchase" placeholder="dd/mm/yyyy" value="<?= esc($fmtDate($row['detailDatePurchase'] ?? '')) ?>">

        <label for="edit-detail_sku_name">SKU Name/ชื่อสินค้า</label>
        <input id="edit-detail_sku_name" name="detail_sku_name" value="<?= esc((string) ($row['detailSKUName'] ?? '')) ?>">

        <fieldset>
            <legend>Waranty Type/ประเภทการรับประกัน</legend>
            <label><input type="radio" name="waranty_type" value="0"<?= $warantyType === '1' ? '' : ' checked' ?>> ไม่มี</label>
            <label><input type="radio" name="waranty_type" value="1"<?= $warantyType === '1' ? ' checked' : '' ?>> มี</label>
        </fieldset>
        <label for="edit-detail_number_waranty">Number Waranty/หมายเลขประกัน</label>
        <input id="edit-detail_number_waranty" name="detail_number_waranty" value="<?= esc((string) ($row['detailNumberWaranty'] ?? '')) ?>">

        <fieldset>
            <legend>Condition/อาการที่ส่งซ่อม</legend>
            <?php $checks($conditions, 'condition', 'condition_id', 'condition_details', (string) ($row['detailCondition'] ?? '')) ?>
            <label for="edit-condition_other">อื่นๆ</label>
            <input id="edit-condition_other" name="condition_other" value="<?= esc((string) ($row['detailConditionOther'] ?? '')) ?>">
        </fieldset>

        <fieldset>
            <legend>Estimate Price/ประเมินราคาส่งซ่อม</legend>
            <?php $checks($estimatePrices, 'estimateprice', 'estimateprice_id', 'estimateprice_details', (string) ($row['detailEstimatePrice'] ?? '')) ?>
            <label for="edit-estimateprice_other">อื่นๆ</label>
            <input id="edit-estimateprice_other" name="estimateprice_other" value="<?= esc((string) ($row['detailEstimatePriceOther'] ?? '')) ?>">
        </fieldset>

        <fieldset>
            <legend>Fixed/สภาพ,ตำหนิ</legend>
            <?php $checks($fixedItems, 'fixed', 'fixed_id', 'fixed_details', (string) ($row['detailFixed'] ?? '')) ?>
            <label for="edit-fixed_other">อื่นๆ</label>
            <input id="edit-fixed_other" name="fixed_other" value="<?= esc((string) ($row['detailFixedOther'] ?? '')) ?>">
        </fieldset>

        <label for="edit-detail_equipment">Equipment/อุปกรณ์ที่มาพร้อมกับสินค้า</label>
        <textarea id="edit-detail_equipment" name="detail_equipment"><?= esc((string) ($row['detailEquipment'] ?? '')) ?></textarea>

        <label for="edit-create_by_user">Created by/พนักงานผู้รับสินค้า</label>
        <input id="edit-create_by_user" name="create_by_user" value="<?= esc((string) ($row['create_by_user'] ?? '')) ?>">

        <label for="edit-image">Repair image (up to 5, replaces current)</label>
        <input id="edit-image" name="detail_image[]" type="file" accept="image/png,image/jpeg,image/gif" multiple>
        <button type="submit">Submit</button>
        <button type="reset">Reset</button>
    </form>
    </div>
</section>
