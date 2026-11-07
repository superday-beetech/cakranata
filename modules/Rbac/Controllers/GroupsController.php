<?php

declare(strict_types=1);

namespace Modules\Rbac\Controllers;

use App\Core\BackendController;
use DomainException;
use Modules\Rbac\Services\GroupService;
use Throwable;

class GroupsController extends BackendController
{
    private GroupService $groupService;

    public function __construct()
    {
        $this->groupService = new GroupService();
    }

    public function index()
    {
        try {
            $this->theme->setTitle('Group Management');

            $this->data = [
                'title' => 'Group Management',
                'groups' => $this->groupService->getAllGroups(),
            ];

            return $this->render('groups/index', $this->data);
        } catch (Throwable $e) {
            log_message('error', 'Groups index error: {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Gagal memuat daftar group.');
        }
    }

    public function create()
    {
        $this->theme->setTitle('Tambah Group');

        return $this->render('groups/form', [
            'title' => 'Tambah Group',
            'group' => null,
            'action' => site_url('rbac/groups/store'),
        ]);
    }

    public function store()
    {
        try {
            $this->groupService->createGroup([
                'name' => trim((string) $this->request->getPost('name')),
                'description' => trim(
                    (string) $this->request->getPost('description')
                ),
            ]);

            return redirect()->to(site_url('rbac/groups'))
                ->with('success', 'Group berhasil ditambahkan.');
        } catch (DomainException $e) {
            return redirect()->back()->withInput()
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'Group create error: {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()
                ->with('error', 'Gagal menambahkan group.');
        }
    }

    public function edit($id = null)
    {
        $groupId = filter_var($id, FILTER_VALIDATE_INT);

        if ($groupId === false || $groupId < 1) {
            return redirect()->to(site_url('rbac/groups'))
                ->with('error', 'ID group tidak valid.');
        }

        try {
            $group = $this->groupService->getGroup($groupId);

            if (! $group) {
                return redirect()->to(site_url('rbac/groups'))
                    ->with('error', 'Group tidak ditemukan.');
            }

            $this->theme->setTitle('Edit Group');

            return $this->render('groups/form', [
                'title' => 'Edit Group',
                'group' => $group,
                'action' => site_url('rbac/groups/update/' . $groupId),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'Group edit error: {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->to(site_url('rbac/groups'))
                ->with('error', 'Gagal memuat data group.');
        }
    }

    public function update($id = null)
    {
        $groupId = filter_var($id, FILTER_VALIDATE_INT);

        if ($groupId === false || $groupId < 1) {
            return redirect()->to(site_url('rbac/groups'))
                ->with('error', 'ID group tidak valid.');
        }

        try {
            $this->groupService->updateGroup($groupId, [
                'description' => trim(
                    (string) $this->request->getPost('description')
                ),
            ]);

            return redirect()->to(site_url('rbac/groups'))
                ->with('success', 'Group berhasil diperbarui.');
        } catch (DomainException $e) {
            return redirect()->back()->withInput()
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'Group update error: {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()
                ->with('error', 'Gagal memperbarui group.');
        }
    }

    public function delete($id = null)
    {
        $groupId = filter_var($id, FILTER_VALIDATE_INT);

        if ($groupId === false || $groupId < 1) {
            return redirect()->to(site_url('rbac/groups'))
                ->with('error', 'ID group tidak valid.');
        }

        try {
            $this->groupService->deleteGroup($groupId);

            return redirect()->to(site_url('rbac/groups'))
                ->with('success', 'Group berhasil dihapus.');
        } catch (DomainException $e) {
            return redirect()->to(site_url('rbac/groups'))
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'Group delete error: {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->to(site_url('rbac/groups'))
                ->with('error', 'Gagal menghapus group.');
        }
    }
}
