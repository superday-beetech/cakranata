<?php

declare(strict_types=1);

namespace Modules\Auth\Repositories\Interfaces;

interface RefreshTokenRepositoryInterface
{
    public function create(
        int $userId,
        string $familyId,
        string $tokenHash,
        string $accessTokenHash,
        string $userAgent,
        string $ipAddress,
        string $expiresAt
    ): void;

    public function findByHash(string $tokenHash): ?array;

    public function consume(int $id): bool;

    public function markReplaced(int $id, string $replacementHash): bool;

    public function revokeFamily(string $familyId): void;

    public function revokeByUser(int $userId): void;

    public function revokeByAccessHash(
        int $userId,
        string $accessTokenHash
    ): void;
}
