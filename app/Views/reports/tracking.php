<?php
/**
 * @var bool $showCmg
 */
$formatDate = static function (mixed $value): string {
    if (! is_string($value) || $value === '') {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '' : date('d/m/Y', $timestamp);
};
$formPath = $branchId === null
    ? 'Order/ReportTrackingListing'
    : 'Order/ReportTrackingListing/0/' . $branchId;
$exportQuery = http_build_query(array_filter([
    'status_id' => implode(',', $selectedStatusIds),
    'sdate' => $startDate,
    'edate' => $endDate,
    'searchText' => $searchText,
    'branch_id' => $branchId,
], static fn (mixed $value): bool => $value !== '' && $value !== null));
$exportUrl = site_url('reports/tracking/export') . ($exportQuery === '' ? '' : '?' . $exportQuery);
?>
<section aria-labelledby="tracking-title">
    <h1 id="tracking-title">Report Tracking</h1>
    <p class="muted"><?= count($rows) ?> matching order(s). Status accepts comma-separated IDs.</p>

    <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert" role="alert"><?= esc($error) ?></div>
    <?php endif ?>

    <form class="card filters" id="searchList" action="<?= site_url($formPath) ?>" method="post">
        <?= csrf_field() ?>
        <div>
            <label for="status_id">Status IDs</label>
            <input id="status_id" name="status_id" value="<?= esc(implode(',', $selectedStatusIds)) ?>" placeholder="2,3" list="status-options">
            <datalist id="status-options">
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= esc((string) $status['status_id']) ?>"><?= esc($status['status_name']) ?></option>
                <?php endforeach ?>
            </datalist>
        </div>
        <div>
            <label for="sdate">From date</label>
            <input id="sdate" name="sdate" value="<?= esc($startDate) ?>" placeholder="dd/mm/yyyy" inputmode="numeric">
        </div>
        <div>
            <label for="edate">To date</label>
            <input id="edate" name="edate" value="<?= esc($endDate) ?>" placeholder="dd/mm/yyyy" inputmode="numeric">
        </div>
        <div>
            <label for="searchText">Search</label>
            <input id="searchText" name="searchText" value="<?= esc($searchText) ?>" maxlength="128" placeholder="Tracking, order, customer">
        </div>
        <button type="submit">Filter</button>
        <a class="button" href="<?= esc($exportUrl) ?>">Export XLS</a>
    </form>

    <div class="card table-wrap">
        <table id="examples">
            <thead>
            <tr>
                <th>No</th>
                <th>Action Status</th>
                <th>Status Update</th>
                <th>TotalDay</th>
                <?php if ($showCmg): ?><th>CMG TotalDay</th><?php endif ?>
                <th>Branch User</th>
                <th>Branch Name</th>
                <th>trackID</th>
                <th>orderID</th>
                <th>CMG No.</th>
                <th>Urgent</th>
                <th>Fullname</th>
                <th>Tel</th>
                <th>Email</th>
                <th>RequestDate</th>
                <th>RepairDate</th>
                <th>StatusDate</th>
                <th>DeliverDate</th>
                <th>CompleteDate</th>
                <th>Provider</th>
                <th>Repair Price</th>
                <th>Warranty</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $index => $row): ?>
                <?php $rowNumber = $page + $index + 1 ?>
                <tr data-row-number="<?= $rowNumber ?>" data-track-id="<?= esc((string) $row['trackID']) ?>">
                    <td><?= $rowNumber ?></td>
                    <td><?= esc((string) ($row['status_name'] ?? '')) ?></td>
                    <td></td>
                    <td><?= esc($row['TotalDay'] === null ? '' : (string) $row['TotalDay']) ?></td>
                    <?php if ($showCmg): ?><td><?= esc($row['CMGTotalDay'] === null ? '' : (string) $row['CMGTotalDay']) ?></td><?php endif ?>
                    <td><?= esc((string) ($row['branch_user_name'] ?? '')) ?></td>
                    <td><?= esc((string) ($row['branch_name'] ?? '')) ?></td>
                    <td><?= esc((string) $row['trackID']) ?></td>
                    <td><?= esc((string) ($row['orderIDShow'] ?? '')) ?></td>
                    <td><?= esc((string) ($row['number_cmg'] ?? '')) ?></td>
                    <td><?= (int) ($row['detailAgent'] ?? 0) === 1 ? 'Urgent' : '' ?></td>
                    <td><?= esc((string) ($row['customerFullname'] ?? '')) ?></td>
                    <td><?= esc((string) ($row['customerTel'] ?? '')) ?></td>
                    <td><?= esc((string) ($row['customerEmail'] ?? '')) ?></td>
                    <td><?= esc($formatDate($row['requestDate'] ?? null)) ?></td>
                    <td><?= esc($formatDate($row['date_repair'] ?? null)) ?></td>
                    <td><?= esc($formatDate($row['date_update_status'] ?? null)) ?></td>
                    <td><?= esc($formatDate($row['date_deliver'] ?? null)) ?></td>
                    <td><?= esc($formatDate($row['date_complete'] ?? null)) ?></td>
                    <td><?= esc((string) ($row['logistics_etc_detail'] ?? '')) ?></td>
                    <td><?= $row['RepairPrice'] === null ? '' : esc(number_format((float) $row['RepairPrice'], 0)) ?></td>
                    <td><?= esc((string) ($row['waranty_cmg'] ?? '')) ?></td>
                    <td>—</td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</section>
