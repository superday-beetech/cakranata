<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Returns;

use Modules\Pharmacy\Models\PharmacyModel;

class SalesReturnModel extends PharmacyModel
{
    protected $table            = 'sales_returns';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'return_number',
        'customer_id',
        'sale_id',
        'delivery_id',
        'return_date',
        'status',
        'reason',
        'total_amount',
        'notes',
        'created_by',
        'updated_by',
    ];
}
