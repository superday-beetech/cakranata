<?php

namespace Modules\Rbac\Models;

use CodeIgniter\Model;

class RouteRegistryPermissionModel extends Model
{
    protected $table            = 'route_registry_permissions';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'route_id',
        'permission',
    ];
    protected $useTimestamps    = false;

    /**
     * Daftar permission unik dari route registry.
     */
    public function getDistinctPermissions(): array
    {
        return $this->builder()
            ->select('permission')
            ->select('COUNT(DISTINCT route_id) AS route_count', false)
            ->where('permission !=', '')
            ->groupBy('permission')
            ->orderBy('permission', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Pastikan permission yang dipilih memang berasal dari tabel sumber.
     */
    public function findPermissionNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $rows = $this->builder()
            ->select('permission')
            ->whereIn('permission', $names)
            ->groupBy('permission')
            ->get()
            ->getResultArray();

        return array_column($rows, 'permission');
    }


    public function getPermissionsWithEndpoints(array $names): array
    {
        if ($names === []) {
            return [];
        }

        return $this->builder()
            ->select('rrp.permission')
            ->select('rr.endpoint')
            ->from('route_registry_permissions rrp')
            ->join(
                'route_registry rr',
                'rr.id = rrp.route_id',
                'inner'
            )
            ->whereIn('rrp.permission', $names)
            ->groupBy(['rrp.permission', 'rr.endpoint'])
            ->orderBy('rrp.permission', 'ASC')
            ->get()
            ->getResultArray();
    }
}
