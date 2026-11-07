<?php

declare(strict_types=1);

namespace Modules\Backend\Repositories;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use InvalidArgumentException;

class IntegrationApiScopeRepository
{
    private const SCOPES_TABLE = 'integration_api_scopes';
    private const PERMISSIONS_TABLE = 'integration_api_scope_permissions';

    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function findAll(bool $activeOnly = false): array
    {
        $builder = $this->db->table(self::SCOPES_TABLE);

        if ($activeOnly) {
            $builder->where('status', 'active');
        }

        $scopes = $builder
            ->orderBy('scope', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($scopes as &$scope) {
            $scope['permissions'] = $this->findPermissions(
                (int) $scope['id']
            );
        }
        unset($scope);

        return $scopes;
    }

    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        $scope = $this->db->table(self::SCOPES_TABLE)
            ->where('id', $id)
            ->get()
            ->getRowArray();

        if ($scope === null) {
            return null;
        }

        $scope['permissions'] = $this->findPermissions($id);

        return $scope;
    }

    public function findByName(string $name): ?array
    {
        $scope = $this->db->table(self::SCOPES_TABLE)
            ->where('scope', trim($name))
            ->get()
            ->getRowArray();

        if ($scope === null) {
            return null;
        }

        $scope['permissions'] = $this->findPermissions(
            (int) $scope['id']
        );

        return $scope;
    }

    public function findPermissions(int $scopeId): array
    {
        return $this->db->table(self::PERMISSIONS_TABLE)
            ->select('permission_name')
            ->where('scope_id', $scopeId)
            ->orderBy('permission_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table(self::SCOPES_TABLE)->insert([
            'scope'       => $data['scope'],
            'description' => $data['description'] ?: null,
            'status'      => $data['status'] ?? 'active',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return (int) $this->db->insertID();
    }

    public function update(int $id, array $data): bool
    {
        $this->db->table(self::SCOPES_TABLE)
            ->where('id', $id)
            ->update([
                'scope'       => $data['scope'],
                'description' => $data['description'] ?: null,
                'status'      => $data['status'],
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

        return $this->db->affectedRows() >= 0;
    }

    public function syncPermissions(
        int $scopeId,
        array $permissionNames
    ): void {
        $this->db->table(self::PERMISSIONS_TABLE)
            ->where('scope_id', $scopeId)
            ->delete();

        foreach (array_unique($permissionNames) as $name) {
            $this->db->table(self::PERMISSIONS_TABLE)->insert([
                'scope_id'        => $scopeId,
                'permission_name' => $name,
            ]);
        }
    }

    public function delete(int $id): bool
    {
        $this->db->table(self::SCOPES_TABLE)
            ->where('id', $id)
            ->delete();

        return $this->db->affectedRows() > 0;
    }

    public function transactionStatus(): bool
    {
        return $this->db->transStatus();
    }

    public function beginTransaction(): void
    {
        $this->db->transBegin();
    }

    public function commitTransaction(): void
    {
        $this->db->transCommit();
    }

    public function rollbackTransaction(): void
    {
        $this->db->transRollback();
    }
}
