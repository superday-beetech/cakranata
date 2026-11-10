<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

class RouteRegistryAccessService
{
    protected BaseConnection $db;
    protected AccessControlService $accessControl;

    public function __construct(
        ?BaseConnection $db = null,
        ?AccessControlService $accessControl = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->accessControl = $accessControl
            ?? new AccessControlService($this->db);
    }


    public function allowsHttpVerb(
        string $endpoint,
        string $httpVerb
    ): bool {
        $endpoint = trim($endpoint);
        $httpVerb = strtoupper(trim($httpVerb));

        if ($endpoint === '' || $httpVerb === '') {
            return false;
        }

        return $this->db->table('route_registry AS rr')
            ->join(
                'route_registry_verbs AS rrv',
                'rrv.route_id = rr.id',
                'inner'
            )
            ->where('rr.endpoint', $endpoint)
            ->where('rrv.http_verb', $httpVerb)
            ->where('rr.is_discovered', 1)
            ->where('rr.is_routable', 1)
            ->where('rr.is_allowed', 1)
            ->countAllResults() > 0;
    }




    /**
     * Cari endpoint yang sudah ditemukan, routable,
     * diizinkan, dan memiliki HTTP verb yang sesuai.
     */
    public function findAllowedRoute(
        string $endpoint,
        string $httpVerb
    ): ?array {
        $endpoint = trim($endpoint);
        $httpVerb = strtoupper(trim($httpVerb));

        if ($endpoint === '' || $httpVerb === '') {
            return null;
        }

        $route = $this->db->table('route_registry AS rr')
            ->select('rr.id, rr.endpoint')
            ->join(
                'route_registry_verbs AS rrv',
                'rrv.route_id = rr.id',
                'inner'
            )
            ->where('rr.endpoint', $endpoint)
            ->where('rrv.http_verb', $httpVerb)
            ->where('rr.is_discovered', 1)
            ->where('rr.is_routable', 1)
            ->where('rr.is_allowed', 1)
            ->get()
            ->getRowArray();

        return $route ?: null;
    }

    /**
     * Periksa seluruh permission yang ditetapkan untuk route.
     * Semua permission harus terpenuhi.
     */
    public function allows(
        int $userId,
        int $routeId
    ): bool {
        if (
            $userId <= 0
            || $routeId <= 0
            || ! $this->accessControl->isActiveUser($userId)
        ) {
            return false;
        }

        $rows = $this->db->table('route_registry_permissions')
            ->select('permission')
            ->where('route_id', $routeId)
            ->get()
            ->getResultArray();

        // Fail-closed: route tanpa permission tidak dapat
        // diakses oleh pengguna biasa.
        if ($rows === []) {
            return false;
        }
        foreach ($rows as $row) {
            $rawId = trim((string) ($row['permission'] ?? ''));

            if ($rawId === '') {
                return false;
            }
            if (! $this->accessControl->hasPermissionId($userId, (int) $rawId)) {
                return false;
            }
        }

        return true;
    }


    public function inspectEndpoint(
        string $endpoint,
        string $httpVerb
    ): array {
        $endpoint = trim($endpoint);
        $httpVerb = strtoupper(trim($httpVerb));

        $route = $this->db->table('route_registry')
            ->where('endpoint', $endpoint)
            ->get()
            ->getRowArray();

        if ($route === null) {
            return [
                'found' => false,
                'endpoint' => $endpoint,
                'http_verb' => $httpVerb,
                'message' => 'Endpoint belum terdaftar.',
            ];
        }

        $verbExists = $this->db->table('route_registry_verbs')
            ->where('route_id', $route['id'])
            ->where('http_verb', $httpVerb)
            ->countAllResults() > 0;

        $permissions = $this->db->table('route_registry_permissions')
            ->select('permission')
            ->where('route_id', $route['id'])
            ->get()
            ->getResultArray();

        return [
            'found' => true,
            'id' => (int) $route['id'],
            'endpoint' => $route['endpoint'],
            'is_discovered' => (bool) $route['is_discovered'],
            'is_routable' => (bool) $route['is_routable'],
            'is_allowed' => (bool) $route['is_allowed'],
            'verb_registered' => $verbExists,
            'permissions' => array_column($permissions, 'permission'),
        ];
    }
}
