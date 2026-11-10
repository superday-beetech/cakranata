<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use Throwable;

final class DeveloperRecoveryTotpService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Membuat secret TOTP sementara untuk aktivasi.
     * Secret hanya dikembalikan sekali kepada pemanggil.
     */
    public function beginSetup(int $userId): array
    {
        $this->assertActiveUser($userId);

        $secret = $this->base32Encode(random_bytes(20));
        $encrypted = $this->encryptSecret($secret);
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->table('developer_recovery_totp')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if ($existing === null) {
            $this->db->table('developer_recovery_totp')->insert([
                'user_id'                  => $userId,
                'secret_encrypted'         => null,
                'pending_secret_encrypted' => $encrypted,
                'confirmed_at'             => null,
                'last_counter'             => null,
                'created_at'               => $now,
                'updated_at'               => $now,
            ]);
        } else {
            $this->db->table('developer_recovery_totp')
                ->where('user_id', $userId)
                ->update([
                    'pending_secret_encrypted' => $encrypted,
                    'updated_at'               => $now,
                ]);
        }

        $label = rawurlencode('ACN Developer Recovery:' . $userId);
        $uri = 'otpauth://totp/' . $label
            . '?secret=' . $secret
            . '&issuer=ACN'
            . '&algorithm=SHA1&digits=6&period=30';

        return [
            'secret'      => $secret,
            'otpauth_uri' => $uri,
        ];
    }

    /**
     * Konfirmasi aktivasi dengan kode dari aplikasi authenticator.
     */
    public function confirmSetup(int $userId, string $code): bool
    {
        $this->assertActiveUser($userId);

        $row = $this->db->table('developer_recovery_totp')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray();

        if (
            $row === null
            || empty($row['pending_secret_encrypted'])
        ) {
            return false;
        }

        $secret = $this->decryptSecret(
            (string) $row['pending_secret_encrypted']
        );

        $counter = $this->matchingCounter($secret, $code);

        if ($counter === null) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        return $this->db->table('developer_recovery_totp')
            ->where('user_id', $userId)
            ->where(
                'pending_secret_encrypted',
                $row['pending_secret_encrypted']
            )
            ->update([
                'secret_encrypted'         => $row['pending_secret_encrypted'],
                'pending_secret_encrypted' => null,
                'confirmed_at'             => $now,
                'last_counter'             => $counter,
                'updated_at'               => $now,
            ]);
    }

    /**
     * Memverifikasi OTP dan mengonsumsi counter secara atomik.
     * OTP yang sama tidak dapat digunakan kembali.
     */
    public function verifyAndConsume(int $userId, string $code): bool
    {
        $this->assertActiveUser($userId);

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $this->db->transBegin();

        try {
            $row = $this->db->query(
                'SELECT * FROM developer_recovery_totp
                 WHERE user_id = ? AND confirmed_at IS NOT NULL
                 FOR UPDATE',
                [$userId]
            )->getRowArray();

            if ($row === null || empty($row['secret_encrypted'])) {
                $this->db->transRollback();
                return false;
            }

            $secret = $this->decryptSecret(
                (string) $row['secret_encrypted']
            );

            $counter = $this->matchingCounter($secret, $code);

            if (
                $counter === null
                || (
                    $row['last_counter'] !== null
                    && $counter <= (int) $row['last_counter']
                )
            ) {
                $this->db->transRollback();
                return false;
            }

            $this->db->table('developer_recovery_totp')
                ->where('user_id', $userId)
                ->update([
                    'last_counter' => $counter,
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                return false;
            }

            $this->db->transCommit();

            return true;
        } catch (Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Developer Recovery TOTP verification failed.');
            return false;
        }
    }

    private function assertActiveUser(int $userId): void
    {
        if (
            $userId <= 0
            || $this->db->table('users')
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->countAllResults() !== 1
        ) {
            throw new RuntimeException('Pengguna tidak aktif.');
        }
    }

    private function encryptSecret(string $secret): string
    {
        return base64_encode(service('encrypter')->encrypt($secret));
    }

    private function decryptSecret(string $encrypted): string
    {
        $raw = base64_decode($encrypted, true);

        if ($raw === false) {
            throw new RuntimeException('Secret TOTP tidak valid.');
        }

        return service('encrypter')->decrypt($raw);
    }

    private function matchingCounter(
        string $secret,
        string $code
    ): ?int {
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $current = intdiv(time(), 30);

        // Toleransi satu interval sebelum dan sesudah waktu server.
        for ($counter = $current - 1; $counter <= $current + 1; $counter++) {
            if ($counter < 0) {
                continue;
            }

            $expected = $this->totp($secret, $counter);

            if (hash_equals($expected, $code)) {
                return $counter;
            }
        }

        return null;
    }

    private function totp(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $message = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $message, $key, true);

        $offset = ord($hash[19]) & 0x0F;

        $binary =
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $result = '';

        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }

            $result .= $alphabet[bindec($chunk)];
        }

        return $result;
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(rtrim($secret, '='));
        $bits = '';

        foreach (str_split($secret) as $char) {
            $position = strpos($alphabet, $char);

            if ($position === false) {
                throw new RuntimeException('Secret TOTP tidak valid.');
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $result = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $result .= chr(bindec($chunk));
            }
        }

        return $result;
    }
}
