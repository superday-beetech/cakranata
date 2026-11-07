<?php

declare(strict_types=1);

namespace Modules\Rbac\Repositories;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Modules\Rbac\Models\AuthGroupModel;
use Modules\Rbac\Models\AuthGroupPermissionModel;
use Modules\Rbac\Models\AuthPermissionModel;
use RuntimeException;
use Throwable;

class GroupPermissionRepository
{
    private AuthGroupModel $groupModel;
    private AuthGroupPermissionModel $groupPermissionModel;
    private AuthPermissionModel $permissionModel;
    private BaseConnection $db;


    public function __construct(
        ?AuthGroupModel $groupModel = null,
        ?AuthGroupPermissionModel $groupPermissionModel = null,
        ?AuthPermissionModel $permissionModel = null,
        ?BaseConnection $db = null
    ) {
        $this->db = $db ?? Database::connect();

        $this->groupModel = $groupModel
            ?? new AuthGroupModel($this->db);

        $this->groupPermissionModel = $groupPermissionModel
            ?? new AuthGroupPermissionModel($this->db);

        $this->permissionModel = $permissionModel
            ?? new AuthPermissionModel($this->db);
    }


    public function getGroups(): array
    {
        return $this->groupModel->getAllGroups();
    }

    public function findGroup(int $id): ?array
    {
        return $this->groupModel->findGroup($id);
    }

    public function getPermissions(): array
    {
        return $this->permissionModel->getAllPermissions();
    }

    public function getPermissionIds(int $groupId): array
    {
        return $this->groupPermissionModel
            ->getPermissionIds($groupId);
    }

    public function findPermissionIds(array $ids): array
    {
        return $this->permissionModel->findPermissionIds($ids);
    }

    /**
     * Mengganti seluruh permission milik grup.
     *
     * Pemanggil bertanggung jawab memulai transaksi
     * dan melakukan commit atau rollback.
     */
    public function replaceGroupPermissions(
        int $groupId,
        array $permissionIds
    ): bool {
        $permissionIds = array_values(array_unique(
            array_map('intval', $permissionIds)
        ));

        if (! $this->groupPermissionModel->deleteForGroup($groupId)) {
            return false;
        }

        if ($permissionIds === []) {
            return true;
        }

        return $this->groupPermissionModel->insertForGroup(
            $groupId,
            $permissionIds
        );
    }

    public function beginTransaction(): bool
    {
        return $this->db->transBegin();
    }

    public function transactionStatus(): bool
    {
        return $this->db->transStatus();
    }

    public function commitTransaction(): bool
    {
        return $this->db->transCommit();
    }

    public function rollbackTransaction(): bool
    {
        return $this->db->transRollback();
    }

    /**
     * Operasi penggantian permission dalam satu transaksi.
     *
     * Validasi keberadaan grup dan permission tetap dilakukan
     * oleh service sebelum method ini dipanggil.
     */
    public function syncGroupPermissions(
        int $groupId,
        array $permissionIds
    ): bool {
        if (! $this->beginTransaction()) {
            throw new RuntimeException(
                'Gagal memulai transaksi permission grup.'
            );
        }

        try {
            if (! $this->replaceGroupPermissions(
                $groupId,
                $permissionIds
            )) {
                throw new RuntimeException(
                    'Gagal mengganti permission grup.'
                );
            }

            if (! $this->transactionStatus()) {
                throw new RuntimeException(
                    'Transaksi permission grup gagal.'
                );
            }

            if (! $this->commitTransaction()) {
                throw new RuntimeException(
                    'Gagal menyimpan transaksi permission grup.'
                );
            }

            return true;
        } catch (Throwable $e) {
            $this->rollbackTransaction();

            throw $e;
        }
    }
}
