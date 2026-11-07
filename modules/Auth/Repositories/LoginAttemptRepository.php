<?php

declare(strict_types=1);

namespace Modules\Auth\Repositories;

use Config\Database;
use Modules\Auth\Repositories\Interfaces\LoginAttemptRepositoryInterface;

class LoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    public function logAttempt(
        string $identifier,
        ?int $userId,
        bool $success,
        string $ip,
        string $userAgent
    ): void {
        Database::connect()->table('auth_token_logins')->insert([
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'id_type'    => filter_var(
                $identifier,
                FILTER_VALIDATE_EMAIL
            ) ? 'email' : 'username',
            'identifier' => $identifier,
            'user_id'    => $userId,
            'date'       => date('Y-m-d H:i:s'),
            'success'    => $success ? 1 : 0,
        ]);
    }
}
