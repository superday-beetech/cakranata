<?php

namespace App\Libraries;

use App\Libraries\DynamicRouter\AccessChecker;
use App\Libraries\DynamicRouter\AuthenticationResolver;
use App\Libraries\DynamicRouter\ControllerScanner;
use App\Libraries\DynamicRouter\HttpVerbPolicy;
use App\Libraries\DynamicRouter\MethodPolicy;
use App\Libraries\DynamicRouter\RateLimiter;
use App\Libraries\DynamicRouter\RouteResolver;
use App\Libraries\DynamicRouter\RouteRegistrySynchronizer;
use App\Libraries\DynamicRouter\RouterResponseHandler;
use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\AuthPolicy;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;

abstract class DynamicRouterBase extends Controller
{
    protected const ENDPOINT_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*::[A-Za-z_][A-Za-z0-9_]*$/';

    protected const HTTP_VERBS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    protected const ARRAY_CONFIG_KEYS = [
        'allowedMethods',
        'publicMethods',
        'requiredPermissions',
        'requiredRoles',
        'allowedHttpVerbs',
        'httpVerbExclusions',
        'rateLimits',
        'authApiExceptions',
    ];
    private const RELAXABLE_ENVIRONMENTS = ['development', 'testing'];

    protected AuthPolicy $authPolicy;

    protected string $moduleFolder = '';
    protected string $moduleNamespace = '';
    protected string $defaultController = '';

    /** @var bool Aktif/nonaktif module */
    protected bool $enabled = true;

    /** @var bool Wajib login? */
    protected bool $authRequired = false;
    protected array $authApiExceptions = [];

    /** @var array Allowed controller names (tanpa namespace) */
    protected array $allowedControllers = [];

    /** @var array Allowed method names */
    protected array $allowedMethods = [];

    /** @var array endpoint => required permissions */
    protected array $requiredPermissions = [];

    /** @var array endpoint => [GET, POST, ...] */
    protected array $allowedHttpVerbs = [];

    /** @var array endpoint => ['limit' => 10, 'minutes' => 1] */
    protected array $rateLimits = [];

    protected bool $rateLimitEnabled = false;

    /** @var array endpoint => required roles */
    protected array $requiredRoles = [];

    /** @var callable|null */
    protected $fallbackHandler = null;
    protected $apiFallbackHandler;
    protected $uiFallbackHandler;

    /** @var array Hooks sebelum controller. Boleh return ResponseInterface untuk menghentikan request. */
    protected array $beforeRouteHooks = [];

    /** @var array Hooks sesudah controller */
    protected array $afterRouteHooks = [];

    protected array $whitelist = ['themes', 'assets', 'uploads'];

    protected array $discoveredMethods = [];
    protected array $controllerClasses = [];
    protected array $routerConfig = [];

    /** @var list<string> Endpoint publik tanpa permission */
    protected array $publicMethods = [];
    protected array $httpVerbExclusions = [];

    protected ControllerScanner $controllerScanner;
    protected MethodPolicy $methodPolicy;
    protected RouteResolver $routeResolver;
    protected AuthenticationResolver $authenticationResolver;
    protected AccessChecker $accessChecker;
    protected HttpVerbPolicy $httpVerbPolicy;
    protected RateLimiter $rateLimiter;
    protected RouterResponseHandler $routerResponseHandler;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // 1. Muat konfigurasi sebelum helper dibuat.
        $this->loadRouterConfig();
        $this->authPolicy = config(AuthPolicy::class);
        // 2. Scan controller.
        $this->controllerScanner = new ControllerScanner($this->moduleFolder, $this->moduleNamespace);
        $this->scanControllersAndMethods();

        // 3. Kebijakan method.
        $this->methodPolicy = new MethodPolicy($this->discoveredMethods, $this->allowedMethods);

        // 4. Resolver route.
        $this->routeResolver = new RouteResolver(
            $request,
            $this->methodPolicy,
            $this->controllerClasses,
            $this->defaultController
        );

        // 5. Autentikasi.
        $this->authenticationResolver = new AuthenticationResolver($request, $this->authPolicy);

        // 6. Otorisasi.
        $this->accessChecker = new AccessChecker(
            $request,
            $this->authenticationResolver,
            $this->moduleFolder,
            $this->publicMethods,
            $this->authPolicy
        );

