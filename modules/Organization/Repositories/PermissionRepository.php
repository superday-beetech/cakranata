<?php

declare(strict_types=1);

namespace Modules\Rbac\Repositories;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Modules\Rbac\Repositories\Interfaces\PermissionRepositoryInterface;
use RuntimeException;
use Throwable;

class PermissionRepository implements PermissionRepositoryInterface
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function findAll(): array
    {
        return $this->db->table('auth_permissions')
            ->select('id, name, description, created_at, updated_at')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->table('auth_permissions')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $row = $this->db->table('auth_permissions')
            ->where('name', trim($name))
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Nama permission wajib diisi.', 422);
        }

        if ($this->findByName($name) !== null) {
            throw new RuntimeException('Permission sudah terdaftar.', 409);
        }

        $now = date('Y-m-d H:i:s');

        $insert = [
            'name'        => $name,
            'description' => trim((string) ($data['description'] ?? '')),
            'created_at'  => $now,
            'updated_at'  => $now,
        ];

        if (! $this->db->table('auth_permissions')->insert($insert)) {
            throw new RuntimeException('Gagal menyimpan permission.', 500);
        }

        return (int) $this->db->insertID();
    }

    public function update(int $id, array $data): bool
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            throw new RuntimeException('Permission tidak ditemukan.', 404);
        }

        // Nama permission menjadi identifier yang stabil.
        if (
            array_key_exists('name', $data)
            && trim((string) $data['name']) !== $existing['name']
        ) {
            throw new RuntimeException(
                'Nama permission tidak dapat diubah. Buat permission baru jika diperlukan.',
                422
            );
        }

        $update = [
            'description' => trim((string) ($data['description'] ?? '')),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if (! $this->db->table('auth_permissions')
            ->where('id', $id)
            ->update($update)
        ) {
            throw new RuntimeException('Gagal memperbarui permission.', 500);
        }

        return true;
    }

    public function delete(int $id): bool
    {
        $permission = $this->findById($id);

        if ($permission === null) {
            throw new RuntimeException('Permission tidak ditemukan.', 404);
        }

        $name = (string) $permission['name'];

        if ($this->getPermissionGroups($name) !== []) {
            throw new RuntimeException(
                'Permission masih digunakan oleh satu atau lebih group.',
                409
            );
        }

        if ($this->getPermissionUsers($name) !== []) {
            throw new RuntimeException(
                'Permission masih diberikan langsung kepada user.',
                409
            );
        }

        $deleted = $this->db->table('auth_permissions')
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            throw new RuntimeException('Gagal menghapus permission.', 500);
        }

        return true;
    }

    public function getPermissionGroups(string $permission): array
    {
        $rows = $this->db->table('auth_groups_permissions')
            ->select('group')
            ->where('permission', trim($permission))
            ->orderBy('group', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_column($rows, 'group')));
    }

    public function getPermissionUsers(string $permission): array
    {
        $rows = $this->db->table('auth_permissions_users')
            ->select('user_id')
            ->where('permission', trim($permission))
            ->orderBy('user_id', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_map(
            'intval',
            array_column($rows, 'user_id')
        )));
    }
}
