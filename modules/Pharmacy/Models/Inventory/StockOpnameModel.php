<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Inventory;

use Modules\Pharmacy\Models\PharmacyModel;

class StockOpnameModel extends PharmacyModel
{
    protected $table = 'stock_opnames';

    protected $allowedFields = [
        'opname_number',
        'opname_date',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
}
