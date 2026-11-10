<?php

use App\Entities\AuthUser;

if (! function_exists('currentUser')) {
    function currentUser(): ?AuthUser
    {
        // return auth()->user();
        $user = auth()->user();

        // Kalau tidak ada user login, return null
        if (! $user) {
            return null;
        }

        // Kalau sudah instance AuthUser, langsung return
        if ($user instanceof AuthUser) {
            return $user;
        }

        // Kalau masih Shield\User biasa, convert ke AuthUser
        return new AuthUser($user->toArray());
    }
}

if (! function_exists('isLogin')) {
    function isLogin(): bool
    {
        return auth()->loggedIn();
    }
}

if (! function_exists('isLogout')) {
    function isLogout(): bool
    {
        return ! auth()->loggedIn();
    }
}

if (! function_exists('currentUserId')) {
    function currentUserId(): ?int
    {
        return currentUser()?->id;
    }
}

if (! function_exists('currentUserName')) {
    function currentUserName(): ?string
    {
        return currentUser()?->username;
    }
}

if (! function_exists('currentUserCreated')) {
    function currentUserCreated(): ?string
    {
        return currentUser()?->created_at;
    }
}

if (! function_exists('currentUserEmail')) {
    function currentUserEmail(): ?string
    {
        return currentUser()?->email;
    }
}
if (! function_exists('currentUserAvatar')) {
    function currentUserAvatar(): ?string
    {
        return currentUser()?->avatar;
    }
}
if (! function_exists('currentUserPasswordChange')) {
    function currentUserPasswordChange(): ?string
    {
        return currentUser()?->password_changed_at;
    }
}
if (! function_exists('currentUserAvatarUrl')) {
    function currentUserAvatarUrl(): ?string
    {
        $user = currentUser(); // dari helper yang sudah ada, return AuthUser|null

        return $user ? $user->getAvatarUrl() : null;
    }
}

if (! function_exists('currentUserFullName')) {
    function currentUserFullName(): ?string
    {
        $user = currentUser(); // dari helper yang sudah ada, return AuthUser|null

        return $user ? $user->getFullName() : null;
    }
}
if (! function_exists('currentUserInitials')) {
    function currentUserInitials(): ?string
    {
        $user = currentUser(); // dari helper yang sudah ada, return AuthUser|null

        return $user ? $user->getInitials() : null;
    }
}
if (! function_exists('userAvatar')) {
    function userAvatar($class = 'avatar-img rounded-circle', $alt = 'avatar'): string
    {
        $avatar  = currentUser()?->avatar;
        $uploads = get_upload_url('uploads/avatars/thumb/' . $avatar);
        if ($avatar && file_exists(FCPATH . 'uploads/avatars/' . $avatar)) {
            // return base_url('uploads/avatars/' . $avatar);
            return "<img class=\" {$class}\"
                                src=\"{$uploads}\"
                                alt=\"{$alt}\">";
        }

        // Kalau tidak ada avatar, return HTML placeholder langsung
        $initial = currentUserInitials() ?? '?';
        $colors  = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];
        $color   = $colors[crc32($initial) % count($colors)];

        return "<div class='{$class} bg-{$color} text-white d-flex align-items-center justify-content-center'
            style=''>{$initial}</div>";
    }
}

if (! function_exists('avatarPlaceholder')) {
    function avatarPlaceholder($name = null)
    {
        $initial = currentUserInitials() ?? '?';  // fallback jika null
        $colors  = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark'];
        $color   = $colors[crc32($initial) % count($colors)];

        return "<div class='avatar-img rounded-circle bg-{$color} text-white d-flex align-items-center justify-content-center' style='width:40px; height:40px; font-weight:bold; font-size:1.25rem; line-height:1;'>{$initial}</div>";
        // return "<div class='avatar avatar-sm bg-{$color} text-white d-flex align-items-center justify-content-center rounded-circle'>{$initial}</div>";
    }
}

if (! function_exists('getUserGroups')) {
    function getUserGroups(): array
    {
        return currentUser()?->getGroupNames() ?? [];
    }
}
if (! function_exists('getUserGroupId')) {
    function getUserGroupId(): ?int
    {
        $groups = currentUser()?->getGroups() ?? [];

        return $groups ? (int) $groups[0] : null;
    }
}
if (! function_exists('getUserGroupNames')) {
    /**
     * Ambil nama-nama group user saat ini sebagai string
     *
     * @param  string $separator Separator antar group, default koma
     * @return string
     */
    function getUserGroupNames(string $separator = ', '): string
    {
        $groups = currentUser()?->getGroupNames() ?? [];

        return implode($separator, $groups);
    }
}

