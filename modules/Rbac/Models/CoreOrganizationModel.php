<?php

declare(strict_types=1);

namespace Modules\Rbac\Models;

use CodeIgniter\Model;

class CoreOrganizationModel extends Model
{
    protected $table            = 'core_organizations';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $protectFields    = true;

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
    ];

    public function getActiveOrganizations(): array
    {
        return $this->where('status', 'active')
            ->where('deleted_at', null)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
