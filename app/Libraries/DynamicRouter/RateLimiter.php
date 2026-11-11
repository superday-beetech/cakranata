<?php

declare(strict_types=1);

namespace App\Libraries\DynamicRouter;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\HTTP\RequestInterface;
use Throwable;

/**
 * Rate limit fixed-window per (modul, endpoint, identitas).
 *
 * - Counter dinaikkan lewat increment() cache backend (atomik di Redis/Memcached;
 *   best-effort di file cache).
 * - Window dihitung dari bucket waktu, sehingga tidak bergeser pada setiap hit.
 * - Kunci cache di-hash agar tidak bisa bertabrakan akibat karakter yang diganti.
 * - Saat cache backend gagal:
 *     - Endpoint dengan fail_closed=true (default untuk auth) -> DITOLAK.
 *     - Endpoint lain -> DIIZINKAN (fail-open) agar gangguan cache tidak melumpuhkan
 *       seluruh aplikasi.
 */
class RateLimiter
{
    /**
     * Endpoint yang WAJIB fail-closed saat cache gagal, meskipun
     * RouterConfig tidak men-set fail_closed secara eksplisit.
     * Ini adalah safety net untuk endpoint yang paling sensitif.
     *
     * @var list<string>
     */
    private const CRITICAL_ENDPOINTS = [
        'Auth::loginAction',
        'Auth::login',
        'Auth::registerAction',
        'Auth::register',
        'Auth::forgotPasswordAction',
        'Auth::resetPasswordAction',
        'Auth::magicLinkAction',
    ];

    public function __construct(
        private RequestInterface $request,
        private string $moduleFolder,
        private array $rateLimits = [],
        private ?AuthenticationResolver $authenticationResolver = null,
        private ?CacheInterface $cache = null,
    ) {}

    public function checkRateLimit(string $endpoint): bool
    {
        $policy = $this->rateLimits[$endpoint] ?? null;

        if ($policy === null) {
            return true;
        }

        $limit   = (int) ($policy['limit'] ?? 10);
        $minutes = (int) ($policy['minutes'] ?? 1);

        if ($limit < 1 || $minutes < 1) {
            return false;
        }

        $user = $this->authenticationResolver
            ? $this->authenticationResolver->getAuthenticatedUser()
            : null;

        $identity = $user
            ? 'user:' . $user->id
            : 'ip:' . $this->request->getIPAddress();

        $window = $minutes * 60;
        $bucket = intdiv(time(), $window);
        $key    = 'rl_' . hash('sha256', "{$this->moduleFolder}|{$endpoint}|{$identity}|{$bucket}");

        try {
            return $this->hit($key, $window) <= $limit;
        } catch (Throwable $e) {
            $failClosed = $this->shouldFailClosed($endpoint, $policy);

            log_message(
                $failClosed ? 'error' : 'critical',
                sprintf(
                    'RateLimiter cache gagal (%s) endpoint=%s: %s',
                    $failClosed ? 'fail-closed' : 'fail-open',
                    $endpoint,
                    $e->getMessage()
                )
            );

            // fail-closed -> tolak (false); fail-open -> izinkan (true)
            return ! $failClosed;
        }
    }

    public function safeCacheKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) ?? '';
    }

    /**
     * Tentukan apakah endpoint harus fail-closed saat cache error.
     *
     * Prioritas:
     * 1. Explicit config: RouterConfig['rateLimits'][endpoint]['fail_closed'].
     * 2. Safety net: endpoint di CRITICAL_ENDPOINTS.
     * 3. Default: fail-open (false).
     */
    private function shouldFailClosed(string $endpoint, array $policy): bool
    {
        if (array_key_exists('fail_closed', $policy)) {
            return (bool) $policy['fail_closed'];
        }

        return in_array($endpoint, self::CRITICAL_ENDPOINTS, true);
    }

    private function hit(string $key, int $ttl): int
    {
        $cache = $this->cache ?? cache();

        $value = $cache->increment($key);

        if (is_int($value)) {
            return $value;
        }

        // Backend tanpa increment yang andal (mis. file cache): fallback baca-tulis.
        // Bukan atomik, tapi minimal tetap menghitung. Gunakan Redis di production.
        $current = (int) ($cache->get($key) ?? 0);
        $new     = $current + 1;
        $cache->save($key, $new, $ttl);

        return $new;
    }
}
