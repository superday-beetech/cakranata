<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Core\BackendController;
use CodeIgniter\Exceptions\PageNotFoundException;
use Modules\Core\Services\OrganizationUnitService;
use Throwable;

class OrganizationUnitsController extends BackendController
{
    protected OrganizationUnitService $unitService;

    public function __construct()
    {
        $this->unitService = new OrganizationUnitService();
    }

    public function index()
    {
        $organizationId = filter_var(
            $this->request->getGet('organization_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $keyword = trim((string) $this->request->getGet('q'));
        $status  = trim((string) $this->request->getGet('status'));

        try {
            $organizations = $this->unitService->getActiveOrganizations();

            if ($organizationId === false) {
                return $this->render('organization-units/index', [
                    'title'         => 'Master Organization Unit',
                    'organizations' => $organizations,
                    'selectedOrganizationId' => null,
                    'units'         => [],
                    'keyword'       => $keyword,
                    'status'        => $status,
                ]);
            }

            $organization = $this->unitService->getOrganization(
                (int) $organizationId
            );

            if (! $organization) {
                throw PageNotFoundException::forPageNotFound(
                    'Organisasi tidak ditemukan.'
                );
            }

            $units = $this->unitService->getList(
                (int) $organizationId,
                $keyword,
                $status
            );

            return $this->render('organization-units/index', [
                'title'         => 'Master Organization Unit',
                'organizations' => $organizations,
                'selectedOrganizationId' => (int) $organizationId,
                'selectedOrganization' => $organization,
                'units'         => $units,
                'keyword'       => $keyword,
                'status'        => $status,
            ]);
        } catch (Throwable $e) {
            log_message('error', '[OrganizationUnitController::index] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with(
                'error',
                'Gagal memuat data unit organisasi.'
            );
        }
    }


    public function parentOptions()
    {
        $organizationId = filter_var(
            $this->request->getGet('organization_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($organizationId === false) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Organisasi wajib dipilih.',
                    'data'    => [],
                ]);
        }

        $excludeRaw = $this->request->getGet('exclude_id');
        $excludeId = null;

        if ($excludeRaw !== null && $excludeRaw !== '') {
            $validatedExclude = filter_var(
                $excludeRaw,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($validatedExclude === false) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'status'  => 'error',
                        'message' => 'ID unit tidak valid.',
                        'data'    => [],
                    ]);
            }

            $excludeId = (int) $validatedExclude;
        }

        try {
            $units = $this->unitService->getParentOptions(
                (int) $organizationId,
                $excludeId
            );

            $data = array_map(
                static fn(array $unit): array => [
                    'id'   => (int) $unit['id'],
                    'code' => $unit['code'],
                    'name' => $unit['name'],
                ],
                $units
            );

            return $this->response->setJSON([
                'status' => 'success',
                'data'   => $data,
            ]);
        } catch (\Throwable $e) {
            log_message(
                'error',
                '[OrganizationUnitController::parentOptions] {message}',
                ['message' => $e->getMessage()]
            );

            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'status'  => 'error',
                    'message' => 'Gagal memuat pilihan parent unit.',
                    'data'    => [],
                ]);
        }
    }


    public function create()
    {
        $organizationId = filter_var(
            $this->request->getGet('organization_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $organizations = $this->unitService->getActiveOrganizations();

        if ($organizationId !== false) {
            $organization = $this->unitService->getOrganization(
                (int) $organizationId
            );

            if (! $organization || $organization['status'] !== 'active') {
                throw PageNotFoundException::forPageNotFound(
                    'Organisasi tidak ditemukan atau tidak aktif.'
                );
            }
        }

        return $this->render('organization-units/form', [
            'title'        => 'Tambah Unit Organisasi',
            'unit'         => $this->emptyUnit(
                $organizationId === false ? null : (int) $organizationId
            ),
            'organizations' => $organizations,
            'formAction'   => site_url('core/organization-units/store'),
        ]);
    }

    public function edit($id = null)
    {
        $unitId = filter_var(
            $id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($unitId === false) {
            throw PageNotFoundException::forPageNotFound(
                'ID unit tidak valid.'
            );
        }

        $unit = $this->unitService->getById((int) $unitId);

        if (! $unit) {
            throw PageNotFoundException::forPageNotFound(
                'Unit organisasi tidak ditemukan.'
            );
        }

        return $this->render('organization-units/form', [
            'title'         => 'Edit Unit Organisasi',
            'unit'          => $unit,
            'organizations' => $this->unitService->getActiveOrganizations(),
            'formAction'    => site_url(
                'core/organization-units/update/' . (int) $unitId
            ),
        ]);
    }

    public function store()
    {
        return $this->saveUnit();
    }

    public function update($id = null)
    {
        $unitId = filter_var(
            $id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($unitId === false) {
            throw PageNotFoundException::forPageNotFound(
                'ID unit tidak valid.'
            );
        }

        return $this->saveUnit((int) $unitId);
    }

    protected function saveUnit(?int $id = null)
    {
        $data = [
            'organization_id' => $this->request->getPost('organization_id'),
            'parent_id'       => $this->request->getPost('parent_id'),
            'code'            => $this->request->getPost('code'),
            'name'            => $this->request->getPost('name'),
            'unit_type'       => $this->request->getPost('unit_type'),
            'status'          => $this->request->getPost('status'),
            'metadata'        => $this->request->getPost('metadata'),
        ];

        try {
            $this->unitService->save($data, $id);

            return redirect()
                ->to(site_url(
                    'core/organization-units?organization_id=' .
                        (int) $data['organization_id']
                ))
                ->with(
                    'success',
                    $id === null
                        ? 'Unit organisasi berhasil ditambahkan.'
                        : 'Unit organisasi berhasil diperbarui.'
                );
        } catch (Throwable $e) {
            log_message('error', '[OrganizationUnitController::saveUnit] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function deactivate($id = null)
    {
        $unitId = filter_var(
            $id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($unitId === false) {
            throw PageNotFoundException::forPageNotFound(
                'ID unit tidak valid.'
            );
        }

        try {
            $unit = $this->unitService->getById((int) $unitId);

            if (! $unit) {
                throw PageNotFoundException::forPageNotFound(
                    'Unit organisasi tidak ditemukan.'
                );
            }

            $this->unitService->deactivate((int) $unitId);

            return redirect()
                ->to(site_url(
                    'core/organization-units?organization_id=' .
                        (int) $unit['organization_id']
                ))
                ->with('success', 'Unit organisasi berhasil dinonaktifkan.');
        } catch (Throwable $e) {
            log_message('error', '[OrganizationUnitController::deactivate] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with(
                'error',
                $e->getMessage()
            );
        }
    }

    protected function emptyUnit(?int $organizationId = null): array
    {
        return [
            'id'              => null,
            'organization_id' => $organizationId,
            'parent_id'       => null,
            'code'            => '',
            'name'            => '',
            'unit_type'       => 'department',
            'status'          => 'active',
            'metadata'        => '',
        ];
    }
}
