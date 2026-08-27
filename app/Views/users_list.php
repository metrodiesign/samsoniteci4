<?php

/** @var list<array<string, mixed>> $rows */
/** @var string $search */
/** @var string $caption */
?>
<section aria-labelledby="page-title">
    <div class="card">
        <h3 class="box-title"><?= esc($caption) ?></h3>
    <form method="get" action="/users">
        <label for="user-search">Search</label>
        <input id="user-search" name="search" value="<?= esc($search) ?>" maxlength="128">
        <button type="submit">Search</button>
    </form>
    <p id="user-delete-error" role="status" aria-live="polite"></p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= esc((string) ($item['name'] ?? '')) ?></td>
                        <td><?= esc((string) ($item['email'] ?? '')) ?></td>
                        <td><?= esc((string) ($item['mobile'] ?? '')) ?></td>
                        <td><?= esc((string) ($item['role'] ?? '')) ?></td>
                        <td>
                            <a href="/users/<?= (int) $item['userId'] ?>">Edit</a>
                            <form class="user-delete" method="post" action="/users/<?= (int) $item['userId'] ?>/delete">
                                <?= csrf_field() ?>
                                <button type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    </div>
</section>
<script>
    (function () {
        var error = document.getElementById('user-delete-error');
        document.querySelectorAll('.user-delete').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var button = form.querySelector('button[type="submit"]');
                if (button.disabled) {
                    return;
                }
                if (!confirm('Are you sure to delete this  ? ')) {
                    return;
                }
                button.disabled = true;
                error.textContent = '';
                fetch(form.action, { method: 'POST', body: new FormData(form) })
                    .then(function (response) {
                        var token = response.headers.get('X-CSRF-TOKEN');
                        document.querySelectorAll('.user-delete input[name="csrf_test_name"]').forEach(function (input) {
                            input.value = token;
                        });
                        if (response.status === 204) {
                            form.closest('tr').remove();
                            return;
                        }
                        error.textContent = 'Unable to delete user.';
                        button.disabled = false;
                    })
                    .catch(function () {
                        error.textContent = 'Unable to delete user.';
                        button.disabled = false;
                    });
            });
        });
    })();
</script>
