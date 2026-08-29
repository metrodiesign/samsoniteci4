<?php

/** @var list<array<string, mixed>> $rows */
/** @var string $search */
/** @var string $caption */
?>
<div class="content-wrapper">
    <div class="background-form" style="background-image: url(<?= base_url('assets/images/bg-form.png') ?>);"></div>
    <div class="content-form">
        <section class="content-header">
            <div class="row">
                <div class="col-xs-6"><h1><i class="fa fa-link"></i> Menu Management <small>Add, Edit, Delete</small></h1></div>
                <div class="col-xs-6 text-right"><div class="form-group"><a class="btn btn-primary" href="<?= base_url('addNewMenu') ?>"><i class="fa fa-plus"></i> Add New</a></div></div>
            </div>
        </section>
        <section class="content"><div class="row"><div class="col-xs-12"><div class="box">
            <div class="box-header">
                <h3 class="box-title"><?= esc($caption) ?></h3>
                <div class="box-tools"><form action="<?= base_url('menuListing') ?>" method="post" id="searchList"><div class="input-group">
                    <input type="text" name="searchText" value="<?= esc($search) ?>" class="form-control input-sm pull-right" style="width: 150px;" placeholder="Search">
                    <div class="input-group-btn"><button class="btn btn-sm btn-default searchList"><i class="fa fa-search"></i></button></div>
                </div></form></div>
            </div>
            <div class="box-body table-responsive no-padding"><table class="table table-hover"><tr>
                <th>ฺId</th><th>Menu Group name</th><th class="text-center">Actions</th>
            </tr><?php foreach ($rows as $item): ?><tr>
                <td><?= (int) $item['id'] ?></td><td><?= esc((string) $item['name']) ?></td>
                <td class="text-center"><a class="btn btn-sm btn-info" href="<?= base_url('editMunuOld/' . (int) $item['id']) ?>" title="Edit"><i class="fa fa-pencil"></i></a></td>
            </tr><?php endforeach ?></table></div>
        </div></div></div></section>
    </div>
</div>
