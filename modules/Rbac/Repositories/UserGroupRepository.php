<?php

declare(strict_types=1);

namespace Modules\Rbac\Repositories;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Modules\Rbac\Models\AuthGroupModel;
use Modules\Rbac\Models\AuthGroupUserModel;
use Modules\Auth\Models\UserModel;

class UserGroupRepository
{
    private AuthGroupModel $groupModel;
    private AuthGroupUserModel $userGroupModel;
    private UserModel $userModel;
    private BaseConnection $db;


    public function __construct(
        ?AuthGroupModel $groupModel = null,
        ?AuthGroupUserModel $userGroupModel = null,
        ?UserModel $userModel = null,
        ?BaseConnection $db = null
    ) {
        $this->db = $db ?? Database::connect();

        $this->groupModel = $groupModel
            ?? new AuthGroupModel($this->db);

        $this->userGroupModel = $userGroupModel
            ?? new AuthGroupUserModel($this->db);

        $this->userModel = $userModel
            ?? new UserModel($this->db);
    }


    public function getGroups(): array
    {
        return $this->groupModel->getAllGroups();
    }

    public function getUsers(): array
    {
        $users = $this->userModel
            ->select('id, username')
            ->orderBy('id', 'ASC')
            ->findAll();

        return array_map(static function ($user): array {
            return [
                'id'       => (int) (is_array($user) ? $user['id'] : $user->id),
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
            'id'       => (int) (is_array($user) ? $user['id'] : $user->id),
            'username' => (string) (
                is_array($user)
                ? ($user['username'] ?? '')
                : ($user->username ?? '')
            ),
        ];
    }

    /* public function getUsers(): array
    {
        return $this->userModel
            ->select('id, username')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function findUser(int $userId): ?array
    {
        $user = $this->userModel
            ->select('id, username')
            ->find($userId);

        return $user ?: null;
    } */


    public function getGroupNamesForUser(int $userId): array
    {
        return $this->userGroupModel->getGroupNamesForUser($userId);
    }


    public function findGroupNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $rows = $this->groupModel
            ->builder()
            ->select('name')
            ->whereIn('name', $names)
            ->get()
            ->getResultArray();

        return array_column($rows, 'name');
    }


    public function replaceUserGroups(
        int $userId,
        array $groupNames
    ): bool {
        if (! $this->userGroupModel->deleteForUser($userId)) {
            return false;
        }

        return $this->userGroupModel->insertForUser(
            $userId,
            $groupNames
        );
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
