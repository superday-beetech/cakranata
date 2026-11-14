<?php

declare(strict_types=1);

namespace Modules\Rbac\Repositories\Interfaces;

interface PermissionRepositoryInterface
{
    public function findAll(): array;

    public function findById(int $id): ?array;

    public function findByName(string $name): ?array;

    public function create(array $data): int;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    public function getPermissionGroups(string $permission): array;

    public function getPermissionUsers(string $permission): array;
}
