<?php

namespace App\Libraries\DynamicRouter;

use CodeIgniter\HTTP\RequestInterface;

class HttpVerbPolicy
{
    public function __construct(
        private RequestInterface $request,
        private array $allowedHttpVerbs = [],
        private array $exclusions = [],
    ) {}

    public function checkHttpVerb(string $endpoint): bool
    {
        // Excluded = selalu izinkan (skip cek).
        if ($this->isHttpVerbExcluded($endpoint)) {
            return true;
        }

        // Jika ada di allowedHttpVerbs lokal, HARUS cocok.
        if (array_key_exists($endpoint, $this->allowedHttpVerbs)) {
            $allowed = $this->allowedHttpVerbs[$endpoint];

            if (! is_array($allowed)) {
                return false;
            }

            $verb = strtoupper($this->request->getMethod());
            $allowedUpper = array_map(
                static fn($v): string => is_string($v) ? strtoupper($v) : '',
                $allowed
            );

            return in_array($verb, $allowedUpper, true);
        }

        // Fallback: registry.
        return service('routeRegistryAccess')->allowsHttpVerb($endpoint, strtoupper($this->request->getMethod()));
    }

    public function isHttpVerbExcluded(string $endpoint): bool
    {
        return in_array($endpoint, $this->exclusions, true);
    }
}
