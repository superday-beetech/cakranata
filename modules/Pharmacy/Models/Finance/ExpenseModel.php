<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Finance;

use Modules\Pharmacy\Models\PharmacyModel;

class ExpenseModel extends PharmacyModel
{
    protected $table = 'expenses';

    protected $allowedFields = [
        'expense_number',
        'expense_date',
        'category',
        'description',
        'amount',
        'payment_method',
        'reference_number',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $useTimestamps = true;
}
