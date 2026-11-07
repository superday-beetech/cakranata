<?php

declare(strict_types=1);

namespace Modules\Rbac\Controllers;

use App\Core\BackendController;
use DomainException;
use Modules\Rbac\Services\GroupPermissionService;
use Modules\Rbac\Services\PermissionSyncService;
use Throwable;

class GroupPermissionsController extends BackendController
{
    private PermissionSyncService $permissionSync;

    public function __construct()
    {
        $this->permissionSync = new PermissionSyncService();
    }

    private function permissionService(): GroupPermissionService
    {
        return new GroupPermissionService();
    }

    public function index()
    {
        $rawGroupId = $this->request->getGet('group_id');
        $groupId = null;

        if ($rawGroupId !== null && $rawGroupId !== '') {
            $validatedId = filter_var(
                $rawGroupId,
                FILTER_VALIDATE_INT
            );

            if ($validatedId === false || $validatedId < 1) {
                return redirect()
                    ->to(site_url('rbac/group-permissions'))
                    ->with('error', 'Group tidak valid.');
            }

            $groupId = $validatedId;
        }

        try {
            $this->theme->setTitle('Permission Groups');

            $this->data = [
                'title' => 'Permission Groups',
                ...$this->permissionService()->getOverview($groupId),
            ];

            return $this->render('permissions/groups', $this->data);
        } catch (DomainException $e) {
            return redirect()
                ->to(site_url('rbac/group-permissions'))
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message(
                'error',
                'Group permission overview error: {message}',
                ['message' => $e->getMessage()]
            );

            return redirect()
                ->back()
                ->with('error', 'Gagal memuat data permission group.');
        }
    }

    public function save()
    {
        $groupId = filter_var(
            $this->request->getPost('group_id'),
            FILTER_VALIDATE_INT
        );

        if ($groupId === false || $groupId === null || $groupId < 1) {
            return redirect()
                ->back()
                ->with('error', 'Group tidak valid.');
        }

        $permissionIds = $this->request->getPost('permissions');

        if (! is_array($permissionIds)) {
            $permissionIds = [];
        }

        try {
            $this->permissionService()->save(
                $groupId,
                $permissionIds
            );

            return redirect()
                ->to(site_url(
                    'rbac/group-permissions?group_id=' . $groupId
                ))
                ->with('success', 'Permission group berhasil disimpan.');
        } catch (DomainException $e) {
            return redirect()
                ->to(site_url(
                    'rbac/group-permissions?group_id=' . $groupId
                ))
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message(
                'error',
                'Group permission save error: {message}',
                ['message' => $e->getMessage()]
            );

            return redirect()
                ->to(site_url(
                    'rbac/group-permissions?group_id=' . $groupId
                ))
                ->with('error', 'Gagal menyimpan permission group.');
        }
    }

    public function synchronize()
    {
        $selected = $this->request->getPost('permissions');

        if (! is_array($selected) || $selected === []) {
            return redirect()
                ->to(site_url('rbac/group-permissions'))
                ->with('error', 'Pilih permission terlebih dahulu.');
        }

        try {
            $result = $this->permissionSync->synchronize($selected);

            return redirect()
                ->to(site_url('rbac/group-permissions'))
                ->with(
                    'success',
                    sprintf(
                        'Sinkronisasi selesai: %d ditambahkan, %d dilewati.',
                        $result['inserted'],
                        $result['skipped']
                    )
                );
        } catch (DomainException $e) {
            return redirect()
                ->to(site_url('rbac/group-permissions'))
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message(
                'error',
                'Permission sync error: {message}',
                ['message' => $e->getMessage()]
            );

            return redirect()
                ->to(site_url('rbac/group-permissions'))
                ->with('error', 'Sinkronisasi gagal. Periksa log aplikasi.');
        }
    }
}
