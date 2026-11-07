<?php

declare(strict_types=1);

namespace Modules\Rbac\Services;

class RbacService
{
    protected GroupService $groups;
    protected PermissionService $permissions;
    protected GroupPermissionService $groupPermissions;
    protected UserGroupService $userGroups;

    public function __construct(
        ?GroupService $groups = null,
        ?PermissionService $permissions = null,
        ?GroupPermissionService $groupPermissions = null,
        ?UserGroupService $userGroups = null
    ) {
        $this->groups = $groups ?? new GroupService();
        $this->permissions = $permissions ?? new PermissionService();

        $this->groupPermissions = $groupPermissions
            ?? new GroupPermissionService();

        $this->userGroups = $userGroups
            ?? new UserGroupService();
    }

    /*
    |--------------------------------------------------------------------------
    | GROUP
    |--------------------------------------------------------------------------
    */

    public function getAllGroups(): array
    {
        return $this->groups->getAllGroups();
    }

    public function getGroup(int $id): array
    {
        return $this->groups->getGroup($id);
    }

    public function createGroup(array $data): int
    {
        return $this->groups->createGroup($data);
    }

    public function updateGroup(int $id, array $data): bool
    {
        return $this->groups->updateGroup($id, $data);
    }

    public function deleteGroup(int $id): never
    {
        $this->groups->deleteGroup($id);
    }

    /*
    |--------------------------------------------------------------------------
    | PERMISSION
    |--------------------------------------------------------------------------
    */

    public function getPaginatedPermissions(
        int $page = 1,
        int $perPage = 20,
        string $search = ''
    ): array {
        return $this->permissions->getPaginated(
            $page,
            $perPage,
            $search
        );
    }

    public function getAllPermissions(): array
    {
        return $this->permissions->getAll();
    }

    public function getAllPermissionNames(): array
    {
        return $this->permissions->getAllNames();
    }

    public function getPermission(int $id): array
    {
        return $this->permissions->find($id);
    }

    public function createPermission(array $data): int
    {
        return $this->permissions->create($data);
    }

    public function updatePermission(int $id, array $data): bool
    {
        return $this->permissions->update($id, $data);
    }

    public function deletePermission(int $id): bool
    {
        return $this->permissions->delete($id);
    }

    /*
    |--------------------------------------------------------------------------
    | GROUP PERMISSIONS
    |--------------------------------------------------------------------------
    */

    public function getGroupPermissionOverview(
        ?int $groupId = null
    ): array {
        return $this->groupPermissions->getOverview($groupId);
    }

    public function saveGroupPermissions(
        int $groupId,
        array $permissionIds
    ): void {
        $this->groupPermissions->save(
            $groupId,
            $permissionIds
        );
    }

    /*
    |--------------------------------------------------------------------------
    | USER GROUPS
    |--------------------------------------------------------------------------
    */

    public function getUserGroupOverview(
        ?int $userId = null
    ): array {
        return $this->userGroups->getOverview($userId);
    }

    public function saveUserGroups(
        int $userId,
        array $groupNames
    ): void {
        $this->userGroups->save(
            $userId,
            $groupNames
        );
    }
}
