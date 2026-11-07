<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Result;
use RuntimeException;

final class WebAuthenticationService
{
    public function __construct(
        private readonly AuthenticationService $authentication
        = new AuthenticationService(),
    ) {}

    public function login(
        string $identity,
        string $password,
        bool $remember = false,
    ): Result {
        if (auth('session')->loggedIn()) {
            auth('session')->logout();
        }

        $credentials = $this->authentication->resolveIdentity($identity);
        $credentials['password'] = $password;

        $result = auth('session')->attempt($credentials);

        if (! $result->isOK()) {
            return $result;
        }

        if ($remember) {
            auth('session')->remember(true);
        }

        return $result;
    }

    public function logout(): void
    {
        if (! auth('session')->logout()) {
            throw new RuntimeException(
                'Sesi pengguna gagal diakhiri.'
            );
        }
    }

    public function user(): ?User
    {
        $user = auth('session')->user();

        return $user instanceof User ? $user : null;
    }
}
