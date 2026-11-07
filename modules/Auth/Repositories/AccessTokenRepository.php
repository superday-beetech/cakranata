<?php

declare(strict_types=1);

namespace Modules\Auth\Repositories;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Modules\Auth\Models\UserModel;
use Modules\Auth\Repositories\Interfaces\AccessTokenRepositoryInterface;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    protected UserIdentityModel $identityModel;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->identityModel = model(UserIdentityModel::class);
        $this->userModel = model(UserModel::class);
    }

    public function getForUser(int $userId): array
    {
        return $this->identityModel
            ->where('user_id', $userId)
            ->where('type', 'access_token')
            ->findAll();
    }

    public function findById(User $user, int $tokenId): ?object
    {
        $token = $user->getAccessTokenById($tokenId);

        return is_object($token) ? $token : null;
    }

    public function revokeById(User $user, int $tokenId): bool
    {
        $token = $this->findById($user, $tokenId);

        if ($token === null) {
            return false;
        }

        $user->revokeAccessTokenBySecret($token->secret);

        return true;
    }

    public function revokeByHash(
        int $userId,
        string $tokenHash
    ): void {
        $token = $this->identityModel
            ->where('user_id', $userId)
            ->where('secret', $tokenHash)
            ->where('type', 'access_token')
            ->first();

        if (! $token) {
            return;
        }

        $user = $this->userModel->findById($userId);

        if (! $user) {
            return;
        }

        $user->revokeAccessTokenBySecret($token->secret);
    }

    public function revokeRaw(User $user, string $rawToken): void
    {
        $user->revokeAccessToken($rawToken);
    }

    public function revokeAll(User $user): void
    {
        $user->revokeAllAccessTokens();
    }
}
