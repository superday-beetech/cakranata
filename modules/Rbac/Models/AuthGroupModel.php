<?php

declare(strict_types=1);

namespace Modules\Rbac\Models;

use CodeIgniter\Model;

class AuthGroupModel extends Model
{
    protected $table            = 'auth_groups';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'description',
    ];

    public function getAllGroups(): array
    {
        return $this->select('id, name, description')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function findGroup(int $id): ?array
    {
        $row = $this->find($id);

        return $row ?: null;
    }
}
