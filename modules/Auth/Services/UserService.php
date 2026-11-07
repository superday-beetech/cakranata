<?php

namespace Modules\Auth\Services;

use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Shield\Entities\User;
use Exception;
use Modules\Auth\Repositories\Interfaces\UserRepositoryInterface;

class UserService
{
    protected UserRepositoryInterface $userRepo;

    public function __construct(UserRepositoryInterface $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    /**
     * Format data profil pengguna + URL Avatar & Thumbnail
     */
    public function getProfile(User $user): array
    {
        $avatarUrl    = null;
        $thumbnailUrl = null;

        if (! empty($user->avatar)) {
            $avatarUrl    = base_url('uploads/avatars/' . $user->avatar);
            $thumbnailUrl = base_url('uploads/avatars/thumb/' . $user->avatar);
        }

        return [
            'id'         => $user->id,
            'username'   => $user->username,
            'email'      => $user->email,
            'avatar'     => $avatarUrl,
            'thumb'       => $thumbnailUrl,
            'created_at' => $user->created_at?->toDateTimeString(),
            'updated_at' => $user->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * Update profil + Kompresi Gambar & Pembuatan Thumbnail
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $avatarFile = null): array
    {
        // 1. Validasi Keunikan Username/Email
        if (isset($data['username']) && $data['username'] !== $user->username) {
            $existingUser = $this->userRepo->findByUsernameOrEmail($data['username']);
            if ($existingUser && $existingUser->id !== $user->id) {
                throw new Exception('Username sudah digunakan.', 422);
            }
        }

        if (isset($data['email']) && $data['email'] !== $user->email) {
            $existingUser = $this->userRepo->findByUsernameOrEmail($data['email']);
            if ($existingUser && $existingUser->id !== $user->id) {
                throw new Exception('Email sudah digunakan.', 422);
            }
        }

        // 2. Tentukan Direktori Penyimpanan
        $uploadPath    = FCPATH . 'uploads/avatars/';
        $thumbPath     = FCPATH . 'uploads/avatars/thumb/';

        // Buat direktori jika belum ada
        if (! is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        if (! is_dir($thumbPath)) {
            mkdir($thumbPath, 0755, true);
        }

        // 3. Proses File Upload
        if ($avatarFile !== null && $avatarFile->isValid() && ! $avatarFile->hasMoved()) {
            $newFileName = $avatarFile->getRandomName();

            // Simpan file asli ke folder uploads/avatars/
            $avatarFile->move($uploadPath, $newFileName);

            $originalFile = $uploadPath . $newFileName;
            $thumbFile    = $thumbPath . $newFileName;

            // --- A. KOMPRESI & RESIZE GAMBAR UTAMA (Maksimal 800x800px, Quality 80%) ---
            \Config\Services::image('gd')
                ->withFile($originalFile)
                ->resize(800, 800, true, 'height') // Maintain aspect ratio
                ->save($originalFile, 80);        // Kompresi kualitas ke 80%

            // --- B. BUAT THUMBNAIL (SQUARE 150x150px) ---
            \Config\Services::image('gd')
                ->withFile($originalFile)
                ->fit(150, 150, 'center')          // Crop center 150x150
                ->save($thumbFile, 75);            // Simpan thumbnail dengan kualitas 75%

            // --- C. HAPUS FOTO DAN THUMBNAIL LAMA ---
            if (! empty($user->avatar)) {
                if (is_file($uploadPath . $user->avatar)) {
                    unlink($uploadPath . $user->avatar);
                }
                if (is_file($thumbPath . $user->avatar)) {
                    unlink($thumbPath . $user->avatar);
                }
            }

            $data['avatar'] = $newFileName;
        }

        if (empty($data)) {
            throw new Exception('Tidak ada data yang dikirim.', 400);
        }

        // 4. Update Database
        $success = $this->userRepo->updateUserProfile($user->id, $data);

        if (! $success) {
            throw new Exception('Gagal memperbarui profil.', 500);
        }

        $updatedUser = $this->userRepo->findById($user->id);

        return $this->getProfile($updatedUser);
    }

    /**
     * Hapus Foto Fisik & Thumbnail
     */
    public function deleteAvatar(User $user): array
    {
        if (! empty($user->avatar)) {
            $uploadPath = FCPATH . 'uploads/avatars/' . $user->avatar;
            $thumbPath  = FCPATH . 'uploads/avatars/thumb/' . $user->avatar;

            if (is_file($uploadPath)) {
                unlink($uploadPath);
            }
            if (is_file($thumbPath)) {
                unlink($thumbPath);
            }

            $this->userRepo->updateUserProfile($user->id, ['avatar' => null]);
        }

        $updatedUser = $this->userRepo->findById($user->id);

        return $this->getProfile($updatedUser);
    }

    /**
     * Ubah password pengguna yang sedang login
     */
    public function changePassword(User $user, string $oldPassword, string $newPassword): bool
    {
        // 1. Verifikasi password lama menggunakan authenticator Shield
        $credentials = [
            'email'    => $user->email,
            'password' => $oldPassword,
        ];

        $auth = auth('tokens')->getProvider();
        $authenticatedUser = $auth->validate($credentials);

        if (! $authenticatedUser) {
            throw new Exception('Password lama tidak sesuai.', 422);
        }

        // 2. Set password baru (Shield otomatis mengenkripsi/hashing saat di-save ke Entity)
        $user->password = $newPassword;

        // 3. Simpan perubahan ke database melalui model
        $userModel = model(\Modules\Auth\Models\UserModel::class);
        return $userModel->save($user);
    }
}
