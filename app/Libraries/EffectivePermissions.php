<?php

declare(strict_types=1);

/**
 * Simpan di: app/Libraries/EffectivePermissions.php
 *
 * Pemakaian di class AccessControl:
 *
 *     use App\Libraries\EffectivePermissions;
 *
 *     class AccessControl
 *     {
 *         use EffectivePermissions;   // hapus getEffectivePermissions() lama
 *         // $this->db harus berupa CodeIgniter\Database\BaseConnection
 *     }
 *
 * ASUMSI SKEMA (ubah tiga konstanta di bawah bila namanya berbeda)
 * ----------------------------------------------------------------
 *  auth_groups_users      (user_id, group)        -> grup/role milik user (tabel Shield)
 *  auth_group_permissions (group, permission)     -> permission per grup/role
 *  auth_permissions_users (user_id, permission)   -> permission langsung per user
 *
 * Hasil = gabungan (union) permission langsung + permission dari semua grup user,
 * sudah di-trim, lowercase, divalidasi, dan tanpa duplikat.
 *
 * KEPUTUSAN DESAIN
 *  - Hanya cache per instance (per request). TIDAK ada cache lintas request,
 *    sehingga perubahan role/permission berlaku pada request berikutnya.
 *    Dua query terindeks per request jauh lebih murah daripada risiko akses basi.
 *  - Error database dilempar ke pemanggil (AccessChecker menangkapnya dan menolak
 *    dengan 500); hasil gagal tidak pernah di-cache.
 *  - Nilai tabel yang tidak valid (mis. "*", "a.*.b") dibuang dan dicatat di log,
 *    bukan ditafsirkan longgar.
 *  - Catatan worker long-lived (Swoole/RoadRunner): panggil forgetEffectivePermissions()
 *    di awal tiap request.
 */

namespace App\Libraries;

trait EffectivePermissions
{
    private const TABLE_USER_GROUPS       = 'auth_groups_users';
    private const TABLE_GROUP_PERMISSIONS = 'auth_group_permissions';
    private const TABLE_USER_PERMISSIONS  = 'auth_permissions_users';

    /** @var array<int, list<string>> */
    private array $effectivePermissionsCache = [];

    /**
     * @return list<string>
     */
    public function getEffectivePermissions(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        if (isset($this->effectivePermissionsCache[$userId])) {
            return $this->effectivePermissionsCache[$userId];
        }

        // `group` adalah reserved word di MySQL/MariaDB.
        $group = $this->db->escapeIdentifiers('group');

        $direct = $this->db->table(self::TABLE_USER_PERMISSIONS)
            ->select('permission')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        $viaGroups = $this->db->table(self::TABLE_GROUP_PERMISSIONS . ' gp')
            ->select('gp.permission')
            ->join(self::TABLE_USER_GROUPS . ' gu', "gu.{$group} = gp.{$group}")
            ->where('gu.user_id', $userId)
            ->get()
            ->getResultArray();

        $effective = [];

        foreach (
            array_merge(
                array_column($direct, 'permission'),
                array_column($viaGroups, 'permission')
            ) as $permission
        ) {
            if (! is_string($permission)) {
                continue;
            }

            $permission = PermissionMatcher::normalize($permission);

            if (! PermissionMatcher::isValidGrant($permission)) {
                log_message('warning', 'Permission tidak valid diabaikan (user {userId}): {permission}', [
                    'userId'     => $userId,
                    'permission' => substr($permission, 0, 100),
                ]);

                continue;
            }

            $effective[$permission] = true;
        }

        return $this->effectivePermissionsCache[$userId] = array_keys($effective);
    }

    public function forgetEffectivePermissions(?int $userId = null): void
    {
        if ($userId === null) {
            $this->effectivePermissionsCache = [];

            return;
        }

        unset($this->effectivePermissionsCache[$userId]);
    }
}
