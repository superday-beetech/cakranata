<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Procurement;

use Modules\Pharmacy\Models\PharmacyModel;

class GoodsReceiptItemModel extends PharmacyModel
{
    protected $table = 'goods_receipt_items';

    protected $allowedFields = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'quantity_received',
        'unit_cost',
        'notes',
    ];

    protected $useTimestamps = true;
}
