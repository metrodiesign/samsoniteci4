<?php

/** @var list<array<string, mixed>> $rows */
/** @var int $userId */
/** @var int $page */
?>
<section aria-labelledby="history-title">
    <h1 id="history-title">Login history</h1>
    <table>
        <thead><tr><th>Date</th><th>IP</th><th>Browser</th><th>Agent</th><th>Platform</th></tr></thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= esc($row['createdDtm']) ?></td>
                    <td><?= esc($row['machineIp']) ?></td>
                    <td><?= esc($row['userAgent']) ?></td>
                    <td><?= esc($row['agentString']) ?></td>
                    <td><?= esc($row['platform']) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
    <?php if (count($rows) === 5): ?><a href="/users/<?= $userId ?>/history/<?= $page + 1 ?>">Next</a><?php endif ?>
</section>
