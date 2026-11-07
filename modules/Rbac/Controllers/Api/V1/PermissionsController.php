<?php

declare(strict_types=1);

namespace Modules\Rbac\Controllers\API\V1;

use App\Core\ApiController;
use Modules\Rbac\Services\PermissionService;
use RuntimeException;
use Throwable;

class PermissionsController extends ApiController
{
    protected PermissionService $permissionService;

    public function __construct()
    {
        $this->permissionService = new PermissionService();
    }

    /**
     * GET: daftar permission dengan pagination dan pencarian.
     */
    public function getIndex()
    {
        try {
            $page = max(
                1,
                (int) ($this->request->getGet('page') ?? 1)
            );

            $perPage = max(
                1,
                min(
                    100,
                    (int) ($this->request->getGet('per_page') ?: 20)
                )
            );

            $search = trim(
                (string) ($this->request->getGet('search') ?? '')
            );

            return $this->successResponse(
                $this->permissionService->getPaginated(
                    $page,
                    $perPage,
                    $search
                ),
                'Daftar permission berhasil dimuat.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[RBAC Permissions][index] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal memuat daftar permission.',
                500
            );
        }
    }

    /**
     * GET: detail satu permission.
     */
    public function show($id = null)
    {
        $id = (int) $id;

        if ($id < 1) {
            return $this->errorResponse(
                'ID permission tidak valid.',
                400
            );
        }

        try {
            return $this->successResponse(
                $this->permissionService->find($id),
                'Detail permission berhasil dimuat.'
            );
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Permission tidak ditemukan.') {
                return $this->errorResponse(
                    $e->getMessage(),
                    404
                );
            }

            log_message(
                'error',
                '[RBAC Permissions][show] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal memuat detail permission.',
                500
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[RBAC Permissions][show] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal memuat detail permission.',
                500
            );
        }
    }

    /**
     * POST: tambah permission.
     */
    public function create()
    {
        $data = $this->request->getJSON(true);

        if (! is_array($data)) {
            return $this->errorResponse(
                'Body JSON tidak valid.',
                400
            );
        }

        try {
            $id = $this->permissionService->create($data);

            return $this->successResponse(
                ['id' => $id],
                'Permission berhasil ditambahkan.',
                201
            );
        } catch (RuntimeException $e) {
            $message = $e->getMessage();

            $code = $message === 'Nama permission sudah digunakan.'
                ? 409
                : 422;

            return $this->errorResponse($message, $code);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[RBAC Permissions][create] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal menambahkan permission.',
                500
            );
        }
    }

    /**
     * PUT/PATCH: ubah deskripsi permission.
     */
    public function update($id = null)
    {

        $id = (int) $id;

        if ($id < 1) {
            return $this->errorResponse(
                'ID permission tidak valid.',
                400
            );
        }

        $data = $this->request->getJSON(true);

        if (! is_array($data)) {
            return $this->errorResponse(
                'Body JSON tidak valid.',
                400
            );
        }

        try {
            $this->permissionService->update($id, $data);

            return $this->successResponse(
                $this->permissionService->find($id),
                'Permission berhasil diperbarui.'
            );
        } catch (RuntimeException $e) {
            $message = $e->getMessage();

            $code = $message === 'Permission tidak ditemukan.'
                ? 404
                : 422;

            return $this->errorResponse($message, $code);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[RBAC Permissions][update] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal memperbarui permission.',
                500
            );
        }
    }

    /**
     * DELETE: hapus permission jika tidak sedang digunakan.
     */
    public function delete($id = null)
    {

        $id = (int) $id;

        if ($id < 1) {
            return $this->errorResponse(
                'ID permission tidak valid.',
                400
            );
        }

        try {
            $this->permissionService->delete($id);

            return $this->successResponse(
                ['id' => $id],
                'Permission berhasil dihapus.'
            );
        } catch (RuntimeException $e) {
            $message = $e->getMessage();

            if ($message === 'Permission tidak ditemukan.') {
                return $this->errorResponse($message, 404);
            }

            $code = str_contains(
                $message,
                'masih digunakan'
            ) ? 409 : 422;

            return $this->errorResponse($message, $code);
        } catch (Throwable $e) {
            log_message(
                'error',
                '[RBAC Permissions][delete] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal menghapus permission.',
                500
            );
        }
    }
}
