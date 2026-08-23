<?php

/** @var list<array<string, mixed>> $rows */
/** @var int $status */
/** @var int $page */
/** @var string $search */
/** @var bool $canWrite */
/** @var list<array<string, mixed>> $providers */
?>
<section aria-labelledby="orders-title">
    <h1 id="orders-title">Orders — status <?= $status ?></h1>
    <form method="get" action="/orders">
        <input type="hidden" name="status" value="<?= $status ?>">
        <label for="order-search">Search</label>
        <input id="order-search" name="search" value="<?= esc($search) ?>" maxlength="128">
        <button type="submit">Search</button>
    </form>
    <table>
        <thead><tr><th>Tracking ID</th><th>Order</th><th>Customer</th><th>Phone</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><a href="/orders/<?= (int) $row['request_id'] ?>"><?= esc($row['trackID']) ?></a></td>
                    <td><?= esc($row['orderIDShow']) ?></td>
                    <td><?= esc($row['customerFullname']) ?></td>
                    <td><?= esc($row['customerTel']) ?></td>
                    <td><?= esc($row['requestDate']) ?></td>
                    <td>
                        <a href="/orders/<?= (int) $row['request_id'] ?>/print">Print</a>
                        <?php if ($canWrite): ?>
                            <a href="/orders/<?= (int) $row['request_id'] ?>">Edit</a>
                            <?php if ($status === 1): ?>
                                <form method="post" action="/sendorderUpdate">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="select_list_id[]" value="<?= (int) $row['request_id'] ?>">
                                    <label for="provider-<?= (int) $row['request_id'] ?>">Provider</label>
                                    <select id="provider-<?= (int) $row['request_id'] ?>" name="provider_id" required>
                                        <option value="">Select provider</option>
                                        <?php foreach ($providers as $provider): ?>
                                            <option value="<?= (int) $provider['provider_id'] ?>"><?= esc((string) $provider['provider_name']) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                    <button type="submit">Send to provider</button>
                                </form>
                            <?php elseif ($status === 2 || $status === 3): ?>
                                <form method="post" action="/sendorderUpdateStatus">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="select_list_id[]" value="<?= (int) $row['request_id'] ?>">
                                    <input type="hidden" name="status_id" value="<?= $status + 1 ?>">
                                    <button type="submit"><?= $status === 2 ? 'Start repair' : 'Complete repair' ?></button>
                                </form>
                            <?php elseif ($status === 4): ?>
                                <form method="post" action="/sendorder_deliver">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="select_list_id[]" value="<?= (int) $row['request_id'] ?>">
                                    <input type="hidden" name="status_id" value="5">
                                    <button type="submit">Deliver to customer</button>
                                </form>
                            <?php endif ?>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
    <?php if (count($rows) === 50): ?><a href="/orders?status=<?= $status ?>&amp;page=<?= $page + 1 ?>">Next</a><?php endif ?>
</section>
