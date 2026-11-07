<?php

declare(strict_types=1);

namespace Modules\Organization\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Organization\Models\OrganizationMembershipModel;
use Modules\Organization\Models\OrganizationModel;
use Modules\Organization\Services\OrganizationService;
use RuntimeException;
use Throwable;

class OrganizationsController extends ApiController
{
    private OrganizationModel $organizations;
    private OrganizationMembershipModel $memberships;
    private OrganizationService $organizationService;

    public function __construct()
    {
        $this->organizations       = new OrganizationModel();
        $this->memberships         = new OrganizationMembershipModel();
        $this->organizationService = new OrganizationService();
    }

    /**
     * GET /api/v1/organizations
     *
     * List organizations accessible to the authenticated user.
     */
    public function index()
    {
        $userId = $this->currentUserId();
        if ($userId === null) {
            return $this->jsonError(
                'UNAUTHENTICATED',
                'Autentikasi diperlukan.',
                401
            );
        }

        try {
            $page = max(1, (int) ($this->request->getGet('page') ?? 1));
            $perPage = min(
                100,
                max(1, (int) ($this->request->getGet('per_page') ?? 20))
            );

            $builder = $this->memberships
                ->builder()
                ->select(
                    'core_organizations.id,
                     core_organizations.code,
                     core_organizations.slug,
                     core_organizations.name,
                     core_organizations.legal_name,
                     core_organizations.organization_type,
                     core_organizations.status,
                     core_organizations.timezone,
                     core_organizations.currency,
                     core_organization_memberships.membership_role,
                     core_organization_memberships.is_default'
                )
                ->join(
                    'core_organizations',
                    'core_organizations.id = core_organization_memberships.organization_id'
                )
                ->where('core_organization_memberships.user_id', $userId)
                ->where('core_organization_memberships.status', 'active')
                ->where('core_organizations.status', 'active')
                ->where('core_organizations.deleted_at', null);

            $search = trim((string) ($this->request->getGet('q') ?? ''));

            if ($search !== '') {
                $builder->groupStart()
                    ->like('core_organizations.code', $search)
                    ->orLike('core_organizations.name', $search)
                    ->orLike('core_organizations.slug', $search)
                    ->groupEnd();
            }

            $countBuilder = clone $builder;
            $total = $countBuilder->countAllResults();

            $data = $builder
                ->orderBy('core_organization_memberships.is_default', 'DESC')
                ->orderBy('core_organizations.name', 'ASC')
                ->limit($perPage, ($page - 1) * $perPage)
                ->get()
                ->getResultArray();

            return $this->jsonSuccess([
                'items' => $data,
                'pagination' => [
                    'page'        => $page,
                    'per_page'    => $perPage,
                    'total'       => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ], 'Daftar organisasi berhasil diambil.');
        } catch (Throwable $e) {
            log_message('error', '[Organization:index] {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->jsonError(
                'INTERNAL_ERROR',
                'Gagal mengambil daftar organisasi.',
                500
            );
        }
    }

    /**
     * GET /api/v1/organizations/{id}
     */
    public function showOrganization(int $id): ResponseInterface
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return $this->jsonError(
                'UNAUTHENTICATED',
                'Autentikasi diperlukan.',
                401
            );
        }

        if ($id < 1) {
            return $this->jsonError(
                'INVALID_ID',
                'ID organisasi tidak valid.',
                400
            );
        }

