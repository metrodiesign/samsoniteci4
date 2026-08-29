<?php

/** @var array{pk: string, label: string, pkLabel?: string, listFields?: list<string>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, fk?: string, formText?: string, listText?: string}>} $definition */
/** @var list<array<string, mixed>> $rows */
/** @var string $search */
/** @var string $type */
/** @var int $page */
/** @var string $caption */
$listFields = $definition['listFields'] ?? array_keys($definition['fields']);
$legacy = [
    'branch' => ['listing' => 'branchListing', 'new' => 'BranchNew', 'edit' => 'editBranchOld', 'delete' => 'deleteBranch', 'class' => 'deleteBranch', 'field' => 'branchid'],
    'branchtype' => ['listing' => 'branchtypeListing', 'new' => 'add_new_branchtype', 'edit' => 'editBranchtypeOld', 'delete' => 'deleteBranchtype', 'class' => 'deleteBranchtype', 'field' => 'branchid'],
    'statustype' => ['listing' => 'statustypeListing', 'new' => 'add_new_statustype', 'edit' => 'editStatustypeOld', 'delete' => 'deleteStatustype', 'class' => 'deleteStatustype', 'field' => 'statusid'],
    'producttype' => ['listing' => 'producttypeListing', 'new' => 'add_new_producttype', 'edit' => 'editProducttypeOld', 'delete' => 'deleteProducttype', 'class' => 'deleteProducttype', 'field' => 'productstypeid'],
    'book' => ['listing' => 'bookListing', 'new' => 'BookNew', 'edit' => 'editBookOld', 'delete' => 'deleteBook', 'class' => 'deleteBook', 'field' => 'bookid'],
    'brand' => ['listing' => 'brandListing', 'new' => 'add_new_brand', 'edit' => 'editBrandOld', 'delete' => 'deleteBrand', 'class' => 'deleteBrand', 'field' => 'brandid'],
    'condition' => ['listing' => 'conditionListing', 'new' => 'add_new_condition', 'edit' => 'editConditionOld', 'delete' => 'deleteCondition', 'class' => 'deleteCondition', 'field' => 'condition_id'],
    'estimateprice' => ['listing' => 'estimatepriceListing', 'new' => 'add_new_estimateprice', 'edit' => 'editEstimatepriceOld', 'delete' => 'deleteEstimateprice', 'class' => 'deleteEstimateprice', 'field' => 'estimateprice_id'],
    'fixed' => ['listing' => 'fixedListing', 'new' => 'add_new_fixed', 'edit' => 'editFixedOld', 'delete' => 'deleteFixed', 'class' => 'deleteFixed', 'field' => 'fixed_id'],
    'provider' => ['listing' => 'providerListing', 'new' => 'add_new_provider', 'edit' => 'editProviderOld', 'delete' => 'deleteProvider', 'class' => 'deleteProvider', 'field' => 'provider_id'],
][$type];
?>
<div class="content-wrapper">
    <div class="background-form" style="background-image: url(<?= base_url('assets/images/bg-form.png') ?>);"></div>
    <div class="content-table-form"><section class="content-header"><div class="row">
        <div class="col-xs-6"><h1><i class="fa fa-link"></i> <?= esc(str_replace(' List', '', $caption)) ?> Management <small>Add, Edit, Delete</small></h1></div>
        <div class="col-xs-6 text-right"><div class="form-group"><a class="btn btn-primary" href="<?= base_url($legacy['new']) ?>"><i class="fa fa-plus"></i> Add New</a></div></div>
    </div></section><section class="content"><div class="row"><div class="col-xs-12"><div class="box<?= $type === 'branch' ? ' box-scroll' : '' ?>">
        <div class="box-header"><h3 class="box-title"><?= esc($caption) ?></h3><div class="box-tools"><form action="<?= base_url($legacy['listing']) ?>" method="post" id="searchList"><div class="input-group">
            <input type="text" name="searchText" value="<?= esc($search) ?>" class="form-control input-sm pull-right" style="width: 150px;" placeholder="Search">
            <div class="input-group-btn"><button class="btn btn-sm btn-default searchList"><i class="fa fa-search"></i></button></div>
        </div></form></div></div>
        <div class="box-body table-responsive no-padding"><table class="table table-hover"><tr>
                <th><?= esc($definition['pkLabel'] ?? $definition['pk']) ?></th>
                <?php foreach ($listFields as $field): ?>
                    <th><?= esc($definition['fields'][$field]['listText'] ?? $field) ?></th>
                <?php endforeach ?>
                <?php if ($type === 'branchtype'): ?>
                    <th>Image</th>
                <?php endif ?>
                <th class="text-center">Actions</th>
            </tr>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><?= (int) $item[$definition['pk']] ?></td>
                    <?php foreach ($listFields as $field): ?>
                        <td><?= esc((string) ($item[$field] ?? '')) ?></td>
                    <?php endforeach ?>
                    <?php if ($type === 'branchtype'): ?>
                        <td>
                            <?php if (is_string($item['branch_type_image'] ?? null) && $item['branch_type_image'] !== ''): ?>
                                <img src="/branch-type-image/<?= esc(rawurlencode((string) $item['branch_type_image']), 'attr') ?>" alt="Branch type image" height="100">
                            <?php endif ?>
                        </td>
                    <?php endif ?>
                    <td class="text-center">
                        <a class="btn btn-sm btn-info" href="<?= base_url($legacy['edit'] . '/' . (int) $item[$definition['pk']]) ?>" title="Edit"><i class="fa fa-pencil"></i></a>
                        <a class="btn btn-sm btn-danger <?= esc($legacy['class']) ?>" href="#" data-record-id="<?= (int) $item[$definition['pk']] ?>" title="Delete"><i class="fa fa-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach ?>
        </table></div>
        <?php if (count($rows) === 50): ?><div class="box-footer clearfix"><a href="<?= base_url($legacy['listing'] . '/' . ($page + 1)) ?>">Next</a></div><?php endif ?>
    </div></div></div></section></div>
</div>
<script type="text/javascript">
jQuery(document).on('click', '.<?= esc($legacy['class']) ?>', function (event) {
    event.preventDefault();
    if (!window.confirm('Are you sure to delete this  ? ')) return;
    var row = jQuery(this).closest('tr');
    var data = { '<?= esc($legacy['field']) ?>': jQuery(this).data('record-id') };
    data['<?= esc(config('Security')->tokenName) ?>'] = '<?= esc(service('security')->getHash()) ?>';
    jQuery.ajax({ type: 'POST', dataType: 'json', url: '<?= base_url($legacy['delete']) ?>', data: data }).done(function (response) {
        if (response.status === true) row.remove();
    });
});
</script>
