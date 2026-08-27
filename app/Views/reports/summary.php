<?php
$exportFilters = array_filter([
    ...$filters,
    'branch_id' => $branchId,
], static fn (mixed $value): bool => $value !== '' && $value !== null);
$exportUrl = site_url('reports/summary/export') . ($exportFilters === [] ? '' : '?' . http_build_query($exportFilters));
$nextUrl = site_url('reportsummary/' . ($page + 100)) . ($exportFilters === [] ? '' : '?' . http_build_query($exportFilters));
?>
<section><p><?= count($rows) ?> matching order(s).</p>
<?php if ($error): ?><div class="alert" role="alert"><?= esc($error) ?></div><?php endif ?>
<form class="card filters" method="post" action="<?= site_url('reportsummary') ?>">
<?= csrf_field() ?>
<div><label for="summary-search">Search</label><input id="summary-search" name="searchText" value="<?= esc($filters['searchText']) ?>" maxlength="128"></div>
<div><label for="summary-start">From date</label><input id="summary-start" name="sdate" value="<?= esc($filters['sdate']) ?>" placeholder="dd/mm/yyyy"></div>
<div><label for="summary-end">To date</label><input id="summary-end" name="edate" value="<?= esc($filters['edate']) ?>" placeholder="dd/mm/yyyy"></div>
<div><label for="summary-status">Status</label><select id="summary-status" name="status_id"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= (int) $status['status_id'] ?>" <?= $filters['status_id'] === (string) $status['status_id'] ? 'selected' : '' ?>><?= esc((string) $status['status_name']) ?></option><?php endforeach ?></select></div>
<div><label for="summary-brand">Brand</label><select id="summary-brand" name="detailBrandId"><option value="">All brands</option><?php foreach ($brands as $brand): ?><option value="<?= (int) $brand['brand_id'] ?>" <?= $filters['detailBrandId'] === (string) $brand['brand_id'] ? 'selected' : '' ?>><?= esc((string) $brand['brand_details']) ?></option><?php endforeach ?></select></div>
<div><label for="summary-type">Type</label><select id="summary-type" name="detailTypeId"><option value="">All types</option><?php foreach ($types as $type): ?><option value="<?= (int) $type['type_id'] ?>" <?= $filters['detailTypeId'] === (string) $type['type_id'] ? 'selected' : '' ?>><?= esc((string) $type['type_details']) ?></option><?php endforeach ?></select></div>
<?php if ($branches !== []): ?><div><label for="summary-branch">Branch</label><select id="summary-branch" name="branch_id"><option value="0">All branches</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['branch_id'] ?>" <?= $branchId === (int) $branch['branch_id'] ? 'selected' : '' ?>><?= esc((string) $branch['branch_name']) ?></option><?php endforeach ?></select></div><?php else: ?><input type="hidden" name="branch_id" value="<?= (int) $branchId ?>"><?php endif ?>
<button type="submit">Filter</button><a class="button" href="<?= esc($exportUrl) ?>">Export XLS</a>
</form>
<?php
// Column order and wording are CI3's reportsummary view verbatim, "Warannty" typo included.
// `$columns` maps each header to the row key (or keys, joined like CI3 does for the
// checkbox groups that carry a free-text "other" value alongside the selected ids).
$columns = [
    'Action Status' => ['status_name'],
    'Branch User' => ['branch_user_name'],
    'Branch Name' => ['branch_name'],
    'trackID' => ['trackID'],
    'orderID' => ['orderIDShow'],
    'Urgent' => ['detailAgent'],
    'Fullname' => ['customerFullname'],
    'Tel' => ['customerTel'],
    'Email' => ['customerEmail'],
    'RequestDate' => ['requestDate'],
    'BRAND ID / ยี่ห้อ' => ['brand_details'],
    'CATEGORY / ประเภท' => ['type_details'],
    'SKU NAME / ชื่อสินค้า' => ['detailSKUName'],
    'WARANTY / หมายเลขประกัน' => ['detailNumberWaranty'],
    'EQUIPMENT / อุปกรณ์ที่มาพร้อมกับสินค้า' => ['detailEquipment'],
    'NOTE / หมายเหตุ' => ['detailNote'],
    'Condition / อาการที่ส่งซ่อม' => ['detailCondition', 'detailConditionOther'],
    'Estimate Price / ประเมินราคาส่งซ่อม' => ['detailEstimatePrice', 'detailEstimatePriceOther'],
    'Fixed / สภาพ, ตำหนิ' => ['detailFixed', 'detailFixedOther'],
    'รับเข้า' => ['date_repair'],
    'อัพเดทล่าสุด' => ['date_update_status'],
    'ศูนย์ส่งคืนสาขา' => ['date_deliver'],
    'ลูกค้ามารับคืน' => ['date_complete'],
    'ราคาซ่อม' => ['RepairPrice'],
    'Warannty' => ['waranty_cmg'],
];
?>
<div class="card table-wrap"><table><thead><tr><th>No</th><?php foreach (array_keys($columns) as $header): ?><th><?= esc($header) ?></th><?php endforeach ?></tr></thead><tbody>
<?php foreach ($rows as $index => $row): ?><tr><td><?= $page + $index + 1 ?></td><?php foreach ($columns as $keys): ?><td><?= esc(implode(' ', array_filter(array_map(static fn (string $key): string => trim((string) ($row[$key] ?? '')), $keys), static fn (string $value): bool => $value !== ''))) ?></td><?php endforeach ?></tr><?php endforeach ?>
</tbody></table></div>
<?php if (count($rows) === 100): ?><a href="<?= esc($nextUrl) ?>">Next</a><?php endif ?>
</section>
