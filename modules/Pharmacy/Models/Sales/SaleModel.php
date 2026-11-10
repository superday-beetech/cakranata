<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Sales;

use Modules\Pharmacy\Models\PharmacyModel;

class SaleModel extends PharmacyModel
{
    protected $table            = 'sales';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'sale_number',
        'customer_id',
        'sale_date',
        'status',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'amount_received',
        'change_amount',
        'settlement_mode',
        'payment_status',
        'payment_method',
        'reference_number',
        'due_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
