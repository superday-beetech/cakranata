<?php

declare(strict_types=1);

namespace Modules\RouteRegistry\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\RouteRegistry\Services\RouteRegistryService;
use Throwable;

class RouteRegistryController extends ApiController
{
    protected RouteRegistryService $service;

    public function __construct()
    {
        $this->service = new RouteRegistryService();
    }

    public function scan(): ResponseInterface
    {

        try {
            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Scan selesai. Database tidak diubah.',
                'data'    => $this->service->scanPreview(),
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                'Route registry scan gagal: {message}',
                ['message' => $e->getMessage()]
            );

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Gagal melakukan scan endpoint.',
                ]);
        }
    }

    public function previewNavigation()
    {
        $db = db_connect();

        $menus = $db->table('menus')
            ->orderBy('position', 'ASC')
            ->orderBy('sequence', 'ASC')
            ->get()
            ->getResultArray();

        $registeredRoutes = service('routes')->getRoutes();

        $routeMap = [];

        foreach ($registeredRoutes as $routePath => $handler) {
            if (! is_string($handler) || ! str_contains($handler, '::')) {
                continue;
            }

            [$controller, $method] = explode('::', $handler, 2);

            $normalizedPath = trim(
                preg_replace('/^(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD):/i', '', $routePath),
                '/'
            );

            $routeMap[strtolower($normalizedPath)] = [
                'controller' => class_basename($controller),
                'method'     => $method,
                'endpoint'   => class_basename($controller) . '::' . $method,
                'module'     => $this->resolveModuleName($controller),
            ];
        }

        $items = [];

        foreach ($menus as $menu) {
            $url = trim((string) ($menu['url'] ?? ''), '/');
            $match = $routeMap[strtolower($url)] ?? [];

            $items[] = [
                'title'      => $menu['title'] ?? '',
                'url'        => $menu['url'] ?? '',
                'module'     => $match['module'] ?? '-',
                'controller' => $match['controller'] ?? '-',
                'method'     => $match['method'] ?? '-',
                'endpoint'   => $match['endpoint'] ?? '-',
                'matched'    => ! empty($match),
            ];
        }

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => 'Pratinjau berhasil dibuat. Database tidak diubah.',
            'items'   => $items,
        ]);
    }

    private function resolveModuleName(string $controller): string
    {
        if (preg_match('#Modules\\\\([^\\\\]+)\\\\Controllers\\\\#', $controller, $matches)) {
            return $matches[1];
        }

        return '-';
    }

    /**
     * Insert/update/delete endpoint yang dipilih.
     */
    public function apply(): ResponseInterface
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        if (! is_array($payload)) {
            return $this->badRequest('Payload tidak valid.');
        }

        $action = $payload['action'] ?? '';
        $selected = $payload['selected'] ?? [];

        if (! is_string($action) || ! is_array($selected)) {
            return $this->badRequest(
                'Action atau data pilihan tidak valid.'
            );
        }

        // Jangan mempercayai metadata yang dikirim browser.
        // Ambil ulang data aktual dari hasil scanner.
        if (in_array(strtolower($action), ['insert', 'update'], true)) {
            try {
                $selected = $this->resolveSelectedFromScan($selected);
            } catch (Throwable $e) {
                return $this->badRequest($e->getMessage());
            }
        }

        try {
            $result = $this->service->apply($action, $selected);

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Tindakan registry berhasil diproses.',
                'data'    => $result,
            ]);
        } catch (\RuntimeException $e) {
            log_message(
                'warning',
                'Route registry apply ditolak: {message}',
                ['message' => $e->getMessage()]
            );

            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                'Route registry apply gagal: {message}',
                ['message' => $e->getMessage()]
            );

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Gagal memproses registry endpoint.',
                ]);
        }
    }

    /**
     * Cocokkan pilihan browser dengan hasil scan server.
     *
     * Browser cukup mengirim:
     * selected: ["UserController::index", ...]
     */
    protected function resolveSelectedFromScan(array $selected): array
    {
        $requested = [];

        foreach ($selected as $endpoint) {
            if (! is_string($endpoint) || trim($endpoint) === '') {
                continue;
            }

            $requested[] = trim($endpoint);
        }

        $requested = array_values(array_unique($requested));

        if ($requested === []) {
            throw new \RuntimeException(
                'Pilih minimal satu endpoint.'
            );
        }

        $scan = $this->service->scanPreview();
        $scanItems = $scan['items'] ?? [];

        $indexed = [];

        foreach ($scanItems as $item) {
            if (isset($item['endpoint'])) {
                $indexed[$item['endpoint']] = $item;
            }
        }

        $resolved = [];

        foreach ($requested as $endpoint) {
            if (! isset($indexed[$endpoint])) {
                throw new \RuntimeException(
                    'Endpoint tidak ditemukan pada hasil scan: '
                        . $endpoint
                );
            }

            $item = $indexed[$endpoint];

            $resolved[] = [
                'module'     => $item['module'],
                'controller' => $item['controller'],
                'method'     => $item['method'],
                'endpoint'   => $item['endpoint'],
                'url_route'  => $item['url_route'] ?? null,
                'permission' => $item['permission'] ?? null,
            ];
        }

        return $resolved;
    }

    public function getVerbs(string $id): ResponseInterface
    {
        if (! ctype_digit($id) || (int) $id < 1) {
            return $this->badRequest('ID endpoint tidak valid.');
        }

        try {
            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'HTTP verb berhasil dimuat.',
                'data'    => $this->service->getVerbsById((int) $id),
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                'Gagal membaca HTTP verb: {message}',
                ['message' => $e->getMessage()]
            );

            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Endpoint tidak ditemukan.',
                ]);
        }
    }

    public function saveVerbs(): ResponseInterface
    {

        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->badRequest('Payload tidak valid.');
        }

        $endpoint = $payload['endpoint'] ?? null;
        $verbs = $payload['verbs'] ?? null;

        if (! is_string($endpoint) || ! is_array($verbs)) {
            return $this->badRequest(
                'Endpoint atau daftar HTTP verb tidak valid.'
            );
        }

        try {
            $result = $this->service->saveVerbs($endpoint, $verbs);

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'HTTP verb berhasil disimpan.',
                'data'    => $result,
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                'Gagal menyimpan HTTP verb: {message}',
                ['message' => $e->getMessage()]
            );

            return $this->badRequest($e->getMessage());
        }
    }


    public function registered(): ResponseInterface
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('perPage') ?? 25);
        $search = trim((string) ($this->request->getGet('search') ?? ''));

        $perPage = min(100, max(10, $perPage));

        try {
            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Registry berhasil dimuat.',
                'data'    => $this->service->getRegisteredPaginated(
                    $page,
                    $perPage,
                    $search
                ),
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                'Gagal memuat route registry: {message}',
                ['message' => $e->getMessage()]
            );

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Gagal memuat route registry.',
                ]);
        }
    }


    /**
     * GET: daftar endpoint yang sudah terdaftar.
     */
    public function registeredNoPaginate(): ResponseInterface
    {

        try {
            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Registry berhasil dimuat.',
                'data'    => $this->service->getRegistered(),
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                'Gagal memuat route registry: {message}',
                ['message' => $e->getMessage()]
            );

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Gagal memuat route registry.',
                ]);
        }
    }

    /**
     * POST: mengubah satu status Routable atau Allowed.
     */
    public function updateStatus(): ResponseInterface
    {

        if (! $this->request->is('patch')) {
            return $this->response
                ->setStatusCode(405)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Metode HTTP tidak diizinkan.',
                ]);
        }

        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->badRequest('Payload tidak valid.');
        }

        $id = $payload['id'] ?? null;
        $field = $payload['field'] ?? null;
        $value = $payload['value'] ?? null;

        if (
            ! is_int($id)
            || $id < 1
            || ! is_string($field)
            || ! in_array($field, ['routable', 'allowed'], true)
            || ! is_bool($value)
        ) {
            return $this->badRequest(
                'ID, jenis status, atau nilai status tidak valid.'
            );
        }

        try {
            $data = $this->service->updateStatus(
                $id,
                $field,
                $value
            );

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Status endpoint berhasil diperbarui.',
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                'Gagal mengubah status route registry: {message}',
                ['message' => $e->getMessage()]
            );

            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Gagal memperbarui status endpoint.',
                ]);
        }
    }

    /*    protected function ensureAuthenticated(): bool
    {
        return function_exists('auth')
            && auth('session')->loggedIn();
    }

    protected function unauthorized(): ResponseInterface
    {
        return $this->response
            ->setStatusCode(401)
            ->setJSON([
                'status'  => 'error',
                'message' => 'Silakan login terlebih dahulu.',
            ]);
    }
 */
    protected function badRequest(string $message): ResponseInterface
    {
        return $this->response
            ->setStatusCode(400)
            ->setJSON([
                'status'  => 'error',
                'message' => $message,
            ]);
    }

    public function syncPermissions()
    {
        if (!auth('session')->loggedIn()) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Sesi login tidak valid.',
                ]);
        }

        $payload = $this->request->getJSON(true);
        $items = $payload['items'] ?? null;

        if (!is_array($items) || $items === []) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Pilih minimal satu permission.',
                ]);
        }

        try {
            $result = $this->service->syncPermissions($items);

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Sinkronisasi permission selesai.',
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            log_message(
                'error',
                '[RouteRegistry] Sync permissions gagal: {message}',
                ['message' => $e->getMessage()]
            );

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Sinkronisasi permission gagal.',
                ]);
        }
    }

    public function syncNavigation()
    {
        $payload = $this->request->getJSON(true) ?? [];
        $items   = $payload['items'] ?? [];

        if (!is_array($items) || $items === []) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Tidak ada kandidat menu yang dipilih.',
                ]);
        }

        try {
            $result = $this->service->syncNavigation($items);

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Sinkronisasi kandidat menu selesai.',
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            log_message(
                'error',
                '[RouteRegistry] Sinkronisasi menu gagal: {message}',
                ['message' => $e->getMessage()]
            );

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Terjadi kesalahan saat sinkronisasi kandidat menu.',
                ]);
        }
    }


    public function syncShieldPermissions(): ResponseInterface
    {
        try {
            if (! auth('session')->loggedIn()) {
                return $this->response->setStatusCode(401)->setJSON([
                    'status'  => 'error',
                    'message' => 'Silakan login terlebih dahulu.',
                    'data'    => [],
                ]);
            }

            $payload = $this->request->getJSON(true);
            $items = $payload['items'] ?? null;

            if (! is_array($items) || $items === []) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status'  => 'error',
                    'message' => 'Data permission untuk disinkronkan kosong.',
                    'data'    => [],
                ]);
            }

            $result = $this->service->syncShieldPermissions($items);

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Sinkronisasi permission ke Shield berhasil.',
                'data'    => $result,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Sync Shield permissions failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Sinkronisasi permission ke Shield gagal.',
                'data'    => [],
            ]);
        }
    }



    public function previewShieldPermissions(): ResponseInterface
    {
        try {
            if (! auth('session')->loggedIn()) {
                return $this->response
                    ->setStatusCode(401)
                    ->setJSON([
                        'status'  => 'error',
                        'message' => 'Silakan login terlebih dahulu.',
                        'data'    => [],
                    ]);
            }

            $data = $this->service->previewShieldPermissions();

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Preview permission berhasil dibuat.',
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Permission preview failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->response
                ->setStatusCode(500)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Gagal membuat preview permission.',
                    'data'    => [],
                ]);
        }
    }


    public function syncAuthPermissions()
    {
        if (! auth('session')->loggedIn()) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Sesi login telah berakhir.',
                ]);
        }

        $payload = $this->request->getJSON(true);
        $permissions = $payload['permissions'] ?? null;

        if (! is_array($permissions) || empty($permissions)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Pilih minimal satu permission.',
                ]);
        }

        $db = \Config\Database::connect();
        $builder = $db->table('auth_permissions');

        $inserted = 0;
        $updated = 0;
        $unchanged = 0;
        $errors = [];

        $db->transBegin();

        try {
            foreach ($permissions as $item) {
                if (! is_array($item)) {
                    throw new \InvalidArgumentException(
                        'Format data permission tidak valid.'
                    );
                }

                $name = trim((string) ($item['name'] ?? ''));
                $description = trim((string) ($item['description'] ?? ''));

                if (
                    $name === '' ||
                    $description === '' ||
                    ! preg_match('/^[a-zA-Z0-9_.-]+$/', $name) ||
                    ! preg_match('/^[a-zA-Z0-9_\\\\:-]+$/', $description)
                ) {
                    throw new \InvalidArgumentException(
                        'Nama atau deskripsi permission tidak valid.'
                    );
                }

                $existing = $builder
                    ->where('name', $name)
                    ->get()
                    ->getRowArray();

                if ($existing === null) {
                    $builder->insert([
                        'name'        => $name,
                        'description' => $description,
                    ]);

                    $inserted++;
                    continue;
                }

                if ($existing['description'] === $description) {
                    $unchanged++;
                    continue;
                }

                $builder
                    ->where('id', $existing['id'])
                    ->update([
                        'description' => $description,
                    ]);

                $updated++;
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException(
                    'Gagal menyimpan permission.'
                );
            }

            $db->transCommit();

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Sinkronisasi permission berhasil.',
                'data'    => [
                    'inserted'  => $inserted,
                    'updated'   => $updated,
                    'unchanged' => $unchanged,
                ],
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();

            log_message(
                'error',
                'Sync permission gagal: {message}',
                ['message' => $e->getMessage()]
            );

            $statusCode = $e instanceof \InvalidArgumentException
                ? 422
                : 500;

            return $this->response
                ->setStatusCode($statusCode)
                ->setJSON([
                    'status'  => 'error',
                    'message' => $statusCode === 422
                        ? $e->getMessage()
                        : 'Sinkronisasi permission gagal.',
                ]);
        }
    }
}
