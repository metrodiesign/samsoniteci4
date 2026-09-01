<?php

namespace App\Master;

final class MasterCatalog
{
    /** @var array<string, list<array{table: string, column: string, mode?: 'csv'|'label'}>> */
    private const REFERENCES = [
        'branch' => [
            ['table' => 'request_order', 'column' => 'branchID'],
            ['table' => 'request_order_delete', 'column' => 'branchID'],
            ['table' => 'tbl_users', 'column' => 'branch_id'],
            ['table' => 'book', 'column' => 'branch_id'],
        ],
        'branchtype' => [
            ['table' => 'request_order', 'column' => 'branch_type_id'],
            ['table' => 'request_order_delete', 'column' => 'branch_type_id'],
            ['table' => 'tbl_users', 'column' => 'branch_type_id'],
            ['table' => 'branch', 'column' => 'branch_type'],
        ],
        'statustype' => [
            ['table' => 'uploadstaus', 'column' => 'tracking_status'],
        ],
        'producttype' => [
            ['table' => 'request_order', 'column' => 'detailTypeId'],
            ['table' => 'request_order_delete', 'column' => 'detailTypeId'],
        ],
        'book' => [
            ['table' => 'request_order', 'column' => 'bookID', 'mode' => 'label'],
            ['table' => 'request_order_delete', 'column' => 'bookID', 'mode' => 'label'],
            ['table' => 'uploadstaus', 'column' => 'bookID', 'mode' => 'label'],
        ],
        'brand' => [
            ['table' => 'request_order', 'column' => 'detailBrandId'],
            ['table' => 'request_order_delete', 'column' => 'detailBrandId'],
        ],
        'condition' => [
            ['table' => 'request_order', 'column' => 'detailCondition', 'mode' => 'csv'],
            ['table' => 'request_order_delete', 'column' => 'detailCondition', 'mode' => 'csv'],
        ],
        'estimateprice' => [
            ['table' => 'request_order', 'column' => 'detailEstimatePrice', 'mode' => 'csv'],
            ['table' => 'request_order_delete', 'column' => 'detailEstimatePrice', 'mode' => 'csv'],
        ],
        'fixed' => [
            ['table' => 'request_order', 'column' => 'detailFixed', 'mode' => 'csv'],
            ['table' => 'request_order_delete', 'column' => 'detailFixed', 'mode' => 'csv'],
        ],
        'provider' => [
            ['table' => 'request_order', 'column' => 'provider_id'],
            ['table' => 'request_order_delete', 'column' => 'provider_id'],
        ],
    ];

