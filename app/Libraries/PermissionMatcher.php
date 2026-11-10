<?php

declare(strict_types=1);

/**
 * Simpan di: app/Libraries/PermissionMatcher.php
 * (sesuaikan namespace/use di AccessControl bila letaknya berbeda)
 *
 * ATURAN
 * ------
 * Permission yang DIMINTA (required):
 *   - format  segmen.segmen[.segmen...]  , segmen = [a-z0-9_-]+
 *   - TIDAK boleh mengandung wildcard. Required berwildcard => selalu ditolak.
 *
 * Permission yang DIMILIKI (granted):
 *   - exact          : "sales.invoice.view"
 *   - wildcard akhir : "sales.*"  -> cocok untuk sales.x dan sales.x.y (semua turunan),
 *                      TIDAK cocok untuk "sales" sendiri maupun "salesx.view".
 *   - "*", "**", "a.*.b", "*.view", dan format lain yang tidak valid DIABAIKAN.
 *     (akses penuh hanya lewat grup superadmin, bukan wildcard global)
 *
 * Semua perbandingan dilakukan setelah trim + lowercase.
 */

namespace App\Libraries;

final class PermissionMatcher
{
    private const REQUIRED_PATTERN = '/^[a-z0-9_-]+(\.[a-z0-9_-]+)*$/';
    private const GRANT_PATTERN    = '/^[a-z0-9_-]+(\.[a-z0-9_-]+)*(\.\*)?$/';

    public static function normalize(string $permission): string
    {
        return strtolower(trim($permission));
    }

    public static function isValidRequired(string $permission): bool
    {
        return preg_match(self::REQUIRED_PATTERN, self::normalize($permission)) === 1;
    }

    public static function isValidGrant(string $permission): bool
    {
        return preg_match(self::GRANT_PATTERN, self::normalize($permission)) === 1;
    }

    /**
     * @param list<string> $granted daftar permission efektif milik user
     */
    public static function matches(string $required, array $granted): bool
    {
        $required = self::normalize($required);

        if (preg_match(self::REQUIRED_PATTERN, $required) !== 1) {
            return false; // kosong, berwildcard, atau berformat aneh
        }

        $set = [];

        foreach ($granted as $grant) {
            if (! is_string($grant)) {
                continue;
            }

            $grant = self::normalize($grant);

            if (preg_match(self::GRANT_PATTERN, $grant) === 1) {
                $set[$grant] = true;
            }
        }

        if ($set === []) {
            return false;
        }

        if (isset($set[$required])) {
            return true;
        }

        // Wildcard prefix: a.b.c -> cek "a.*" lalu "a.b.*"
        $segments = explode('.', $required);
        array_pop($segments); // segmen terakhir tidak punya wildcard sendiri

        $prefix = '';

        foreach ($segments as $segment) {
            $prefix .= ($prefix === '' ? '' : '.') . $segment;

            if (isset($set[$prefix . '.*'])) {
                return true;
            }
        }

        return false;
    }
}
