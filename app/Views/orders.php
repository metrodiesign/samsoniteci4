<?php

/** @var list<array<string, mixed>> $rows */
/** @var int $status */
/** @var int $page */
/** @var string $search */
/** @var string $sdate */
/** @var string $edate */
/** @var array{title: string, subtitle: string, list_title: string, add_new: bool, to_date: bool, headers: list<string>, bulk_endpoint: ?string, statuses: list<int>, row_action: ?string}|null $profile */
/** @var array<string, string> $statusUpdates */
/** @var bool $canWrite */
/** @var list<array<string, mixed>> $providers */

$hasBulk = $canWrite && $profile !== null && $profile['bulk_endpoint'] !== null;
$headers = $profile['headers'] ?? ['Id', 'TrackID', 'OrderID', 'Fullname', 'Tel', 'Email', 'RequestDate', 'Action status'];
// CI3 shows row actions only on queue 1 (edit/delete/print icons) and queue 5 (ประเมิน);
// queues 2-4 use the Actions column for the bulk checkboxes and queue 7 has no Actions
// column at all. The /orders/{id} and /orders/{id}/print routes stay reachable by URL.
$rowActions = $status === 1 || ($profile['row_action'] ?? null) === 'rate';
$selectAllInHeader = in_array($status, [2, 3, 4], true);
$hasActions = $rowActions || $hasBulk;
$nextQuery = array_filter([
    'status' => $status, 'page' => $page + 1, 'sdate' => $sdate, 'edate' => $edate, 'search' => $search,
], static fn (int|string $value): bool => $value !== '');