    /** @var array<string, array{table: string, pk: string, label: string, pkLabel?: string, listFields?: list<string>, searchColumns?: list<string>, searchJoins?: list<array{table: string, on: string}>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, preserveEmpty?: bool, trim?: bool, preserveNull?: bool, emptyIntValue?: int, fk?: string, formText?: string, listText?: string}>}> */
    // formText / listText / pkLabel are copied verbatim from the CI3 views under
    // samsoniteci3/application/views/master/ for UI parity. Typos, doubled spaces and stray
    // characters (e.g. the leading mark in 'ฺBookId') are intentional — do NOT "fix" them.
    // Fields marked INVENTED below have no CI3 listing counterpart but remain in CI4 forms and CRUD.
    private const DEFINITIONS = [
        'branch' => [
            'table' => 'branch', 'pk' => 'branch_id', 'label' => 'branch_name', 'pkLabel' => 'Id',
            'listFields' => ['branch_type', 'branch_user_name', 'branch_name', 'default_suffix', 'customer_ref'],
            'searchColumns' => ['branch.branch_name', 'branch_type.branch_type_details', 'branch.default_suffix'],
            'searchJoins' => [['table' => 'branch_type', 'on' => 'branch_type.branch_type_id = branch.branch_type']],
            'fields' => [
                'branch_type' => ['kind' => 'int', 'required' => true, 'fk' => 'branchtype', 'formText' => 'Branch Type', 'listText' => 'Branch type'],
                'branch_user_name' => ['kind' => 'string', 'max' => 100, 'preserveEmpty' => true, 'formText' => 'Branch User', 'listText' => 'Branch User'],
                'branch_name' => ['kind' => 'string', 'max' => 250, 'required' => true, 'formText' => 'Branch Name', 'listText' => 'Branch name'],
                'branch_details' => ['kind' => 'string', 'max' => 250, 'preserveEmpty' => true, 'formText' => 'Detail', 'listText' => 'Detail'],
                'default_suffix' => ['kind' => 'string', 'max' => 10, 'preserveEmpty' => true, 'formText' => 'PREFIX', 'listText' => 'Branch suffix'],
                'book_order' => ['kind' => 'string', 'max' => 10, 'preserveEmpty' => true, 'formText' => 'book order', 'listText' => 'book order'],
                'customer_ref' => ['kind' => 'string', 'max' => 50, 'preserveEmpty' => true, 'formText' => 'Customer Ref', 'listText' => 'Ref'],
            ],
        ],
        'branchtype' => [
            'table' => 'branch_type', 'pk' => 'branch_type_id', 'label' => 'branch_type_details', 'pkLabel' => 'Branch type Id',
            'fields' => ['branch_type_details' => ['kind' => 'string', 'max' => 250, 'required' => true, 'formText' => 'Branch type Name', 'listText' => 'Branch type Details']],
        ],
        'statustype' => [
            'table' => 'tracking_status', 'pk' => 'status_id', 'label' => 'description_en', 'pkLabel' => 'Id',
            'searchColumns' => ['description_en', 'description_th'],
            'fields' => [
                'description_th' => ['kind' => 'string', 'max' => 250, 'required' => true, 'formText' => 'Description th', 'listText' => 'Description th'],
                'description_en' => ['kind' => 'string', 'max' => 250, 'required' => true, 'formText' => 'Description en', 'listText' => 'Description en'],
                'success' => ['kind' => 'int', 'allowZero' => true, 'preserveNull' => true, 'emptyIntValue' => 0, 'formText' => 'Config Status(0/1)', 'listText' => 'Config Status'],
            ],
        ],
        'producttype' => [
            'table' => 'type', 'pk' => 'type_id', 'label' => 'type_details', 'pkLabel' => 'Products type Id',
            'fields' => ['type_details' => ['kind' => 'string', 'max' => 250, 'required' => true, 'formText' => 'Products type details', 'listText' => 'Products type Details']],
        ],
        'book' => [
            'table' => 'book', 'pk' => 'book_id', 'label' => 'book_detail', 'pkLabel' => 'ฺBookId',
            'listFields' => ['branch_id', 'book_detail', 'status'],
            'searchColumns' => ['book.book_detail', 'branch.branch_name'],
            'searchJoins' => [['table' => 'branch', 'on' => 'branch.branch_id = book.branch_id']],
            'fields' => [
                'branch_id' => ['kind' => 'int', 'required' => true, 'fk' => 'branch', 'formText' => 'Branch', 'listText' => 'Branch name'],
                'book_detail' => ['kind' => 'string', 'max' => 3, 'required' => true, 'formText' => 'Book Detail', 'listText' => 'Book Details'],
                'status' => ['kind' => 'int', 'required' => true, 'allowZero' => true, 'formText' => 'Publishing Status', 'listText' => 'Status'],
                'bunber_limit' => ['kind' => 'int', 'allowZero' => true, 'visible' => false],
            ],
        ],
        'brand' => [
            'table' => 'brand', 'pk' => 'brand_id', 'label' => 'brand_details', 'pkLabel' => 'Brand Id',
            'fields' => ['brand_details' => ['kind' => 'string', 'max' => 250, 'required' => true, 'formText' => 'Brand Name', 'listText' => 'Brand Details']],
        ],
        'condition' => [
            'table' => 'condition', 'pk' => 'condition_id', 'label' => 'condition_details', 'pkLabel' => 'Condition Id',
            'fields' => ['condition_details' => ['kind' => 'string', 'max' => 250, 'required' => true, 'formText' => 'Condition Name', 'listText' => 'Condition Details']],
        ],
        'estimateprice' => [
            'table' => 'estimateprice', 'pk' => 'estimateprice_id', 'label' => 'estimateprice_details', 'pkLabel' => 'Estimateprice Id',
            'fields' => ['estimateprice_details' => ['kind' => 'string', 'max' => 250, 'required' => true, 'formText' => 'Estimateprice Name', 'listText' => 'Estimateprice Details']],
        ],
        'fixed' => [
            'table' => 'fixed', 'pk' => 'fixed_id', 'label' => 'fixed_details', 'pkLabel' => 'Fixed Id',
            'fields' => ['fixed_details' => ['kind' => 'string', 'max' => 250, 'required' => true, 'formText' => 'Fixed Name', 'listText' => 'Fixed Details']],
        ],
        'provider' => [
            'table' => 'provider', 'pk' => 'provider_id', 'label' => 'provider_name', 'pkLabel' => 'Provider Id',
            'fields' => [
                'provider_name' => ['kind' => 'string', 'max' => 250, 'required' => true, 'formText' => 'Provider Name', 'listText' => 'Provider Name'],
                'provider_tel' => ['kind' => 'string', 'max' => 50, 'required' => true, 'formText' => 'Provider Tel', 'listText' => 'Provider Tel'],
                'provider_datail' => ['kind' => 'string', 'max' => 4000, 'preserveEmpty' => true, 'trim' => false, 'formText' => 'Detail', 'listText' => 'Provider Details'],
            ],
        ],
    ];

