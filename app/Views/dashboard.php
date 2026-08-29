<div class="content-dashbord">
    <section class="content">
        <div class="row">
            <?php foreach ($tiles as $tile): ?>
                <?php $report = $tile['label'] === 'REPORTS'; ?>
                <div class="col-sm-12 col-lg-<?= $report ? '4' : '5' ?> col-md-<?= $report ? '4' : '6' ?> ">
                    <a href="<?= esc($tile['href']) ?>" class="small-box-footer">
                        <div class="small-box <?= $report ? 'bg-blue' : 'bg-light-blue' ?>">
                            <div class="inner"><h2><?= esc($tile['label']) ?></h2></div>
                            <div class="icon"><i class="ion <?= esc($tile['icon'] ?? 'ion-bag', 'attr') ?>"></i></div>
                        </div>
                    </a>
                </div>
            <?php endforeach ?>
        </div>
    </section>
</div>
