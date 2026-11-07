<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Procurement;

use Modules\Pharmacy\Models\PharmacyModel;

class PurchaseOrderModel extends PharmacyModel
{
    protected $table = 'purchase_orders';

    protected $allowedFields = [
        'order_number',
        'supplier_id',
        'order_date',
        'expected_date',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
}
