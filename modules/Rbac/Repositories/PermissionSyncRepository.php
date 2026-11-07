<?php

namespace Modules\Rbac\Repositories;

use CodeIgniter\Database\BaseConnection;
use Modules\Rbac\Models\AuthPermissionModel;
use Modules\Rbac\Models\RouteRegistryPermissionModel;

class PermissionSyncRepository
{
    private RouteRegistryPermissionModel $sourceModel;
    private AuthPermissionModel $permissionModel;
    private BaseConnection $db;

    public function __construct(
        ?RouteRegistryPermissionModel $sourceModel = null,
        ?AuthPermissionModel $permissionModel = null,
        ?BaseConnection $db = null
    ) {
        $this->sourceModel = $sourceModel
            ?? new RouteRegistryPermissionModel();

        $this->permissionModel = $permissionModel
            ?? new AuthPermissionModel();

        $this->db = $db ?? db_connect();
    }

    public function getSourcePermissions(): array
    {
        return $this->sourceModel->getDistinctPermissions();
    }

    public function getRegisteredPermissionNames(): array
    {
        return $this->permissionModel->getPermissionNames();
    }

    public function findSourceNames(array $names): array
    {
        return $this->sourceModel->findPermissionNames($names);
    }

    public function findRegisteredNames(array $names): array
    {
        return $this->permissionModel->findPermissionNames($names);
    }


    public function insertPermissions(array $permissions): int
    {
        return $this->permissionModel->insertPermissions($permissions);
    }


    public function beginTransaction(): bool
    {
        return $this->db->transBegin();
    }

    public function commitTransaction(): bool
    {
        return $this->db->transCommit();
    }

    public function rollbackTransaction(): bool
    {
        return $this->db->transRollback();
    }

    public function transactionStatus(): bool
    {
        return $this->db->transStatus();
    }


    public function getSourcePermissionsByNames(array $names): array
    {
        return $this->sourceModel->getPermissionsWithEndpoints($names);
    }
}
