<?php

declare(strict_types=1);

namespace Modules\Core\Repositories;

use Modules\Core\Models\StorageFileModel;
use RuntimeException;

final class StorageFileRepository
{
    public function __construct(
        private ?StorageFileModel $model = null
    ) {
        $this->model ??= new StorageFileModel();
    }

    public function create(array $metadata): int|string
    {
        $id = $this->model->insert($metadata, true);

        if (
            $id === false
            || $id === 0
            || $id === '0'
            || $id === ''
        ) {
            throw new RuntimeException(
                'Gagal menyimpan metadata file.'
            );
        }

        return $id;
    }

    public function findActiveById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        $row = $this->model
            ->where('id', $id)
            ->where('status', 'active')
            ->first();

        return is_array($row) ? $row : null;
    }

    public function paginate(
        ?int $organizationId = null,
        int $page = 1,
        int $perPage = 20
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $countModel = new StorageFileModel();
        $countModel->where('status', 'active');

        $listModel = new StorageFileModel();
        $listModel->where('status', 'active');

        if ($organizationId !== null) {
            $countModel->where('organization_id', $organizationId);
            $listModel->where('organization_id', $organizationId);
        }

        $total = $countModel->countAllResults();

        $items = $listModel
            ->orderBy('id', 'DESC')
            ->findAll($perPage, $offset);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function softDelete(int $id): bool
    {
        if ($id < 1 || $this->findActiveById($id) === null) {
            return false;
        }

        return $this->model->delete($id);
    }
}
