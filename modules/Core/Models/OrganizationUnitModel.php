<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use CodeIgniter\Model;

class OrganizationUnitModel extends Model
{
    protected $table            = 'core_organization_units';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'organization_id',
        'parent_id',
        'code',
        'name',
        'unit_type',
        'status',
        'metadata',
        'deleted_at',
    ];

    public function findAvailable(int $id): ?array
    {
        $result = $this->where('id', $id)
            ->where('deleted_at', null)
            ->first();

        return is_array($result) ? $result : null;
    }

    public function getByOrganization(int $organizationId): array
    {
        return $this->where('organization_id', $organizationId)
            ->where('deleted_at', null)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function getActiveByOrganization(int $organizationId): array
    {
        return $this->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->where('deleted_at', null)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
