<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Sales;

use Modules\Pharmacy\Models\PharmacyModel;

class SaleItemModel extends PharmacyModel
{
    protected $table            = 'sale_items';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'sale_id',
        'product_id',
        'batch_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'subtotal',
        'unit_cost',
        'notes',
    ];
}
