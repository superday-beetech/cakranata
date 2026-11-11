<?php

declare(strict_types=1);

namespace App\Libraries\DynamicRouter;

class MethodPolicy
{
    /** @var array<string, array<string, string>> */
    private array $index = [];

    /** @var array<string, true> */
    private array $allowedLookup = [];

    public function __construct(
        private array $discoveredMethods,
        private array $allowedMethods = [],
    ) {
        $this->buildIndex();
        $this->allowedLookup = array_fill_keys($allowedMethods, true);
    }

    private function buildIndex(): void
    {
        foreach ($this->discoveredMethods as $endpoint) {
            $parts = explode('::', $endpoint, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$controller, $method] = $parts;
            $key = strtolower($method);

            if (isset($this->index[$controller][$key]) && $this->index[$controller][$key] !== $method) {
                log_message('warning', "MethodPolicy: duplikat case-insensitive '{$controller}::{$method}'");
            }

            $this->index[$controller][$key] = $method;
        }
    }

    public function normalizeMethodName(string $method): string
    {
        $method = trim($method);
        if ($method === '') {
            return 'index';
        }
        if (! str_contains($method, '-') && ! str_contains($method, '_') && ! str_contains($method, ' ')) {
            return $method;
        }
        $parts = preg_split('/[-_\s]+/', strtolower($method), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return 'index';
        }
        $first = array_shift($parts);
        return $first . implode('', array_map('ucfirst', $parts));
    }

    public function resolveDiscoveredMethod(string $controller, string $requestedMethod): ?string
    {
        if (! isset($this->index[$controller])) {
            return null;
        }
        $key = strtolower(trim($requestedMethod));
        if (isset($this->index[$controller][$key])) {
            return $this->index[$controller][$key];
        }
        $normalized = $this->normalizeMethodName($requestedMethod);
        return $this->index[$controller][strtolower($normalized)] ?? null;
    }

    public function isMethodAllowed(string $controller, string $method): bool
    {
        return $this->diagnoseMethod($controller, $method)['allowed'];
    }

    public function isDiscovered(string $controller, string $method): bool
    {
        return $this->resolveDiscoveredMethod($controller, $method) !== null;
    }

    /**
     * Diagnosa method.
     *
     * Alur:
     * 1. Method harus ada di controller (discovered).
     * 2. Jika RouterConfig::$allowedMethods tidak kosong → override statis.
     * 3. Jika kosong → fallback ke database (route_registry).
     *
     * @return array{allowed: bool, reason: string, endpoint?: string}
     */
    public function diagnoseMethod(
        string $controller,
        string $method,
        string $httpVerb = ''
    ): array {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $controller)) {
            return ['allowed' => false, 'reason' => 'INVALID_CONTROLLER_NAME'];
        }

        $resolved = $this->resolveDiscoveredMethod($controller, $method);

        if ($resolved === null) {
            return ['allowed' => false, 'reason' => 'METHOD_NOT_DISCOVERED'];
        }

        $endpoint = $controller . '::' . $resolved;

        // 1. Override statis dari RouterConfig.
        if ($this->allowedMethods !== []) {
            if (! isset($this->allowedLookup[$endpoint])) {
                return [
                    'allowed'  => false,
                    'reason'   => 'METHOD_NOT_ALLOWLISTED',
                    'endpoint' => $endpoint,
                ];
            }
            return [
                'allowed'  => true,
                'reason'   => 'METHOD_ALLOWED_BY_CONFIG',
                'endpoint' => $endpoint,
            ];
        }

        // 2. Fallback database.
        if ($httpVerb === '') {
            // Tanpa verb: anggap OK, otorisasi penuh di AccessChecker.
            return [
                'allowed'  => true,
                'reason'   => 'METHOD_DISCOVERED_ONLY',
                'endpoint' => $endpoint,
            ];
        }

        try {
            $registry = service('routeRegistryAccess');
            $route = $registry->findAllowedRoute($endpoint, $httpVerb);

            if ($route === null) {
                return [
                    'allowed'  => false,
                    'reason'   => 'ENDPOINT_NOT_REGISTERED',
                    'endpoint' => $endpoint,
                ];
            }

            return [
                'allowed'  => true,
                'reason'   => 'METHOD_ALLOWED_BY_DATABASE',
                'endpoint' => $endpoint,
            ];
        } catch (\Throwable $e) {
            log_message('error', 'MethodPolicy: gagal cek database — ' . $e->getMessage());
            return [
                'allowed'  => false,
                'reason'   => 'REGISTRY_ERROR',
                'endpoint' => $endpoint,
            ];
        }
    }
}
