<?php

namespace Modules\Rbac\Controllers;

use App\Core\BackendController;
use DomainException;
use Modules\Rbac\Services\PermissionSyncService;
use Throwable;

class PermissionSyncController extends BackendController
{
    private PermissionSyncService $permissionSync;

    public function __construct()
    {
        $this->permissionSync = new PermissionSyncService();
    }

    public function index()
    {
        return $this->render('permissions/index', [
            'title' => 'Sinkronisasi Permission',
            ...$this->permissionSync->getOverview(),
        ]);
    }

    public function synchronize()
    {
        $selected = $this->request->getPost('permissions');

        if (! is_array($selected)) {
            return redirect()->to(site_url('backend/permission-sync'))
                ->with('error', 'Pilih permission terlebih dahulu.');
        }

        try {
            $result = $this->permissionSync->synchronize($selected);

            return redirect()->to(site_url('backend/permission-sync'))
                ->with('success', sprintf(
                    'Sinkronisasi selesai: %d ditambahkan, %d dilewati.',
                    $result['inserted'],
                    $result['skipped']
                ));
        } catch (DomainException $e) {
            return redirect()->to(site_url('backend/permission-sync'))
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'Permission sync error: {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->to(site_url('backend/permission-sync'))
                ->with('error', 'Sinkronisasi gagal. Periksa log aplikasi.');
        }
    }
}
