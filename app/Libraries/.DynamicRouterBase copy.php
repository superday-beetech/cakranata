<?php

namespace App\Libraries;

use App\Libraries\DynamicRouter\AccessChecker;
use App\Libraries\DynamicRouter\AuthenticationResolver;
use App\Libraries\DynamicRouter\ControllerScanner;
use App\Libraries\DynamicRouter\HttpVerbPolicy;
use App\Libraries\DynamicRouter\MethodPolicy;
use App\Libraries\DynamicRouter\RateLimiter;
use App\Libraries\DynamicRouter\RouteResolver;
use App\Libraries\DynamicRouter\RouterResponseHandler;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use CodeIgniter\Exceptions\PageNotFoundException;
use ReflectionClass;
use ReflectionMethod;

abstract class DynamicRouterBase extends Controller
{

    protected string $moduleFolder = '';
    protected string $moduleNamespace = '';
    protected string $defaultController = '';

    /** @var bool Aktif/nonaktif module */
    protected bool $enabled = true;

    /** @var bool Wajib login? */
    protected bool $authRequired       = false;
    protected array $authApiExceptions = [];
    /** @var array Allowed controller names (tanpa namespace) */
    protected array $allowedControllers = [];

    /** @var array Allowed method names (tanpa controller) */
    protected array $allowedMethods = [];

    /** @var array method => required permission */
    protected array $requiredPermissions = [];

    /** @var array method => [get,post] */
    protected array $allowedHttpVerbs = [];

    /** @var array method => ['limit'=>10,'minutes'=>1] */
    protected array $rateLimits = [];

    protected bool $rateLimitEnabled = false;

    /** @var array List required roles (['admin','editor']) */
    protected array $requiredRoles = [];

    /** @var array alias => [Controller, method] */
    protected array $routeAliases = [];

    /** @var callable|null Fallback handler jika controller/method tidak ditemukan */
    protected $fallbackHandler = null;

    protected $apiFallbackHandler;
    protected $uiFallbackHandler;

    /** @var array Hooks sebelum route dieksekusi */
    protected array $beforeRouteHooks = [];

    /** @var array Hooks sesudah route dieksekusi */
    protected array $afterRouteHooks = [];

    protected array $whitelist = ['themes', 'assets', 'uploads'];
    /**
     * Method publik yang ditemukan saat scan.
     */
    protected array $discoveredMethods = [];
    protected array $controllerClasses = [];
    /**
     * Metadata kebijakan dari RouterConfig.
     */
    protected array $routerConfig = [];

    /**
     * Endpoint publik yang tidak membutuhkan permission.
     *
     * @var list<string>
     */
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

        // 2. Scan controller.
        $this->controllerScanner = new ControllerScanner(
            $this->moduleFolder,
            $this->moduleNamespace
        );

        $this->scanControllersAndMethods();

        // 3. Kebijakan method.
        $this->methodPolicy = new MethodPolicy(
            $this->discoveredMethods,
            $this->allowedMethods
        );

        // 4. Resolver route.
        $this->routeResolver = new RouteResolver(
            $request,
            $this->methodPolicy,
            $this->controllerClasses,
            $this->defaultController
        );

        // 5. Autentikasi.
        $this->authenticationResolver = new AuthenticationResolver($request);

        // 6. Otorisasi.
        $this->accessChecker = new AccessChecker(
            $request,
            $this->authenticationResolver,
            $this->publicMethods
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
        $result = $this->controllerScanner->scan();

        $this->applyScanResult($result);
    }

    protected function scanControllersAndMethodsNoCache(): void
    {
        $result = $this->controllerScanner->scanWithoutCache();

        $this->applyScanResult($result);
    }

    protected function applyScanResult(array $result): void
    {
        $this->allowedControllers = $result['allowedControllers'];
        $this->discoveredMethods = $result['discoveredMethods'];
        $this->controllerClasses = $result['controllerClasses'];
    }

