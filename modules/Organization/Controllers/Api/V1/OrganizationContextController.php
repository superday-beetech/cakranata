<?php

declare(strict_types=1);

namespace Modules\Organization\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Modules\Organization\Services\OrganizationContextService;
use RuntimeException;
use Throwable;

class OrganizationContextController extends ApiController
{
    private OrganizationContextService $contextService;

    public function __construct()
    {
        $this->contextService = new OrganizationContextService();
    }

    public function index()
    {
        return true;
    }

    /**
     * GET organization/context
     *
     * Mengambil konteks organisasi yang sedang aktif.
     */
    public function indexContext()
    {
        $userId = $this->authenticatedUserId();

        if ($userId === 0) {
            return $this->jsonError(
                'UNAUTHENTICATED',
                'Sesi pengguna tidak ditemukan.',
                401
            );
        }

        try {
            $context = $this->contextService->getCurrentContext($userId);

            return $this->jsonSuccess(
                $context,
                $context === null
                    ? 'Belum ada organisasi aktif.'
                    : 'Konteks organisasi berhasil dimuat.'
            );
        } catch (Throwable $e) {
            log_message('error', '[Organization Context] ' . $e->getMessage());

            return $this->jsonError(
                'CONTEXT_LOAD_FAILED',
                'Gagal memuat konteks organisasi.',
                500
            );
        }
    }

    /**
     * GET organization/available
     *
     * Mengambil daftar organisasi yang dapat diakses pengguna.
     */
    public function indexAvailable()
    {
        $userId = $this->authenticatedUserId();

        if ($userId === 0) {
            return $this->jsonError(
                'UNAUTHENTICATED',
                'Sesi pengguna tidak ditemukan.',
                401
            );
        }

        try {
            $organizations = $this->contextService
                ->getAvailableOrganizations($userId);

            return $this->jsonSuccess(
                $organizations,
                'Daftar organisasi berhasil dimuat.'
            );
        } catch (Throwable $e) {
            log_message('error', '[Organization Available] ' . $e->getMessage());

            return $this->jsonError(
                'ORGANIZATION_LIST_FAILED',
                'Gagal memuat daftar organisasi.',
                500
            );
        }
    }

    /**
     * POST organization/switch
     *
     * Body:
     * {
     *     "organization_id": 2
     * }
     */
    public function switchOrganization()
    {
        $userId = $this->authenticatedUserId();

        if ($userId === 0) {
            return $this->jsonError(
                'UNAUTHENTICATED',
                'Sesi pengguna tidak ditemukan.',
                401
            );
        }

        $data = $this->getRequestData();

        $organizationId = $data['organization_id'] ?? null;

        if (
            ! is_numeric($organizationId)
            || (int) $organizationId <= 0
        ) {
            return $this->jsonError(
                'VALIDATION_ERROR',
                'Organization ID wajib diisi dengan nilai yang valid.',
                422
            );
        }

        try {
            $context = $this->contextService->switchOrganization(
                $userId,
                (int) $organizationId
            );

            return $this->jsonSuccess(
                $context,
                'Organisasi aktif berhasil diganti.'
            );
        } catch (InvalidArgumentException $e) {
            return $this->jsonError(
                'ORGANIZATION_ACCESS_DENIED',
                $e->getMessage(),
                403
            );
        } catch (Throwable $e) {
            log_message('error', '[Organization Switch] ' . $e->getMessage());

            return $this->jsonError(
                'ORGANIZATION_SWITCH_FAILED',
                'Gagal mengganti organisasi aktif.',
                500
            );
        }
    }

    /**
     * POST organization/clear
     *
     * Menghapus konteks organisasi aktif dari session.
     */
    public function clearContext()
    {
        $userId = $this->authenticatedUserId();

        if ($userId === 0) {
            return $this->jsonError(
                'UNAUTHENTICATED',
                'Sesi pengguna tidak ditemukan.',
                401
            );
        }

        try {
            $this->contextService->clearContext();

            return $this->jsonSuccess(
                null,
                'Konteks organisasi berhasil dihapus.'
            );
        } catch (Throwable $e) {
            log_message('error', '[Organization Clear Context] ' . $e->getMessage());

            return $this->jsonError(
                'CONTEXT_CLEAR_FAILED',
                'Gagal menghapus konteks organisasi.',
                500
            );
        }
    }

    /**
     * Mengambil ID pengguna dari Shield.
     */
    private function authenticatedUserId(): int
    {
        $user = auth()->user();

        if ($user === null) {
            return 0;
        }

        return (int) $user->id;
    }

    /**
     * Membaca request JSON dengan fallback form POST.
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
