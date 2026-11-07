<?php

declare(strict_types=1);

namespace Modules\Rbac\Services;

use Modules\Rbac\Repositories\PermissionRepository;
use RuntimeException;

class PermissionService
{
    protected PermissionRepository $repository;

    public function __construct(
        ?PermissionRepository $repository = null
    ) {
        $this->repository = $repository ?? new PermissionRepository();
    }

    /**
     * Daftar permission dengan pagination dan pencarian.
     */
    public function getPaginated(
        int $page = 1,
        int $perPage = 20,
        string $search = ''
    ): array {
        return $this->repository->getPaginated(
            max(1, $page),
            max(1, min($perPage, 100)),
            trim($search)
        );
    }

    /**
     * Ambil seluruh permission.
     */
    public function getAll(): array
    {
        return $this->repository->getAll();
    }

    /**
     * Ambil seluruh nama permission.
     */
    public function getAllNames(): array
    {
        return $this->repository->getAllNames();
    }

    /**
     * Detail permission berdasarkan ID.
     */
    public function find(int $id): array
    {
        $permission = $this->repository->findById($id);

        if ($permission === null) {
            throw new RuntimeException(
                'Permission tidak ditemukan.'
            );
        }

        return $permission;
    }

    /**
     * Buat permission baru.
     */
    public function create(array $data): int
    {
        $validated = $this->validateData($data);

        if ($this->repository->existsByName($validated['name'])) {
            throw new RuntimeException(
                'Nama permission sudah digunakan.'
            );
        }

        return $this->repository->create($validated);
    }

    /**
     * Perbarui deskripsi permission.
     *
     * Nama permission tidak dapat diubah melalui edit biasa.
     */
    public function update(int $id, array $data): bool
    {
        $current = $this->find($id);

        if (
            array_key_exists('name', $data)
            && trim((string) $data['name']) !== $current['name']
        ) {
            throw new RuntimeException(
                'Nama permission tidak dapat diubah melalui fitur edit.'
            );
        }

        $description = array_key_exists('description', $data)
            ? $data['description']
            : $current['description'];

        if ($description !== null && ! is_string($description)) {
            throw new RuntimeException(
                'Deskripsi permission harus berupa teks.'
            );
        }

        $description = $description === null
            ? null
            : trim($description);

        if (
            $description !== null
            && mb_strlen($description) > 255
        ) {
            throw new RuntimeException(
                'Deskripsi maksimal 255 karakter.'
            );
        }

        return $this->repository->update($id, [
            'name'        => $current['name'],
            'description' => $description === '' ? null : $description,
        ]);
    }

    /**
     * Hapus permission jika tidak sedang digunakan.
     */
    public function delete(int $id): bool
    {
        $this->find($id);

        return $this->repository->delete($id);
    }

    /**
     * Validasi data permission.
     */
    protected function validateData(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $description = $data['description'] ?? null;

        if ($name === '') {
            throw new RuntimeException(
                'Nama permission wajib diisi.'
            );
        }

        if (mb_strlen($name) > 150) {
            throw new RuntimeException(
                'Nama permission maksimal 150 karakter.'
            );
        }

        /*
         * Format:
         * backend.navigation.view
         * routeregistry.scan
         * inventory.stock.update
         *
         * Setiap segmen diawali huruf kecil.
         */
        if (! preg_match(
            '/^[a-z][a-z0-9_-]*(?:\.[a-z][a-z0-9_-]*)+$/',
            $name
        )) {
            throw new RuntimeException(
                'Format permission tidak valid. '
                    . 'Gunakan format seperti backend.navigation.view.'
            );
        }

        if ($description !== null && ! is_string($description)) {
            throw new RuntimeException(
                'Deskripsi permission harus berupa teks.'
            );
        }

        if (
            $description !== null
            && mb_strlen(trim($description)) > 255
        ) {
            throw new RuntimeException(
                'Deskripsi maksimal 255 karakter.'
            );
        }

        return [
            'name'        => $name,
            'description' => $description === null
                ? null
                : (trim($description) === ''
                    ? null
                    : trim($description)),
        ];
    }
}
