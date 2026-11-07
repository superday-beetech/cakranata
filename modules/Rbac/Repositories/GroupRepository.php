<?php

declare(strict_types=1);

namespace Modules\Rbac\Repositories;

use Modules\Rbac\Models\AuthGroupModel;

class GroupRepository
{
    public function __construct(
        private ?AuthGroupModel $model = null
    ) {
        $this->model ??= new AuthGroupModel();
    }

    /**
     * Ambil seluruh grup.
     */
    public function getAll(): array
    {
        return $this->model
            ->select('id, name, description')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Ambil grup berdasarkan ID.
     */
    public function findById(int $id): ?array
    {
        $row = $this->model->find($id);

        return $row ?: null;
    }

    /**
     * Ambil grup berdasarkan nama.
     */
    public function findByName(string $name): ?array
    {
        $row = $this->model
            ->where('name', $name)
            ->first();

        return $row ?: null;
    }

    /**
     * Periksa apakah nama grup sudah digunakan.
     */
    public function nameExists(
        string $name,
        ?int $exceptId = null
    ): bool {
        $builder = $this->model
            ->builder()
            ->where('name', $name);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Buat grup baru.
     */
    public function create(array $data): int
    {
        if (! $this->model->insert($data)) {
            throw new \RuntimeException(
                'Gagal membuat grup: ' .
                    json_encode($this->model->errors())
            );
        }

        return (int) $this->model->getInsertID();
    }

    /**
     * Perbarui grup.
     */
    public function update(int $id, array $data): bool
    {
        return $this->model->update($id, $data);
    }

    /**
     * Hapus grup berdasarkan ID.
     */
    public function delete(int $id): bool
    {
        return $this->model->delete($id);
    }
}
