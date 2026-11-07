<?php

declare(strict_types=1);

namespace Modules\Backend\Services;

use InvalidArgumentException;
use Modules\Backend\Repositories\IntegrationApiScopeRepository;
use Modules\Rbac\Repositories\PermissionRepository;
use RuntimeException;
use Throwable;

class IntegrationApiScopeService
{
    protected IntegrationApiScopeRepository $scopes;
    protected PermissionRepository $permissions;

    public function __construct(
        ?IntegrationApiScopeRepository $scopes = null,
        ?PermissionRepository $permissions = null
    ) {
        $this->scopes = $scopes ?? new IntegrationApiScopeRepository();
        $this->permissions = $permissions ?? new PermissionRepository();
    }

    public function listScopes(bool $activeOnly = false): array
    {
        return $this->scopes->findAll($activeOnly);
    }

    public function listAvailablePermissions(): array
    {
        return $this->permissions->findAll();
    }

    public function findScope(int $id): ?array
    {
        return $this->scopes->findById($id);
    }

    public function createScope(
        string $scope,
        ?string $description,
        array $permissionNames
    ): int {
        $data = $this->validateData(
            $scope,
            $description,
            'active',
            $permissionNames
        );

        if ($this->scopes->findByName($data['scope']) !== null) {
            throw new InvalidArgumentException('Scope sudah terdaftar.');
        }

        $this->scopes->beginTransaction();

        try {
            $id = $this->scopes->create($data);
            $this->scopes->syncPermissions(
                $id,
                $data['permissions']
            );

            if (! $this->scopes->transactionStatus()) {
                throw new RuntimeException(
                    'Gagal menyimpan katalog scope.'
                );
            }

            $this->scopes->commitTransaction();

            return $id;
        } catch (Throwable $e) {
            $this->scopes->rollbackTransaction();
            throw $e;
        }
    }

    public function updateScope(
        int $id,
        string $scope,
        ?string $description,
        string $status,
        array $permissionNames
    ): bool {
        if ($id < 1 || $this->scopes->findById($id) === null) {
            throw new InvalidArgumentException('Scope tidak ditemukan.');
        }

        $data = $this->validateData(
            $scope,
            $description,
            $status,
            $permissionNames
        );

        $existing = $this->scopes->findByName($data['scope']);

        if ($existing !== null && (int) $existing['id'] !== $id) {
            throw new InvalidArgumentException('Nama scope sudah digunakan.');
        }

        $this->scopes->beginTransaction();

        try {
            $this->scopes->update($id, $data);
            $this->scopes->syncPermissions(
                $id,
                $data['permissions']
            );

            if (! $this->scopes->transactionStatus()) {
                throw new RuntimeException(
                    'Gagal memperbarui katalog scope.'
                );
            }

            $this->scopes->commitTransaction();

            return true;
        } catch (Throwable $e) {
            $this->scopes->rollbackTransaction();
            throw $e;
        }
    }

    public function deleteScope(int $id): bool
    {
        if ($id < 1 || $this->scopes->findById($id) === null) {
            throw new InvalidArgumentException('Scope tidak ditemukan.');
        }

        if (! $this->scopes->delete($id)) {
            throw new RuntimeException('Scope gagal dihapus.');
        }

        return true;
    }

    private function validateData(
        string $scope,
        ?string $description,
        string $status,
        array $permissionNames
    ): array {
        $scope = trim($scope);
        $description = trim((string) $description);

        if (
            ! preg_match(
                '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D',
                // '/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D',
                $scope
            )
        ) {
            throw new InvalidArgumentException(
                'Format nama scope tidak valid.'
            );
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException(
                'Status scope tidak valid.'
            );
        }

        $available = [];

        foreach ($this->permissions->findAll() as $permission) {
            $name = (string) ($permission['name'] ?? '');

            if ($name !== '') {
                $available[$name] = true;
            }
        }

        $normalized = [];

        foreach ($permissionNames as $name) {
            if (! is_string($name) || trim($name) === '') {
                throw new InvalidArgumentException(
                    'Permission scope tidak valid.'
                );
            }

            $name = trim($name);

            if (! isset($available[$name])) {
                throw new InvalidArgumentException(
                    'Permission tidak terdaftar di Shield: ' . $name
                );
            }

            $normalized[] = $name;
        }

        $normalized = array_values(array_unique($normalized));

        if ($normalized === []) {
            throw new InvalidArgumentException(
                'Pilih minimal satu permission untuk scope API.'
            );
        }

        return [
            'scope'       => $scope,
            'description' => $description,
            'status'      => $status,
            'permissions' => $normalized,
        ];
    }
}
