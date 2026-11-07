<?php

declare(strict_types=1);

namespace Modules\Rbac\Controllers\API\V1;

use App\Core\ApiController;
use DomainException;
use Modules\Rbac\Services\UserGroupService;
use Throwable;

class UserGroupsController extends ApiController
{
    protected UserGroupService $userGroupService;

    public function __construct()
    {
        $this->userGroupService = new UserGroupService();
    }

    /**
     * GET: daftar user, group, dan keanggotaan group user.
     */
    public function getIndex()
    {
        /*
        if (! $this->authorizePermission('rbac.user-groups.view')) {
            return $this->response;
        }
        */

        try {
            $userId = filter_var(
                $this->request->getGet('user_id'),
                FILTER_VALIDATE_INT
            );

            if ($userId !== false && $userId !== null && $userId < 1) {
                return $this->errorResponse(
                    'ID user tidak valid.',
                    400
                );
            }

            return $this->successResponse(
                $this->userGroupService->getOverview(
                    $userId !== false && $userId !== null
                        ? $userId
                        : null
                ),
                'Data group user berhasil dimuat.'
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[RBAC UserGroups][index] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal memuat data group user.',
                500
            );
        }
    }

    /**
     * PUT/PATCH: mengganti seluruh group milik user.
     *
     * JSON:
     * {
     *   "groups": ["admin", "developer"]
     * }
     */
    public function update($id = null)
    {
        $userId = filter_var($id, FILTER_VALIDATE_INT);

        if ($userId === false || $userId === null || $userId < 1) {
            return $this->errorResponse(
                'ID user tidak valid.',
                400
            );
        }

        $data = $this->request->getJSON(true);

        if (
            ! is_array($data)
            || ! array_key_exists('groups', $data)
            || ! is_array($data['groups'])
        ) {
            return $this->errorResponse(
                'Body JSON harus berisi array groups.',
                400
            );
        }

        try {
            $this->userGroupService->save(
                $userId,
                $data['groups']
            );

            return $this->successResponse(
                $this->userGroupService->getOverview($userId),
                'Group user berhasil diperbarui.'
            );
        } catch (DomainException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                422
            );
        } catch (Throwable $e) {
            log_message(
                'error',
                '[RBAC UserGroups][update] ' . $e->getMessage()
            );

            return $this->errorResponse(
                'Gagal memperbarui group user.',
                500
            );
        }
    }
}
