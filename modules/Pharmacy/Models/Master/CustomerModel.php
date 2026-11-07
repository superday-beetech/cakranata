<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Master;

use Modules\Pharmacy\Models\PharmacyModel;

class CustomerModel extends PharmacyModel
{
    protected $table = 'customers';

    protected $allowedFields = [
        'code',
        'name',
        'customer_type',
        'phone',
        'email',
        'address',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
}
