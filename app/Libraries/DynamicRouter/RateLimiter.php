<?php

namespace App\Libraries\DynamicRouter;

use CodeIgniter\HTTP\RequestInterface;

class RateLimiter
{
    public function __construct(
        private RequestInterface $request,
        private string $moduleFolder,
        private array $rateLimits = [],
        private ?AuthenticationResolver $authenticationResolver = null,
    ) {}

    public function checkRateLimit(
        string $endpoint
    ): bool {
        $policy = $this->rateLimits[$endpoint] ?? null;

        if ($policy === null) {
            return true;
        }

        $limit = (int) ($policy['limit'] ?? 10);
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

        $key = $this->safeCacheKey(
            "rate_limit:{$this->moduleFolder}:{$endpoint}:{$identity}"
        );

        $count = (int) (cache()->get($key) ?? 0);

        if ($count >= $limit) {
            return false;
        }

        cache()->save($key, $count + 1, $minutes * 60);

        return true;
    }

    public function safeCacheKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) ?? '';
    }
}