/** Maps a CI3 header label to the row value it renders; casing varies per queue. */
$cell = static function (string $header, array $row, int $index) use ($statusUpdates): string {
    $date = static function (mixed $value): string {
        $ts = strtotime((string) $value);

        return $ts !== false ? date('d/m/Y', $ts) : '';
    };

    return match (strtolower(str_replace(' ', '', $header))) {
        'id'           => (string) ($index + 1),
        'trackid'      => (string) $row['trackID'],
        'orderid'      => (string) $row['orderID'],
        'fullname'     => (string) $row['customerFullname'],
        'tel'          => (string) $row['customerTel'],
        'email'        => (string) $row['customerEmail'],
        'requestdate'  => $date($row['requestDate']),
        'completeddate' => $date($row['date_complete'] ?? ''),
        'actionstatus' => (string) $row['status_name'],
        'statusupdate' => $statusUpdates[$row['orderID'] . "\x00" . $row['customerTel']] ?? '',
        default        => '',
    };
};
?>
<section aria-labelledby="page-title">
    <div class="card">
        <?php if (($profile['list_title'] ?? '') !== ''): ?>
            <h3 class="box-title"><?= esc($profile['list_title']) ?></h3>
        <?php endif ?>
        <form class="box-tools" method="get" action="/orders">
            <input type="hidden" name="status" value="<?= $status ?>">
            <label for="order-date">from Date :</label>
            <input id="order-date" name="sdate" value="<?= esc($sdate) ?>" placeholder="Date">
            <?php if ($profile['to_date'] ?? false): ?>
                <label for="order-date-to">To Date : </label>
                <input id="order-date-to" name="edate" value="<?= esc($edate) ?>" placeholder="Date">
            <?php endif ?>
            <label for="order-search">Detail : </label>
            <input id="order-search" name="search" value="<?= esc($search) ?>" maxlength="128" placeholder="Search">
            <button type="submit">Search</button>
        </form>
        <?php if ($hasBulk): ?>
        <form method="post" action="<?= esc($profile['bulk_endpoint']) ?>">
            <?= csrf_field() ?>
        <?php endif ?>
        <div class="table-wrap">
        <table>
            <thead><tr>
                <?php foreach ($headers as $header): ?><th><?= esc($header) ?></th><?php endforeach ?>
                <?php if ($hasActions): ?>
                    <th>Actions
                        <?php // CI3 carries the select-all control in the Actions header on queues
                              // 2-4 only. Queues 1 and 5 keep the bulk form (a CI4 addition), so the
                              // control moves down beside the Send button instead of disappearing. ?>
                        <?php if ($hasBulk && $selectAllInHeader): ?>
                            <label for="selectall_tracking">Select ALL tracking</label>
                            <input type="checkbox" id="selectall_tracking">
                        <?php endif ?>
                    </th>
                <?php endif ?>
            </tr></thead>
            <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <tr>
                        <?php foreach ($headers as $header): ?>
                            <td><?= esc($cell($header, $row, $index)) ?></td>
                        <?php endforeach ?>
                        <?php if ($hasActions): ?>
                            <td>
                                <?php if ($status === 1 && $canWrite): ?>
                                    <a class="icon-action" href="/orders/<?= (int) $row['request_id'] ?>" title="Edit" aria-label="Edit">
                                        <svg width="1em" height="1em" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M410 45 467 102 158 411 74 438l27-84L410 45z"/></svg>
                                    </a>
                                    <button type="button" class="icon-action order-delete" data-request-id="<?= (int) $row['request_id'] ?>" title="Delete" aria-label="Delete">
                                        <svg width="1em" height="1em" viewBox="0 0 448 512" fill="currentColor" aria-hidden="true"><path d="M135 20 121 48H32v48h384V48h-89l-14-28H135zM64 144v304a48 48 0 0 0 48 48h224a48 48 0 0 0 48-48V144H64z"/></svg>
                                    </button>
                                <?php endif ?>
                                <?php if ($status === 1): ?>
                                    <a class="icon-action" href="/orders/<?= (int) $row['request_id'] ?>/print" title="Print" aria-label="Print">
                                        <svg width="1em" height="1em" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M128 32h256v96H128V32zM96 160h320a64 64 0 0 1 64 64v128h-96v128H128V352H32V224a64 64 0 0 1 64-64zm80 224h160v96H176v-96z"/></svg>
                                    </a>
                                <?php endif ?>
                                <?php if (($profile['row_action'] ?? null) === 'rate'): ?>
                                    <button type="button" class="rate-open" data-request-id="<?= (int) $row['request_id'] ?>" data-track-id="<?= esc($row['trackID'], 'attr') ?>">ประเมิน</button>
                                <?php endif ?>
                                <?php if ($hasBulk): ?>
                                    <input type="checkbox" name="select_list_id[]" value="<?= (int) $row['request_id'] ?>">
                                <?php endif ?>
                            </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        </div>
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
            <?php if (! $selectAllInHeader): ?>
                <label for="selectall_tracking">Select ALL tracking</label>
                <input type="checkbox" id="selectall_tracking">
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
        <?php if (count($rows) === 50): ?><a href="/orders?<?= esc(http_build_query($nextQuery)) ?>">Next</a><?php endif ?>
        <?php if ($status === 1 && $canWrite): ?>
            <p class="alert" id="order-delete-error" role="alert"></p>
            <span id="order-delete-csrf" hidden><?= csrf_field() ?></span>
            <script>
                (function () {
                    var box = document.getElementById('order-delete-csrf');
                    var error = document.getElementById('order-delete-error');
                    document.querySelectorAll('.order-delete').forEach(function (button) {
                        button.addEventListener('click', function () {
                            if (button.disabled || !confirm('Are you sure to Delete this  ? ')) {
                                return;
                            }
                            button.disabled = true;
                            error.textContent = '';
                            var input = box.querySelector('input');
                            var data = new FormData();
                            data.append(input.name, input.value);
                            fetch('/orders/' + button.dataset.requestId + '/delete', { method: 'POST', body: data })
                                .then(function (response) {
                                    // Refresh before branching: a failed delete has to leave a
                                    // usable token behind or every later attempt 403s.
                                    var token = response.headers.get('X-CSRF-TOKEN');
                                    if (token) {
                                        input.value = token;
                                    }
                                    if (response.status === 204) {
                                        button.closest('tr').remove();

                                        return;
                                    }
                                    error.textContent = 'Unable to delete order.';
                                    button.disabled = false;
                                })
                                .catch(function () {
                                    error.textContent = 'Unable to delete order.';
                                    button.disabled = false;
                                });
                        });
                    });
                })();
            </script>
        <?php endif ?>
    </div>
    <?php if (($profile['row_action'] ?? null) === 'rate'): ?><?= view('orders_rating_modal') ?><?php endif ?>
</section>