        try {
            $membership = $this->organizationService->findForUser(
                $userId,
                $id
            );

            if ($membership === null) {
                return $this->jsonError(
                    'ORGANIZATION_NOT_FOUND',
                    'Organisasi tidak ditemukan atau akses tidak tersedia.',
                    404
                );
            }

            return $this->jsonSuccess(
                $membership,
                'Detail organisasi berhasil diambil.'
            );
        } catch (Throwable $e) {
            log_message('error', '[Organization:show] {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->jsonError(
                'INTERNAL_ERROR',
                'Gagal mengambil detail organisasi.',
                500
            );
        }
    }

    /**
     * POST /api/v1/organizations
     */
    public function createOrganization(): ResponseInterface
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return $this->jsonError(
                'UNAUTHENTICATED',
                'Autentikasi diperlukan.',
                401
            );
        }

        if (! $this->hasPermission('core.organizations.create')) {
            return $this->jsonError(
                'FORBIDDEN',
                'Tidak memiliki izin membuat organisasi.',
                403
            );
        }

        $input = $this->request->getJSON(true);

        if (! is_array($input)) {
            return $this->jsonError(
                'INVALID_PAYLOAD',
                'Body request harus berupa objek JSON.',
                400
            );
        }

        $data = [
            'code'              => strtoupper(trim((string) ($input['code'] ?? ''))),
            'slug'              => strtolower(trim((string) ($input['slug'] ?? ''))),
            'name'              => trim((string) ($input['name'] ?? '')),
            'legal_name'        => isset($input['legal_name'])
                ? trim((string) $input['legal_name'])
                : null,
            'organization_type' => trim(
                (string) ($input['organization_type'] ?? 'business')
            ),
            'timezone'           => trim(
                (string) ($input['timezone'] ?? 'Asia/Jakarta')
            ),
            'currency'           => strtoupper(
                trim((string) ($input['currency'] ?? 'IDR'))
            ),
        ];

        $validation = service('validation');

        $validation->reset();
        $validation->setRules([
            'code' => 'required|alpha_numeric_punct|max_length[50]',
            'slug' => 'required|alpha_dash|max_length[100]',
            'name' => 'required|max_length[150]',
            'legal_name' => 'permit_empty|max_length[200]',
            'organization_type' => 'required|alpha_dash|max_length[50]',
            'timezone' => 'required|max_length[64]',
            'currency' => 'required|alpha|exact_length[3]',
        ]);

        if (! $validation->run($data)) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                'Data organisasi tidak valid.',
                422,
                $validation->getErrors()
            );
        }

        try {
            $organizationId = $this->organizationService->create(
                $data,
                $userId
            );

            $organization = $this->organizations->find($organizationId);

            return $this->jsonSuccess(
                $organization,
                'Organisasi berhasil dibuat.',
                201
            );
        } catch (Throwable $e) {
            log_message('error', '[Organization:create] {message}', [
                'message' => $e->getMessage(),
            ]);

            if (method_exists($e, 'getCode') && $e->getCode() === 1062) {
                return $this->jsonError(
                    'DUPLICATE_ORGANIZATION',
                    'Kode atau slug organisasi sudah digunakan.',
                    409
                );
            }

            return $this->jsonError(
                'INTERNAL_ERROR',
                'Gagal membuat organisasi.',
                500
            );
        }
    }


    /**
     * PUT /api/v1/organizations/{id}
     */
    public function updateOrganization(int $id): ResponseInterface
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return $this->jsonError(
                'UNAUTHENTICATED',
                'Autentikasi diperlukan.',
                401
            );
        }

        if ($id < 1) {
            return $this->jsonError(
                'INVALID_ID',
                'ID organisasi tidak valid.',
                400
            );
        }

        // Permission RBAC sistem.
        /* if (! $this->hasPermission('core.organizations.update')) {
            return $this->jsonError(
                'FORBIDDEN',
                'Tidak memiliki izin mengubah organisasi.',
                403
            );
        }
 */
        // Pastikan user merupakan anggota organisasi.
        $membership = $this->organizationService->findForUser(
            $userId,
            $id
        );

        if ($membership === null) {
            return $this->jsonError(
                'ORGANIZATION_NOT_FOUND',
                'Organisasi tidak ditemukan atau akses tidak tersedia.',
                404
            );
        }

        // Permission organisasi: owner atau admin.
        /* if (! $this->organizationService->userCanManage($userId, $id)) {
            return $this->jsonError(
                'FORBIDDEN',
                'Hanya owner atau admin yang dapat mengubah organisasi.',
                403
            );
        } */

        $input = $this->request->getJSON(true);

        if (! is_array($input) || $input === []) {
            return $this->jsonError(
                'INVALID_PAYLOAD',
                'Body request tidak valid.',
                400
            );
        }

        $allowed = [
            'name',
            'legal_name',
            'organization_type',
            'timezone',
            'currency',
        ];

        $data = array_intersect_key(
            $input,
            array_flip($allowed)
        );

        if ($data === []) {
            return $this->jsonError(
                'NO_UPDATABLE_FIELDS',
                'Tidak ada field yang dapat diubah.',
                422
            );
        }

        foreach ($data as $key => $value) {
            if ($key === 'legal_name' && $value === null) {
                continue;
            }

            if (! is_string($value)) {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'Format field tidak valid.',
                    422,
                    ['field' => $key]
                );
            }

            $data[$key] = trim($value);
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $validation = service('validation');
        $validation->reset();

        $validation->setRules([
            'name' => 'permit_empty|max_length[150]',
            'legal_name' => 'permit_empty|max_length[200]',
            'timezone' => 'permit_empty|max_length[64]',
            'currency' => 'permit_empty|alpha|exact_length[3]',
        ]);

        if (! $validation->run($data)) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                'Data organisasi tidak valid.',
                422,
                $validation->getErrors()
            );
        }

        try {
            $updated = $this->organizationService->update(
                $id,
                $userId,
                $data
            );

            if (! $updated) {
                return $this->jsonError(
                    'UPDATE_FAILED',
                    'Organisasi gagal diperbarui.',
                    500
                );
            }

            return $this->jsonSuccess(
                $this->organizationService->findForUser($userId, $id),
                'Organisasi berhasil diperbarui.'
            );
        } catch (\RuntimeException $e) {
            return $this->jsonError(
                'UPDATE_REJECTED',
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            log_message('error', '[Organization:update] {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->jsonError(
                'INTERNAL_ERROR',
                'Gagal memperbarui organisasi.',
                500
            );
        }
    }


    /**
     * DELETE /api/v1/organizations/{id}
     *
     * Soft-delete. Does not physically remove database records.
     */
    public function deleteOrganization(int $id): ResponseInterface
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return $this->jsonError(
                'UNAUTHENTICATED',
                'Autentikasi diperlukan.',
                401
            );
        }

        if (! $this->organizationService->userCanManage($userId, $id)) {
            return $this->jsonError(
                'FORBIDDEN',
                'Hanya owner atau admin yang dapat mengarsipkan organisasi.',
                403
            );
        }
        if ($this->organizationService->findForUser($userId, $id) === null) {
            return $this->jsonError(
                'ORGANIZATION_NOT_FOUND',
                'Organisasi tidak ditemukan atau akses tidak tersedia.',
                404
            );
        }

        try {
            if (! $this->organizations->delete($id)) {
                return $this->jsonError(
                    'DELETE_FAILED',
                    'Organisasi gagal dihapus.',
                    500
                );
            }

            return $this->jsonSuccess(
                ['id' => $id],
                'Organisasi berhasil diarsipkan.'
            );
        } catch (Throwable $e) {
            log_message('error', '[Organization:delete] {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->jsonError(
                'INTERNAL_ERROR',
                'Gagal mengarsipkan organisasi.',
                500
            );
        }
    }


    /**
     * GET /organizations/{id}/units
     */
    public function indexUnits(int $id): ResponseInterface
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return $this->jsonError('UNAUTHENTICATED', 'Autentikasi diperlukan.', 401);
        }

        if (! $this->hasPermission('core.organization_units.view')) {
            return $this->jsonError('FORBIDDEN', 'Tidak memiliki izin melihat unit organisasi.', 403);
        }

        if (! $this->organizationService->userCanAccess($userId, $id)) {
            return $this->jsonError('ORGANIZATION_NOT_FOUND', 'Organisasi tidak ditemukan atau akses tidak tersedia.', 404);
        }

        try {
            $includeInactive = $this->request->getGet('include_inactive') === '1';

            return $this->jsonSuccess(
                $this->organizationService->listUnits(
                    $userId,
                    $id,
                    $includeInactive
                ),
                'Daftar unit organisasi berhasil diambil.'
            );
        } catch (Throwable $e) {
            log_message('error', '[Organization:indexUnits] {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->jsonError('INTERNAL_ERROR', 'Gagal mengambil unit organisasi.', 500);
        }
    }

    /**
     * GET /organizations/{id}/units/{unitId}
     */
    public function showUnits(): ResponseInterface
    {
        $userId = $this->currentUserId();
        $data = $this->request->getJSON(true) ?? [];

        $organizationId = (int) ($data['organization_id'] ?? 0);
        $unitId = (int) ($data['unit_id'] ?? 0);
        if ($userId === null) {
            return $this->jsonError('UNAUTHENTICATED', 'Autentikasi diperlukan.', 401);
        }

        if (! $this->hasPermission('core.organization_units.view')) {
            return $this->jsonError('FORBIDDEN', 'Tidak memiliki izin melihat unit organisasi.', 403);
        }

        if (! $this->organizationService->userCanAccess($userId, $organizationId)) {
            return $this->jsonError('ORGANIZATION_NOT_FOUND', 'Organisasi tidak ditemukan atau akses tidak tersedia.', 404);
        }

        $unit = $this->organizationService->findUnit($organizationId, $unitId);

        if ($unit === null) {
            return $this->jsonError('UNIT_NOT_FOUND', 'Unit organisasi tidak ditemukan.', 404);
        }

        return $this->jsonSuccess($unit, 'Detail unit organisasi berhasil diambil.');
    }

    /**
     * POST /organizations/{id}/units
     */
    public function createUnits(int $id): ResponseInterface
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return $this->jsonError('UNAUTHENTICATED', 'Autentikasi diperlukan.', 401);
        }

        if (! $this->hasPermission('core.organization_units.create')) {
            return $this->jsonError('FORBIDDEN', 'Tidak memiliki izin membuat unit organisasi.', 403);
        }

        if (! $this->organizationService->userCanAccess($userId, $id)) {
            return $this->jsonError('ORGANIZATION_NOT_FOUND', 'Organisasi tidak ditemukan atau akses tidak tersedia.', 404);
        }

        $input = $this->request->getJSON(true);

        if (! is_array($input) || $input === []) {
            return $this->jsonError('INVALID_PAYLOAD', 'Body request harus berupa objek JSON.', 400);
        }

        try {
            $unitId = $this->organizationService->createUnit(
                $userId,
                $id,
                $input
            );

            return $this->jsonSuccess(
                $this->organizationService->findUnit($id, $unitId),
                'Unit organisasi berhasil dibuat.',
                201
            );
        } catch (RuntimeException $e) {
            return $this->jsonError('UNIT_CREATE_REJECTED', $e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message('error', '[Organization:createUnit] {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->jsonError('INTERNAL_ERROR', 'Gagal membuat unit organisasi.', 500);
        }
    }

    /**
     * PUT /organizations/{id}/units/{unitId}
     */
    public function updateUnits(int $id, int $unitId): ResponseInterface
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return $this->jsonError('UNAUTHENTICATED', 'Autentikasi diperlukan.', 401);
        }

        if (! $this->hasPermission('core.organization_units.update')) {
            return $this->jsonError('FORBIDDEN', 'Tidak memiliki izin mengubah unit organisasi.', 403);
        }

        if (! $this->organizationService->userCanAccess($userId, $id)) {
            return $this->jsonError('ORGANIZATION_NOT_FOUND', 'Organisasi tidak ditemukan atau akses tidak tersedia.', 404);
        }

        $input = $this->request->getJSON(true);

        if (! is_array($input) || $input === []) {
            return $this->jsonError('INVALID_PAYLOAD', 'Body request tidak valid.', 400);
        }

        try {
            $updated = $this->organizationService->updateUnit(
                $userId,
                $id,
                $unitId,
                $input
            );

            if (! $updated) {
                return $this->jsonError('UPDATE_FAILED', 'Unit organisasi gagal diperbarui.', 500);
            }

            return $this->jsonSuccess(
                $this->organizationService->findUnit($id, $unitId),
                'Unit organisasi berhasil diperbarui.'
            );
        } catch (RuntimeException $e) {
            return $this->jsonError('UNIT_UPDATE_REJECTED', $e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message('error', '[Organization:updateUnit] {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->jsonError('INTERNAL_ERROR', 'Gagal memperbarui unit organisasi.', 500);
        }
    }

    /**
     * DELETE /organizations/{id}/units/{unitId}
     */
    public function deleteUnits(int $id, int $unitId): ResponseInterface
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return $this->jsonError('UNAUTHENTICATED', 'Autentikasi diperlukan.', 401);
        }

        if (! $this->hasPermission('core.organization_units.delete')) {
            return $this->jsonError('FORBIDDEN', 'Tidak memiliki izin menonaktifkan unit organisasi.', 403);
        }

        if (! $this->organizationService->userCanAccess($userId, $id)) {
            return $this->jsonError('ORGANIZATION_NOT_FOUND', 'Organisasi tidak ditemukan atau akses tidak tersedia.', 404);
        }

        try {
            $deleted = $this->organizationService->deactivateUnit(
                $userId,
                $id,
                $unitId
            );

            if (! $deleted) {
                return $this->jsonError('DEACTIVATE_FAILED', 'Unit organisasi gagal dinonaktifkan.', 500);
            }

            return $this->jsonSuccess(
                ['id' => $unitId, 'status' => 'inactive'],
                'Unit organisasi berhasil dinonaktifkan.'
            );
        } catch (RuntimeException $e) {
            return $this->jsonError('UNIT_DEACTIVATE_REJECTED', $e->getMessage(), 422);
        } catch (Throwable $e) {
            log_message('error', '[Organization:deleteUnit] {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->jsonError('INTERNAL_ERROR', 'Gagal menonaktifkan unit organisasi.', 500);
        }
    }


    private function currentUserId(): ?int
    {
        // API: utamakan authenticator token.
        // Jika endpoint juga menerima session browser, session menjadi fallback.
        foreach (['tokens', 'session'] as $authenticator) {
            $user = auth($authenticator)->user();

            if ($user !== null && isset($user->id) && (int) $user->id > 0) {
                return (int) $user->id;
            }
        }

        return null;
    }

    private function hasPermission(string $permission): bool
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return false;
        }

        return service('accessControl')->hasPermission(
            $userId,
            $permission
        );
    }


    private function jsonSuccess(
        mixed $data,
        string $message,
        int $status = 200
    ): ResponseInterface {
        return $this->response
            ->setStatusCode($status)
            ->setJSON([
                'status'  => 'success',
                'error'   => false,
                'message' => $message,
                'data'    => $data,
            ]);
    }

    private function jsonError(
        string $code,
        string $message,
        int $status,
        array $details = []
    ): ResponseInterface {
        $payload = [
            'status'  => 'error',
            'error'   => true,
            'message' => $message,
            'data'    => null,
            'code'    => $code,
        ];

        if ($details !== []) {
            $payload['details'] = $details;
        }

        return $this->response
            ->setStatusCode($status)
            ->setJSON($payload);
    }
}
