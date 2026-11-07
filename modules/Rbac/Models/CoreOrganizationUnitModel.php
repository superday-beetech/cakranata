<?php

declare(strict_types=1);

namespace Modules\Rbac\Models;

use CodeIgniter\Model;

class CoreOrganizationUnitModel extends Model
{
    protected $table            = 'core_organization_units';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'organization_id',
        'parent_id',
        'code',
        'name',
        'unit_type',
        'status',
        'metadata',
    ];

    public function getActiveByOrganization(int $organizationId): array
    {
        return $this->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->where('deleted_at', null)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
