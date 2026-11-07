<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Return;

use Modules\Pharmacy\Models\PharmacyModel;

class PurchaseReturnItemModel extends PharmacyModel
{
    protected $table = 'purchase_return_items';

    protected $allowedFields = [
        'purchase_return_id',
        'goods_receipt_item_id',
        'batch_id',
        'quantity',
        'unit_cost',
        'subtotal',
        'notes',
    ];

    protected $useTimestamps = true;
}
