<?php

namespace Modules\Rbac\Repositories;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Modules\Rbac\Repositories\Interfaces\GroupRepositoryInterface;
use RuntimeException;
use Throwable;

class GroupRepository implements GroupRepositoryInterface
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }
    public function findAll(): array
    {
        $groups = $this->findAllGroup();
        $counts = $this->getPermissionCounts();

        foreach ($groups as &$group) {
            $group['permission_count'] =
                $counts[$group['name']] ?? 0;
        }

        unset($group);

        return $groups;
    }

    public function getPermissionCounts(): array
    {
        $rows = $this->db->table('auth_groups_permissions')
            ->select('`group`, COUNT(*) AS permission_count', false)
            ->groupBy('group')
            ->get()
            ->getResultArray();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['group']] = (int) $row['permission_count'];
        }

        return $counts;
    }

    public function findAllGroup(): array
    {
        return $this->db->table('auth_groups')
            ->select('id, name, description, created_at, updated_at')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->table('auth_groups')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $row = $this->db->table('auth_groups')
            ->where('name', $name)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('auth_groups')->insert([
            'name'        => trim($data['name']),
            'description' => $data['description'] ?? null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return (int) $this->db->insertID();
    }

    public function update(int $id, array $data): bool
    {
        $payload = [
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (array_key_exists('name', $data)) {
            $payload['name'] = trim($data['name']);
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }

        return $this->db->table('auth_groups')
            ->where('id', $id)
            ->update($payload);
    }

    public function delete(int $id): bool
    {
        $group = $this->findById($id);

        if ($group === null) {
            return false;
        }

        $this->db->transBegin();

        try {
            $name = $group['name'];

            $this->db->table('auth_groups_permissions')
                ->where('group', $name)
                ->delete();

            $this->db->table('auth_groups_users')
                ->where('group', $name)
                ->delete();

            $this->db->table('auth_groups')
                ->where('id', $id)
                ->delete();

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menghapus group beserta relasinya.'
                );
            }

            $this->db->transCommit();

            return true;
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }

    public function getGroupPermissions(string $group): array
    {
        $rows = $this->db->table('auth_groups_permissions')
            ->select('permission')
            ->where('group', $group)
            ->orderBy('permission', 'ASC')
            ->get()
            ->getResultArray();

        return array_column($rows, 'permission');
    }

    /**
     * Mengganti seluruh permission milik group secara atomik.
     *
     * @param list<string> $permissions
     */
    public function syncPermissions(string $group, array $permissions): bool
    {
        $group = trim($group);

        if ($group === '') {
            throw new \RuntimeException('Nama group wajib diisi.', 422);
        }

        $groupExists = $this->db->table('auth_groups')
            ->where('name', $group)
            ->countAllResults() > 0;

        if (! $groupExists) {
            throw new \RuntimeException('Group tidak ditemukan.', 404);
        }

        $normalized = [];

        foreach ($permissions as $permission) {
            if (! is_string($permission)) {
                throw new \RuntimeException(
                    'Daftar permission tidak valid.',
                    422
                );
            }

            $permission = trim($permission);

            if ($permission === '') {
                throw new \RuntimeException(
                    'Nama permission tidak boleh kosong.',
                    422
                );
            }

            $normalized[$permission] = $permission;
        }

        $permissionNames = array_values($normalized);

        // Validasi semua permission sebelum mengubah relasi.
        if ($permissionNames !== []) {
            $rows = $this->db->table('auth_permissions')
                ->select('name')
                ->whereIn('name', $permissionNames)
                ->get()
                ->getResultArray();

            $existingNames = array_column($rows, 'name');
            $unknown = array_values(
                array_diff($permissionNames, $existingNames)
            );

            if ($unknown !== []) {
                throw new \RuntimeException(
                    'Permission tidak terdaftar: ' . implode(', ', $unknown),
                    422
                );
            }
        }

        $this->db->transBegin();

        try {
            $this->db->table('auth_groups_permissions')
                ->where('group', $group)
                ->delete();

            if ($permissionNames !== []) {
                $batch = [];

                foreach ($permissionNames as $permission) {
                    $batch[] = [
                        'group'      => $group,
                        'permission' => $permission,
                    ];
                }

                $this->db->table('auth_groups_permissions')
                    ->insertBatch($batch);
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException(
                    'Gagal menyimpan relasi permission group.',
                    500
                );
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }


    public function syncPermissions222222222222(
        string $group,
        array $permissions
    ): bool {
        $group = trim($group);

        if ($group === '') {
            throw new RuntimeException('Nama group tidak valid.', 400);
        }

        // Pastikan group tersedia.
        if ($this->findByName($group) === null) {
            throw new RuntimeException('Group tidak ditemukan.', 404);
        }

        // Normalisasi daftar permission.
        $permissions = array_values(array_unique(
            array_filter(
                array_map(
                    static fn($permission): string =>
                    trim((string) $permission),
                    $permissions
                ),
                static fn(string $permission): bool =>
                $permission !== ''
            )
        ));

        // Pastikan semua permission terdaftar di Shield.
        if ($permissions !== []) {
            $rows = $this->db->table('auth_permissions')
                ->select('name')
                ->whereIn('name', $permissions)
                ->get()
                ->getResultArray();

            $existing = array_column($rows, 'name');
            $missing = array_values(array_diff($permissions, $existing));

            if ($missing !== []) {
                throw new RuntimeException(
                    'Permission tidak ditemukan: ' . implode(', ', $missing),
                    422
                );
            }
        }

        $this->db->transBegin();

        try {
            $this->db->table('auth_groups_permissions')
                ->where('group', $group)
                ->delete();

            if ($permissions !== []) {
                $insertRows = array_map(
                    static fn(string $permission): array => [
                        'group'      => $group,
                        'permission' => $permission,
                    ],
                    $permissions
                );

                $this->db->table('auth_groups_permissions')
                    ->insertBatch($insertRows);
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menyimpan permission group.'
                );
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }

    public function syncPermissionsOld(
        string $group,
        array $permissions
    ): bool {
        $permissions = array_values(array_unique(
            array_filter(
                array_map(
                    static fn($permission) => trim((string) $permission),
                    $permissions
                ),
                static fn($permission) => $permission !== ''
            )
        ));

        $this->db->transBegin();

        try {
            $this->db->table('auth_groups_permissions')
                ->where('group', $group)
                ->delete();

            foreach ($permissions as $permission) {
                $this->db->table('auth_groups_permissions')->insert([
                    'group'      => $group,
                    'permission' => $permission,
                ]);
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menyimpan permission group.'
                );
            }

            $this->db->transCommit();

            return true;
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }
}
