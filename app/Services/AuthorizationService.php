<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class AuthorizationService
{
    public function __construct(
        private ?AccessControlService $accessControl = null
    ) {
        $this->accessControl ??= service('accessControl');
    }

    public function allows(int $userId, string $permission): bool
    {
        return $this->accessControl->hasPermission(
            $userId,
            $permission
        );
    }

    public function authorize(
        int $userId,
        string $permission
    ): void {
        $this->accessControl->requirePermission(
            $userId,
            $permission
        );
    }

    public function authorizeSuperadmin(int $userId): void
    {
        $this->accessControl->requireSuperadmin($userId);
    }
}
