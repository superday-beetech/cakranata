<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class AuthPolicy extends BaseConfig
{
    /** Nilai override: cukup sudah login, tanpa permission spesifik. */
    public const AUTHENTICATED = '@authenticated';

    /** Tujuan redirect (web) bila belum login. */
    public string $loginRoute = 'login';

    /** Prefix path API (tanpa slash). */
    public string $apiPrefix = 'api';

    /**
     * Authenticator Shield per jenis request.
     * API sengaja token-only: cookie session di API membuka celah CSRF.
     */
    public array $webAuthenticators = ['session', 'cookie'];
    public array $apiAuthenticators = ['tokens', 'bearer', 'session'];

    /**
     * Grup yang melewati pengecekan permission (tetap harus login & tidak banned).
     * Pakai seperlunya; sebaiknya hanya 'superadmin' (owner).
     */
    public array $bypassGroups = ['superadmin'];

    /**
     * Path yang TIDAK butuh autentikasi/RBAC. Jaga sesedikit mungkin.
     * Jangan pernah menaruh prefix modul di sini.
     * Format: "path" atau "METHOD path". '*' = satu segmen, '**' = sisa path.
     */
    public array $publicPatterns = [
        'login',
        'login/*',
        'logout',
        'auth/*',      // aksi Shield (aktivasi/2FA)
        'health',
        'webhook/**',  // divalidasi WebhookSignatureFilter
    ];

    /**
     * Override pemetaan path -> permission untuk route yang strukturnya
     * tidak mengikuti pola dinamis (module/controller/action).
     * Nilai: nama permission Shield ("scope.aksi") atau self::AUTHENTICATED.
     */
    public array $overrides = [
        'GET /' => self::AUTHENTICATED, // beranda/dashboard
        // 'GET sales-order/invoices/*/print' => 'sales-order.invoice.print',
    ];

    /** Batas panjang path dan jumlah segmen. */
    public int $maxPathLength = 2048;
    public int $maxSegments   = 12;
}
