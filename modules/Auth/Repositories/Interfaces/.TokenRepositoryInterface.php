<?php

namespace Modules\Auth\Repositories\Interfaces;

interface TokenRepositoryInterface
{
    public function logAttempt(
        string $identifier,
        ?int $userId,
        bool $success,
        string $ip,
        string $userAgent
    ): void;

    public function findTokenByRaw(
        int $userId,
        string $rawToken
    ): ?object;

    public function getUserAccessTokens(int $userId): array;

    public function revokeTokenById(
        int $tokenId,
        int $userId
    ): bool;

    public function createRefreshToken(
        int $userId,
        string $familyId,
        string $tokenHash,
        ?string $accessTokenHash,
        string $userAgent,
        string $ipAddress,
        ?string $expiresAt = null
    ): void;

    public function findRefreshToken(string $tokenHash): ?array;

    public function consumeRefreshToken(int $id): bool;

    public function revokeRefreshFamily(string $familyId): void;

    public function revokeRefreshTokensByUser(int $userId): void;

    public function revokeRefreshTokenByAccessHash(
        int $userId,
        string $accessTokenHash
    ): void;

    public function revokeAccessTokenByHash(
        int $userId,
        string $accessTokenHash
    ): void;

    public function deleteRefreshTokenByAccessHash(
        int $userId,
        string $accessTokenHash
    ): bool;

    public function markRefreshTokenReplaced(
        int $id,
        string $replacementHash
    ): bool;
}
