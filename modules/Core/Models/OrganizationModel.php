<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use CodeIgniter\Model;

class OrganizationModel extends Model
{
    protected $table            = 'core_organizations';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'code',
        'slug',
        'name',
        'legal_name',
        'organization_type',
        'status',
        'timezone',
        'currency',
        'settings',
        'deleted_at',
    ];

    public function getActiveOrganizations(): array
    {
        return $this->where('status', 'active')
            ->where('deleted_at', null)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function findAvailable(int $id): ?array
    {
        $result = $this->where('id', $id)
            ->where('deleted_at', null)
            ->first();

        return is_array($result) ? $result : null;
    }
}