    public function route($seg1 = '', $seg2 = null, ...$params)
    {
        // 1. Periksa status modul.
        if (! $this->enabled && ! $this->isSuperadmin()) {
            throw PageNotFoundException::forPageNotFound(
                "Module '{$this->moduleFolder}' disabled."
            );
        }

        // 2. Tentukan controller, metode, dan parameter.
        [$controller, $method, $params] = $this->resolveControllerAndMethod($seg1, $seg2, $params);

        $method = $this->resolveEffectiveMethod($controller, $method);
        // 3. Validasi controller dan metode.
        if (! in_array($controller, $this->allowedControllers, true)) {
            log_message('warning', 'DynamicRouter rejected: ' . json_encode([
                'stage'      => 'controller_policy',
                'reason'     => 'CONTROLLER_NOT_ALLOWED',
                'controller' => $controller,
            ]));
            throw PageNotFoundException::forPageNotFound();
        }

        // 4. Validasi metode dan allowlist.
        $methodDecision = $this->methodPolicy->diagnoseMethod(
            $controller,
            $method
        );

        if (! $methodDecision['allowed']) {
            log_message('warning', 'DynamicRouter rejected: ' . json_encode([
                'stage'      => 'method_policy',
                'reason'     => $methodDecision['reason'],
                'controller' => $controller,
                'method'     => $method,
            ]));

            // Pilihan: samarkan endpoint yang dibatasi sebagai 404.
            throw PageNotFoundException::forPageNotFound();
        }
        $endpoint = $controller . '::' . $method;

        // 4. Validasi HTTP verb.
        $settings = service('settings');


        $httpVerbs = $settings->get('Core.httpVerbs');

        $httpVerbExcluded = $this->isHttpVerbExcluded($endpoint);
        $shouldCheckHttpVerb = ! $httpVerbExcluded
            && ($httpVerbs === null || (bool) $httpVerbs);

        if ($shouldCheckHttpVerb && ! $this->checkHttpVerb($endpoint)) {
            log_message('warning', 'DynamicRouter rejected: ' . json_encode([
                'stage'    => 'http_verb',
                'reason'   => 'HTTP_VERB_NOT_ALLOWED',
                'endpoint' => $endpoint,
                'verb'     => strtoupper($this->request->getMethod()),
            ]));

            // Gunakan handler 405 yang sudah tersedia di aplikasi.
            // Jika belum ada, implementasikan handler 405 tersendiri.
            return $this->handleMethodNotAllowed('HTTP_VERB_NOT_ALLOWED', null, $controller);
        }

        $checkAccess = $settings->get('Core.checkAccess');

        if ($checkAccess) {
            $decision = $this->accessChecker->diagnoseAccess($endpoint);

            if (! $decision['allowed']) {
                log_message(
                    'notice',
                    'DynamicRouter access rejected: {reason}; endpoint={endpoint}; status={status}',
                    [
                        'reason'   => $decision['reason'],
                        'endpoint' => $endpoint,
                        'status'   => $decision['status'],
                    ]
                );

                return $this->routerResponseHandler
                    ->handleAccessDecision($decision);
            }
        }


        // 6. Batasi frekuensi permintaan jika diaktifkan.
        if (
            $this->rateLimitEnabled
            && ! $this->checkRateLimit($endpoint)
        ) {
            return $this->response
                ->setStatusCode(429)
                ->setBody('Too Many Requests');
        }

        // 7. Jalankan hook sebelum controller.
        foreach ($this->beforeRouteHooks as $hook) {
            if (is_callable($hook)) {
                $hook($controller, $method, $params);
            }
        }

        // 8. Jalankan controller.
        $result = $this->runController($controller, $method, $params);

        // 9. Jalankan hook setelah controller.
        foreach ($this->afterRouteHooks as $hook) {
            if (is_callable($hook)) {
                $hook($controller, $method, $params, $result);
            }
        }

        return $result;
    }


    protected function isSuperadmin(): bool
    {
        return $this->accessChecker->isSuperadmin();
    }

    protected function normalizeMethodName(
        string $method,
        string $controller
    ): string {
        return $this->methodPolicy->normalizeMethodName(
            $method,
            $controller
        );
    }

    protected function resolveDiscoveredMethod(
        string $controller,
        string $requestedMethod
    ): ?string {
        return $this->methodPolicy->resolveDiscoveredMethod(
            $controller,
            $requestedMethod
        );
    }

