<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Procurement;

use Modules\Pharmacy\Models\PharmacyModel;

class PurchaseOrderItemModel extends PharmacyModel
{
    protected $table = 'purchase_order_items';

    protected $allowedFields = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'unit_cost',
        'discount_amount',
        'subtotal',
        'notes',
    ];

    protected $useTimestamps = true;
}
