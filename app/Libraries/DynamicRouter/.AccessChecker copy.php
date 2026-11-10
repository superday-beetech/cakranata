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
            $info = $registry->inspectEndpoint($endpoint, $httpVerb);
        } catch (Throwable $e) {
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

        // 4. Validasi ID route.
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

        // 5. Public endpoint hanya melewati autentikasi dan permission.
        // Route Registry dan HTTP verb tetap wajib valid.
        if (in_array($endpoint, $this->publicMethods, true)) {
            return $this->decision(
                true,
                'public_endpoint',
                'Akses diizinkan.',
                200
            );
        }

        // 6. Autentikasi.
        $user = $this->authenticationResolver->getAuthenticatedUser();

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
            return $this->decision(
                false,
                'invalid_user',
                'Akun tidak dapat diverifikasi. Silakan masuk kembali.',
                401
            );
        }

        // 7. Validasi status akun dan superadmin.
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

            $isSuperadmin = $accessControl->isSuperadmin($userId);
        } catch (Throwable $e) {
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

        // 8. Validasi struktur permission.
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

        // 9. Superadmin melewati grant permission.
        // Pemeriksaan route, verb, dan status akun tetap berlaku.
        if ($isSuperadmin) {
            return $this->decision(
                true,
                'superadmin',
                'Akses diizinkan.',
                200
            );
        }

        // 10. User biasa wajib memiliki permission.
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
        } catch (Throwable $e) {
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

        if (! $allowed) {
            return $this->decision(
                false,
                'permission_denied',
                'Anda tidak memiliki izin untuk mengakses fitur ini.',
                403
            );
        }

        return $this->decision(
            true,
            'permission_granted',
            'Akses diizinkan.',
            200
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
        } catch (Throwable $e) {
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

    private function resolvePermissionOverride(
        string $endpoint
    ): ?string {
        $endpoint = trim($endpoint, '/');

        $method = strtoupper(
            $this->request->getMethod()
        );

        foreach ($this->policy->overrides as $pattern => $permission) {
            if (! is_string($pattern) || ! is_string($permission)) {
                continue;
            }

            if (! preg_match('/^([A-Z]+)\s+(.+)$/i', $pattern, $matches)) {
                continue;
            }

            if (strtoupper($matches[1]) !== $method) {
                continue;
            }

            $pathPattern = trim($matches[2], '/');

            $regex = preg_quote($pathPattern, '#');

            $regex = str_replace(
                ['\*\*', '\*'],
                ['.*', '[^/]+'],
                $regex
            );

            if (preg_match('#^' . $regex . '$#', $endpoint)) {
                return $permission;
            }
        }

        return null;
    }

    private function isBypassGroup(int $userId): bool
    {
        $db = db_connect();

        return $db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->whereIn('group', $this->policy->bypassGroups)
            ->countAllResults() > 0;
    }

    private function isPublicEndpoint(string $endpoint): bool
    {
        $endpoint = trim($endpoint, '/');

        if ($endpoint === '') {
            $endpoint = '/';
        }

        $method = strtoupper(
            $this->request->getMethod()
        );

        foreach ($this->policy->publicPatterns as $pattern) {
            $pattern = trim((string) $pattern);

            if ($pattern === '') {
                continue;
            }

            $patternMethod = null;

            if (preg_match('/^([A-Z]+)\s+(.+)$/i', $pattern, $matches)) {
                $patternMethod = strtoupper($matches[1]);
                $pattern = trim($matches[2]);
            }

            if (
                $patternMethod !== null
                && $patternMethod !== $method
            ) {
                continue;
            }

            $regex = preg_quote(trim($pattern, '/'), '#');

            $regex = str_replace(
                ['\*\*', '\*'],
                ['.*', '[^/]+'],
                $regex
            );

            if (preg_match('#^' . $regex . '$#', $endpoint)) {
                return true;
            }
        }

        return false;
    }
}
