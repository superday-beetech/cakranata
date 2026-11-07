<?php

declare(strict_types=1);

namespace Modules\Auth\Repositories;

use Config\Database;
use Modules\Auth\Repositories\Interfaces\RefreshTokenRepositoryInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function create(
        int $userId,
        string $familyId,
        string $tokenHash,
        string $accessTokenHash,
        string $userAgent,
        string $ipAddress,
        string $expiresAt
    ): void {
        Database::connect()->table('auth_access_tokens')->insert([
            'user_id'           => $userId,
            'family_id'         => $familyId,
            'token_hash'        => $tokenHash,
            'access_token_hash' => $accessTokenHash,
            'user_agent'        => $userAgent ?: null,
            'ip_address'        => $ipAddress ?: null,
            'consumed_at'       => null,
            'expires_at'        => $expiresAt,
            'revoked_at'        => null,
            'replaced_by_hash'  => null,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    public function findByHash(string $tokenHash): ?array
    {
        return Database::connect()
            ->table('auth_access_tokens')
            ->where('token_hash', $tokenHash)
            ->get()
            ->getRowArray() ?: null;
    }

    public function consume(int $id): bool
    {
        $db = Database::connect();

        $db->table('auth_access_tokens')
            ->where('id', $id)
            ->where('consumed_at', null)
            ->where('revoked_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->update([
                'consumed_at' => date('Y-m-d H:i:s'),
            ]);

        return $db->affectedRows() === 1;
    }

    public function markReplaced(
        int $id,
        string $replacementHash
    ): bool {
        $db = Database::connect();

        $db->table('auth_access_tokens')
            ->where('id', $id)
            ->where('consumed_at IS NOT NULL', null, false)
            ->where('revoked_at', null)
            ->update([
                'replaced_by_hash' => $replacementHash,
            ]);

        return $db->affectedRows() === 1;
    }

    public function revokeFamily(string $familyId): void
    {
        Database::connect()
            ->table('auth_access_tokens')
            ->where('family_id', $familyId)
            ->where('revoked_at', null)
            ->update([
                'revoked_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function revokeByUser(int $userId): void
    {
        Database::connect()
            ->table('auth_access_tokens')
            ->where('user_id', $userId)
            ->where('revoked_at', null)
            ->update([
                'revoked_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function revokeByAccessHash(
        int $userId,
        string $accessTokenHash
    ): void {
        Database::connect()
            ->table('auth_access_tokens')
            ->where('user_id', $userId)
            ->where('access_token_hash', $accessTokenHash)
            ->where('revoked_at', null)
            ->update([
                'revoked_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
