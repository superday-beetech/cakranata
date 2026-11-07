<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Master;

use Modules\Pharmacy\Models\PharmacyModel;

class UnitModel extends PharmacyModel
{
    protected $table = 'units';

    protected $allowedFields = [
        'name',
        'abbreviation',
    ];

    protected $useTimestamps = true;
}