    protected function isMethodAllowed(
        string $controller,
        string $method
    ): bool {
        return $this->methodPolicy->isMethodAllowed(
            $controller,
            $method
        );
    }

    protected function resolveControllerAndMethod(
        $seg1,
        $seg2,
        array &$params
    ): array {
        return $this->routeResolver->resolveControllerAndMethod(
            $seg1,
            $seg2,
            $params
        );
    }

    protected function resolveEffectiveMethod(
        string $controller,
        string $method
    ): string {
        return $this->routeResolver->resolveEffectiveMethod(
            $controller,
            $method
        );
    }

    protected function resolveMethodByVerb(
        string $controller,
        string $requestedMethod
    ): ?string {
        return $this->routeResolver->resolveMethodByVerb(
            $controller,
            $requestedMethod
        );
    }

    protected function normalizeControllerName(
        string $controller
    ): string {
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

        if ($className === null || ! class_exists($className)) {
            return $this->handleNotFound(
                'NOT_FOUND',
                "Controller '{$controller}' tidak ditemukan."
            );
        }

        $resolvedMethod = $this->methodPolicy->resolveDiscoveredMethod(
            $controller,
            $method
        );

        if ($resolvedMethod === null) {
            return $this->handleNotFound(
                'NOT_FOUND',
                "Method '{$controller}::{$method}' tidak ditemukan."
            );
        }

        $method = $resolvedMethod;

        $instance = new $className();

        $instance->initController($this->request, $this->response, service('logger'));

        return $instance->{$method}(...$params);
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
        return $this->routerResponseHandler->handleMethodNotAllowed(
            $codeOrMessage,
            $customMessage,
            $prefix,
        );
    }

    /**
     * Inisialisasi default response handlers.
     *
     * Modul turunan boleh menyediakan handler sendiri.
     */
    protected function configureResponseHandlers(): void
    {
        $isApiRequest = fn(): bool => $this->isApiRequest();

        $uiFallbackHandler = function (
            string $message,
            int $status,
            string $code
        ) {
            $html = view('errors/response', [
                'status'  => $status,
                'code'    => $code,
                'title'    => $this->getErrorTitle($status),
                'message' => $message,
            ]);

            return $this->response
                ->setStatusCode($status)
                ->setContentType('text/html', 'UTF-8')
                ->setBody($html);
        };

        $apiFallbackHandler = function (
            string $message,
            int $status,
            string $code
        ) {
            return $this->response
                ->setStatusCode($status)
                ->setJSON([
                    'status'  => $status,
                    'error'   => true,
                    'code'    => $code,
                    'title'    => $this->getErrorTitle($status),
                    'message' => $message,
                ]);
        };

        $fallbackHandler = function (
            string $message,
            int $status,
            string $code
        ) use ($isApiRequest, $apiFallbackHandler, $uiFallbackHandler) {
            return $isApiRequest()
                ? $apiFallbackHandler($message, $status, $code)
                : $uiFallbackHandler($message, $status, $code);
        };

        $this->apiFallbackHandler = $apiFallbackHandler;
        $this->uiFallbackHandler = $uiFallbackHandler;
        $this->fallbackHandler = $fallbackHandler;

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
                throw new \RuntimeException(
                    "RouterConfig harus mengembalikan array: {$configFile}"
                );
            }

            $this->validateRouterConfig($config, $configFile);
            $this->routerConfig = $config;

            $this->allowedMethods = $config['allowedMethods']
                ?? $this->allowedMethods;

            $this->publicMethods = $config['publicMethods']
                ?? $this->publicMethods;

            $this->requiredPermissions = $config['requiredPermissions']
                ?? $this->requiredPermissions;

            $this->requiredRoles = $config['requiredRoles']
                ?? $this->requiredRoles;

            // File menimpa aturan modul per endpoint.
            $this->allowedHttpVerbs = array_replace(
                $this->allowedHttpVerbs,
                $config['allowedHttpVerbs'] ?? []
            );

            // Pengecualian file dan modul bersifat kumulatif.
            $this->httpVerbExclusions = array_values(array_unique(
                array_merge(
                    $this->httpVerbExclusions,
                    $config['httpVerbExclusions'] ?? []
                )
            ));

