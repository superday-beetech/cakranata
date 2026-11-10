<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Authorization\PermissionMatcher;
use Config\Database;
use RuntimeException;

class AccessControlService
{
    private const SUPERADMIN_GROUP = 'superadmin';
    protected BaseConnection $db;
    /**
     * Cache selama instance service hidup.
     */
    private array $activeUserCache = [];
    private array $superadminCache = [];
    private array $effectivePermissionsCache = [];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Memastikan pengguna ada dan belum dihapus.
     */
    public function isActiveUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return $this->db->table('users')
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->countAllResults() > 0;
    }

    /**
     * Memeriksa keanggotaan grup superadmin.
     */
    public function isSuperadmin(int $userId): bool
    {
        if (! $this->isActiveUser($userId)) {
            return false;
        }

        return $this->db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', self::SUPERADMIN_GROUP)
            ->countAllResults() > 0;
    }

    /**
     * Mengambil gabungan permission langsung dan permission grup.
     *
     * Permission dikembalikan sebagai nama agar kompatibel
     * dengan PermissionMatcher dan mekanisme permission Shield.
     *
     * @return list<string>
     */
    public function getEffectivePermissions(int $userId): array
    {
        if (! $this->isActiveUser($userId)) {
            return [];
        }

        // Permission yang diberikan langsung kepada pengguna.
        $directRows = $this->db->table('auth_permissions_users')
            ->select('permission')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        // Permission dari grup:
        // auth_groups_users.group
        // -> auth_groups.name
        // -> auth_groups.id
        // -> auth_groups_permissions.group_id
        // -> auth_permissions.id
        $groupRows = $this->db->table('auth_groups_users AS agu')
            ->select('ap.name AS permission')
            ->join(
                'auth_groups AS ag',
                'ag.name = agu.group',
                'inner'
            )
            ->join(
                'auth_groups_permissions AS agp',
                'agp.group_id = ag.id',
                'inner'
            )
            ->join(
                'auth_permissions AS ap',
                'ap.id = agp.permission_id',
                'inner'
            )
            ->where('agu.user_id', $userId)
            ->get()
            ->getResultArray();

        $permissions = [];

        foreach (array_merge($directRows, $groupRows) as $row) {
            $name = trim((string) ($row['permission'] ?? ''));

            if ($name !== '') {
                $permissions[$name] = $name;
            }
        }
        return array_values($permissions);
    }

    /**
     * Memeriksa permission pengguna, termasuk pola wildcard Shield.
     */
    public function hasPermission(
        int $userId,
        string $permission
    ): bool {
        $permission = trim($permission);

        if ($permission === '' || ! $this->isActiveUser($userId)) {
            return false;
        }

        if ($this->isSuperadmin($userId)) {
            return true;
        }

        $permissions = $this->getEffectivePermissions($userId);

        return $permissions !== []
            && PermissionMatcher::matches($permission, $permissions);
    }

    /**
     * Mengharuskan pengguna memiliki permission tertentu.
     *
     * @throws RuntimeException
     */
    public function requirePermission(
        int $userId,
        string $permission
    ): void {
        if (! $this->hasPermission($userId, $permission)) {
            throw new RuntimeException(
                'Anda tidak memiliki izin untuk melakukan tindakan ini.',
                403
            );
        }
    }

    /**
     * Mengharuskan pengguna merupakan superadmin.
     *
     * @throws RuntimeException
     */
    public function requireSuperadmin(int $userId): void
    {
        if (! $this->isSuperadmin($userId)) {
            throw new RuntimeException(
                'Tindakan ini hanya dapat dilakukan oleh superadmin.',
                403
            );
        }
    }

    /**
     * Memeriksa permission yang diberikan langsung kepada pengguna,
     * tidak termasuk permission dari grup.
     */
    public function hasDirectPermission(
        int $userId,
        string $permission
    ): bool {
        $permission = trim($permission);

        if ($permission === '' || ! $this->isActiveUser($userId)) {
            return false;
        }

        $rows = $this->db->table('auth_permissions_users')
            ->select('permission')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        $permissionNames = array_values(array_unique(
            array_filter(
                array_map(
                    static fn(array $row): string =>
                    trim((string) ($row['permission'] ?? '')),
                    $rows
                ),
                static fn(string $name): bool => $name !== ''
            )
        ));

        return $permissionNames !== []
            && PermissionMatcher::matches(
                $permission,
                $permissionNames
            );
    }


    /**
     * Memeriksa permission pengguna berdasarkan ID auth_permissions.
     */
    public function hasPermissionId(int $userId, int $permissionId): bool
    {
        // if ($userId <= 0 || $permissionId <= 0 || ! $this->isActiveUser($userId)) {
        if ($userId <= 0 || $permissionId === '' || ! $this->isActiveUser($userId)) {
            return false;
        }

        $row = $this->db->table('auth_permissions')
            ->select('name')
            ->where('name', $permissionId)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return false;
        }

        return $this->hasPermission($userId, (string) $row['name']);
    }

    /**
     * Memeriksa permission berdasarkan ID auth_permissions.
     */
    public function hasPermissionIds(int $userId, int $permissionId): bool
    {
        if ($permissionId === '' || ! $this->isActiveUser($userId)) {
            return false;
        }

        $row = $this->db->table('auth_permissions')
            ->select('name')
            ->where('name', $permissionId)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return false;
        }

        return $this->hasPermission(
            $userId,
            (string) $row['name']
        );
    }

    /**
     * Memeriksa apakah pengguna memenuhi seluruh permission yang diwajibkan.
     *
     * @param list<int> $permissionIds
     */
    public function hasAllPermissionIds(
        int $userId,
        array $permissionIds
    ): bool {
        if (! $this->isActiveUser($userId)) {
            return false;
        }

        $permissionIds = array_values(array_unique($permissionIds));

        if ($permissionIds === []) {
            return false;
        }

        foreach ($permissionIds as $permissionId) {
            if (! is_int($permissionId) || $permissionId <= 0) {
                return false;
            }

            if (! $this->hasPermissionId($userId, $permissionId)) {
                return false;
            }
        }

        return true;
    }
}
