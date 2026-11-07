<?php

declare(strict_types=1);

namespace Modules\Organization\Services;

use Config\Database;
use InvalidArgumentException;

class OrganizationContextService
{
    private const SESSION_KEY = 'organization_context';

    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Mengambil seluruh organisasi aktif yang dimiliki user
     * melalui membership aktif.
     */
    public function getAvailableOrganizations(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return $this->db
            ->table('core_organization_memberships m')
            ->select([
                'm.id AS membership_id',
                'm.organization_id',
                'm.user_id',
                'm.unit_id',
                'm.membership_role',
                'm.is_default',
                'o.code AS organization_code',
                'o.name AS organization_name',
                'o.organization_type',
                'o.timezone',
                'o.currency',
                'ou.name AS unit_name',
            ])
            ->join(
                'core_organizations o',
                'o.id = m.organization_id',
                'inner'
            )
            ->join(
                'core_organization_units ou',
                'ou.id = m.unit_id',
                'left'
            )
            ->where('m.user_id', $userId)
            ->where('m.status', 'active')
            ->where('o.status', 'active')
            ->where('o.deleted_at', null)
            ->orderBy('m.is_default', 'DESC')
            ->orderBy('o.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Menentukan konteks awal berdasarkan membership user.
     *
     * Aturan:
     * - Satu organisasi: otomatis dipilih.
     * - Beberapa organisasi dengan satu default: pilih default.
     * - Beberapa organisasi tanpa default atau default ganda:
     *   user harus memilih sendiri.
     */
    public function resolveInitialContext(int $userId): ?array
    {
        $organizations = $this->getAvailableOrganizations($userId);

        if ($organizations === []) {
            $this->clearContext();

            return null;
        }

        if (count($organizations) === 1) {
            return $this->switchOrganization(
                $userId,
                (int) $organizations[0]['organization_id']
            );
        }

        $defaults = array_values(array_filter(
            $organizations,
            static fn(array $item): bool =>
            (int) $item['is_default'] === 1
        ));

        if (count($defaults) === 1) {
            return $this->switchOrganization(
                $userId,
                (int) $defaults[0]['organization_id']
            );
        }

        $this->clearContext();

        return null;
    }

    /**
     * Memilih organisasi aktif.
     *
     * Organization ID harus divalidasi melalui membership user.
     */
    public function switchOrganization(
        int $userId,
        int $organizationId
    ): array {
        if ($userId <= 0 || $organizationId <= 0) {
            throw new InvalidArgumentException(
                'User atau organisasi tidak valid.'
            );
        }

        $context = $this->loadOrganizationContext(
            $userId,
            $organizationId
        );

        if ($context === null) {
            throw new InvalidArgumentException(
                'Organisasi tidak ditemukan atau user tidak memiliki membership aktif.'
            );
        }

        // Simpan hanya ID. Informasi lainnya selalu dibaca ulang
        // dari database untuk menghindari data session kedaluwarsa.
        session()->set(self::SESSION_KEY, [
            'organization_id' => $context['organization']['id'],
            'membership_id' => $context['membership']['id'],
        ]);

        return $context;
    }

    /**
     * Mengambil konteks organisasi yang sedang aktif.
     *
     * Membership divalidasi ulang setiap kali konteks dibaca.
     */
    public function getCurrentContext(int $userId): ?array
    {
        $sessionContext = session()->get(self::SESSION_KEY);

        if (
            ! is_array($sessionContext)
            || empty($sessionContext['organization_id'])
        ) {
            return null;
        }

        $organizationId = (int) $sessionContext['organization_id'];

        $context = $this->loadOrganizationContext(
            $userId,
            $organizationId
        );

        if (
            $context === null
            || (int) $context['membership']['id']
            !== (int) ($sessionContext['membership_id'] ?? 0)
        ) {
            $this->clearContext();

            return null;
        }

        return $context;
    }

    /**
     * Menghapus organisasi aktif dari session.
     */
    public function clearContext(): void
    {
        session()->remove(self::SESSION_KEY);
    }

    /**
     * Memuat dan memvalidasi organisasi, membership,
     * serta unit yang terhubung.
     */
    private function loadOrganizationContext(
        int $userId,
        int $organizationId
    ): ?array {
        $membership = $this->db
            ->table('core_organization_memberships m')
            ->select([
                'm.id AS membership_id',
                'm.organization_id',
                'm.user_id',
                'm.unit_id',
                'm.membership_role',
                'm.status AS membership_status',
                'm.is_default',
                'o.code AS organization_code',
                'o.name AS organization_name',
                'o.organization_type',
                'o.timezone',
                'o.currency',
            ])
            ->join(
                'core_organizations o',
                'o.id = m.organization_id',
                'inner'
            )
            ->where('m.user_id', $userId)
            ->where('m.organization_id', $organizationId)
            ->where('m.status', 'active')
            ->where('o.status', 'active')
            ->where('o.deleted_at', null)
            ->get()
            ->getRowArray();

        if ($membership === null) {
            return null;
        }

        $unit = null;

        if ($membership['unit_id'] !== null) {
            $unit = $this->db
                ->table('core_organization_units')
                ->where('id', $membership['unit_id'])
                ->where('organization_id', $organizationId)
                ->where('status', 'active')
                ->where('deleted_at', null)
                ->get()
                ->getRowArray();

            if ($unit === null) {
                throw new InvalidArgumentException(
                    'Unit membership tidak ditemukan atau sudah tidak aktif.'
                );
            }
        }

        return [
            'organization' => [
                'id' => (int) $membership['organization_id'],
                'code' => $membership['organization_code'],
                'name' => $membership['organization_name'],
                'organization_type' => $membership['organization_type'],
                'timezone' => $membership['timezone'],
                'currency' => $membership['currency'],
            ],

            'unit' => $unit === null ? null : [
                'id' => (int) $unit['id'],
                'code' => $unit['code'],
                'name' => $unit['name'],
                'unit_type' => $unit['unit_type'],
            ],

            'membership' => [
                'id' => (int) $membership['membership_id'],
                'user_id' => (int) $membership['user_id'],
                'role' => $membership['membership_role'],
                'status' => $membership['membership_status'],
                'is_default' => (bool) $membership['is_default'],
            ],
        ];
    }
}
