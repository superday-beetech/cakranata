<?php

declare(strict_types=1);

namespace Modules\Rbac\Repositories;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Modules\Rbac\Models\PermissionModel;
use RuntimeException;
use Throwable;

class PermissionRepository
{
    protected BaseConnection $db;
    protected PermissionModel $model;

    public function __construct(
        ?BaseConnection $db = null,
        ?PermissionModel $model = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->model = $model ?? new PermissionModel($this->db);
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
    /**
     * Ambil permission berdasarkan ID.
     */
    public function findById(int $id): ?array
    {
        $result = $this->model->find($id);

        return is_array($result) ? $result : null;
    }

    /**
     * Periksa keunikan nama permission.
     */
    public function existsByName(
        string $name,
        ?int $exceptId = null
    ): bool {
        $builder = $this->db->table('auth_permissions')
            ->where('name', $name);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Tambah permission.
     */
    public function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException(
                'Nama permission wajib diisi.'
            );
        }

        if ($this->existsByName($name)) {
            throw new RuntimeException(
                'Nama permission sudah digunakan.'
            );
        }

        if (! $this->model->insert([
            'name'        => $name,
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
     * Ubah permission.
     */
    public function update(int $id, array $data): bool
    {
        if ($this->findById($id) === null) {
            throw new RuntimeException(
                'Permission tidak ditemukan.'
            );
        }

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException(
                'Nama permission wajib diisi.'
            );
        }

        if ($this->existsByName($name, $id)) {
            throw new RuntimeException(
                'Nama permission sudah digunakan.'
            );
        }

        if (! $this->model->update($id, [
            'name'        => $name,
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
     * Hapus hanya jika tidak memiliki relasi grup atau pengguna.
     */
    public function delete(int $id): bool
    {
        $this->db->transBegin();

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

            $groupUsage = $this->db->table('auth_groups_permissions')
                ->where('permission_id', $id)
                ->countAllResults();

            $userUsage = $this->db->table('auth_permissions_users')
                ->where('permission', $permission['name'])
                ->countAllResults();

            if ($groupUsage > 0 || $userUsage > 0) {
                throw new RuntimeException(
                    'Permission tidak dapat dihapus karena masih digunakan '
                        . 'oleh grup atau pengguna. Cabut relasinya terlebih dahulu.'
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

            $this->db->transCommit();

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
