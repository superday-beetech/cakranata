<?php

namespace Modules\Rbac\Repositories\Interfaces;

interface GroupRepositoryInterface
{
    public function findAll(): array;

    public function findById(int $id): ?array;

    public function findByName(string $name): ?array;

    public function create(array $data): int;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    public function getGroupPermissions(string $group): array;

    public function syncPermissions(
        string $group,
        array $permissions
    ): bool;
}
