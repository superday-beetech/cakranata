<?php

namespace Modules\Auth\Controllers\API\V1;

use App\Core\ApiController;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Config\Services as AuthServices;
use Modules\Auth\Services\AuthService;
use Modules\Auth\Services\UserService;
use Throwable;

class AuthTokenController extends ApiController
{
    private AuthService $authService;
    protected UserService $userService;

    public function __construct()
    {
        $this->authService = AuthServices::authService();
        $this->userService = AuthServices::userService();
    }

    /**
     * POST /api/v1/auth/login
     * Khusus klien API mobile.
     */

    public function login()
    {
        $payload = $this->payload();

        $identifier = trim((string) ($payload['identity'] ?? ''));
        // Password jangan di-trim.
        $password = (string) ($payload['password'] ?? '');

        $deviceName = trim((string) (
            $payload['device_name'] ?? 'mobile_app'
        ));

        $remember = filter_var(
            $payload['remember'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $mode = strtolower(trim(
            $this->request->getHeaderLine('X-ACN-Auth-Mode')
        ));

        // Default kompatibilitas untuk klien API lama.
        if ($mode === '') {
            $mode = 'bearer';
        }

        if (! in_array($mode, ['cookie', 'bearer'], true)) {
            return $this->errorResponse(
                'Mode autentikasi login tidak valid.',
                400
            );
        }

        if ($identifier === '' || $password === '') {
            return $this->errorResponse(
                'Username/Email dan Password wajib diisi.',
                422
            );
        }

        try {
            $data = $this->authService->login(
                $identifier,
                $password,
                $deviceName !== ''
                    ? $deviceName
                    : ($mode === 'cookie' ? 'web_erp' : 'mobile_app'),
                $this->request->getIPAddress(),
                (string) $this->request->getUserAgent(),
                $remember
            );

            if ($mode === 'cookie') {
                $accessToken = (string) $data['access_token'];
                $refreshToken = (string) $data['refresh_token'];

                // Cookie hanya dikirim oleh server; JavaScript tidak dapat membacanya.
                $secure = $this->request->isSecure();

                $accessCookie = [
                    'name'     => 'acn_access_token',
                    'value'    => $accessToken,
                    'expire'   => $remember ? 2592000 : 3600,
                    'path'     => '/',
                    'secure'   => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ];

                $refreshCookie = [
                    'name'     => 'acn_refresh_token',
                    'value'    => $refreshToken,
                    'expire'   => $remember ? 2592000 : 604800,
                    'path'     => '/',
                    'secure'   => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ];

                $this->response->setCookie($accessCookie);
                $this->response->setCookie($refreshCookie);

                return $this->successResponse([
                    'user' => $data['user'],
                    'auth_mode' => 'cookie',
                ], 'Login berhasil.');
            }

            // Mode Bearer: tetap mengembalikan token untuk aplikasi mobile.
            return $this->successResponse([
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'user'          => $data['user'],
            ], 'Login berhasil.');
        } catch (\Throwable $e) {
            log_message('warning', 'API login gagal: {message}', [
                'message' => $e->getMessage(),
            ]);

            $status = (int) $e->getCode();
            $status = ($status >= 400 && $status < 600)
                ? $status
                : 401;

            return $this->errorResponse(
                $status === 401
                    ? 'Username/Email atau Password tidak valid.'
                    : 'Login gagal diproses.',
                $status
            );
        }
    }

    /**
     * POST /api/v1/auth/register
     * Registrasi melalui API.
     */
    public function register()
    {
        $payload = $this->payload();

        $data = [
            'username' => trim((string) (
                $payload['username'] ?? ''
            )),
            'email' => trim((string) (
                $payload['email'] ?? ''
            )),
            'password' => (string) (
                $payload['password'] ?? ''
            ),
            'device_name' => trim((string) (
                $payload['device_name'] ?? 'mobile_app'
            )),
        ];

        $rules = [
            'username' => 'required|min_length[3]|max_length[30]|is_unique[users.username]',
            'email' => 'required|valid_email|is_unique[auth_identities.secret]',
            'password' => 'required|min_length[8]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->errorResponse(
                (string) (
                    reset($this->validator->getErrors())
                    ?: 'Validasi registrasi gagal.'
                ),
                422
            );
        }

        try {
            $result = $this->authService->register(
                $data,
                $this->request->getIPAddress(),
                (string) $this->request->getUserAgent()
            );

            return $this->successResponse([
                'access_token'  => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'user'          => $result['user'],
            ], 'Registrasi berhasil.', 201);
        } catch (Throwable $e) {
            log_message('error', 'API registrasi gagal: {message}', [
                'message' => $e->getMessage(),
            ]);

            $status = (int) $e->getCode();
            $status = ($status >= 400 && $status < 600)
                ? $status
                : 500;

            return $this->errorResponse(
                'Registrasi gagal diproses.',
                $status
            );
        }
    }

    /**
     * POST /api/v1/auth/refresh
     * Rotasi access token dan refresh token mobile.
     */
    public function generateToken()
    {
        $mode = strtolower(trim(
            $this->request->getHeaderLine('X-ACN-Auth-Mode')
        ));

        $cookieMode = $mode === 'cookie';

        $refreshToken = trim((string) (
            $this->payload()['refresh_token'] ?? ''
        ));

        if ($cookieMode && $refreshToken === '') {
            $refreshToken = (string) $this->request->getCookie(
                'acn_refresh_token'
            );
        }

        if ($refreshToken === '') {
            return $this->errorResponse(
                'Refresh token tidak tersedia.',
                401
            );
        }

        try {
            $data = $this->authService->refresh(
                $refreshToken,
                (string) $this->request->getIPAddress(),
                (string) $this->request->getUserAgent()
            );

            if ($cookieMode) {
                $secure = $this->request->isSecure();

                $this->response->setCookie([
                    'name'     => 'acn_access_token',
                    'value'    => $data['access_token'],
                    'expire'   => 3600,
                    'path'     => '/',
                    'secure'   => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);

                $this->response->setCookie([
                    'name'     => 'acn_refresh_token',
                    'value'    => $data['refresh_token'],
                    'expire'   => 604800,
                    'path'     => '/',
                    'secure'   => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);

                return $this->successResponse(
                    ['auth_mode' => 'cookie'],
                    'Token berhasil diperbarui.'
                );
            }

            // Pertahankan kontrak token untuk aplikasi mobile.
            return $this->successResponse([
                'access_token'  => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
            ], 'Token berhasil diperbarui.');
        } catch (\Throwable $e) {
            log_message('warning', 'Token refresh gagal: {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Refresh token tidak valid atau kedaluwarsa.',
                401
            );
        }
    }

    /**
     * GET /api/v1/me
     */
    public function meA()
    {
        $user = $this->tokenUser();

        if (! $user) {
            return $this->errorResponse(
                'Token tidak valid atau kedaluwarsa.',
                401
            );
        }

        $profile = $this->userService->getProfile($user);

        $profile['groups'] = $user->getGroups();
        $profile['identities'] = $user->identities;

        return $this->successResponse(
            $profile,
            'Profil pengguna berhasil diambil.'
        );
    }

    /**
     * POST /api/v1/me/change-password
     */
    public function changePassword()
    {
        $user = $this->tokenUser();

        if (! $user) {
            return $this->errorResponse(
                'Pengguna tidak terautentikasi.',
                401
            );
        }

        $payload = $this->payload();

        $rules = [
            'old_password'         => 'required',
            'new_password'         => 'required|min_length[8]',
            'new_password_confirm' => 'required|matches[new_password]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->errorResponse(
                (string) (
                    reset($this->validator->getErrors())
                    ?: 'Validasi password gagal.'
                ),
                422
            );
        }

        try {
            $this->userService->changePassword(
                $user,
                (string) $payload['old_password'],
                (string) $payload['new_password']
            );

            return $this->successResponse(
                null,
                'Password berhasil diperbarui.'
            );
        } catch (Throwable $e) {
            log_message('warning', 'Ganti password gagal: {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Password gagal diperbarui.',
                400
            );
        }
    }

    /**
     * PUT /api/v1/me
     */
    public function updateProfile()
    {
        $user = $this->tokenUser();

        if (! $user) {
            return $this->errorResponse(
                'Pengguna tidak terautentikasi.',
                401
            );
        }

        $rules = [
            'username' => 'permit_empty|alpha_numeric_space|min_length[3]|max_length[30]',
            'email'    => 'permit_empty|valid_email',
            'avatar'   => 'permit_empty|is_image[avatar]|mime_in[avatar,image/jpg,image/jpeg,image/png,image/webp]|max_size[avatar,2048]',
        ];

        if (! $this->validate($rules)) {
            return $this->errorResponse(
                (string) (
                    reset($this->validator->getErrors())
                    ?: 'Validasi profil gagal.'
                ),
                422
            );
        }

        $inputData = [];

        foreach (['username', 'email'] as $field) {
            $value = $this->request->getPost($field);

            if ($value !== null && trim($value) !== '') {
                $inputData[$field] = trim($value);
            }
        }

        $avatarFile = $this->request->getFile('avatar');

        try {
            $profile = $this->userService->updateProfile(
                $user,
                $inputData,
                $avatarFile
            );

            return $this->successResponse(
                $profile,
                'Profil berhasil diperbarui.'
            );
        } catch (Throwable $e) {
            log_message('warning', 'Update profil gagal: {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Profil gagal diperbarui.',
                400
            );
        }
    }

    /**
     * DELETE /api/v1/me/avatar
     */
    public function deleteAvatar()
    {
        $user = $this->tokenUser();

        if (! $user) {
            return $this->errorResponse(
                'Pengguna tidak terautentikasi.',
                401
            );
        }

        try {
            $profile = $this->userService->deleteAvatar($user);

            return $this->successResponse(
                $profile,
                'Foto profil berhasil dihapus.'
            );
        } catch (Throwable $e) {
            log_message('error', 'Hapus avatar gagal: {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Foto profil gagal dihapus.',
                500
            );
        }
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout()
    {
        $user = $this->tokenUser();
        $rawToken = $this->getCurrentRawToken();

        if (! $user || $rawToken === '') {
            return $this->errorResponse(
                'Token tidak valid.',
                401
            );
        }

        try {
            $this->authService->logout($user, $rawToken);

            return $this->successResponse(
                null,
                'Berhasil logout dari perangkat ini.'
            );
        } catch (Throwable $e) {
            log_message('error', 'API logout gagal: {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Logout gagal diproses.',
                500
            );
        }
    }

    /**
     * POST /api/v1/auth/logout-all
     */
    public function logoutAllDevices()
    {
        $user = $this->tokenUser();

        if (! $user) {
            return $this->errorResponse(
                'Token tidak valid.',
                401
            );
        }

        try {
            $this->authService->revokeAllDevices($user);

            return $this->successResponse(
                null,
                'Berhasil keluar dari semua perangkat.'
            );
        } catch (Throwable $e) {
            log_message('error', 'Logout semua perangkat gagal: {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Logout semua perangkat gagal.',
                500
            );
        }
    }

    /**
     * GET /api/v1/auth/devices
     */
    public function devices()
    {
        $user = $this->tokenUser();

        if (! $user) {
            return $this->errorResponse(
                'Token tidak valid.',
                401
            );
        }

        $devices = $this->authService->getActiveDevices(
            $user,
            $this->getCurrentRawToken()
        );

        return $this->successResponse(
            $devices,
            'Daftar perangkat aktif berhasil diambil.'
        );
    }

    /**
     * DELETE /api/v1/auth/devices/{id}
     */
    public function revokeDeviceById($id = null)
    {
        $user = $this->tokenUser();

        if (! $user) {
            return $this->errorResponse(
                'Token tidak valid.',
                401
            );
        }

        if (! is_numeric($id) || (int) $id < 1) {
            return $this->errorResponse(
                'ID perangkat tidak valid.',
                422
            );
        }

        try {
            $this->authService->revokeDevice($user, (int) $id);

            return $this->successResponse(
                null,
                'Akses perangkat berhasil dicabut.'
            );
        } catch (Throwable $e) {
            log_message('warning', 'Pencabutan perangkat gagal: {message}', [
                'message' => $e->getMessage(),
            ]);

            $status = (int) $e->getCode();

            if (! in_array($status, [404, 500], true)) {
                $status = 400;
            }

            return $this->errorResponse(
                $status === 404
                    ? 'Perangkat tidak ditemukan.'
                    : 'Akses perangkat gagal dicabut.',
                $status
            );
        }
    }

    /**
     * Hanya untuk endpoint API mobile.
     * Route API nantinya wajib memakai filter token.
     */

    private function tokenUser(): ?User
    {
        $mode = strtolower(trim(
            $this->request->getHeaderLine('X-ACN-Auth-Mode')
        ));

        $rawToken = $mode === 'cookie'
            ? (string) $this->request->getCookie('acn_access_token')
            : '';

        if ($rawToken === '') {
            $authorization = $this->request->getHeaderLine('Authorization');

            if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
                $rawToken = trim($matches[1]);
            }
        }

        if ($rawToken === '') {
            return null;
        }

        $authenticator = auth('tokens')->getAuthenticator();
        $result = $authenticator->check(['token' => $rawToken]);

        if (! $result->isOK()) {
            return null;
        }

        $user = $result->extraInfo();

        return $user instanceof User ? $user : null;
    }

    private function tokenUsersss(): ?User
    {
        $user = auth('tokens')->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Ambil payload JSON atau form.
     */
    private function payload(): array
    {
        $json = $this->request->getJSON(true);

        if (is_array($json)) {
            return array_merge(
                $this->request->getPost() ?? [],
                $json
            );
        }

        return $this->request->getPost() ?? [];
    }

    /**
     * Ambil token dari Authorization: Bearer.
     */
    private function getCurrentRawToken(): string
    {
        $header = trim(
            $this->request->getHeaderLine('Authorization')
        );

        if (
            $header === ''
            || ! preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)
        ) {
            return '';
        }

        return $matches[1];
    }
}
