<?php

declare(strict_types=1);

namespace App\Libraries\DynamicRouter;

use CodeIgniter\HTTP\RequestInterface;
use Config\AuthPolicy;
use Throwable;

class AccessChecker
{
    private AuthPolicy $policy;

    public function __construct(
        private RequestInterface $request,
        private AuthenticationResolver $authenticationResolver,
        private string $module,
        private array $publicMethods = [],
        ?AuthPolicy $policy = null
    ) {
        $this->policy = $policy ?? config(AuthPolicy::class);
    }

    /**
     * Mendiagnosis akses endpoint.
     *
     * @return array{
     *     allowed: bool,
     *     reason: string,
     *     message: string,
     *     status: int
     * }
     */


    public function diagnoseAccess(string $endpoint): array
    {
        $endpoint = trim($endpoint);

        if ($endpoint === '') {
            return $this->decision(
                false,
                'invalid_endpoint',
                'Endpoint yang diminta tidak valid.',
                404
            );
        }

        $httpVerb = strtoupper($this->request->getMethod());
        // 1. Periksa Route Registry.
        try {
            $registry = service('routeRegistryAccess');
            $info = $registry->inspectEndpoint($this->module, $endpoint, $httpVerb);
        } catch (\Throwable $e) {
            log_message(
                'error',
                'Gagal memeriksa route registry. Endpoint: {endpoint}; Error: {error}',
                [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]
            );

            return $this->decision(
                false,
                'route_registry_error',
                'Status fitur tidak dapat diverifikasi. Silakan coba kembali atau hubungi administrator.',
                500
            );
        }

        if (($info['found'] ?? false) !== true) {
            return $this->decision(
                false,
                'endpoint_not_registered',
                'Fitur ini belum terdaftar di sistem. Silakan hubungi administrator.',
                404
            );
        }

        // 2. Validasi status route.
        $routeFlags = [
            'is_discovered' => [
                'endpoint_not_discovered',
                'Endpoint belum terdeteksi oleh sistem. Silakan hubungi administrator.',
            ],
            'is_routable' => [
                'route_not_routable',
                'Fitur ini belum diaktifkan untuk routing. Silakan hubungi administrator.',
            ],
            'is_allowed' => [
                'route_not_allowed',
                'Akses ke fitur ini belum diizinkan oleh administrator.',
            ],
        ];

        foreach ($routeFlags as $flag => [$reason, $message]) {
            if (($info[$flag] ?? false) !== true) {
                return $this->decision(
                    false,
                    $reason,
                    $message,
                    403
                );
            }
        }

        // 3. Validasi HTTP verb.
        if (($info['verb_registered'] ?? false) !== true) {
            return $this->decision(
                false,
                'http_verb_not_allowed',
                'Metode akses untuk fitur ini belum dikonfigurasi.',
                405
            );
        }

        // 4. Validasi route ID.
        $routeId = (int) ($info['id'] ?? 0);

        if ($routeId <= 0) {
            log_message(
                'error',
                'Route ID tidak valid untuk endpoint {endpoint}.',
                ['endpoint' => $endpoint]
            );

            return $this->decision(
                false,
                'invalid_route_id',
                'Konfigurasi fitur tidak valid. Silakan hubungi administrator.',
                500
            );
        }

        // 5. Public endpoint.
        // Tetap wajib melewati validasi Route Registry dan HTTP verb.
        try {
            if ($this->isPolicyPublicEndpoint($endpoint, $httpVerb)) {
                return $this->decision(
                    true,
                    'public_endpoint',
                    'Akses diizinkan.',
                    200
                );
            }
        } catch (\Throwable $e) {
            log_message(
                'error',
                'Gagal memeriksa public policy: {error}',
                ['error' => $e->getMessage()]
            );

            return $this->decision(
                false,
                'public_policy_error',
                'Kebijakan akses tidak dapat diverifikasi.',
                500
            );
        }

        // 6. Autentikasi.
        $user = $this->authenticationResolver->getAuthenticatedUser();

        // $user = $this->authenticationResolver->getSessionUser();
        if ($user === null) {
            return $this->decision(
                false,
                'authentication_required',
                'Silakan masuk terlebih dahulu untuk mengakses fitur ini.',
                401
            );
        }
        $userId = (int) ($user->id ?? 0);
        if ($userId <= 0) {
            log_message('error', 'AccessChecker: authenticated user dengan ID invalid.', [
                'endpoint' => $endpoint,
            ]);
            return $this->decision(
                false,
                'invalid_user',
                'Akun tidak dapat diverifikasi. Silakan masuk kembali.',
                401
            );
        }

        // 7. Validasi akun dan bypass group.
        try {
            $accessControl = service('accessControl');

            if (! $accessControl->isActiveUser($userId)) {
                return $this->decision(
                    false,
                    'user_inactive',
                    'Akun tidak aktif atau tidak dapat digunakan. Silakan hubungi administrator.',
                    403
                );
            }

            $isBypassGroup = $this->isPolicyBypassGroup($userId);
        } catch (\Throwable $e) {
            log_message(
                'error',
                'Gagal memvalidasi akun {userId}: {error}',
                [
                    'userId' => $userId,
                    'error' => $e->getMessage(),
                ]
            );

            return $this->decision(
                false,
                'account_validation_error',
                'Status akun tidak dapat diverifikasi. Silakan coba kembali.',
                500
            );
        }

        // 8. Bypass group hanya melewati grant permission.
        // Tidak melewati status akun, route, atau HTTP verb.
        if ($isBypassGroup) {
            // Batasi bypass ke GET/HEAD (read-only) untuk mengurangi risiko.
            if (! in_array($httpVerb, ['GET', 'HEAD'], true)) {
                log_message('info', "AccessChecker: bypass group diblokir untuk {$httpVerb} {$endpoint}");
                // Lanjut ke pengecekan permission normal
            } else {
                return $this->decision(true, 'bypass_group', 'Akses diizinkan.', 200);
            }
        }

        // 9. Permission override.
        try {
            $override = $this->resolvePolicyOverride(
                $endpoint,
                $httpVerb
            );
        } catch (\Throwable $e) {
            log_message(
                'error',
                'Permission override tidak valid. Endpoint: {endpoint}; Error: {error}',
                [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]
            );

            return $this->decision(
                false,
                'invalid_permission_override',
                'Konfigurasi hak akses tidak valid. Silakan hubungi administrator.',
                500
            );
        }

        if ($override !== null) {
            // Cukup login dan akun aktif.
            if ($override === AuthPolicy::AUTHENTICATED) {
                return $this->decision(
                    true,
                    'authenticated_access_granted',
                    'Akses diizinkan.',
                    200
                );
            }

            // Override menggantikan permission Route Registry.
            try {
                $allowed = $accessControl->hasAllPermissions(
                    $userId,
                    [$override]
                );
            } catch (\Throwable $e) {
                log_message(
                    'error',
                    'Override permission check gagal. Endpoint: {endpoint}; User: {userId}; Error: {error}',
                    [
                        'endpoint' => $endpoint,
                        'userId' => $userId,
                        'error' => $e->getMessage(),
                    ]
                );

                return $this->decision(
                    false,
                    'authorization_error',
                    'Izin akses tidak dapat diverifikasi.',
                    500
                );
            }

            return $this->decision(
                $allowed,
                $allowed ? 'permission_override_granted' : 'permission_denied',
                $allowed
                    ? 'Akses diizinkan.'
                    : 'Anda tidak memiliki izin untuk mengakses fitur ini.',
                $allowed ? 200 : 403
            );
        }

        // 10. Validasi permission Route Registry.
        $permissions = $info['permissions'] ?? null;

        if (! is_array($permissions)) {
            log_message(
                'error',
                'Format permission route tidak valid untuk endpoint {endpoint}.',
                ['endpoint' => $endpoint]
            );

            return $this->decision(
                false,
                'invalid_route_permissions',
                'Konfigurasi hak akses fitur tidak valid. Silakan hubungi administrator.',
                500
            );
        }

        $normalizedPermissions = [];

        foreach ($permissions as $permission) {
            if (! is_string($permission) || trim($permission) === '') {
                log_message(
                    'error',
                    'Permission route tidak valid. Endpoint: {endpoint}; Route ID: {routeId}.',
                    [
                        'endpoint' => $endpoint,
                        'routeId' => $routeId,
                    ]
                );

                return $this->decision(
                    false,
                    'invalid_route_permissions',
                    'Konfigurasi hak akses fitur tidak valid. Silakan hubungi administrator.',
                    500
                );
            }

            $permission = trim($permission);
            $normalizedPermissions[$permission] = $permission;
        }

        $permissions = array_values($normalizedPermissions);

        if ($permissions === []) {
            log_message(
                'error',
                'Permission belum dikonfigurasi untuk endpoint {endpoint}, route ID {routeId}.',
                [
                    'endpoint' => $endpoint,
                    'routeId' => $routeId,
                ]
            );

            return $this->decision(
                false,
                'route_permission_not_configured',
                'Hak akses untuk fitur ini belum dikonfigurasi. Silakan hubungi administrator.',
                403
            );
        }

        // 11. Pemeriksaan permission melalui Route Registry.
        try {
            $allowed = $registry->allows($userId, $routeId);
        } catch (\Throwable $e) {
            log_message(
                'error',
                'Pemeriksaan permission gagal. Endpoint: {endpoint}; User: {userId}; Error: {error}',
                [
                    'endpoint' => $endpoint,
                    'userId' => $userId,
                    'error' => $e->getMessage(),
                ]
            );

            return $this->decision(
                false,
                'authorization_error',
                'Izin akses tidak dapat diverifikasi. Silakan coba kembali atau hubungi administrator.',
                500
            );
        }

        return $this->decision(
            $allowed,
            $allowed ? 'permission_granted' : 'permission_denied',
            $allowed
                ? 'Akses diizinkan.'
                : 'Anda tidak memiliki izin untuk mengakses fitur ini.',
            $allowed ? 200 : 403
        );
    }



