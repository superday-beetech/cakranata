<?php

namespace App\Entities;

use CodeIgniter\Shield\Entities\User as ShieldUser;
use Modules\Auth\Models\GroupHasModel;

class AuthUser extends ShieldUser
{
    protected $attributes = [
        'firstName'           => null,
        'lastName'            => null,
        'phone'               => null,
        'password_changed_at' => null,
    ];

    protected $casts = [
        'id'                  => 'integer',
        'firstName'           => 'string',
        'lastName'            => 'string',
        'phone'               => 'string',
        'password_changed_at' => 'datetime',
    ];

    protected ?array $rolesCache = null;

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    public function getInitials(): string
    {
        return strtoupper(substr($this->firstName ?? '', 0, 1));
    }

    public function getAvatarUrl(): string
    {
        return $this->avatar
            ? base_url('uploads/avatars/' . $this->avatar)
            : base_url('uploads/default-avatar.png');
    }

    public function isActive(): bool
    {
        return $this->active ?? false;
    }

    public function setEmail(string $email): void
    {
        parent::setEmail($email);
        $this->attributes['email'] = $email;
    }

    // --- USER ROLES ---

    public function getRoles(): array
    {
        if ($this->rolesCache !== null) {
            return $this->rolesCache;
        }

        $roleUserModel = model(\Modules\Auth\Models\RoleUserModel::class);

        return $this->rolesCache = $roleUserModel->getRolesForUserByName($this->id);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    public function hasAnyRole(array $roles): bool
    {
        $userRoles = $this->getRoles() ?? [];

        return (bool) array_intersect(
            array_map('strtolower', $roles),
            array_map('strtolower', $userRoles)
        );
    }

    public function role(string|array $roles): bool
    {
        $userRoles = $this->getRoles();
        if (is_array($roles)) {
            return !empty(array_intersect($roles, $userRoles));
        }

        return in_array($roles, $userRoles);
    }

    public function getRolesFromGroupsId(): array
    {
        $groupNames = $this->getGroupNames(); // ambil nama-nama grup user
        $roles      = [];

        $roleModel = model(\Modules\Auth\Models\RoleUserModel::class);

        foreach ($groupNames as $groupName) {
            $groupRoles = $roleModel->getRolesForGroupByName($groupName);
            foreach ($groupRoles as $r) {
                $roles[] = strtolower($r['name']);
            }
        }

        return array_unique($roles); // hilangkan duplikat
    }

    // --- GROUPS ---

    public function getGroups(): array
    {
        $groupModel = model(\Modules\Auth\Models\GroupHasModel::class);
        $userGroups = $groupModel->getGroupsForUser($this->id);

        return array_column($userGroups, 'id');
    }

    public function getGroupNames(): array
    {
        $groupModel = model(GroupHasModel::class);
        $groups     = $groupModel->getGroupsForUser($this->id);

        return array_column($groups, 'name');
    }

    // --- ROLES FROM GROUPS ---

    public function getRolesFromGroupsName(): array
    {
        $groupIds = $this->getGroups();
        if (empty($groupIds)) {
            return [];
        }

        $roleUserModel = model(\Modules\Auth\Models\RoleUserModel::class);
        $roles         = [];

        foreach ($groupIds as $groupId) {
            $groupRoles = $roleUserModel->getRolesForGroup($groupId); // array of ['name' => 'editor']
            $roles      = array_merge($roles, array_column($groupRoles, 'name'));
        }

        return array_unique($roles);
    }

    public function showRoles(): string
    {
        $roles = $this->getRoles();

        if (empty($roles)) {
            return '<em>Tidak ada role</em>';
        }

        return implode(', ', array_map('ucwords', $roles));
    }
}
