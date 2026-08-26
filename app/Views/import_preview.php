<?php
/** @var string $kind */
/** @var string $batch_id */
/** @var int $accepted */
/** @var int $rejected */
/** @var list<array{row: int, accepted: bool, error: string|null}> $rows */
?>
<section data-batch-id="<?= esc($batch_id) ?>">
    <p>Accepted: <?= $accepted ?></p><p>Rejected: <?= $rejected ?></p>
    <div class="table-wrap">
    <table><thead><tr><th>Row</th><th>Result</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><?= $row['row'] ?></td><td><?= $row['accepted'] ? 'accepted' : esc($row['error']) ?></td></tr><?php endforeach ?>
    </tbody></table>
    </div>
    <?php if ($accepted > 0): ?>
        <form method="post" action="/imports/<?= esc($kind) ?>/<?= esc($batch_id) ?>/confirm">
            <?= csrf_field() ?><button type="submit">Confirm</button>
        </form>
    <?php endif ?>
</section>
