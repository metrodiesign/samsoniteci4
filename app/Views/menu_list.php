<?php

/** @var list<array<string, mixed>> $rows */
/** @var string $search */
?>
<section aria-labelledby="page-title">
    <form method="get" action="/menu">
        <label for="menu-search">Search</label>
        <input id="menu-search" name="search" value="<?= esc($search) ?>" maxlength="128">
        <button type="submit">Search</button>
    </form>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ฺId</th>
                <th>Menu Group name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= (int) $item['id'] ?></td>
                    <td><?= esc((string) $item['name']) ?></td>
                    <td><a href="/menu/<?= (int) $item['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
    </div>
</section>
