<?php

declare(strict_types=1);

namespace Modules\Auth\Repositories\Interfaces;

interface LoginAttemptRepositoryInterface
{
    public function logAttempt(
        string $identifier,
        ?int $userId,
        bool $success,
        string $ip,
        string $userAgent
    ): void;
}
