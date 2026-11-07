<?php

declare(strict_types=1);

namespace Modules\Organization\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Modules\Organization\Models\OrganizationMembershipModel;
use Modules\Organization\Models\OrganizationModel;
use Modules\Organization\Models\OrganizationUnitModel;
use RuntimeException;
use Throwable;

class OrganizationService
{
    private BaseConnection $db;
    private OrganizationUnitModel $units;
    public function __construct(
        private readonly OrganizationModel $organizations = new OrganizationModel(),
        private readonly OrganizationMembershipModel $memberships = new OrganizationMembershipModel(),
        ?OrganizationUnitModel $units = null,
    ) {
        $this->db = Database::connect();
        $this->units = $units ?? new OrganizationUnitModel();
    }

    public function findActive(int $organizationId): ?array
    {
        return $this->organizations
            ->where('id', $organizationId)
            ->where('status', 'active')
            ->first();
    }

    public function findForUser(
        int $userId,
        int $organizationId
    ): ?array {
        return $this->memberships
            ->select([
                'core_organization_memberships.*',
                'core_organizations.code',
                'core_organizations.slug',
                'core_organizations.name',
                'core_organizations.legal_name',
                'core_organizations.organization_type',
                'core_organizations.timezone',
                'core_organizations.currency',
            ])
            ->join(
                'core_organizations',
                'core_organizations.id = core_organization_memberships.organization_id'
            )
            ->where('core_organization_memberships.user_id', $userId)
            ->where('core_organization_memberships.organization_id', $organizationId)
            ->where('core_organization_memberships.status', 'active')
            ->where('core_organizations.status', 'active')
            ->where('core_organizations.deleted_at', null)
            ->first();
    }

    public function create(array $data, int $ownerUserId): int
    {
        if ($ownerUserId <= 0) {
            throw new RuntimeException('User pemilik organisasi tidak valid.');
        }

        $organizationData = [
            'code' => strtoupper(trim($data['code'] ?? '')),
            'slug' => strtolower(trim($data['slug'] ?? '')),
            'name' => trim($data['name'] ?? ''),
            'legal_name' => trim($data['legal_name'] ?? '') ?: null,
            'organization_type' => trim($data['organization_type'] ?? 'business'),
            'status' => 'active',
            'timezone' => trim($data['timezone'] ?? 'Asia/Jakarta'),
            'currency' => strtoupper(trim($data['currency'] ?? 'IDR')),
            'settings' => null,
        ];

        $this->validateOrganizationData($organizationData);

        $this->db->transBegin();

        try {
            // Pastikan seluruh model menggunakan koneksi transaksi.
            // $this->organizations->setConnection($this->db);
            // $this->memberships->setConnection($this->db);

            $organizationId = $this->organizations->insert(
                $organizationData,
                true
            );

            if ($organizationId === false) {
                throw new RuntimeException('Gagal membuat organisasi.');
            }

            // Satu user hanya boleh memiliki satu default organization.
            $this->memberships
                ->where('user_id', $ownerUserId)
                ->set(['is_default' => 0])
                ->update();

            $membershipId = $this->memberships->insert([
                'organization_id' => $organizationId,
                'user_id' => $ownerUserId,
                'unit_id' => null,
                'membership_role' => 'owner',
                'status' => 'active',
                'is_default' => 1,
            ]);

            if ($membershipId === false) {
                throw new RuntimeException(
                    'Gagal membuat membership pemilik organisasi.'
                );
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Transaksi organisasi gagal.');
            }

            $this->db->transCommit();

            return (int) $organizationId;
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }
    }