    /** @return array{table: string, pk: string, label: string, pkLabel?: string, listFields?: list<string>, searchColumns?: list<string>, searchJoins?: list<array{table: string, on: string}>, fields: array<string, array{kind: string, max?: int, required?: bool, allowZero?: bool, preserveEmpty?: bool, trim?: bool, preserveNull?: bool, emptyIntValue?: int, fk?: string, formText?: string, listText?: string}>}|null */
    public static function definition(string $type): ?array
    {
        return self::DEFINITIONS[$type] ?? null;
    }

    /** @return list<array{table: string, column: string, mode?: 'csv'|'label'}> */
    public static function references(string $type): array
    {
        return self::REFERENCES[$type] ?? [];
    }

    /**
     * Page and card headings copied verbatim from the CI3 master views, inconsistent casing
     * included (`Branch type Management`, `fixed Management`, `Enter brand Details`). The
     * visual parity comparison reads these strings, so do not normalise them.
     *
     * @var array<string, array{list: string, listCaption: string, form: string, formCaption: string}>
     */
    private const TITLES = [
        'branch' => ['list' => 'Branch Management', 'listCaption' => 'Branch List', 'form' => 'Branch Management', 'formCaption' => 'Enter Branch Details'],
        'branchtype' => ['list' => 'Branch Type Management', 'listCaption' => 'Branch Type List', 'form' => 'Branch type Management', 'formCaption' => 'Enter branch type Details'],
        'statustype' => ['list' => 'Status Type Management', 'listCaption' => 'Status Type List', 'form' => 'Status type Management', 'formCaption' => 'Enter Status type Details'],
        'producttype' => ['list' => 'Products Type Management', 'listCaption' => 'Products Type List', 'form' => 'Products type Management', 'formCaption' => 'Enter Products type Details'],
        'book' => ['list' => 'Book Management', 'listCaption' => 'Book List', 'form' => 'Book Management', 'formCaption' => 'Enter Book Details'],
        'brand' => ['list' => 'Brand Management', 'listCaption' => 'Brand List', 'form' => 'Brand Management', 'formCaption' => 'Enter brand Details'],
        'condition' => ['list' => 'Condition Management', 'listCaption' => 'Condition List', 'form' => 'Condition Management', 'formCaption' => 'Enter condition Details'],
        'estimateprice' => ['list' => 'Estimateprice Management', 'listCaption' => 'Estimateprice List', 'form' => 'Estimateprice Management', 'formCaption' => 'Enter Estimateprice Details'],
        'fixed' => ['list' => 'fixed Management', 'listCaption' => 'fixed List', 'form' => 'fixed Management', 'formCaption' => 'Enter fixed Details'],
        'provider' => ['list' => 'Provider Management', 'listCaption' => 'Provider List', 'form' => 'Provider Management', 'formCaption' => 'Enter Provider Details'],
    ];

    /** CI3's `Add / Edit <thing>` subtitle on the form page, per type. */
    private const FORM_SUBTITLES = [
        'branch' => 'Add / Edit Branch',
        'branchtype' => 'Add / Edit Branch',
        'statustype' => 'Add / Edit Status',
        'producttype' => 'Add / Edit Products type',
        'book' => 'Add / Edit Book',
        'brand' => 'Add / Edit Brand',
        'condition' => 'Add / Edit Condition',
        'estimateprice' => 'Add / Edit Estimateprice',
        'fixed' => 'Add / Edit fixed',
        'provider' => 'Add / Edit Provider',
    ];

    /** @return array{title: string, subtitle: string, caption: string} */
    public static function heading(string $type, bool $form): array
    {
        $titles = self::TITLES[$type] ?? null;
        if ($titles === null) {
            return ['title' => 'Master data: ' . $type, 'subtitle' => '', 'caption' => ''];
        }

        return $form
            ? ['title' => $titles['form'], 'subtitle' => self::FORM_SUBTITLES[$type] ?? '', 'caption' => $titles['formCaption']]
            : ['title' => $titles['list'], 'subtitle' => 'Add, Edit, Delete', 'caption' => $titles['listCaption']];
    }
}
