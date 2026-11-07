<?php

declare(strict_types=1);

namespace Modules\Rbac\Services;

use Modules\Rbac\Repositories\GroupRepository;
use RuntimeException;
use Throwable;

class GroupService
{
    private GroupRepository $groups;

    public function __construct(
        ?GroupRepository $groups = null
    ) {
        $this->groups = $groups ?? new GroupRepository();
    }

    /**
     * Ambil seluruh grup.
     */
    public function getAllGroups(): array
    {
        return $this->groups->getAll();
    }

    /**
     * Ambil grup berdasarkan ID.
     */
    public function getGroup(int $id): array
    {
        $group = $this->groups->findById($id);

        if ($group === null) {
            throw new RuntimeException('Grup tidak ditemukan.');
        }

        return $group;
    }

    /**
     * Buat grup baru.
     */
    public function createGroup(array $data): int
    {
        $name = $this->normalizeName($data['name'] ?? '');

        $description = trim(
            (string) ($data['description'] ?? '')
        );

        $this->validateName($name);

        if ($this->groups->nameExists($name)) {
            throw new RuntimeException(
                "Nama grup '{$name}' sudah digunakan."
            );
        }

        return $this->groups->create([
            'name'        => $name,
            'description' => $description !== ''
                ? $description
                : null,
        ]);
    }

    /**
     * Perbarui grup.
     */
    public function updateGroup(int $id, array $data): bool
    {
        $group = $this->getGroup($id);

        $name = $this->normalizeName(
            $data['name'] ?? $group['name']
        );

        $description = array_key_exists('description', $data)
            ? trim((string) ($data['description'] ?? ''))
            : $group['description'];

        $this->validateName($name);

        // Nama grup superadmin tidak boleh diubah.
        if (
            $group['name'] === 'superadmin'
            && $name !== 'superadmin'
        ) {
            throw new RuntimeException(
                'Nama grup superadmin tidak boleh diubah.'
            );
        }

        if ($this->groups->nameExists($name, $id)) {
            throw new RuntimeException(
                "Nama grup '{$name}' sudah digunakan."
            );
        }

        return $this->groups->update($id, [
            'name'        => $name,
            'description' => $description !== ''
                ? $description
                : null,
        ]);
    }

    /**
     * Hapus grup.
     *
     * Penghapusan relasi belum diaktifkan sampai
     * transaksi relasi diimplementasikan.
     */
    public function deleteGroup(int $id): never
    {
        $group = $this->getGroup($id);

        if ($group['name'] === 'superadmin') {
            throw new RuntimeException(
                'Grup superadmin tidak boleh dihapus.'
            );
        }

        throw new RuntimeException(
            'Penghapusan grup belum diaktifkan. '
                . 'Relasi pengguna dan permission harus ditangani '
                . 'secara transaksional terlebih dahulu.'
        );
    }

    private function normalizeName(mixed $name): string
    {
        return strtolower(trim((string) $name));
    }

    private function validateName(string $name): void
    {
        if ($name === '') {
            throw new RuntimeException(
                'Nama grup wajib diisi.'
            );
        }

        if (strlen($name) > 100) {
            throw new RuntimeException(
                'Nama grup maksimal 100 karakter.'
            );
        }

        if (! preg_match('/^[a-z0-9_-]+$/', $name)) {
            throw new RuntimeException(
                'Nama grup hanya boleh berisi huruf kecil, '
                    . 'angka, tanda hubung, dan garis bawah.'
            );
        }
    }
}
