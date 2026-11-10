<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RequestInterface;
use Config\Database;
use RuntimeException;
use Throwable;

class DeveloperRecoveryAuditService
{
    protected BaseConnection $db;

    private string $logDirectory;

    public function __construct(
        ?BaseConnection $db = null,
        ?string $logDirectory = null
    ) {
        $this->db = $db ?? Database::connect();

        $this->logDirectory = $logDirectory
            ?? WRITEPATH . 'logs/developer-recovery';
    }

    /**
     * Catat peristiwa ke database dan file.
     *
     * Jangan masukkan password, OTP, secret MFA, token,
     * cookie, atau isi kredensial ke metadata.
     */
    public function record(
        string $event,
        string $outcome,
        ?int $actorUserId = null,
        ?int $recoverySessionId = null,
        array $metadata = [],
        ?RequestInterface $request = null
    ): void {
        $allowedEvents = [
            'activated',
            'activation_failed',
            'deactivated',
            'expired',
            'verification_failed',
            'access_elevated',
            'access_denied',
        ];

        $allowedOutcomes = [
            'success',
            'failure',
            'denied',
        ];

        if (
            ! in_array($event, $allowedEvents, true)
            || ! in_array($outcome, $allowedOutcomes, true)
        ) {
            throw new RuntimeException(
                'Jenis peristiwa audit recovery tidak valid.'
            );
        }

        // Whitelist metadata untuk menghindari pencatatan rahasia.
        $safeMetadata = [];

        foreach (
            [
                'reason',
                'verification_method',
                'expires_at',
                'endpoint',
            ] as $key
        ) {
            if (
                array_key_exists($key, $metadata)
                && is_scalar($metadata[$key])
            ) {
                $safeMetadata[$key] = (string) $metadata[$key];
            }
        }

        $ipAddress = $request !== null
            ? $request->getIPAddress()
            : null;

        $userAgent = '';

        if ($request !== null) {
            $userAgent = (string) $request->getHeaderLine('User-Agent');
        }

        $userAgentHash = $userAgent !== ''
            ? hash('sha256', $userAgent)
            : null;

        $createdAt = date('Y-m-d H:i:s');

        $row = [
            'recovery_session_id' => $recoverySessionId,
            'actor_user_id'       => $actorUserId,
            'event'               => $event,
            'outcome'             => $outcome,
            'ip_address'          => $ipAddress,
            'user_agent_hash'     => $userAgentHash,
            'metadata_json'       => $safeMetadata !== []
                ? json_encode(
                    $safeMetadata,
                    JSON_UNESCAPED_SLASHES
                        | JSON_INVALID_UTF8_SUBSTITUTE
                )
                : null,
            'created_at' => $createdAt,
        ];

        $fileRecord = [
            'timestamp'            => $createdAt,
            'recovery_session_id'  => $recoverySessionId,
            'actor_user_id'        => $actorUserId,
            'event'                => $event,
            'outcome'              => $outcome,
            'ip_address'           => $ipAddress,
            'user_agent_hash'      => $userAgentHash,
            'metadata'             => $safeMetadata,
        ];

        // Kedua kanal wajib berhasil.
        try {
            $this->db->table('developer_recovery_audits')
                ->insert($row);

            $this->writeFileRecord($fileRecord);
        } catch (Throwable $e) {
            log_message(
                'critical',
                'Developer Recovery audit gagal ditulis: {message}',
                ['message' => $e->getMessage()]
            );

            throw new RuntimeException(
                'Audit recovery gagal. Operasi sensitif harus ditolak.',
                0,
                $e
            );
        }
    }

    private function writeFileRecord(array $record): void
    {
        if (
            ! is_dir($this->logDirectory)
            && ! mkdir($this->logDirectory, 0700, true)
            && ! is_dir($this->logDirectory)
        ) {
            throw new RuntimeException(
                'Direktori audit recovery tidak dapat dibuat.'
            );
        }

        $file = $this->logDirectory
            . DIRECTORY_SEPARATOR
            . 'recovery-'
            . date('Y-m-d')
            . '.jsonl';

        $json = json_encode(
            $record,
            JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            throw new RuntimeException(
                'Data audit recovery gagal diserialisasi.'
            );
        }

        $written = file_put_contents(
            $file,
            $json . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        if ($written === false) {
            throw new RuntimeException(
                'File audit recovery tidak dapat ditulis.'
            );
        }

        // Perlindungan tambahan pada sistem yang mendukung chmod.
        @chmod($file, 0600);
    }
}
