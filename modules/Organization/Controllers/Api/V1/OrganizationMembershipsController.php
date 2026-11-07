<?php

declare(strict_types=1);

namespace Modules\Organization\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Modules\Organization\Services\OrganizationMembershipService;
use RuntimeException;
use Throwable;

class OrganizationMembershipsController extends ApiController
{
    private OrganizationMembershipService $membershipService;

    public function __construct()
    {
        $this->membershipService = new OrganizationMembershipService();
    }

    /**
     * GET: Daftar membership dalam organisasi.
     */
    public function indexMemberships(int $organizationId): ResponseInterface
    {
        try {
            $page = max(1, (int) ($this->request->getGet('page') ?? 1));
            $perPage = max(1, (int) ($this->request->getGet('per_page') ?? 10));

            $search = $this->request->getGet('q');
            $status = $this->request->getGet('status');

            $result = $this->membershipService->listForOrganization(
                $organizationId,
                $page,
                $perPage,
                $search !== null ? (string) $search : null,
                $status !== null ? (string) $status : null
            );

            return $this->jsonSuccess(
                $result,
                'Data membership berhasil dimuat.'
            );
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * POST: Detail membership.
     *
     * Body:
     * {
     *     "organization_id": 1,
     *     "membership_id": 10
     * }
     */
    public function showMembership(): ResponseInterface
    {
        try {
            $data = $this->getRequestData();

            $organizationId = (int) ($data['organization_id'] ?? 0);
            $membershipId = (int) ($data['membership_id'] ?? 0);

            if ($organizationId <= 0 || $membershipId <= 0) {
                return $this->jsonError(
                    'VALIDATION_ERROR',
                    'Organization ID dan Membership ID wajib diisi.',
                    422
                );
            }

            $membership = $this->membershipService->findForOrganization(
                $organizationId,
                $membershipId
            );

            if (!$membership) {
                return $this->jsonError(
                    'NOT_FOUND',
                    'Membership tidak ditemukan.',
                    404
                );
            }

            return $this->jsonSuccess(
                $membership,
                'Detail membership berhasil dimuat.'
            );
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * POST: Membuat membership.
     */
    public function createMembership(int $organizationId): ResponseInterface
    {
        try {
            $data = $this->getRequestData();

            $membershipId = $this->membershipService->create(
                $organizationId,
                $data
            );

            $membership = $this->membershipService->findForOrganization(
                $organizationId,
                $membershipId
            );

            return $this->jsonSuccess(
                $membership,
                'Membership berhasil ditambahkan.',
                201
            );
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * PUT: Memperbarui membership.
     */
    public function updateMemberships(): ResponseInterface
    {
        try {
            $data = $this->getRequestData();

            $organizationId = (int) ($data['organization_id'] ?? 0);
            $membershipId = (int) ($data['membership_id'] ?? 0);
            $this->membershipService->update(
                $organizationId,
                $membershipId,
                $data
            );

            $membership = $this->membershipService->findForOrganization(
                $organizationId,
                $membershipId
            );

            return $this->jsonSuccess(
                $membership,
                'Membership berhasil diperbarui.'
            );
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * DELETE: Menonaktifkan membership.
     */
    public function deleteMembership(
        int $organizationId,
        int $membershipId
    ): ResponseInterface {
        try {
            $this->membershipService->deactivate(
                $organizationId,
                $membershipId
            );

            return $this->jsonSuccess(
                ['id' => $membershipId],
                'Membership berhasil dinonaktifkan.'
            );
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * GET: Daftar user dari Shield.
     */
    public function indexUsers(): ResponseInterface
    {
        try {
            $page = max(1, (int) ($this->request->getGet('page') ?? 1));
            $perPage = max(1, (int) ($this->request->getGet('per_page') ?? 10));
            $search = $this->request->getGet('q');

            $result = $this->membershipService->listUsers(
                $page,
                $perPage,
                $search !== null ? (string) $search : null
            );

            return $this->jsonSuccess(
                $result,
                'Daftar user berhasil dimuat.'
            );
        } catch (Throwable $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Mengambil payload JSON atau form request.
     */
    private function getRequestData(): array
    {
        $data = $this->request->getJSON(true);

        if (is_array($data)) {
            return $data;
        }

        $data = $this->request->getPost();

        return is_array($data) ? $data : [];
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

    /**
     * Penanganan error API.
     */
    private function handleError(Throwable $e): ResponseInterface
    {
        if ($e instanceof InvalidArgumentException) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                $e->getMessage(),
                422
            );
        }

        if ($e instanceof RuntimeException) {
            $message = $e->getMessage();

            if (
                str_contains(strtolower($message), 'tidak ditemukan') ||
                str_contains(strtolower($message), 'tidak aktif')
            ) {
                return $this->jsonError(
                    'NOT_FOUND',
                    $message,
                    404
                );
            }

            return $this->jsonError(
                'OPERATION_FAILED',
                $message,
                422
            );
        }

        log_message('error', '[OrganizationMembershipsController] {message}', [
            'message' => $e->getMessage(),
        ]);

        return $this->jsonError(
            'SERVER_ERROR',
            'Terjadi kesalahan pada server.',
            500
        );
    }
}
