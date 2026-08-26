<section>
<?php if ($error): ?><div class="alert" role="alert"><?= esc($error) ?></div><?php endif ?>
<form class="card filters" method="post"><input type="hidden" name="csrf_test_name" value="<?= csrf_hash() ?>">
<div><label for="branch_id">Branch:</label><?php if (! $showBranchSelect): ?><input type="hidden" id="branch_id" name="branch_id" value="<?= $branchId ?>"><?php else: ?><select id="branch_id" name="branch_id"><option value="0">ALL</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['branch_id'] ?>"<?= $branchId === (int) $branch['branch_id'] ? ' selected' : '' ?>><?= esc((string) $branch['branch_name'] . ',' . (string) $branch['branch_user_name']) ?></option><?php endforeach ?></select><?php endif ?></div>
<div><label for="start_date">From date</label><input id="start_date" name="start_date" value="<?= esc($startDate) ?>" placeholder="dd/mm/yyyy"></div>
<div><label for="end_date">To date</label><input id="end_date" name="end_date" value="<?= esc($endDate) ?>" placeholder="dd/mm/yyyy"></div>
<?php if ($kind === 'in-progress'): ?>
<?php $selectedStatus = $statusId === '' ? [] : explode(',', $statusId); ?>
<div><label for="status_id">Status</label><select id="status_id" name="status_id[]" multiple><?php foreach ($statuses as $status): ?><option value="<?= (int) $status['status_id'] ?>"<?= in_array((string) $status['status_id'], $selectedStatus, true) ? ' selected' : '' ?>><?= esc((string) $status['status_name_th']) ?></option><?php endforeach ?></select></div>
<?php endif ?>
<button type="submit">Filter</button>
<?php $exportType = $kind === 'ratings' ? 'ratings' : ($kind === 'in-progress' ? 'in-progress' : null); ?>
<?php if ($exportType !== null): ?>
<?php $exportQuery = http_build_query(array_filter(['start_date' => $startDate, 'end_date' => $endDate, 'status_id' => $kind === 'in-progress' ? $statusId : '', 'branch_id' => $branchId === null ? '' : (string) $branchId], static fn (string $value): bool => $value !== '')); ?>
<a class="button" href="<?= esc(site_url('reports/' . $exportType . '/export') . ($exportQuery === '' ? '' : '?' . $exportQuery)) ?>">Export XLS</a>
<?php endif ?>
</form>
<div class="card table-wrap">
<?php if ($caption !== ''): ?><h3 class="box-title"><?= esc($caption) ?></h3><?php endif ?>
<?php if ($sectionTitle !== ''): ?><h2><?= esc($sectionTitle) ?></h2><?php endif ?>
<?php if ($kind === 'ratings'): ?>
<?php
// Question wording and numbering copied from CI3 `application/views/report.php`: add_id 1-4 are
// the four satisfaction questions, add_id 5-8 are the four sub-items of question 5, and
// question 6 is the free-text section rendered as the No / Note table below.
$questions = [
    1 => '1. ความพึงพอใจในการให้บริการของเจ้าหน้าที่ ณ จุดรับซ่อม',
    2 => '2. ความพึงพอใจในการให้บริการของศูนย์บริการ',
    3 => '3. ความพึงพอใจในคุณภาพงานซ่อม',
    4 => '4. ระยะเวลาที่ใช้ในการซ่อม',
    5 => '5.1 ระยะเวลาซ่อม',
    6 => '5.2 ค่าบริการซ่อม',
    7 => '5.3 คุณภาพงานซ่อม',
    8 => '5.4 ความพึงพอใจในการบริการ',
];
?>
<?php foreach ($rows as $row): ?>
<?php $question = (int) $row['question']; ?>
<?php if ($question === 5): ?><h4>5. ลำดับความสำคัญที่ลูกค้าพิจารณา</h4><?php endif ?>
<div class="rating-group" data-question="<?= $question ?>" data-total="<?= (int) $row['total'] ?>">
    <h4><?= esc($questions[$question] ?? ('Question ' . $question)) ?></h4>
    <h5>Total <?= number_format((int) $row['total']) ?></h5>
    <ul class="rating-scores">
        <?php foreach ($row['scores'] as $score => $value): ?>
            <li data-score="<?= (int) $score ?>"><?= (int) $score ?>: <?= esc($value['percentage']) ?>%</li>
        <?php endforeach ?>
    </ul>
</div>
<?php endforeach ?>
<h4>6. ข้อเสนอแนะเพิ่มเติม</h4>
<table><thead><tr><th>No</th><th>Note</th></tr></thead><tbody>
<?php foreach ($ratingComments as $index => $comment): ?>
<tr><td><?= $index + 1 ?></td><td><?= esc((string) $comment['comment']) ?></td></tr>
<?php endforeach ?>
</tbody></table>
<?php else: ?>
<?php $columns = $rows === [] ? [] : array_keys($rows[0]); ?>
<table data-kind="<?= esc($kind) ?>"><thead><tr><?php foreach ($columns as $column): ?><th><?= esc((string) $column) ?></th><?php endforeach ?></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $column => $value): ?><td data-col="<?= esc((string) $column) ?>"><?= esc((string) $value) ?></td><?php endforeach ?></tr><?php endforeach ?></tbody></table>
<?php endif ?>
</div></section>
