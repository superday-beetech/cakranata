<?php

declare(strict_types=1);

namespace Modules\Rbac\Models;

use CodeIgniter\Model;

class UserPositionModel extends Model
{
    protected $table            = 'user_positions';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'user_id',
        'position_id',
        'organization_unit_id',
        'atasan_user_id',
        'is_primary',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'catatan',
    ];

    public function getByUserId(int $userId): array
    {
        return $this->where('user_id', $userId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('tanggal_mulai', 'DESC')
            ->findAll();
    }
}
