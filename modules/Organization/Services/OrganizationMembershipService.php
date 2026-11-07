<?php

declare(strict_types=1);

namespace Modules\Organization\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use InvalidArgumentException;
use RuntimeException;
use Modules\Organization\Models\OrganizationModel;
use Modules\Organization\Models\OrganizationUnitModel;
use Modules\Organization\Models\OrganizationMembershipModel;
use CodeIgniter\Shield\Models\UserModel;

class OrganizationMembershipService
{
    private BaseConnection $db;

    private OrganizationModel $organizations;

    private OrganizationUnitModel $units;

    private OrganizationMembershipModel $memberships;

    public function __construct()
    {
        $this->db = Database::connect();

        $this->organizations = new OrganizationModel();
        $this->units = new OrganizationUnitModel();
        $this->memberships = new OrganizationMembershipModel();
    }

    /**
     * Daftar user dari CodeIgniter Shield.
     */
    public function listUsers(
        int $page = 1,
        int $perPage = 10,
        ?string $search = null
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $builder = $this->db->table('users')
            ->select('id, username');

        if ($search !== null && trim($search) !== '') {
            $builder->like('username', trim($search));
        }

        $total = $builder->countAllResults(false);

        $items = $builder
            ->orderBy('username', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Daftar membership dalam satu organisasi.
     */

    public function listForOrganization(
        int $organizationId,
        int $page = 1,
        int $perPage = 10,
        ?string $search = null,
        ?string $status = null
    ): array {
        $this->validateOrganization($organizationId);

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $builder = $this->db->table('core_organization_memberships m')
            ->select([
                'm.id',
                'm.organization_id',
                'm.user_id',
                'm.unit_id',
                'm.membership_role',
                'm.status',
                'm.is_default',
                'm.created_at',
                'm.updated_at',
                'u.username',
                'ou.name AS unit_name',
            ])
            ->join('users u', 'u.id = m.user_id', 'left')
            ->join(
                'core_organization_units ou',
                'ou.id = m.unit_id',
                'left'
            )
            ->where('m.organization_id', $organizationId);

        if ($status !== null && $status !== '') {
            $builder->where('m.status', $status);
        }

        if ($search !== null && trim($search) !== '') {
            $keyword = trim($search);

            $builder->groupStart()
                ->like('u.username', $keyword)
                ->orLike('m.membership_role', $keyword)
                ->orLike('m.user_id', $keyword)
                ->orLike('ou.name', $keyword)
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);

        $items = $builder
            ->orderBy('m.is_default', 'DESC')
            ->orderBy('m.id', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }


    /**
     * Detail membership dalam satu organisasi.
     */
    public function findForOrganization(
        int $organizationId,
        int $membershipId
    ): ?array {
        $this->validateOrganization($organizationId);

        $membership = $this->memberships
            ->where('organization_id', $organizationId)
            ->where('id', $membershipId)
            ->first();

        return $membership ?: null;
    }

    /**
     * Membuat membership baru.
     */
    public function create(
        int $organizationId,
        array $data
    ): int {
        $this->validateOrganization($organizationId);

        $payload = $this->normalizeData($data, true);

        $this->validateUnit(
            $organizationId,
            $payload['unit_id']
        );

        $existing = $this->memberships
            ->where('organization_id', $organizationId)
            ->where('user_id', $payload['user_id'])
            ->first();

        if ($existing) {
            throw new InvalidArgumentException(
                'User sudah terdaftar dalam organisasi ini.'
            );
        }

        $payload['organization_id'] = $organizationId;

        $this->db->transBegin();

        try {
            $membershipId = $this->memberships->insert($payload);

            if ($membershipId === false) {
                throw new RuntimeException(
                    $this->getModelErrors()
                );
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menyimpan membership.'
                );
            }

            $this->db->transCommit();

            return (int) $membershipId;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Memperbarui membership.
     */
    public function update(
        int $organizationId,
        int $membershipId,
        array $data
    ): bool {
        $this->validateOrganization($organizationId);

        $membership = $this->findForOrganization(
            $organizationId,
            $membershipId
        );

        if (!$membership) {
            throw new RuntimeException(
                'Membership tidak ditemukan.'
            );
        }

        $payload = $this->normalizeData($data, false);

        $this->validateUnit(
            $organizationId,
            $payload['unit_id'] ?? $membership['unit_id']
        );

        if (isset($payload['user_id'])) {
            $existing = $this->memberships
                ->where('organization_id', $organizationId)
                ->where('user_id', $payload['user_id'])
                ->where('id !=', $membershipId)
                ->first();

            if ($existing) {
                throw new InvalidArgumentException(
                    'User sudah terdaftar dalam organisasi ini.'
                );
            }
        }

        if (empty($payload)) {
            throw new InvalidArgumentException(
                'Tidak ada data yang diperbarui.'
            );
        }

        $this->db->transBegin();

        try {
            $updated = $this->memberships->update(
                $membershipId,
                $payload
            );

            if (!$updated) {
                throw new RuntimeException(
                    $this->getModelErrors()
                );
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal memperbarui membership.'
                );
            }

            $this->db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Menonaktifkan membership.
     */
    public function deactivate(
        int $organizationId,
        int $membershipId
    ): bool {
        $this->validateOrganization($organizationId);

        $membership = $this->findForOrganization(
            $organizationId,
            $membershipId
        );

        if (!$membership) {
            throw new RuntimeException(
                'Membership tidak ditemukan.'
            );
        }

        if ($membership['status'] === 'inactive') {
            throw new InvalidArgumentException(
                'Membership sudah tidak aktif.'
            );
        }

        return $this->memberships->update(
            $membershipId,
            ['status' => 'inactive']
        );
    }

    /**
     * Normalisasi dan validasi payload.
     */
    private function normalizeData(
        array $data,
        bool $creating
    ): array {
        $allowedFields = [
            'user_id',
            'unit_id',
            'membership_role',
            'status',
            'is_default',
        ];

        $payload = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if ($creating && empty($payload['user_id'])) {
            throw new InvalidArgumentException(
                'User wajib dipilih.'
            );
        }

        if (isset($payload['user_id'])) {
            $payload['user_id'] = (int) $payload['user_id'];

            if ($payload['user_id'] <= 0) {
                throw new InvalidArgumentException(
                    'User ID tidak valid.'
                );
            }
        }

        if (array_key_exists('unit_id', $payload)) {
            $payload['unit_id'] = $payload['unit_id'] !== null
                && $payload['unit_id'] !== ''
                ? (int) $payload['unit_id']
                : null;

            if ($payload['unit_id'] !== null && $payload['unit_id'] <= 0) {
                throw new InvalidArgumentException(
                    'Unit ID tidak valid.'
                );
            }
        } elseif ($creating) {
            $payload['unit_id'] = null;
        }

        if (isset($payload['membership_role'])) {
            $payload['membership_role'] = trim(
                (string) $payload['membership_role']
            );

            if ($payload['membership_role'] === '') {
                throw new InvalidArgumentException(
                    'Role membership tidak boleh kosong.'
                );
            }
        } elseif ($creating) {
            $payload['membership_role'] = 'member';
        }

        if (isset($payload['status'])) {
            if (!in_array($payload['status'], ['active', 'inactive'], true)) {
                throw new InvalidArgumentException(
                    'Status membership tidak valid.'
                );
            }
        } elseif ($creating) {
            $payload['status'] = 'active';
        }

        if (array_key_exists('is_default', $payload)) {
            $payload['is_default'] = filter_var(
                $payload['is_default'],
                FILTER_VALIDATE_BOOLEAN
            ) ? 1 : 0;
        } elseif ($creating) {
            $payload['is_default'] = 0;
        }

        return $payload;
    }

    /**
     * Memastikan organisasi tersedia.
     */
    private function validateOrganization(int $organizationId): array
    {
        if ($organizationId <= 0) {
            throw new InvalidArgumentException(
                'Organization ID tidak valid.'
            );
        }

        $organization = $this->organizations->find($organizationId);

        if (!$organization || $organization['status'] !== 'active') {
            throw new RuntimeException(
                'Organisasi tidak ditemukan atau tidak aktif.'
            );
        }

        return $organization;
    }

    /**
     * Memastikan unit termasuk dalam organisasi yang dipilih.
     */
    private function validateUnit(
        int $organizationId,
        ?int $unitId
    ): void {
        if ($unitId === null) {
            return;
        }

        $unit = $this->units->find($unitId);

        if (
            !$unit ||
            (int) $unit['organization_id'] !== $organizationId
        ) {
            throw new InvalidArgumentException(
                'Unit organisasi tidak valid.'
            );
        }

        if ($unit['status'] !== 'active') {
            throw new InvalidArgumentException(
                'Unit organisasi tidak aktif.'
            );
        }
    }

    /**
     * Mengambil pesan validasi model.
     */
    private function getModelErrors(): string
    {
        $errors = $this->memberships->errors();

        return !empty($errors)
            ? implode(' ', $errors)
            : 'Terjadi kesalahan saat menyimpan membership.';
    }
}
