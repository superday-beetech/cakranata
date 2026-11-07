<?php

namespace Modules\Rbac\Services;

use DomainException;
use Modules\Rbac\Repositories\PermissionSyncRepository;
use Throwable;

class PermissionSyncService
{
    private PermissionSyncRepository $repository;

    public function __construct(
        ?PermissionSyncRepository $repository = null
    ) {
        $this->repository = $repository
            ?? new PermissionSyncRepository();
    }

    public function getOverview(): array
    {
        $source = $this->repository->getSourcePermissions();

        $registered = array_fill_keys(
            $this->repository->getRegisteredPermissionNames(),
            true
        );

        $permissions = [];

        foreach ($source as $row) {
            $name = trim((string) ($row['permission'] ?? ''));

            if ($name === '') {
                continue;
            }

            $permissions[] = [
                'name'        => $name,
                'endpoint'    => $row['endpoint'] ?? '',
                'route_count' => (int) ($row['route_count'] ?? 0),
                'exists'      => isset($registered[$name]),
            ];
        }

        return [
            'permissions' => $permissions,
            'total_source' => count($permissions),
            'total_registered' => count(array_filter(
                $permissions,
                static fn($item) => $item['exists']
            )),
            'total_missing' => count(array_filter(
                $permissions,
                static fn($item) => ! $item['exists']
            )),
        ];
    }

    public function synchronize(array $selectedNames): array
    {
        $selectedNames = array_values(array_unique(array_filter(
            array_map(
                static fn($name) => trim((string) $name),
                $selectedNames
            ),
            static fn($name) => $name !== ''
        )));

        if ($selectedNames === []) {
            throw new DomainException(
                'Pilih minimal satu permission.'
            );
        }

        // Validasi sekaligus mengambil endpoint dari sumber.
        $sourceRows = $this->repository
            ->getSourcePermissionsByNames($selectedNames);

        $sourceMap = [];

        foreach ($sourceRows as $row) {
            $name = trim((string) ($row['permission'] ?? ''));

            if ($name !== '') {
                $sourceMap[$name] = [
                    'permission' => $name,
                    'endpoint' => $row['endpoint'] ?? '',
                ];
            }
        }

        if (array_diff($selectedNames, array_keys($sourceMap)) !== []) {
            throw new DomainException(
                'Ada permission pilihan yang tidak valid. Muat ulang halaman.'
            );
        }

        $this->repository->beginTransaction();

        try {
            $existing = array_fill_keys(
                $this->repository->findRegisteredNames($selectedNames),
                true
            );

            $toInsert = [];

            foreach ($selectedNames as $name) {
                if (! isset($existing[$name])) {
                    $toInsert[] = $sourceMap[$name];
                }
            }

            $inserted = $this->repository->insertPermissions(
                $toInsert
            );

            if (! $this->repository->transactionStatus()) {
                throw new \RuntimeException(
                    'Transaksi sinkronisasi gagal.'
                );
            }

            $this->repository->commitTransaction();

            return [
                'inserted' => $inserted,
                'skipped' => count($selectedNames) - $inserted,
            ];
        } catch (Throwable $e) {
            $this->repository->rollbackTransaction();
            throw $e;
        }
    }
}
