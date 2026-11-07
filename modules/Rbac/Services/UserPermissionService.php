<?php

declare(strict_types=1);

namespace Modules\Rbac\Services;

use DomainException;
use Modules\Rbac\Repositories\UserPermissionRepository;
use Throwable;

class UserPermissionService
{
    private UserPermissionRepository $repository;

    public function __construct(
        ?UserPermissionRepository $repository = null
    ) {
        $this->repository = $repository
            ?? new UserPermissionRepository();
    }

    public function getOverview(?int $userId = null): array
    {
        $users = $this->repository->getUsers();
        $permissions = $this->repository->getPermissions();

        if ($users === []) {
            if ($userId !== null) {
                throw new DomainException('User tidak ditemukan.');
            }

            return [
                'users' => [],
                'selectedUser' => null,
                'permissions' => [],
            ];
        }

        $userIds = array_map(
            static fn(array $user): int => (int) $user['id'],
            $users
        );

        if ($userId !== null && ! in_array($userId, $userIds, true)) {
            throw new DomainException('User tidak ditemukan.');
        }

        $userId ??= $userIds[0];

        $selectedUser = $this->repository->findUser($userId);

        if ($selectedUser === null) {
            throw new DomainException('User tidak ditemukan.');
        }

        $assignedNames = array_fill_keys(
            $this->repository->getPermissionNamesForUser($userId),
            true
        );

        $items = [];

        foreach ($permissions as $permission) {
            $name = (string) $permission['name'];

            $items[] = [
                'id' => (int) $permission['id'],
                'name' => $name,
                'description' => $permission['description'] ?? '',
                'assigned' => isset($assignedNames[$name]),
            ];
        }

        return [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'permissions' => $items,
        ];
    }

    public function save(int $userId, array $permissionNames): void
    {
        if ($userId < 1) {
            throw new DomainException('User tidak valid.');
        }

        if ($this->repository->findUser($userId) === null) {
            throw new DomainException('User tidak ditemukan.');
        }

        $permissionNames = array_values(array_unique(array_map(
            static function ($name): string {
                if (! is_string($name) || trim($name) === '') {
                    throw new DomainException(
                        'Nama permission tidak valid.'
                    );
                }

                return trim($name);
            },
            $permissionNames
        )));

        $validNames = $this->repository->findPermissionNames(
            $permissionNames
        );

        if (count($validNames) !== count($permissionNames)) {
            throw new DomainException(
                'Terdapat permission yang tidak terdaftar.'
            );
        }

        $this->repository->beginTransaction();

        try {
            if (! $this->repository->replaceUserPermissions(
                $userId,
                $permissionNames
            )) {
                throw new DomainException(
                    'Gagal memperbarui permission user.'
                );
            }

            if (! $this->repository->transactionStatus()) {
                throw new DomainException(
                    'Transaksi penyimpanan permission gagal.'
                );
            }

            if (! $this->repository->commitTransaction()) {
                throw new DomainException(
                    'Gagal menyelesaikan transaksi permission.'
                );
            }
        } catch (Throwable $e) {
            $this->repository->rollbackTransaction();
            throw $e;
        }
    }
}
