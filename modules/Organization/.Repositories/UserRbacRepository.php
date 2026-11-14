<?php

declare(strict_types=1);

namespace Modules\Rbac\Repositories;

use App\Exceptions\DuplicateUserDataException;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Modules\Rbac\Models\RbacUserModel;
use Modules\Rbac\Models\UserProfileModel;
use Modules\Rbac\Repositories\Interfaces\UserRbacRepositoryInterface;
use RuntimeException;
use Throwable;

class UserRbacRepository implements UserRbacRepositoryInterface
{
    protected RbacUserModel $userModel;

    protected UserProfileModel $profileModel;

    protected UserIdentityModel $identityModel;

    private const PROFILE_FIELDS = [
        'username',
        'firstName',
        'lastName',
        'phone',
        'avatar',
    ];

    public function __construct(
        ?RbacUserModel $userModel = null,
        ?UserIdentityModel $identityModel = null,
        ?UserProfileModel $profileModel = null
    ) {
        $this->userModel = $userModel ?? new RbacUserModel();
        $this->identityModel = $identityModel ?? new UserIdentityModel();
        $this->profileModel = $profileModel ?? new UserProfileModel();
    }

    /**
     * Daftar user aktif secara database (belum soft-deleted).
     */

    public function findAll(): array
    {
        $users = $this->userModel
            ->withDeleted()
            ->withIdentities()
            ->withGroups()
            ->withPermissions()
            ->orderBy('id', 'DESC')
            ->findAll();

        if ($users === []) {
            return [];
        }

        $userIds = array_map(
            static fn(User $user): int => (int) $user->id,
            $users
        );

        $profiles = $this->profileModel
            ->whereIn('user_id', $userIds)
            ->findAll();

        $profilesByUserId = [];

        foreach ($profiles as $profile) {
            $profilesByUserId[(int) $profile['user_id']] = $profile;
        }

        $result = [];

        foreach ($users as $user) {
            $result[] = $this->toArray(
                $user,
                $profilesByUserId[(int) $user->id] ?? null
            );
        }

        return $result;
    }

    public function findAllC(): array
    {
        $users = $this->userModel
            ->withIdentities()
            ->withGroups()
            ->withPermissions()
            ->orderBy('id', 'DESC')
            ->findAll();

        return array_map(
            fn(User $user): array => $this->toArray($user),
            $users
        );
    }

    public function paginate(
        int $page,
        int $perPage,
        string $search = '',
        string $sortField = 'id',
        string $sortDirection = 'desc'
    ): array {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $allowedSorts = [
            'id'          => 'u.id',
            'username'    => 'u.username',
            'name'        => 'up.nama_lengkap',
            'email'       => 'email_identity.secret',
            'active'      => 'u.active',
            'last_active' => 'u.last_active',
            'created_at'  => 'u.created_at',
        ];

        $sortColumn = $allowedSorts[$sortField] ?? 'u.id';
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'ASC' : 'DESC';

        $db = db_connect();

        $buildQuery = function () use ($db, $search) {
            $builder = $db->table('users u')
                ->join(
                    'user_profiles up',
                    'up.user_id = u.id',
                    'left'
                )
                ->join(
                    'auth_identities email_identity',
                    "email_identity.user_id = u.id
                 AND email_identity.type = 'email_password'",
                    'left'
                )
                ->where('u.deleted_at', null);

            if ($search !== '') {
                $builder->groupStart()
                    ->like('u.username', $search)
                    ->orLike('email_identity.secret', $search)
                    ->orLike('up.nama_lengkap', $search)
                    ->orLike('up.nama_panggilan', $search)
                    ->orLike('up.nomor_pegawai', $search)
                    ->groupEnd();
            }

            return $builder;
        };

        // $total = $buildQuery()->countAllResults();
        $total = $db->table('users u')
            ->where('u.deleted_at', null)
            ->countAllResults();

        $rows = $buildQuery()
            ->select([
                'u.id',
                'u.username',
                'u.status',
                'u.status_message',
                'u.active',
                'u.last_active',
                'u.created_at',
                'u.updated_at',
                'email_identity.secret AS email',
                'up.nama_lengkap',
                'up.nama_panggilan',
                'up.nomor_pegawai',
                'up.tanggal_lahir',
                'up.jenis_kelamin',
                'up.nomor_telepon_alternatif',
                'up.alamat',
                'up.foto_profil',

            ])
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('u.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        $userIds = array_map(
            static fn(array $row): int => (int) $row['id'],
            $rows
        );

        $groupsByUser = [];

        if ($userIds !== []) {
            $groupRows = $db->table('auth_groups_users agu')
                ->select('agu.user_id, ag.name, ag.description')
                ->join('auth_groups ag', 'ag.name = agu.group')
                ->whereIn('agu.user_id', $userIds)
                ->get()
                ->getResultArray();

            foreach ($groupRows as $group) {
                $groupsByUser[(int) $group['user_id']][] = [
                    'name'        => $group['name'],
                    'title'       => $group['name'],
                    'description' => $group['description'] ?? '',
                ];
            }
        }

        $data = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];

