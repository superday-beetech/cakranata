<?php

declare(strict_types=1);

namespace Modules\Rbac\Models;

use CodeIgniter\Model;
use RuntimeException;
use InvalidArgumentException;

class AuthPermissionModel extends Model
{
    protected $table         = 'auth_permissions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $protectFields = true;

    protected $allowedFields = [
        'name',
        'description',
    ];

    protected $useTimestamps = false;

    /**
     * Ambil seluruh nama permission.
     */
    public function getPermissionNames(): array
    {
        $rows = $this->builder()
            ->select('name')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return array_column($rows, 'name');
    }

    /**
     * Cari nama permission yang sudah terdaftar.
     */
    public function findPermissionNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $rows = $this->builder()
            ->select('name')
            ->whereIn('name', $names)
            ->get()
            ->getResultArray();

        return array_column($rows, 'name');
    }

    /**
     * Tambahkan nama permission.
     */
    public function insertPermissionNames(array $names): int
    {
        $inserted = 0;

        foreach (array_unique($names) as $name) {
            $name = trim((string) $name);

            if ($name === '') {
                throw new InvalidArgumentException(
                    'Nama permission tidak boleh kosong.'
                );
            }

            if ($this->insert([
                'name'        => $name,
                'description' => null,
            ]) === false) {
                throw new RuntimeException(
                    'Gagal menambahkan permission: ' . $name
                );
            }

            $inserted++;
        }

        return $inserted;
    }

    /**
     * Tambahkan permission hasil pemindaian Route Registry.
     *
     * permission: nama permission.
     * endpoint: informasi endpoint yang ditemukan.
     */
    public function insertPermissions(array $permissions): int
    {
        $inserted = 0;

        foreach ($permissions as $item) {
            $name = trim((string) ($item['permission'] ?? ''));
            $endpoint = trim((string) ($item['endpoint'] ?? ''));

            if ($name === '') {
                throw new InvalidArgumentException(
                    'Nama permission tidak boleh kosong.'
                );
            }

            if ($this->insert([
                'name'        => $name,
                'description' => $endpoint !== '' ? $endpoint : null,
            ]) === false) {
                throw new RuntimeException(
                    'Gagal menambahkan permission: ' . $name
                );
            }

            $inserted++;
        }

        return $inserted;
    }

    /**
     * Ambil seluruh data permission.
     */
    public function getAllPermissions(): array
    {
        return $this->builder()
            ->select('id, name, description')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Cari ID permission yang tersedia.
     */
    public function findPermissionIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn(int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $rows = $this->builder()
            ->select('id')
            ->whereIn('id', $ids)
            ->get()
            ->getResultArray();

        return array_map(
            'intval',
            array_column($rows, 'id')
        );
    }
}
