<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Core\BackendController;
use Modules\Core\Services\OrganizationService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

class OrganizationsController extends BackendController
{
    protected OrganizationService $organizationService;

    public function __construct()
    {
        $this->organizationService = new OrganizationService();
    }

    public function index()
    {
        $keyword = trim((string) $this->request->getGet('q'));
        $status  = trim((string) $this->request->getGet('status'));

        try {
            $organizations = $this->organizationService->getList(
                $keyword,
                $status
            );

            return $this->render('organizations/index', [
                'title'         => 'Master Organization',
                'organizations' => $organizations,
                'keyword'       => $keyword,
                'status'        => $status,
            ]);
        } catch (Throwable $e) {
            log_message('error', '[OrganizationController::index] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with(
                'error',
                'Gagal memuat data organisasi.'
            );
        }
    }

    public function create()
    {
        return $this->render('organizations/form', [
            'title'        => 'Tambah Organisasi',
            'organization' => $this->emptyOrganization(),
            'formAction'   => site_url('core/organizations/store'),
        ]);
    }

    public function edit($id = null)
    {
        $organizationId = filter_var(
            $id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($organizationId === false) {
            throw PageNotFoundException::forPageNotFound(
                'ID organisasi tidak valid.'
            );
        }

        $organization = $this->organizationService->getById(
            (int) $organizationId
        );

        if (! $organization) {
            throw PageNotFoundException::forPageNotFound(
                'Organisasi tidak ditemukan.'
            );
        }

        return $this->render('organizations/form', [
            'title'        => 'Edit Organisasi',
            'organization' => $organization,
            'formAction'   => site_url(
                'core/organizations/update/' . $organizationId
            ),
        ]);
    }

    public function store()
    {
        return $this->saveOrganization();
    }

    public function update($id = null)
    {
        $organizationId = filter_var(
            $id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($organizationId === false) {
            throw PageNotFoundException::forPageNotFound(
                'ID organisasi tidak valid.'
            );
        }

        return $this->saveOrganization((int) $organizationId);
    }

    protected function saveOrganization(?int $id = null)
    {
        $data = [
            'code'              => $this->request->getPost('code'),
            'slug'              => $this->request->getPost('slug'),
            'name'              => $this->request->getPost('name'),
            'legal_name'        => $this->request->getPost('legal_name'),
            'organization_type' => $this->request->getPost('organization_type'),
            'status'            => $this->request->getPost('status'),
            'timezone'          => $this->request->getPost('timezone'),
            'currency'          => $this->request->getPost('currency'),
            'settings'          => $this->request->getPost('settings'),
        ];

        try {
            $savedId = $this->organizationService->save($data, $id);

            $message = $id === null
                ? 'Organisasi berhasil ditambahkan.'
                : 'Organisasi berhasil diperbarui.';

            return redirect()
                ->to(site_url('core/organizations'))
                ->with('success', $message);
        } catch (Throwable $e) {
            log_message('error', '[OrganizationController::saveOrganization] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function deactivate($id = null)
    {
        $organizationId = filter_var(
            $id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($organizationId === false) {
            throw PageNotFoundException::forPageNotFound(
                'ID organisasi tidak valid.'
            );
        }

        try {
            $this->organizationService->deactivate(
                (int) $organizationId
            );

            return redirect()
                ->to(site_url('core/organizations'))
                ->with('success', 'Organisasi berhasil dinonaktifkan.');
        } catch (Throwable $e) {
            log_message('error', '[OrganizationController::deactivate] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with(
                'error',
                $e->getMessage()
            );
        }
    }

    protected function emptyOrganization(): array
    {
        return [
            'id'                => null,
            'code'              => '',
            'slug'              => '',
            'name'              => '',
            'legal_name'        => '',
            'organization_type' => 'business',
            'status'            => 'active',
            'timezone'          => 'Asia/Jakarta',
            'currency'          => 'IDR',
            'settings'          => '',
        ];
    }
}
