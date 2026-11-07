<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Shield\Entities\User;
use RuntimeException;

final class TokenAuthenticationService
{
    public function __construct(
        private readonly AuthenticationService $authentication
        = new AuthenticationService(),
    ) {}

    /**
     * @return array{
     *     user: User,
     *     access_token: string
     * }
     */
    public function login(
        string $identity,
        string $password,
        string $deviceName = 'mobile',
    ): array {
        $credentials = $this->authentication
            ->resolveIdentity($identity);

        $credentials['password'] = $password;

        // Memeriksa kredensial tanpa membuat session browser.
        $result = auth('tokens')->check($credentials);

        if (! $result->isOK()) {
            throw new RuntimeException(
                'Email/username atau password tidak valid.'
            );
        }

        $user = $result->extraInfo();

        if (! $user instanceof User) {
            throw new RuntimeException(
                'Identitas pengguna tidak valid.'
            );
        }

        if ($user->isBanned()) {
            throw new RuntimeException(
                'Akun tidak diizinkan login.'
            );
        }

        if (! $user->active) {
            throw new RuntimeException(
                'Akun belum aktif.'
            );
        }

        // Jangan melewati action login Shield yang diwajibkan.
        $actions = setting('Auth.actions');

        if (! empty($actions['login'])) {
            throw new RuntimeException(
                'Login memerlukan penyelesaian action autentikasi.'
            );
        }

        $deviceName = trim($deviceName);

        if ($deviceName === '') {
            $deviceName = 'mobile';
        }

        $token = $user->generateAccessToken(
            mb_substr($deviceName, 0, 100)
        );

        return [
            'user'         => $user,
            'access_token' => $token->raw_token,
        ];
    }

    public function logout(User $user, string $rawToken): void
    {
        if (trim($rawToken) === '') {
            throw new RuntimeException(
                'Token tidak tersedia.'
            );
        }

        $user->revokeAccessToken($rawToken);
    }
}
