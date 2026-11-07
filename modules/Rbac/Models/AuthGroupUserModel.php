<?php

declare(strict_types=1);

namespace Modules\Rbac\Models;

use CodeIgniter\Model;
use RuntimeException;

class AuthGroupUserModel extends Model
{
    protected $table         = 'auth_groups_users';
    protected $primaryKey    = 'user_id';
    protected $returnType    = 'array';
    protected $protectFields = true;

    protected $allowedFields = [
        'user_id',
        'group',
    ];

    protected $useTimestamps = false;

    /**
     * Ambil nama group yang dimiliki user.
     */
    public function getGroupNamesForUser(int $userId): array
    {
        $rows = $this->builder()
            ->select('group')
            ->where('user_id', $userId)
            ->orderBy('group', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_map(
            static fn(array $row): string => (string) $row['group'],
            $rows
        ));
    }

    /**
     * Hapus seluruh relasi group milik user.
     */
    public function deleteForUser(int $userId): bool
    {
        return $this->builder()
            ->where('user_id', $userId)
            ->delete() !== false;
    }

    /**
     * Tambahkan relasi group berdasarkan nama group.
     */
    public function insertForUser(
        int $userId,
        array $groupNames
    ): bool {
        if ($userId < 1) {
            throw new RuntimeException('User ID tidak valid.');
        }

        $groupNames = array_values(array_unique(array_map(
            static function ($groupName): string {
                if (
                    ! is_string($groupName)
                    || trim($groupName) === ''
                ) {
                    throw new RuntimeException(
                        'Nama group tidak valid.'
                    );
                }

                return trim($groupName);
            },
            $groupNames
        )));

        if ($groupNames === []) {
            return true;
        }

        $rows = [];

        foreach ($groupNames as $groupName) {
            $rows[] = [
                'user_id' => $userId,
                'group'   => $groupName,
            ];
        }

        return $this->db
            ->table($this->table)
            ->insertBatch($rows) !== false;
    }
}
