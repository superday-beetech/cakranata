<?php

namespace Modules\Auth\Services;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Exception;
use Modules\Auth\Repositories\Interfaces\AccessTokenRepositoryInterface;
use Modules\Auth\Repositories\Interfaces\LoginAttemptRepositoryInterface;
use Modules\Auth\Repositories\Interfaces\RefreshTokenRepositoryInterface;
use Modules\Auth\Repositories\Interfaces\UserRepositoryInterface;

class AuthService
{

    protected LoginAttemptRepositoryInterface $loginAttemptRepo;
    protected RefreshTokenRepositoryInterface $refreshTokenRepo;
    protected AccessTokenRepositoryInterface $accessTokenRepo;
    protected UserRepositoryInterface $userRepo;

    public function __construct(
        LoginAttemptRepositoryInterface $loginAttemptRepo,
        RefreshTokenRepositoryInterface $refreshTokenRepo,
        AccessTokenRepositoryInterface $accessTokenRepo,
        UserRepositoryInterface $userRepo
    ) {
        $this->loginAttemptRepo = $loginAttemptRepo;
        $this->refreshTokenRepo = $refreshTokenRepo;
        $this->accessTokenRepo = $accessTokenRepo;
        $this->userRepo = $userRepo;
    }



    public function authenticate(string $identifier, string $password, string $ip, string $userAgent): User
    {
        $identifier = trim($identifier);

        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $field    => $identifier,
            'password' => $password,
        ];

        // Memvalidasi kredensial melalui Shield tanpa login ke sesi web.
        $result = auth('session')
            ->getAuthenticator()
            ->check($credentials);

        if (! $result->isOK()) {
            $this->loginAttemptRepo->logAttempt(
                $identifier,
                null,
                false,
                $ip,
                $userAgent
            );

            throw new Exception('Kredensial login tidak valid.', 401);
        }

        $user = $result->extraInfo();

        if (! $user instanceof User) {
            $this->loginAttemptRepo->logAttempt($identifier, null, false, $ip, $userAgent);

            throw new Exception('Kredensial login tidak valid.', 401);
        }

        if ($user->isBanned()) {
            $this->loginAttemptRepo->logAttempt($identifier, (int) $user->id, false, $ip, $userAgent);

            throw new Exception('Akun tidak diizinkan untuk login.', 401);
        }

