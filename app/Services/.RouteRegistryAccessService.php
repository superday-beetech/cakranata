<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

class RouteRegistryAccessService
{
    protected BaseConnection $db;
    protected AccessControlService $accessControl;

    /**
     * Cache hanya hidup selama instance service hidup.
     */
    private array $routeCache = [];
    private array $routePermissionsCache = [];

    public function __construct(
        ?BaseConnection $db = null,
        ?AccessControlService $accessControl = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->accessControl = $accessControl
            ?? new AccessControlService($this->db);
    }

    /**
     * Kunci cache untuk endpoint dan HTTP verb.
     */
    private function routeCacheKey(
        string $endpoint,
        string $httpVerb
    ): string {
        return $endpoint . '|' . $httpVerb;
    }

    public function clearCache(
        ?string $endpoint = null,
        ?int $routeId = null
    ): void {
        if ($endpoint !== null) {
            $endpoint = trim($endpoint);

            foreach (array_keys($this->routeCache) as $key) {
                if (str_starts_with($key, $endpoint . '|')) {
                    unset($this->routeCache[$key]);
                }
            }
        } else {
            $this->routeCache = [];
        }

        if ($routeId !== null) {
            unset($this->routePermissionsCache[$routeId]);
        } else {
            $this->routePermissionsCache = [];
        }
    }

    /**
     * Mengambil route yang aktif dan diizinkan untuk verb tertentu.
     * Hasil null juga di-cache agar pencarian yang gagal tidak berulang.
     */
    private function findRoute(
        string $endpoint,
        string $httpVerb
    ): ?array {
        $endpoint = trim($endpoint);
        $httpVerb = strtoupper(trim($httpVerb));

        if ($endpoint === '' || $httpVerb === '') {
            return null;
        }

        $key = $this->routeCacheKey($endpoint, $httpVerb);

        if (array_key_exists($key, $this->routeCache)) {
            return $this->routeCache[$key];
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

        return $this->routeCache[$key] = $route ?: null;
    }

    public function allowsHttpVerb(
        string $endpoint,
        string $httpVerb
    ): bool {
        return $this->findRoute($endpoint, $httpVerb) !== null;
    }

    public function findAllowedRoute(
        string $endpoint,
        string $httpVerb
    ): ?array {
        return $this->findRoute($endpoint, $httpVerb);
    }

    /**
     * Ambil permission route satu kali, lalu cache hasilnya.
     * Nilai permission adalah NAMA permission, bukan ID.
     */
    private function getRoutePermissions(int $routeId): array
    {

        if ($routeId <= 0) {
            return [];
        }

        if (array_key_exists($routeId, $this->routePermissionsCache)) {
            return $this->routePermissionsCache[$routeId];
        }

        $rows = $this->db->table('route_registry_permissions')
            ->select('permission')
            ->where('route_id', $routeId)
            ->get()
            ->getResultArray();

        $permissions = [];

        foreach ($rows as $row) {
            $permission = trim((string) ($row['permission'] ?? ''));

            // Data permission kosong dianggap konfigurasi tidak valid.
            if ($permission === '') {
                log_message(
                    'error',
                    'Permission route tidak valid: route_id={routeId}',
                    ['routeId' => $routeId]
                );
                return $this->routePermissionsCache[$routeId] = [];
            }

            $permissions[$permission] = $permission;
        }
        $permissions = array_values($permissions);

        if ($permissions === []) {
            log_message(
                'error',
                'Permission route belum dikonfigurasi: route_id={routeId}',
                ['routeId' => $routeId]
            );
        }
        return $this->routePermissionsCache[$routeId] = $permissions;
    }

    /**
     * Semua permission route harus dipenuhi.
     * Route tanpa permission ditolak untuk pengguna biasa.
     */
    public function allows(int $userId, int $routeId): bool
    {
        if (
            $userId <= 0
            || $routeId <= 0
            || ! $this->accessControl->isActiveUser($userId)
        ) {
            return false;
        }

        $permissions = $this->getRoutePermissions((int) $routeId);

        if ($permissions === []) {
            return false;
        }

        return $this->accessControl->hasAllPermissions(
            $userId,
            $permissions
        );
    }

    public function inspectEndpoint(string $module, string $endpoint, string $httpVerb): array
    {
        $module   = trim($module);
        $endpoint = trim($endpoint);
        $httpVerb = strtoupper(trim($httpVerb));

        $route = $this->db->table('route_registry')
            ->where('module', $module)
            ->where('endpoint', $endpoint)
            ->get()
            ->getRowArray();
        // print_r($endpoint);
        if ($route === null) {
            return [
                'found'     => false,
                'module'    => $module,
                'endpoint'  => $endpoint,
                'http_verb' => $httpVerb,
                'message'   => 'Endpoint belum terdaftar.',
            ];
        }
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
            'verb_registered' => $this->db
                ->table('route_registry_verbs')
                ->where('route_id', $route['id'])
                ->where('http_verb', $httpVerb)
                ->countAllResults() > 0,
            'permissions' => array_column($permissions, 'permission'),
        ];
    }
}
