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

    /** @var array<string, array{table: string, pk: string, label: string, fields: array<string, array{kind: string, max?: int, required?: bool}>}> */
    private const DEFINITIONS = [
        'branch' => [
            'table' => 'branch', 'pk' => 'branch_id', 'label' => 'branch_name',
            'fields' => [
                'branch_type' => ['kind' => 'int', 'required' => true],
                'branch_user_name' => ['kind' => 'string', 'max' => 100],
                'branch_name' => ['kind' => 'string', 'max' => 250, 'required' => true],
                'branch_details' => ['kind' => 'string', 'max' => 250, 'required' => true],
                'default_suffix' => ['kind' => 'string', 'max' => 10, 'required' => true],
                'book_order' => ['kind' => 'string', 'max' => 10, 'required' => true],
                'customer_ref' => ['kind' => 'string', 'max' => 50],
            ],
        ],
        'branchtype' => [
            'table' => 'branch_type', 'pk' => 'branch_type_id', 'label' => 'branch_type_details',
            'fields' => ['branch_type_details' => ['kind' => 'string', 'max' => 250, 'required' => true]],
        ],
        'statustype' => [
            'table' => 'tracking_status', 'pk' => 'status_id', 'label' => 'description_en',
            'fields' => [
                'description_th' => ['kind' => 'string', 'max' => 250, 'required' => true],
                'description_en' => ['kind' => 'string', 'max' => 250, 'required' => true],
                'success' => ['kind' => 'int', 'required' => true],
            ],
        ],
        'producttype' => [
            'table' => 'type', 'pk' => 'type_id', 'label' => 'type_details',
            'fields' => ['type_details' => ['kind' => 'string', 'max' => 250, 'required' => true]],
        ],
        'book' => [
            'table' => 'book', 'pk' => 'book_id', 'label' => 'book_detail',
            'fields' => [
                'branch_id' => ['kind' => 'int', 'required' => true],
                'book_detail' => ['kind' => 'string', 'max' => 3, 'required' => true],
                'status' => ['kind' => 'int', 'required' => true],
                'bunber_limit' => ['kind' => 'int', 'required' => true],
            ],
        ],
        'brand' => [
            'table' => 'brand', 'pk' => 'brand_id', 'label' => 'brand_details',
            'fields' => ['brand_details' => ['kind' => 'string', 'max' => 250, 'required' => true]],
        ],
        'condition' => [
            'table' => 'condition', 'pk' => 'condition_id', 'label' => 'condition_details',
            'fields' => ['condition_details' => ['kind' => 'string', 'max' => 250, 'required' => true]],
        ],
        'estimateprice' => [
            'table' => 'estimateprice', 'pk' => 'estimateprice_id', 'label' => 'estimateprice_details',
            'fields' => ['estimateprice_details' => ['kind' => 'string', 'max' => 250, 'required' => true]],
        ],
        'fixed' => [
            'table' => 'fixed', 'pk' => 'fixed_id', 'label' => 'fixed_details',
            'fields' => ['fixed_details' => ['kind' => 'string', 'max' => 250, 'required' => true]],
        ],
        'provider' => [
            'table' => 'provider', 'pk' => 'provider_id', 'label' => 'provider_name',
            'fields' => [
                'provider_name' => ['kind' => 'string', 'max' => 250, 'required' => true],
                'provider_tel' => ['kind' => 'string', 'max' => 50, 'required' => true],
                'provider_datail' => ['kind' => 'string', 'max' => 4000, 'required' => true],
            ],
        ],
    ];

    /** @return array{table: string, pk: string, label: string, fields: array<string, array{kind: string, max?: int, required?: bool}>}|null */
    public static function definition(string $type): ?array
    {
        return self::DEFINITIONS[$type] ?? null;
    }

    /** @return list<array{table: string, column: string, mode?: 'csv'|'label'}> */
    public static function references(string $type): array
    {
        return self::REFERENCES[$type] ?? [];
    }
}
