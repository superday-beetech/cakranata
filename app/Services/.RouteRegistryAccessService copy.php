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

    /**
     * Mengambil route yang disetujui dan cocok dengan HTTP verb.
     *
     * @return array<string, mixed>|null
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

        return $this->db->table('route_registry AS rr')
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
            ->getRowArray() ?: null;
    }


    /**
     * Memeriksa permission yang diwajibkan oleh route.
     *
     * Tidak ada permission yang dikonfigurasi berarti ditolak.
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

        // Fail-closed: route tanpa permission tidak diizinkan.
        if ($rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            $rawId = trim((string) ($row['permission'] ?? ''));

            // Hanya terima ID integer positif.
            if (
                $rawId === ''
                || ! ctype_digit($rawId)
                || (int) $rawId <= 0
            ) {
                return false;
            }

            if (! $this->accessControl->hasPermissionId(
                $userId,
                (int) $rawId
            )) {
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
