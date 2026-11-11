<?php

declare(strict_types=1);

namespace App\Libraries\DynamicRouter;

class MethodPolicy
{
    /** @var array<string, array<string, string>> controller => [lowerMethodName => actualMethodName] */
    private array $index = [];

    /** @var array<string, true> endpoint => true, untuk lookup O(1) */
    private array $allowedLookup = [];

    /** @var bool Jika false, allowedMethods kosong = fail-closed */
    private bool $allowAllIfEmpty = false;

    public function __construct(
        private array $discoveredMethods,
        private array $allowedMethods = [],
        bool $allowAllIfEmpty = false,
    ) {
        $this->allowAllIfEmpty = $allowAllIfEmpty;
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
                log_message('warning', "MethodPolicy: duplikat case-insensitive '{$controller}::{$method}' vs '{$this->index[$controller][$key]}'");
            }

            $this->index[$controller][$key] = $method;
        }
    }

    /**
     * Normalisasi nama method: kebab/snake/space → camelCase.
     * Jika tanpa separator, kembalikan apa adanya (case dipertahankan).
     */
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

    /**
     * @return array{allowed: bool, reason: string, endpoint?: string}
     */
    /**
     * Cari nama method aktual yang ter-discover untuk controller.
     * Case-insensitive + normalisasi kebab/snake → camelCase.
     */
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
        $key = strtolower($normalized);

        return $this->index[$controller][$key] ?? null;
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
     * @return array{allowed: bool, reason: string, endpoint?: string}
     */
    public function diagnoseMethod(string $controller, string $method): array
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $controller)) {
            return [
                'allowed' => false,
                'reason'  => 'INVALID_CONTROLLER_NAME',
            ];
        }

        $resolved = $this->resolveDiscoveredMethod($controller, $method);

        if ($resolved === null) {
            return [
                'allowed' => false,
                'reason'  => 'METHOD_NOT_DISCOVERED',
            ];
        }

        $endpoint = $controller . '::' . $resolved;

        // Fail-closed: allowlist wajib kecuali allowAllIfEmpty = true
        if ($this->allowedMethods === [] && ! $this->allowAllIfEmpty) {
            log_message('warning', "MethodPolicy: allowedMethods kosong untuk '{$controller}' — fail-closed.");

            return [
                'allowed'  => false,
                'reason'   => 'METHOD_ALLOWLIST_EMPTY',
                'endpoint' => $endpoint,
            ];
        }

        if ($this->allowedLookup !== [] && ! isset($this->allowedLookup[$endpoint])) {
            log_message('debug', "MethodPolicy: method '{$endpoint}' tidak ada di allowlist.");

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
