<?php

declare(strict_types=1);

namespace Modules\RouteRegistry\Repositories;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use Throwable;

class RouteRegistryRepository
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }


    /**
     * Daftar registry dengan server-side pagination dan pencarian.
     */
    public function getRegisteredPaginated(
        int $page = 1,
        int $perPage = 25,
        string $search = ''
    ): array {
        $page = max(1, $page);
        $perPage = min(100, max(10, $perPage));
        $search = trim($search);

        $builder = $this->db->table('route_registry');

        if ($search !== '') {
            $builder->groupStart()
                ->like('module', $search)
                ->orLike('controller', $search)
                ->orLike('method', $search)
                ->orLike('endpoint', $search)
                ->groupEnd();
        }

        $total = (int) $builder->countAllResults();

        $totalPages = (int) ceil($total / $perPage);
        $totalPages = max(1, $totalPages);

        // Jika halaman yang diminta melebihi jumlah halaman.
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $query = $this->db->table('route_registry')
            ->select(
                'id, module, controller, method, endpoint, url,'
                    . 'is_discovered, is_routable, is_allowed, '
                    . 'scan_status, last_scanned_at'
            );

        if ($search !== '') {
            $query->groupStart()
                ->like('module', $search)
                ->orLike('controller', $search)
                ->orLike('method', $search)
                ->orLike('endpoint', $search)
                ->groupEnd();
        }

        $rows = $query
            ->orderBy('module', 'ASC')
            ->orderBy('controller', 'ASC')
            ->orderBy('method', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        // Ambil verb hanya untuk endpoint di halaman ini.
        $routeIds = array_map(
            static fn(array $row): int => (int) $row['id'],
            $rows
        );

        $permissionsByRouteId = [];


        if ($routeIds !== []) {
            $permissionRows = $this->db->table('route_registry_permissions')
                ->select('route_id, permission')
                ->whereIn('route_id', $routeIds)
                ->orderBy('permission', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($permissionRows as $permissionRow) {
                $routeId = (int) $permissionRow['route_id'];
                $permissionsByRouteId[$routeId] = $permissionRow['permission'];
            }
        }

        $verbsByRouteId = [];

        if ($routeIds !== []) {
            $verbRows = $this->db->table('route_registry_verbs')
                ->select('route_id, http_verb')
                ->whereIn('route_id', $routeIds)
                ->orderBy('http_verb', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($verbRows as $verbRow) {
                $routeId = (int) $verbRow['route_id'];
                $verbsByRouteId[$routeId][] = $verbRow['http_verb'];
            }
        }

        $items = [];

        foreach ($rows as $row) {
            $routeId = (int) $row['id'];

            $row['id'] = $routeId;
            $row['is_discovered'] = (int) $row['is_discovered'];
            $row['is_routable'] = (int) $row['is_routable'];
            $row['is_allowed'] = (int) $row['is_allowed'];
            $row['url'] = $row['url'];
            $row['permissions'] = $permissionsByRouteId[$routeId] ?? [];
            $row['verbs'] = $verbsByRouteId[$routeId] ?? [];

            $items[] = [
                'module'     => $row['module'],
                'controller' => $row['controller'],
                'method'     => $row['method'],
                'endpoint'   => $row['endpoint'],
                'state'      => 'registered',
                'registry'   => $row,
            ];
        }

        return [
            'items' => $items,
            'pagination' => [
                'page'       => $page,
                'perPage'    => $perPage,
                'total'      => $total,
                'totalPages' => $totalPages,
                'hasPrev'    => $page > 1,
                'hasNext'    => $page < $totalPages,
            ],
        ];
    }


    /**
     * Gabungkan hasil scan dengan registry dan HTTP verb.
     * Read-only, tanpa perubahan database.
     */
    public function mergeScanWithRegistry(array $scan): array
    {
        $registered = $this->db->table('route_registry')
            ->select(
                'id, module, controller, method, endpoint, '
                    . 'is_discovered, is_routable, is_allowed, '
                    . 'scan_status, last_scanned_at'
            )
            ->get()
            ->getResultArray();

        if ($registered === []) {
            foreach ($scan as &$item) {
                $item['registry'] = null;
                $item['state'] = 'new';
            }

            unset($item);

            return $scan;
        }

        // Ambil seluruh HTTP verb sekaligus.
        $verbRows = $this->db->table('route_registry_verbs')
            ->select('route_id, http_verb')
            ->orderBy('http_verb', 'ASC')
            ->get()
            ->getResultArray();

        // Kelompokkan verb berdasarkan route_id.
        $verbsByRouteId = [];

        foreach ($verbRows as $verbRow) {
            $routeId = (int) $verbRow['route_id'];

            $verbsByRouteId[$routeId][] = $verbRow['http_verb'];
        }

        // Indeks registry berdasarkan endpoint.
        $byEndpoint = [];

        foreach ($registered as $row) {
            $routeId = (int) $row['id'];

            $row['verbs'] = $verbsByRouteId[$routeId] ?? [];

            $byEndpoint[(string) $row['endpoint']] = $row;
        }

        foreach ($scan as &$item) {
            $endpoint = (string) $item['endpoint'];

            $item['registry'] = $byEndpoint[$endpoint] ?? null;
            $item['state'] = $item['registry'] === null
                ? 'new'
                : 'registered';
        }

        unset($item);

        return $scan;
    }


    public function mergeScanWithRegistrySaja(array $scan): array
    {
        $registered = $this->db->table('route_registry')
            ->select(
                'id, module, controller, method, endpoint, '
                    . 'is_discovered, is_routable, is_allowed, '
                    . 'scan_status, last_scanned_at'
            )
            ->get()
            ->getResultArray();

        $byEndpoint = [];

        foreach ($registered as $row) {
            $byEndpoint[(string) $row['endpoint']] = $row;
        }

        foreach ($scan as &$item) {
            $item['registry'] = $byEndpoint[$item['endpoint']] ?? null;

            $item['state'] = $item['registry'] === null
                ? 'new'
                : 'registered';
        }

        unset($item);

        return $scan;
    }

    /**
     * Insert endpoint baru yang dipilih admin.
     */
    public function insertSelected(array $items): array
    {
        $inserted = 0;
        $skipped = 0;

        $now = date('Y-m-d H:i:s');

        $this->db->transBegin();

        try {
            foreach ($items as $item) {
                $endpoint = $this->validateItem($item);

                if ($this->findByEndpoint($endpoint) !== null) {
                    $skipped++;
                    continue;
                }

                $this->db->table('route_registry')->insert([
                    'module'         => $item['module'],
                    'controller'     => $item['controller'],
                    'method'         => $item['method'],
                    'endpoint'       => $endpoint,
                    'is_discovered'  => 1,
                    'is_routable'    => 0,
                    'is_allowed'     => 0,
                    'scan_status'    => 'new',
                    'last_scanned_at' => $now,
                ]);

                $inserted++;
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menyimpan endpoint baru.'
                );
            }

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return [
            'inserted' => $inserted,
            'skipped'  => $skipped,
        ];
    }

    /**
     * Update metadata endpoint yang sudah terdaftar.
     *
     * Tidak mengubah is_routable, is_allowed,
     * route_registry_verbs, atau route_registry_permissions.
     */
    public function updateSelected(array $items): array
    {
        $updated = 0;
        $skipped = 0;

        $now = date('Y-m-d H:i:s');

        $this->db->transBegin();

        try {
            foreach ($items as $item) {
                $endpoint = $this->validateItem($item);
                $existing = $this->findByEndpoint($endpoint);

                if ($existing === null) {
                    $skipped++;
                    continue;
                }

                $this->db->table('route_registry')
                    ->where('id', (int) $existing['id'])
                    ->update([
                        'module'          => $item['module'],
                        'controller'      => $item['controller'],
                        'method'          => $item['method'],
                        'is_discovered'   => 1,
                        'scan_status'     => 'scanned',
                        'last_scanned_at' => $now,
                    ]);

                $updated++;
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal memperbarui metadata endpoint.'
                );
            }

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * Hapus endpoint yang dipilih admin.
     *
     * Relasi verb dan permission mengikuti aturan FK
     * yang sudah ada pada database.
     */
    public function deleteSelected(array $endpoints): array
    {
        $deleted = 0;
        $skipped = 0;

        $this->db->transBegin();

        try {
            foreach ($endpoints as $value) {
                $endpoint = trim((string) $value);

                if ($endpoint === '' || strlen($endpoint) > 300) {
                    $skipped++;
                    continue;
                }

                $existing = $this->findByEndpoint($endpoint);

                if ($existing === null) {
                    $skipped++;
                    continue;
                }

                $this->db->table('route_registry')
                    ->where('id', (int) $existing['id'])
                    ->delete();

                $deleted++;
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menghapus endpoint.'
                );
            }

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
        ];
    }

    /**
     * Cari satu endpoint berdasarkan identitas uniknya.
     */
    protected function findByEndpoint(string $endpoint): ?array
    {
        $row = $this->db->table('route_registry')
            ->where('endpoint', $endpoint)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Validasi data hasil scan sebelum menulis ke database.
     */
    protected function validateItem(array $item): string
    {
        foreach (
            ['module', 'controller', 'method', 'endpoint'] as $key
        ) {
            if (
                ! isset($item[$key])
                || ! is_string($item[$key])
                || trim($item[$key]) === ''
            ) {
                throw new RuntimeException(
                    'Data endpoint hasil scan tidak valid.'
                );
            }
        }

        if (
            strlen($item['module']) > 100
            || strlen($item['controller']) > 190
            || strlen($item['method']) > 100
            || strlen($item['endpoint']) > 300
        ) {
            throw new RuntimeException(
                'Data endpoint melebihi batas kolom database.'
            );
        }

        $expected = $item['controller']
            . '::'
            . $item['method'];

        if (! hash_equals($expected, $item['endpoint'])) {
            throw new RuntimeException(
                'Identitas endpoint tidak konsisten.'
            );
        }

        return $expected;
    }


    /**
     * Daftar HTTP verb yang tersimpan untuk suatu endpoint.
     */
    public function getVerbs(string $endpoint): array
    {
        $endpoint = trim($endpoint);
        $route = $this->findByEndpoint($endpoint);

        if ($route === null) {
            throw new RuntimeException(
                'Endpoint belum terdaftar di Route Registry.'
            );
        }

        $rows = $this->db->table('route_registry_verbs')
            ->select('http_verb')
            ->where('route_id', (int) $route['id'])
            ->orderBy('http_verb', 'ASC')
            ->get()
            ->getResultArray();

        return array_column($rows, 'http_verb');
    }

    /**
     * Mengganti konfigurasi HTTP verb endpoint terpilih.
     *
     * Hanya menyimpan konfigurasi; tidak mengubah route aplikasi.
     */
    public function saveVerbs(
        string $endpoint,
        array $verbs
    ): array {
        $endpoint = trim($endpoint);

        if ($endpoint === '' || strlen($endpoint) > 300) {
            throw new RuntimeException('Endpoint tidak valid.');
        }

        $allowedVerbs = [
            'GET',
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
            'OPTIONS',
            'HEAD',
        ];

        $normalized = [];

        foreach ($verbs as $verb) {
            if (! is_string($verb)) {
                throw new RuntimeException(
                    'HTTP verb harus berupa string.'
                );
            }

            $verb = strtoupper(trim($verb));

            if (! in_array($verb, $allowedVerbs, true)) {
                throw new RuntimeException(
                    'HTTP verb tidak didukung: ' . $verb
                );
            }

            $normalized[] = $verb;
        }

        $normalized = array_values(array_unique($normalized));

        $route = $this->findByEndpoint($endpoint);

        if ($route === null) {
            throw new RuntimeException(
                'Endpoint belum terdaftar di Route Registry.'
            );
        }

        $routeId = (int) $route['id'];

        $this->db->transBegin();

        try {
            $this->db->table('route_registry_verbs')
                ->where('route_id', $routeId)
                ->delete();

            foreach ($normalized as $verb) {
                $this->db->table('route_registry_verbs')->insert([
                    'route_id'  => $routeId,
                    'http_verb' => $verb,
                ]);
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menyimpan konfigurasi HTTP verb.'
                );
            }

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return [
            'endpoint' => $endpoint,
            'verbs'    => $normalized,
            'saved'    => count($normalized),
        ];
    }

    public function getVerbsById(int $routeId): array
    {
        $route = $this->db->table('route_registry')
            ->select('id, endpoint')
            ->where('id', $routeId)
            ->get()
            ->getRowArray();

        if ($route === null) {
            throw new RuntimeException(
                'Endpoint tidak ditemukan di registry.'
            );
        }

        $rows = $this->db->table('route_registry_verbs')
            ->select('http_verb')
            ->where('route_id', $routeId)
            ->orderBy('http_verb', 'ASC')
            ->get()
            ->getResultArray();

        return [
            'id'       => (int) $route['id'],
            'endpoint' => $route['endpoint'],
            'verbs'    => array_column($rows, 'http_verb'),
        ];
    }

    /**
     * Mengambil endpoint yang sudah terdaftar beserta HTTP verb.
     * Read-only: tidak melakukan scan module atau perubahan database.
     */
    public function getRegistered(): array
    {
        $rows = $this->db->table('route_registry')
            ->select(
                'id, module, controller, method, endpoint, url, permission,'
                    . 'is_discovered, is_routable, is_allowed, '
                    . 'scan_status, last_scanned_at'
            )
            ->orderBy('module', 'ASC')
            ->orderBy('controller', 'ASC')
            ->orderBy('method', 'ASC')
            ->get()
            ->getResultArray();

        if ($rows === []) {
            return [];
        }

        $permissionRows = $this->db->table('route_registry_permissions')
            ->select('route_id, permission')
            ->orderBy('permission', 'ASC')
            ->get()
            ->getResultArray();

        $permissionsByRouteId = [];

        foreach ($permissionsByRouteId as $permissionRows) {
            $permissionRowId = (int) $permissionRows['route_id'];
            $permissionRowByRouteId[$permissionRowId][] = $permissionRows['permission'];
        }

        $verbRows = $this->db->table('route_registry_verbs')
            ->select('route_id, http_verb')
            ->orderBy('http_verb', 'ASC')
            ->get()
            ->getResultArray();

        $verbsByRouteId = [];

        foreach ($verbRows as $verbRow) {
            $routeId = (int) $verbRow['route_id'];
            $verbsByRouteId[$routeId][] = $verbRow['http_verb'];
        }

        $items = [];

        foreach ($rows as $row) {
            $routeId = (int) $row['id'];

            $row['id'] = $routeId;
            $row['is_discovered'] = (int) $row['is_discovered'];
            $row['is_routable'] = (int) $row['is_routable'];
            $row['is_allowed'] = (int) $row['is_allowed'];
            $row['permissions'] = $permissionsByRouteId[$routeId] ?? [];
            $row['verbs'] = $verbsByRouteId[$routeId] ?? [];

            $items[] = [
                'module'     => $row['module'],
                'controller' => $row['controller'],
                'method'     => $row['method'],
                'endpoint'   => $row['endpoint'],
                'url'        => $row['url'],
                'state'      => 'registered',
                'registry'   => $row,
            ];
        }

        return $items;
    }

    /**
     * Mengubah satu status registry dengan whitelist kolom.
     */
    public function updateStatus(
        int $routeId,
        string $field,
        bool $enabled
    ): array {
        if ($routeId < 1) {
            throw new RuntimeException('ID endpoint tidak valid.');
        }

        $columns = [
            'routable' => 'is_routable',
            'allowed'  => 'is_allowed',
        ];

        if (! isset($columns[$field])) {
            throw new RuntimeException(
                'Jenis status registry tidak diizinkan.'
            );
        }

        $column = $columns[$field];

        $route = $this->db->table('route_registry')
            ->select('id, endpoint, is_routable, is_allowed')
            ->where('id', $routeId)
            ->get()
            ->getRowArray();

        if ($route === null) {
            throw new RuntimeException(
                'Endpoint tidak ditemukan di registry.'
            );
        }

        $this->db->transBegin();

        try {
            $this->db->table('route_registry')
                ->where('id', $routeId)
                ->update([
                    $column => $enabled ? 1 : 0,
                ]);

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal memperbarui status endpoint.'
                );
            }

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        $route[$column] = $enabled ? 1 : 0;

        return [
            'id'          => $routeId,
            'endpoint'    => $route['endpoint'],
            'field'       => $field,
            'value'       => $enabled,
            'is_routable' => (int) $route['is_routable'],
            'is_allowed'  => (int) $route['is_allowed'],
        ];
    }

    public function syncPermissions(array $items): array
    {
        $db = \Config\Database::connect();

        $inserted = 0;
        $existing = 0;
        $skipped = 0;

        $db->transBegin();

        try {
            foreach ($items as $item) {
                $endpoint = trim((string) ($item['endpoint'] ?? ''));
                $permission = trim((string) ($item['permission'] ?? ''));

                if ($endpoint === '' || $permission === '') {
                    $skipped++;
                    continue;
                }

                $route = $db->table('route_registry')
                    ->select('id')
                    ->where('endpoint', $endpoint)
                    ->get()
                    ->getRowArray();

                if (!$route) {
                    $skipped++;
                    continue;
                }

                $exists = $db->table('route_registry_permissions')
                    ->where('route_id', $route['id'])
                    ->where('permission', $permission)
                    ->countAllResults() > 0;

                if ($exists) {
                    $existing++;
                    continue;
                }

                $db->table('route_registry_permissions')->insert([
                    'route_id'   => $route['id'],
                    'permission' => $permission,
                ]);

                $inserted++;
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException(
                    'Gagal menyimpan relasi permission.'
                );
            }

            $db->transCommit();

            return [
                'inserted' => $inserted,
                'existing' => $existing,
                'skipped'  => $skipped,
            ];
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }


    public function syncNavigation(array $items): array
    {
        $db = \Config\Database::connect();

        $result = [
            'inserted' => 0,
            'updated'  => 0,
            'existing' => 0,
            'skipped'  => 0,
        ];

        $db->transBegin();

        try {
            foreach ($items as $item) {
                $module     = trim($item['module'] ?? '');
                $controller = trim($item['controller'] ?? '');
                $method     = strtolower(trim($item['method'] ?? ''));
                $endpoint   = trim($item['endpoint'] ?? '');
                $url        = trim($item['url'] ?? '');

                if (
                    $module === '' ||
                    $controller === '' ||
                    $method === '' ||
                    $url === ''
                    // || ! in_array($method, ['index', 'dashboard'], true)
                ) {
                    $result['skipped']++;
                    continue;
                }

                if ($endpoint === '') {
                    $endpoint = $controller . '::' . $method;
                }

                // Cari route berdasarkan endpoint.
                $route = $db->table('route_registry')
                    ->select('id')
                    ->where('endpoint', $endpoint)
                    ->get()
                    ->getRowArray();

                // Jika belum ada, daftarkan route terlebih dahulu.
                if ($route === null) {
                    $db->table('route_registry')->insert([
                        'module'        => $module,
                        'controller'    => $controller,
                        'method'        => $method,
                        'endpoint'      => $endpoint,
                        'is_discovered' => 1,
                        'is_routable'   => 0,
                        'is_allowed'    => 0,
                        'scan_status'   => 'new',
                    ]);

                    $routeId = (int) $db->insertID();

                    if ($routeId === 0) {
                        $route = $db->table('route_registry')
                            ->select('id')
                            ->where('endpoint', $endpoint)
                            ->get()
                            ->getRowArray();

                        if ($route === null) {
                            throw new \RuntimeException(
                                "Gagal mendapatkan route ID: {$endpoint}"
                            );
                        }

                        $routeId = (int) $route['id'];
                    }
                } else {
                    $routeId = (int) $route['id'];
                }

                // Bentuk judul menu.
                $title = preg_replace('/Controller$/i', '', $controller);
                $title = preg_replace('/(?<!^)([A-Z])/', ' $1', $title);
                $title = trim($title);

                if ($title === '') {
                    $result['skipped']++;
                    continue;
                }

                $menuTable = $db->table('route_registry_menus');

                $existing = $menuTable
                    ->where('route_id', $routeId)
                    ->get()
                    ->getRowArray();

                $now = date('Y-m-d H:i:s');

                $data = [
                    'title'      => $title,
                    'url'        => $url,
                    'updated_at' => $now,
                ];

                if ($existing !== null) {
                    $changed = $existing['title'] !== $title
                        || $existing['url'] !== $url;

                    if ($changed) {
                        $menuTable
                            ->where('route_id', $routeId)
                            ->update($data);

                        $result['updated']++;
                    } else {
                        $result['existing']++;
                    }

                    continue;
                }

                $data['route_id']   = $routeId;
                $data['created_at'] = $now;

                $menuTable->insert($data);
                $result['inserted']++;
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException(
                    'Sinkronisasi kandidat menu gagal.'
                );
            }

            $db->transCommit();

            return $result;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
