<?php

declare(strict_types=1);

namespace Modules\Rbac\Services;

use DomainException;
use Modules\Rbac\Repositories\GroupPermissionRepository;
use RuntimeException;
use Throwable;

class GroupPermissionService
{
    private GroupPermissionRepository $repository;

    public function __construct(
        ?GroupPermissionRepository $repository = null
    ) {
        $this->repository = $repository
            ?? new GroupPermissionRepository();
    }

    /**
     * Ringkasan group dan status permission yang dimiliki.
     */
    public function getOverview(?int $groupId = null): array
    {
        $groups = $this->repository->getGroups();

        if ($groups === []) {
            return [
                'groups' => [],
                'selectedGroup' => null,
                'permissions' => [],
            ];
        }

        $groupIds = array_map(
            static fn(array $group): int => (int) $group['id'],
            $groups
        );

        if (
            $groupId === null
            || ! in_array($groupId, $groupIds, true)
        ) {
            $groupId = $groupIds[0];
        }

        $selectedGroup = $this->repository->findGroup($groupId);

        if ($selectedGroup === null) {
            throw new DomainException('Group tidak ditemukan.');
        }

        $assigned = array_fill_keys(
            $this->repository->getPermissionIds($groupId),
            true
        );

        $permissions = [];

        foreach ($this->repository->getPermissions() as $permission) {
            $id = (int) $permission['id'];

            $permissions[] = [
                'id' => $id,
                'name' => $permission['name'],
                'description' => $permission['description'] ?? '',
                'assigned' => isset($assigned[$id]),
            ];
        }

        return [
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
            'permissions' => $permissions,
        ];
    }

    /**
     * Mengganti seluruh permission milik group.
     */
    public function save(int $groupId, array $permissionIds): void
    {
        if (
            $groupId < 1
            || $this->repository->findGroup($groupId) === null
        ) {
            throw new DomainException('Group tidak ditemukan.');
        }

        // Tolak input tidak valid, bukan membuangnya diam-diam.
        $normalizedIds = [];

        foreach ($permissionIds as $id) {
            if (filter_var($id, FILTER_VALIDATE_INT) === false) {
                throw new DomainException(
                    'Daftar permission mengandung ID yang tidak valid.'
                );
            }

            $id = (int) $id;

            if ($id < 1) {
                throw new DomainException(
                    'ID permission harus berupa bilangan positif.'
                );
            }

            $normalizedIds[] = $id;
        }

        $permissionIds = array_values(
            array_unique($normalizedIds)
        );

        $validIds = $this->repository->findPermissionIds(
            $permissionIds
        );

        if (count($validIds) !== count($permissionIds)) {
            throw new DomainException(
                'Ada permission yang tidak valid. Muat ulang halaman.'
            );
        }

        if (! $this->repository->beginTransaction()) {
            throw new RuntimeException(
                'Gagal memulai transaksi penyimpanan permission.'
            );
        }

        try {
            if (! $this->repository->replaceGroupPermissions(
                $groupId,
                $permissionIds
            )) {
                throw new RuntimeException(
                    'Gagal menyimpan permission group.'
                );
            }

            if (! $this->repository->transactionStatus()) {
                throw new RuntimeException(
                    'Transaksi penyimpanan permission gagal.'
                );
            }

            if (! $this->repository->commitTransaction()) {
                throw new RuntimeException(
                    'Gagal melakukan commit transaksi.'
                );
            }
        } catch (Throwable $e) {
            $this->repository->rollbackTransaction();

            throw $e;
        }
    }
}