        return $user;
    }


    public function login(string $identifier, string $password, string $deviceName, string $ip, string $userAgent, bool $remember = false): array
    {
        // Autentikasi kredensial tanpa membuat sesi web.
        $user = $this->authenticate($identifier, $password, $ip, $userAgent);

        $db = db_connect();

        try {
            $db->transBegin();

            // Access token diterbitkan oleh CodeIgniter Shield.
            $tokens = $this->issueTokenPair(
                $user,
                $deviceName,
                null,
                $ip,
                $userAgent,
                $remember
            );

            $this->loginAttemptRepo->logAttempt(
                $identifier,
                $user->id,
                true,
                $ip,
                $userAgent
            );

            $db->transCommit();

            return array_merge($tokens, [
                'user' => [
                    'id'       => $user->id,
                    'username' => $user->username,
                    'email'    => $user->email,
                ],
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function logout(
        ?User $user = null,
        string $rawAccessToken = ''
    ): void {
        if ($user === null || $rawAccessToken === '') {
            throw new Exception(
                'Pengguna dan access token wajib tersedia.',
                401
            );
        }

        $db = db_connect();

        try {
            $db->transBegin();

            // Pencabutan access token menggunakan Shield.
            $this->accessTokenRepo->revokeRaw(
                $user,
                $rawAccessToken
            );

            $this->refreshTokenRepo->revokeByAccessHash(
                (int) $user->id,
                hash('sha256', $rawAccessToken)
            );

            if ($db->transStatus() === false) {
                throw new Exception(
                    'Gagal memproses logout API.',
                    500
                );
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }


    public function register(array $data, string $ip, string $userAgent): array
    {
        if ($this->userRepo->findByUsernameOrEmail($data['email'])) {
            throw new Exception('Email sudah terdaftar.', 422);
        }

        if ($this->userRepo->findByUsernameOrEmail($data['username'])) {
            throw new Exception('Username sudah digunakan.', 422);
        }

        $db = db_connect();

        try {
            $db->transBegin();

            $user       = $this->userRepo->createUser($data);
            $deviceName = $data['device_name'] ?? 'web_app';

            $tokens = $this->issueTokenPair($user, $deviceName, null, $ip, $userAgent);
            $this->loginAttemptRepo->logAttempt($data['email'], $user->id, true, $ip, $userAgent);

            $db->transCommit();

            return array_merge($tokens, [
                'user' => [
                    'id'       => $user->id,
                    'username' => $user->username,
                    'email'    => $user->email,
                ],
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }


    public function refresh(
        string $rawRefreshToken,
        string $ip,
        string $userAgent
    ): array {
        $tokenHash = hash('sha256', $rawRefreshToken);
        $tokenData = $this->refreshTokenRepo->findByHash($tokenHash);

        if (! $tokenData) {
            throw new Exception('Refresh token tidak valid.', 401);
        }

        $familyId = (string) $tokenData['family_id'];

        if (! empty($tokenData['revoked_at'])) {
            throw new Exception('Refresh token sudah dicabut.', 401);
        }

        // Token pernah dipakai: cabut seluruh keluarga.
        if (! empty($tokenData['consumed_at'])) {
            $this->refreshTokenRepo->revokeFamily($familyId);

            throw new Exception(
                'Refresh token terdeteksi penyalahgunaan.',
                401
            );
        }

        $expiresAt = Time::parse($tokenData['expires_at']);

        if (
            $expiresAt->isBefore(Time::now()) ||
            $expiresAt->equals(Time::now())
        ) {
            $this->refreshTokenRepo->revokeFamily($familyId);

            throw new Exception(
                'Refresh token telah kedaluwarsa.',
                401
            );
        }

        $db = db_connect();
        $transactionOpen = false;
        $newAccessToken = null;

        try {
            $db->transBegin();
            $transactionOpen = true;

            // Konsumsi atomik: hanya satu request yang boleh berhasil.
            $consumed = $this->refreshTokenRepo->consume(
                (int) $tokenData['id']
            );

            if (! $consumed) {
                $db->transRollback();
                $transactionOpen = false;

                // Harus dilakukan setelah rollback.
                $this->refreshTokenRepo->revokeFamily($familyId);

                throw new Exception(
                    'Refresh token sedang digunakan atau telah terpakai.',
                    401
                );
            }

            $user = $this->userRepo->findById(
                (int) $tokenData['user_id']
            );

            if (! $user) {
                throw new Exception('Pengguna tidak ditemukan.', 404);
            }

            // Terbitkan pasangan baru dengan batas kedaluwarsa yang sama.
            $tokens = $this->issueTokenPair(
                $user,
                'refreshed_device',
                $familyId,
                $ip,
                $userAgent,
                false,
                $tokenData['expires_at']
            );

            $newAccessToken = $tokens['access_token'];

            // Catat hash refresh token pengganti.
            $replacementHash = hash(
                'sha256',
                $tokens['refresh_token']
            );

            $marked = $this->refreshTokenRepo->markReplaced(
                (int) $tokenData['id'],
                $replacementHash
            );

            if (! $marked) {
                throw new Exception(
                    'Gagal mencatat rotasi refresh token.',
                    500
                );
            }

            // Cabut access token lama setelah token baru siap.
            if (! empty($tokenData['access_token_hash'])) {
                $this->accessTokenRepo->revokeByHash(
                    (int) $user->id,
                    $tokenData['access_token_hash']
                );
            }

            if ($db->transStatus() === false) {
                throw new Exception(
                    'Transaksi refresh token gagal.',
                    500
                );
            }

            $db->transCommit();
            $transactionOpen = false;

            return $tokens;
        } catch (\Throwable $e) {
            if ($transactionOpen) {
                $db->transRollback();
                $transactionOpen = false;
            }

            // Pembersihan best-effort jika access token baru sudah dibuat.
            if ($newAccessToken !== null && isset($user)) {
                try {
                    $user->revokeAccessToken($newAccessToken);
                } catch (\Throwable $cleanupError) {
                    log_message(
                        'error',
                        'Gagal mencabut access token hasil refresh yang dibatalkan.'
                    );
                }
            }

            throw $e;
        }
    }


    public function getActiveDevices(User $user, string $currentRawToken): array
    {
        $tokens = $this->accessTokenRepo->getForUser($user->id);

        return array_map(function ($token) use ($currentRawToken) {
            $isCurrent = false;

            if ($currentRawToken !== '') {
                $isCurrent = hash('sha256', $currentRawToken) === $token->secret;
            }

            return [
                'id'           => $token->id,
                'device_name'  => $token->name,
                'last_used_at' => $this->formatDate($token->last_used_at),
                'created_at'   => $this->formatDate($token->created_at),
                'is_current'   => $isCurrent,
            ];
        }, $tokens);
    }

    public function revokeDevice(User $user, int $tokenId): void
    {
        $db = db_connect();

        try {
            $db->transBegin();

            $token = $this->accessTokenRepo->findById(
                $user,
                $tokenId
            );

            if ($token === null) {
                throw new Exception(
                    'Perangkat tidak ditemukan.',
                    404
                );
            }

            // secret pada entitas access token adalah hash yang disimpan Shield.
            $accessTokenHash = (string) $token->secret;

            if (! $this->accessTokenRepo->revokeById($user, $tokenId)) {
                throw new Exception(
                    'Access token gagal dicabut.',
                    500
                );
            }

            $this->refreshTokenRepo->revokeByAccessHash(
                (int) $user->id,
                $accessTokenHash
            );

            if ($db->transStatus() === false) {
                throw new Exception(
                    'Transaksi pencabutan perangkat gagal.',
                    500
                );
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }


    public function revokeAllDevices(User $user): void
    {
        $db = db_connect();

        $db->transBegin();

        try {
            $this->accessTokenRepo->revokeAll($user);

            $this->refreshTokenRepo->revokeByUser(
                (int) $user->id
            );

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }


    /**
     * Helper privat untuk membuat pasangan Access Token & Refresh Token
     */
    private function issueTokenPair(User $user, string $deviceName, ?string $familyId, string $ip, string $userAgent, bool $remember = false, ?string $refreshExpiresAt = null): array
    {
        // $accessToken     = $user->generateAccessToken($deviceName);
        $accessExpiresAt = Time::now()->addMinutes(15);

        $accessToken = $user->generateAccessToken(
            $deviceName,
            ['*'],
            $accessExpiresAt
        );
        $accessTokenHash = hash('sha256', $accessToken->raw_token);

        $rawRefreshToken  = bin2hex(random_bytes(32));
        $refreshTokenHash = hash('sha256', $rawRefreshToken);
        $familyId         = $familyId ?? bin2hex(random_bytes(16));

        // ✅ Tentukan masa kedaluwarsa berdasarkan pilihan Remember Me
        // Jika remember true: 30 hari, jika false: 1 hari (atau sesuaikan kebutuhan)
        $daysToExpire = $remember ? 30 : 1;
        $expiresAt = $refreshExpiresAt ?? Time::now()->addDays($daysToExpire)->toDateTimeString();

        $this->refreshTokenRepo->create(
            (int) $user->id,
            $familyId,
            $refreshTokenHash,
            $accessTokenHash,
            $userAgent,
            $ip,
            $expiresAt
        );

        return [
            'access_token'  => $accessToken->raw_token,
            'refresh_token' => $rawRefreshToken,
        ];
    }

    private function formatDate($date): ?string
    {
        if (! $date) {
            return null;
        }

        if (is_object($date) && method_exists($date, 'toDateTimeString')) {
            return $date->toDateTimeString();
        }

        try {
            return Time::parse((string) $date)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
