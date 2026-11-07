<?php

declare(strict_types=1);

namespace Modules\Rbac\Controllers;

use App\Core\BackendController;
use Modules\Auth\Models\UserModel;
use Modules\Rbac\Models\UserProfileModel;
use Throwable;

class UsersController extends BackendController
{
    private UserModel $userModel;
    private UserProfileModel $profileModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->profileModel = new UserProfileModel();
    }

    public function index()
    {
        // try {
        $records = auth()->getProvider()
            ->select('id, username')
            ->orderBy('id', 'ASC')
            ->findAll();

        // Lanjutkan ke view setelah memastikan data valid
        $this->theme->setTitle('User Management');

        return $this->render('users/index', [
            'title' => 'User Management',
            'users' => $records,
        ]);
        /*     } catch (Throwable $e) {
            log_message('error', '[UsersController::index] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with(
                'error',
                'Gagal memuat daftar pengguna.'
            );
        } */
    }

    public function edit($id = null)
    {
        $userId = filter_var($id, FILTER_VALIDATE_INT);

        if ($userId === false || $userId === null || $userId < 1) {
            return redirect()->to(site_url('rbac/users'))
                ->with('error', 'ID user tidak valid.');
        }

        $user = $this->userModel->find($userId);

        if ($user === null) {
            return redirect()->to(site_url('rbac/users'))
                ->with('error', 'User tidak ditemukan.');
        }

        $userData = [
            'id' => (int) (
                is_array($user) ? $user['id'] : $user->id
            ),
            'username' => (string) (
                is_array($user)
                ? ($user['username'] ?? '')
                : ($user->username ?? '')
            ),
        ];

        $profile = $this->profileModel->findByUserId($userId);

        $profile ??= [
            'nama_lengkap' => '',
            'nama_panggilan' => '',
            'nomor_pegawai' => '',
            'tanggal_lahir' => '',
            'jenis_kelamin' => '',
            'nomor_telepon_alternatif' => '',
            'alamat' => '',
        ];

        $this->theme->setTitle('Edit User');

        return $this->render('users/form', [
            'title' => 'Edit User',
            'user' => $userData,
            'profile' => $profile,
            'action' => site_url('rbac/users/update/' . $userId),
        ]);
    }



    public function update($id = null)
    {
        $userId = filter_var($id, FILTER_VALIDATE_INT);

        if ($userId === false || $userId === null || $userId < 1) {
            return redirect()->to(site_url('rbac/users'))
                ->with('error', 'ID user tidak valid.');
        }

        $user = $this->userModel->find($userId);

        if ($user === null) {
            return redirect()->to(site_url('rbac/users'))
                ->with('error', 'User tidak ditemukan.');
        }

        $username = trim((string) $this->request->getPost('username'));

        $profileData = [
            'nama_lengkap' => trim((string) $this->request->getPost('nama_lengkap')),
            'nama_panggilan' => trim((string) $this->request->getPost('nama_panggilan')),
            'nomor_pegawai' => trim((string) $this->request->getPost('nomor_pegawai')),
            'tanggal_lahir' => trim((string) $this->request->getPost('tanggal_lahir')),
            'jenis_kelamin' => trim((string) $this->request->getPost('jenis_kelamin')),
            'nomor_telepon_alternatif' => trim((string) $this->request->getPost('nomor_telepon_alternatif')),
            'alamat' => trim((string) $this->request->getPost('alamat')),
        ];

        if ($username === '' || mb_strlen($username) > 30) {
            return redirect()->back()->withInput()
                ->with('error', 'Username wajib diisi dan maksimal 30 karakter.');
        }

        if (! in_array($profileData['jenis_kelamin'], ['', 'L', 'P'], true)) {
            return redirect()->back()->withInput()
                ->with('error', 'Jenis kelamin tidak valid.');
        }

        if ($profileData['tanggal_lahir'] !== '') {
            $date = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $profileData['tanggal_lahir']
            );

            if (
                $date === false
                || $date->format('Y-m-d') !== $profileData['tanggal_lahir']
            ) {
                return redirect()->back()->withInput()
                    ->with('error', 'Format tanggal lahir tidak valid.');
            }
        }

        $duplicateUser = $this->userModel
            ->where('username', $username)
            ->where('id !=', $userId)
            ->first();

        if ($duplicateUser !== null) {
            return redirect()->back()->withInput()
                ->with('error', 'Username sudah digunakan.');
        }

        $profileData['tanggal_lahir'] =
            $profileData['tanggal_lahir'] !== ''
            ? $profileData['tanggal_lahir']
            : null;

        $profileData['jenis_kelamin'] =
            $profileData['jenis_kelamin'] !== ''
            ? $profileData['jenis_kelamin']
            : null;

        foreach (
            [
                'nama_lengkap',
                'nama_panggilan',
                'nomor_pegawai',
                'nomor_telepon_alternatif',
                'alamat',
            ] as $field
        ) {
            $profileData[$field] = $profileData[$field] !== ''
                ? $profileData[$field]
                : null;
        }

        $profileData['user_id'] = $userId;

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            if (! $this->userModel->update($userId, [
                'username' => $username,
            ])) {
                throw new \RuntimeException('Gagal memperbarui akun user.');
            }

            $existingProfile = $this->profileModel->findByUserId($userId);

            if ($existingProfile !== null) {
                $saved = $this->profileModel->update(
                    (int) $existingProfile['id'],
                    $profileData
                );
            } else {
                $saved = $this->profileModel->insert($profileData);
            }

            if ($saved === false || $db->transStatus() === false) {
                throw new \RuntimeException('Gagal menyimpan profil user.');
            }

            if ($db->transCommit() === false) {
                throw new \RuntimeException('Gagal menyelesaikan transaksi.');
            }

            return redirect()->to(site_url('rbac/users'))
                ->with('success', 'Akun dan profil user berhasil diperbarui.');
        } catch (\Throwable $e) {
            $db->transRollback();

            log_message('error', '[UsersController::update] {message}', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()
                ->with('error', 'Gagal menyimpan akun dan profil user.');
        }
    }
}
