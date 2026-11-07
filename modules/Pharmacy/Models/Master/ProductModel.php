<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Master;

use Modules\Pharmacy\Models\PharmacyModel;

class ProductModel extends PharmacyModel
{
    protected $table = 'products';

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
}
