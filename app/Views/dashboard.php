<nav aria-label="Dashboard shortcuts">
    <ul class="dashboard-actions">
        <?php foreach ($tiles as $tile): ?>
            <li><a class="dashboard-action" href="<?= esc($tile['href']) ?>"><?= esc($tile['label']) ?></a></li>
        <?php endforeach ?>
    </ul>
</nav>
