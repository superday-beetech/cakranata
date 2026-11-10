<?php

declare(strict_types=1);

namespace App\Services;

final class DeveloperRecoveryOtpService
{
    /**
     * Verifikasi TOTP 6 digit dengan toleransi waktu ±30 detik.
     * Secret harus berupa Base32 dan disimpan di .env, bukan database.
     */
    public function verify(string $code): bool
    {
        $secret = strtoupper(trim(
            (string) env('DEVELOPER_RECOVERY_TOTP_SECRET', '')
        ));

        if ($secret === '' || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $key = $this->decodeBase32($secret);

        if ($key === '') {
            return false;
        }

        $counter = (int) floor(time() / 30);

        for ($offset = -1; $offset <= 1; $offset++) {
            $candidate = $this->generateCode($key, $counter + $offset);

            if (hash_equals($candidate, $code)) {
                return true;
            }
        }

        return false;
    }


    public function matchedCounter(string $code): ?int
    {
        $secret = strtoupper(trim(
            (string) env('DEVELOPER_RECOVERY_TOTP_SECRET', '')
        ));

        if ($secret === '' || ! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $key = $this->decodeBase32($secret);

        if ($key === '') {
            return null;
        }

        $counter = (int) floor(time() / 30);

        for ($offset = -1; $offset <= 1; $offset++) {
            $candidateCounter = $counter + $offset;

            if ($candidateCounter < 0) {
                continue;
            }

            $candidate = $this->generateCode($key, $candidateCounter);

            if (hash_equals($candidate, $code)) {
                return $candidateCounter;
            }
        }

        return null;
    }


    private function generateCode(string $key, int $counter): string
    {
        $counterBytes = pack('N2', ($counter >> 32) & 0xFFFFFFFF, $counter & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $counterBytes, $key, true);
        $offset = ord($hash[19]) & 0x0F;

        $binary =
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function decodeBase32(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = rtrim($secret, '=');
        $buffer = 0;
        $bits = 0;
        $result = '';

        foreach (str_split($secret) as $char) {
            $value = strpos($alphabet, $char);

            if ($value === false) {
                return '';
            }

            $buffer = ($buffer << 5) | $value;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $result .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $result;
    }
}
