<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Inventory;

use Modules\Pharmacy\Models\PharmacyModel;

class StockOpnameItemModel extends PharmacyModel
{
    protected $table = 'stock_opname_items';

    protected $allowedFields = [
        'stock_opname_id',
        'product_id',
        'batch_id',
        'system_quantity',
        'counted_quantity',
        'difference_quantity',
        'notes',
    ];

    protected $useTimestamps = true;
}
