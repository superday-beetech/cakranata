<?php

declare(strict_types=1);

namespace Modules\Rbac\Controllers;

use App\Core\BackendController;
use DomainException;
use Modules\Rbac\Services\UserGroupService;
use Throwable;

class UserGroupsController extends BackendController
{
    private UserGroupService $userGroupService;

    public function __construct()
    {

        $this->userGroupService = new UserGroupService();
    }

    /**
     * Halaman pengelolaan group user.
     */


    public function index()
    {
        $userId = filter_var(
            $this->request->getGet('user_id'),
            FILTER_VALIDATE_INT
        );

        try {
            $this->theme->setTitle('Group Users');

            $this->data = [
                'title' => 'Group Users',
                ...$this->userGroupService->getOverview(
                    $userId !== false && $userId !== null && $userId > 0
                        ? $userId
                        : null
                ),
            ];

            return $this->render('users/group', $this->data);
        } catch (DomainException $e) {
            return redirect()->to(site_url('rbac/user-groups'))
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'User group overview error: {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Gagal memuat data group user.');
        }
    }

    public function indexes()
    {
        $rawUserId = $this->request->getGet('user_id');
        $userId = null;

        if ($rawUserId !== null && $rawUserId !== '') {
            $validated = filter_var($rawUserId, FILTER_VALIDATE_INT);

            if ($validated === false || $validated < 1) {
                return redirect()->to(site_url('rbac/user-groups'))
                    ->with('error', 'User ID tidak valid.');
            }

            $userId = $validated;
        }

        try {
            $this->theme->setTitle('Group Users');

            $this->data = [
                'title' => 'Group Users',
                ...$this->userGroupService->getOverview($userId),
            ];

            return $this->render('users/groups', $this->data);
        } catch (DomainException $e) {
            return redirect()->to(site_url('rbac/user-groups'))
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message(
                'error',
                'User group overview error: {message}',
                ['message' => $e->getMessage()]
            );

            return redirect()->back()
                ->with('error', 'Gagal memuat data group user.');
        }
    }


    /**
     * Simpan keanggotaan group user.
     */
    public function save()
    {
        $userId = filter_var(
            $this->request->getPost('user_id'),
            FILTER_VALIDATE_INT
        );

        if ($userId === false || $userId === null || $userId < 1) {
            return redirect()->back()
                ->with('error', 'User tidak valid.');
        }

        $groupNames = $this->request->getPost('groups');

        if (! is_array($groupNames)) {
            $groupNames = [];
        }

        try {
            $this->userGroupService->save($userId, $groupNames);

            return redirect()->to(site_url(
                'rbac/user-groups?user_id=' . $userId
            ))->with('success', 'Group user berhasil disimpan.');
        } catch (DomainException $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message(
                'error',
                'User group save error: {message}',
                ['message' => $e->getMessage()]
            );

            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal menyimpan group user.');
        }
    }
}
