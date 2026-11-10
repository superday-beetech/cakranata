<?php

declare(strict_types=1);

/**
 * Simpan di: app/Filters/RbacFilter.php
 *
 * ============================================================
 * RBAC FILTER (default deny, fail closed)
 * ============================================================
 * Alur:
 *  1. Normalisasi & validasi path (tolak ../, %, //, karakter aneh).
 *  2. Path publik (Config\Rbac::$publicPatterns) -> lolos.
 *  3. Autentikasi via Shield (web: session, API: token).
 *  4. User banned -> ditolak + logout (revoke langsung, bukan menunggu session habis).
 *  5. Grup bypass (superadmin) -> lolos.
 *  6. Path -> permission:
 *       web : {module}.{controller}.{action}        (default controller/action = index)
 *       api : {module}.api.{resource}.{read|create|update|delete}
 *       atau lewat Config\Rbac::$overrides, atau argumen filter "rbac:scope.aksi".
 *  7. Tidak ada policy -> DITOLAK. Tidak punya permission -> DITOLAK.
 *  8. Error apa pun (DB down, exception) -> DITOLAK (503).
 *
 * Tidak ada cache permission di filter ini: perubahan role/permission di Shield
 * langsung berlaku pada request berikutnya.
 *
 * Idempoten: aman dipasang sebagai global filter DAN di group route.
 *
 * Event audit: Events::trigger('rbac_denied', array $context)
 *   -> sambungkan ke modul audit log milikmu di app/Config/Events.php.
 */

namespace App\Filters;

use CodeIgniter\Events\Events;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Config\Rbac as RbacConfig;
use LogicException;
use Throwable;
use WeakMap;

final class RbacFilter implements FilterInterface
{
    private const ABILITY = [
        'GET'    => 'read',
        'HEAD'   => 'read',
        'POST'   => 'create',
        'PUT'    => 'update',
        'PATCH'  => 'update',
        'DELETE' => 'delete',
    ];

    private const MESSAGES = [
        400 => 'Permintaan tidak valid.',
        401 => 'Autentikasi diperlukan.',
        403 => 'Akses ditolak.',
        503 => 'Layanan sementara tidak tersedia.',
    ];

    /** @var WeakMap<object, bool>|null */
    private static ?WeakMap $checked = null;

    /**
     * @param array<int, string>|null $arguments contoh: 'rbac:sales.approve' (any-of)
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            // CLI dipercaya; request HTTP non-standar ditolak.
            return is_cli() ? null : service('response')->setStatusCode(400);
        }

        self::$checked ??= new WeakMap();

        if (self::$checked[$request] ?? false) {
            return null; // sudah lolos di filter lain pada request ini
        }

        /** @var RbacConfig $cfg */
        $cfg = config('Rbac');

        try {
            $result = $this->authorize($request, $cfg, is_array($arguments) ? $arguments : []);
        } catch (Throwable $e) {
            log_message('critical', '[RBAC] fail-closed: ' . $e::class . ': ' . $e->getMessage());

            return $this->deny($request, $cfg, 'rbac_error', 503);
        }

        if ($result === null) {
            self::$checked[$request] = true;
        }

