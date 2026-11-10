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
        $verb = strtoupper($this->request->getMethod());

        if (array_key_exists($endpoint, $this->allowedHttpVerbs)) {
            $allowed = $this->allowedHttpVerbs[$endpoint];

            if (! is_array($allowed)) {
                return false;
            }

            $allowed = array_map(
                static fn($item): string =>
                is_string($item) ? strtoupper($item) : '',
                $allowed
            );

            return in_array($verb, $allowed, true);
        }

        return service('routeRegistryAccess')
            ->allowsHttpVerb($endpoint, $verb);
    }

    public function isHttpVerbExcluded(string $endpoint): bool
    {
        return in_array($endpoint, $this->exclusions, true);
    }
}