if (! function_exists('isAdmin')) {
    function isAdmin(): bool
    {
        return isLogin() && in_array('admin', currentUser()?->getGroupNames());
    }
}
if (! function_exists('isSuperadmin')) {
    function isSuperadmin(): bool
    {
        if (!isLogin()) {
            return false;
        }

        static $isSuper = null;
        if ($isSuper !== null) {
            return $isSuper;
        }

        $userGroups = currentUser()?->getGroupNames(); // Sudah dalam bentuk nama grup
        $superAdmin = config('AuthGroups')->superAdminGroups ?? ['superadmin'];

        return $isSuper = !empty(array_intersect($userGroups, $superAdmin));
    }
}

if (! function_exists('hasPermissionOrSuperadmin')) {
    function hasPermissionOrSuperadmin(string $permission): bool
    {
        return isSuperadmin() || auth()->user()->can($permission);
    }
}

if (! function_exists('inRoleOrSuperadmin')) {
    function inRoleOrSuperadmin(string $role): bool
    {
        return isSuperadmin() || currentUser()?->hasRole($role);
    }
}

if (! function_exists('isMember')) {
    function isMember(): bool
    {
        return isLogin() && in_array('member', currentUser()?->getGroupNames());
    }
}

if (! function_exists('isUser')) {
    function isUser(): bool
    {
        return isLogin() && currentUser()?->hasRole('user');
    }
}

if (! function_exists('inGroup')) {
    function inGroup(string|array $groups): bool
    {
        if (!isLogin()) {
            return false;
        }

        $userGroups = currentUser()?->getGroupNames() ?? [];

        if (is_array($groups)) {
            return !empty(array_intersect($groups, $userGroups));
        }

        return in_array($groups, $userGroups);
    }
}

if (! function_exists('role')) {
    function role(string|array $roles): bool
    {
        return isLogin() && currentUser()->role($roles);
    }
}
if (! function_exists('hasRole')) {
    function hasRole(string $role): bool
    {
        return isLogin() && currentUser()?->hasRole($role);
    }
}

if (! function_exists('hasAnyRole')) {
    function hasAnyRole(array $roles): bool
    {
        return isLogin() && currentUser()?->hasAnyRole($roles);
    }
}
if (! function_exists('showUserRoles')) {
    function showUserRoles(): string
    {
        if (! isLogin()) {
            return '<em>Tidak ada role</em>';
        }

        // Ambil roles dari groups
        $roles = currentUser()?->getRolesFromGroupsName() ?? [];

        if (empty($roles)) {
            return '<em>Tidak ada role</em>';
        }

        return implode(', ', array_map('ucwords', $roles));
    }
}

if (! function_exists('hasGroupRoleId')) {
    function hasGroupRoleId(string|array $roles): bool
    {
        if (!isLogin()) {
            return false;
        }

        $userRoles = currentUser()?->getRolesFromGroupsId() ?? [];

        if (is_array($roles)) {
            return !empty(array_intersect($roles, $userRoles));
        }

        return in_array($roles, $userRoles);
    }
}
if (! function_exists('hasGroupRoleName')) {
    function hasGroupRoleName(string|array $roles): bool
    {
        if (!isLogin()) {
            return false;
        }

        $userRoles = currentUser()?->getRolesFromGroupsId() ?? [];

        if (is_array($roles)) {
            return !empty(array_intersect($roles, $userRoles));
        }

        return in_array($roles, $userRoles);
    }
}

if (! function_exists('hasGroup')) {
    function hasGroup(string $group): bool
    {
        return isLogin() && in_array($group, currentUser()?->getGroupNames() ?? []);
    }
}