        return $result;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak dipakai.
    }

    // ============================================================
    // KEPUTUSAN
    // ============================================================

    private function authorize(IncomingRequest $request, RbacConfig $cfg, array $arguments): ?ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $path   = $this->normalizePath((string) $request->getPath(), $cfg);
        $routed = service('router')->controllerName();

        if (is_string($routed) && is_subclass_of(ltrim($routed, '\\'), \App\Libraries\DynamicRouterBase::class)) {
            return null; // keputusan sepenuhnya di AccessChecker (registry, fail-closed)
        }
        if ($path === null) {
            return $this->deny($request, $cfg, 'malformed_path', 400);
        }

        foreach ($cfg->publicPatterns as $pattern) {
            if ($this->matches((string) $pattern, $method, $path)) {
                return null;
            }
        }

        $isApi = $this->isApiPath($path, $cfg);
        $user  = $this->resolveUser($isApi ? $cfg->apiAuthenticators : $cfg->webAuthenticators);


        if ($user === null) {
            return $this->unauthenticated($request, $cfg, $method);
        }

        if (method_exists($user, 'isBanned') && $user->isBanned()) {
            $this->logout();

            return $this->deny($request, $cfg, 'account_banned', 403, $user);
        }

        foreach ($cfg->bypassGroups as $group) {
            if ($user->inGroup((string) $group)) {
                return null;
            }
        }

        if ($arguments !== []) {
            $permissions = array_values(array_filter(
                array_map(static fn($a): string => strtolower(trim((string) $a)), $arguments),
                static fn(string $p): bool => $p !== '' && str_contains($p, '.')
            ));
        } else {
            $derived = $this->resolvePermission($cfg, $method, $path);

            if ($derived === null) {
                return $this->deny($request, $cfg, 'no_policy', 403, $user);
            }

            if ($derived === RbacConfig::AUTHENTICATED) {
                return null;
            }

            $permissions = [$derived];
        }

        if ($permissions === []) {
            return $this->deny($request, $cfg, 'no_policy', 403, $user);
        }

        // di authorize(), ganti bagian bypass dan pengecekan permission:
        $accessControl = service('accessControl');

        if (! $accessControl->isActiveUser((int) $user->id)) {
            $this->logout();
            return $this->deny($request, $cfg, 'account_inactive', 403, $user);
        }

        if ($accessControl->isSuperadmin((int) $user->id)) {
            return null;
        }

        // ...
        if ($accessControl->hasAllPermissions((int) $user->id, $permissions)) {
            return null;
        }

        /*  foreach ($permissions as $permission) {
            try {
                if ($user->can($permission)) {
                    return null;
                }
            } catch (LogicException) {
                // format permission tidak valid -> anggap tidak punya
            }
        } */

        return $this->deny($request, $cfg, 'forbidden', 403, $user, implode('|', $permissions));
    }

    /** Path -> nama permission, atau null bila tidak ada policy (=> deny). */
    private function resolvePermission(RbacConfig $cfg, string $method, string $path): ?string
    {
        foreach ($cfg->overrides as $pattern => $permission) {
            if ($this->matches((string) $pattern, $method, $path)) {
                return (string) $permission;
            }
        }

        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);
        $safe     = static fn(?string $s): bool => $s !== null && preg_match('/^[a-z0-9_-]+$/', $s) === 1;

        // api/v1/{module}/{resource}[/{id}]
        if ($segments[0] === $cfg->apiPrefix) {
            $ability = self::ABILITY[$method] ?? null;

            if ($ability === null || count($segments) < 4 || preg_match('/^v\d+$/', $segments[1]) !== 1) {
                return null;
            }

            if (! $safe($segments[2]) || ! $safe($segments[3])) {
                return null;
            }

            return "{$segments[2]}.api.{$segments[3]}.{$ability}";
        }

        // {module}[/{controller}[/{action}]]
        $module     = $segments[0];
        $controller = $segments[1] ?? 'index';
        $action     = $segments[2] ?? 'index';

        if (! $safe($module) || ! $safe($controller) || ! $safe($action)) {
            return null; // titik/karakter lain dilarang agar key tidak bisa dipalsukan
        }

        return "{$module}.{$controller}.{$action}";
    }

    // ============================================================
    // AUTENTIKASI
    // ============================================================

    /** @param list<string> $aliases */
    private function resolveUser(array $aliases): ?User
    {
        foreach ($aliases as $alias) {
            try {
                $auth = auth($alias);

                if ($auth->loggedIn()) {
                    $user = $auth->user();

                    if ($user !== null) {
                        return $user;
                    }
                }
            } catch (Throwable $e) {
                log_message('warning', "[RBAC] authenticator '{$alias}' gagal: " . $e->getMessage());
            }
        }

        return null;
    }

    private function logout(): void
    {
        try {
            auth('session')->logout();
        } catch (Throwable) {
            // abaikan; request tetap ditolak
        }
    }

    private function unauthenticated(IncomingRequest $request, RbacConfig $cfg, string $method): ResponseInterface
    {
        /*  if ($this->wantsJson($request, $cfg)) {
            return $this->deny($request, $cfg, 'unauthenticated', 401);
        } */

        if ($method === 'GET') {
            try {
                session()->setTempdata('beforeLoginUrl', current_url(), 300);
            } catch (Throwable) {
                // session bermasalah tidak boleh membuka akses
            }
        }

        log_message('debug', '[RBAC] belum login -> redirect login');

        return redirect()->to(site_url($cfg->loginRoute));
    }

    // ============================================================
    // PATH
    // ============================================================

    private function normalizePath(string $raw, RbacConfig $cfg): ?string
    {
        if (strlen($raw) > $cfg->maxPathLength) {
            return null;
        }

        // Karakter kontrol, '%' (sisa encoding / double-encoding), dan backslash.
        if (preg_match('/[\x00-\x1F\x7F%\\\\]/', $raw) === 1) {
            return null;
        }

        $path = strtolower(trim($raw, '/'));

        if ($path === '') {
            return '';
        }

        if (str_contains($path, '//') || preg_match('#^[a-z0-9/_\-.]+$#', $path) !== 1) {
            return null;
        }

        $segments = explode('/', $path);

        if (count($segments) > $cfg->maxSegments) {
            return null;
        }

        foreach ($segments as $segment) {
            if (str_contains($segment, '..') || $segment === '.') {
                return null;
            }
        }

        return $path;
    }

    private function isApiPath(string $path, RbacConfig $cfg): bool
    {
        return $path === $cfg->apiPrefix || str_starts_with($path, $cfg->apiPrefix . '/');
    }

    /**
     * Pola: "path" atau "METHOD path". '*' = satu segmen, '**' = sisa path.
     */
    private function matches(string $pattern, string $method, string $path): bool
    {
        if (preg_match('/^([A-Z]+)\s+(.*)$/', $pattern, $m) === 1) {
            if ($m[1] !== $method) {
                return false;
            }

            $pattern = $m[2];
        }

        $regex = preg_quote(strtolower(trim($pattern, '/')), '#');
        $regex = str_replace(['\*\*', '\*'], ['.*', '[^/]+'], $regex);

        return preg_match('#^' . $regex . '$#', $path) === 1;
    }

    // ============================================================
    // RESPON & AUDIT
    // ============================================================

    private function wantsJson(IncomingRequest $request, RbacConfig $cfg): bool
    {
        $path = strtolower(trim((string) $request->getPath(), '/'));

        return $this->isApiPath($path, $cfg)
            || $request->isAJAX()
            || str_contains(strtolower($request->getHeaderLine('Accept')), 'application/json');
    }

    private function deny(
        IncomingRequest $request,
        RbacConfig $cfg,
        string $reason,
        int $status,
        ?User $user = null,
        ?string $permission = null
    ): ResponseInterface {
        $this->audit($request, $reason, $status, $user, $permission);

        $message  = self::MESSAGES[$status] ?? self::MESSAGES[403];
        $response = service('response')
            ->setStatusCode($status)
            ->setHeader('Cache-Control', 'no-store');

        if ($status === 401) {
            $response->setHeader('WWW-Authenticate', 'Bearer realm="api"');
        }

        if ($this->wantsJson($request, $cfg)) {
            $body = ['status' => false, 'message' => $message];

            // Detail hanya di non-production untuk memudahkan debugging.
            if (ENVIRONMENT !== 'production') {
                $body['reason']              = $reason;
                $body['required_permission'] = $permission;
            }

            return $response->setJSON($body);
        }

        return $response->setContentType('text/plain')->setBody($message);
    }

    private function audit(
        IncomingRequest $request,
        string $reason,
        int $status,
        ?User $user,
        ?string $permission
    ): void {
        // Sanitasi agar tidak bisa menyuntik baris log palsu.
        $path = str_replace('"', "'", (string) preg_replace('/[^\x20-\x7E]/', '?', substr((string) $request->getPath(), 0, 200)));

        $context = [
            'reason'     => $reason,
            'status'     => $status,
            'user_id'    => $user?->id,
            'method'     => strtoupper($request->getMethod()),
            'path'       => $path,
            'permission' => $permission,
            'ip'         => $request->getIPAddress(),
        ];

        log_message('warning', sprintf(
            '[RBAC] deny reason=%s status=%d user=%s method=%s path="%s" perm=%s ip=%s',
            $reason,
            $status,
            $context['user_id'] ?? '-',
            $context['method'],
            $path,
            $permission ?? '-',
            $context['ip']
        ));

        try {
            Events::trigger('rbac_denied', $context);
        } catch (Throwable $e) {
            log_message('error', '[RBAC] event audit gagal: ' . $e->getMessage());
        }
    }
}