            $data[] = [
                'id'          => $id,
                'username'    => $row['username'],
                'email'       => $row['email'] ?? '',
                'active'      => $row['active'],
                'status'      => $row['status'],
                'status_message' => $row['status_message'],
                'last_active' => $row['last_active'],
                'created_at'  => $row['created_at'],
                'updated_at'  => $row['updated_at'],
                'groups'      => $groupsByUser[$id] ?? [],
                'profile'     => [
                    'nama_lengkap'             => $row['nama_lengkap'] ?? '',
                    'nama_panggilan'           => $row['nama_panggilan'] ?? '',
                    'nomor_pegawai'            => $row['nomor_pegawai'] ?? '',
                    'tanggal_lahir'            => $row['tanggal_lahir'] ?? '',
                    'jenis_kelamin'             => $row['jenis_kelamin'] ?? '',
                    'nomor_telepon_alternatif' => $row['nomor_telepon_alternatif'] ?? '',
                    'alamat'                   => $row['alamat'] ?? '',
                    'foto_profil'              => $row['foto_profil'] ?? '',
                ],
            ];
        }

        return [
            'data'         => $data,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
            'total'        => $total,
            'current_page' => $page,
            'per_page'     => $perPage,
        ];
    }
    /**
     * Detail user berdasarkan ID.
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $user = $this->userModel
            ->withIdentities()
            ->withGroups()
            ->withPermissions()
            ->findById($id);

        if (! $user instanceof User) {
            return null;
        }

        $profile = $this->profileModel
            ->where('user_id', $id)
            ->first();

        return $this->toArray($user, $profile);

        // return $this->toArray($user);
    }

    /**
     * Update profil user.
     *
     * Email dan password ditangani oleh entity User dan callback
     * UserModel Shield. Password kosong berarti tidak diubah.
     *
     * Group dan permission sengaja tidak diizinkan di sini.
     */
    /* public function updateProfile(int $id, array $data): bool
    {
        if ($id <= 0 || $data === []) {
            return false;
        }

        $allowed = array_merge(
            self::PROFILE_FIELDS,
            ['email', 'password']
        );

        $unknown = array_diff(array_keys($data), $allowed);

        if ($unknown !== []) {
            throw new RuntimeException(
                'Payload memuat field yang tidak diizinkan: '
                . implode(', ', $unknown)
            );
        }

        $user = $this->userModel->findById($id);

        if (! $user instanceof User) {
            return false;
        }

        $changed = false;

        foreach (self::PROFILE_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($field === 'username') {
                if (! is_string($value) || $value === '') {
                    throw new RuntimeException(
                        'Username wajib diisi.'
                    );
                }
            }

            $user->{$field} = $value;
            $changed = true;
        }

        if (array_key_exists('email', $data)) {
            $email = trim((string) $data['email']);

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException(
                    'Format email tidak valid.'
                );
            }

            $this->assertEmailAvailable($email, $id);

            $user->setEmail($email);
            $changed = true;
        }

        if (array_key_exists('password', $data)) {
            $password = (string) $data['password'];

            // Password kosong tidak mengubah password yang tersimpan.
            if ($password !== '') {
                $user->setPassword($password);
                $changed = true;
            }
        }

        if (! $changed) {
            return false;
        }

        // save(User) mempertahankan callback penyimpanan identitas Shield.
        return $this->userModel->save($user);
    }
 */


    public function updateProfile(int $id, array $data): bool
    {
        if ($id <= 0 || $data === []) {
            return false;
        }

        $accountFields = ['username', 'email', 'password'];

        $profileFields = [
            'nama_lengkap',
            'nama_panggilan',
            'nomor_pegawai',
            'tanggal_lahir',
            'jenis_kelamin',
            'nomor_telepon_alternatif',
            'alamat',
        ];

        $allowedFields = array_merge($accountFields, $profileFields);
        $unknownFields = array_diff(array_keys($data), $allowedFields);

        if ($unknownFields !== []) {
            throw new RuntimeException(
                'Field tidak diizinkan: ' . implode(', ', $unknownFields)
            );
        }

        $user = $this->userModel->findById($id);

        if (! $user instanceof User) {
            return false;
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $accountChanged = false;

            /*
             * AKUN SHIELD
             */
            if (array_key_exists('username', $data)) {
                $username = trim((string) $data['username']);

                if ($username === '') {
                    throw new RuntimeException('Username wajib diisi.');
                }

                $existingUsername = $this->userModel
                    ->where('username', $username)
                    ->where('id !=', $id)
                    ->first();

                if ($existingUsername !== null) {
                    throw new DuplicateUserDataException(
                        'Username sudah digunakan.',
                        'username'
                    );
                }

                $user->username = $username;
                $accountChanged = true;
            }

            if (array_key_exists('email', $data)) {
                $email = trim((string) $data['email']);

                if (
                    $email === ''
                    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
                ) {
                    throw new RuntimeException('Format email tidak valid.');
                }

                $this->assertEmailAvailable($email, $id);

                $user->setEmail($email);
                $accountChanged = true;
            }

            if (array_key_exists('password', $data)) {
                $password = (string) $data['password'];

                // Password kosong tidak mengubah password lama.
                if ($password !== '') {
                    if (strlen($password) < 8) {
                        throw new RuntimeException(
                            'Password minimal 8 karakter.'
                        );
                    }

                    $user->setPassword($password);
                    $accountChanged = true;
                }
            }

            if (
                $accountChanged
                && ! $this->userModel->save($user)
            ) {
                throw new RuntimeException(
                    'Gagal memperbarui akun Shield.'
                );
            }

            /*
             * PROFIL PENGGUNA
             */
            $profileData = array_intersect_key(
                $data,
                array_flip($profileFields)
            );

            if ($profileData !== []) {
                foreach ($profileData as $field => $value) {
                    if ($value !== null && ! is_string($value)) {
                        throw new RuntimeException(
                            "Nilai field {$field} tidak valid."
                        );
                    }

                    if (is_string($value)) {
                        $value = trim($value);
                        $profileData[$field] = $value === '' ? null : $value;
                    }
                }

                if (
                    isset($profileData['jenis_kelamin'])
                    && ! in_array(
                        $profileData['jenis_kelamin'],
                        ['L', 'P'],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Jenis kelamin harus L atau P.'
                    );
                }

                if (
                    isset($profileData['tanggal_lahir'])
                    && $profileData['tanggal_lahir'] !== null
                ) {
                    $date = \DateTimeImmutable::createFromFormat(
                        '!Y-m-d',
                        $profileData['tanggal_lahir']
                    );

                    if (
                        $date === false
                        || $date->format('Y-m-d') !== $profileData['tanggal_lahir']
                    ) {
                        throw new RuntimeException(
                            'Tanggal lahir harus menggunakan format YYYY-MM-DD.'
                        );
                    }
                }

                $profile = $this->profileModel
                    ->where('user_id', $id)
                    ->first();

                if ($profile === null) {
                    $profileData['user_id'] = $id;

                    $saved = $this->profileModel->insert($profileData);
                } else {
                    $profileData['id'] = (int) $profile['id'];

                    $saved = $this->profileModel->save($profileData);
                }

                if (! $saved) {
                    throw new RuntimeException(
                        'Gagal menyimpan profil pengguna.'
                    );
                }
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException(
                    'Transaksi akun dan profil gagal.'
                );
            }

            $db->transCommit();

            return true;
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }

    /**
     * Soft-delete memakai konfigurasi UserModel Shield.
     *
     * Akun juga dinonaktifkan terlebih dahulu untuk mencegah
     * penggunaan akun yang sudah dihapus secara lunak.
     */

    public function softDelete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $user = $this->userModel->findById($id);

        if (! $user instanceof User) {
            return false;
        }

        // Mencegah penonaktifan akun yang sudah nonaktif.
        if (! $user instanceof User) {
            return false;
        }

        if ((int) $user->active === 0) {
            return true;
        }

        $user->active = 0;

        // Simpan perubahan status tanpa menghapus record.
        return $this->userModel->save($user);
    }

    public function activate(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        // Ambil akun termasuk yang sudah soft-delete.
        $user = $this->userModel
            ->withDeleted()
            ->findById($id);

        if (! $user instanceof User) {
            return false;
        }

        // Aktifkan kembali akun.
        $user->active = 1;

        // Simpan status aktif.
        if (! $this->userModel->save($user)) {
            return false;
        }

        // Pulihkan deleted_at jika akun pernah soft-delete.
        $this->userModel->builder()
            ->where('id', $id)
            ->set('deleted_at', null)
            ->update();

        return true;
    }


    /**
     * Membaca group melalui entity User.
     */
    public function getGroups(int $userId): array
    {
        $user = $this->findEntity($userId);

        if (! $user instanceof User) {
            return [];
        }

        return array_values($user->getGroups() ?? []);
    }

    /**
     * Membaca permission melalui entity User.
     */
    public function getPermissions(int $userId): array
    {
        $user = $this->findEntity($userId);

        if (! $user instanceof User) {
            return [];
        }

        return array_values($user->getPermissions() ?? []);
    }

    /**
     * Sinkronisasi group melalui Authorizable.
     *
     * Pemanggil harus melakukan pemeriksaan otorisasi terlebih dahulu.
     */
    public function syncGroups(int $userId, array $groups): bool
    {
        $user = $this->requireEntity($userId);
        $groups = $this->normalizeNames($groups);

        $user->syncGroups(...$groups);

        return true;
    }

    /**
     * Sinkronisasi permission langsung pada user melalui Authorizable.
     */

    /**
     * Sinkronisasi permission langsung pengguna.
     *
     * Validasi menggunakan tabel master auth_permissions,
     * bukan konfigurasi statis AuthGroups.permissions milik Shield.
     */
    public function syncPermissions(
        int $userId,
        array $permissions
    ): bool {
        $user = $this->requireEntity($userId);
        $permissions = $this->normalizeNames($permissions);

        $db = $this->userModel->db;

        // Validasi seluruh permission terhadap master database.
        if ($permissions !== []) {
            $rows = $db->table('auth_permissions')
                ->select('name')
                ->whereIn('name', $permissions)
                ->get()
                ->getResultArray();

            $validNames = array_column($rows, 'name');
            $invalidNames = array_diff($permissions, $validNames);

            if ($invalidNames !== []) {
                throw new RuntimeException(
                    'Permission tidak ditemukan: '
                        . implode(', ', $invalidNames)
                );
            }
        }

        // Sinkronisasi langsung ke tabel Shield tanpa melewati
        // validasi konfigurasi statis AuthGroups.permissions.
        $db->transBegin();

        try {
            $builder = $db->table('auth_permissions_users');

            if ($permissions === []) {
                $builder->where('user_id', $userId)->delete();
            } else {
                $builder->where('user_id', $userId)
                    ->whereNotIn('permission', $permissions)
                    ->delete();

                $existingRows = $db->table('auth_permissions_users')
                    ->select('permission')
                    ->where('user_id', $userId)
                    ->get()
                    ->getResultArray();

                $existing = array_column($existingRows, 'permission');
                $newPermissions = array_values(
                    array_diff($permissions, $existing)
                );

                if ($newPermissions !== []) {
                    $now = date('Y-m-d H:i:s');

                    $inserts = [];

                    foreach ($newPermissions as $permission) {
                        $inserts[] = [
                            'user_id'    => $userId,
                            'permission' => $permission,
                            'created_at' => $now,
                        ];
                    }

                    $db->table('auth_permissions_users')
                        ->insertBatch($inserts);
                }
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException(
                    'Gagal menyimpan permission pengguna.'
                );
            }

            $db->transCommit();

            return true;
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }
    public function syncPermissions2(
        int $userId,
        array $permissions
    ): bool {
        $user = $this->requireEntity($userId);
        $permissions = $this->normalizeNames($permissions);

        $user->syncPermissions(...$permissions);

        return true;
    }

    /**
     * Mengambil entity User untuk operasi yang membutuhkan Authorizable.
     */
    protected function findEntity(int $userId): ?User
    {
        if ($userId <= 0) {
            return null;
        }

        $user = $this->userModel->findById($userId);

        return $user instanceof User ? $user : null;
    }

    protected function requireEntity(int $userId): User
    {
        $user = $this->findEntity($userId);

        if (! $user instanceof User) {
            throw new RuntimeException(
                'User tidak ditemukan atau sudah dihapus.'
            );
        }

        return $user;
    }

    /**
     * Normalisasi daftar group/permission.
     */
    protected function normalizeNames(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new RuntimeException(
                    'Setiap group atau permission harus berupa string.'
                );
            }

            $value = strtolower(trim($value));

            if ($value === '') {
                continue;
            }

            $normalized[$value] = $value;
        }

        return array_values($normalized);
    }

    /**
     * Mencegah email digunakan oleh akun lain.
     */


    protected function assertEmailAvailable(
        string $email,
        int $currentUserId
    ): void {
        $identity = $this->identityModel
            ->select('user_id')
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->where('secret', $email)
            ->first();

        if ($identity === null) {
            return;
        }

        $ownerId = is_array($identity)
            ? (int) ($identity['user_id'] ?? 0)
            : (int) ($identity->user_id ?? 0);

        if ($ownerId !== 0 && $ownerId !== $currentUserId) {
            throw new DuplicateUserDataException(
                'Email sudah digunakan oleh akun lain.',
                'email'
            );
        }
    }


    protected function assertEmailAvailables(
        string $email,
        int $currentUserId
    ): void {
        $identity = $this->identityModel
            ->select('user_id')
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->where('secret', $email)
            ->first();

        if ($identity === null) {
            return;
        }

        $ownerId = is_array($identity)
            ? (int) ($identity['user_id'] ?? 0)
            : (int) ($identity->user_id ?? 0);

        if ($ownerId !== 0 && $ownerId !== $currentUserId) {
            throw new RuntimeException(
                'Email sudah digunakan oleh akun lain.'
            );
        }
    }

    /**
     * Konversi entity Shield menjadi format array API.
     */
    protected function toArray(
        User $user,
        ?array $profile = null
    ): array {
        return [
            'id'            => (int) $user->id,
            'username'      => $user->username,
            'firstName'     => $user->firstName,
            'lastName'      => $user->lastName,
            'phone'         => $user->phone,
            'avatar'        => $user->avatar,
            'status'        => $user->status,
            'status_message' => $user->status_message,
            'active'        => $user->active,
            'last_active'   => $user->last_active,
            'created_at'    => $user->created_at,
            'updated_at'    => $user->updated_at,
            'email'         => $user->getEmail(),
            'groups'        => array_values($user->getGroups() ?? []),
            'permissions'   => array_values($user->getPermissions() ?? []),
            'profile' => $profile === null ? null : [
                'nama_lengkap'             => $profile['nama_lengkap'],
                'nama_panggilan'           => $profile['nama_panggilan'],
                'nomor_pegawai'            => $profile['nomor_pegawai'],
                'tanggal_lahir'            => $profile['tanggal_lahir'],
                'jenis_kelamin'            => $profile['jenis_kelamin'],
                'nomor_telepon_alternatif' => $profile['nomor_telepon_alternatif'],
                'alamat'                   => $profile['alamat'],
                'foto_profil'              => $profile['foto_profil'],
            ],
        ];
    }

    /**
     * Mengambil permission yang diberikan langsung kepada pengguna.
     *
     * Tidak termasuk permission yang diwarisi dari group.
     */
    public function getDirectPermissions(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->userModel->db->table('auth_permissions_users')
            ->select('permission')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_filter(
            array_map(
                static fn(array $row): string => trim(
                    (string) ($row['permission'] ?? '')
                ),
                $rows
            ),
            static fn(string $permission): bool => $permission !== ''
        )));
    }
}
