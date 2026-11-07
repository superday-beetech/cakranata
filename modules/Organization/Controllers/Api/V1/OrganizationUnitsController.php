<?php

declare(strict_types=1);

namespace Modules\Organization\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Organization\Models\OrganizationModel;
use Modules\Organization\Services\OrganizationService;
use RuntimeException;
use Throwable;

class OrganizationUnitsController extends ApiController
{
    private OrganizationService $organizationService;

    public function __construct()
    {
        $this->organizationService = new OrganizationService();
    }


    /**
     * GET /organizations/{id}/units
     */
    public function index(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];

        $id = (int) ($data['organization_id'] ?? 0);

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
    public function updateUnits(): ResponseInterface
    {
        $userId = $this->currentUserId();
        $data = $this->request->getJSON(true) ?? [];

        $organizationId = (int) ($data['organization_id'] ?? 0);
        $unitId = (int) ($data['unit_id'] ?? 0);

        if ($userId === null) {
            return $this->jsonError('UNAUTHENTICATED', 'Autentikasi diperlukan.', 401);
        }

        if (! $this->hasPermission('core.organization_units.update')) {
            return $this->jsonError('FORBIDDEN', 'Tidak memiliki izin mengubah unit organisasi.', 403);
        }

        if (! $this->organizationService->userCanAccess($userId, $organizationId)) {
            return $this->jsonError('ORGANIZATION_NOT_FOUND', 'Organisasi tidak ditemukan atau akses tidak tersedia.', 404);
        }

        $input = $this->request->getJSON(true);

        if (! is_array($input) || $input === []) {
            return $this->jsonError('INVALID_PAYLOAD', 'Body request tidak valid.', 400);
        }

        try {
            $updated = $this->organizationService->updateUnit(
                $userId,
                $organizationId,
                $unitId,
                $input
            );

            if (! $updated) {
                return $this->jsonError('UPDATE_FAILED', 'Unit organisasi gagal diperbarui.', 500);
            }

            return $this->jsonSuccess(
                $this->organizationService->findUnit($organizationId, $unitId),
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
