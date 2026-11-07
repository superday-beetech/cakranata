<?php

declare(strict_types=1);

namespace Modules\Rbac\Models;

use CodeIgniter\Model;
use RuntimeException;

class AuthGroupPermissionModel extends Model
{
    protected $table         = 'auth_groups_permissions';
    protected $primaryKey    = 'group_id';
    protected $returnType    = 'array';
    protected $protectFields = true;

    protected $allowedFields = [
        'group_id',
        'permission_id',
        'created_at',
    ];

    protected $useTimestamps = false;

    /**
     * Ambil seluruh ID permission milik grup.
     */
    public function getPermissionIds(int $groupId): array
    {
        $rows = $this->builder()
            ->select('permission_id')
            ->where('group_id', $groupId)
            ->get()
            ->getResultArray();

        return array_map(
            'intval',
            array_column($rows, 'permission_id')
        );
    }

    /**
     * Hapus seluruh relasi permission milik grup.
     */
    public function deleteForGroup(int $groupId): bool
    {
        $builder = $this->builder()
            ->where('group_id', $groupId);

        if ($builder->delete() === false) {
            return false;
        }

        return true;
    }

    /**
     * Tambahkan relasi permission untuk grup.
     */

    public function insertForGroup(
        int $groupId,
        array $permissionIds
    ): bool {
        $permissionIds = array_values(array_unique(array_filter(
            array_map('intval', $permissionIds),
            static fn(int $id): bool => $id > 0
        )));

        if ($permissionIds === []) {
            return true;
        }

        $createdAt = date('Y-m-d H:i:s');

        $rows = [];

        foreach ($permissionIds as $permissionId) {
            $rows[] = [
                'group_id'      => $groupId,
                'permission_id' => $permissionId,
                'created_at'    => $createdAt,
            ];
        }

        return $this->db
            ->table($this->table)
            ->insertBatch($rows) !== false;
    }
}
