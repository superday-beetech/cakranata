<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Core\BackendController;
use Modules\Auth\Models\UserModel;
use Modules\Core\Services\OrganizationMembershipService;
use Modules\Rbac\Models\CoreOrganizationUnitModel;
use Throwable;

class OrganizationMembershipsController extends BackendController
{
    private UserModel $userModel;
    private OrganizationMembershipService $membershipService;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->membershipService = new OrganizationMembershipService();
    }

    public function index()
    {
        $userId = (int) $this->request->getGet('user_id');

        if ($userId > 0) {
            $overview = $this->membershipService->getOverviewByUser($userId);
            $user = auth()->getProvider()->findById($userId);

            return $this->render('organization-membership/index', [
                'title'        => 'Keanggotaan Organisasi',
                'user'         => $user,
                'organizations' => $overview['organizations'],
                'memberships'  => $overview['memberships'],
                'userId'       => $userId,
            ]);
        }

        // Pertahankan alur daftar umum dan filter organisasi
        $organizationId = (int) $this->request->getGet('organization_id');
        $keyword = trim((string) $this->request->getGet('keyword'));
        $status = trim((string) $this->request->getGet('status'));

        return $this->render('organization-membership/index', [
            'title'                 => 'Keanggotaan Organisasi',
            'user'                  => null,
            'organizations'         => $this->membershipService->getActiveOrganizations(),
            'memberships'           => $this->membershipService->getList(
                $organizationId,
                $keyword,
                $status
            ),
            'selectedOrganizationId' => $organizationId,
            'keyword'               => $keyword,
            'status'                => $status,
            'userId'                => 0,
        ]);
    }

    public function indexes()
    {
        $organizationId = filter_var(
            $this->request->getGet('organization_id') ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $organizationId = $organizationId === false
            ? 0
            : (int) $organizationId;

        $keyword = trim((string) $this->request->getGet('q'));
        $status  = trim((string) $this->request->getGet('status'));

        try {
            $organizations = $this->membershipService->getActiveOrganizations();

            $memberships = $organizationId > 0
                ? $this->membershipService->getList(
                    $organizationId,
                    $keyword,
                    $status
                )
                : [];

            $this->theme->setTitle('Organization Membership');

            return $this->render('organization-membership/index', [
                'title'                 => 'Organization Membership',
                'user'                  => null,
                'organizations'          => $organizations,
                'memberships'            => $memberships,
                'selectedOrganizationId' => $organizationId,
                'keyword'                => $keyword,
                'status'                 => $status,
            ]);
        } catch (Throwable $e) {
            log_message('error', '[OrganizationMembershipsController::index] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Gagal memuat keanggotaan organisasi.');
        }
    }



    public function save()
    {
        $userId = filter_var(
            $this->request->getPost('user_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($userId === false) {
            return redirect()->back()->withInput()
                ->with('error', 'User ID tidak valid.');
        }

        try {
            $savedId = $this->membershipService->save([
                'organization_id' => $this->request->getPost('organization_id'),
                'user_id'         => $userId,
                'unit_id'         => $this->request->getPost('unit_id'),
                'membership_role' => $this->request->getPost('membership_role'),
                'status'          => $this->request->getPost('status'),
                'is_default'      => $this->request->getPost('is_default'),
            ]);

            return redirect()->to(
                site_url('core/organization-memberships?user_id=' . $userId)
            )->with('success', 'Keanggotaan organisasi berhasil disimpan.');
        } catch (Throwable $e) {
            log_message('error', '[OrganizationMembershipsController::save] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()
                ->with('error', $e->getMessage());
        }
    }


    public function create()
    {
        $organizationId = filter_var(
            $this->request->getGet('organization_id') ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $organizationId = $organizationId === false
            ? 0
            : (int) $organizationId;

        try {
            $organizations = $this->membershipService->getActiveOrganizations();

            $this->theme->setTitle('Tambah Membership');

            return $this->render('organization-membership/form', [
                'title' => 'Tambah Membership',
                'membership' => [
                    'organization_id' => $organizationId,
                    'user_id' => 0,
                    'unit_id' => null,
                    'membership_role' => 'member',
                    'status' => 'active',
                    'is_default' => 0,
                ],
                'organizations' => $organizations,
                'units' => $organizationId > 0
                    ? $this->membershipService->getActiveUnits($organizationId)
                    : [],
                'formAction' => site_url('core/organization-memberships/save'),
                'isEdit' => false,
            ]);
        } catch (Throwable $e) {
            log_message('error', '[OrganizationMembershipsController::create] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Gagal membuka form membership.');
        }
    }



    public function edit(int $id)
    {
        $membership = $this->membershipService->getById($id);

        if ($membership === null) {
            return redirect()->to(site_url('core/organization-memberships'))
                ->with('error', 'Data keanggotaan tidak ditemukan.');
        }

        $organizationId = (int) $membership['organization_id'];
        $userId         = (int) $membership['user_id'];

        $user = auth()->getProvider()->findById($userId);

        if ($user === null) {
            return redirect()->to(site_url('core/organization-memberships'))
                ->with('error', 'Pengguna tidak ditemukan.');
        }

        return $this->render('organization-membership/form', [
            'title'        => 'Edit Keanggotaan Organisasi',
            'membership'   => $membership,
            'user'         => $user,
            'organizations' => $this->membershipService->getActiveOrganizations(),
            'units'        => $this->membershipService->getActiveUnits($organizationId),
            'isEdit'       => true,
            'formAction'   => site_url('core/organization-memberships/update/' . $id),
        ]);
    }

    public function update(int $id)
    {
        $membership = $this->membershipService->getById($id);

        if ($membership === null) {
            return redirect()->to(site_url('core/organization-memberships'))
                ->with('error', 'Data keanggotaan tidak ditemukan.');
        }

        try {
            $this->membershipService->save([
                // Pertahankan identitas dan organisasi dari data tersimpan.
                'organization_id' => (int) $membership['organization_id'],
                'user_id'         => (int) $membership['user_id'],
                'unit_id'         => $this->request->getPost('unit_id') ?: null,
                'membership_role' => trim((string) $this->request->getPost('membership_role')),
                'status'          => (string) $this->request->getPost('status'),
                'is_default'      => $this->request->getPost('is_default') ? 1 : 0,
            ], $id);

            return redirect()->to(
                site_url('core/organization-memberships?organization_id='
                    . (int) $membership['organization_id'])
            )->with('message', 'Keanggotaan organisasi berhasil diperbarui.');
        } catch (\Throwable $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function deactivate(int $id)
    {
        $membership = $this->membershipService->getById($id);

        if ($membership === null) {
            return redirect()->to(site_url('core/organization-memberships'))
                ->with('error', 'Data keanggotaan tidak ditemukan.');
        }

        try {
            $this->membershipService->deactivate($id);

            return redirect()->to(
                site_url('core/organization-memberships?organization_id='
                    . (int) $membership['organization_id'])
            )->with('message', 'Keanggotaan organisasi berhasil dinonaktifkan.');
        } catch (\Throwable $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }


    public function createsss()
    {
        $organizationId = filter_var(
            $this->request->getGet('organization_id') ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $organizationId = $organizationId === false
            ? 0
            : (int) $organizationId;

        try {
            $organizations = $this->membershipService->getActiveOrganizations();

            $this->theme->setTitle('Tambah Organization Membership');

            return $this->render('organization-membership/form', [
                'title'          => 'Tambah Organization Membership',
                'membership'     => null,
                'organizations'  => $organizations,
                'selectedOrganizationId' => $organizationId,
                'users'          => $this->userModel->findAll(),
                'units'          => $organizationId > 0
                    ? $this->membershipService->getActiveUnits($organizationId)
                    : [],
                'action'         => site_url('core/organization-membership/save'),
                'isEdit'         => false,
            ]);
        } catch (Throwable $e) {
            log_message('error', '[OrganizationMembershipsController::create] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', 'Gagal membuka form membership.');
        }
    }




    public function delete()
    {
        $membershipId = filter_var(
            $this->request->getPost('membership_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $organizationId = filter_var(
            $this->request->getPost('organization_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($membershipId === false || $organizationId === false) {
            return redirect()->back()
                ->with('error', 'ID membership atau organisasi tidak valid.');
        }

        try {
            $membership = $this->membershipService->getById($membershipId);

            if (
                $membership === null
                || (int) $membership['organization_id'] !== $organizationId
            ) {
                throw new \RuntimeException(
                    'Membership tidak ditemukan pada organisasi tersebut.'
                );
            }

            $this->membershipService->deactivate($membershipId);

            return redirect()->to(
                site_url('core/organization-memberships?organization_id=' . $organizationId)
            )->with('success', 'Membership berhasil dinonaktifkan.');
        } catch (Throwable $e) {
            log_message('error', '[OrganizationMembershipsController::delete] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }


    public function userOptions()
    {
        try {
            $users = $this->userModel->findAll();

            $data = array_map(static function ($user): array {
                $id = is_array($user) ? $user['id'] : $user->id;
                $username = is_array($user)
                    ? ($user['username'] ?? '')
                    : ($user->username ?? '');

                return [
                    'id' => (int) $id,
                    'username' => (string) $username,
                ];
            }, $users);

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Daftar user berhasil dimuat.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            log_message('error', '[OrganizationMembershipsController::userOptions] {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Gagal memuat daftar user.',
                'data' => [],
            ]);
        }
    }



    public function unitOptions()
    {
        $organizationId = filter_var(
            $this->request->getGet('organization_id') ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($organizationId === false) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => 'error',
                'message' => 'Organisasi wajib dipilih.',
                'data' => [],
            ]);
        }

        try {
            $units = $this->membershipService->getActiveUnits(
                (int) $organizationId
            );

            return $this->response->setJSON([
                'status' => 'success',
                'data' => array_map(
                    static fn(array $unit): array => [
                        'id' => (int) $unit['id'],
                        'code' => (string) $unit['code'],
                        'name' => (string) $unit['name'],
                    ],
                    $units
                ),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[OrganizationMembershipController::unitOptions] {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->response->setStatusCode(400)->setJSON([
                'status' => 'error',
                'message' => 'Gagal memuat unit organisasi.',
                'data' => [],
            ]);
        }
    }
}