    /**
     * Kompatibilitas dengan pemanggil yang membutuhkan boolean.
     */
    public function checkAccess(string $endpoint): bool
    {
        return $this->diagnoseAccess($endpoint)['allowed'];
    }

    public function isSuperadmin(): bool
    {
        $user = $this->authenticationResolver->getAuthenticatedUser();
        $userId = (int) ($user->id ?? 0);

        if ($userId <= 0) {
            return false;
        }

        try {
            $accessControl = service('accessControl');

            return $accessControl->isActiveUser($userId)
                && $accessControl->isSuperadmin($userId);
        } catch (\Throwable $e) {
            log_message(
                'error',
                'Gagal memeriksa status superadmin: {error}',
                ['error' => $e->getMessage()]
            );

            return false;
        }
    }

    private function decision(
        bool $allowed,
        string $reason,
        string $message,
        int $status
    ): array {
        return [
            'allowed' => $allowed,
            'reason' => $reason,
            'message' => $message,
            'status' => $status,
        ];
    }


    private function matchesPolicyPattern(
        string $endpoint,
        string $pattern,
        string $httpVerb
    ): bool {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return false;
        }

        // Mendukung format "METHOD path".
        if (preg_match('/^([A-Z]+)\s+(.+)$/i', $pattern, $matches)) {
            if (strtoupper($matches[1]) !== $httpVerb) {
                return false;
            }

            $pattern = trim($matches[2]);
        }

