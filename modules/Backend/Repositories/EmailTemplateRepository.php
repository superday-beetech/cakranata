<?php

declare(strict_types=1);

namespace Modules\Backend\Repositories;

use Modules\Backend\Models\EmailTemplateModel;

class EmailTemplateRepository
{
    public function __construct(
        private readonly EmailTemplateModel $model = new EmailTemplateModel()
    ) {}

    /**
     * Cari template berdasarkan kode unik.
     */
    public function findByCode(
        string $code,
        bool $activeOnly = true
    ): ?array {
        $builder = $this->model
            ->where('code', $code)
            ->where('channel', 'email');

        if ($activeOnly) {
            $builder->where('is_active', 1);
        }

        $result = $builder->first();

        return is_array($result) ? $result : null;
    }

    /**
     * Cari template berdasarkan ID.
     */
    public function findById(int $id): ?array
    {
        $result = $this->model->find($id);

        return is_array($result) ? $result : null;
    }

    /**
     * Ambil daftar template dengan filter opsional.
     */
    public function findAll(
        ?string $category = null,
        ?int $active = null
    ): array {
        $builder = $this->model
            ->orderBy('name', 'ASC');

        if ($category !== null && $category !== '') {
            $builder->where('category', $category);
        }

        if ($active !== null) {
            $builder->where('is_active', $active);
        }

        return $builder->findAll();
    }

    /**
     * Buat template baru.
     */
    public function create(array $data): int|false
    {
        $result = $this->model->insert($data);

        if ($result === false) {
            return false;
        }

        return (int) $this->model->getInsertID();
    }

    /**
     * Perbarui template berdasarkan ID.
     */
    public function update(int $id, array $data): bool
    {
        if ($this->model->find($id) === null) {
            return false;
        }

        return $this->model->update($id, $data);
    }

    /**
     * Aktifkan atau nonaktifkan template.
     */
    public function setActive(int $id, bool $active): bool
    {
        return $this->update($id, [
            'is_active' => $active ? 1 : 0,
        ]);
    }

    /**
     * Periksa apakah kode sudah digunakan.
     */
    public function codeExists(
        string $code,
        ?int $excludeId = null
    ): bool {
        $builder = $this->model->builder()
            ->where('code', $code);

        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }
}
