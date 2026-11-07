<?php

namespace Modules\Auth\Repositories;

use CodeIgniter\Shield\Models\UserIdentityModel;
use Modules\Auth\Models\UserModel;
use Modules\Auth\Repositories\Interfaces\TokenRepositoryInterface;

class TokenRepository implements TokenRepositoryInterface
{
    protected UserIdentityModel $identityModel;
    protected UserModel $userModel;


    public function __construct()
    {
        $this->identityModel = model(UserIdentityModel::class);
        $this->userModel = model(UserModel::class);
    }

    public function logAttempt(string $identifier, ?int $userId, bool $success, string $ip, string $userAgent): void
    {
        $db = \Config\Database::connect();
        $db->table('auth_token_logins')->insert([
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'id_type'    => filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username',
            'identifier' => $identifier,
            'user_id'    => $userId,
            'date'       => date('Y-m-d H:i:s'),
            'success'    => $success ? 1 : 0,
        ]);
    }

    public function findTokenByRaw(int $userId, string $rawToken): ?object
    {
        return $this->identityModel
            ->where('user_id', $userId)
            ->where('secret', hash('sha256', $rawToken))
            ->where('type', 'access_token')
            ->first();
    }

    public function getUserAccessTokens(int $userId): array
    {
        return $this->identityModel
            ->where('user_id', $userId)
            ->where('type', 'access_token')
            ->findAll();
    }

    public function revokeTokenById(int $tokenId, int $userId): bool
    {
        $user = $this->userModel->findById($userId);

        if (! $user) {
            return false;
        }

        $token = $user->getAccessTokenById($tokenId);

        if (! $token) {
            return false;
        }

        // Shield menangani pencabutan access token.
        $user->revokeAccessTokenBySecret($token->secret);

        // Cabut refresh token ACN yang berpasangan dengan access token ini.
        $this->revokeRefreshTokenByAccessHash(
            $userId,
            $token->secret
        );

        return true;
    }

    public function createRefreshToken(
        int $userId,
        string $familyId,
        string $tokenHash,
        ?string $accessTokenHash,
        string $userAgent,
        string $ipAddress,
        ?string $expiresAt = null
    ): void {
        db_connect()->table('auth_access_tokens')->insert([
            'user_id'           => $userId,
            'family_id'         => $familyId,
            'token_hash'        => $tokenHash,
            'access_token_hash' => $accessTokenHash,
            'user_agent'        => $userAgent ?: null,
            'ip_address'        => $ipAddress ?: null,
            'expires_at'        => $expiresAt
                ?? date('Y-m-d H:i:s', time() + 86400),
            'revoked_at'        => null,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);
    }



    public function findRefreshToken(string $tokenHash): ?array
    {
        return db_connect()->table('auth_access_tokens')
            ->where('token_hash', $tokenHash)
            ->get()
            ->getRowArray() ?: null;
    }

    public function consumeRefreshToken(int $id): bool
    {
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        $db->table('auth_access_tokens')
            ->where('id', $id)
            ->where('consumed_at', null)
            ->where('revoked_at', null)
            ->where('expires_at >', $now)
            ->update([
                'consumed_at' => $now,
            ]);

        return $db->affectedRows() === 1;
    }

    public function revokeRefreshFamily(string $familyId): void
    {
        db_connect()->table('auth_access_tokens')
            ->where('family_id', $familyId)
            ->where('revoked_at', null)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function revokeRefreshTokensByUser(int $userId): void
    {
        db_connect()->table('auth_access_tokens')
            ->where('user_id', $userId)
            ->where('revoked_at', null)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function revokeRefreshTokenByAccessHash(
        int $userId,
        string $accessTokenHash
    ): void {
        db_connect()->table('auth_access_tokens')
            ->where('user_id', $userId)
            ->where('access_token_hash', $accessTokenHash)
            ->where('revoked_at', null)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function revokeAccessTokenByHash(
        int $userId,
        string $accessTokenHash
    ): void {
        $token = $this->identityModel
            ->where('user_id', $userId)
            ->where('secret', $accessTokenHash)
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

    public function deleteRefreshTokenByAccessHash(int $userId, string $accessTokenHash): bool
    {
        return db_connect()
            ->table('auth_access_tokens')
            ->where('user_id', $userId)
            ->where('access_token_hash', $accessTokenHash)
            ->delete();
    }


    public function markRefreshTokenReplaced(
        int $id,
        string $replacementHash
    ): bool {
        $db = db_connect();

        $db->table('auth_access_tokens')
            ->where('id', $id)
            ->where('consumed_at IS NOT NULL', null, false)
            ->where('revoked_at', null)
            ->update([
                'replaced_by_hash' => $replacementHash,
            ]);

        return $db->affectedRows() === 1;
    }
}
