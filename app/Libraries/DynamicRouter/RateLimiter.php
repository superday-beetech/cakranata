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
 * - Bila cache backend gagal, request DIIZINKAN (fail-open) dan error dicatat
 *   critical, supaya gangguan cache tidak melumpuhkan seluruh aplikasi.
 */
class RateLimiter
{
    public function __construct(
        private RequestInterface $request,
        private string $moduleFolder,
        private array $rateLimits = [],
        private ?AuthenticationResolver $authenticationResolver = null,
        private ?CacheInterface $cache = null,
    ) {}

    private const CRITICAL_ENDPOINTS = [
        'AuthController::loginAction',
        'AuthController::registerAction',
        'AuthController::forgotPasswordAction',
        'AuthController::resetPasswordAction',
    ];

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
            $isCritical = in_array($endpoint, self::CRITICAL_ENDPOINTS, true);

            log_message(
                $isCritical ? 'error' : 'critical',
                sprintf(
                    'RateLimiter cache gagal (%s): %s',
                    $isCritical ? 'fail-closed' : 'fail-open',
                    $e->getMessage()
                )
            );

            return ! $isCritical;
        }
    }

    public function safeCacheKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) ?? '';
    }

    private function hit(string $key, int $ttl): int
    {
        $cache = $this->cache ?? cache();

        if ($cache->get($key) === null) {
            $cache->save($key, 0, $ttl);
        }

        $value = $cache->increment($key);

        if (is_int($value)) {
            return $value;
        }

        log_message('warning', 'RateLimiter: increment tidak atomik (file cache). Gunakan Redis untuk production.');


        $current = (int) ($cache->get($key) ?? 0);
        $new = $current + 1;
        $cache->save($key, $new, $ttl);

        return $new;
    }
}
