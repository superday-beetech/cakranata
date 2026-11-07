<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Distribution;

use Modules\Pharmacy\Models\PharmacyModel;

class DeliveryItemModel extends PharmacyModel
{
    protected $table = 'delivery_items';

    protected $allowedFields = [
        'delivery_id',
        'distribution_item_id',
        'batch_id',
        'quantity_dispatched',
        'unit_price',
    ];

    protected $useTimestamps = true;
}
