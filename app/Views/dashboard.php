<section aria-labelledby="dashboard-title" data-background="<?= esc($background) ?>">
    <h1 id="dashboard-title">Dashboard</h1>
    <p>Welcome, <?= esc($name !== '' ? $name : 'user') ?>.</p>
    <?php if ($branchName !== ''): ?><p><?= esc($branchName) ?></p><?php endif ?>

    <div class="metrics">
        <?php for ($status = 1; $status <= 8; $status++): ?>
            <article class="metric" data-status="<?= $status ?>" data-count="<?= (int) ($counts[$status] ?? 0) ?>">
                <span>Status <?= $status ?></span>
                <strong><?= esc((string) ($counts[$status] ?? 0)) ?></strong>
            </article>
        <?php endfor ?>
    </div>

    <a class="button" href="<?= site_url('Order/ReportTrackingListing') ?>">Open Report Tracking</a>
</section>
