<?php

namespace Modules\Auth\Repositories;

use CodeIgniter\Shield\Entities\User;
use Exception;
use Modules\Auth\Models\UserModel;
use Modules\Auth\Repositories\Interfaces\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = model(UserModel::class);
    }

    public function findById(int $id): ?User
    {
        /** @var User|null $user */
        $user = $this->userModel->findById($id);

        return $user;
    }

    public function findByUsernameOrEmail(string $identifier): ?User
    {
        $provider = model(UserModel::class);
        $field    = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        /** @var User|null $user */
        $user = $provider->findByCredentials([$field => $identifier]);

        return $user;
    }

    public function createUser(array $data): User
    {
        $user = new User([
            'username' => $data['username'],
            'email'    => $data['email'],
            'password' => $data['password'],
        ]);

        if (! $this->userModel->save($user)) {
            $errors = implode(', ', $this->userModel->errors());
            throw new Exception('Gagal mendaftarkan pengguna: ' . $errors, 400);
        }

        $insertedUser = $this->userModel->find($this->userModel->getInsertID());

        if (! $insertedUser) {
            throw new Exception('Pengguna berhasil dibuat tetapi tidak ditemukan.', 500);
        }

        $insertedUser->addGroup('user');
        $insertedUser->activate();

        return $insertedUser;
    }

    public function createUserss(array $data): User
    {
        $user = new User($data);
        $this->userModel->save($user);

        return $this->userModel->findById($this->userModel->getInsertID());
    }

    public function updateUserProfile(int $userId, array $data): bool
    {
        $user = $this->findById($userId);

        if (! $user) {
            return false;
        }

        $user->fill($data);

        return $this->userModel->save($user);
    }
}
