<?php

declare(strict_types=1);

namespace Modules\Rbac\Models;

use CodeIgniter\Shield\Models\PermissionModel;
use RuntimeException;

class AuthPermissionUserModel extends PermissionModel
{

    public function getPermissionNamesForUser(int $userId): array
    {
        $rows = $this->builder()
            ->select('permission')
            ->where('user_id', $userId)
            ->orderBy('permission', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_map(
            static fn(array $row): string => (string) $row['permission'],
            $rows
        ));
    }

    /**
     * Hapus seluruh permission langsung milik user.
     */
    public function deleteForUser(int $userId): bool
    {
        return $this->builder()
            ->where('user_id', $userId)
            ->delete() !== false;
    }

    /**
     * Tambahkan permission langsung ke user.
     */
    public function insertForUser(int $userId, array $permissionNames): bool
    {
        if ($userId < 1) {
            throw new RuntimeException('User ID tidak valid.');
        }

        $permissionNames = array_values(array_unique(array_map(
            static function ($permission): string {
                if (! is_string($permission) || trim($permission) === '') {
                    throw new RuntimeException('Nama permission tidak valid.');
                }

                return trim($permission);
            },
            $permissionNames
        )));

        if ($permissionNames === []) {
            return true;
        }

        $rows = [];

        foreach ($permissionNames as $permission) {
            $rows[] = [
                'user_id'    => $userId,
                'permission' => $permission,
            ];
        }

        return $this->db
            ->table($this->table)
            ->insertBatch($rows) !== false;
    }
}
