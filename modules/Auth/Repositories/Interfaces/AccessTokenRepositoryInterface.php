<?php

declare(strict_types=1);

namespace Modules\Auth\Repositories\Interfaces;

use CodeIgniter\Shield\Entities\User;

interface AccessTokenRepositoryInterface
{
    public function getForUser(int $userId): array;

    public function findById(User $user, int $tokenId): ?object;

    public function revokeById(User $user, int $tokenId): bool;

    public function revokeByHash(int $userId, string $tokenHash): void;

    public function revokeRaw(User $user, string $rawToken): void;

    public function revokeAll(User $user): void;
}
