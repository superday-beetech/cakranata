<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Authorization\PermissionMatcher;
use Config\AuthPolicy;
use Config\Database;
use RuntimeException;

class AccessControlService
{
    private const SUPERADMIN_GROUP = 'superadmin';

    protected BaseConnection $db;

    /**
     * Cache hanya selama instance service hidup.
     */
    private array $activeUserCache = [];
    private array $superadminCache = [];
    private array $effectivePermissionsCache = [];
    private AuthPolicy $policy;
    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->policy = $policy ?? config(AuthPolicy::class);
    }

    /**
     * Memastikan pengguna ada dan belum dihapus.
     */
    public function isActiveUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (array_key_exists($userId, $this->activeUserCache)) {
            return $this->activeUserCache[$userId];
        }

        $row = $this->db->table('users')
            ->select('id, status, active')
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        $isActive = $row !== null
            && ($row['status'] ?? null) !== 'banned';
        // Aktifkan baris di bawah bila semua akun memang melewati aktivasi Shield.
        // Akun hasil seeder dengan active=0 akan terkunci jika syarat ini dipasang.
        // && (int) ($row['active'] ?? 0) === 1;

        return $this->activeUserCache[$userId] = $isActive;
    }
    /* public function isActiveUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (array_key_exists($userId, $this->activeUserCache)) {
            return $this->activeUserCache[$userId];
        }

        return $this->activeUserCache[$userId] =
            $this->db->table('users')
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->countAllResults() > 0;
    } */

    /**
     * Memeriksa keanggotaan grup superadmin.
     */
    public function isSuperadmin(int $userId): bool
    {
        if ($userId <= 0 || ! $this->isActiveUser($userId)) {
            return false;
        }

        if (array_key_exists($userId, $this->superadminCache)) {
            return $this->superadminCache[$userId];
        }

        return $this->superadminCache[$userId] =
            $this->db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->where('group', $this->policy->bypassGroups)
            ->countAllResults() > 0;
    }

    /**
     * Mengambil gabungan permission langsung dan permission grup.
     *
     * Permission dikembalikan sebagai nama agar kompatibel
     * dengan PermissionMatcher.
     *
     * User tanpa permission mengembalikan array kosong,
     * bukan exception.
     *
     * @return list<string>
     */
    public function getEffectivePermissions(int $userId): array
    {
        if ($userId <= 0 || ! $this->isActiveUser($userId)) {
            return [];
        }

        if (array_key_exists($userId, $this->effectivePermissionsCache)) {
            return $this->effectivePermissionsCache[$userId];
        }

        // Permission yang diberikan langsung kepada user.
        $directRows = $this->db->table('auth_permissions_users')
            ->select('permission')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        // Permission yang diperoleh melalui grup.
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

        $result = array_values($permissions);

        if ($result === []) {
            log_message(
                'debug',
                'User ID {userId} tidak memiliki permission langsung maupun dari grup.',
                ['userId' => $userId]
            );
        }

        return $this->effectivePermissionsCache[$userId] = $result;
    }

    /**
     * Semua permission yang diwajibkan harus terpenuhi.
     */


    public function hasAllPermissions(
        int $userId,
        array $requiredPermissions
    ): bool {
        if (
            $userId <= 0
            || ! $this->isActiveUser($userId)
            || $requiredPermissions === []
        ) {
            return false;
        }

        // Validasi seluruh permission sebelum bypass superadmin.
        foreach ($requiredPermissions as $permission) {
            if (
                ! is_string($permission)
                || trim($permission) === ''
            ) {
                return false;
            }
        }

        // Superadmin melewati grant permission.
        if ($this->isSuperadmin($userId)) {
            return true;
        }

        $effectivePermissions = $this->getEffectivePermissions($userId);

        if ($effectivePermissions === []) {
            return false;
        }

        // Semua permission wajib terpenuhi.
        foreach ($requiredPermissions as $permission) {
            if (! PermissionMatcher::matches(
                trim($permission),
                $effectivePermissions
            )) {
                return false;
            }
        }

        return true;
    }


    /**
     * Memeriksa permission pengguna, termasuk pola wildcard.
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        $permission = trim($permission);

        if ($permission === '') {
            return false;
        }

        return $this->hasAllPermissions($userId, [$permission]);
    }

    /**
     * Memeriksa permission berdasarkan auth_permissions.id.
     */
    public function hasPermissionId(
        int $userId,
        int $permissionId
    ): bool {
        if (
            $userId <= 0
            || $permissionId <= 0
            || ! $this->isActiveUser($userId)
        ) {
            return false;
        }

        $row = $this->db->table('auth_permissions')
            ->select('name')
            ->where('id', $permissionId)
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
     * tidak termasuk permission yang berasal dari grup.
     */
    public function hasDirectPermission(
        int $userId,
        string $permission
    ): bool {
        $permission = trim($permission);

        if (
            $userId <= 0
            || $permission === ''
            || ! $this->isActiveUser($userId)
        ) {
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

    public function clearCache(?int $userId = null): void
    {
        if ($userId === null) {
            $this->activeUserCache = [];
            $this->superadminCache = [];
            $this->effectivePermissionsCache = [];

            return;
        }

        unset(
            $this->activeUserCache[$userId],
            $this->superadminCache[$userId],
            $this->effectivePermissionsCache[$userId]
        );
    }
}
