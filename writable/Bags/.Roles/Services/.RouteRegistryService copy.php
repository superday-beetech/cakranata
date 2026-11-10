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
        $this->repository = $repository ?? new RouteRegistryRepository();
    }

    /**
     * Preview hasil scan tanpa menyimpan perubahan ke database.
     */
    public function scanPreview(): array
    {
        $scan = $this->scanner->scan();

        return [
            'total' => count($scan),
            'items' => $this->repository->mergeScanWithRegistry($scan),
        ];
    }

    /**
     * Menjalankan tindakan eksplisit terhadap endpoint terpilih.
     */
    public function apply(string $action, array $selected): array
    {
        if ($selected === []) {
            throw new RuntimeException('Pilih minimal satu endpoint.');
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
     * Memvalidasi item scan untuk tindakan insert/update.
     */
    protected function validateScanItems(array $items): array
    {
        $valid = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new RuntimeException(
                    'Format endpoint terpilih tidak valid.'
                );
            }

            foreach (['module', 'controller', 'method', 'endpoint'] as $key) {
                if (
                    ! isset($item[$key])
                    || ! is_string($item[$key])
                    || trim($item[$key]) === ''
                ) {
                    throw new RuntimeException(
                        "Data endpoint tidak lengkap: {$key}."
                    );
                }

                $item[$key] = trim($item[$key]);
            }

            $expectedEndpoint = $item['controller']
                . '::'
                . $item['method'];

            if ($item['endpoint'] !== $expectedEndpoint) {
                throw new RuntimeException(
                    'Format endpoint tidak sesuai dengan controller dan method.'
                );
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
     * Memvalidasi endpoint untuk tindakan delete.
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
                throw new RuntimeException(
                    'Format endpoint untuk penghapusan tidak valid.'
                );
            }

            if ($endpoint === '') {
                throw new RuntimeException(
                    'Endpoint untuk penghapusan tidak boleh kosong.'
                );
            }

            $endpoints[] = $endpoint;
        }

        return array_values(array_unique($endpoints));
    }

    /**
     * Data endpoint terdaftar untuk tampilan paginasi.
     */
    public function getRegisteredPaginated(
        int $page = 1,
        int $perPage = 25,
        string $search = ''
    ): array {
        return $this->repository->getRegisteredPaginated(
            max(1, $page),
            max(10, min(100, $perPage)),
            trim($search)
        );
    }

    /**
     * Data endpoint terdaftar untuk konsumen lama.
     */
    public function getRegistered(): array
    {
        return $this->repository->getRegistered();
    }

    /**
     * Mengambil konfigurasi HTTP verbs berdasarkan endpoint.
     */
    public function getVerbs(string $endpoint): array
    {
        $endpoint = trim($endpoint);

        if ($endpoint === '') {
            throw new RuntimeException('Endpoint wajib dipilih.');
        }

        return $this->repository->getVerbs($endpoint);
    }

    /**
     * Menyimpan konfigurasi HTTP verbs.
     */
    public function saveVerbs(string $endpoint, array $verbs): array
    {
        $endpoint = trim($endpoint);

        if ($endpoint === '') {
            throw new RuntimeException('Endpoint wajib dipilih.');
        }

        if ($verbs === []) {
            throw new RuntimeException(
                'Pilih minimal satu HTTP verb.'
            );
        }

        return $this->repository->saveVerbs($endpoint, $verbs);
    }

    /**
     * Memperbarui status routable/allowed secara eksplisit.
     */
    public function updateStatus(
        int $routeId,
        string $field,
        bool $enabled
    ): array {
        if ($routeId < 1) {
            throw new RuntimeException('ID endpoint tidak valid.');
        }

        $field = strtolower(trim($field));

        if (! in_array($field, ['routable', 'allowed'], true)) {
            throw new RuntimeException(
                'Field status Route Registry tidak valid.'
            );
        }

        return $this->repository->updateStatus(
            $routeId,
            $field,
            $enabled
        );
    }

    /**
     * Mengambil HTTP verbs berdasarkan ID registry.
     */
    public function getVerbsById(int $routeId): array
    {
        if ($routeId < 1) {
            throw new RuntimeException('ID endpoint tidak valid.');
        }

        return $this->repository->getVerbsById($routeId);
    }

    /**
     * Kompatibilitas sementara untuk pemanggil lama.
     *
     * Sinkronisasi permission belum mengubah auth_permissions Shield.
     */
    public function syncPermissions(array $items): array
    {
        return $this->repository->syncPermissions($items);
    }

    /**
     * Kompatibilitas sementara untuk pemanggil lama.
     *
     * Sinkronisasi menu tidak boleh membuat endpoint registry baru.
     */
    public function syncNavigation(array $items): array
    {
        return $this->repository->syncNavigation($items);
    }
}
