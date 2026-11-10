<?php

declare(strict_types=1);

namespace Modules\Rbac\Controllers;

use App\Core\BackendController;
use DomainException;
use Modules\Rbac\Services\UserPermissionService;
use Throwable;

class UserPermissionsController extends BackendController
{
    private UserPermissionService $userPermissionService;

    public function __construct()
    {
        $this->userPermissionService = new UserPermissionService();
    }

    public function index()
    {
        $rawUserId = $this->request->getGet('user_id');
        $userId = null;

        if ($rawUserId !== null && $rawUserId !== '') {
            $validatedId = filter_var(
                $rawUserId,
                FILTER_VALIDATE_INT
            );

            if ($validatedId === false || $validatedId < 1) {
                return redirect()
                    ->to(site_url('rbac/user-permissions'))
                    ->with('error', 'User ID tidak valid.');
            }

            $userId = $validatedId;
        }

        try {
            $this->theme->setTitle('User Permissions');

            $this->data = [
                'title' => 'User Permissions',
                ...$this->userPermissionService->getOverview($userId),
            ];

            return $this->render('permissions/users', $this->data);
        } catch (DomainException $e) {
            return redirect()
                ->to(site_url('rbac/user-permissions'))
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message(
                'error',
                'User permission overview error: {message}',
                ['message' => $e->getMessage()]
            );

            return redirect()
                ->back()
                ->with('error', 'Gagal memuat data user permissions.');
        }
    }

    public function save()
    {
        $userId = filter_var(
            $this->request->getPost('user_id'),
            FILTER_VALIDATE_INT
        );

        if ($userId === false || $userId === null || $userId < 1) {
            return redirect()
                ->back()
                ->with('error', 'User tidak valid.');
        }

        $permissionNames = $this->request->getPost('permissions');

        if (! is_array($permissionNames)) {
            $permissionNames = [];
        }

        try {
            $this->userPermissionService->save(
                $userId,
                $permissionNames
            );

            return redirect()
                ->to(site_url(
                    'rbac/user-permissions?user_id=' . $userId
                ))
                ->with(
                    'success',
                    'Permission user berhasil disimpan.'
                );
        } catch (DomainException $e) {
            return redirect()
                ->to(site_url(
                    'rbac/user-permissions?user_id=' . $userId
                ))
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message(
                'error',
                'User permission save error: {message}',
                ['message' => $e->getMessage()]
            );

            return redirect()
                ->to(site_url(
                    'rbac/user-permissions?user_id=' . $userId
                ))
                ->with(
                    'error',
                    'Gagal menyimpan permission user.'
                );
        }
    }
}
