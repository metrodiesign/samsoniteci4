<?php

/** @var string $language */
/** @var string $trackId */
/** @var list<array{status_id: int, status_name: string, status_name_th: string, occurred_at: string}> $timeline */
/** @var string|null $backgroundImage */
/** @var string|null $backgroundImageMobile */
?>
<section id="rs-track">
    <?php if ($backgroundImage !== null): ?>
        <div class="banner-control">
            <picture>
                <?php if ($backgroundImageMobile !== null): ?>
                    <source media="(max-width: 850px)" srcset="/background-image/<?= esc($backgroundImageMobile) ?>">
                <?php endif ?>
                <img class="rs-bg-size" src="/background-image/<?= esc($backgroundImage) ?>" alt="">
            </picture>
        </div>
    <?php endif ?>

    <div class="container">
        <div class="row">
            <p data-tracking-id><?= esc($trackId) ?></p>
            <div class="con-pro-bar">
                <?php if ($timeline !== []): ?>
                    <?php $last = count($timeline) - 1; ?>
                    <?php foreach ($timeline as $i => $entry): ?>
                        <?php
                            // Bug fix (design §3.1): both EN and TH compare the EN column
                            // status_name against 'complete'. CI3 TH compared status_name_th,
                            // a Thai value that never matched, so the green circle never showed.
                            if (strcasecmp($entry['status_name'], 'complete') === 0) {
                                $circle = 'circle-awe bg-success circle-awe-animate';
                            } elseif ($i === 0) {
                                $circle = 'circle-awe circle-awe-animate';
                            } else {
                                $circle = 'circle-awe bg-pass';
                            }
                            $label = $language === 'th' ? $entry['status_name_th'] : $entry['status_name'];
                            // CI3 parity: d/m/Y with the Buddhist-era year for both languages.
                            $ts   = strtotime($entry['occurred_at']);
                            $date = $ts === false
                                ? $entry['occurred_at']
                                : date('d/m/', $ts) . (date('Y', $ts) + 543);
                        ?>
                        <div class="con-step-pass">
                            <div class="contain-process">
                                <div class="<?= $circle ?>"></div>
                                <?php if ($i !== $last): ?>
                                    <div class="line-normal line-progress"></div>
                                <?php endif ?>
                            </div>
                            <div class="txt-normal"><?= esc($label . ' ' . $date) ?></div>
                        </div>
                    <?php endforeach ?>
                <?php else: ?>
                    <div class="con-step-pass">
                        <div class="circle-awe bg-unpass">
                            <div class="line-normal"></div>
                        </div>
                        <div class="txt-normal">ไม่มีสินค้า</div>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</section>
