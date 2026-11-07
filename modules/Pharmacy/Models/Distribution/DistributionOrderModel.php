<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Distribution;

use Modules\Pharmacy\Models\PharmacyModel;

class DistributionOrderModel extends PharmacyModel
{
    protected $table = 'distribution_orders';

    protected $allowedFields = [
        'order_number',
        'customer_id',
        'order_date',
        'requested_delivery_date',
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
