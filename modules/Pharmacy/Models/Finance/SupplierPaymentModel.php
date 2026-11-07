<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Finance;

use Modules\Pharmacy\Models\PharmacyModel;

class SupplierPaymentModel extends PharmacyModel
{
    protected $table = 'supplier_payments';

    protected $allowedFields = [
        'payment_number',
        'supplier_id',
        'purchase_order_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'created_by',
    ];

    protected $useTimestamps = true;
}
