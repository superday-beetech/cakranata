<?php

declare(strict_types=1);

namespace Modules\Rbac\Repositories\Interfaces;

interface UserRbacRepositoryInterface
{
    public function findAll(): array;

    public function findById(int $id): ?array;

    public function updateProfile(int $id, array $data): bool;

    public function softDelete(int $id): bool;

    public function getGroups(int $userId): array;

    public function getPermissions(int $userId): array;

    public function syncGroups(int $userId, array $groups): bool;

    public function syncPermissions(int $userId, array $permissions): bool;

    public function activate(int $id): bool;

    public function paginate(
        int $page,
        int $perPage,
        string $search = '',
        string $sortField = 'id',
        string $sortDirection = 'desc'
    ): array;
}
