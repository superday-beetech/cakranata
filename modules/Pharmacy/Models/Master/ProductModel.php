<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Master;

use Modules\Pharmacy\Models\PharmacyModel;

class ProductModel extends PharmacyModel
{
    protected $table            = 'products';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $allowedFields = [
        'sku',
        'barcode',
        'name',
        'category_id',
        'unit_id',
        'purchase_price',
        'selling_price',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'sku' => 'required|max_length[50]|is_unique[products.sku,id,{id}]',

        'barcode' => 'permit_empty|max_length[100]',

        'name' => 'required|max_length[180]',

        'category_id' => 'required|integer|greater_than[0]',

        'unit_id' => 'required|integer|greater_than[0]',

        'purchase_price' => 'required|decimal|greater_than_equal_to[0]',

        'selling_price' => 'required|decimal|greater_than_equal_to[0]',

        'description' => 'permit_empty',

        'is_active' => 'required|in_list[0,1]',
    ];

    protected $validationMessages = [
        'sku' => [
            'required' => 'SKU produk wajib diisi.',
            'max_length' => 'SKU maksimal 50 karakter.',
            'is_unique' => 'SKU sudah digunakan produk lain.',
        ],
        'barcode' => [
            'max_length' => 'Barcode maksimal 100 karakter.',
        ],
        'name' => [
            'required' => 'Nama produk wajib diisi.',
            'max_length' => 'Nama produk maksimal 180 karakter.',
        ],
        'category_id' => [
            'required' => 'Kategori produk wajib dipilih.',
            'integer' => 'Kategori produk tidak valid.',
            'greater_than' => 'Kategori produk tidak valid.',
        ],
        'unit_id' => [
            'required' => 'Satuan produk wajib dipilih.',
            'integer' => 'Satuan produk tidak valid.',
            'greater_than' => 'Satuan produk tidak valid.',
        ],
        'purchase_price' => [
            'required' => 'Harga beli wajib diisi.',
            'decimal' => 'Harga beli harus berupa angka desimal.',
            'greater_than_equal_to' => 'Harga beli tidak boleh negatif.',
        ],
        'selling_price' => [
            'required' => 'Harga jual wajib diisi.',
            'decimal' => 'Harga jual harus berupa angka desimal.',
            'greater_than_equal_to' => 'Harga jual tidak boleh negatif.',
        ],
        'is_active' => [
            'required' => 'Status produk wajib ditentukan.',
            'in_list' => 'Status produk tidak valid.',
        ],
    ];
}