        $endpoint = trim($endpoint, '/');
        $pattern = trim($pattern, '/');

        // Root endpoint.
        if ($endpoint === '' && $pattern === '') {
            return true;
        }

        $regex = preg_quote($pattern, '#');

        // ** = nol atau lebih segmen.
        // *  = tepat satu segmen.
        $regex = str_replace(
            ['\*\*', '\*'],
            ['.*', '[^/]+'],
            $regex
        );

        return preg_match(
            '#^' . $regex . '$#',
            $endpoint
        ) === 1;
    }

    private function isPolicyPublicEndpoint(
        string $endpoint,
        string $httpVerb
    ): bool {
        // Pertahankan kompatibilitas konfigurasi publicMethods lama.
        if (in_array($endpoint, $this->publicMethods, true)) {
            return true;
        }

        foreach ($this->policy->publicPatterns as $pattern) {
            if (
                is_string($pattern)
                && $this->matchesPolicyPattern(
                    $endpoint,
                    $pattern,
                    $httpVerb
                )
            ) {
                return true;
            }
        }

        return false;
    }


    private function resolvePolicyOverride(
        string $endpoint,
        string $httpVerb
    ): ?string {
        foreach ($this->policy->overrides as $pattern => $permission) {
            if (! is_string($pattern) || ! is_string($permission)) {
                continue;
            }

            if (! $this->matchesPolicyPattern(
                $endpoint,
                $pattern,
                $httpVerb
            )) {
                continue;
            }

            $permission = trim($permission);

            if ($permission === '') {
                throw new \UnexpectedValueException(
                    'Permission override tidak boleh kosong: ' . $pattern
                );
            }

            return $permission;
        }

        return null;
    }

    private function isPolicyBypassGroup(int $userId): bool
    {
        $groups = array_values(array_filter(
            $this->policy->bypassGroups,
            static fn($group): bool =>
            is_string($group) && trim($group) !== ''
        ));

        if ($groups === []) {
            return false;
        }

        return db_connect()
            ->table('auth_groups_users')
            ->where('user_id', $userId)
            ->whereIn('group', $groups)
            ->countAllResults() > 0;
    }
}
