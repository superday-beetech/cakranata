<?php

declare(strict_types=1);

namespace Modules\Rbac\Controllers;

use App\Core\BackendController;
use Modules\Auth\Models\UserModel;
use Throwable;

class UsersController extends BackendController
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function index()
    {
        try {
            $records = $this->userModel
                ->select('id, username')
                ->orderBy('id', 'ASC')
                ->findAll();

            $users = array_map(
                static function ($user): array {
                    return [
                        'id' => (int) (
                            is_array($user) ? $user['id'] : $user->id
                        ),
                        'username' => (string) (
                            is_array($user)
                            ? ($user['username'] ?? '')
                            : ($user->username ?? '')
                        ),
                    ];
                },
                $records
            );

            $this->theme->setTitle('User Management');

            return $this->render('users/index', [
                'title' => 'User Management',
                'users' => $users,
            ]);
        } catch (Throwable $e) {
            log_message('error', '[UsersController::index] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with(
                'error',
                'Gagal memuat daftar pengguna.'
            );
        }
    }
}
