<?php
/** @var string $kind */
/** @var string $batch_id */
/** @var int $accepted */
/** @var int $rejected */
/** @var list<array{row: int, accepted: bool, error: string|null}> $rows */
/** @var string $legacyConfirm */
?>
<section data-batch-id="<?= esc($batch_id) ?>">
    <p>Accepted: <?= $accepted ?></p><p>Rejected: <?= $rejected ?></p>
    <div class="table-wrap">
    <table><thead><tr><th>Row</th><th>Result</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><?= $row['row'] ?></td><td><?= $row['accepted'] ? 'accepted' : esc($row['error']) ?></td></tr><?php endforeach ?>
    </tbody></table>
    </div>
    <?php if ($accepted > 0): ?>
        <form method="post" action="<?= base_url($legacyConfirm) ?>" id="sendorderUpdate">
            <?= csrf_field() ?><input type="hidden" name="batch_id" value="<?= esc($batch_id) ?>"><button type="submit">Confirm</button>
        </form>
    <?php endif ?>
</section>
