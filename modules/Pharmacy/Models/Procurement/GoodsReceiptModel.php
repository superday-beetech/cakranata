<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Procurement;

use Modules\Pharmacy\Models\PharmacyModel;

class GoodsReceiptModel extends PharmacyModel
{
    protected $table = 'goods_receipts';

    protected $allowedFields = [
        'receipt_number',
        'purchase_order_id',
        'received_date',
        'supplier_delivery_number',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
}
