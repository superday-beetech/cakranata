<?php

declare(strict_types=1);

namespace Modules\Rbac\Repositories;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Modules\Rbac\Models\AuthPermissionModel;
use RuntimeException;
use Throwable;

class PermissionRepository
{
    private AuthPermissionModel $model;
    private BaseConnection $db;


    public function __construct(
        ?AuthPermissionModel $model = null,
        ?BaseConnection $db = null
    ) {
        $this->db = $db ?? Database::connect();

        $this->model = $model ?? new AuthPermissionModel(
            $this->db
        );
    }


    /**
     * Daftar permission dengan pencarian dan pagination.
     */
    public function getPaginated(
        int $page = 1,
        int $perPage = 20,
        string $search = ''
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;
        $search = trim($search);

        $countQuery = $this->db->table('auth_permissions');

        if ($search !== '') {
            $countQuery->groupStart()
                ->like('name', $search)
                ->orLike('description', $search)
                ->groupEnd();
        }

        $total = $countQuery->countAllResults();

        $query = $this->db->table('auth_permissions');

        if ($search !== '') {
            $query->groupStart()
                ->like('name', $search)
                ->orLike('description', $search)
                ->groupEnd();
        }

        $items = $query
            ->select('id, name, description')
            ->orderBy('name', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public function getAll(): array
    {
        return $this->model->getAllPermissions();
    }

    public function getAllNames(): array
    {
        return $this->model->getPermissionNames();
    }

    public function findExistingNames(array $names): array
    {
        return $this->model->findPermissionNames($names);
    }

    public function findExistingIds(array $ids): array
    {
        return $this->model->findPermissionIds($ids);
    }

    public function insertNames(array $names): int
    {
        if ($names === []) {
            return 0;
        }

        return $this->model->insertPermissionNames($names);
    }

    public function insertPermissions(array $permissions): int
    {
        if ($permissions === []) {
            return 0;
        }

        return $this->model->insertPermissions($permissions);
    }

    public function findById(int $id): ?array
    {
        $row = $this->model->find($id);

        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        return $this->model
            ->where('name', $name)
            ->first() ?: null;
    }

    public function existsByName(
        string $name,
        ?int $exceptId = null
    ): bool {
        $builder = $this->db
            ->table('auth_permissions')
            ->where('name', $name);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Tambah permission.
     *
     * Validasi bisnis nantinya dipusatkan di PermissionService.
     */
    public function create(array $data): int
    {
        if (! $this->model->insert([
            'name'        => trim((string) ($data['name'] ?? '')),
            'description' => $this->normalizeDescription(
                $data['description'] ?? null
            ),
        ])) {
            throw new RuntimeException(
                implode(' ', $this->model->errors())
                    ?: 'Gagal menambahkan permission.'
            );
        }

        return (int) $this->model->getInsertID();
    }

    /**
     * Perbarui permission berdasarkan ID.
     */
    public function update(int $id, array $data): bool
    {
        if (! $this->model->update($id, [
            'name'        => trim((string) ($data['name'] ?? '')),
            'description' => $this->normalizeDescription(
                $data['description'] ?? null
            ),
        ])) {
            throw new RuntimeException(
                implode(' ', $this->model->errors())
                    ?: 'Gagal memperbarui permission.'
            );
        }

        return true;
    }

    /**
     * Hapus permission hanya jika tidak memiliki relasi.
     */
    public function delete(int $id): bool
    {
        if (! $this->db->transBegin()) {
            throw new RuntimeException(
                'Gagal memulai transaksi penghapusan permission.'
            );
        }

        try {
            $permission = $this->db->query(
                'SELECT id, name
                 FROM auth_permissions
                 WHERE id = ?
                 FOR UPDATE',
                [$id]
            )->getRowArray();

            if ($permission === null) {
                throw new RuntimeException(
                    'Permission tidak ditemukan.'
                );
            }

            $groupUsage = $this->db
                ->table('auth_groups_permissions')
                ->where('permission_id', $id)
                ->countAllResults();

            $userUsage = $this->db
                ->table('auth_permissions_users')
                ->where('permission', $permission['name'])
                ->countAllResults();

            if ($groupUsage > 0 || $userUsage > 0) {
                throw new RuntimeException(
                    'Permission masih digunakan oleh grup atau pengguna. '
                        . 'Cabut relasinya terlebih dahulu.'
                );
            }

            if (! $this->model->delete($id)) {
                throw new RuntimeException(
                    'Gagal menghapus permission.'
                );
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Transaksi penghapusan permission gagal.'
                );
            }

            if (! $this->db->transCommit()) {
                throw new RuntimeException(
                    'Gagal melakukan commit penghapusan permission.'
                );
            }

            return true;
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }

    protected function normalizeDescription(
        mixed $description
    ): ?string {
        if ($description === null) {
            return null;
        }

        $description = trim((string) $description);

        return $description === '' ? null : $description;
    }
}
