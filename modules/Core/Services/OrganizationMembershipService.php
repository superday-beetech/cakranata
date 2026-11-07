<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use CodeIgniter\Exceptions\PageNotFoundException;
use Modules\Core\Models\OrganizationMembershipModel;
use Modules\Core\Models\OrganizationModel;
use Modules\Core\Models\OrganizationUnitModel;
use RuntimeException;
use Throwable;

class OrganizationMembershipService
{
    private OrganizationMembershipModel $memberships;
    private OrganizationModel $organizations;
    private OrganizationUnitModel $units;

    public function __construct()
    {
        $this->memberships  = new OrganizationMembershipModel();
        $this->organizations = new OrganizationModel();
        $this->units         = new OrganizationUnitModel();
    }


    public function getOverviewByUser(int $userId): array
    {
        $user = auth()->getProvider()->findById($userId);

        if ($user === null) {
            throw new RuntimeException('User tidak ditemukan.');
        }

        $memberships = $this->memberships
            ->where('user_id', $userId)
            ->orderBy('is_default', 'DESC')
            ->orderBy('organization_id', 'ASC')
            ->findAll();

        $organizations = [];

        foreach ($memberships as $membership) {
            $organization = $this->organizations->findAvailable(
                (int) $membership['organization_id']
            );

            if ($organization === null) {
                continue;
            }

            $organization['membership_id'] = (int) $membership['id'];
            $organization['user_id'] = (int) $membership['user_id'];
            $organization['unit_id'] = $membership['unit_id'] !== null
                ? (int) $membership['unit_id']
                : null;
            $organization['membership_role'] = $membership['membership_role'];
            $organization['membership_status'] = $membership['status'];
            $organization['is_default'] = (int) $membership['is_default'];

            $organizations[] = $organization;
        }

        return [
            'organizations' => $organizations,
            'memberships'   => $memberships,
        ];
    }


    public function getList(
        int $organizationId = 0,
        string $keyword = '',
        string $status = ''
    ): array {
        $builder = $this->memberships->builder();

        if ($organizationId > 0) {
            $builder->where('organization_id', $organizationId);
        }

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('membership_role', $keyword)
                ->orLike('user_id', $keyword)
                ->groupEnd();
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $builder->where('status', $status);
        }

        return $builder
            ->orderBy('organization_id', 'ASC')
            ->orderBy('user_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getById(int $id): ?array
    {
        return $this->memberships->findAvailable($id);
    }

    public function getActiveOrganizations(): array
    {
        return $this->organizations->getActiveOrganizations();
    }

    public function getActiveUnits(int $organizationId): array
    {
        $organization = $this->organizations->findAvailable(
            $organizationId
        );

        if (
            $organization === null
            || ($organization['status'] ?? '') !== 'active'
        ) {
            throw new RuntimeException(
                'Organisasi tidak tersedia atau tidak aktif.'
            );
        }

        return $this->units->getActiveByOrganization($organizationId);
    }

    public function save(array $data, ?int $id = null): int
    {
        $organizationId = filter_var(
            $data['organization_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $userId = filter_var(
            $data['user_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($organizationId === false || $userId === false) {
            throw new RuntimeException(
                'Organisasi dan user wajib dipilih.'
            );
        }

        $organizationId = (int) $organizationId;
        $userId = (int) $userId;

        $organization = $this->organizations->findAvailable(
            $organizationId
        );

        if (
            $organization === null
            || ($organization['status'] ?? '') !== 'active'
        ) {
            throw new RuntimeException(
                'Organisasi tidak tersedia atau tidak aktif.'
            );
        }

        // Pastikan user benar-benar ada di Shield.
        $user = auth()->getProvider()->findById($userId);

        if ($user === null) {
            throw new RuntimeException(
                'User tidak ditemukan.'
            );
        }

        $role = trim((string) ($data['membership_role'] ?? 'member'));
        $status = (string) ($data['status'] ?? 'active');
        $isDefault = (int) ($data['is_default'] ?? 0) === 1 ? 1 : 0;

        if ($role === '' || mb_strlen($role) > 50) {
            throw new RuntimeException(
                'Role keanggotaan wajib diisi, maksimal 50 karakter.'
            );
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException(
                'Status keanggotaan tidak valid.'
            );
        }

        // User nonaktif tidak boleh menjadi organisasi default.
        if ($status !== 'active') {
            $isDefault = 0;
        }

        $unitId = null;
        $unitRaw = $data['unit_id'] ?? '';

        if ($unitRaw !== '' && $unitRaw !== null) {
            $validatedUnitId = filter_var(
                $unitRaw,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($validatedUnitId === false) {
                throw new RuntimeException(
                    'Unit organisasi tidak valid.'
                );
            }

            $unitId = (int) $validatedUnitId;
            $unit = $this->units->findAvailable($unitId);

            if (
                $unit === null
                || (int) $unit['organization_id'] !== $organizationId
                || ($unit['status'] ?? '') !== 'active'
            ) {
                throw new RuntimeException(
                    'Unit harus aktif dan berasal dari organisasi yang dipilih.'
                );
            }
        }

        $db = db_connect();
        $db->transBegin();

        try {
            if ($id !== null) {
                $existing = $this->memberships->findAvailable($id);

                if ($existing === null) {
                    throw PageNotFoundException::forPageNotFound(
                        'Data membership tidak ditemukan.'
                    );
                }

                // Hindari pemindahan membership secara diam-diam.
                if (
                    (int) $existing['organization_id'] !== $organizationId
                    || (int) $existing['user_id'] !== $userId
                ) {
                    throw new RuntimeException(
                        'Organisasi dan user pada membership yang sudah ada tidak boleh diganti.'
                    );
                }
            } else {
                // Cegah membership aktif ganda untuk user dan organisasi.
                $duplicate = $db->table('core_organization_memberships')
                    ->where('organization_id', $organizationId)
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->countAllResults();

                if ($status === 'active' && $duplicate > 0) {
                    throw new RuntimeException(
                        'User sudah memiliki membership aktif di organisasi ini.'
                    );
                }
            }

            // Hanya satu membership default aktif per user.
            if ($isDefault === 1) {
                $db->table('core_organization_memberships')
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->where('is_default', 1)
                    ->where('id !=', $id ?? 0)
                    ->update(['is_default' => 0]);
            }

            $payload = [
                'organization_id' => $organizationId,
                'user_id'         => $userId,
                'unit_id'         => $unitId,
                'membership_role' => $role,
                'status'          => $status,
                'is_default'      => $isDefault,
            ];

            if ($id === null) {
                $this->memberships->insert($payload);
                $savedId = (int) $this->memberships->getInsertID();
            } else {
                $this->memberships->update($id, $payload);
                $savedId = $id;
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menyimpan membership.'
                );
            }

            $db->transCommit();

            return $savedId;
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    public function deactivate(int $id): void
    {
        $membership = $this->memberships->findAvailable($id);

        if ($membership === null) {
            throw PageNotFoundException::forPageNotFound(
                'Data membership tidak ditemukan.'
            );
        }

        $db = db_connect();

        $db->table('core_organization_memberships')
            ->where('id', $id)
            ->update([
                'status'     => 'inactive',
                'is_default' => 0,
            ]);

        if ($db->transStatus() === false) {
            throw new RuntimeException(
                'Gagal menonaktifkan membership.'
            );
        }
    }
}
