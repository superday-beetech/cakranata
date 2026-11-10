<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Core\BackendController;
use CodeIgniter\Exceptions\PageNotFoundException;
use Modules\Core\Services\OrganizationMembershipService;
use Throwable;

class OrganizationMembershipController extends BackendController
{
    private OrganizationMembershipService $membershipService;

    public function __construct()
    {
        $this->membershipService = new OrganizationMembershipService();
    }

    public function index()
    {
        $organizationId = filter_var(
            $this->request->getGet('organization_id') ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $keyword = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));

        try {
            $organizations = $this->membershipService
                ->getActiveOrganizations();

            $selectedOrganizationId = $organizationId === false
                ? 0
                : (int) $organizationId;

            $memberships = [];

            if ($selectedOrganizationId > 0) {
                $memberships = $this->membershipService->getList(
                    $selectedOrganizationId,
                    $keyword,
                    $status
                );
            }

            return $this->render('organization-memberships/index', [
                'title'                  => 'Organization Membership',
                'organizations'           => $organizations,
                'memberships'             => $memberships,
                'selectedOrganizationId' => $selectedOrganizationId,
                'keyword'                => $keyword,
                'status'                 => $status,
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[OrganizationMembershipController::index] {message}',
                ['message' => $e->getMessage()]
            );

            return redirect()->back()->with(
                'error',
                'Gagal memuat data membership.'
            );
        }
    }

    public function create()
    {
        try {
            $organizationId = filter_var(
                $this->request->getGet('organization_id') ?? '',
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            $organizations = $this->membershipService
                ->getActiveOrganizations();

            $selectedOrganizationId = $organizationId === false
                ? 0
                : (int) $organizationId;

            $units = $selectedOrganizationId > 0
                ? $this->membershipService->getActiveUnits(
                    $selectedOrganizationId
                )
                : [];

            return $this->render('organization-memberships/form', [
                'title'                  => 'Tambah Membership',
                'membership'             => $this->emptyMembership(
                    $selectedOrganizationId
                ),
                'organizations'           => $organizations,
                'units'                   => $units,
                'formAction'              => site_url(
                    'core/organization-memberships/store'
                ),
                'isEdit'                  => false,
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[OrganizationMembershipController::create] {message}',
                ['message' => $e->getMessage()]
            );

            return redirect()->back()->with(
                'error',
                'Gagal membuka form membership.'
            );
        }
    }

    public function edit($id)
    {
        try {
            $membership = $this->membershipService->getById((int) $id);

            if ($membership === null) {
                throw PageNotFoundException::forPageNotFound(
                    'Membership tidak ditemukan.'
                );
            }

            $organizationId = (int) $membership['organization_id'];

            return $this->render('organization-memberships/form', [
                'title'                  => 'Edit Membership',
                'membership'             => $membership,
                'organizations'           => $this->membershipService
                    ->getActiveOrganizations(),
                'units'                   => $this->membershipService
                    ->getActiveUnits($organizationId),
                'formAction'              => site_url(
                    'core/organization-memberships/update/' . (int) $id
                ),
                'isEdit'                  => true,
            ]);
        } catch (PageNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            log_message(
                'error',
                '[OrganizationMembershipController::edit] {message}',
                ['message' => $e->getMessage()]
            );

            return redirect()->back()->with(
                'error',
                'Gagal membuka data membership.'
            );
        }
    }

    public function store()
    {
        return $this->saveMembership();
    }

    public function update($id)
    {
        return $this->saveMembership((int) $id);
    }

    private function saveMembership(?int $id = null)
    {
        if (! $this->request->is('post')) {
            return $this->response->setStatusCode(405);
        }

        try {
            $savedId = $this->membershipService->save([
                'organization_id' => $this->request->getPost(
                    'organization_id'
                ),
                'user_id' => $this->request->getPost('user_id'),
                'unit_id' => $this->request->getPost('unit_id'),
                'membership_role' => $this->request->getPost(
                    'membership_role'
                ),
                'status' => $this->request->getPost('status'),
                'is_default' => $this->request->getPost('is_default'),
            ], $id);

            $membership = $this->membershipService->getById($savedId);
            $organizationId = (int) ($membership['organization_id'] ?? 0);

            return redirect()->to(
                site_url(
                    'core/organization-memberships?organization_id='
                        . $organizationId
                )
            )->with(
                'success',
                $id === null
                    ? 'Membership berhasil ditambahkan.'
                    : 'Membership berhasil diperbarui.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[OrganizationMembershipController::saveMembership] {message}',
                ['message' => $e->getMessage()]
            );

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function deactivate($id)
    {
        if (! $this->request->is('post')) {
            return $this->response->setStatusCode(405);
        }

        try {
            $membership = $this->membershipService->getById((int) $id);

            if ($membership === null) {
                throw PageNotFoundException::forPageNotFound(
                    'Membership tidak ditemukan.'
                );
            }

            $organizationId = (int) $membership['organization_id'];

            $this->membershipService->deactivate((int) $id);

            return redirect()->to(
                site_url(
                    'core/organization-memberships?organization_id='
                        . $organizationId
                )
            )->with('success', 'Membership berhasil dinonaktifkan.');
        } catch (PageNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            log_message(
                'error',
                '[OrganizationMembershipController::deactivate] {message}',
                ['message' => $e->getMessage()]
            );

            return redirect()->back()->with(
                'error',
                'Gagal menonaktifkan membership.'
            );
        }
    }

    public function userOptions()
    {
        $organizationId = filter_var(
            $this->request->getGet('organization_id') ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($organizationId === false) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Organisasi wajib dipilih.',
                'data'    => [],
            ]);
        }

        try {
            $this->membershipService->getActiveUnits(
                (int) $organizationId
            );

            $users = model(\CodeIgniter\Shield\Models\UserModel::class)
                ->select('users.id, users.username')
                ->orderBy('users.username', 'ASC')
                ->findAll();

            $data = array_map(
                static fn(array $user): array => [
                    'id'       => (int) $user['id'],
                    'username' => (string) $user['username'],
                ],
                $users
            );

            return $this->response->setJSON([
                'status' => 'success',
                'data'   => $data,
            ]);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[OrganizationMembershipController::userOptions] {message}',
                ['message' => $e->getMessage()]
            );

            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Gagal memuat pilihan user.',
                'data'    => [],
            ]);
        }
    }

    private function emptyMembership(int $organizationId = 0): array
    {
        return [
            'id'              => null,
            'organization_id' => $organizationId,
            'user_id'         => '',
            'unit_id'         => '',
            'membership_role' => 'member',
            'status'          => 'active',
            'is_default'      => 0,
        ];
    }
}
