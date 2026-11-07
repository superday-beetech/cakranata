<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Master;

use Modules\Pharmacy\Models\PharmacyModel;

class SupplierModel extends PharmacyModel
{
    protected $table = 'suppliers';

    protected $allowedFields = [
        'code',
        'name',
        'phone',
        'email',
        'address',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
}
