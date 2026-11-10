<?php

namespace App\Libraries\DynamicRouter;

class MethodPolicy
{
    public function __construct(
        private array $discoveredMethods,
        private array $allowedMethods = [],
    ) {}

    public function normalizeMethodName(
        string $method,
        string $controller
    ): string {
        $method = trim($method);

        if ($method === '') {
            return 'index';
        }

        // Utamakan nama metode asli yang ditemukan saat scanning.
        foreach ($this->discoveredMethods as $endpoint) {
            $parts = explode('::', $endpoint, 2);

            if (count($parts) !== 2) {
                continue;
            }

            [$name, $discoveredMethod] = $parts;

            if (
                $name === $controller
                && strcasecmp($discoveredMethod, $method) === 0
            ) {
                return $discoveredMethod;
            }
        }

        // Contoh: user-profile atau user_profile -> userProfile.
        $normalized = lcfirst(
            str_replace(
                ' ',
                '',
                ucwords(str_replace(['-', '_'], ' ', $method))
            )
        );

        foreach ($this->discoveredMethods as $endpoint) {
            $parts = explode('::', $endpoint, 2);

            if (count($parts) !== 2) {
                continue;
            }

            [$name, $discoveredMethod] = $parts;

            if (
                $name === $controller
                && strcasecmp($discoveredMethod, $normalized) === 0
            ) {
                return $discoveredMethod;
            }
        }

        // Jangan menganggap metode yang tidak dikenal valid.
        return $method;
    }

    public function resolveDiscoveredMethod(
        string $controller,
        string $requestedMethod
    ): ?string {
        $normalized = $this->normalizeMethodName(
            $requestedMethod,
            $controller
        );

        foreach ($this->discoveredMethods as $endpoint) {
            $parts = explode('::', $endpoint, 2);

            if (count($parts) !== 2) {
                continue;
            }

            [$name, $method] = $parts;

            if (
                $name === $controller
                && strcasecmp($method, $normalized) === 0
            ) {
                return $method;
            }
        }

        return null;
    }

    public function isMethodAllowed(
        string $controller,
        string $method
    ): bool {
        return $this->diagnoseMethod(
            $controller,
            $method
        )['allowed'];
    }

    public function isDiscovered(
        string $controller,
        string $method
    ): bool {
        return $this->resolveDiscoveredMethod(
            $controller,
            $method
        ) !== null;
    }

    public function diagnoseMethod(
        string $controller,
        string $method
    ): array {
        $resolved = $this->resolveDiscoveredMethod(
            $controller,
            $method
        );

        if ($resolved === null) {
            return [
                'allowed' => false,
                'reason'  => 'METHOD_NOT_DISCOVERED',
            ];
        }

        $endpoint = $controller . '::' . $resolved;

        if (
            $this->allowedMethods !== []
            && ! in_array($endpoint, $this->allowedMethods, true)
        ) {
            return [
                'allowed'  => false,
                'reason'   => 'METHOD_NOT_ALLOWLISTED',
                'endpoint' => $endpoint,
            ];
        }

        return [
            'allowed'  => true,
            'reason'   => 'METHOD_ALLOWED',
            'endpoint' => $endpoint,
        ];
    }
}