        // 7. HTTP verb.
        $this->httpVerbPolicy = new HttpVerbPolicy(
            $request,
            $this->allowedHttpVerbs,
            $this->httpVerbExclusions
        );

        // 8. Rate limit.
        $this->rateLimiter = new RateLimiter(
            $request,
            $this->moduleFolder,
            $this->rateLimits,
            $this->authenticationResolver
        );

        $this->configureResponseHandlers();
    }

    protected function scanControllersAndMethods(): void
    {
        $this->applyScanResult($this->controllerScanner->scan());
    }

    protected function scanControllersAndMethodsNoCache(): void
    {
        $this->applyScanResult($this->controllerScanner->scanWithoutCache());
    }

    protected function applyScanResult(array $result): void
    {
        $this->allowedControllers = $result['allowedControllers'];
        $this->discoveredMethods  = $result['discoveredMethods'];
        $this->controllerClasses  = $result['controllerClasses'];
    }

    public function route($seg1 = '', $seg2 = null, ...$params)
    {
        if (! $this->enabled && ! $this->isSuperadmin()) {
            throw PageNotFoundException::forPageNotFound();
        }

        [$controller, $method, $params] = $this->resolveControllerAndMethod($seg1, $seg2, $params);
        $method = $this->resolveEffectiveMethod($controller, $method);

        if (! in_array($controller, $this->allowedControllers, true)) {
            $this->rejectAsNotFound('controller_policy', 'CONTROLLER_NOT_ALLOWED', ['controller' => $controller]);
        }
        
        $methodDecision = $this->methodPolicy->diagnoseMethod($controller, $method);

        if (! $methodDecision['allowed']) {
            $this->rejectAsNotFound('method_policy', $methodDecision['reason'], [
                'controller' => $controller,
                'method'     => $method,
            ]);
        }

        $resolved = $this->methodPolicy->resolveDiscoveredMethod($controller, $method);

        if ($resolved === null) {
            $this->rejectAsNotFound('method_policy', 'METHOD_NOT_DISCOVERED', [
                'controller' => $controller,
                'method'     => $method,
            ]);
        }

        $method   = $resolved;
        $endpoint = $controller . '::' . $method;

        // Rate limit lebih dulu: melindungi endpoint publik (login, register, magic link).
        if ($this->rateLimitEnabled && ! $this->checkRateLimit($endpoint)) {
            return $this->handleTooManyRequests($endpoint);
        }

        // Akses: autentikasi -> akun aktif -> registry -> permission.
        if ($this->isSecurityFeatureEnabled('Core.checkAccess')) {
            $decision = $this->accessChecker->diagnoseAccess($endpoint);

            if (! $decision['allowed']) {
                $this->auditDenied($endpoint, $decision);

                return $this->routerResponseHandler->handleAccessDecision($decision);
            }
        }

        // Verb dari RouterConfig (hanya bisa mempersempit), setelah akses lolos.
        if (
            $this->isSecurityFeatureEnabled('Core.httpVerbs')
            && ! $this->isHttpVerbExcluded($endpoint)
            && ! $this->checkHttpVerb($endpoint)
        ) {
            $this->logRejection('http_verb', 'HTTP_VERB_NOT_ALLOWED', [
                'endpoint' => $endpoint,
                'verb'     => strtoupper($this->request->getMethod()),
            ]);

            return $this->handleMethodNotAllowed('HTTP_VERB_NOT_ALLOWED', null, null);
        }

        foreach ($this->beforeRouteHooks as $hook) {
            $this->assertHookCallable($hook, 'beforeRouteHooks');

            $override = $hook($controller, $method, $params);

            if ($override instanceof ResponseInterface) {
                return $override;
            }
        }

        $result = $this->runController($controller, $method, $params);

        foreach ($this->afterRouteHooks as $hook) {
            $this->assertHookCallable($hook, 'afterRouteHooks');
            $hook($controller, $method, $params, $result);
        }

        return $result;
    }

    protected function auditDenied(string $endpoint, array $decision): void
    {
        $context = [
            'reason'   => $decision['reason'] ?? '',
            'status'   => $decision['status'] ?? 0,
            'user_id'  => $decision['user_id'] ?? null,
            'module'   => $this->moduleFolder,
            'endpoint' => $endpoint,
            'method'   => strtoupper($this->request->getMethod()),
            'ip'       => $this->request->getIPAddress(),
        ];

        log_message(($decision['status'] ?? 0) === 401 ? 'debug' : 'warning', 'DynamicRouter denied: ' . json_encode($context));

        if (($decision['status'] ?? 0) !== 401) {
            try {
                \CodeIgniter\Events\Events::trigger('rbac_denied', $context);
            } catch (\Throwable $e) {
                log_message('error', 'Event audit gagal: ' . $e->getMessage());
            }
        }
    }



    /**
     * Setting keamanan: default AKTIF (fail-closed).
     * Di production, setting tidak bisa mematikan fitur ini.
     */
    protected function isSecurityFeatureEnabled(string $settingKey): bool
    {
        $value = service('settings')->get($settingKey);

        if ($value === null) {
            return true;
        }

        $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($enabled === null || $enabled === true) {
            return true;
        }


        // Di luar development/testing, setting tidak boleh mematikan fitur keamanan.
        if (! in_array(ENVIRONMENT, self::RELAXABLE_ENVIRONMENTS, true)) {
            log_message('critical', "DynamicRouter: setting {$settingKey} dimatikan di " . ENVIRONMENT . ', diabaikan.');

            return true;
        }

        return false;
    }

    protected function assertHookCallable(mixed $hook, string $name): void
    {
        if (! is_callable($hook)) {
            throw new RuntimeException("Hook pada {$name} harus callable.");
        }
    }

    protected function logRejection(string $stage, string $reason, array $context = []): void
    {
        log_message('warning', 'DynamicRouter rejected: ' . json_encode([
            'stage'  => $stage,
            'reason' => $reason,
            'ip'     => $this->request->getIPAddress(),
        ] + $context));
    }

    /**
     * Catat lalu samarkan sebagai 404.
     *
     * @return never
     */
    protected function rejectAsNotFound(string $stage, string $reason, array $context = [])
    {
        $this->logRejection($stage, $reason, $context);

        throw PageNotFoundException::forPageNotFound();
    }

    protected function handleTooManyRequests(string $endpoint)
    {
        $minutes = (int) ($this->rateLimits[$endpoint]['minutes'] ?? 1);

        $this->response->setHeader('Retry-After', (string) (max(1, $minutes) * 60));

        return ($this->fallbackHandler)(
            'Terlalu banyak permintaan. Silakan coba lagi nanti.',
            429,
            'RATE_LIMIT_EXCEEDED'
        );
    }

    protected function isSuperadmin(): bool
    {
        return $this->accessChecker->isSuperadmin();
    }

    protected function normalizeMethodName(string $method, string $controller): string
    {
        return $this->methodPolicy->normalizeMethodName($method, $controller);
    }

    protected function resolveDiscoveredMethod(string $controller, string $requestedMethod): ?string
    {
        return $this->methodPolicy->resolveDiscoveredMethod($controller, $requestedMethod);
    }

    protected function isMethodAllowed(string $controller, string $method): bool
    {
        return $this->methodPolicy->isMethodAllowed($controller, $method);
    }

    protected function resolveControllerAndMethod($seg1, $seg2, array &$params): array
    {
        return $this->routeResolver->resolveControllerAndMethod($seg1, $seg2, $params);
    }

    protected function resolveEffectiveMethod(string $controller, string $method): string
    {
        return $this->routeResolver->resolveEffectiveMethod($controller, $method);
    }

    protected function resolveMethodByVerb(string $controller, string $requestedMethod): ?string
    {
        return $this->routeResolver->resolveMethodByVerb($controller, $requestedMethod);
    }

    protected function normalizeControllerName(string $controller): string
    {
        return $this->routeResolver->normalizeControllerName($controller);
    }

    protected function isAjax(): bool
    {
        return $this->routeResolver->isAjax();
    }

    protected function isApiRequest(): bool
    {
        return $this->authenticationResolver->isApiRequest();
    }

    protected function isAuthenticated(): bool
    {
        return $this->authenticationResolver->isAuthenticated();
    }

    protected function getAuthenticatedUser()
    {
        return $this->authenticationResolver->getAuthenticatedUser();
    }

    protected function checkAccess(string $endpoint): bool
    {
        return $this->accessChecker->checkAccess($endpoint);
    }

    protected function checkHttpVerb(string $endpoint): bool
    {
        return $this->httpVerbPolicy->checkHttpVerb($endpoint);
    }

    protected function isHttpVerbExcluded(string $endpoint): bool
    {
        return $this->httpVerbPolicy->isHttpVerbExcluded($endpoint);
    }

    protected function checkRateLimit(string $endpoint): bool
    {
        return $this->rateLimiter->checkRateLimit($endpoint);
    }

    protected function safe_cache_key(string $key): string
    {
        return $this->rateLimiter->safeCacheKey($key);
    }

    protected function runController(string $controller, string $method, array $params = [])
    {
        $className = $this->controllerClasses[$controller] ?? null;
        $notFound  = fn() => $this->handleNotFound('NOT_FOUND', 'Halaman tidak ditemukan.');

        if (
            $className === null
            || ! str_starts_with($className, trim($this->moduleNamespace, '\\') . '\\')
            || ! class_exists($className)
            || ! is_subclass_of($className, Controller::class)
        ) {
            log_message('warning', "runController: controller tidak valid '{$controller}'.");

            return $notFound();
        }

        try {
            $reflection = new ReflectionMethod($className, $method);
        } catch (ReflectionException) {
            return $notFound();
        }

        if (! $reflection->isPublic() || $reflection->isStatic()) {
            return $notFound();
        }

        $params = array_values($params);

        if (
            count($params) < $reflection->getNumberOfRequiredParameters()
            || (! $reflection->isVariadic() && count($params) > $reflection->getNumberOfParameters())
            || ! $this->paramsMatchSignature($reflection, $params)
        ) {
            return $notFound();
        }

        $instance = new $className();
        $instance->initController($this->request, $this->response, $this->logger);

        return $instance->{$method}(...$params);
    }

    /** Parameter URL selalu string: tolak yang tidak bisa menjadi tipe skalar yang diminta (hindari TypeError 500). */
    protected function paramsMatchSignature(ReflectionMethod $method, array $params): bool
    {
        foreach ($method->getParameters() as $i => $parameter) {
            if (! array_key_exists($i, $params)) {
                continue;
            }

            $type = $parameter->getType();

            if (! $type instanceof \ReflectionNamedType || ! $type->isBuiltin()) {
                continue;
            }

            $value = $params[$i];

            $ok = match ($type->getName()) {
                'int'   => is_string($value) && preg_match('/^-?\d{1,18}$/', $value) === 1,
                'float' => is_numeric($value),
                'bool'  => in_array($value, ['0', '1', 'true', 'false'], true),
                default => true,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    protected function handleNotFound(string $codeOrMessage = 'NOT_FOUND', ?string $customMessage = null)
    {
        return $this->routerResponseHandler->handleNotFound($codeOrMessage, $customMessage);
    }

    protected function handleMethodNotAllowed(
        string $codeOrMessage = 'HTTP_VERB_NOT_ALLOWED',
        ?string $customMessage = null,
        ?string $prefix = null,
    ) {
        return $this->routerResponseHandler->handleMethodNotAllowed($codeOrMessage, $customMessage, $prefix);
    }

    /**
     * Inisialisasi default response handlers. Modul turunan boleh menyediakan handler sendiri.
     */
    protected function configureResponseHandlers(): void
    {
        $isApiRequest = fn(): bool => $this->isApiRequest();

        $uiFallbackHandler = function (string $message, int $status, string $code) {
            $html = view('errors/response', [
                'status'  => $status,
                'code'    => $code,
                'title'   => $this->getErrorTitle($status),
                'message' => $message,
            ]);

            return $this->response
                ->setStatusCode($status)
                ->setContentType('text/html', 'UTF-8')
                ->setBody($html);
        };

        $apiFallbackHandler = function (string $message, int $status, string $code) {
            return $this->response
                ->setStatusCode($status)
                ->setJSON([
                    'status'  => $status,
                    'error'   => true,
                    'code'    => $code,
                    'title'   => $this->getErrorTitle($status),
                    'message' => $message,
                ]);
        };

        $fallbackHandler = function (string $message, int $status, string $code) use (
            $isApiRequest,
            $apiFallbackHandler,
            $uiFallbackHandler
        ) {
            return $isApiRequest()
                ? $apiFallbackHandler($message, $status, $code)
                : $uiFallbackHandler($message, $status, $code);
        };

        $this->apiFallbackHandler = $apiFallbackHandler;
        $this->uiFallbackHandler  = $uiFallbackHandler;
        $this->fallbackHandler    = $fallbackHandler;

        $this->routerResponseHandler = new RouterResponseHandler(
            $this->response,
            $isApiRequest,
            $fallbackHandler,
            $apiFallbackHandler,
            $uiFallbackHandler
        );
    }

    protected function getErrorTitle(int $status): string
    {
        return match ($status) {
            401     => 'Autentikasi Diperlukan',
            403     => 'Akses Ditolak',
            404     => 'Halaman Tidak Ditemukan',
            405     => 'Metode Tidak Diizinkan',
            429     => 'Terlalu Banyak Permintaan',
            default => 'Terjadi Kesalahan',
        };
    }

    protected function handleAccessDenied(string $codeOrMessage = 'ACCESS_DENIED', ?string $customMessage = null)
    {
        return $this->routerResponseHandler->handleAccessDenied($codeOrMessage, $customMessage);
    }

    protected function handleUnauthenticated(string $codeOrMessage = 'AUTHENTICATION_REQUIRED', ?string $customMessage = null)
    {
        return $this->routerResponseHandler->handleUnauthenticated($codeOrMessage, $customMessage);
    }

    public function getDefaultController(): string
    {
        return $this->defaultController;
    }

    public function getAllowedControllers(): array
    {
        return $this->allowedControllers;
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    protected function loadRouterConfig(): void
    {
        $configFile = $this->getRouterConfigPath();

        $this->routerConfig = [];

        if (is_file($configFile)) {
            $config = require $configFile;

            if (! is_array($config)) {
                throw new RuntimeException("RouterConfig harus mengembalikan array: {$configFile}");
            }

            $this->validateRouterConfig($config, $configFile);
            $this->validateAgainstShield($config, $configFile);
            $this->routerConfig = $config;

            // Menimpa nilai modul.
            $this->allowedMethods      = $config['allowedMethods'] ?? $this->allowedMethods;
            $this->publicMethods       = $config['publicMethods'] ?? $this->publicMethods;
            $this->requiredPermissions = $config['requiredPermissions'] ?? $this->requiredPermissions;
            $this->requiredRoles       = $config['requiredRoles'] ?? $this->requiredRoles;
            $this->authApiExceptions   = $config['authApiExceptions'] ?? $this->authApiExceptions;
            $this->authRequired        = $config['authRequired'] ?? $this->authRequired;

            // Per endpoint: file menimpa modul.
            $this->allowedHttpVerbs = array_replace(
                $this->allowedHttpVerbs,
                $config['allowedHttpVerbs'] ?? []
            );

            $this->rateLimits = array_replace(
                $this->rateLimits,
                $config['rateLimits'] ?? []
            );

            // Pengecualian bersifat kumulatif.
            $this->httpVerbExclusions = array_values(array_unique(array_merge(
                $this->httpVerbExclusions,
                $config['httpVerbExclusions'] ?? []
            )));
        }

        // Status dihitung dari konfigurasi efektif, file ada ataupun tidak.
        $this->rateLimitEnabled = ! empty($this->rateLimits);
    }

    protected function validateRouterConfig(array $config, string $configFile): void
    {
        foreach (self::ARRAY_CONFIG_KEYS as $key) {
            if (array_key_exists($key, $config) && ! is_array($config[$key])) {
                throw new RuntimeException("RouterConfig['{$key}'] harus berupa array: {$configFile}");
            }
        }

        $this->assertEndpointList($config['allowedMethods'] ?? [], 'allowedMethods', $configFile);
        $this->assertEndpointList($config['httpVerbExclusions'] ?? [], 'httpVerbExclusions', $configFile);

        foreach ($config['publicMethods'] ?? [] as $endpoint) {
            if (! is_string($endpoint) || trim($endpoint) === '') {
                throw new RuntimeException("Setiap publicMethods harus berupa string endpoint: {$configFile}");
            }
        }

        $this->assertStringListMap($config['requiredPermissions'] ?? [], 'requiredPermissions', $configFile);
        $this->assertStringListMap($config['requiredRoles'] ?? [], 'requiredRoles', $configFile);

        foreach ($config['allowedHttpVerbs'] ?? [] as $endpoint => $verbs) {
            if (! is_string($endpoint) || ! is_array($verbs)) {
                throw new RuntimeException("Format allowedHttpVerbs tidak valid: {$configFile}");
            }

            foreach ($verbs as $verb) {
                if (! is_string($verb) || ! in_array(strtoupper($verb), self::HTTP_VERBS, true)) {
                    throw new RuntimeException("HTTP verb tidak valid pada {$endpoint}: {$configFile}");
                }
            }
        }

        foreach ($config['rateLimits'] ?? [] as $endpoint => $policy) {
            if (
                ! is_string($endpoint)
                || ! is_array($policy)
                || ! isset($policy['limit'], $policy['minutes'])
                || ! is_numeric($policy['limit'])
                || ! is_numeric($policy['minutes'])
                || (int) $policy['limit'] < 1
                || (int) $policy['minutes'] < 1
            ) {
                throw new RuntimeException("Konfigurasi rate limit tidak valid pada {$endpoint}: {$configFile}");
            }
        }

        foreach ($config['authApiExceptions'] ?? [] as $exception) {
            if (! is_string($exception) || trim($exception, '/') === '') {
                throw new RuntimeException("Setiap authApiExceptions harus berupa path yang valid: {$configFile}");
            }
        }

        if (array_key_exists('authRequired', $config) && ! is_bool($config['authRequired'])) {
            throw new RuntimeException("RouterConfig['authRequired'] harus berupa boolean: {$configFile}");
        }
    }

    protected function assertEndpointList(array $endpoints, string $name, string $configFile): void
    {
        foreach ($endpoints as $endpoint) {
            if (! is_string($endpoint) || ! preg_match(self::ENDPOINT_PATTERN, $endpoint)) {
                throw new RuntimeException("Format {$name} tidak valid: {$configFile}");
            }
        }
    }

    protected function assertStringListMap(array $map, string $name, string $configFile): void
    {
        foreach ($map as $endpoint => $values) {
            if (! is_string($endpoint) || trim($endpoint) === '' || ! is_array($values)) {
                throw new RuntimeException("Format {$name} tidak valid: {$configFile}");
            }

            foreach ($values as $value) {
                if (! is_string($value) || trim($value) === '') {
                    throw new RuntimeException("Nilai tidak valid pada {$name} untuk {$endpoint}: {$configFile}");
                }
            }
        }
    }

    /**
     * Cocokkan permission dan role di RouterConfig dengan konfigurasi Shield.
     * Hanya di luar production (jalankan di test/CI agar typo ketahuan).
     */
    protected function validateAgainstShield(array $config, string $configFile): void
    {
        if (ENVIRONMENT === 'production') {
            return;
        }

        $authGroups = config('AuthGroups');

        if ($authGroups === null) {
            return;
        }

        $knownPermissions = array_keys($authGroups->permissions ?? []);
        $knownGroups      = array_keys($authGroups->groups ?? []);

        foreach ($config['requiredPermissions'] ?? [] as $endpoint => $permissions) {
            foreach ($permissions as $permission) {
                if (! in_array($permission, $knownPermissions, true)) {
                    throw new RuntimeException(
                        "Permission '{$permission}' pada {$endpoint} tidak ada di AuthGroups::\$permissions: {$configFile}"
                    );
                }
            }
        }

        foreach ($config['requiredRoles'] ?? [] as $endpoint => $roles) {
            foreach ($roles as $role) {
                if (! in_array($role, $knownGroups, true)) {
                    throw new RuntimeException(
                        "Role '{$role}' pada {$endpoint} tidak ada di AuthGroups::\$groups: {$configFile}"
                    );
                }
            }
        }
    }

    protected function getRouterConfigPath(): string
    {
        if ($this->moduleFolder !== '') {
            return ROOTPATH . 'modules/' . trim($this->moduleFolder, '/\\') . '/Config/RouterConfig.php';
        }

        return APPPATH . 'Config/RouterConfig.php';
    }

    /** @deprecated Gunakan RouteRegistrySynchronizer::scanAllModules(). */
    public static function scanAllModules(): array
    {
        return RouteRegistrySynchronizer::scanAllModules();
    }

    /** @deprecated Gunakan RouteRegistrySynchronizer::syncRouteRegistry(). */
    public static function syncRouteRegistry(): int
    {
        return RouteRegistrySynchronizer::syncRouteRegistry();
    }
}
