<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Inventory;

use Modules\Pharmacy\Models\PharmacyModel;

class StockMovementModel extends PharmacyModel
{
    protected $table = 'stock_movements';

    protected $allowedFields = [
        'product_id',
        'batch_id',
        'movement_date',
        'movement_type',
        'reference_type',
        'reference_id',
        'quantity_in',
        'quantity_out',
        'unit_cost',
        'notes',
        'created_by',
    ];

    protected $useTimestamps = false;

    protected $createdField = 'created_at';

    protected $updatedField = '';
}
