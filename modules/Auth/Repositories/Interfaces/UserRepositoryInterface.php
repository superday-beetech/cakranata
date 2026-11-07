<?php

namespace Modules\Auth\Repositories\Interfaces;

use CodeIgniter\Shield\Entities\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByUsernameOrEmail(string $identifier): ?User;

    public function createUser(array $data): User;

    public function updateUserProfile(int $userId, array $data): bool;
}
