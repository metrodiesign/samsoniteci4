<?php

/** @var list<array<string, mixed>> $contacts */
/** @var string $search */
?>
<section aria-labelledby="page-title">
    <div class="card">
        <h3 class="box-title">Contact List</h3>
        <form class="box-tools" method="get" action="/contact-list">
            <label for="contact-search">Detail : </label>
            <input id="contact-search" name="searchText" value="<?= esc($search) ?>" maxlength="128" placeholder="Search">
            <button type="submit">Search</button>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr>
                    <th>Id</th><th>Name</th><th>Email</th><th>Samsoniteid</th>
                    <th>Phone</th><th>Detail</th><th>Date</th>
                </tr></thead>
                <tbody>
                <?php foreach ($contacts as $contact): ?>
                    <tr>
                        <td><?= (int) $contact['id'] ?></td>
                        <td><?= esc($contact['fullname']) ?></td>
                        <td><?= esc($contact['email']) ?></td>
                        <td><?= esc($contact['samsoniteid']) ?></td>
                        <td><?= esc($contact['phone']) ?></td>
                        <td><?= esc($contact['detail']) ?></td>
                        <td><?= esc($contact['cdate']) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
