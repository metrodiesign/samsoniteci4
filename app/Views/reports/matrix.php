<section><h1><?= esc($heading) ?></h1>
<?php if ($error): ?><div class="alert" role="alert"><?= esc($error) ?></div><?php endif ?>
<form class="card filters" method="post"><input type="hidden" name="csrf_test_name" value="<?= csrf_hash() ?>">
<div><label for="start_date">From date</label><input id="start_date" name="start_date" value="<?= esc($startDate) ?>" placeholder="dd/mm/yyyy"></div>
<div><label for="end_date">To date</label><input id="end_date" name="end_date" value="<?= esc($endDate) ?>" placeholder="dd/mm/yyyy"></div>
<button type="submit">Filter</button>
<?php $exportType = $kind === 'ratings' ? 'ratings' : ($kind === 'in-progress' ? 'in-progress' : null); ?>
<?php if ($exportType !== null): ?>
<?php $exportQuery = http_build_query(array_filter(['start_date' => $startDate, 'end_date' => $endDate], static fn (string $value): bool => $value !== '')); ?>
<a class="button" href="<?= esc(site_url('reports/' . $exportType . '/export') . ($exportQuery === '' ? '' : '?' . $exportQuery)) ?>">Export XLS</a>
<?php endif ?>
</form>
<div class="card table-wrap"><table><thead><tr><th>Item</th><th>Total</th><th>Detail</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<?php if ($kind === 'ratings'): ?>
<tr data-question="<?= (int) $row['question'] ?>" data-total="<?= (int) $row['total'] ?>"><td>Question <?= (int) $row['question'] ?></td><td><?= (int) $row['total'] ?></td><td><?php foreach ($row['scores'] as $score => $value): ?><?= (int) $score ?>: <?= esc($value['percentage']) ?>% <?php endforeach ?></td></tr>
<?php else: ?>
<tr><td><?= esc((string) ($row['label'] ?? $row['id'] ?? '')) ?></td><td><?= esc((string) ($row['total'] ?? '')) ?></td><td><?= esc((string) ($row['action_status'] ?? '')) ?></td></tr>
<?php endif ?>
<?php endforeach ?></tbody></table></div></section>
