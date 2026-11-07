<?php

declare(strict_types=1);

namespace Modules\Rbac\Services;

use DomainException;
use Modules\Rbac\Repositories\UserGroupRepository;
use RuntimeException;
use Throwable;

class UserGroupService
{
    private UserGroupRepository $repository;

    public function __construct(
        ?UserGroupRepository $repository = null
    ) {
        $this->repository = $repository
            ?? new UserGroupRepository();
    }

    /**
     * Ringkasan group dan keanggotaan user.
     */

    public function getOverview(?int $userId = null): array
    {
        $groups = $this->repository->getGroups();
        $users  = $this->repository->getUsers();

        if ($users === []) {
            if ($userId !== null) {
                throw new DomainException('User tidak ditemukan.');
            }

            return [
                'users'        => [],
                'groups'       => $groups,
                'selectedUser' => null,
                'userGroups'   => [],
            ];
        }

        $userIds = array_map(
            static fn($user): int => (int) (
                is_array($user) ? $user['id'] : $user->id
            ),
            $users
        );

        // Jika ID diminta secara eksplisit, jangan mengganti user otomatis.
        if ($userId !== null && ! in_array($userId, $userIds, true)) {
            throw new DomainException('User tidak ditemukan.');
        }

        // Tanpa ID, pilih user pertama sebagai pilihan awal halaman.
        if ($userId === null) {
            $userId = $userIds[0];
        }

        $selectedUser = $this->repository->findUser($userId);
        if ($selectedUser !== null && ! is_array($selectedUser)) {
            $selectedUser = [
                'id'       => (int) $selectedUser->id,
                'username' => (string) $selectedUser->username,
            ];
        }
        if ($selectedUser === null) {
            throw new DomainException('User tidak ditemukan.');
        }

        $assignedNames = array_fill_keys(
            $this->repository->getGroupNamesForUser($userId),
            true
        );

        $userGroups = [];

        foreach ($groups as $group) {
            $name = (string) $group['name'];

            $userGroups[] = [
                'id'          => (int) $group['id'],
                'name'        => $name,
                'description' => $group['description'] ?? '',
                'assigned'    => isset($assignedNames[$name]),
            ];
        }

        return [
            'users'        => $users,
            'groups'       => $groups,
            'selectedUser' => $selectedUser,
            'userGroups'   => $userGroups,
        ];
    }


    /**
     * Mengganti seluruh group yang dimiliki user.
     *
     * $groupNames berisi nama group, bukan ID.
     */
    public function save(int $userId, array $groupNames): void
    {
        if (
            $userId < 1
            || $this->repository->findUser($userId) === null
        ) {
            throw new DomainException('User tidak ditemukan.');
        }

        $normalizedNames = [];

        foreach ($groupNames as $name) {
            if (! is_string($name)) {
                throw new DomainException(
                    'Nama group harus berupa teks.'
                );
            }

            $name = trim($name);

            if ($name === '') {
                throw new DomainException(
                    'Nama group tidak boleh kosong.'
                );
            }

            $normalizedNames[] = $name;
        }

        $groupNames = array_values(
            array_unique($normalizedNames)
        );

        $validNames = $this->repository->findGroupNames(
            $groupNames
        );

        if (count($validNames) !== count($groupNames)) {
            throw new DomainException(
                'Ada group yang tidak valid. Muat ulang halaman.'
            );
        }

        if (! $this->repository->beginTransaction()) {
            throw new RuntimeException(
                'Gagal memulai transaksi keanggotaan user.'
            );
        }

        try {
            if (! $this->repository->replaceUserGroups(
                $userId,
                $groupNames
            )) {
                throw new RuntimeException(
                    'Gagal menyimpan group user.'
                );
            }

            if (! $this->repository->transactionStatus()) {
                throw new RuntimeException(
                    'Transaksi penyimpanan group user gagal.'
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
