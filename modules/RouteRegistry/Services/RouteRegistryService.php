<?php

declare(strict_types=1);

namespace Modules\RouteRegistry\Services;

use Modules\RouteRegistry\Repositories\RouteRegistryRepository;
use RuntimeException;

class RouteRegistryService
{
    protected ModuleScannerService $scanner;

    protected RouteRegistryRepository $repository;

    public function __construct(
        ?ModuleScannerService $scanner = null,
        ?RouteRegistryRepository $repository = null
    ) {
        $this->scanner = $scanner ?? new ModuleScannerService();

        $this->repository = $repository
            ?? new RouteRegistryRepository();
    }

    public function syncPermissions(array $items): array
    {
        return $this->repository->syncPermissions($items);
    }

    public function syncNavigation(array $items): array
    {
        return $this->repository->syncNavigation($items);
    }


    public function getRegisteredPaginated(
        int $page = 1,
        int $perPage = 25,
        string $search = ''
    ): array {
        return $this->repository->getRegisteredPaginated(
            $page,
            $perPage,
            $search
        );
    }


    /**
     * Scan read-only dan gabungkan dengan registry saat ini.
     */
    public function scanPreview(): array
    {
        $scan = $this->scanner->scan();

        return [
            'total' => count($scan),
            'items' => $this->repository
                ->mergeScanWithRegistry($scan),
        ];
    }

    /**
     * Jalankan tindakan terhadap endpoint terpilih.
     *
     * $selected:
     * - insert/update: array data hasil scanner
     * - delete: array string endpoint
     */
    public function apply(
        string $action,
        array $selected
    ): array {
        if ($selected === []) {
            throw new RuntimeException(
                'Pilih minimal satu endpoint.'
            );
        }

        $action = strtolower(trim($action));

        return match ($action) {
            'insert' => $this->repository->insertSelected(
                $this->validateScanItems($selected)
            ),

            'update' => $this->repository->updateSelected(
                $this->validateScanItems($selected)
            ),

            'delete' => $this->repository->deleteSelected(
                $this->validateEndpoints($selected)
            ),

            default => throw new RuntimeException(
                'Tindakan tidak dikenal.'
            ),
        };
    }

    /**
     * Pastikan input insert/update berbentuk data scan.
     */
    protected function validateScanItems(array $items): array
    {
        $valid = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach (
                ['module', 'controller', 'method', 'endpoint'] as $key
            ) {
                if (
                    ! isset($item[$key])
                    || ! is_string($item[$key])
                    || trim($item[$key]) === ''
                ) {
                    throw new RuntimeException(
                        'Data endpoint terpilih tidak lengkap.'
                    );
                }
            }

            $valid[] = $item;
        }

        if ($valid === []) {
            throw new RuntimeException(
                'Tidak ada endpoint valid yang dipilih.'
            );
        }

        return $valid;
    }

    /**
     * Pastikan input delete berisi endpoint.
     */
    protected function validateEndpoints(array $items): array
    {
        $endpoints = [];

        foreach ($items as $item) {
            if (is_string($item)) {
                $endpoint = trim($item);
            } elseif (
                is_array($item)
                && isset($item['endpoint'])
                && is_string($item['endpoint'])
            ) {
                $endpoint = trim($item['endpoint']);
            } else {
                continue;
            }

            if ($endpoint !== '') {
                $endpoints[] = $endpoint;
            }
        }

        if ($endpoints === []) {
            throw new RuntimeException(
                'Tidak ada endpoint valid untuk dihapus.'
            );
        }

        return array_values(array_unique($endpoints));
    }


    /**
     * Membaca HTTP verb suatu endpoint.
     */
    public function getVerbs(string $endpoint): array
    {
        return $this->repository->getVerbs($endpoint);
    }

    /**
     * Menyimpan konfigurasi HTTP verb secara eksplisit.
     */
    public function saveVerbs(
        string $endpoint,
        array $verbs
    ): array {
        if (trim($endpoint) === '') {
            throw new RuntimeException(
                'Endpoint wajib dipilih.'
            );
        }

        return $this->repository->saveVerbs(
            $endpoint,
            $verbs
        );
    }

    /**
     * Daftar registry untuk pemuatan awal halaman.
     */
    public function getRegistered(): array
    {
        $items = $this->repository->getRegistered();

        return [
            'total' => count($items),
            'items' => $items,
        ];
    }

    /**
     * Memperbarui satu status registry.
     */
    public function updateStatus(
        int $routeId,
        string $field,
        bool $enabled
    ): array {
        return $this->repository->updateStatus(
            $routeId,
            $field,
            $enabled
        );
    }

    public function getVerbsById(int $routeId): array
    {
        if ($routeId < 1) {
            throw new RuntimeException('ID endpoint tidak valid.');
        }

        return $this->repository->getVerbsById($routeId);
    }

    public function syncShieldPermissions(array $items): array
    {
        return $this->repository->syncShieldPermissions($items);
    }

    public function previewShieldPermissions(): array
    {
        return $this->repository->previewShieldPermissions();
    }


    public function updateEndpointIdentity(
        int $routeId,
        string $module,
        string $controller,
        string $method
    ): array {
        return $this->repository->updateEndpointIdentity(
            $routeId,
            $module,
            $controller,
            $method
        );
    }


    public function getMenuSyncPreview(): array
    {
        return $this->repository->getMenuSyncPreview();
    }

    public function syncSelectedMenus(array $items): array
    {
        if ($items === []) {
            throw new \InvalidArgumentException(
                'Pilih minimal satu endpoint untuk disinkronkan.'
            );
        }

        return $this->repository->syncSelectedMenus($items);
    }
}
