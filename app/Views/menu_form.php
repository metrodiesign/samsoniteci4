<?php

/** @var array<string, mixed>|null $row */
/** @var list<array{id: int, name: string}> $menuGroups */
$selected = array_filter(array_map('intval', explode(',', (string) ($row['group_type'] ?? ''))));
/** @var string $caption */
/** @var string $legacyAction */
?>
<div class="content-wrapper">
    <div class="background-form" style="background-image: url(<?= base_url('assets/images/bg-form.png') ?>);"></div>
    <div class="content-form">
        <section class="content-header">
            <h1><i class="fa fa-users"></i> Menu Management <small>Add / Edit Menu</small></h1>
        </section>
        <section class="content"><div class="row"><div class="col-md-12"><div class="box box-primary">
            <div class="box-header"><h3 class="box-title"><?= esc($caption) ?></h3></div>
            <form role="form" id="addMenu" method="post" action="<?= base_url($legacyAction) ?>">
        <?= csrf_field() ?>
        <div class="box-body"><div class="row"><div class="col-md-12"><div class="form-group">
        <label for="name">Menu Gruop Name</label>
        <input class="form-control required" id="name" name="name" value="<?= esc($row['name'] ?? '') ?>" maxlength="250" required>
        <?php if ($row !== null): ?><input type="hidden" name="group_id" id="group_id" value="<?= (int) $row['id'] ?>"><?php endif ?>
        </div></div></div><div class="row"><div class="col-md-12"><div class="form-group">
            <label for="group_type">Select Type menu</label><br>
            <?php foreach ($menuGroups as $group): ?>
                <label>
                    <input class="form-check-input" type="checkbox" id="group_type[]" name="group_type[]" value="<?= $group['id'] ?>" <?= in_array($group['id'], $selected, true) ? 'checked' : '' ?>>
                    <span class="label-text"><?= esc($group['name']) ?></span>
                </label>
            <?php endforeach ?>
        </div></div></div></div>
        <div class="box-footer"><input type="submit" class="btn btn-primary" value="Submit"><input type="reset" class="btn btn-default" value="Reset"></div>
            </form>
        </div></div></div></section>
    </div>
</div>
<script src="<?= base_url('assets/js/addMenu.js') ?>" type="text/javascript"></script>
