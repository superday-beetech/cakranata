<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\HTTP\IncomingRequest;
use Config\Database;
use RuntimeException;
use Throwable;

final class DeveloperRecoveryService
{
    private const DURATION_MINUTES = 30;

    public function __construct(
        private ?DeveloperRecoveryOtpService $otpService = null,
        private ?DeveloperRecoveryAuditService $auditService = null,
    ) {
        $this->otpService ??= new DeveloperRecoveryTotpService();
        $this->auditService ??= new DeveloperRecoveryAuditService();
    }

    /**
     * Aktivasi hanya untuk akun developer yang dikonfigurasi.
     * Pemanggil tetap wajib melewati autentikasi Shield.
     */
    public function activate(
        string $otp,
        IncomingRequest $request
    ): array {
        $user = auth()->user();

        if ($user === null) {
            throw new RuntimeException('Login Shield diperlukan.');
        }

        $userId = (int) $user->id;
        $developerId = (int) env('DEVELOPER_RECOVERY_USER_ID', 0);

        if ($developerId < 1 || $userId !== $developerId) {
            $this->auditService->record(
                'activation_failed',
                'denied',
                $userId,
                null,
                ['reason' => 'developer_identity_mismatch'],
                $request
            );

            throw new RuntimeException('Akun tidak diizinkan mengaktifkan Recovery Mode.');
        }

        if (! service('accessControl')->isActiveUser($userId)) {
            throw new RuntimeException('Akun developer tidak aktif.');
        }

        $sessionId = (string) service('session')->getId();

        if ($sessionId === '') {
            throw new RuntimeException('Sesi browser tidak valid.');
        }

        /*  if (! $this->otpService->verify($otp)) {
            $this->auditService->record(
                'verification_failed',
                'failure',
                $userId,
                null,
                ['reason' => 'invalid_totp'],
                $request
            );

            throw new RuntimeException('Kode OTP tidak valid.');
        } */

        $totpCounter = $this->otpService->matchedCounter($otp);

        if ($totpCounter === null) {
            $this->auditService->record(
                'verification_failed',
                'failure',
                $userId,
                null,
                ['reason' => 'invalid_or_expired_totp'],
                $request
            );

            throw new RuntimeException('Kode OTP tidak valid atau kedaluwarsa.');
        }

        $db = Database::connect();
        $now = date('Y-m-d H:i:s');
        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + (self::DURATION_MINUTES * 60)
        );
        $fingerprint = hash('sha256', $sessionId);

        $db->transBegin();

        try {
            // Cabut sesi recovery lama milik developer ini.
            $db->table('developer_recovery_sessions')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->update([
                    'status'     => 'revoked',
                    'revoked_at' => $now,
                    'updated_at' => $now,
                ]);

            $db->table('developer_recovery_sessions')->insert([
                'user_id'            => $userId,
                'status'             => 'active',
                'verification_method' => 'totp',
                'session_fingerprint' => $fingerprint,
                'totp_counter' => $totpCounter,
                'activated_at'       => $now,
                'expires_at'         => $expiresAt,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            $recoverySessionId = (int) $db->insertID();

            if ($db->transStatus() === false || $recoverySessionId < 1) {
                throw new RuntimeException('Gagal menyimpan sesi recovery.');
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            $this->auditService->record(
                'activation_failed',
                'failure',
                $userId,
                null,
                ['reason' => 'session_persistence_failed'],
                $request
            );

            throw $e;
        }

        try {
            $this->auditService->record(
                'activated',
                'success',
                $userId,
                $recoverySessionId,
                [
                    'verification_method' => 'totp',
                    'expires_at' => $expiresAt,
                ],
                $request
            );
        } catch (Throwable $e) {
            // Jika audit tidak lengkap, cabut recovery yang baru dibuat.
            $db->table('developer_recovery_sessions')
                ->where('id', $recoverySessionId)
                ->update([
                    'status'     => 'revoked',
                    'revoked_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            throw new RuntimeException(
                'Audit gagal. Recovery Mode dibatalkan.',
                0,
                $e
            );
        }

        return [
            'active'     => true,
            'expires_at' => $expiresAt,
            'duration_minutes' => self::DURATION_MINUTES,
        ];
    }

    /**
     * Memeriksa recovery hanya untuk user dan sesi browser saat ini.
     * Return false berarti pemeriksaan RBAC normal harus tetap berjalan.
     */

    /**
     * Mengembalikan data sesi recovery aktif milik user dan sesi browser saat ini.
     * null berarti recovery tidak aktif.
     */
    public function getActiveSessionForCurrentSession(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }

        $user = auth()->user();

        if (
            $user === null
            || (int) $user->id !== $userId
            || (int) env('DEVELOPER_RECOVERY_USER_ID', 0) !== $userId
        ) {
            return null;
        }

        if (! service('accessControl')->isActiveUser($userId)) {
            return null;
        }

        $sessionId = (string) session('session')->getId();

        if ($sessionId === '') {
            return null;
        }

        $fingerprint = hash('sha256', $sessionId);
        $now = date('Y-m-d H:i:s');
        $db = Database::connect();

        $recovery = $db->table('developer_recovery_sessions')
            ->where('user_id', $userId)
            ->where('session_fingerprint', $fingerprint)
            ->where('status', 'active')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        if (! is_array($recovery)) {
            return null;
        }

        $recoveryId = (int) ($recovery['id'] ?? 0);
        $expiresAt = strtotime((string) ($recovery['expires_at'] ?? ''));

        if ($recoveryId < 1 || $expiresAt === false || $expiresAt <= time()) {
            $db->table('developer_recovery_sessions')
                ->where('id', $recoveryId)
                ->where('status', 'active')
                ->update([
                    'status'     => 'expired',
                    'updated_at' => $now,
                ]);

            $this->auditService->record(
                'expired',
                'success',
                $userId,
                $recoveryId > 0 ? $recoveryId : null,
                ['reason' => 'time_limit_reached']
            );

            return null;
        }

        return $recovery;
    }

    /**
     * Kompatibilitas dengan pemanggil yang hanya membutuhkan boolean.
     */
    public function isActiveForCurrentSession(int $userId): bool
    {
        return $this->getActiveSessionForCurrentSession($userId) !== null;
    }


    public function deactivate(
        IncomingRequest $request
    ): void {
        $user = auth()->user();

        if ($user === null) {
            throw new RuntimeException('Login Shield diperlukan.');
        }

        $userId = (int) $user->id;
        $sessionId = (string) session()->get('__ci_last_regenerate');

        if ($sessionId === '') {
            $sessionId = (string) session_id();
        }

        $fingerprint = hash('sha256', $sessionId);
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');

        $recovery = $db->table('developer_recovery_sessions')
            ->where('user_id', $userId)
            ->where('session_fingerprint', $fingerprint)
            ->where('status', 'active')
            ->get()
            ->getRowArray();

        if (! $recovery) {
            return;
        }

        $db->table('developer_recovery_sessions')
            ->where('id', (int) $recovery['id'])
            ->update([
                'status'     => 'revoked',
                'revoked_at' => $now,
                'updated_at' => $now,
            ]);

        $this->auditService->record(
            'deactivated',
            'success',
            $userId,
            (int) $recovery['id'],
            ['reason' => 'manual_deactivation'],
            $request
        );
    }
}
