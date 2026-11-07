<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Models\Finance;

use Modules\Pharmacy\Models\PharmacyModel;

class CashTransactionModel extends PharmacyModel
{
    protected $table = 'cash_transactions';

    protected $allowedFields = [
        'transaction_number',
        'transaction_date',
        'transaction_type',
        'reference_type',
        'reference_id',
        'description',
        'amount',
        'payment_method',
        'created_by',
    ];

    protected $useTimestamps = false;

    protected $createdField = 'created_at';

    protected $updatedField = '';
}
