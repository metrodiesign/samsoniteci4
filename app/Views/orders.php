<?php

/** @var list<array<string, mixed>> $rows */
/** @var int $status */
/** @var int $page */
/** @var string $search */
/** @var array{title: string, bulk_endpoint: ?string, statuses: list<int>, row_action: ?string}|null $profile */
/** @var array<string, string> $statusUpdates */
/** @var bool $canWrite */
/** @var list<array<string, mixed>> $providers */

$hasBulk = $canWrite && $profile !== null && $profile['bulk_endpoint'] !== null;
?>
<section aria-labelledby="orders-title">
    <h1 id="orders-title"><?= $profile !== null ? esc($profile['title']) : 'Orders — status ' . $status ?></h1>
    <form method="get" action="/orders">
        <input type="hidden" name="status" value="<?= $status ?>">
        <label for="order-search">Search</label>
        <input id="order-search" name="search" value="<?= esc($search) ?>" maxlength="128">
        <button type="submit">Search</button>
    </form>
    <?php if ($hasBulk): ?>
    <form method="post" action="<?= esc($profile['bulk_endpoint']) ?>">
        <?= csrf_field() ?>
    <?php endif ?>
    <table>
        <thead><tr>
            <?php if ($hasBulk): ?><th><input type="checkbox" id="selectall_tracking"></th><?php endif ?>
            <th>Id</th><th>Tracking ID</th><th>Order</th><th>Fullname</th><th>Tel</th><th>Email</th><th>RequestDate</th><?php if ($status === 7): ?><th>Completed Date</th><?php endif ?><th>Action Status</th><th>Status Update</th><th>Actions</th>
        </tr></thead>
        <tbody>
            <?php foreach ($rows as $index => $row): ?>
                <?php $requestTs = strtotime((string) $row['requestDate']); ?>
                <tr>
                    <?php if ($hasBulk): ?><td><input type="checkbox" name="select_list_id[]" value="<?= (int) $row['request_id'] ?>"></td><?php endif ?>
                    <td><?= $index + 1 ?></td>
                    <td><a href="/orders/<?= (int) $row['request_id'] ?>"><?= esc($row['trackID']) ?></a></td>
                    <td><?= esc($row['orderID']) ?></td>
                    <td><?= esc($row['customerFullname']) ?></td>
                    <td><?= esc($row['customerTel']) ?></td>
                    <td><?= esc($row['customerEmail']) ?></td>
                    <td><?= esc($requestTs !== false ? date('d/m/Y', $requestTs) : '') ?></td>
                    <?php if ($status === 7): ?>
                        <?php $completeTs = strtotime((string) ($row['date_complete'] ?? '')); ?>
                        <td><?= esc($completeTs !== false ? date('d/m/Y', $completeTs) : '') ?></td>
                    <?php endif ?>
                    <td><?= esc($row['status_name']) ?></td>
                    <td><?= esc($statusUpdates[$row['orderID'] . "\x00" . $row['customerTel']] ?? '') ?></td>
                    <td>
                        <a href="/orders/<?= (int) $row['request_id'] ?>/print">Print</a>
                        <?php if (($profile['row_action'] ?? null) === 'rate'): ?>
                            <button type="button" class="rate-open" data-request-id="<?= (int) $row['request_id'] ?>" data-track-id="<?= esc($row['trackID'], 'attr') ?>">ประเมิน</button>
                        <?php endif ?>
                        <?php if ($canWrite): ?>
                            <a href="/orders/<?= (int) $row['request_id'] ?>">Edit</a>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
    <?php if ($hasBulk): ?>
        <?php if ($status === 1): ?>
        <label for="bulk-provider">Provider</label>
        <select id="bulk-provider" name="provider_id" required>
            <option value="">Select provider</option>
            <?php foreach ($providers as $provider): ?>
                <option value="<?= (int) $provider['provider_id'] ?>"><?= esc((string) $provider['provider_name']) ?></option>
            <?php endforeach ?>
        </select>
        <?php else: ?>
        <label for="bulk-status">Status</label>
        <select id="bulk-status" name="status_id">
            <?php foreach ($profile['statuses'] as $nextStatus): ?>
                <option value="<?= (int) $nextStatus ?>"><?= (int) $nextStatus ?></option>
            <?php endforeach ?>
        </select>
        <?php endif ?>
        <button type="submit">Send</button>
    </form>
    <script>
        document.getElementById('selectall_tracking').addEventListener('change', function () {
            var checked = this.checked;
            document.querySelectorAll('input[name="select_list_id[]"]').forEach(function (box) { box.checked = checked; });
        });
    </script>
    <?php endif ?>
    <?php if (count($rows) === 50): ?><a href="/orders?status=<?= $status ?>&amp;page=<?= $page + 1 ?>">Next</a><?php endif ?>
    <?php if (($profile['row_action'] ?? null) === 'rate'): ?><?= view('orders_rating_modal') ?><?php endif ?>
</section>
