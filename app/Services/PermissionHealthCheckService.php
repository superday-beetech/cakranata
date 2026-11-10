<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Throwable;

class PermissionHealthCheckService
{
    private BaseConnection $db;


    public function __construct(
        ?BaseConnection $db = null,
        private array $publicEndpoints = []
    ) {
        $this->db = $db ?? Database::connect();

        $this->publicEndpoints = array_values(array_unique(array_filter(
            array_map(
                static fn($endpoint): string => trim((string) $endpoint),
                $this->publicEndpoints
            ),
            static fn(string $endpoint): bool => $endpoint !== ''
        )));
    }

    /**
     * Memeriksa konsistensi konfigurasi permission route.
     * Read-only: tidak mengubah data.
     */
    public function inspect(): array
    {
        $issues = [];

        try {
            $routes = $this->db->table('route_registry')
                ->select('id, endpoint, is_discovered, is_routable, is_allowed')
                ->get()
                ->getResultArray();

            $routeIds = [];
            $endpoints = [];

            foreach ($routes as $route) {
                $routeId = (int) $route['id'];
                $endpoint = trim((string) ($route['endpoint'] ?? ''));

                $routeIds[$routeId] = $routeId;

                if ($endpoint === '') {
                    $issues[] = $this->issue(
                        'error',
                        'empty_endpoint',
                        $routeId,
                        'Route memiliki endpoint kosong.'
                    );
                    continue;
                }

                $endpoints[$endpoint][] = $routeId;

                if ((int) $route['is_discovered'] !== 1) {
                    $issues[] = $this->issue(
                        'warning',
                        'route_not_discovered',
                        $routeId,
                        'Route belum ditandai sebagai discovered.'
                    );
                }

                if ((int) $route['is_routable'] !== 1) {
                    $issues[] = $this->issue(
                        'warning',
                        'route_not_routable',
                        $routeId,
                        'Route tidak ditandai sebagai routable.'
                    );
                }

                if ((int) $route['is_allowed'] !== 1) {
                    $issues[] = $this->issue(
                        'warning',
                        'route_not_allowed',
                        $routeId,
                        'Route tidak diizinkan oleh registry.'
                    );
                }
            }

            foreach ($endpoints as $endpoint => $ids) {
                if (count($ids) > 1) {
                    $issues[] = [
                        'severity' => 'warning',
                        'code' => 'duplicate_endpoint',
                        'route_id' => null,
                        'endpoint' => $endpoint,
                        'message' => 'Endpoint digunakan oleh beberapa route_registry ID: '
                            . implode(', ', $ids),
                    ];
                }
            }

            $verbs = $this->db->table('route_registry_verbs')
                ->select('route_id')
                ->get()
                ->getResultArray();

            $routesWithVerbs = [];

            foreach ($verbs as $verb) {
                $routesWithVerbs[(int) $verb['route_id']] = true;
            }




            foreach ($routeIds as $routeId) {
                $route = null;

                foreach ($routes as $candidate) {
                    if ((int) $candidate['id'] === $routeId) {
                        $route = $candidate;
                        break;
                    }
                }

                if ($route === null) {
                    continue;
                }

                $endpoint = trim((string) ($route['endpoint'] ?? ''));

                // Endpoint publik tidak diwajibkan memiliki permission route.
                if (in_array($endpoint, $this->publicEndpoints, true)) {
                    continue;
                }

                if (empty($permissionsByRoute[$routeId])) {
                    $issues[] = $this->issue(
                        'error',
                        'route_without_permission',
                        $routeId,
                        'Route privat tidak memiliki permission yang diwajibkan.'
                    );
                }
            }


            $allowedVerbs = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

            $verbRows = $this->db->table('route_registry_verbs')
                ->select('id, route_id, http_verb')
                ->get()
                ->getResultArray();

            foreach ($verbRows as $row) {
                $routeId = (int) ($row['route_id'] ?? 0);
                $verb = strtoupper(trim((string) ($row['http_verb'] ?? '')));

                if (! isset($routeIds[$routeId])) {
                    $issues[] = $this->issue(
                        'error',
                        'orphan_verb_mapping',
                        $routeId,
                        'Mapping HTTP verb mengacu pada route yang tidak ditemukan.'
                    );

                    continue;
                }

                if (! in_array($verb, $allowedVerbs, true)) {
                    $issues[] = $this->issue(
                        'error',
                        'invalid_http_verb',
                        $routeId,
                        'HTTP verb kosong atau tidak termasuk daftar verb yang diizinkan.'
                    );
                }
            }

            $mappings = $this->db->table('route_registry_permissions')
                ->select('route_id, permission')
                ->get()
                ->getResultArray();

            $permissionsByRoute = [];
            $mappedNames = [];

            foreach ($mappings as $mapping) {
                $routeId = (int) $mapping['route_id'];
                $permission = trim((string) ($mapping['permission'] ?? ''));

                if (! isset($routeIds[$routeId])) {
                    $issues[] = $this->issue(
                        'error',
                        'orphan_permission_mapping',
                        $routeId,
                        'Mapping permission mengacu pada route yang tidak ditemukan.'
                    );
                    continue;
                }

                if ($permission === '') {
                    $issues[] = $this->issue(
                        'error',
                        'empty_permission',
                        $routeId,
                        'Mapping route memiliki nama permission kosong.'
                    );
                    continue;
                }

                $permissionsByRoute[$routeId][$permission] = true;
                $mappedNames[$permission] = $permission;
            }

            foreach ($routeIds as $routeId) {
                if (empty($permissionsByRoute[$routeId])) {
                    $issues[] = $this->issue(
                        'error',
                        'route_without_permission',
                        $routeId,
                        'Route tidak memiliki permission yang diwajibkan.'
                    );
                }
            }

            $definedNames = [];

            if ($mappedNames !== []) {
                $defined = $this->db->table('auth_permissions')
                    ->select('name')
                    ->whereIn('name', array_values($mappedNames))
                    ->get()
                    ->getResultArray();

                foreach ($defined as $row) {
                    $definedNames[(string) $row['name']] = true;
                }
            }

            foreach ($mappedNames as $permission) {
                if (! isset($definedNames[$permission])) {
                    $issues[] = [
                        'severity' => 'error',
                        'code' => 'undefined_permission',
                        'route_id' => null,
                        'permission' => $permission,
                        'message' => 'Permission route belum terdaftar di auth_permissions.',
                    ];
                }
            }
        } catch (Throwable $e) {
            log_message(
                'error',
                'Permission health check gagal: {error}',
                ['error' => $e->getMessage()]
            );

            return [
                'healthy' => false,
                'summary' => [
                    'total' => 1,
                    'errors' => 1,
                    'warnings' => 0,
                ],
                'issues' => [[
                    'severity' => 'error',
                    'code' => 'health_check_failed',
                    'route_id' => null,
                    'message' => 'Pemeriksaan gagal. Periksa log aplikasi.',
                ]],
            ];
        }

        $errors = count(array_filter(
            $issues,
            static fn(array $issue): bool =>
            $issue['severity'] === 'error'
        ));

        $warnings = count(array_filter(
            $issues,
            static fn(array $issue): bool =>
            $issue['severity'] === 'warning'
        ));

        return [
            'healthy' => $errors === 0,
            'summary' => [
                'total' => count($issues),
                'errors' => $errors,
                'warnings' => $warnings,
            ],
            'issues' => $issues,
        ];
    }

    private function issue(
        string $severity,
        string $code,
        ?int $routeId,
        string $message
    ): array {
        return [
            'severity' => $severity,
            'code' => $code,
            'route_id' => $routeId,
            'message' => $message,
        ];
    }
}
