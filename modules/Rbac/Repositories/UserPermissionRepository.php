<?php

declare(strict_types=1);

namespace Modules\Rbac\Repositories;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Modules\Auth\Models\UserModel;
use Modules\Rbac\Models\AuthPermissionModel;
use Modules\Rbac\Models\AuthPermissionUserModel;

class UserPermissionRepository
{
    private BaseConnection $db;
    private UserModel $userModel;
    private AuthPermissionModel $permissionModel;
    private AuthPermissionUserModel $userPermissionModel;

    public function __construct(
        ?UserModel $userModel = null,
        ?AuthPermissionModel $permissionModel = null,
        ?AuthPermissionUserModel $userPermissionModel = null,
        ?BaseConnection $db = null
    ) {
        $this->db = $db ?? Database::connect();

        $this->userModel = $userModel
            ?? new UserModel($this->db);

        $this->permissionModel = $permissionModel
            ?? new AuthPermissionModel($this->db);

        $this->userPermissionModel = $userPermissionModel
            ?? new AuthPermissionUserModel($this->db);
    }

    public function getUsers(): array
    {
        $users = $this->userModel
            ->select('id, username')
            ->orderBy('id', 'ASC')
            ->findAll();

        return array_map(static function ($user): array {
            return [
                'id' => (int) (
                    is_array($user) ? $user['id'] : $user->id
                ),
                'username' => (string) (
                    is_array($user)
                    ? ($user['username'] ?? '')
                    : ($user->username ?? '')
                ),
            ];
        }, $users);
    }

    public function findUser(int $userId): ?array
    {
        $user = $this->userModel
            ->select('id, username')
            ->find($userId);

        if ($user === null) {
            return null;
        }

        return [
            'id' => (int) (
                is_array($user) ? $user['id'] : $user->id
            ),
            'username' => (string) (
                is_array($user)
                ? ($user['username'] ?? '')
                : ($user->username ?? '')
            ),
        ];
    }

    public function getPermissions(): array
    {
        return $this->permissionModel->getAllPermissions();
    }

    public function getPermissionNamesForUser(int $userId): array
    {
        return $this->userPermissionModel
            ->getPermissionNamesForUser($userId);
    }

    public function findPermissionNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        return $this->permissionModel->findPermissionNames($names);
    }

    public function replaceUserPermissions(
        int $userId,
        array $permissionNames
    ): bool {
        if (! $this->userPermissionModel->deleteForUser($userId)) {
            return false;
        }

        return $this->userPermissionModel
            ->insertForUser($userId, $permissionNames);
    }

    public function beginTransaction(): bool
    {
        return $this->db->transBegin();
    }

    public function transactionStatus(): bool
    {
        return $this->db->transStatus();
    }

    public function commitTransaction(): bool
    {
        return $this->db->transCommit();
    }

    public function rollbackTransaction(): bool
    {
        return $this->db->transRollback();
    }
}
