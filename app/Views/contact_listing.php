<?php

/** @var list<array<string, mixed>> $contacts */
/** @var string $search */
?>
<section aria-labelledby="contact-list-title">
    <h1 id="contact-list-title">Contact messages</h1>
    <form method="get" action="/contact-list">
        <label for="contact-search">Search</label>
        <input id="contact-search" name="search" value="<?= esc($search) ?>" maxlength="128">
        <button type="submit">Search</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Tracking ID</th><th>Phone</th><th>Message</th><th>Created</th></tr></thead>
            <tbody>
            <?php foreach ($contacts as $contact): ?>
                <tr>
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
</section>
