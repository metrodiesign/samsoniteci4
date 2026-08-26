<?php

/** @var list<array<string, mixed>> $rows */
/** @var int $userId */
/** @var int $page */
/** @var string $search */
/** @var int $pages */
/** @var string $ownerName */
/** @var string $ownerEmail */

$link = static fn (int $target, string $search): string => '/users/' . $userId . '/history/' . $target
    . ($search === '' ? '' : '?searchText=' . rawurlencode($search));
?>
<section aria-labelledby="page-title">
    <div class="card">
        <h3 class="box-title"><?= esc($ownerName) ?> : <?= esc($ownerEmail) ?></h3>
        <form class="box-tools" method="get" action="/users/<?= $userId ?>/history/1">
            <label for="history-search">Detail : </label>
            <input id="history-search" name="searchText" value="<?= esc($search) ?>" maxlength="128" placeholder="Search">
            <button type="submit">Search</button>
        </form>
        <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Session Data</th><th>IP Address</th><th>User Agent</th>
                <th>Agent Full String</th><th>Platform</th><th>Date-Time</th>
            </tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= esc((string) ($row['sessionData'] ?? '')) ?></td>
                        <td><?= esc($row['machineIp']) ?></td>
                        <td><?= esc($row['userAgent']) ?></td>
                        <td><?= esc($row['agentString']) ?></td>
                        <td><?= esc($row['platform']) ?></td>
                        <td><?= esc($row['createdDtm']) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
        </div>
        <?php if ($pages > 1): ?>
            <?php
            // CI3's pager shows a three-link window, not every page: this user has hundreds of
            // sign-ins in the real database and listing them all buries the Next control.
            $first = max(1, min($page - 1, $pages - 2));
            $last = min($pages, $first + 2);
            ?>
            <nav class="pager" aria-label="Login history pages">
                <?php for ($number = $first; $number <= $last; $number++): ?>
                    <?php if ($number === $page): ?>
                        <strong><?= $number ?></strong>
                    <?php else: ?>
                        <a href="<?= esc($link($number, $search)) ?>"><?= $number ?></a>
                    <?php endif ?>
                <?php endfor ?>
                <?php if ($page < $pages): ?>
                    <a href="<?= esc($link($page + 1, $search)) ?>">Next</a>
                <?php endif ?>
            </nav>
        <?php endif ?>
    </div>
</section>
