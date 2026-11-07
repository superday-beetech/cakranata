<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Distribution;

use Modules\Pharmacy\Models\PharmacyModel;

class DistributionItemModel extends PharmacyModel
{
    protected $table = 'distribution_items';

    protected $allowedFields = [
        'distribution_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'subtotal',
        'notes',
    ];

    protected $useTimestamps = true;
}
