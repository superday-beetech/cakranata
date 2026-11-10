<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Config\Database;
use Modules\Core\Models\OrganizationMembershipModel;
use Modules\Core\Models\OrganizationModel;
use Modules\Core\Models\OrganizationUnitModel;
use RuntimeException;
use Throwable;

class OrganizationMembershipService
{
    private $db;
    private OrganizationMembershipModel $membershipModel;
    private OrganizationModel $organizationModel;
    private OrganizationUnitModel $unitModel;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->membershipModel = new OrganizationMembershipModel();
        $this->organizationModel = new OrganizationModel();
        $this->unitModel = new OrganizationUnitModel();
    }

    public function getOverview(int $userId): array
    {
        if ($userId < 1) {
            throw new RuntimeException('User ID tidak valid.');
        }

        return [
            'userId' => $userId,
            'organizations' => $this->organizationModel
                ->getActiveOrganizations(),
            'memberships' => $this->getMembershipsWithNames($userId),
        ];
    }

    private function getMembershipsWithNames(int $userId): array
    {
        return $this->db->table('core_organization_memberships m')
            ->select(
                'm.id, m.organization_id, m.unit_id, m.membership_role, ' .
                    'm.status, m.is_default, o.name AS organization_name, ' .
                    'u.name AS unit_name'
            )
            ->join(
                'core_organizations o',
                'o.id = m.organization_id'
            )
            ->join(
                'core_organization_units u',
                'u.id = m.unit_id',
                'left'
            )
            ->where('m.user_id', $userId)
            ->orderBy('m.is_default', 'DESC')
            ->orderBy('o.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function save(int $userId, array $data): void
    {
        if ($userId < 1) {
            throw new RuntimeException('User ID tidak valid.');
        }

        $organizationId = filter_var(
            $data['organization_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $unitId = trim((string) ($data['unit_id'] ?? ''));

        $role = trim((string) ($data['membership_role'] ?? 'member'));
        $status = trim((string) ($data['status'] ?? 'active'));
        $isDefault = filter_var(
            $data['is_default'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if ($organizationId === false || $organizationId === null || $organizationId < 1) {
            throw new RuntimeException('Organisasi wajib dipilih.');
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Status keanggotaan tidak valid.');
        }

        if ($role === '' || mb_strlen($role) > 50) {
            throw new RuntimeException('Peran keanggotaan tidak valid.');
        }

        $organization = $this->organizationModel
            ->where('id', $organizationId)
            ->where('status', 'active')
            ->where('deleted_at', null)
            ->first();

        if ($organization === null) {
            throw new RuntimeException('Organisasi tidak ditemukan atau tidak aktif.');
        }

        $unitIdValue = null;

        if ($unitId !== '') {
            $validatedUnitId = filter_var($unitId, FILTER_VALIDATE_INT);

            if ($validatedUnitId === false || $validatedUnitId < 1) {
                throw new RuntimeException('Unit organisasi tidak valid.');
            }

            $unit = $this->unitModel
                ->where('id', $validatedUnitId)
                ->where('organization_id', $organizationId)
                ->where('status', 'active')
                ->where('deleted_at', null)
                ->first();

            if ($unit === null) {
                throw new RuntimeException(
                    'Unit tidak aktif atau bukan bagian dari organisasi yang dipilih.'
                );
            }

            $unitIdValue = $validatedUnitId;
        }

        $existing = $this->db->table('core_organization_memberships')
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->get()
            ->getRowArray();

        $membershipData = [
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'unit_id' => $unitIdValue,
            'membership_role' => $role,
            'status' => $status,
            'is_default' => $isDefault ? 1 : 0,
        ];

        $this->db->transBegin();

        try {
            // Default hanya boleh menunjuk ke keanggotaan aktif.
            if ($isDefault && $status !== 'active') {
                throw new RuntimeException(
                    'Keanggotaan nonaktif tidak dapat dijadikan default.'
                );
            }

            // Jika ini organisasi default, kosongkan default sebelumnya.
            if ($isDefault) {
                $this->db->table('core_organization_memberships')
                    ->where('user_id', $userId)
                    ->update(['is_default' => 0]);
            }

            if ($existing !== null) {
                $ok = $this->db->table('core_organization_memberships')
                    ->where('id', $existing['id'])
                    ->update($membershipData);
            } else {
                $ok = $this->db->table('core_organization_memberships')
                    ->insert($membershipData);
            }

            if ($ok === false || $this->db->transStatus() === false) {
                throw new RuntimeException('Gagal menyimpan keanggotaan organisasi.');
            }

            if ($this->db->transCommit() === false) {
                throw new RuntimeException('Gagal menyelesaikan transaksi.');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function delete(int $userId, int $membershipId): void
    {
        if ($userId < 1 || $membershipId < 1) {
            throw new RuntimeException('ID tidak valid.');
        }

        $membership = $this->db->table('core_organization_memberships')
            ->where('id', $membershipId)
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if ($membership === null) {
            throw new RuntimeException('Keanggotaan tidak ditemukan.');
        }

        if (
            $this->db->table('core_organization_memberships')
            ->where('user_id', $userId)
            ->countAllResults() <= 1
        ) {
            throw new RuntimeException(
                'Keanggotaan terakhir tidak dapat dihapus.'
            );
        }

        $this->db->transBegin();

        try {
            $deleted = $this->db->table('core_organization_memberships')
                ->where('id', $membershipId)
                ->where('user_id', $userId)
                ->delete();

            if ($deleted === false || $this->db->transStatus() === false) {
                throw new RuntimeException('Gagal menghapus keanggotaan.');
            }

            // Jika default dihapus, jadikan salah satu anggota aktif sebagai default.
            if ((int) $membership['is_default'] === 1) {
                $next = $this->db->table('core_organization_memberships')
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->orderBy('id', 'ASC')
                    ->get(1)
                    ->getRowArray();

                if ($next !== null) {
                    $this->db->table('core_organization_memberships')
                        ->where('id', $next['id'])
                        ->update(['is_default' => 1]);
                }
            }

            if ($this->db->transCommit() === false) {
                throw new RuntimeException('Gagal menyelesaikan transaksi.');
            }
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
}