if (! function_exists('hasAnyGroup')) {
    function hasAnyGroup(array $groups): bool
    {
        if (! isLogin()) {
            return false;
        }
        $userGroups = currentUser()?->getGroupNames() ?? [];

        foreach ($groups as $g) {
            if (in_array($g, $userGroups)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('can')) {
    /**
     * Cek apakah user saat ini punya permission tertentu.
     *
     * @param  string $permission Contoh: 'users.create'
     * @return bool
     */
    function can(string $permission): bool
    {
        $user = auth()->user();
        // Super admin bypass: selalu true
        if (hasRole('superadmin')) {
            return true;
        }
        // Pastikan user login dan instance dari AuthUser
        if ($user && $user instanceof AuthUser) {
            return $user->hasPermission($permission);
        }

        return false;
    }
}
if (! function_exists('getUserGroupBadges')) {
    /**
     * Ambil group user saat ini dan langsung bikin badge HTML
     *
     * @return string
     */
    function getUserGroupBadges(): string
    {
        $groups = currentUser()?->getGroupNames() ?? [];

        if (empty($groups)) {
            return '<span class="badge bg-secondary">Guest</span>';
        }

        // Bisa di-custom warna per group
        $colors = [
            'Admin'      => 'primary',
            'Superadmin' => 'danger',
            'User'       => 'success',
        ];

        $badges = array_map(function ($group) use ($colors) {
            $color = $colors[$group] ?? 'secondary';

            return '<span class="badge bg-' . $color . '">' . esc($group) . '</span>';
        }, $groups);

        return implode(' ', $badges);
    }
}

if (! function_exists('random_base62')) {
    /**
     * Membuat string acak dengan karakter base62
     */
    function random_base62(int $length = 8): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max   = strlen($chars) - 1;
        $s     = '';
        $bytes = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $s .= $chars[ord($bytes[$i]) % ($max + 1)];
        }

        return $s;
    }
}
if (! function_exists('user_encrypt')) {
    /**
     * Buat token pendek yang menunjuk ke blob terenkripsi.
     * $ttl dalam detik (mis 3600 = 1 jam)
     *
     * @param  int               $userId
     * @param  int               $ttl      Waktu cache dalam detik
     * @param  int               $tokenLen Panjang token base62
     * @return string            Token unik
     * @throws \RuntimeException jika key env tidak ditemukan atau encrypt gagal
     */
    function user_encrypt(int $userId, int $ttl = 3600, int $tokenLen = 8): string
    {
        $encKey  = env('USER_ENCRYPT_KEY');
        $hmacKey = env('USER_HMAC_KEY');

        if (!$encKey || !$hmacKey) {
            throw new \RuntimeException('USER_ENCRYPT_KEY / USER_HMAC_KEY belum di-set');
        }

        $method = 'AES-256-CBC';
        $ivLen  = openssl_cipher_iv_length($method);
        $iv     = random_bytes($ivLen);

        // Payload berisi userId + waktu pembuatan
        $payload = json_encode([
            'userId' => $userId,
            'iat'    => time(),
        ], JSON_UNESCAPED_SLASHES);

        $cipher = openssl_encrypt($payload, $method, $encKey, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('Encrypt gagal');
        }

        // Format: iv || cipher || hmac
        $data = $iv . $cipher;
        $hmac = hash_hmac('sha256', $data, $hmacKey, true);

        $blob = base64_encode($data . $hmac);

        // Prefix cache key
        $cachePrefix = 'user_token_';

        // Retry limit untuk generate token unik supaya tidak infinite loop
        $maxRetries = 10;
        $retry      = 0;

        do {
            if ($retry >= $maxRetries) {
                throw new \RuntimeException('Gagal generate token unik setelah beberapa percobaan');
            }

            $token = random_base62($tokenLen);
            $retry++;
        } while (cache()->get($cachePrefix . $token) !== null);

        // Simpan blob ke cache (prefix key harus aman, tidak mengandung karakter terlarang)
        cache()->save($cachePrefix . $token, $blob, $ttl);

        return $token;
    }
}

if (! function_exists('user_dencrypt')) {
    /**
     * Dari token pendek -> ambil blob -> verify HMAC -> decrypt -> return userId
     */
    function user_dencrypt(string $token): ?int
    {
        $encKey  = env('USER_ENCRYPT_KEY');
        $hmacKey = env('USER_HMAC_KEY');

        if (!$encKey || !$hmacKey) {
            throw new \RuntimeException('USER_ENCRYPT_KEY / USER_HMAC_KEY belum di-set');
        }

        // Ambil blob dari cache
        $blob = cache()->get('user_token_' . $token);
        if (!$blob) {
            return null;
        }

        $raw = base64_decode($blob, true);
        if ($raw === false) {
            return null;
        }

        $method  = 'AES-256-CBC';
        $ivLen   = openssl_cipher_iv_length($method);
        $hmacLen = 32; // Panjang output sha256 dalam bentuk raw

        if (strlen($raw) < $ivLen + $hmacLen) {
            return null;
        }

        $iv     = substr($raw, 0, $ivLen);
        $cipher = substr($raw, $ivLen, -$hmacLen);
        $hmac   = substr($raw, -$hmacLen);

        // Verifikasi HMAC
        $calc = hash_hmac('sha256', $iv . $cipher, $hmacKey, true);
        if (! hash_equals($calc, $hmac)) {
            return null; // Data dimanipulasi
        }

        $decrypted = openssl_decrypt($cipher, $method, $encKey, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            return null;
        }

        $payload = json_decode($decrypted, true);

        return isset($payload['userId']) ? (int) $payload['userId'] : null;
    }
}
if (!function_exists('log_user')) {
    /**
     * Helper untuk mencatat aktivitas user
     *
     * @param  int|null          $userId
     * @param  string            $activity
     * @param  string|array|null $details
     * @return void
     */
    function log_user(string $activity, $details = null, ?int $response = 200, ?int $userId = null): void
    {
        // static $logger = null;

        // if ($logger === null) {
        //     $logger = new UserActivityLog();
        // }

        // Jika userId tidak diberikan, ambil currentUserId dari helper kamu
        // if ($userId === null) {
        //     $userId = function_exists('currentUserId') ? currentUserId() : null;
        // }

        // $logger->log($userId, $activity, $details, $response);
    }

}
/* if (! function_exists('inGroup')) {
    function inGroup($groups): bool
    {
        $user = auth()->user();

        if ($user && $user instanceof AuthUser) {
            if (is_array($groups)) {
                return $user->inAnyGroup($groups);
            }

            return $user->inGroup($groups);
        }

        return false;
    }
} */
