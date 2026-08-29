<?php

/** @var list<array<string, mixed>> $contacts */
/** @var string $search */
?>
<div class="content-wrapper">
    <div class="background-form" style="background-image: url(<?= base_url('assets/images/bg-form.png') ?>);"></div>
    <div class="content-table-form"><section class="content-header"><div class="row"><div class="col-xs-12">
        <h1><i class="fa fa-link"></i> Contact Management</h1>
    </div></div></section><section class="content"><div class="row"><div class="col-xs-12"><div class="box box-scroll">
        <div class="box-header"><h3 class="box-title">Contact List</h3><div class="box-tools"><form action="<?= base_url('contactListing') ?>" method="post" id="searchList"><div class="input-group">
            <input type="text" name="searchText" value="<?= esc($search) ?>" class="form-control input-sm pull-right" style="width: 150px;" placeholder="Search">
            <div class="input-group-btn"><button class="btn btn-sm btn-default searchList"><i class="fa fa-search"></i></button></div>
        </div></form></div></div>
        <div class="box-body table-responsive no-padding"><table class="table table-hover"><tr>
            <th>Id</th><th>Name</th><th>Email</th><th>Samsoniteid</th><th>Phone</th><th>Detail</th><th>Date</th>
        </tr>
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
        </table></div>
    </div></div></div></section></div>
</div>