    public function update(
        int $organizationId,
        int $userId,
        array $data
    ): bool {
        $membership = $this->findForUser($userId, $organizationId);

        if ($membership === null) {
            throw new RuntimeException('Organisasi tidak ditemukan atau tidak dapat diakses.');
        }

        /* if (!in_array($membership['membership_role'], ['superadmin', 'owner', 'admin'], true)) {
            throw new RuntimeException('Tidak memiliki kewenangan mengubah organisasi.');
        } */

        $allowed = [
            'name',
            'legal_name',
            'organization_type',
            'timezone',
            'currency',
        ];

        $updateData = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $field === 'legal_name'
                    ? (trim((string) $data[$field]) ?: null)
                    : trim((string) $data[$field]);
            }
        }

        if ($updateData === []) {
            throw new RuntimeException('Tidak ada data organisasi yang diperbarui.');
        }

        $this->validateOrganizationData(
            array_merge($membership, $updateData)
        );

        return $this->organizations->update(
            $organizationId,
            $updateData
        ) !== false;
    }

    public function userCanAccess(
        int $userId,
        int $organizationId
    ): bool {
        return $this->findForUser($userId, $organizationId) !== null;
    }

    public function userCanManage(
        int $userId,
        int $organizationId
    ): bool {
        $membership = $this->findForUser($userId, $organizationId);

        return $membership !== null
            && in_array(
                $membership['membership_role'],
                ['owner', 'admin'],
                true
            );
    }

    public function listForUser(int $userId): array
    {
        return $this->memberships
            ->select([
                'core_organizations.id',
                'core_organizations.code',
                'core_organizations.slug',
                'core_organizations.name',
                'core_organizations.organization_type',
                'core_organizations.status',
                'core_organization_memberships.membership_role',
                'core_organization_memberships.is_default',
            ])
            ->join(
                'core_organizations',
                'core_organizations.id = core_organization_memberships.organization_id'
            )
            ->where('core_organization_memberships.user_id', $userId)
            ->where('core_organization_memberships.status', 'active')
            ->where('core_organizations.status', 'active')
            ->where('core_organizations.deleted_at', null)
            ->orderBy('core_organization_memberships.is_default', 'DESC')
            ->orderBy('core_organizations.name', 'ASC')
            ->findAll();
    }


    /**
     * Daftar unit dalam organisasi.
     */
    public function listUnits(
        int $userId,
        int $organizationId,
        bool $includeInactive = false
    ): array {
        if (! $this->userCanAccess($userId, $organizationId)) {
            throw new RuntimeException('Akses organisasi tidak tersedia.');
        }

        $builder = $this->units
            ->where('organization_id', $organizationId);

        if (! $includeInactive) {
            $builder->where('status', 'active');
        }

        return $builder
            ->orderBy('parent_id', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Detail satu unit dalam organisasi.
     */
    public function findUnit(
        int $organizationId,
        int $unitId
    ): ?array {
        return $this->units
            ->where('organization_id', $organizationId)
            ->where('id', $unitId)
            ->first();
    }

    /**
     * Membuat unit organisasi.
     */
    public function createUnit(
        int $userId,
        int $organizationId,
        array $data
    ): int {
        if (! $this->userCanManage($userId, $organizationId)) {
            throw new RuntimeException('Tidak memiliki kewenangan mengelola unit organisasi.');
        }

        $unitData = $this->normalizeUnitData($data, true);

        $this->validateParentUnit(
            $organizationId,
            $unitData['parent_id']
        );

        $unitId = $this->units->insert([
            'organization_id' => $organizationId,
            ...$unitData,
        ], true);

        if ($unitId === false) {
            throw new RuntimeException('Gagal membuat unit organisasi.');
        }

        return (int) $unitId;
    }

    /**
     * Mengubah unit organisasi.
     */
    public function updateUnit(
        int $userId,
        int $organizationId,
        int $unitId,
        array $data
    ): bool {
        if (! $this->userCanManage($userId, $organizationId)) {
            throw new RuntimeException('Tidak memiliki kewenangan mengelola unit organisasi.');
        }

        $unit = $this->findUnit($organizationId, $unitId);

        if ($unit === null) {
            throw new RuntimeException('Unit organisasi tidak ditemukan.');
        }

        $unitData = $this->normalizeUnitData($data, false);

        if (array_key_exists('parent_id', $unitData)) {
            $this->validateParentUnit(
                $organizationId,
                $unitData['parent_id'],
                $unitId
            );
        }

        return $this->units->update($unitId, $unitData) !== false;
    }

    /**
     * Menonaktifkan unit organisasi.
     *
     * Unit tidak dihapus secara fisik agar riwayat relasi tetap terjaga.
     */
    public function deactivateUnit(
        int $userId,
        int $organizationId,
        int $unitId
    ): bool {
        if (! $this->userCanManage($userId, $organizationId)) {
            throw new RuntimeException('Tidak memiliki kewenangan mengelola unit organisasi.');
        }

        $unit = $this->findUnit($organizationId, $unitId);

        if ($unit === null) {
            throw new RuntimeException('Unit organisasi tidak ditemukan.');
        }

        $hasActiveChildren = $this->units
            ->where('organization_id', $organizationId)
            ->where('parent_id', $unitId)
            ->where('status', 'active')
            ->countAllResults() > 0;

        if ($hasActiveChildren) {
            throw new RuntimeException(
                'Unit tidak dapat dinonaktifkan karena masih memiliki unit aktif di bawahnya.'
            );
        }

        $hasMembership = $this->memberships
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->countAllResults() > 0;

        if ($hasMembership) {
            throw new RuntimeException(
                'Unit tidak dapat dinonaktifkan karena masih digunakan oleh anggota organisasi.'
            );
        }

        return $this->units->update($unitId, [
            'status' => 'inactive',
        ]) !== false;
    }

    /**
     * Normalisasi dan validasi dasar data unit.
     */
    private function normalizeUnitData(
        array $data,
        bool $creating
    ): array {
        $allowed = [
            'parent_id',
            'code',
            'name',
            'unit_type',
            'status',
            'metadata',
        ];

        $data = array_intersect_key($data, array_flip($allowed));

        if ($creating) {
            $data['parent_id'] = $data['parent_id'] ?? null;
            $data['code'] = strtoupper(trim((string) ($data['code'] ?? '')));
            $data['name'] = trim((string) ($data['name'] ?? ''));
            $data['unit_type'] = trim((string) ($data['unit_type'] ?? 'department'));
            $data['status'] = trim((string) ($data['status'] ?? 'active'));
        }

        if (array_key_exists('parent_id', $data)) {
            $parentId = $data['parent_id'];

            if ($parentId === null || $parentId === '') {
                $data['parent_id'] = null;
            } elseif (filter_var($parentId, FILTER_VALIDATE_INT) !== false && (int) $parentId > 0) {
                $data['parent_id'] = (int) $parentId;
            } else {
                throw new RuntimeException('Parent unit tidak valid.');
            }
        }

        foreach (['code', 'name', 'unit_type', 'status'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if (! is_string($data[$field])) {
                throw new RuntimeException("Format field {$field} tidak valid.");
            }

            $data[$field] = trim($data[$field]);
        }

        if (isset($data['code']) && (
            $data['code'] === '' || strlen($data['code']) > 50
        )) {
            throw new RuntimeException('Kode unit wajib diisi, maksimal 50 karakter.');
        }

        if (isset($data['name']) && (
            $data['name'] === '' || strlen($data['name']) > 150
        )) {
            throw new RuntimeException('Nama unit wajib diisi, maksimal 150 karakter.');
        }

        if (isset($data['unit_type']) && (
            $data['unit_type'] === '' || strlen($data['unit_type']) > 50
        )) {
            throw new RuntimeException('Jenis unit tidak valid.');
        }

        if (isset($data['status']) && ! in_array(
            $data['status'],
            ['active', 'inactive'],
            true
        )) {
            throw new RuntimeException('Status unit tidak valid.');
        }

        if (array_key_exists('metadata', $data)) {
            if ($data['metadata'] !== null && ! is_array($data['metadata'])) {
                throw new RuntimeException('Metadata harus berupa objek JSON.');
            }

            $data['metadata'] = $data['metadata'] === null
                ? null
                : json_encode($data['metadata'], JSON_THROW_ON_ERROR);
        }

        if ($data === []) {
            throw new RuntimeException('Tidak ada data unit yang diperbarui.');
        }

        return $data;
    }

    /**
     * Memastikan parent berada dalam organisasi yang sama
     * dan tidak membuat siklus hierarki.
     */
    private function validateParentUnit(
        int $organizationId,
        ?int $parentId,
        ?int $currentUnitId = null
    ): void {
        if ($parentId === null) {
            return;
        }

        if ($currentUnitId !== null && $parentId === $currentUnitId) {
            throw new RuntimeException('Unit tidak dapat menjadi parent bagi dirinya sendiri.');
        }

        $parent = $this->findUnit($organizationId, $parentId);

        if ($parent === null || $parent['status'] !== 'active') {
            throw new RuntimeException('Parent unit tidak ditemukan atau tidak aktif.');
        }

        $visited = [];
        $cursor = $parent;

        while ($cursor !== null) {
            $cursorId = (int) $cursor['id'];

            if (isset($visited[$cursorId])) {
                throw new RuntimeException('Struktur hierarki unit sudah memiliki siklus.');
            }

            $visited[$cursorId] = true;

            if ($currentUnitId !== null && $cursorId === $currentUnitId) {
                throw new RuntimeException('Parent unit tidak boleh merupakan turunan dari unit ini.');
            }

            $nextParentId = $cursor['parent_id'] ?? null;

            if ($nextParentId === null) {
                break;
            }

            $cursor = $this->findUnit(
                $organizationId,
                (int) $nextParentId
            );

            if ($cursor === null) {
                throw new RuntimeException('Struktur parent unit tidak valid.');
            }
        }
    }

    private function validateOrganizationData(array $data): void
    {
        if (empty($data['code']) || strlen($data['code']) > 50) {
            throw new RuntimeException('Kode organisasi wajib diisi, maksimal 50 karakter.');
        }

        if (empty($data['slug']) || strlen($data['slug']) > 100) {
            throw new RuntimeException('Slug organisasi wajib diisi, maksimal 100 karakter.');
        }

        if (empty($data['name']) || strlen($data['name']) > 150) {
            throw new RuntimeException('Nama organisasi wajib diisi, maksimal 150 karakter.');
        }

        if (empty($data['organization_type'])) {
            throw new RuntimeException('Jenis organisasi wajib diisi.');
        }

        if (empty($data['timezone']) || strlen($data['timezone']) > 64) {
            throw new RuntimeException('Zona waktu organisasi tidak valid.');
        }

        if (!preg_match('/^[A-Z]{3}$/', $data['currency'] ?? '')) {
            throw new RuntimeException('Kode mata uang harus terdiri dari 3 huruf kapital.');
        }
    }
}
