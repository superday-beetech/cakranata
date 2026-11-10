<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use Throwable;

final class DeveloperRecoverySessionService
{
    private BaseConnection $db;

    public function __construct(
        private ?DeveloperRecoveryTotpService $totp = null,
        ?BaseConnection $db = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->totp ??= new DeveloperRecoveryTotpService($this->db);
    }

    /**
     * Aktifkan recovery untuk user yang sudah login melalui Shield.
     * Jangan menerima user_id dari input browser.
     */
    public function activate(string $code): int
    {
        $user = auth()->user();

        if ($user === null) {
            throw new RuntimeException('Login diperlukan.', 401);
        }

        $userId = (int) $user->id;

        if (
            $userId <= 0
            || ! $this->db->table('users')
                ->where('id', $userId)
                ->where('deleted_at', null)
                ->countAllResults()
        ) {
            throw new RuntimeException('Pengguna tidak aktif.', 403);
        }

        if ($this->getCurrentSessionId() === '') {
            throw new RuntimeException('Sesi browser tidak valid.', 401);
        }

        if (! $this->totp->verifyAndConsume($userId, $code)) {
            $this->audit(
                null,
                $userId,
                'recovery.activate',
                'denied',
                ['reason' => 'totp_invalid']
            );

            throw new RuntimeException('Kode OTP tidak valid atau sudah digunakan.', 403);
        }

        // Rotasi ID sesi sebelum mengikat fingerprint.
        session()->regenerate(true);

        $sessionId = $this->getCurrentSessionId();

        if ($sessionId === '') {
            $this->audit(null, $userId, 'recovery.activate', 'error');
            throw new RuntimeException('Gagal membuat sesi browser.', 500);
        }

        $now = new \DateTimeImmutable('now');
        $expires = $now->modify('+30 minutes');

        $this->db->transBegin();

        try {
            // Nonaktifkan sesi recovery sebelumnya milik pengguna ini.
            $this->db->table('developer_recovery_sessions')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->update([
                    'status'     => 'revoked',
                    'revoked_at' => $now->format('Y-m-d H:i:s'),
                    'updated_at' => $now->format('Y-m-d H:i:s'),
                ]);

            $totpRow = $this->db->table('developer_recovery_totp')
                ->select('last_counter')
                ->where('user_id', $userId)
                ->where('confirmed_at IS NOT NULL', null, false)
                ->get()
                ->getRowArray();

            if ($totpRow === null || $totpRow['last_counter'] === null) {
                throw new RuntimeException('Konfigurasi TOTP belum aktif.');
            }

            $this->db->table('developer_recovery_sessions')->insert([
                'user_id'             => $userId,
                'status'              => 'active',
                'verification_method' => 'totp',
                'session_fingerprint' => hash('sha256', $sessionId),
                'totp_counter'        => (int) $totpRow['last_counter'],
                'activated_at'        => $now->format('Y-m-d H:i:s'),
                'expires_at'          => $expires->format('Y-m-d H:i:s'),
                'revoked_at'          => null,
                'created_at'          => $now->format('Y-m-d H:i:s'),
                'updated_at'          => null,
            ]);

            $recoveryId = (int) $this->db->insertID();

            $this->insertAudit(
                $recoveryId,
                $userId,
                'recovery.activate',
                'success',
                ['expires_at' => $expires->format('Y-m-d H:i:s')]
            );

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Gagal menyimpan sesi recovery.');
            }

            $this->db->transCommit();

            log_message(
                'critical',
                'Developer Recovery activated. User ID: {userId}; Recovery ID: {recoveryId}.',
                ['userId' => $userId, 'recoveryId' => $recoveryId]
            );

            return $recoveryId;
        } catch (Throwable $e) {
            $this->db->transRollback();

            $this->audit(
                null,
                $userId,
                'recovery.activate',
                'error',
                ['reason' => 'session_creation_failed']
            );

            log_message('error', 'Developer Recovery session creation failed.');

            throw new RuntimeException(
                'Sesi recovery gagal dibuat. Periksa audit aplikasi.',
                500,
                $e
            );
        }
    }

    public function revokeCurrent(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            throw new RuntimeException('Login diperlukan.', 401);
        }

        $userId = (int) $user->id;
        $fingerprint = hash('sha256', $this->getCurrentSessionId());
        $now = date('Y-m-d H:i:s');

        $this->db->transBegin();

        try {
            $this->db->table('developer_recovery_sessions')
                ->where('user_id', $userId)
                ->where('session_fingerprint', $fingerprint)
                ->where('status', 'active')
                ->update([
                    'status'     => 'revoked',
                    'revoked_at' => $now,
                    'updated_at' => $now,
                ]);

            $changed = $this->db->affectedRows() > 0;

            $this->insertAudit(
                null,
                $userId,
                'recovery.revoke',
                $changed ? 'success' : 'not_found'
            );

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Gagal menonaktifkan recovery.');
            }

            $this->db->transCommit();

            return $changed;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function audit(
        ?int $recoveryId,
        ?int $userId,
        string $event,
        string $outcome,
        array $metadata = []
    ): void {
        try {
            $this->insertAudit(
                $recoveryId,
                $userId,
                $event,
                $outcome,
                $metadata
            );
        } catch (Throwable $e) {
            log_message('critical', 'Developer Recovery audit database write failed.');
        }
    }

    private function insertAudit(
        ?int $recoveryId,
        ?int $userId,
        string $event,
        string $outcome,
        array $metadata = []
    ): void {
        $request = service('request');

        $this->db->table('developer_recovery_audits')->insert([
            'recovery_session_id' => $recoveryId,
            'actor_user_id'       => $userId,
            'event'               => $event,
            'outcome'             => $outcome,
            'ip_address'          => $request->getIPAddress(),
            'user_agent_hash'     => hash(
                'sha256',
                (string) $request->getUserAgent()
            ),
            'metadata_json'       => $metadata === []
                ? null
                : json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at'          => date('Y-m-d H:i:s'),
        ]);
    }


    private function getCurrentSessionId(): string
    {
        $sessionId = session_id();

        if ($sessionId === '') {
            throw new \RuntimeException(
                'ID sesi browser tidak tersedia.',
                401
            );
        }

        return $sessionId;
    }
}
