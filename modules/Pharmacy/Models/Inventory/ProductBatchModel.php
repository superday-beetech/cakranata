<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Inventory;

use Modules\Pharmacy\Models\PharmacyModel;

class ProductBatchModel extends PharmacyModel
{
    protected $table = 'product_batches';

    protected $allowedFields = [
        'product_id',
        'goods_receipt_item_id',
        'batch_number',
        'expired_at',
        'received_quantity',
        'quantity_remaining',
        'unit_cost',
        'status',
    ];

    protected $useTimestamps = true;
}
