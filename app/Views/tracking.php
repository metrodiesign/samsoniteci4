<?php

/** @var string $language */
/** @var list<array{status_id: int, status_name: string, status_name_th: string, occurred_at: string}> $timeline */
/** @var string $trackId */
/** @var string|null $backgroundImage */
/** @var string|null $backgroundImageMobile */
?>
<?php if ($backgroundImage !== null): ?>
    <style>#tracking-panel { background-image: url('/background-image/<?= esc($backgroundImage) ?>'); background-size: cover; }</style>
<?php endif ?>
<?php if ($backgroundImageMobile !== null): ?>
    <style>@media (max-width: 850px) { #tracking-panel { background-image: url('/background-image/<?= esc($backgroundImageMobile) ?>'); } }</style>
<?php endif ?>
<section id="tracking-panel" aria-labelledby="tracking-title">
    <h1 id="tracking-title"><?= $language === 'th' ? 'ติดตามงานซ่อม' : 'Repair tracking' ?></h1>
    <form method="get" action="<?= $language === 'th' ? '/tracking-th' : '/tracking' ?>">
        <label for="tracking-id"><?= $language === 'th' ? 'หมายเลขติดตาม' : 'Tracking ID' ?></label>
        <input id="tracking-id" name="tracking_id" value="<?= esc($trackId) ?>" maxlength="100" required>
        <button type="submit"><?= $language === 'th' ? 'ค้นหา' : 'Search' ?></button>
    </form>
    <?php if ($trackId !== ''): ?>
        <p data-tracking-id><?= esc($trackId) ?></p>
        <?php if ($timeline === []): ?>
            <p role="status"><?= $language === 'th' ? 'ไม่พบหมายเลขติดตาม' : 'Tracking ID not found' ?></p>
        <?php else: ?>
            <ol aria-label="<?= $language === 'th' ? 'สถานะงานซ่อม' : 'Repair timeline' ?>">
                <?php foreach ($timeline as $entry): ?>
                    <li data-status-id="<?= $entry['status_id'] ?>">
                        <strong><?= esc($language === 'th' ? $entry['status_name_th'] : $entry['status_name']) ?></strong>
                        <?php
                            // CI3 parity (business decision 2026-08-23): both languages
                            // render d/m/Y with the Buddhist-era year, as legacy did.
                            $occurredTimestamp = strtotime($entry['occurred_at']);
                            $occurredDisplay   = $occurredTimestamp === false
                                ? $entry['occurred_at']
                                : date('d/m/', $occurredTimestamp) . (date('Y', $occurredTimestamp) + 543);
                        ?>
                        <time datetime="<?= esc($entry['occurred_at']) ?>"><?= esc($occurredDisplay) ?></time>
                    </li>
                <?php endforeach ?>
            </ol>
        <?php endif ?>
    <?php endif ?>
</section>
