<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use CodeIgniter\Model;

class OrganizationMembershipModel extends Model
{
    protected $table            = 'core_organization_memberships';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields   = true;
    protected $useTimestamps   = false;

    protected $allowedFields = [
        'organization_id',
        'user_id',
        'unit_id',
        'membership_role',
        'status',
        'is_default',
    ];

    public function findAvailable(int $id): ?array
    {
        $row = $this->find($id);

        return is_array($row) ? $row : null;
    }

    public function getByOrganization(int $organizationId): array
    {
        return $this->where('organization_id', $organizationId)
            ->orderBy('user_id', 'ASC')
            ->findAll();
    }

    public function getByUser(int $userId): array
    {
        return $this->where('user_id', $userId)
            ->orderBy('is_default', 'DESC')
            ->findAll();
    }
}
