<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Modules\Core\Models\OrganizationModel;
use Modules\Core\Models\OrganizationUnitModel;
use RuntimeException;

class OrganizationUnitService
{
    protected OrganizationUnitModel $units;
    protected OrganizationModel $organizations;

    public function __construct()
    {
        $this->units = new OrganizationUnitModel();
        $this->organizations = new OrganizationModel();
    }

    public function getList(
        int $organizationId,
        string $keyword = '',
        string $status = ''
    ): array {
        $organization = $this->organizations->findAvailable($organizationId);

        if (! $organization) {
            throw new RuntimeException('Organisasi tidak ditemukan.');
        }

        $builder = $this->units
            ->where('organization_id', $organizationId)
            ->where('deleted_at', null);

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('code', $keyword)
                ->orLike('name', $keyword)
                ->orLike('unit_type', $keyword)
                ->groupEnd();
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $builder->where('status', $status);
        }

        return $builder->orderBy('name', 'ASC')->findAll();
    }

    public function getById(int $id): ?array
    {
        return $this->units->findAvailable($id);
    }

    public function getActiveOrganizations(): array
    {
        return $this->organizations->getActiveOrganizations();
    }

    public function getOrganization(int $organizationId): ?array
    {
        return $this->organizations->findAvailable($organizationId);
    }


    public function getParentOptions(
        int $organizationId,
        ?int $excludeUnitId = null
    ): array {
        $organization = $this->organizations->findAvailable($organizationId);

        if (! $organization || $organization['status'] !== 'active') {
            throw new \RuntimeException(
                'Organisasi tidak ditemukan atau tidak aktif.'
            );
        }

        $units = $this->units->getActiveByOrganization($organizationId);

        $excludedIds = [];

        if ($excludeUnitId !== null) {
            $current = $this->units->findAvailable($excludeUnitId);

            if (
                ! $current
                || (int) $current['organization_id'] !== $organizationId
            ) {
                throw new \RuntimeException(
                    'Unit yang sedang diedit tidak valid.'
                );
            }

            // Unit itu sendiri tidak boleh menjadi parent.
            $excludedIds[$excludeUnitId] = true;

            // Kumpulkan semua keturunan secara bertingkat.
            $childrenByParent = [];

            foreach ($units as $unit) {
                if ($unit['parent_id'] !== null) {
                    $childrenByParent[(int) $unit['parent_id']][] =
                        (int) $unit['id'];
                }
            }

            $queue = [$excludeUnitId];

            while ($queue !== []) {
                $parentId = array_shift($queue);

                foreach ($childrenByParent[$parentId] ?? [] as $childId) {
                    if (isset($excludedIds[$childId])) {
                        continue;
                    }

                    $excludedIds[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_values(array_filter(
            $units,
            static fn(array $unit): bool =>
            ! isset($excludedIds[(int) $unit['id']])
        ));
    }


    public function save(array $data, ?int $id = null): int
    {
        $organizationId = filter_var(
            $data['organization_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($organizationId === false) {
            throw new RuntimeException('Organisasi wajib dipilih.');
        }

        $organization = $this->organizations->findAvailable(
            (int) $organizationId
        );

        if (! $organization || $organization['status'] !== 'active') {
            throw new RuntimeException(
                'Organisasi tidak ditemukan atau tidak aktif.'
            );
        }

        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $unitType = trim((string) ($data['unit_type'] ?? 'department'));
        $status = trim((string) ($data['status'] ?? 'active'));

        $parentRaw = $data['parent_id'] ?? '';

        $parentId = ($parentRaw === '' || $parentRaw === null)
            ? null
            : filter_var(
                $parentRaw,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

        if ($parentId === false) {
            throw new RuntimeException('Parent unit tidak valid.');
        }

        if ($code === '' || mb_strlen($code) > 50) {
            throw new RuntimeException(
                'Kode unit wajib diisi dan maksimal 50 karakter.'
            );
        }

        if ($name === '' || mb_strlen($name) > 150) {
            throw new RuntimeException(
                'Nama unit wajib diisi dan maksimal 150 karakter.'
            );
        }

        if ($unitType === '' || mb_strlen($unitType) > 50) {
            throw new RuntimeException('Jenis unit tidak valid.');
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Status unit tidak valid.');
        }

        if ($id !== null) {
            $current = $this->units->findAvailable($id);

            if (! $current) {
                throw new RuntimeException('Unit organisasi tidak ditemukan.');
            }
        }

        if ($parentId !== null) {
            if ($id !== null && (int) $parentId === $id) {
                throw new RuntimeException(
                    'Unit tidak boleh menjadi parent bagi dirinya sendiri.'
                );
            }

            $parent = $this->units->findAvailable((int) $parentId);

            if (
                ! $parent
                || (int) $parent['organization_id'] !== (int) $organizationId
            ) {
                throw new RuntimeException(
                    'Parent harus berasal dari organisasi yang sama.'
                );
            }

            if ($parent['status'] !== 'active' && $status === 'active') {
                throw new RuntimeException(
                    'Unit aktif tidak boleh berada di bawah parent nonaktif.'
                );
            }

            if ($id !== null && $this->isDescendant($id, (int) $parentId)) {
                throw new RuntimeException(
                    'Parent tidak boleh berasal dari keturunan unit ini.'
                );
            }
        }

        $duplicate = $this->units
            ->where('organization_id', (int) $organizationId)
            ->where('code', $code)
            ->where('deleted_at', null)
            ->first();

        if ($duplicate && (int) $duplicate['id'] !== $id) {
            throw new RuntimeException(
                'Kode unit sudah digunakan dalam organisasi ini.'
            );
        }

        $metadata = $data['metadata'] ?? '';
        $metadata = is_string($metadata) ? trim($metadata) : '';

        if ($metadata !== '') {
            json_decode($metadata);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Metadata harus berupa JSON valid.');
            }
        } else {
            $metadata = null;
        }

        $payload = [
            'organization_id' => (int) $organizationId,
            'parent_id'       => $parentId,
            'code'            => $code,
            'name'            => $name,
            'unit_type'       => $unitType,
            'status'          => $status,
            'metadata'        => $metadata,
        ];

        if ($id !== null) {
            if (! $this->units->update($id, $payload)) {
                throw new RuntimeException(
                    implode(' ', $this->units->errors())
                        ?: 'Gagal memperbarui unit organisasi.'
                );
            }

            return $id;
        }

        $newId = $this->units->insert($payload, true);

        if ($newId === false) {
            throw new RuntimeException(
                implode(' ', $this->units->errors())
                    ?: 'Gagal menambahkan unit organisasi.'
            );
        }

        return (int) $newId;
    }

    public function deactivate(int $id): void
    {
        $unit = $this->units->findAvailable($id);

        if (! $unit) {
            throw new RuntimeException('Unit organisasi tidak ditemukan.');
        }

        // Hindari menonaktifkan parent yang masih memiliki unit aktif.
        $activeChildren = $this->units
            ->where('parent_id', $id)
            ->where('status', 'active')
            ->where('deleted_at', null)
            ->countAllResults();

        if ($activeChildren > 0) {
            throw new RuntimeException(
                'Nonaktifkan unit anak terlebih dahulu.'
            );
        }

        if ($unit['status'] === 'inactive') {
            return;
        }

        if (! $this->units->update($id, ['status' => 'inactive'])) {
            throw new RuntimeException('Gagal menonaktifkan unit organisasi.');
        }
    }

    protected function isDescendant(int $unitId, int $candidateParentId): bool
    {
        $visited = [];
        $currentId = $candidateParentId;

        while ($currentId > 0) {
            if ($currentId === $unitId) {
                return true;
            }

            if (isset($visited[$currentId])) {
                throw new RuntimeException(
                    'Ditemukan siklus pada hierarki unit organisasi.'
                );
            }

            $visited[$currentId] = true;

            $current = $this->units->findAvailable($currentId);

            if (! $current || empty($current['parent_id'])) {
                return false;
            }

            $currentId = (int) $current['parent_id'];
        }

        return false;
    }
}
