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
    /* public function getRegisteredPaginated(
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
                'id, module, controller, method, endpoint, url_route,permission,'
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

        $permissionsByRouteId[] = [];


        if ($routeIds !== []) {
            $permissionRows = $this->db->table('route_registry_permissions')
                ->select('route_id, permission')
                ->whereIn('route_id', $routeIds)
                ->orderBy('permission', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($permissionRows as $permissionRow) {
                $routeId = (int) $permissionRow['route_id'];
                $permissionsByRouteId[$routeId][] = $permissionRow['permission'];
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
            $row['url_route'] = $row['url_route'];
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
    } */

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

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $query = $this->db->table('route_registry')
            ->select(
                'id, module, controller, method, endpoint, url_route, namespace, '
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

        $routeIds = array_map(
            static fn(array $row): int => (int) $row['id'],
            $rows
        );

        // Permission per endpoint.
        $permissionsByRouteId = [];

        // HTTP verb per endpoint.
        $verbsByRouteId = [];

        if ($routeIds !== []) {
            $permissionRows = $this->db->table('route_registry_permissions')
                ->select('route_id, permission')
                ->whereIn('route_id', $routeIds)
                ->orderBy('permission', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($permissionRows as $permissionRow) {
                $routeId = (int) $permissionRow['route_id'];

                $permissionsByRouteId[$routeId][] =
                    $permissionRow['permission'];
            }

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

            $row['permissions'] = $permissionsByRouteId[$routeId] ?? [];
            $row['verbs'] = $verbsByRouteId[$routeId] ?? [];

            $items[] = [
                'module' => $row['module'],
                'controller' => $row['controller'],
                'method' => $row['method'],
                'endpoint' => $row['endpoint'],
                'state' => 'registered',
                'registry' => $row,
            ];
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
                'hasPrev' => $page > 1,
                'hasNext' => $page < $totalPages,
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
                'id, module, controller, method, endpoint, url_route, namespace,'
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


    /**
     * Mengubah identitas endpoint yang sudah terdaftar.
     *
     * ID registry dipertahankan.
     * Relasi HTTP verbs, permissions, dan menu tetap terhubung
     * melalui route_id yang sama.
     */
    public function updateEndpointIdentity(
        int $routeId,
        string $module,
        string $controller,
        string $method
    ): array {
        $module     = trim($module);
        $controller = trim($controller);
        $method     = trim($method);

        if ($routeId < 1) {
            throw new RuntimeException('ID endpoint tidak valid.');
        }

        if (
            $module === ''
            || $controller === ''
            || $method === ''
        ) {
            throw new RuntimeException(
                'Module, controller, dan method wajib diisi.'
            );
        }

        if (
            strlen($module) > 100
            || strlen($controller) > 190
            || strlen($method) > 100
        ) {
            throw new RuntimeException(
                'Identitas endpoint melebihi batas kolom database.'
            );
        }

        // Validasi nama class/controller dan method PHP.
        if (! preg_match(
            '/^[A-Za-z_][A-Za-z0-9_\\\\]*$/',
            $controller
        )) {
            throw new RuntimeException(
                'Nama controller tidak valid.'
            );
        }

        if (! preg_match(
            '/^[A-Za-z_][A-Za-z0-9_]*$/',
            $method
        )) {
            throw new RuntimeException(
                'Nama method tidak valid.'
            );
        }

        // Endpoint selalu dibentuk dari controller dan method.
        $endpoint = $controller . '::' . $method;

        if (strlen($endpoint) > 300) {
            throw new RuntimeException(
                'Endpoint melebihi batas 300 karakter.'
            );
        }

        $this->db->transBegin();

        try {
            $existing = $this->db->table('route_registry')
                ->where('id', $routeId)
                ->get()
                ->getRowArray();

            if ($existing === null) {
                throw new RuntimeException(
                    'Endpoint tidak ditemukan di registry.'
                );
            }

            // Tolak endpoint duplikat milik record lain.
            $duplicate = $this->db->table('route_registry')
                ->where('endpoint', $endpoint)
                ->where('id !=', $routeId)
                ->countAllResults();

            if ($duplicate > 0) {
                throw new RuntimeException(
                    'Identitas endpoint sudah digunakan oleh record lain.'
                );
            }

            $this->db->table('route_registry')
                ->where('id', $routeId)
                ->update([
                    'module'     => $module,
                    'controller' => $controller,
                    'method'     => $method,
                    'endpoint'   => $endpoint,
                ]);


            // Bentuk permission dasar dari identitas endpoint.
            // Format: module.controller.method
            $permission = strtolower(
                trim($module, '.')
                    . '.'
                    . trim(
                        preg_replace('/Controller$/i', '', basename(str_replace('\\', '/', $controller))),
                        '.'
                    )
                    . '.'
                    . trim($method, '.')
            );

            // Pastikan permission tidak melebihi batas kolom.
            if (strlen($permission) > 100) {
                throw new RuntimeException(
                    'Nama permission melebihi batas 100 karakter.'
                );
            }

            // Buat relasi permission jika belum ada.
            // Permission lama tetap dipertahankan.
            $permissionExists = $this->db
                ->table('route_registry_permissions')
                ->where('route_id', $routeId)
                ->where('permission', $permission)
                ->countAllResults();

            if ($permissionExists === 0) {
                $this->db->table('route_registry_permissions')->insert([
                    'route_id'   => $routeId,
                    'permission' => $permission,
                ]);
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal memperbarui identitas endpoint.'
                );
            }

            $this->db->transCommit();

            return [
                'id'         => $routeId,
                'module'     => $module,
                'controller' => $controller,
                'method'     => $method,
                'endpoint'   => $endpoint,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
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

                $permission = $item['permission'] ?? null;

                if (
                    ! is_string($permission)
                    || trim($permission) === ''
                ) {
                    throw new RuntimeException(
                        'Permission tidak valid untuk endpoint: '
                            . $endpoint
                    );
                }

                $this->db->table('route_registry')->insert([
                    'module'          => $item['module'],
                    'controller'      => $item['controller'],
                    'method'          => $item['method'],
                    'endpoint'        => $endpoint,
                    'url_route'       => $item['url_route'] ?? null,
                    'namespace'       => $item['namespace'] ?? null,
                    'is_discovered'   => 1,
                    'is_routable'     => 1,
                    'is_allowed'      => 1,
                    'scan_status'     => 'new',
                    'last_scanned_at' => $now,
                ]);

                $routeId = (int) $this->db->insertID();

                if ($routeId <= 0) {
                    throw new RuntimeException(
                        'Gagal mendapatkan ID endpoint: ' . $endpoint
                    );
                }

                $this->db->table('route_registry_permissions')->insert([
                    'route_id'   => $routeId,
                    'permission' => trim($permission),
                ]);

                $inserted++;
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menyimpan endpoint dan permission.'
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

                $routeId = (int) $existing['id'];

                $this->db->table('route_registry')
                    ->where('id', $routeId)
                    ->update([
                        'module'          => $item['module'],
                        'controller'      => $item['controller'],
                        'method'          => $item['method'],
                        'url_route'       => $item['url_route'] ?? null,
                        'is_discovered'   => 1,
                        'scan_status'     => 'scanned',
                        'last_scanned_at' => $now,
                    ]);

                // Sinkronkan permission dari hasil Scan.
                $permission = $item['permission'] ?? null;

                if (
                    ! is_string($permission)
                    || trim($permission) === ''
                ) {
                    throw new RuntimeException(
                        'Permission tidak valid untuk endpoint: '
                            . $endpoint
                    );
                }

                $permission = trim($permission);

                // Hapus pemetaan lama untuk endpoint ini saja.
                $this->db->table('route_registry_permissions')
                    ->where('route_id', $routeId)
                    ->delete();

                // Simpan permission terbaru.
                $this->db->table('route_registry_permissions')
                    ->insert([
                        'route_id'   => $routeId,
                        'permission' => $permission,
                    ]);

                $updated++;
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal memperbarui endpoint dan permission.'
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
        $endpoints = array_values(array_unique(array_filter(
            array_map(
                static fn($endpoint): string =>
                is_string($endpoint) ? trim($endpoint) : '',
                $endpoints
            ),
            static fn(string $endpoint): bool => $endpoint !== ''
        )));

        if ($endpoints === []) {
            throw new RuntimeException(
                'Tidak ada endpoint valid untuk dihapus.'
            );
        }

        $deleted = [];
        $skipped = [];

        $this->db->transBegin();

        try {
            foreach ($endpoints as $endpoint) {
                $route = $this->findByEndpoint($endpoint);

                if ($route === null) {
                    $skipped[] = $endpoint;
                    continue;
                }

                $routeId = (int) $route['id'];

                $this->db->table('route_registry_verbs')
                    ->where('route_id', $routeId)
                    ->delete();

                $this->db->table('route_registry_permissions')
                    ->where('route_id', $routeId)
                    ->delete();

                $this->db->table('route_registry_menus')
                    ->where('route_id', $routeId)
                    ->delete();

                $this->db->table('route_registry')
                    ->where('id', $routeId)
                    ->delete();

                $deleted[] = $endpoint;
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menghapus data Route Registry.'
                );
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
            'total_deleted' => count($deleted),
            'total_skipped' => count($skipped),
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
                'id, module, controller, method, endpoint, url_route, permission,'
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

        foreach ($permissionRows as $permissionRow) {
            $routeId = (int) $permissionRow['route_id'];

            $permissionsByRouteId[$routeId][] =
                $permissionRow['permission'];
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
            $row['url_route']        = $row['url_route'];
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
                $url_route        = trim($item['url_route'] ?? '');

                if (
                    $module === '' ||
                    $controller === '' ||
                    $method === '' ||
                    $url_route === ''
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
                    'url_route'        => $url_route,
                    'updated_at' => $now,
                ];

                if ($existing !== null) {
                    $changed = $existing['title'] !== $title
                        || $existing['url_route'] !== $url_route;

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

    /**
     * Sinkronisasi permission registry ke Shield auth_permissions.
     *
     * Hanya endpoint yang ditemukan, routable, dan diizinkan.
     * Tidak menghapus permission dan tidak mengubah grup pengguna.
     */


    public function syncShieldPermissions(array $items): array
    {
        $inserted = 0;
        $updated  = 0;
        $existing = 0;
        $skipped  = 0;

        $this->db->transBegin();

        try {
            // Validasi ulang: hanya permission dari route yang aktif
            // dan diizinkan yang boleh disinkronkan.
            $rows = $this->db->table('route_registry_permissions rp')
                ->select('rp.permission')
                ->join('route_registry rr', 'rr.id = rp.route_id', 'inner')
                ->where('rr.is_discovered', 1)
                ->where('rr.is_routable', 1)
                ->where('rr.is_allowed', 1)
                ->get()
                ->getResultArray();

            $allowed = [];

            foreach ($rows as $row) {
                $name = trim((string) ($row['permission'] ?? ''));

                if ($name !== '' && strlen($name) <= 150) {
                    $allowed[$name] = true;
                }
            }

            // Hindari permission duplikat dalam request.
            $submitted = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    $skipped++;
                    continue;
                }

                $permission = trim((string) ($item['permission'] ?? ''));
                $description = trim((string) ($item['description'] ?? ''));

                if (
                    $permission === ''
                    || strlen($permission) > 150
                    || $description === ''
                    || strlen($description) > 255
                    || ! isset($allowed[$permission])
                    || isset($submitted[$permission])
                ) {
                    $skipped++;
                    continue;
                }

                $submitted[$permission] = $description;
            }

            $now = date('Y-m-d H:i:s');

            foreach ($submitted as $permission => $description) {
                $current = $this->db->table('auth_permissions')
                    ->select('id, description')
                    ->where('name', $permission)
                    ->get()
                    ->getRowArray();

                if ($current === null) {
                    $this->db->table('auth_permissions')->insert([
                        'name'        => $permission,
                        'description' => $description,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]);

                    $inserted++;
                    continue;
                }

                $oldDescription = (string) ($current['description'] ?? '');

                if ($oldDescription !== $description) {
                    $this->db->table('auth_permissions')
                        ->where('id', $current['id'])
                        ->update([
                            'description' => $description,
                            'updated_at'  => $now,
                        ]);

                    $updated++;
                } else {
                    $existing++;
                }
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menyinkronkan permission ke Shield.'
                );
            }

            $this->db->transCommit();

            return [
                'inserted' => $inserted,
                'updated'  => $updated,
                'existing' => $existing,
                'skipped'  => $skipped,
            ];
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }



    private function buildPermissionDescription(
        string $permission,
        array $row
    ): ?string {
        $permission = trim($permission);

        $module = trim((string) ($row['module'] ?? ''));
        $controller = trim((string) ($row['controller'] ?? ''));
        $method = trim((string) ($row['method'] ?? ''));
        $endpoint = trim((string) ($row['endpoint'] ?? ''));

        // Nama objek dari bagian permission sebelum titik terakhir.
        $parts = explode('.', $permission);
        $action = strtolower((string) array_pop($parts));
        $resource = implode(' ', $parts);

        // Jika permission tidak memuat aksi yang dikenali,
        // gunakan nama method controller sebagai petunjuk.
        $actionMap = [
            'index'       => 'menampilkan',
            'view'        => 'melihat',
            'show'        => 'melihat detail',
            'get'         => 'mengambil data',
            'list'        => 'melihat daftar',
            'registered'  => 'melihat daftar endpoint terdaftar',
            'read'        => 'membaca',
            'create'      => 'membuat',
            'store'       => 'menyimpan',
            'save'        => 'menyimpan',
            'insert'      => 'menambahkan',
            'update'      => 'memperbarui',
            'edit'        => 'mengubah',
            'patch'       => 'memperbarui',
            'put'         => 'memperbarui',
            'delete'      => 'menghapus',
            'destroy'     => 'menghapus',
            'remove'      => 'menghapus',
            'scan'        => 'memindai dan menginventarisasi',
            'apply'       => 'menerapkan konfigurasi',
            'sync'        => 'menyinkronkan',
            'syncpermissions' => 'menyinkronkan permission',
            'syncnavigation'  => 'menyinkronkan navigasi',
            'synshieldpermissions' => 'menyinkronkan permission ke Shield',
            'export'      => 'mengekspor',
            'import'      => 'mengimpor',
            'search'      => 'mencari',
            'login'       => 'melakukan autentikasi',
            'logout'      => 'mengakhiri sesi',
            'enable'      => 'mengaktifkan',
            'disable'     => 'menonaktifkan',
            'status'      => 'mengubah status',
            'verbs'       => 'melihat konfigurasi metode HTTP',
            'saveverbs'   => 'mengatur metode HTTP yang diizinkan',
            'backup'      => 'mencadangkan',
            'restore'     => 'memulihkan',
        ];

        $normalizedAction = strtolower(
            preg_replace('/[^a-zA-Z0-9]/', '', $action) ?? $action
        );

        $verb = $actionMap[$normalizedAction] ?? null;

        if ($verb === null && $method !== '') {
            $normalizedMethod = strtolower(
                preg_replace('/[^a-zA-Z0-9]/', '', $method) ?? $method
            );

            $verb = $actionMap[$normalizedMethod] ?? null;
        }

        if ($verb === null) {
            $verb = 'menjalankan operasi';
        }

        // Terjemahkan nama resource menjadi label yang mudah dipahami.
        $resourceMap = [
            'user'             => 'akun pengguna',
            'users'            => 'akun pengguna',
            'group'            => 'grup pengguna',
            'groups'           => 'grup pengguna',
            'role'             => 'peran pengguna',
            'roles'            => 'peran pengguna',
            'permission'       => 'permission atau hak akses',
            'permissions'      => 'permission atau hak akses',
            'navigation'       => 'navigasi aplikasi',
            'menu'             => 'menu aplikasi',
            'menus'            => 'menu aplikasi',
            'route registry'   => 'data Route Registry',
            'route-registry'   => 'data Route Registry',
            'route_registry'   => 'data Route Registry',
            'routes'           => 'rute aplikasi',
            'route'            => 'rute aplikasi',
            'module'           => 'modul aplikasi',
            'modules'          => 'modul aplikasi',
            'verb'             => 'metode HTTP',
            'verbs'            => 'metode HTTP',
            'setting'          => 'pengaturan aplikasi',
            'settings'         => 'pengaturan aplikasi',
            'log'              => 'log aktivitas',
            'logs'             => 'log aktivitas',
            'audit'            => 'audit aktivitas',
            'profile'          => 'profil pengguna',
            'profiles'         => 'profil pengguna',
            'token'            => 'token akses',
            'tokens'           => 'token akses',
            'backup'           => 'cadangan data',
            'backups'          => 'cadangan data',
            'report'           => 'laporan',
            'reports'          => 'laporan',
            'product'          => 'produk',
            'products'         => 'produk',
            'inventory'        => 'persediaan barang',
            'finance'          => 'data keuangan',
            'employee'         => 'data karyawan',
            'employees'        => 'data karyawan',
        ];

        $normalizedResource = strtolower(
            str_replace(['_', '-'], ' ', $resource)
        );

        $object = $resourceMap[$normalizedResource] ?? null;

        if ($object === null || $object === '') {
            // Jika resource tidak dikenal, ubah slug menjadi label.
            $object = $resource !== ''
                ? ucwords(str_replace(
                    ['_', '-', '.'],
                    ' ',
                    $resource
                ))
                : '';
        }

        // Gunakan endpoint sebagai petunjuk tambahan jika nama permission
        // belum cukup menjelaskan resource yang diakses.
        if ($object === '' && $endpoint !== '') {
            $object = 'endpoint ' . $endpoint;
        }

        if ($object === '') {
            $object = 'fitur aplikasi';
        }

        $moduleLabel = $module !== ''
            ? ucwords(str_replace(['_', '-'], ' ', $module))
            : '';

        $description = 'Memberi izin untuk '
            . $verb
            . ' '
            . $object;

        if ($moduleLabel !== '') {
            $description .= ' melalui modul ' . $moduleLabel;
        }

        $description .= '.';

        // Kolom Shield memiliki batas 255 karakter.
        if (strlen($description) > 255) {
            $description = substr($description, 0, 252) . '...';
        }

        return $description;
    }


    public function previewShieldPermissions(): array
    {
        $rows = $this->db->table('route_registry_permissions rp')
            ->select([
                'rp.permission',
                'rr.module',
                'rr.controller',
                'rr.method',
                'rr.endpoint',
            ])
            ->join('route_registry rr', 'rr.id = rp.route_id', 'inner')
            ->where('rr.is_discovered', 1)
            ->where('rr.is_routable', 1)
            ->where('rr.is_allowed', 1)
            ->orderBy('rp.permission', 'ASC')
            ->get()
            ->getResultArray();

        $permissions = [];

        foreach ($rows as $row) {
            $permission = trim((string) ($row['permission'] ?? ''));

            if ($permission === '' || strlen($permission) > 150) {
                continue;
            }

            if (! isset($permissions[$permission])) {
                $permissions[$permission] = $row;
            }
        }

        $result = [];

        foreach ($permissions as $permission => $row) {
            $generated = $this->buildPermissionDescription($permission, $row);

            if ($generated === null || $generated === '') {
                continue;
            }

            $current = $this->db->table('auth_permissions')
                ->select('description')
                ->where('name', $permission)
                ->get()
                ->getRowArray();

            $oldDescription = trim(
                (string) ($current['description'] ?? '')
            );

            // Pertahankan deskripsi manual yang sudah bermakna.
            $isGeneric = $oldDescription === ''
                || $oldDescription === 'Disinkronkan dari Route Registry';

            $result[] = [
                'permission' => $permission,
                'description' => $current !== null && ! $isGeneric
                    ? $oldDescription
                    : $generated,
                'exists' => $current !== null,
            ];
        }

        return $result;
    }


    /**
     * Daftar endpoint yang dapat disinkronkan ke menu.
     */
    public function getMenuSyncPreview(): array
    {
        $rows = $this->db->table('route_registry r')
            ->select([
                'r.id AS route_id',
                'r.module',
                'r.controller',
                'r.method',
                'r.endpoint',
                'r.url_route',
                'm.title AS menu_title',
                'm.url AS menu_url',
            ])
            ->join(
                'route_registry_menus m',
                'm.route_id = r.id',
                'left'
            )
            ->where('r.url_route IS NOT NULL', null, false)
            ->where("TRIM(r.url_route) !=", '')
            ->orderBy('r.module', 'ASC')
            ->orderBy('r.endpoint', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $controller = basename(
                str_replace('\\', '/', (string) $row['controller'])
            );

            $controller = preg_replace('/Controller$/i', '', $controller);

            $method = (string) $row['method'];

            // Judul awal: nama controller untuk method index,
            // atau nama method untuk method lainnya.
            $defaultTitle = $method === 'index'
                ? $controller
                : $method;

            $defaultTitle = preg_replace(
                '/(?<!^)([A-Z])/',
                ' $1',
                str_replace(['_', '-'], ' ', $defaultTitle)
            );

            $row['title'] = trim((string) ($row['menu_title'] ?? ''))
                ?: trim($defaultTitle);

            $row['url'] = trim((string) ($row['menu_url'] ?? ''))
                ?: trim((string) $row['url_route']);

            $row['is_synced'] = $row['menu_title'] !== null;
            unset($row['menu_title'], $row['menu_url']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Upsert menu berdasarkan route_id.
     */
    public function syncSelectedMenus(array $items): array
    {
        $saved = 0;
        $inserted = 0;
        $updated = 0;

        $this->db->transBegin();

        try {
            foreach ($items as $item) {
                $routeId = (int) ($item['route_id'] ?? 0);
                $title = trim((string) ($item['title'] ?? ''));
                $url = trim((string) ($item['url'] ?? ''));

                if ($routeId < 1) {
                    throw new \InvalidArgumentException(
                        'ID endpoint tidak valid.'
                    );
                }

                if ($title === '' || mb_strlen($title) > 100) {
                    throw new \InvalidArgumentException(
                        'Title wajib diisi dan maksimal 100 karakter.'
                    );
                }

                if ($url === '' || mb_strlen($url) > 255) {
                    throw new \InvalidArgumentException(
                        'URL wajib diisi dan maksimal 255 karakter.'
                    );
                }

                // Validasi ulang: endpoint harus ada dan url_route
                // sumbernya tidak NULL atau kosong.
                $route = $this->db->table('route_registry')
                    ->select('id')
                    ->where('id', $routeId)
                    ->where('url_route IS NOT NULL', null, false)
                    ->where("TRIM(url_route) !=", '')
                    ->get()
                    ->getRowArray();

                if ($route === null) {
                    throw new \InvalidArgumentException(
                        "Endpoint ID {$routeId} tidak memenuhi syarat menu."
                    );
                }

                $existing = $this->db->table('route_registry_menus')
                    ->select('id')
                    ->where('route_id', $routeId)
                    ->get()
                    ->getRowArray();

                $data = [
                    'title'      => $title,
                    'url'        => $url,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                if ($existing !== null) {
                    $this->db->table('route_registry_menus')
                        ->where('route_id', $routeId)
                        ->update($data);

                    $updated++;
                } else {
                    $data['route_id'] = $routeId;
                    $data['created_at'] = date('Y-m-d H:i:s');

                    $this->db->table('route_registry_menus')->insert($data);

                    $inserted++;
                }

                $saved++;
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException(
                    'Gagal menyimpan sinkronisasi menu.'
                );
            }

            $this->db->transCommit();

            return [
                'saved'    => $saved,
                'inserted' => $inserted,
                'updated'  => $updated,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
}