            $this->rateLimits = array_replace(
                $this->rateLimits,
                $config['rateLimits'] ?? []
            );

            $this->authApiExceptions = $config['authApiExceptions']
                ?? $this->authApiExceptions;

            $this->authRequired = $config['authRequired']
                ?? $this->authRequired;
        }

        // Hitung status berdasarkan konfigurasi efektif,
        // baik file tersedia maupun tidak.
        $this->rateLimitEnabled = ! empty($this->rateLimits);
    }


    protected function validateRouterConfig(
        array $config,
        string $configFile
    ): void {
        $arrayKeys = [
            'allowedMethods',
            'publicMethods',
            'requiredPermissions',
            'requiredRoles',
            'allowedHttpVerbs',
            'httpVerbExclusions',
            'rateLimits',
            'authApiExceptions'
        ];

        foreach ($arrayKeys as $key) {
            if (
                array_key_exists($key, $config)
                && ! is_array($config[$key])
            ) {
                throw new \RuntimeException(
                    "RouterConfig['{$key}'] harus berupa array: {$configFile}"
                );
            }
        }

        foreach ($config['allowedMethods'] ?? [] as $endpoint) {
            if (
                ! is_string($endpoint)
                || ! preg_match(
                    // '/^[A-Za-z_][A-Za-z0-9_]*::[A-Za-z_][A-Za-z0-9_]*$/',
                    '/^[A-Za-z_][A-Za-z0-9_]*::[A-Za-z_][A-Za-z0-9_]*$/',
                    $endpoint
                )
            ) {
                throw new \RuntimeException(
                    "Format allowedMethods tidak valid: {$configFile}"
                );
            }
        }

        foreach ($config['publicMethods'] ?? [] as $endpoint) {
            if (! is_string($endpoint) || trim($endpoint) === '') {
                throw new \RuntimeException(
                    "Setiap publicMethods harus berupa string endpoint: {$configFile}"
                );
            }
        }


        foreach ($config['requiredPermissions'] ?? [] as $endpoint => $permissions) {
            if (
                ! is_string($endpoint)
                || trim($endpoint) === ''
                || ! is_array($permissions)
            ) {
                throw new \RuntimeException(
                    "Format requiredPermissions tidak valid: {$configFile}"
                );
            }

            foreach ($permissions as $permission) {
                if (! is_string($permission) || trim($permission) === '') {
                    throw new \RuntimeException(
                        "Permission tidak valid pada {$endpoint}: {$configFile}"
                    );
                }
            }
        }

        foreach ($config['requiredRoles'] ?? [] as $endpoint => $roles) {
            if (
                ! is_string($endpoint)
                || trim($endpoint) === ''
                || ! is_array($roles)
            ) {
                throw new \RuntimeException(
                    "Format requiredRoles tidak valid: {$configFile}"
                );
            }

            foreach ($roles as $role) {
                if (! is_string($role) || trim($role) === '') {
                    throw new \RuntimeException(
                        "Role tidak valid pada {$endpoint}: {$configFile}"
                    );
                }
            }
        }
        foreach ($config['allowedHttpVerbs'] ?? [] as $endpoint => $verbs) {
            if (
                ! is_string($endpoint)
                || ! is_array($verbs)
            ) {
                throw new \RuntimeException(
                    "Format allowedHttpVerbs tidak valid: {$configFile}"
                );
            }

            foreach ($verbs as $verb) {
                if (
                    ! is_string($verb)
                    || ! in_array(
                        strtoupper($verb),
                        ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'],
                        true
                    )
                ) {
                    throw new \RuntimeException(
                        "HTTP verb tidak valid pada {$endpoint}: {$configFile}"
                    );
                }
            }
        }

        foreach ($config['httpVerbExclusions'] ?? [] as $endpoint) {
            if (
                ! is_string($endpoint)
                || ! preg_match(
                    '/^[A-Za-z_][A-Za-z0-9_]*::[A-Za-z_][A-Za-z0-9_]*$/',
                    $endpoint
                )
            ) {
                throw new \RuntimeException(
                    "Format httpVerbExclusions tidak valid: {$configFile}"
                );
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
                throw new \RuntimeException(
                    "Konfigurasi rate limit tidak valid pada {$endpoint}: {$configFile}"
                );
            }
        }
        foreach ($config['authApiExceptions'] ?? [] as $exception) {
            if (! is_string($exception) || trim($exception, '/') === '') {
                throw new \RuntimeException(
                    "Setiap authApiExceptions harus berupa path yang valid: {$configFile}"
                );
            }
        }
        if (
            array_key_exists('authRequired', $config)
            && ! is_bool($config['authRequired'])
        ) {
            throw new \RuntimeException(
                "RouterConfig['authRequired'] harus berupa boolean: {$configFile}"
            );
        }
    }

    protected function getRouterConfigPath(): string
    {
        if ($this->moduleFolder !== '') {
            return ROOTPATH
                . 'modules/'
                . trim($this->moduleFolder, '/\\')
                . '/Config/RouterConfig.php';
        }

        return APPPATH
            . 'Config/RouterConfig.php';
    }

    public static function syncRouteRegistry(): int
    {
        $db = \Config\Database::connect();
        $modules = self::scanAllModules();
        $count = 0;

        foreach ($modules as $module => $data) {
            foreach ($data['controllers'] as $controller => $info) {
                foreach ($info['methods'] as $method) {
                    $endpoint = $controller . '::' . $method;

                    $exists = $db->table('route_registry')
                        ->where('module', $module)
                        ->where('endpoint', $endpoint)
                        ->countAllResults();

                    if ($exists > 0) {
                        continue;
                    }

                    $db->table('route_registry')->insert([
                        'module'        => $module,
                        'controller'    => $controller,
                        'method'        => $method,
                        'endpoint'      => $endpoint,
                        'is_discovered' => 1,
                        'is_routable'   => 0,
                        'is_allowed'    => 0,
                        'scan_status'   => 'new',
                    ]);

                    $count++;
                }
            }
        }

        return $count;
    }

    public static function scanAllModules(): array
    {
        $modulesPath = ROOTPATH . 'modules';
        $result = [];

        foreach (new \DirectoryIterator($modulesPath) as $moduleDir) {
            if (
                $moduleDir->isDot()
                || ! $moduleDir->isDir()
            ) {
                continue;
            }

            $module = $moduleDir->getFilename();
            $controllerPath = $moduleDir->getPathname() . DIRECTORY_SEPARATOR . 'Controllers';

            if (! is_dir($controllerPath)) {
                continue;
            }

            $namespace = 'Modules\\' . $module . '\\Controllers';
            $controllers = [];
            $methods = [];

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $controllerPath,
                    \FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = substr(
                    $file->getPathname(),
                    strlen($controllerPath) + 1
                );

                $relativeClass = str_replace(
                    DIRECTORY_SEPARATOR,
                    '\\',
                    substr($relativePath, 0, -4)
                );

                $className = $namespace . '\\' . $relativeClass;

                if (! class_exists($className)) {
                    continue;
                }

                try {
                    $reflection = new \ReflectionClass($className);
                } catch (\ReflectionException) {
                    continue;
                }

                if (! $reflection->isInstantiable() || $reflection->isAbstract()) {
                    continue;
                }

                // Jangan anggap DynamicRouter sebagai controller endpoint.
                if ($reflection->getShortName() === 'DynamicRouter') {
                    continue;
                }

                $controller = $reflection->getShortName();

                $controllers[$controller] = [
                    'class' => $className,
                    'methods' => [],
                ];

                foreach (
                    $reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method
                ) {
                    if (
                        $method->getDeclaringClass()->getName() !== $reflection->getName()
                        || $method->isStatic()
                        || $method->isConstructor()
                        || str_starts_with($method->getName(), '_')
                    ) {
                        continue;
                    }

                    $methodName = $method->getName();
                    $controllers[$controller]['methods'][] = $methodName;
                    $methods[] = $controller . '::' . $methodName;
                }
            }

            $result[$module] = [
                // 'controller_count' => count($controllers),
                'method_count' => count($methods),
                'controllers' => $controllers,
                'discovered_methods' => $methods,
            ];
        }

        return $result;
    }
}
